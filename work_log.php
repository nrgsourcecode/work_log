<?php

$sleep_duration = 3;
$sum_seconds_passed = 0;
$program_start_time = microtime(true);

if (file_exists(log_file_path())) {
    unlink(log_file_path());
}

while (true) {

    $cycle_start_time = microtime(true);

    sleep($sleep_duration);

    $active_window_handle = exec('xprop -root -f _NET_ACTIVE_WINDOW 0x " \$0\\n" _NET_ACTIVE_WINDOW | awk "{print \$2}"');
    log_to_file('active_window_handle', $active_window_handle);
    $idle_milliseconds = exec('xprintidle');
    log_to_file('idle_milliseconds', $idle_milliseconds);

    $settings_path = __DIR__ . '/settings.json';
    $settings = json_decode(file_get_contents($settings_path), true);
    extract($settings);

    log_to_file('idle_timeout_seconds', $idle_timeout_seconds);

    $idle = ($idle_milliseconds > $idle_timeout_seconds * 1000) || $active_window_handle == '0x0';
    log_to_file('idle', $idle);

    $date = date('Y-m-d');
    $application_path = null;
    $application_id = null;
    $window_details = [
        'application_id' => null,
        'activity_id' => null,
        'project_id' => null,
        'task_id' => null,
        'window_title' => null,
        'file_path' => null,
        'window_url' => null
    ];

    $window_detail_id = null;

    $connection = new mysqli($DB_HOST, $DB_USERNAME, $DB_PASSWORD, $DB_DATABASE);

    if ($idle) {
        $window_details['activity_id'] = 1;
        $window_details['window_title'] = 'COMPUTER_IS_IDLE';
    } else {
        $active_window_id = exec('xdotool getactivewindow');
        log_to_file('active_window_id', $active_window_id);
        if (!$active_window_id) {
            continue;
        }
        $active_process_id = exec("xdotool getwindowpid $active_window_id");
        log_to_file('active_process_id', $active_process_id);

        $process_information = [];
        exec("ps aux | grep $active_process_id", $process_information);
        log_to_file('process_information', $process_information);

        foreach ($process_information as $line) {
            $line = preg_replace('!\s+!', ' ', $line);
            $line_array = explode(' ', $line);
            if ($line_array[1] == $active_process_id) {
                $active_process_info = $line;
                $application_path = $line_array[10];

                $sql = "SELECT `id` FROM applications WHERE `path` = '$application_path'";
                $resource = query($connection, $sql);
                if ($resource->num_rows) {
                    while ($row = $resource->fetch_assoc()) {
                        $application_id = $row['id'];
                    }
                } else {
                    $sql = "INSERT INTO applications(`path`) VALUES ('$application_path')";
                    query($connection, $sql);
                    $application_id = $connection->insert_id;
                }
            }
        }
        $window_details['application_id'] = $application_id;

        $patterns = [];
        $sql = "SELECT * FROM patterns ORDER BY sort_order, id";
        $resource = query($connection, $sql);
        if ($resource->num_rows) {
            while ($row = $resource->fetch_assoc()) {
                $pattern = $row;
                if (value_matched($application_path, $pattern['application_path'])) {
                    $pattern['application_id'] = $application_id;
                    $patterns[] = $pattern;
                }
            }
        }

        $window_title = trim(exec("xdotool getwindowname $active_window_id"));
        $first_letter = mb_substr($window_title, 0, 1);
        if ($first_letter == '●' || $first_letter == '*') {
            $window_title = trim(mb_substr($window_title, 1));
        }

        if (strpos($application_path, 'dbeaver')) {
            $dash_position = strpos($window_title, ' - ');
            if ($dash_position) {
                $window_title = 'DBeaver' . substr($window_title, $dash_position);
            }
        }

        $window_title = mb_substr($window_title, 0, 512);
        $window_details['window_title'] = $window_title;

        if (strpos($application_path, 'chrome/chrome')) {
            $bt_clients = [];
            exec('bt clients', $bt_clients);
            foreach ($bt_clients as $bt_client) {
                if (strpos($bt_client, 'ERROR')) {
                    $bt_client_info = explode("\t", $bt_client);
                    $process_id = $bt_client_info[2];
                    exec("kill $process_id");
                }
            }
            $active_tabs_info = [];
            $active_tab_ids = [];
            exec('bt active', $active_tabs_info);
            log_to_file('active_tab_info', $active_tabs_info);

            foreach ($active_tabs_info as $active_tab_info) {
                $active_tab_array = explode("\t", $active_tab_info);
                $active_tab_id = $active_tab_array[0];
                $active_tab_ids[$active_tab_id] = $active_tab_id;
            }
            log_to_file('active_tab_ids', $active_tab_ids);

            $tab_list = [];
            $confirmed = false;
            $window_url = null;
            exec('bt list', $tab_list);
            log_to_file('tab_list', $tab_list);
            foreach ($tab_list as $tab_info) {
                $tab_array = explode("\t", $tab_info);
                $tab_id = $tab_array[0];
                $tab_title = $tab_array[1];
                $tab_url = $tab_array[2] ?? null;
                if (mb_strpos($window_title, $tab_title) === 0 && !$confirmed) {
                    $confirmed = isset($active_tab_ids[$tab_id]);
                    $window_url = $tab_url;
                    $window_url = explode('&', $window_url)[0];
                    $window_details['window_url'] = $window_url;
                }
            }

            if (!$window_url) {
                $window_details['window_title'] = 'PRIVATE_BROWSING';
            }

            log_to_file('window_details', $window_details);

        } elseif (strpos($application_path, 'code/code')) {
            // requires '${activeEditorLong}' in 'Window: Title' setting and ' • ' in 'Window: Title Separator'
            $title_array = explode(' • ', $window_title);
            $window_details['file_path'] = $title_array[0];
        }

        foreach ($patterns as $pattern) {
            if (pattern_matched($window_details, $pattern)) {
                foreach ($window_details as $field => $value) {
                    $pattern_value = $pattern[$field];
                    if (is_null($value) || $pattern['override_matched_details']) {
                        $window_details[$field] = $pattern_value;
                    }
                }
                break;
            }
        }
    }

    $sql = "SELECT * FROM window_details WHERE ";
    $insert_fields_sql = '';
    $insert_values_sql = '';
    $counter = 0;
    $search_counter = 0;
    foreach ($window_details as $field => $value) {
        if (is_null($value)) {
            $value = 'null';
        } else {
            if (is_string($value) && !is_numeric($value)) {
                $value = "'" . $connection->real_escape_string($value) . "'";
            }

            if (strpos($field, '_id') === false) {
                $sql .= ($search_counter ? ' AND ' : '') . $field . ' = ' . $value;
                $search_counter++;
            }

            $insert_fields_sql .= ($counter ? ', ' : '') . $field;
            $insert_values_sql .= ($counter ? ', ' : '') . $value;
            $counter++;
        }
        $window_details[$field] = $value;
    }

    $resource = query($connection, $sql);
    if ($resource->num_rows) {
        while ($row = $resource->fetch_assoc()) {
            $window_detail_id = $row['id'];
            $window_details['project_id'] = $row['project_id'];
        }
    } else {
        $sql = "INSERT INTO window_details($insert_fields_sql) VALUES ($insert_values_sql)";
        query($connection, $sql);
        $window_detail_id = $connection->insert_id;
    }

    check_upwork($window_details);

    $sql = "SELECT `id` FROM `activity_log` WHERE `window_detail_id` = $window_detail_id AND `date` = '$date'";
    $resource = query($connection, $sql);
    $microtime = microtime(true);
    $actual_total_seconds_passed = $microtime - $program_start_time;
    $seconds_passed = round($microtime - $cycle_start_time, 3);
    $sum_seconds_passed += $seconds_passed;
    if ($resource->num_rows) {
        while ($row = $resource->fetch_assoc()) {
            $id = $row['id'];
        }
        $sql = "UPDATE activity_log SET `seconds` = `seconds` + $seconds_passed WHERE `id` = $id";
    } else {
        $sql = "INSERT INTO `activity_log` (`window_detail_id`, `seconds`) VALUES ($window_detail_id, $seconds_passed)";
    }
    query($connection, $sql);
    $connection->close();
}

function check_upwork($window_details)
{
    global $upwork_enabled_project_ids;
    $is_upwork_started = exec('ps aux | pgrep upwork') !== '';
    $should_track_time = in_array($window_details['project_id'], $upwork_enabled_project_ids);
    $notification_text = null;
    $icon = null;
    if ($should_track_time) {
        if (!$is_upwork_started) {
            $notification_text = 'Start upwork timer';
            $icon = 'start';
        }
    } else if ($is_upwork_started) {
        $notification_text = 'Stop upwork timer';
        $icon = 'stop';
    }

    $theme_changed = set_theme($notification_text !== null);

    if (!$notification_text && !$theme_changed) {
        return;
    }

    notify($notification_text, $icon);
}

function set_theme($error = false)
{
    $command = 'gsettings get org.gnome.shell.extensions.user-theme name';
    $current_theme = trim(exec($command), "'");

    $new_theme = 'work-log-' . ($error ? 'error' : 'regular');

    if ($current_theme === $new_theme) {
        return false;
    }
    
    $command = "gsettings set org.gnome.shell.extensions.user-theme name \"'$new_theme'\"";
    exec($command);
    return true;
}

function pattern_matched($window_details, $pattern)
{
    $result = true;
    foreach ($window_details as $field => $value) {
        if (strpos($field, '_id') === false) {
            $result = $result && value_matched($value, $pattern[$field]);
            if (!$result) {
                break;
            }
        }
    }
    return $result;
}

function value_matched($match_value, $pattern_value)
{
    if (empty($pattern_value)) {
        return true;
    }

    if (is_null($match_value)) {
        return false;
    }

    $result = true;
    $match_left = substr($pattern_value, 0, 1) != '*';
    if (!$match_left) {
        $pattern_value = substr($pattern_value, 1);
    }
    $match_right = substr($pattern_value, -1, ) != '*';
    if (!$match_right) {
        $pattern_value = substr($pattern_value, 0, -1);
    }
    $match_any = !$match_left && !$match_right;

    $pattern_index = strpos($match_value, $pattern_value);

    if ($match_any) {
        $result = $result && $pattern_index !== false;
    } else {
        if ($match_left) {
            $result = $result && $pattern_index === 0;
        }

        if ($match_right) {
            $result = $result && strrpos($match_value, $pattern_value) == strlen($match_value) - strlen($pattern_value);
        }
    }

    return $result;
}

function query($connection, $sql)
{
    log_to_file("sql", $sql);
    $result = $connection->query($sql);
    $error = $connection->error;
    if ($error) {
        notify($error);
    }
    return $result;
}

function notify($text, $icon = null)
{
    $text = '"' . str_replace('"', '\"', $text) . '"';
    $command = "notify-send -h int:transient:1";
    if ($icon) {
        $command .= " -i media-playback-$icon";
    }
    exec("$command $text &");

    if (!$icon) {
        return;
    }
    
    $command = 'paplay /usr/share/sounds/freedesktop/stereo/' . ($icon == 'start' ? 'complete' : 'power-unplug') . '.oga &';
    exec($command);
}

function log_file_path()
{
    return dirname(__FILE__) . '/query_log.txt';
}

function log_to_file($variable_name, $value, $force = false)
{
    global $enable_logging;

    if (!$enable_logging && !$force) {
        return;
    }

    $output = (is_array($value) ? json_encode($value) : $value);
    file_put_contents(log_file_path(), "\n\n$variable_name:\n$output", FILE_APPEND);
}