<?php

$application_path = '';
$total_seconds_tracked = 0;
$program_start_time = microtime(true);
$microtime = $program_start_time;
$window_details_template = [
    'application_id' => null,
    'activity_id' => null,
    'project_id' => null,
    'task_id' => null,
    'window_title' => null,
    'file_path' => null,
    'window_url' => null
];

function get_all_window_details(): array
{
    $command_output = [];
    $command = 'gdbus call --session --dest org.gnome.Shell --object-path /org/gnome/Shell/Extensions/Windows --method org.gnome.Shell.Extensions.Windows.List';
    exec($command, $command_output);
    if (empty($command_output)) {
        handle_error('Failed to get window details');
        return [];
    }
    $trimmed_command_output = substr($command_output[0], 2, -3);
    $all_window_details = json_decode($trimmed_command_output, true);

    return $all_window_details;
}

function get_window_details(): array|false
{
    global $window_details_template;
    global $idle_timeout_seconds;

    $result = $window_details_template;

    $idle_milliseconds = get_idle_time_in_milliseconds();

    $all_window_details = get_all_window_details();
    if (empty($all_window_details)) {
        return false;
    }

    $focused_window_details = array_find($all_window_details, function($window_details) {
        return $window_details['focus'] == 1;
    });

    if (($idle_milliseconds > $idle_timeout_seconds * 1000) || empty($focused_window_details)) {
        $result['activity_id'] = 1;
        $result['window_title'] = 'COMPUTER_IS_IDLE';
        return $result;
    }

    $active_process_id = $focused_window_details['pid'];
    $wm_class = $focused_window_details['wm_class'];

    $application_details = get_application_details($active_process_id);

    if (empty($application_details)) {
        handle_error('Failed to get application details for process id ' . $active_process_id);
        return false;
    }

    $application_id = $application_details['id'];
    $application_path = $application_details['path'];
    
    $result['application_id'] = $application_id;

    $patterns = fetch_patterns($application_id, $application_path);
    if ($patterns === false) {
        handle_error('Failed to fetch patterns for application id ' . $application_id);
        return false;
    }

    $window_title = $focused_window_details['title'];
    $first_letter = mb_substr($window_title, 0, 1);

    $window_title = mb_substr($window_title, 0, 512);

    if ($first_letter == '●' || $first_letter == '*') {
        $window_title = trim(mb_substr($window_title, 1));
    }

    if (strpos($application_path, 'dbeaver')) {
        handle_dbeaver($window_title);
    } else if (strpos($application_path, 'chrome/chrome')) {
        handle_chrome($result, $wm_class, $window_title);
    } else if (strpos($application_path, 'code/code') || strpos($application_path, 'mount_Cursor')) {
        handle_code($result, $window_title);
    }

    $result['window_title'] = $window_title;

    apply_matched_pattern($result, $patterns);
 
    return $result;
}

function fetch_patterns($application_id, $application_path): array|false
{
    $patterns = [];
    $sql = "SELECT * FROM patterns ORDER BY sort_order, id";
    $data = select_query($sql);
    if ($data === false) {
        return false;
    }

    foreach ($data as $row) {
        $pattern = $row;
        if (value_matched($application_path, $pattern['application_path'])) {
            $pattern['application_id'] = $application_id;
            $patterns[] = $pattern;
        }
    }
    return $patterns;
}

function apply_matched_pattern(&$window_details, $patterns)
{
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

function handle_dbeaver(&$window_title)
{
    if (!$dash_position = strpos($window_title, ' - ')) {
        return;
    }

    $window_title = 'DBeaver' . substr($window_title, $dash_position);
}


function handle_chrome(&$window_details, $wm_class, &$window_title)
{
    $url_separator = ' - tab-url: ';
    $whatsapp_url = 'web.whatsapp.com';

    if (!str_contains($window_title, $url_separator) && str_contains($wm_class, $whatsapp_url)) {
        $window_title .= $url_separator . $whatsapp_url;
    }

    $window_title_array = explode($url_separator, $window_title);
    if (!$window_url = $window_title_array[1] ?? null) {
        $window_title = 'PRIVATE_BROWSING';
        return;
    }

    $window_title = $window_title_array[0];
    $window_url = explode('&', $window_url)[0];
    $window_url = mb_substr($window_url, 0, 512);
    $window_details['window_url'] = $window_url;
}

function handle_code(&$window_details, $window_title)
{
    $title_array = explode(' • ', $window_title);
    $window_details['file_path'] = $title_array[0];
}

function get_application_details($process_id): false|array
{
    $process_information = [];
    exec("ps aux | grep $process_id", $process_information);

    foreach ($process_information as $line) {
        $line = preg_replace('!\s+!', ' ', $line);
        $line_array = explode(' ', $line);
        if ($line_array[1] != $process_id) {
            continue;
        }

        $application_path = $line_array[10];

        $sql = "SELECT `id` FROM applications WHERE `path` = '$application_path'";
        $data = select_query($sql);

        if ($data === false) {
            return false;
        }

        if (!$application_id = $data[0]['id'] ?? null) {
            $sql = "INSERT INTO applications(`path`) VALUES ('$application_path')";
            $application_id = insert_query($sql);
        }

        return [
            'id' => $application_id,
            'path' => $application_path
        ];
    }

    return false;
}

function get_idle_time_in_milliseconds(): float
{
    $command = 'gdbus call --session --dest org.gnome.Mutter.IdleMonitor --object-path /org/gnome/Mutter/IdleMonitor/Core --method org.gnome.Mutter.IdleMonitor.GetIdletime';
    $result = exec($command);
    $result = substr($result, 8, -2);
    return (float) $result;
}

while (true) {

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

    $date = date('Y-m-d');
    $application_path = '';
    $application_id = null;

    $window_details = get_window_details();
    if ($window_details === false) {
        continue;
    }

    $window_detail_id = null;

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
            $value = "'" . addslashes($value) . "'";
        }

        if (strpos($field, '_id') === false) {
            $sql .= ($search_counter ? ' AND ' : '') . $field . ' = ' . $value;
            $search_counter++;
        }

        $insert_fields_sql .= ($counter ? ', ' : '') . $field;
        $insert_values_sql .= ($counter ? ', ' : '') . $value;
        $counter ++;
    }

    $data = select_query($sql);
    if ($data === false) {
        continue;
    }


    if ($row = $data[0] ?? null) {
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
        $window_detail_id = insert_query($sql);

        if ($window_detail_id === false) {
            continue;
        }

    }

    $sql = "SELECT `id` FROM `activity_log` WHERE `window_detail_id` = $window_detail_id AND `date` = '$date'";
    $data = select_query($sql);

    if ($data === false) {
        continue;
    }

    $microtime = microtime(true);
    $total_seconds_passed = round($microtime - $program_start_time, 3);
    $seconds_to_track = round($total_seconds_passed - $total_seconds_tracked, 3);
    $total_seconds_tracked += $seconds_to_track;
    check_upwork($window_details['project_id']);

    if ($id = $data[0]['id'] ?? null) {
        $sql = "UPDATE activity_log SET `seconds` = `seconds` + $seconds_to_track WHERE `id` = $id";
        query($sql);
        continue;
    }

    $sql = "INSERT INTO `activity_log` (`window_detail_id`, `date`, `seconds`) VALUES ($window_detail_id, '$date', $seconds_to_track)";
    insert_query($sql);
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

function check_upwork($project_id)
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
        if (str_ends_with($field, '_id')) {
            continue;
        }

        $result = $result && value_matched($value, $pattern[$field]);

        if (!$result) {
            break;
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

function query($sql, $insert = false): int|false|mysqli_result
{
    global $DB_HOST;
    global $DB_USERNAME;
    global $DB_PASSWORD;
    global $DB_DATABASE;

    $connection = null;

    try {
        $connection = new mysqli($DB_HOST, $DB_USERNAME, $DB_PASSWORD, $DB_DATABASE);
        $resource = $connection->query($sql);
    } catch (mysqli_sql_exception $e) {
        handle_error($e->getMessage());
        if ($connection) {
            $connection->close();
        }
        return false;
    }

    if ($insert) {
        $last_inserted_id = $connection->insert_id;
        $connection->close();
        return $last_inserted_id;
    }

    $connection->close();
    return $resource;
}

function insert_query($sql)
{
    return query($sql, true);
}

function select_query($sql): array|false
{
    if (!$resource = query($sql)) {
        return false;
    }

    $result = [];
    while ($row = $resource->fetch_assoc()) {
        $result[] = $row;
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
    return dirname(__FILE__) . '/work_log.txt';
}

function handle_error($error)
{
    log_to_file('Error', $error, true);
    notify('An error occurred', $error, 'error');
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
