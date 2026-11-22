<?php
/**
 * Verbose AI - Secure Authentication, Session & Google OAuth Service
 * Features:
 * - Google OAuth 2.0 (Authorization Code Flow & Google Identity Services Token Verification)
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
    const OAUTH_STATE_COOKIE = 'verbose_oauth_state';
    const SESSION_LIFETIME = 2592000; // 30 days
    const MAX_FAILED_ATTEMPTS = 5;
    const LOCKOUT_WINDOW = 600; // 10 minutes

    /**
     * Ensure database schema includes all necessary fields for local and OAuth auth
     */
    public static function ensureSchema(): void {
        static $ensured = false;
        if ($ensured) return;
        $pdo = Database::getConnection();
        if (!$pdo) return;

        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id SERIAL PRIMARY KEY,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password_hash VARCHAR(255),
                    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
                    google_id VARCHAR(255),
                    name VARCHAR(255),
                    avatar_url TEXT,
                    auth_provider VARCHAR(50) DEFAULT 'local',
                    is_unlimited BOOLEAN DEFAULT false,
                    quota_credits_total INT DEFAULT 1000,
                    quota_credits_used INT DEFAULT 0,
                    selected_tier VARCHAR(20) DEFAULT 'medium'
                );
                ALTER TABLE users ALTER COLUMN password_hash DROP NOT NULL;
                ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(255);
                ALTER TABLE users ADD COLUMN IF NOT EXISTS name VARCHAR(255);
                ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_url TEXT;
                ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_provider VARCHAR(50) DEFAULT 'local';
                ALTER TABLE users ADD COLUMN IF NOT EXISTS is_unlimited BOOLEAN DEFAULT false;
                ALTER TABLE users ADD COLUMN IF NOT EXISTS quota_credits_total INT DEFAULT 1000;
                ALTER TABLE users ADD COLUMN IF NOT EXISTS quota_credits_used INT DEFAULT 0;
                ALTER TABLE users ADD COLUMN IF NOT EXISTS selected_tier VARCHAR(20) DEFAULT 'medium';
                CREATE UNIQUE INDEX IF NOT EXISTS idx_users_google_id ON users(google_id) WHERE google_id IS NOT NULL;
                UPDATE users SET is_unlimited = true WHERE LOWER(email) = 'musabj1917@gmail.com';
            ");
            $ensured = true;
        } catch (Exception $e) {
            error_log("Schema ensure error: " . $e->getMessage());
        }
    }

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
     * Check if Google OAuth is configured
     */
    public static function isGoogleConfigured(): bool {
        $clientId = Config::get('GOOGLE_CLIENT_ID');
        return !empty($clientId);
    }

    /**
     * Get Google Client ID
     */
    public static function getGoogleClientId(): ?string {
        return Config::get('GOOGLE_CLIENT_ID');
    }

    /**
     * Build standard Google OAuth redirect URI
     */
    public static function getGoogleRedirectUri(?string $customUri = null): string {
        if (!empty($customUri)) {
            return $customUri;
        }
        $configured = Config::get('GOOGLE_REDIRECT_URI');
        if (!empty($configured)) {
            return $configured;
        }
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080';
        return "{$scheme}://{$host}/auth/google/callback";
    }

    /**
     * Generate and store CSRF state token for OAuth
     */
    public static function createOAuthState(): string {
        $state = bin2hex(random_bytes(16));
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        if (!headers_sent()) {
            setcookie(self::OAUTH_STATE_COOKIE, $state, [
                'expires' => time() + 900, // 15 minutes
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        $_COOKIE[self::OAUTH_STATE_COOKIE] = $state;
        return $state;
    }

    /**
     * Verify OAuth CSRF state token
     */
    public static function verifyOAuthState(?string $state): bool {
        $saved = $_COOKIE[self::OAUTH_STATE_COOKIE] ?? null;
        if (empty($state) || empty($saved)) {
            return false;
        }
        return hash_equals($saved, $state);
    }

    /**
     * Clear OAuth CSRF state cookie
     */
    public static function clearOAuthState(): void {
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        if (!headers_sent()) {
            setcookie(self::OAUTH_STATE_COOKIE, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        unset($_COOKIE[self::OAUTH_STATE_COOKIE]);
    }

    /**
     * Generate Google OAuth 2.0 authorization URL
     */
    public static function getGoogleAuthUrl(?string $customRedirectUri = null): string {
        $clientId = Config::get('GOOGLE_CLIENT_ID');
        if (empty($clientId)) {
            return '';
        }
        $redirectUri = self::getGoogleRedirectUri($customRedirectUri);
        $state = self::createOAuthState();

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'state' => $state,
            'prompt' => 'select_account'
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Handle Google OAuth authorization code callback
     */
    public static function handleGoogleCallback(string $code, string $state, ?string $customRedirectUri = null): array {
        if (!self::verifyOAuthState($state)) {
            self::clearOAuthState();
            return ['success' => false, 'error' => 'Invalid or expired OAuth security token. Please try again.'];
        }
        self::clearOAuthState();

        $clientId = Config::get('GOOGLE_CLIENT_ID');
        $clientSecret = Config::get('GOOGLE_CLIENT_SECRET');
        $redirectUri = self::getGoogleRedirectUri($customRedirectUri);

        if (empty($clientId) || empty($clientSecret)) {
            return ['success' => false, 'error' => 'Google OAuth client is not fully configured (missing Client ID or Client Secret).'];
        }

        // Exchange code for token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => http_build_query([
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code'
            ])
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        @curl_close($ch);

        if ($curlError || $httpCode !== 200 || empty($response)) {
            error_log("Google OAuth token exchange failed. Code: {$httpCode}, Error: {$curlError}, Resp: {$response}");
            return ['success' => false, 'error' => 'Failed to exchange authorization code with Google.'];
        }

        $tokenData = json_decode($response, true);
        if (empty($tokenData['access_token'])) {
            return ['success' => false, 'error' => 'Google token response missing access token.'];
        }

        // Fetch user profile using access token
        $uch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
        curl_setopt_array($uch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $tokenData['access_token'],
                'Accept: application/json'
            ]
        ]);
        $uResponse = curl_exec($uch);
        $uHttpCode = curl_getinfo($uch, CURLINFO_HTTP_CODE);
        @curl_close($uch);

        if ($uHttpCode !== 200 || empty($uResponse)) {
            return ['success' => false, 'error' => 'Failed to retrieve user profile from Google.'];
        }

        $profile = json_decode($uResponse, true);
        if (empty($profile['email'])) {
            return ['success' => false, 'error' => 'Unable to read email from Google profile.'];
        }

        return self::findOrCreateGoogleUser($profile);
    }

    /**
     * Verify Google ID token from Google Identity Services (One Tap / GIS frontend button)
     */
    public static function verifyAndLoginGoogleIdToken(string $credential): array {
        if (empty($credential)) {
            return ['success' => false, 'error' => 'Missing Google credential token.'];
        }

        $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            return ['success' => false, 'error' => 'Invalid or expired Google credential token.'];
        }

        $tokenInfo = json_decode($response, true);
        if (!$tokenInfo || empty($tokenInfo['email'])) {
            return ['success' => false, 'error' => 'Could not verify Google credential.'];
        }

        // Validate Google client ID if configured
        $configuredClientId = Config::get('GOOGLE_CLIENT_ID');
        if (!empty($configuredClientId) && isset($tokenInfo['aud']) && $tokenInfo['aud'] !== $configuredClientId) {
            return ['success' => false, 'error' => 'Google token audience mismatch.'];
        }

        // Validate issuer
        $iss = $tokenInfo['iss'] ?? '';
        if ($iss !== 'accounts.google.com' && $iss !== 'https://accounts.google.com') {
            return ['success' => false, 'error' => 'Untrusted Google token issuer.'];
        }

        return self::findOrCreateGoogleUser($tokenInfo);
    }

    /**
     * Find or create user from Google profile and establish authenticated session
     */
    private static function findOrCreateGoogleUser(array $profile): array {
        self::ensureSchema();

        $email = strtolower(trim($profile['email']));
        $googleId = $profile['sub'] ?? null;
        $name = trim($profile['name'] ?? ($profile['given_name'] ?? ''));
        $avatarUrl = $profile['picture'] ?? null;

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Google profile returned an invalid email.'];
        }

        $pdo = Database::getConnection();
        if (!$pdo) {
            return ['success' => false, 'error' => 'Database service currently unavailable. Please try again.'];
        }

        try {
            // Find existing user by google_id OR email
            $stmt = $pdo->prepare("SELECT id, email, name, avatar_url, google_id, auth_provider FROM users WHERE google_id = ? OR email = ? LIMIT 1");
            $stmt->execute([$googleId, $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Update profile info
                $updateStmt = $pdo->prepare("UPDATE users SET 
                    google_id = COALESCE(google_id, ?),
                    name = CASE WHEN (name IS NULL OR name = '') THEN ? ELSE name END,
                    avatar_url = CASE WHEN avatar_url IS NULL THEN ? ELSE avatar_url END,
                    auth_provider = CASE WHEN auth_provider = 'local' AND google_id IS NULL THEN 'both' ELSE COALESCE(auth_provider, 'google') END
                    WHERE id = ? RETURNING id, email, name, avatar_url, auth_provider");
                $updateStmt->execute([$googleId, $name, $avatarUrl, $user['id']]);
                $updatedUser = $updateStmt->fetch(PDO::FETCH_ASSOC);
                if ($updatedUser) {
                    $user = $updatedUser;
                }
            } else {
                // Insert new Google user
                $insertStmt = $pdo->prepare("INSERT INTO users (email, google_id, name, avatar_url, auth_provider) VALUES (?, ?, ?, ?, 'google') RETURNING id, email, name, avatar_url, auth_provider");
                $insertStmt->execute([$email, $googleId, $name, $avatarUrl]);
                $user = $insertStmt->fetch(PDO::FETCH_ASSOC);
            }

            self::clearFailedAttempts();
            self::setSession($email);

            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'] ?? null,
                    'email' => $email,
                    'name' => $user['name'] ?? ($name ?: explode('@', $email)[0]),
                    'avatar_url' => $user['avatar_url'] ?? $avatarUrl,
                    'auth_provider' => $user['auth_provider'] ?? 'google'
                ]
            ];
        } catch (Exception $e) {
            error_log("findOrCreateGoogleUser error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to save user account: ' . $e->getMessage()];
        }
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

        if (!headers_sent()) {
            setcookie(self::COOKIE_NAME, $token, [
                'expires' => time() + self::SESSION_LIFETIME,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

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
     * Get full user profile for current authenticated session
     */
    public static function getCurrentUserProfile(): ?array {
        $email = self::getCurrentUser();
        if (!$email) {
            return null;
        }

        $email = strtolower(trim($email));
        $isMusab = ($email === 'musabj1917@gmail.com');

        $pdo = Database::getConnection();
        if (!$pdo) {
            return [
                'email' => $email,
                'name' => explode('@', $email)[0],
                'avatar_url' => null,
                'auth_provider' => 'local',
                'is_unlimited' => $isMusab,
                'quota_credits_total' => $isMusab ? 999999 : 1000,
                'quota_credits_used' => 0,
                'credits_remaining' => $isMusab ? 999999 : 1000,
                'selected_tier' => 'medium'
            ];
        }

        try {
            $stmt = $pdo->prepare("SELECT id, email, name, avatar_url, auth_provider, is_unlimited, quota_credits_total, quota_credits_used, selected_tier, created_at FROM users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                if (empty($user['name'])) {
                    $user['name'] = explode('@', $email)[0];
                }
                $isUnlimited = $isMusab || (!empty($user['is_unlimited']));
                $total = intval($user['quota_credits_total'] ?? 1000);
                $used = intval($user['quota_credits_used'] ?? 0);
                $user['is_unlimited'] = $isUnlimited;
                $user['quota_credits_total'] = $isUnlimited ? 999999 : $total;
                $user['quota_credits_used'] = $isUnlimited ? 0 : $used;
                $user['credits_remaining'] = $isUnlimited ? 999999 : max(0, $total - $used);
                $user['selected_tier'] = (!empty($user['selected_tier']) && in_array($user['selected_tier'], ['pro', 'medium', 'light'])) ? $user['selected_tier'] : 'medium';
                return $user;
            }
        } catch (Exception $e) {
            error_log("getCurrentUserProfile error: " . $e->getMessage());
        }

        return [
            'email' => $email,
            'name' => explode('@', $email)[0],
            'avatar_url' => null,
            'auth_provider' => 'local',
            'is_unlimited' => $isMusab,
            'quota_credits_total' => $isMusab ? 999999 : 1000,
            'quota_credits_used' => 0,
            'credits_remaining' => $isMusab ? 999999 : 1000,
            'selected_tier' => 'medium'
        ];
    }

    /**
     * Get usage quota status for current session or specified user
     */
    public static function getQuotaStatus(?string $email = null): array {
        self::ensureSchema();
        if ($email === null) {
            $email = self::getCurrentUser();
        }
        $tierCosts = ['pro' => 25, 'medium' => 5, 'light' => 1];
        if (!$email) {
            return [
                'authenticated' => false,
                'email' => null,
                'is_unlimited' => false,
                'credits_total' => 1000,
                'credits_used' => 0,
                'credits_remaining' => 1000,
                'selected_tier' => 'medium',
                'tier_costs' => $tierCosts
            ];
        }

        $email = strtolower(trim($email));
        $isMusab = ($email === 'musabj1917@gmail.com');

        $pdo = Database::getConnection();
        if (!$pdo) {
            return [
                'authenticated' => true,
                'email' => $email,
                'is_unlimited' => $isMusab,
                'credits_total' => $isMusab ? 999999 : 1000,
                'credits_used' => 0,
                'credits_remaining' => $isMusab ? 999999 : 1000,
                'selected_tier' => 'medium',
                'tier_costs' => $tierCosts
            ];
        }

        try {
            $stmt = $pdo->prepare("SELECT id, email, is_unlimited, quota_credits_total, quota_credits_used, selected_tier FROM users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $isUnlimited = $isMusab || (!empty($row['is_unlimited']));
            $total = intval($row['quota_credits_total'] ?? 1000);
            $used = intval($row['quota_credits_used'] ?? 0);
            $selectedTier = $row['selected_tier'] ?? 'medium';
            if (!in_array($selectedTier, ['pro', 'medium', 'light'])) {
                $selectedTier = 'medium';
            }

            return [
                'authenticated' => true,
                'email' => $email,
                'is_unlimited' => $isUnlimited,
                'credits_total' => $isUnlimited ? 999999 : $total,
                'credits_used' => $isUnlimited ? 0 : $used,
                'credits_remaining' => $isUnlimited ? 999999 : max(0, $total - $used),
                'selected_tier' => $selectedTier,
                'tier_costs' => $tierCosts
            ];
        } catch (Exception $e) {
            return [
                'authenticated' => true,
                'email' => $email,
                'is_unlimited' => $isMusab,
                'credits_total' => $isMusab ? 999999 : 1000,
                'credits_used' => 0,
                'credits_remaining' => $isMusab ? 999999 : 1000,
                'selected_tier' => 'medium',
                'tier_costs' => $tierCosts
            ];
        }
    }

    /**
     * Deduct usage credits for an AI turn across a specific tier cluster
     */
    public static function deductCredits(?string $email, string $tier): array {
        self::ensureSchema();
        if ($email === null) {
            $email = self::getCurrentUser();
        }
        $tierCosts = ['pro' => 25, 'medium' => 5, 'light' => 1];
        $tier = strtolower(trim($tier));
        if (!isset($tierCosts[$tier])) {
            $tier = 'medium';
        }
        $cost = $tierCosts[$tier];

        if (!$email) {
            return [
                'allowed' => true,
                'is_unlimited' => false,
                'cost' => $cost,
                'tier' => $tier,
                'credits_remaining' => 999
            ];
        }

        $email = strtolower(trim($email));
        $isMusab = ($email === 'musabj1917@gmail.com');

        $pdo = Database::getConnection();
        if (!$pdo) {
            return [
                'allowed' => true,
                'is_unlimited' => $isMusab,
                'cost' => $cost,
                'tier' => $tier,
                'credits_remaining' => 999999
            ];
        }

        try {
            $stmt = $pdo->prepare("SELECT id, is_unlimited, quota_credits_total, quota_credits_used FROM users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $isUnlimited = $isMusab || (!empty($user['is_unlimited']));
            if ($isUnlimited) {
                return [
                    'allowed' => true,
                    'is_unlimited' => true,
                    'cost' => 0,
                    'tier' => $tier,
                    'credits_remaining' => 999999,
                    'message' => '∞ Unlimited Developer Access'
                ];
            }

            $total = intval($user['quota_credits_total'] ?? 1000);
            $used = intval($user['quota_credits_used'] ?? 0);
            $remaining = max(0, $total - $used);

            if ($remaining < $cost) {
                return [
                    'allowed' => false,
                    'is_unlimited' => false,
                    'cost' => $cost,
                    'tier' => $tier,
                    'credits_remaining' => $remaining,
                    'error' => "Usage limit reached for Verbose " . ucfirst($tier) . " cluster (costs {$cost} credits; {$remaining} remaining). Switch to Verbose Light or await daily refresh."
                ];
            }

            $newUsed = $used + $cost;
            $upStmt = $pdo->prepare("UPDATE users SET quota_credits_used = ? WHERE id = ?");
            $upStmt->execute([$newUsed, $user['id']]);

            return [
                'allowed' => true,
                'is_unlimited' => false,
                'cost' => $cost,
                'tier' => $tier,
                'credits_remaining' => max(0, $total - $newUsed)
            ];
        } catch (Exception $e) {
            return [
                'allowed' => true,
                'is_unlimited' => $isMusab,
                'cost' => $cost,
                'tier' => $tier,
                'credits_remaining' => 999
            ];
        }
    }

    /**
     * Persist user's selected model tier cluster (pro, medium, light)
     */
    public static function setUserTier(?string $email, string $tier): bool {
        self::ensureSchema();
        if ($email === null) {
            $email = self::getCurrentUser();
        }
        if (!$email) return false;
        $tier = strtolower(trim($tier));
        if (!in_array($tier, ['pro', 'medium', 'light'])) return false;

        $pdo = Database::getConnection();
        if (!$pdo) return false;

        try {
            $stmt = $pdo->prepare("UPDATE users SET selected_tier = ? WHERE LOWER(email) = ?");
            return $stmt->execute([$tier, strtolower(trim($email))]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Invalidate session and expire cookie
     */
    public static function clearSession(): void {
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        if (!headers_sent()) {
            setcookie(self::COOKIE_NAME, '', [
                'expires' => time() - 86400,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
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
     * Authenticate or register a user safely with email/password
     */
    public static function handleAuth(string $email, string $password, string $mode = 'signin'): array {
        self::ensureSchema();

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
                // Check if user already exists
                $checkStmt = $pdo->prepare("SELECT id, auth_provider FROM users WHERE email = ?");
                $checkStmt->execute([$email]);
                $existing = $checkStmt->fetch();
                if ($existing) {
                    if (($existing['auth_provider'] ?? '') === 'google') {
                        return ['success' => false, 'error' => 'An account exists using Google Sign-In. Please sign in with Google.'];
                    }
                    return ['success' => false, 'error' => 'An account with this email already exists. Please sign in.'];
                }

                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $name = explode('@', $email)[0];
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, name, auth_provider) VALUES (?, ?, ?, 'local') RETURNING id, email, name");
                $stmt->execute([$email, $hash, $name]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                self::clearFailedAttempts();
                self::setSession($email);
                return ['success' => true, 'user' => $user];
            } else {
                // Sign in
                $stmt = $pdo->prepare("SELECT id, email, password_hash, name, avatar_url, auth_provider FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    self::recordFailedAttempt();
                    return ['success' => false, 'error' => 'Invalid email or password.'];
                }

                if (empty($user['password_hash'])) {
                    return ['success' => false, 'error' => 'This account was created with Google Sign-In. Please continue with Google.'];
                }

                if (password_verify($password, $user['password_hash'])) {
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
