<?php

$sum_seconds_passed = 0;
$program_start_time = microtime(true);
$last_upwork_timer_notice_timestamp = 0;
$application_path = '';

$log_file_path = log_file_path();

if (file_exists($log_file_path)) {
    unlink($log_file_path);
}

while (true) {

    $cycle_start_time = microtime(true);

    // Read settings
    $settings_path = __DIR__ . '/settings.json';
    $settings = json_decode(file_get_contents($settings_path), true);
    extract($settings);

    $command = 'service site_blocker status | grep "Active:" | awk \'{print $2}\'';
    $site_blocker_status = exec($command);
    if ($site_blocker_status == 'inactive') {
        $command = 'sudo service site_blocker start';
        exec($command);
    }

    // Sleep before fetching data from the active application
    sleep($refresh_interval);

    // Get the active window
    $active_window_handle = exec('xprop -root -f _NET_ACTIVE_WINDOW 0x " \$0\\n" _NET_ACTIVE_WINDOW | awk "{print \$2}"');
    $idle_milliseconds = exec('xprintidle');


    $idle = ($idle_milliseconds > $idle_timeout_seconds * 1000) || $active_window_handle == '0x0';

    $date = date('Y-m-d');
    $application_path = '';
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
        if (!$active_window_id) {
            continue;
        }
        $active_process_id = exec("xdotool getwindowpid $active_window_id");

        $process_information = [];
        exec("ps aux | grep $active_process_id", $process_information);

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

        if (strpos($application_path, 'chrome/chrome')) {

            $url_separator = ' - tab-url: ';
            $whatsapp_url = 'web.whatsapp.com';
            $wm_class = exec("xprop -id $active_window_id WM_CLASS");

            if (!str_contains($window_title, $url_separator) && str_contains($wm_class, $whatsapp_url)) {
                $window_title .= $url_separator . $whatsapp_url;
            }

            $window_title_array = explode($url_separator, $window_title);
            if ($window_url = $window_title_array[1] ?? null) {
                $window_title = $window_title_array[0];
                $window_url = explode('&', $window_url)[0];
                $window_url = mb_substr($window_url, 0, 512);
                $window_details['window_url'] = $window_url;
            } else {
                $window_title = 'PRIVATE_BROWSING';
            }

        } elseif (strpos($application_path, 'code/code') || strpos($application_path, 'mount_Cursor')) {
            // requires '${activeEditorLong}' in 'Window: Title' setting and ' • ' in 'Window: Title Separator'
            $title_array = explode(' • ', $window_title);
            $window_details['file_path'] = $title_array[0];
        }

        $window_details['window_title'] = $window_title;

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
            continue;
        }
        
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

    $resource = query($connection, $sql);
    if ($resource->num_rows) {
        $row = $resource->fetch_assoc();
        $window_detail_id = $row['id'];
        foreach ($window_details as $field => $value) {
            if (empty($value)) {
                $row_value = $row[$field] ?? null;
                if (!empty($row_value)) {
                    $window_details[$field] = $row_value;
                }
            }
        }
    } else {
        $sql = "INSERT INTO window_details ($insert_fields_sql) VALUES ($insert_values_sql)";
        query($connection, $sql);
        $window_detail_id = $connection->insert_id;
    }


    $sql = "SELECT `id` FROM `activity_log` WHERE `window_detail_id` = $window_detail_id AND `date` = '$date'";
    $resource = query($connection, $sql);
    $microtime = microtime(true);
    $actual_total_seconds_passed = $microtime - $program_start_time;
    $seconds_passed = round($microtime - $cycle_start_time, 3);
    $sum_seconds_passed += $seconds_passed;
    check_upwork($window_details['project_id'], $seconds_passed);

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

function is_time_tracked_in_upwork_window($upwork_process_id, $title, $x, $y)
{
    $control_panel_window_ids = [];
    $command = "xdotool search --name '$title'";
    exec($command, $control_panel_window_ids);

    if (count($control_panel_window_ids) > 1) {
        $upwork_window_ids = [];
        $command = "xdotool search --pid $upwork_process_id";
        exec($command, $upwork_window_ids);
        $control_panel_window_ids = array_intersect($control_panel_window_ids, $upwork_window_ids);
    }

    $window_id = array_pop($control_panel_window_ids);
    $command = "import -silent -windowid $window_id -crop 1x1+$x+$y txt:- | grep -oP '#[0-9A-Fa-f]{12}'";
    $toggle_color = exec($command);
    return $toggle_color === '#10108A8A0000';
}

function is_time_tracked()
{
    $upwork_processes = [];
    $command = 'ps aux | pgrep upwork';
    exec($command, $upwork_processes);

    $upwork_process_id = $upwork_processes[0] ?? null;
    if (!$upwork_process_id) {
        return false;
    }

    $result =
        is_time_tracked_in_upwork_window($upwork_process_id, 'Time Tracker', 305, 105) ||
        is_time_tracked_in_upwork_window($upwork_process_id, 'Control Panel', 40, 40);

    return $result;
}

function check_upwork($project_id, $seconds_passed)
{
    global $upwork_enabled_project_ids;
    global $application_path;

    $is_upwork_active = strpos($application_path, 'Upwork/upwork');
    if ($is_upwork_active) {
        return;
    }

    $should_track_time = in_array($project_id, $upwork_enabled_project_ids);
    $is_time_tracked = is_time_tracked();

    $notification_text = null;
    $icon = null;
    $subtitle = null;

    if ($should_track_time) {
        if (!$is_time_tracked) {
            $notification_text = 'Start upwork timer';
            $icon = 'start';
        }
    } else if ($is_time_tracked) {
        $notification_text = 'Stop upwork timer';
        $icon = 'stop';
    }

    $theme_changed = set_theme($notification_text !== null);

    if (!$notification_text || !$theme_changed) {
        return;
    }

    notify($notification_text, $subtitle, $icon);
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
    $result = $connection->query($sql);
    $error = $connection->error;
    if ($error) {
        notify($error);
    }
    return $result;
}

function notify($title, $subtitle = null, $icon = null)
{
    $command = "notify-send -h int:transient:1";

    if ($icon) {
        $command .= " -i media-playback-$icon";
    }

    $command .= ' "' . str_replace('"', '\"', $title) . '"';
    if ($subtitle !== null) {
        $command .= ' "' . str_replace('"', '\"', $subtitle) . '"';
    }
    $command .= ' &';

    exec($command);

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
