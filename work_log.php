<?php

require_once __DIR__ . '/chrome_url_bridge.php';
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/check_settings_window.php';

startServer();

$applicationPath = '';
$totalSecondsTracked = 0;
$programStartTime = microtime(true);
$microtime = $programStartTime;

function getAllWindowDetails(): array|false
{
    $output = [];
    $resultCode = 0;
    $command = 'gdbus call --session --dest org.gnome.Shell --object-path /org/gnome/Shell/Extensions/Windows --method org.gnome.Shell.Extensions.Windows.List 2>/dev/null';
    exec($command, $output, $resultCode);
    if ($resultCode !== 0) {
        return false;
    }

    $outputString = implode("\n", $output);

    if (strlen($outputString) < 5) {
        handleError("Window list command output too short $outputString");
        return false;
    }

    $trimmedCommandOutput = substr($outputString, 2, -3);
    if (substr($trimmedCommandOutput, 2, 1) === '\\') {
        $trimmedCommandOutput = json_decode('"' . $trimmedCommandOutput . '"');
    }
    $trimmedCommandOutput = str_replace('\\\\', '\\', $trimmedCommandOutput);

    $allWindowDetails = json_decode($trimmedCommandOutput, true);

    if (is_null($allWindowDetails)) {
        handleError("Failed to decode JSON from window list\nCommand output: $outputString\nError: " . json_last_error_msg());
        return false;
    }

    return $allWindowDetails;
}

function getWindowDetails(): array|false
{
    global $settings;
    global $applicationPath;

    $result = [
        'application_id' => null,
        'activity_id' => null,
        'project_id' => null,
        'task_id' => null,
        'window_title' => null,
        'file_path' => null,
        'window_url' => null
    ];

    $allWindowsDetails = getAllWindowDetails();
    if ($allWindowsDetails === false) {
        return idleWindowDetails($result);
    }

    $focusedWindowDetails = array_find($allWindowsDetails, function ($windowDetails) {
        return $windowDetails['focus'] == 1;
    });

    if (empty($focusedWindowDetails)) {
        return idleWindowDetails($result);
    }

    if (getIdleTimeInSeconds() > $settings['idleTimeoutSeconds']) {
        return idleWindowDetails($result);
    }

    $activeProcessId = $focusedWindowDetails['pid'];

    $applicationDetails = getApplicationDetails($activeProcessId);

    if (empty($applicationDetails)) {
        handleError('Failed to get application details for process id ' . $activeProcessId);
        return false;
    }

    $applicationId = $applicationDetails['id'];
    $applicationPath = $applicationDetails['path'];

    $result['application_id'] = $applicationId;

    $patterns = fetchPatterns($applicationId, $applicationPath);
    if ($patterns === false) {
        handleError('Failed to fetch patterns for application id ' . $applicationId);
        return false;
    }

    $windowTitle = $focusedWindowDetails['title'];
    $firstLetter = mb_substr($windowTitle, 0, 1);

    $windowTitle = mb_substr($windowTitle, 0, 512);

    if ($firstLetter == '●' || $firstLetter == '*') {
        $windowTitle = trim(mb_substr($windowTitle, 1));
    }

    if ($applicationPath == '/usr/bin/gnome-control-center' && $windowTitle === 'Settings') {
        closeAddUser($activeProcessId);
    }

    if (strpos($applicationPath, 'dbeaver')) {
        handleDbeaver($windowTitle);
    } else if (strpos($applicationPath, 'chrome/chrome')) {
        handleChrome($result, $windowTitle);
    } else if (strpos($applicationPath, 'code/code') || strpos($applicationPath, 'mount_Cursor')) {
        handleCode($result, $windowTitle);
    }

    $result['window_title'] = $windowTitle;

    applyMatchedPattern($result, $patterns);

    return $result;
}

function idleWindowDetails(array $windowDetails)
{
    $windowDetails['activity_id'] = 1;
    $windowDetails['window_title'] = 'COMPUTER_IS_IDLE';
    return $windowDetails;
}

function fetchPatterns(int|null $applicationId, string $applicationPath): array|false
{
    $patterns = [];
    $sql = "SELECT * FROM patterns ORDER BY sort_order, id";
    $data = selectQuery($sql);
    if ($data === false) {
        return false;
    }

    foreach ($data as $row) {
        $pattern = $row;
        if (valueMatched($applicationPath, $pattern['application_path'])) {
            $pattern['application_id'] = $applicationId;
            $patterns[] = $pattern;
        }
    }
    return $patterns;
}

function applyMatchedPattern(array &$windowDetails, array $patterns)
{
    foreach ($patterns as $pattern) {
        if (patternMatched($windowDetails, $pattern)) {
            foreach ($windowDetails as $field => $value) {
                $patternValue = $pattern[$field];
                if (is_null($value) || $pattern['override_matched_details']) {
                    $windowDetails[$field] = $patternValue;
                }
            }
            break;
        }
    }
}

function handleDbeaver(string &$windowTitle)
{
    if (!$dashPosition = strpos($windowTitle, ' - ')) {
        return;
    }

    $windowTitle = 'DBeaver' . substr($windowTitle, $dashPosition);
}


function handleChrome(array &$windowDetails, string &$windowTitle)
{

    if (!$windowUrl = getChromeUrl()) {
        $windowTitle = 'PRIVATE_BROWSING';
        return;
    }

    $windowUrl = explode('&', $windowUrl)[0];
    $windowUrl = mb_substr($windowUrl, 0, 512);
    $windowDetails['window_url'] = $windowUrl;
}

function handleCode(array &$windowDetails, string $windowTitle)
{
    $titleArray = explode(' • ', $windowTitle);
    $windowDetails['file_path'] = $titleArray[0];
}

function getApplicationDetails(int $processId): false|array
{
    $processInformation = [];
    exec("ps aux | grep $processId", $processInformation);

    foreach ($processInformation as $line) {
        $line = preg_replace('!\s+!', ' ', $line);
        $lineArray = explode(' ', $line);
        if ($lineArray[1] != $processId) {
            continue;
        }

        $applicationPath = $lineArray[10];

        $sql = "SELECT `id` FROM applications WHERE `path` = '$applicationPath'";
        $data = selectQuery($sql);

        if ($data === false) {
            return false;
        }

        if (!$applicationId = $data[0]['id'] ?? null) {
            $sql = "INSERT INTO applications(`path`) VALUES ('$applicationPath')";
            $applicationId = insertQuery($sql);
        }

        return [
            'id' => $applicationId,
            'path' => $applicationPath
        ];
    }

    return false;
}

function setImmutableFlag(string $filePath)
{
    $command = "lsattr $filePath";
    $result = exec($command);
    if (strpos($result, '---i---')) {
        return;
    }

    $command = "/usr/bin/chattr +i $filePath";
    $result = exec($command);

    $command = "lsattr $filePath";
    $result = exec($command);
}

function getIdleTimeInSeconds(): float
{
    $output = [];
    $resultCode = 0;
    $command = 'gdbus call --session --dest org.gnome.Mutter.IdleMonitor --object-path /org/gnome/Mutter/IdleMonitor/Core --method org.gnome.Mutter.IdleMonitor.GetIdletime 2>/dev/null';
    exec($command, $output, $resultCode);

    if ($resultCode !== 0) {
        return 0;
    }

    $outputString = implode("\n", $output);
    if (strlen($outputString) < 5) {
        handleError("Window list command output too short $outputString");
        return 0;
    }

    $timeInMilliseconds = (float) substr($outputString, 8, -2);
    return $timeInMilliseconds / 1000;
}

function trackWindowDetails()
{
    global $settings;
    global $totalSecondsTracked;
    global $programStartTime;
    global $applicationPath;
    global $microtime;

    $settings = Settings::loadSettings();

    $command = 'service site_blocker status | grep "Active:" | awk \'{print $2}\'';
    $siteBlockerStatus = exec($command);
    if ($siteBlockerStatus == 'inactive') {
        $command = 'sudo /usr/sbin/service site_blocker start';
        exec($command);
    }

    setImmutableFlag(Settings::$settingsPath);
    setImmutableFlag(__DIR__ . '/site_blocker.php');

    sleep($settings['refreshInterval']);

    $timezone = new DateTimeZone($settings['timezone']);
    $dateTime = new DateTime('now', $timezone);

    if ($dateTime->format('H:i') < $settings['workdayStart']) {
        $dateTime->modify('-1 day');
    }

    $date = $dateTime->format('Y-m-d');

    $applicationPath = '';

    $windowDetails = getWindowDetails();
    if ($windowDetails === false) {
        return;
    }

    $windowDetailId = null;

    $sql = "SELECT * FROM window_details WHERE ";
    $insertFieldsSql = '';
    $insertValuesSql = '';
    $counter = 0;
    $searchCounter = 0;
    foreach ($windowDetails as $field => $value) {
        if (is_null($value)) {
            continue;
        }

        if (is_string($value) && !is_numeric($value)) {
            $value = "'" . addslashes($value) . "'";
        }

        if (strpos($field, '_id') === false) {
            $sql .= ($searchCounter ? ' AND ' : '') . $field . ' = ' . $value;
            $searchCounter++;
        }

        $insertFieldsSql .= ($counter ? ', ' : '') . $field;
        $insertValuesSql .= ($counter ? ', ' : '') . $value;
        $counter++;
    }

    $data = selectQuery($sql);
    if ($data === false) {
        return;
    }


    if ($row = $data[0] ?? null) {
        $windowDetailId = $row['id'];

        foreach ($windowDetails as $field => $value) {
            if (empty($value)) {
                $rowValue = $row[$field] ?? null;
                if (!empty($rowValue)) {
                    $windowDetails[$field] = $rowValue;
                }
            }
        }
    } else {

        $sql = "INSERT INTO window_details ($insertFieldsSql) VALUES ($insertValuesSql)";
        $windowDetailId = insertQuery($sql);

        if ($windowDetailId === false) {
            return;
        }
    }

    $sql = "SELECT `id` FROM `activity_log` WHERE `window_detail_id` = $windowDetailId AND `date` = '$date'";
    $data = selectQuery($sql);

    if ($data === false) {
        return;
    }

    $microtime = microtime(true);
    $totalSecondsPassed = round($microtime - $programStartTime, 3);
    $secondsToTrack = round($totalSecondsPassed - $totalSecondsTracked, 3);
    $totalSecondsTracked += $secondsToTrack;
    checkUpwork($windowDetails['project_id']);

    if ($id = $data[0]['id'] ?? null) {
        $sql = "UPDATE activity_log SET `seconds` = `seconds` + $secondsToTrack WHERE `id` = $id";
        query($sql);
        return;
    }

    $sql = "INSERT INTO `activity_log` (`window_detail_id`, `date`, `seconds`) VALUES ($windowDetailId, '$date', $secondsToTrack)";
    insertQuery($sql);
}

while (true) {
    trackWindowDetails();
}

function isTimeTrackedInUpworkWindow(int $upworkProcessId, string $title, int $x, int $y)
{
    $controlPanelWindowIds = [];
    $command = "xdotool search --name '$title'";
    exec($command, $controlPanelWindowIds);

    if (count($controlPanelWindowIds) > 1) {
        $upworkWindowIds = [];
        $command = "xdotool search --pid $upworkProcessId";
        exec($command, $upworkWindowIds);
        $controlPanelWindowIds = array_intersect($controlPanelWindowIds, $upworkWindowIds);
    }

    $windowId = array_pop($controlPanelWindowIds);
    $command = "import -silent -windowid $windowId -crop 1x1+$x+$y txt:- | grep -oP '#[0-9A-Fa-f]{12}'";
    $toggleColor = exec($command);
    return $toggleColor === '#10108A8A0000';
}

function isTimeTracked()
{
    $upworkProcesses = [];
    $command = 'ps aux | pgrep upwork';
    exec($command, $upworkProcesses);

    $upworkProcessId = $upworkProcesses[0] ?? null;
    if (!$upworkProcessId) {
        return false;
    }

    $result =
        isTimeTrackedInUpworkWindow($upworkProcessId, 'Time Tracker', 305, 105) ||
        isTimeTrackedInUpworkWindow($upworkProcessId, 'Control Panel', 40, 40);

    return $result;
}

function checkUpwork(int|null $projectId)
{
    if ($projectId === null) {
        return;
    }

    global $settings;
    global $applicationPath;

    $isUpworkActive = strpos($applicationPath, 'Upwork/upwork');
    if ($isUpworkActive) {
        return;
    }

    $shouldTrackTime = in_array($projectId, $settings['upworkEnabledProjectIds']);
    $isTimeTracked = isTimeTracked();

    $notificationText = null;
    $icon = null;
    $subtitle = null;

    if ($shouldTrackTime) {
        if (!$isTimeTracked) {
            $notificationText = 'Start upwork timer';
            $icon = 'start';
        }
    } else if ($isTimeTracked) {
        $notificationText = 'Stop upwork timer';
        $icon = 'stop';
    }

    $themeChanged = setTheme($notificationText !== null);

    if (!$notificationText || !$themeChanged) {
        return;
    }

    notify($notificationText, $subtitle, $icon);
}

function setTheme($error = false)
{
    $command = 'gsettings get org.gnome.shell.extensions.user-theme name';
    $currentTheme = trim(exec($command), "'");

    $newTheme = 'work-log-' . ($error ? 'error' : 'regular');

    if ($currentTheme === $newTheme) {
        return false;
    }

    $command = "gsettings set org.gnome.shell.extensions.user-theme name \"'$newTheme'\"";
    exec($command);
    return true;
}

function patternMatched(array $windowDetails, array $pattern)
{
    $result = true;
    foreach ($windowDetails as $field => $value) {
        if (str_ends_with($field, '_id')) {
            continue;
        }

        $result = $result && valueMatched($value, $pattern[$field]);

        if (!$result) {
            break;
        }
    }
    return $result;
}

function valueMatched($matchValue, $patternValue)
{
    if (empty($patternValue)) {
        return true;
    }

    if (is_null($matchValue)) {
        return false;
    }

    $result = true;
    $matchLeft = substr($patternValue, 0, 1) != '*';
    if (!$matchLeft) {
        $patternValue = substr($patternValue, 1);
    }
    $matchRight = substr($patternValue, -1,) != '*';
    if (!$matchRight) {
        $patternValue = substr($patternValue, 0, -1);
    }
    $matchAny = !$matchLeft && !$matchRight;

    $patternIndex = strpos($matchValue, $patternValue);

    if ($matchAny) {
        $result = $result && $patternIndex !== false;
    } else {
        if ($matchLeft) {
            $result = $result && $patternIndex === 0;
        }

        if ($matchRight) {
            $result = $result && strrpos($matchValue, $patternValue) == strlen($matchValue) - strlen($patternValue);
        }
    }

    return $result;
}

function query(string $sql, $insert = false): int|false|mysqli_result
{
    global $settings;

    $connection = null;

    try {
        $dbSettings = $settings['db'];
        $connection = new mysqli($dbSettings['host'], $dbSettings['username'], $dbSettings['password'], $dbSettings['database']);
        $resource = $connection->query($sql);
    } catch (mysqli_sql_exception $e) {
        handleError($e->getMessage());
        if ($connection) {
            $connection->close();
        }
        return false;
    }

    if ($insert) {
        $lastInsertedId = $connection->insert_id;
        $connection->close();
        return $lastInsertedId;
    }

    $connection->close();
    return $resource;
}

function insertQuery(string $sql)
{
    return query($sql, true);
}

function selectQuery(string $sql): array|false
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

function notify(string $title, string|null $subtitle = null, string|null $icon = null)
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

function handleError(string $error)
{
    Logger::log('Error', $error, true);
    notify('An error occurred', $error, 'error');
}
