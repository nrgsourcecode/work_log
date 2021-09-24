<?php

	$sleep_duration = 3;
    $sum_seconds_passed = 0;
    $program_start_time = microtime(true);

	while (true) {

        $cycle_start_time = microtime(true);

        sleep($sleep_duration);

        $active_window_handle = exec('xprop -root -f _NET_ACTIVE_WINDOW 0x " \$0\\n" _NET_ACTIVE_WINDOW | awk "{print \$2}"');
		$idle_milliseconds = exec('xprintidle');

        $idle = $idle_milliseconds > 60000 || $active_window_handle == '0x0';

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
        $settings_path = __DIR__ . '/settings.json';

		$settings = json_decode(file_get_contents($settings_path), true);

		extract($settings);
		$connection = new mysqli($DB_HOST, $DB_USERNAME, $DB_PASSWORD, $DB_DATABASE);

        if ($idle) {
            $window_details['activity_id'] = 1;
            $window_details['window_title'] = 'COMPUTER_IS_IDLE';
        } else {
            $active_window_id = exec('xdotool getactivewindow');
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
                        while($row = $resource->fetch_assoc()) {
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
                while($row = $resource->fetch_assoc()) {
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
                $active_tab_info = exec('bt active');
                $active_tab_array = explode("\t", $active_tab_info);
                $active_tab_id = $active_tab_array[0];
                $tab_list = [];
                exec('bt list', $tab_list);
                foreach ($tab_list as $tab_info) {
                    $tab_array = explode("\t", $tab_info);
                    if ($tab_array[0] == $active_tab_id) {
                        $window_url = (count($tab_array) > 2 ? $tab_array[2] : '');
                        $window_url = explode('&', $window_url)[0];
                        $window_details['window_url'] = $window_url;
                    }
                }
            } else if (strpos($application_path, 'code/code')) {
                // requires '${activeEditorLong}' in 'Window: Title' setting and ' • ' in 'Window: Title Separator'
                $title_array = explode(' • ', $window_title);
                $window_details['file_path']  = $title_array[0];

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
                    $search_counter ++;
                }

                $insert_fields_sql .= ($counter ? ', ' : '') . $field;
                $insert_values_sql .= ($counter ? ', ' : '') . $value;
                $counter ++;
            }
            $window_details[$field] = $value;
        }

        $resource = query($connection, $sql);
        if ($resource->num_rows) {
            while($row = $resource->fetch_assoc()) {
                $window_detail_id = $row['id'];
            }
        } else {
            $sql = "INSERT INTO window_details($insert_fields_sql) VALUES ($insert_values_sql)";
            query($connection, $sql);
            $window_detail_id = $connection->insert_id;
        }

        $sql = "SELECT `id` FROM `activity_log` WHERE `window_detail_id` = $window_detail_id AND `date` = '$date'";
        $resource = query($connection, $sql);
        $microtime = microtime(true);
        $actual_total_seconds_passed = $microtime - $program_start_time;
        $seconds_passed = round($microtime - $cycle_start_time, 3);
        $sum_seconds_passed += $seconds_passed;
        if ($resource->num_rows) {
            while($row = $resource->fetch_assoc()) {
                $id = $row['id'];
            }
            $sql = "UPDATE activity_log SET `seconds` = `seconds` + $seconds_passed WHERE `id` = $id";
        } else {
            $sql = "INSERT INTO `activity_log` (`window_detail_id`, `seconds`) VALUES ($window_detail_id, $seconds_passed)";
        }
        query($connection, $sql);
            echo date('Y-m-d H:i:s') . '.' . get_milliseconds() .
                "\t\tSum of seconds passed: " . round($sum_seconds_passed, 3) .
                "\tActual total seconds passed: " . round($actual_total_seconds_passed, 3) .
                "\tDifference: " . round($actual_total_seconds_passed - $sum_seconds_passed, 3) .
                "\tSeconds passed: " . round($seconds_passed, 3) . "\n\n";

		$connection->close();
    }

    function pattern_matched($window_details, $pattern) {
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

        function get_milliseconds()
        {
            $timestamp = microtime(true);
            return (int)(($timestamp - (int)$timestamp) * 1000);
        }

    function value_matched($match_value, $pattern_value) {


        if (is_null($pattern_value)) {
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
        $match_right = substr($pattern_value, -1,) != '*';
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
        echo $sql . "\n\n";
        $result = $connection->query($sql);
        $error = $connection->error;
        if ($error) {
            $log_path = __DIR__ . '/error.log';
            file_put_contents($log_path, $error);
            exec("gedit '$log_path'");
            die;
        }
        return $result;
    }


?>