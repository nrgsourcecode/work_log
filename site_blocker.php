<?php

register_shutdown_function('check_shutdown_type');
pcntl_signal(SIGINT, 'signal_handler');
pcntl_signal(SIGTERM, 'signal_handler');
pcntl_signal(SIGHUP, 'signal_handler');

function signal_handler($signal) {
    switch ($signal) {
        case SIGINT:
        case SIGTERM:
        case SIGHUP:
            echo "Caught signal: $signal" . PHP_EOL;
            check_shutdown_type();
            exit;
    }
}

while (true) {

    $cycle_start_time = microtime(true);

    // Read settings
    $settings_path = __DIR__ . '/settings.json';
    $settings = json_decode(file_get_contents($settings_path), true);
    extract($settings);

    if (!is_array($blocked_websites)) {
        $blocked_websites = [];
    }

    $always_blocked = ['chess.com', 'lichess.org'];
    $blocked_websites = array_merge($blocked_websites, $always_blocked);
    $blocked_websites = array_unique($blocked_websites);

    check_hosts($blocked_websites);

    sleep($refresh_interval);
    pcntl_signal_dispatch();
}

function check_shutdown_type() {
    $output = shell_exec('runlevel');
    
    if ($output) {
        $runlevel = trim(explode(' ', $output)[1]);
        
        if (in_array($runlevel, ['0', '1', '6'])) {
            echo "Service is stopping due to system shutdown or reboot." . PHP_EOL;
        } else {
            echo "Service was stopped manually by the user." . PHP_EOL;
            shell_exec('shutdown -h now');
        }
    } else {
        echo "Unable to determine shutdown type." . PHP_EOL;
    }
}

function check_hosts($websites) {

    $hosts_file = '/etc/hosts';

    $hosts_contents = file_get_contents($hosts_file);

    $block_contents = "\n# BLOCK MANAGED BY WORK_LOG\n# EVERYTHING BELOW THIS BLOCK WILL BE DELETED\n";
    $first_line_start = strpos($hosts_contents, $block_contents);

    foreach($websites as $website) {
        $block_contents.= build_hosts_line($website);
    }

    $block_contents .= "\n# END BLOCK";
    $block_start = strpos($hosts_contents, $block_contents);
    if ($block_start !== false) {
        return;
    }

    if ($first_line_start === false) {
        $hosts_contents .= $block_contents;
    } else {
        $hosts_contents = substr($hosts_contents, 0, $first_line_start) . $block_contents;
    }

    file_put_contents($hosts_file, $hosts_contents);
}

function build_hosts_line($website, $add_www = true) {
    $result = "\n127.0.0.1\t" . $website;
    if ($add_www) {
        $result .= "\n127.0.0.1\twww." . $website;
    }
    return $result;
}