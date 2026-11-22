<?php
/**
 * Verbose AI - Secure Authentication & Session Service
 * Features:
 * - Cryptographically signed HMAC-SHA256 session tokens (tamper-proof)
 * - Strict cookie security flags (HttpOnly, Secure, SameSite=Lax)
 * - Brute-force rate limiting by IP (max 5 failed attempts per 10 minutes)
 * - Strong password hashing (BCRYPT with standard cost)
 * - Input validation & strict error handling (zero bypasses)
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Config.php';

class AuthService {
    const COOKIE_NAME = 'verbose_session';
    const SESSION_LIFETIME = 2592000; // 30 days
    const MAX_FAILED_ATTEMPTS = 5;
    const LOCKOUT_WINDOW = 600; // 10 minutes

    /**
     * Get secret key for HMAC session signing
     */
    private static function getSecret(): string {
        $secret = Config::get('SESSION_SECRET');
        if (!empty($secret)) {
            return $secret;
        }
        $dbUrl = Config::get('DATABASE_URL') ?? 'verbose_fallback_secret_key';
        return hash('sha256', 'verbose_hmac_secret_' . $dbUrl);
    }

    /**
     * Sign and issue a secure session cookie
     */
    public static function setSession(string $email): string {
        $secret = self::getSecret();
        $payload = [
            'email' => strtolower(trim($email)),
            'iat' => time(),
            'exp' => time() + self::SESSION_LIFETIME,
            'nonce' => bin2hex(random_bytes(16))
        ];
        $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $payloadEncoded, $secret);
        $token = $payloadEncoded . '.' . $signature;

        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

        setcookie(self::COOKIE_NAME, $token, [
            'expires' => time() + self::SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        $_COOKIE[self::COOKIE_NAME] = $token;
        return $token;
    }

    /**
     * Verify and parse signed session token from cookie
     * Returns verified user email or null if invalid / expired / forged
     */
    public static function getCurrentUser(): ?string {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (empty($cookie) || !is_string($cookie)) {
            return null;
        }

        $parts = explode('.', $cookie);
        if (count($parts) !== 2) {
            return null;
        }

        list($payloadEncoded, $signature) = $parts;
        $secret = self::getSecret();
        $expectedSignature = hash_hmac('sha256', $payloadEncoded, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $json = base64_decode(strtr($payloadEncoded, '-_', '+/'));
        if (!$json) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['email']) || empty($data['exp'])) {
            return null;
        }

        if (time() > $data['exp']) {
            self::clearSession();
            return null;
        }

        return $data['email'];
    }

    /**
     * Invalidate session and expire cookie
     */
    public static function clearSession(): void {
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 86400,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        unset($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Client IP address extraction
     */
    private static function getClientIp(): string {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Check if client IP is currently rate-limited
     */
    public static function isRateLimited(): bool {
        $ip = self::getClientIp();
        $cacheFile = sys_get_temp_dir() . '/verbose_ratelimit_' . md5($ip) . '.json';
        if (!file_exists($cacheFile)) {
            return false;
        }
        $data = json_decode(@file_get_contents($cacheFile), true);
        if (!$data || !is_array($data)) {
            return false;
        }
        if (time() - ($data['last_attempt'] ?? 0) > self::LOCKOUT_WINDOW) {
            @unlink($cacheFile);
            return false;
        }
        return ($data['failures'] ?? 0) >= self::MAX_FAILED_ATTEMPTS;
    }

    /**
     * Record a failed authentication attempt
     */
    public static function recordFailedAttempt(): void {
        $ip = self::getClientIp();
        $cacheFile = sys_get_temp_dir() . '/verbose_ratelimit_' . md5($ip) . '.json';
        $data = ['failures' => 0, 'last_attempt' => time()];
        if (file_exists($cacheFile)) {
            $existing = json_decode(@file_get_contents($cacheFile), true);
            if (is_array($existing) && (time() - ($existing['last_attempt'] ?? 0)) < self::LOCKOUT_WINDOW) {
                $data['failures'] = ($existing['failures'] ?? 0);
            }
        }
        $data['failures']++;
        $data['last_attempt'] = time();
        @file_put_contents($cacheFile, json_encode($data));
    }

    /**
     * Clear failed attempts on successful login
     */
    public static function clearFailedAttempts(): void {
        $ip = self::getClientIp();
        $cacheFile = sys_get_temp_dir() . '/verbose_ratelimit_' . md5($ip) . '.json';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    /**
     * Authenticate or register a user safely
     */
    public static function handleAuth(string $email, string $password, string $mode = 'signin'): array {
        $email = strtolower(trim($email));
        $password = (string)$password;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please enter a valid email address.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters long.'];
        }

        if (self::isRateLimited()) {
            return ['success' => false, 'error' => 'Too many failed login attempts. Please wait 10 minutes.'];
        }

        $pdo = Database::getConnection();
        if (!$pdo) {
            return ['success' => false, 'error' => 'Database service currently unavailable. Please try again.'];
        }

        try {
            if ($mode === 'signup') {
                // Ensure users table exists
                $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                    id SERIAL PRIMARY KEY,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
                );");

                // Check if user already exists
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $checkStmt->execute([$email]);
                if ($checkStmt->fetch()) {
                    return ['success' => false, 'error' => 'An account with this email already exists. Please sign in.'];
                }

                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?) RETURNING id, email");
                $stmt->execute([$email, $hash]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                self::clearFailedAttempts();
                self::setSession($email);
                return ['success' => true, 'user' => $user];
            } else {
                // Sign in
                $stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password_hash'])) {
                    self::clearFailedAttempts();
                    self::setSession($email);
                    unset($user['password_hash']);
                    return ['success' => true, 'user' => $user];
                } else {
                    self::recordFailedAttempt();
                    return ['success' => false, 'error' => 'Invalid email or password.'];
                }
            }
        } catch (Exception $e) {
            error_log("Auth exception: " . $e->getMessage());
            return ['success' => false, 'error' => 'Authentication failed due to a system error.'];
        }
    }
}
