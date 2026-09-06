<?php

declare(strict_types=1);

define('ACCESSIBILITY_STATE_ACTIVE', 1);
define('ACCESSIBILITY_STATE_VISIBLE', 30);

function extractStateInts(string $raw): array
{
    $cleaned = preg_replace('/\b(?:byte|u?int(?:16|32|64))\b/', '', $raw);
    preg_match_all('/\d+/', $cleaned, $m);
    return array_map('intval', $m[0]);
}

function extractQuotedString(string $raw): ?string
{
    if (!preg_match('/[\'"]/', $raw, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $delimiter = $m[0][0];
    $start = $m[0][1] + 1;
    $escapedDelim = preg_quote($delimiter, '/');

    if (!preg_match('/((?:\\\\.|[^' . $escapedDelim . '\\\\])*)' . $escapedDelim . '/', $raw, $contentMatch, 0, $start)) {
        return null;
    }

    $content = $contentMatch[1];
    return str_replace(['\\\\', "\\'", '\\"'], ['\\', "'", '"'], $content);
}

function getAccessibilityBusAddress(): ?string
{
    static $cachedAddress = null;
    if ($cachedAddress !== null) {
        return $cachedAddress;
    }

    $command = 'gdbus call --session --dest org.a11y.Bus --object-path /org/a11y/bus --method org.a11y.Bus.GetAddress 2>/dev/null';
    $output = [];
    $resultCode = null;
    exec($command, $output, $resultCode);

    if ($resultCode === 0 && !empty($output)) {
        $rawAddress = implode("\n", $output);
        if (preg_match("/'([^']*)'/", $rawAddress, $matches)) {
            $cachedAddress = $matches[1];
            return $cachedAddress;
        }
    }
    return null;
}

function callAccessibilityMethod(
    string $destination,
    string $objectPath,
    string $interface,
    string $method,
    string $arguments = ''
): ?string {
    $accessibilityAddress = getAccessibilityBusAddress();

    $busTarget = $accessibilityAddress !== null
        ? sprintf('--address %s', escapeshellarg($accessibilityAddress))
        : '--session';

    $command = sprintf(
        'gdbus call %s --dest %s --object-path %s --method %s.%s %s 2>/dev/null',
        $busTarget,
        escapeshellarg($destination),
        escapeshellarg($objectPath),
        escapeshellarg($interface),
        escapeshellarg($method),
        $arguments
    );

    $output = [];
    $resultCode = null;
    exec($command, $output, $resultCode);

    if ($resultCode === 0 && !empty($output)) {
        return implode("\n", $output);
    }
    return null;
}

function checkVisibleTree(string $busName, string $objectPath, int $depth = 0, array $map = []): void
{
    $stateRaw = callAccessibilityMethod($busName, $objectPath, 'org.a11y.atspi.Accessible', 'GetState');
    if ($stateRaw === null) {
        return;
    }

    $states = extractStateInts($stateRaw);

    $isElementVisible = false;
    if (isset($states[0]) && ($states[0] & (1 << ACCESSIBILITY_STATE_VISIBLE)) !== 0) {
        $isElementVisible = true;
    }

    if ($isElementVisible === false) {
        return;
    }

    $nameRaw = callAccessibilityMethod($busName, $objectPath, 'org.freedesktop.DBus.Properties', 'Get', 'org.a11y.atspi.Accessible Name');
    $elementName = '';
    if ($nameRaw !== null) {
        $extracted = extractQuotedString($nameRaw);
        if ($extracted !== null) {
            $elementName = trim($extracted);
        }
    }

    $roleRaw = callAccessibilityMethod($busName, $objectPath, 'org.a11y.atspi.Accessible', 'GetRoleName');
    $roleName = 'unknown';
    if ($roleRaw !== null) {
        $extracted = extractQuotedString($roleRaw);
        if ($extracted !== null) {
            $roleName = $extracted;
        }
    }

    $nextMap = $map;

    if ($elementName !== '') {
        $mapElement = $map[0];
        if ($mapElement['role'] === $roleName && $mapElement['name'] === $elementName) {
            array_shift($nextMap);
        } else {
            return;
        }
        echo str_repeat("  ", $depth) . "[{$roleName}] {$elementName}\n";
    }

    $childCountRaw = callAccessibilityMethod($busName, $objectPath, 'org.freedesktop.DBus.Properties', 'Get', 'org.a11y.atspi.Accessible ChildCount');
    $childCount = 0;
    if ($childCountRaw !== null && preg_match("/<(\d+)>/", $childCountRaw, $childCountMatches)) {
        $childCount = (int)$childCountMatches[1];
    }

    for ($counter = 0; $counter < $childCount; $counter++) {
        $childRaw = callAccessibilityMethod($busName, $objectPath, 'org.a11y.atspi.Accessible', 'GetChildAtIndex', (string)$counter);

        if ($childRaw !== null && preg_match("/['\"]([^'\"]*)['\"],\s*(?:objectpath\s+)?['\"]([^'\"]*)['\"]/", $childRaw, $childMatches)) {
            $childBusName = $childMatches[1];
            $childObjectPath = $childMatches[2];
            checkVisibleTree($childBusName, $childObjectPath, $depth + 1, $nextMap ?? []);
        }
    }
}

function extractSingleInt(string $raw): ?int
{
    $cleaned = preg_replace('/\b(?:byte|u?int(?:16|32|64))\b/', '', $raw);
    if (preg_match('/\d+/', $cleaned, $m)) {
        return (int)$m[0];
    }
    return null;
}

function findApplicationByPid(int $targetPid): ?array
{
    $registryBusName = "org.a11y.atspi.Registry";
    $rootObjectPath = "/org/a11y/atspi/accessible/root";

    $countRaw = callAccessibilityMethod($registryBusName, $rootObjectPath, 'org.freedesktop.DBus.Properties', 'Get', 'org.a11y.atspi.Accessible ChildCount');
    if ($countRaw === null || !preg_match("/<(\d+)>/", $countRaw, $countMatches)) {
        return null;
    }
    $count = (int)$countMatches[1];

    for ($counter = 0; $counter < $count; $counter++) {
        $childRaw = callAccessibilityMethod($registryBusName, $rootObjectPath, 'org.a11y.atspi.Accessible', 'GetChildAtIndex', (string)$counter);
        if ($childRaw === null || !preg_match("/['\"]([^'\"]*)['\"],\s*(?:objectpath\s+)?['\"]([^'\"]*)['\"]/", $childRaw, $childMatches)) {
            continue;
        }
        $appBusName = $childMatches[1];
        $appObjectPath = $childMatches[2];

        $pidRaw = callAccessibilityMethod('org.freedesktop.DBus', '/org/freedesktop/DBus', 'org.freedesktop.DBus', 'GetConnectionUnixProcessID', escapeshellarg($appBusName));
        if ($pidRaw !== null) {
            $foundPid = extractSingleInt($pidRaw);
            if ($foundPid === $targetPid) {
                return [$appBusName, $appObjectPath];
            }
        }
    }
    return null;
}

function findWindowInApp(string $appBusName, string $appObjectPath): ?array
{
    $countRaw = callAccessibilityMethod($appBusName, $appObjectPath, 'org.freedesktop.DBus.Properties', 'Get', 'org.a11y.atspi.Accessible ChildCount');
    if ($countRaw === null || !preg_match("/<(\d+)>/", $countRaw, $countMatches)) {
        return null;
    }
    $count = (int)$countMatches[1];

    for ($counter = 0; $counter < $count; $counter++) {
        $childRaw = callAccessibilityMethod($appBusName, $appObjectPath, 'org.a11y.atspi.Accessible', 'GetChildAtIndex', (string)$counter);
        if ($childRaw === null || !preg_match("/['\"]([^'\"]*)['\"],\s*(?:objectpath\s+)?['\"]([^'\"]*)['\"]/", $childRaw, $childMatches)) {
            continue;
        }
        $childBusName = $childMatches[1];
        $childObjectPath = $childMatches[2];

        $roleRaw = callAccessibilityMethod($childBusName, $childObjectPath, 'org.a11y.atspi.Accessible', 'GetRoleName');
        $role = $roleRaw !== null ? extractQuotedString($roleRaw) : null;

        if ($role === 'window' || $role === 'frame') {
            return [$childBusName, $childObjectPath];
        }
    }
    return null;
}

function getWindowTreeByPid(int $pid, array $map): void
{
    $app = findApplicationByPid($pid);
    if ($app === null) {
        return;
    }
    [$appBusName, $appObjectPath] = $app;

    $window = findWindowInApp($appBusName, $appObjectPath);
    if ($window === null) {
        return;
    }
    [$windowBusName, $windowObjectPath] = $window;

    checkVisibleTree($windowBusName, $windowObjectPath, 0, $map);
}

function getActiveWindowTree(): void
{
    $registryBusName = "org.a11y.atspi.Registry";
    $rootObjectPath = "/org/a11y/atspi/accessible/root";

    $applicationCountRaw = callAccessibilityMethod($registryBusName, $rootObjectPath, 'org.freedesktop.DBus.Properties', 'Get', 'org.a11y.atspi.Accessible ChildCount');
    if ($applicationCountRaw === null || !preg_match("/<(\d+)>/", $applicationCountRaw, $applicationCountMatches)) {
        return;
    }
    $applicationCount = (int)$applicationCountMatches[1];

    for ($appIterator = 0; $appIterator < $applicationCount; $appIterator++) {
        $applicationRaw = callAccessibilityMethod($registryBusName, $rootObjectPath, 'org.a11y.atspi.Accessible', 'GetChildAtIndex', (string)$appIterator);

        if ($applicationRaw === null || !preg_match("/['\"]([^'\"]*)['\"],\s*(?:objectpath\s+)?['\"]([^'\"]*)['\"]/", $applicationRaw, $applicationMatches)) {
            continue;
        }

        $applicationBusName = $applicationMatches[1];
        $applicationObjectPath = $applicationMatches[2];

        $windowCountRaw = callAccessibilityMethod($applicationBusName, $applicationObjectPath, 'org.freedesktop.DBus.Properties', 'Get', 'org.a11y.atspi.Accessible ChildCount');
        if ($windowCountRaw === null || !preg_match("/<(\d+)>/", $windowCountRaw, $windowCountMatches)) {
            continue;
        }
        $windowCount = (int)$windowCountMatches[1];

        for ($windowIterator = 0; $windowIterator < $windowCount; $windowIterator++) {
            $windowRaw = callAccessibilityMethod($applicationBusName, $applicationObjectPath, 'org.a11y.atspi.Accessible', 'GetChildAtIndex', (string)$windowIterator);

            if ($windowRaw === null || !preg_match("/['\"]([^'\"]*)['\"],\s*(?:objectpath\s+)?['\"]([^'\"]*)['\"]/", $windowRaw, $windowMatches)) {
                continue;
            }

            $windowBusName = $windowMatches[1];
            $windowObjectPath = $windowMatches[2];

            $windowStateRaw = callAccessibilityMethod($windowBusName, $windowObjectPath, 'org.a11y.atspi.Accessible', 'GetState');
            if ($windowStateRaw === null) {
                continue;
            }

            $windowStates = extractStateInts($windowStateRaw);

            $isWindowActive = false;
            if (isset($windowStates[0]) && ($windowStates[0] & (1 << ACCESSIBILITY_STATE_ACTIVE)) !== 0) {
                $isWindowActive = true;
            }

            if ($isWindowActive === true) {
                $windowNameRaw = callAccessibilityMethod($windowBusName, $windowObjectPath, 'org.freedesktop.DBus.Properties', 'Get', 'org.a11y.atspi.Accessible Name');
                $windowName = 'Nepoznat Prozor';
                if ($windowNameRaw !== null) {
                    $extracted = extractQuotedString($windowNameRaw);
                    if ($extracted !== null) {
                        $windowName = $extracted;
                    }
                }

                echo "--- Accerciser Dump for: {$windowName} ---\n";
                checkVisibleTree($windowBusName, $windowObjectPath);
                return;
            }
        }
    }
}
