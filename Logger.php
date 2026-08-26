<?php

require_once __DIR__ . '/Settings.php';

class Logger
{
    public static function logFilePath()
    {
        return dirname(__FILE__) . '/work_log.txt';
    }

    public static function log(string $variableName, array|string $value, bool $force = false)
    {

        $settings = Settings::loadSettings();

        if (!($settings['enable_logging'] ?? false) && !$force) {
            return;
        }

        $output = (is_array($value) ? json_encode($value) : (string)$value);
        file_put_contents(static::logFilePath(), "\n\n" . date('Y-m-d H:i:s') . "\n$$variableName:\n$output", FILE_APPEND);
    }
}