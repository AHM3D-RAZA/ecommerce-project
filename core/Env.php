<?php

/**
 * Tiny .env reader so secrets (API keys etc.) live outside the code.
 * Reads KEY=VALUE lines from the project root's .env file. A real
 * server environment variable, if set, wins over the file.
 */
class Env
{
    private static $vars = null;

    public static function load($file = null)
    {
        if (self::$vars !== null) {
            return;
        }

        self::$vars = [];
        $file = $file ?: dirname(__DIR__) . '/.env';

        if (!is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if (!empty($lines)) {
            // Some Windows editors put an invisible BOM at the start of the file
            $lines[0] = preg_replace('/^\xEF\xBB\xBF/', '', $lines[0]);
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            $quote = $value[0] ?? '';
            if (($quote === '"' || $quote === "'") && strlen($value) >= 2 && substr($value, -1) === $quote) {
                $value = substr($value, 1, -1);
            }

            self::$vars[$key] = $value;
        }
    }

    public static function get($key, $default = null)
    {
        self::load();

        $fromServer = getenv($key);
        if ($fromServer !== false && $fromServer !== '') {
            return $fromServer;
        }

        if (isset(self::$vars[$key]) && self::$vars[$key] !== '') {
            return self::$vars[$key];
        }

        return $default;
    }

    public static function bool($key, $default = false)
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}
