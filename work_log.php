<?php

	$sleep_duration = 1;

	while (true) {

        sleep($sleep_duration);

        $active_window_handle = exec('xprop -root -f _NET_ACTIVE_WINDOW 0x " \$0\\n" _NET_ACTIVE_WINDOW | awk "{print \$2}"');
		$idle_milliseconds = exec('xprintidle');

        if ($idle_milliseconds > 30000 || $active_window_handle == '0x0') {
            continue;
        }

		$date = date('Y-m-d');
		$application_id = null;
		$application_path = null;
		$window_title = 'null';
		$file_path = 'null';
		$window_url = 'null';
		$window_detail_id = null;
        $settings_path = __DIR__ . '/settings.json';

		$settings = json_decode(file_get_contents($settings_path), true);

		extract($settings);
		$connection = new mysqli($DB_HOST, $DB_USERNAME, $DB_PASSWORD, $DB_DATABASE);

        $result_code = null;
        $output_array = [];

		$idle_milliseconds = exec('xprintidle');

        $active_window_id = exec('xdotool getactivewindow');
        $active_process_id = exec("xdotool getwindowpid $active_window_id");

        exec("ps aux | grep $active_process_id", $process_information);
        foreach ($process_information as $line) {
            $line = preg_replace('!\s+!', ' ', $line);
            $line_array = explode(' ', $line);
            if ($line_array[1] == $active_process_id) {
                $active_process_info = $line;
                $application_path = $line_array[10];

                $sql = "SELECT `id` FROM applications WHERE `path` = '$application_path'";
                $resource = $connection->query($sql);
                if ($resource->num_rows) {
                    while($row = $resource->fetch_assoc()) {
                        $application_id = $row['id'];
                    }
                } else {
                    $sql = "INSERT INTO applications(`path`) VALUES ('$application_path')";
                    $connection->query($sql);
                    $application_id = $connection->insert_id;
                }
            }
        }

        if (!$patterns_set) {
            $connection->close();
            continue;
        }

        $window_title = trim(exec("xdotool getwindowname $active_window_id"));
        $first_letter = mb_substr($window_title, 0, 1);
        if ($first_letter == '●' || $first_letter == '*') {
            $window_title = trim(mb_substr($window_title, 1));
        }
        $window_title = "'" . $connection->real_escape_string($window_title) . "'";
        $process_information = [];

        if (strpos($application_path, 'chrome/chrome')) {
            $active_tab_info = exec('bt active');
            $active_tab_array = explode("\t", $active_tab_info);
            $active_tab_id = $active_tab_array[0];
            $tab_list = [];
            exec('bt list', $tab_list);
            foreach ($tab_list as $tab_info) {
                $tab_array = explode("\t", $tab_info);
                if ($tab_array[0] == $active_tab_id) {
                    $window_url = "'" . $connection->real_escape_string($tab_array[2]) . "'";
                    $patterns = [
                        'mail.google.com',
                        'youtube.com',
                        'facebook.com',
                        'redrox.local',
                        'nrgsourcecode.atlassian.net'
                    ];
                    foreach ($patterns as $pattern) {
                        if (strpos($window_url, $pattern)) {
                            $window_url = "'*$pattern*'";
                            $window_title = $window_url;
                        }
                    }
                }
            }
        }

        $sql = "SELECT * FROM window_details WHERE application_id = $application_id AND " . ($window_url == 'null' ? "window_title = $window_title" : "window_url = $window_url");
        $resource = $connection->query($sql);
        if ($resource->num_rows) {
            while($row = $resource->fetch_assoc()) {
                $window_detail_id = $row['id'];
            }
        } else {
            $sql = "INSERT INTO window_details(`application_id`, `window_title`, `window_url`) VALUES ($application_id, $window_title, $window_url)";
            $connection->query($sql);
            $window_detail_id = $connection->insert_id;
        }

        $sql = "SELECT `id` FROM `activity_log` WHERE `window_detail_id` = $window_detail_id AND `date` = '$date'";
        $resource = $connection->query($sql);
        if ($resource->num_rows) {
            while($row = $resource->fetch_assoc()) {
                $id = $row['id'];
            }
            $sql = "UPDATE activity_log SET `seconds` = `seconds` + $sleep_duration WHERE `id` = $id";
        } else {
            $sql = "INSERT INTO `activity_log` (`window_detail_id`, `seconds`) VALUES ($window_detail_id, $sleep_duration)";
        }
        $connection->query($sql);

		$connection->close();
    }

?>