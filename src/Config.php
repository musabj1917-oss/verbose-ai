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
        if (isset(self::$env[$key])) {
            return self::$env[$key];
        }
        $envVal = getenv($key);
        return ($envVal !== false) ? $envVal : $default;
    }
}

Config::load();
