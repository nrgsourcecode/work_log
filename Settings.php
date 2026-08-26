<?php

class Settings
{
    public static string $settings_path = __DIR__ . '/settings.json';

    public static function loadSettings()
    {
        $settings_path = static::$settings_path;
        $settings = json_decode(file_get_contents($settings_path), true);
        return $settings;
    }
}
