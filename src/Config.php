<?php
/**
 * Verbose AI - Configuration Loader
 */

class Config {
    private static $env = [];

    public static function load($path = __DIR__ . '/../.env') {
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    self::$env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
                }
            }
        }
    }

    public static function get($key, $default = null) {
        if (isset(self::$env[$key]) && self::$env[$key] !== '') {
            return self::$env[$key];
        }
        $envVal = getenv($key);
        if ($envVal !== false && $envVal !== '') {
            return $envVal;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        return $default;
    }
}

Config::load();
