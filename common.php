<?php

require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Logger.php';

$settings = [];

pcntl_async_signals(true);

pcntl_signal(SIGINT, 'signalHandler');
pcntl_signal(SIGTERM, 'signalHandler');
pcntl_signal(SIGHUP, 'signalHandler');

function signalHandler(int $signal)
{
    Logger::log('signal', "Caught signal: $signal");
    $parentPid = posix_getppid();

    $parentCommand = trim(exec("ps -p $parentPid -o comm= 2>/dev/null"));

    if ($parentCommand !== 'bash') {
        checkSystemStatus();
    }
    exit;
}

function checkSystemStatus()
{
    $systemStatus = exec('systemctl is-system-running');

    if (in_array($systemStatus, ['running', 'degrading'])) {
        Logger::log('system_status', "Service was stopped manually by the user, system_status: $systemStatus.");
        // exec('shutdown -h now');
    } else {
        Logger::log('system_status', "Service is stopping due to system shutdown or reboot, system_status: $systemStatus.");
    }
}
