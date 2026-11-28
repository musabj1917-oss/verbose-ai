<?php
/**
 * Verbose AI - Neon Postgres PDO Wrapper with pgvector support
 */

require_once __DIR__ . '/Config.php';

class Database {
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo !== null) {
            try {
                self::$pdo->query('SELECT 1');
                return self::$pdo;
            } catch (Exception $e) {
                self::$pdo = null;
            }
        }

        $databaseUrl = Config::get('DATABASE_URL');
        if (!$databaseUrl) {
            $host = Config::get('DB_HOST', 'localhost');
            $port = Config::get('DB_PORT', '5432');
            $dbname = Config::get('DB_NAME', 'verbose_db');
            $user = Config::get('DB_USER', 'postgres');
            $pass = Config::get('DB_PASS', '');
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
        } else {
            $dbparts = parse_url($databaseUrl);
            $host = $dbparts['host'] ?? 'localhost';
            $port = $dbparts['port'] ?? '5432';
            $dbname = ltrim($dbparts['path'] ?? 'verbose_db', '/');
            $user = $dbparts['user'] ?? '';
            $pass = $dbparts['pass'] ?? '';
            // If host has -pooler, verify DNS or fallback to direct endpoint
            if (strpos($host, '-pooler.') !== false) {
                if (gethostbyname($host) === $host) {
                    $host = str_replace('-pooler.', '.', $host);
                }
            }
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 20
                ]);
                self::$pdo->exec("CREATE EXTENSION IF NOT EXISTS vector;");
                return self::$pdo;
            } catch (Exception $e) {
                if ($attempt === 3) {
                    error_log("Database connection error: " . $e->getMessage());
                    return null;
                }
                sleep(2);
            }
        }
        return null;
    }
}
