<?php

$last_reboot = [];
$reboot_history = [];

exec('last reboot', $last_reboot);

foreach ($last_reboot as $line) {

    if (empty($line)) {
        break;
    }

    $columns = explode(' ', preg_replace('/\s+/', ' ', $line));
    $date = $columns[4] . ' ' . $columns[5] . ' ' . $columns[6];
    $time = $columns[7];

    $reboot_history[$date] = "$date $time";
}

echo implode("\n", $reboot_history) . "\n";