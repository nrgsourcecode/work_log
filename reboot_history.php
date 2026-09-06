<?php

$lastReboot = [];
$rebootHistory = [];
require_once __DIR__ . '/common.php';

$settings = Settings::loadSettings();
$workdayStart = $settings['workdayStart'] ?? '05:00';

exec('last reboot', $lastReboot);

foreach ($lastReboot as $line) {

    if (empty($line)) {
        break;
    }

    $columns = explode(' ', preg_replace('/\s+/', ' ', $line));
    $date = $columns[4] . ' ' . $columns[5] . ' ' . $columns[6];
    $time = $columns[7];

    if ($time < $workdayStart) {
        continue;
    }

    $rebootHistory[$date] = "$date $time";
}

$rebootHistory = array_reverse(array_values($rebootHistory));
echo implode("\n", $rebootHistory) . "\n";
