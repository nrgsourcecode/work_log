<?php

while (true) {

    $cycle_start_time = microtime(true);

    // Read settings
    $settings_path = __DIR__ . '/settings.json';
    $settings = json_decode(file_get_contents($settings_path), true);
    extract($settings);

    check_hosts($blocked_websites);

    sleep($refresh_interval);
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