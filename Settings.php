<?php

class Settings
{
    public static string $settingsPath = __DIR__ . '/settings.json';

    public static function loadSettings()
    {
        $settingsPath = static::$settingsPath;
        $settings = json_decode(file_get_contents($settingsPath), true);
        return $settings ?? [];
    }
}
