<?php

class Settings
{
    public static function loadSettings()
    {
        $settings_path = __DIR__ . '/settings.json';
        $settings = json_decode(file_get_contents($settings_path), true);
        return $settings;
    }
}