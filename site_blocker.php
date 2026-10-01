<?php

require_once __DIR__ . '/common.php';
$initialBlockedWebsites = null;

while (true) {

    $settings = Settings::loadSettings();
    $blockedWebsites = $settings['blockedWebsites'] ?? [];

    if (!is_array($blockedWebsites)) {
        $blockedWebsites = [];
    }

    $alwaysBlocked = [
        'chess.com',
        'lichess.org',
        'chesspuzzle.net',
        'chesstempo.com',
        'worldchess.com',
        'wintrchess.com',
        'chesscompass.com',
        'chesspuzzler.com',
        'chesskid.com',
        'chessmood.com',
        'q3js.com',
        'dos.zone'
    ];

    $blockedWebsites = array_merge($blockedWebsites, $alwaysBlocked);
    $blockedWebsites = array_unique($blockedWebsites);

    if (is_null($initialBlockedWebsites)) {
        $initialBlockedWebsites = $blockedWebsites;
    }

    $removedWebsites = array_diff($initialBlockedWebsites, $blockedWebsites);
    if (!empty($removedWebsites)) {
        Logger::log('hosts', 'Removed websites detected, reapplying block: ' . implode(', ', $removedWebsites));
        $initialBlockedWebsites = $blockedWebsites;
    }

    checkHosts($blockedWebsites);

    sleep($settings['refreshInterval']);
}

function checkHosts(array $websites)
{

    $hostsFile = '/etc/hosts';

    $hostsContents = file_get_contents($hostsFile);

    $blockContents = "\n# BLOCK MANAGED BY WORK_LOG\n# EVERYTHING BELOW THIS BLOCK WILL BE DELETED\n";
    $firstLineStart = strpos($hostsContents, $blockContents);

    foreach ($websites as $website) {
        $blockContents .= buildHostsLine($website);
    }

    $blockContents .= "\n# END BLOCK";
    $blockStart = strpos($hostsContents, $blockContents);
    if ($blockStart !== false) {
        return;
    }

    if ($firstLineStart === false) {
        $hostsContents .= $blockContents;
    } else {
        $hostsContents = substr($hostsContents, 0, $firstLineStart) . $blockContents;
    }

    file_put_contents($hostsFile, $hostsContents);
}

function buildHostsLine(string $website, bool $addWww = true)
{
    $result = "\n127.0.0.1\t" . $website;
    if ($addWww) {
        $result .= "\n127.0.0.1\twww." . $website;
    }
    return $result;
}

function snapshotFiles(): array
{
    $root = __DIR__;

    $snapshot = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink()) {
            continue;
        }

        $path = $file->getPathname();

        $contents = file_get_contents($path);

        if ($contents === false) {
            continue;
        }

        $snapshot[$path] = $contents;
    }

    return $snapshot;
}

function isImmutable(string $path): bool
{
    $output = [];
    $exitCode = 0;

    exec(
        'lsattr -d ' . escapeshellarg($path) . ' 2>/dev/null',
        $output,
        $exitCode
    );

    if ($exitCode !== 0 || empty($output)) {
        return false;
    }

    return isset($output[0][4]) && $output[0][4] === 'i';
}

function makeImmutable(string $path): void
{
    exec(
        'sudo chattr +i -- ' . escapeshellarg($path) . ' 2>/dev/null',
        $output,
        $exitCode
    );
}

// $snapshot = snapshotFiles();

// while (true) {
//     sleep(10);

//     foreach ($snapshot as $path => $originalContents) {
//         if (!file_exists($path)) {
//             file_put_contents($path, $originalContents);

//             makeImmutable($path);

//             continue;
//         }

//         $currentContents = file_get_contents($path);

//         if ($currentContents === false) {
//             continue;
//         }

//         if (!hash_equals(
//             hash('sha256', $originalContents),
//             hash('sha256', $currentContents)
//         )) {
//             if (isImmutable($path)) {
//                 exec(
//                     'chattr -i -- ' . escapeshellarg($path)
//                 );
//             }

//             file_put_contents($path, $originalContents);

//             makeImmutable($path);
//         }

//         if (!isImmutable($path)) {
//             makeImmutable($path);
//         }
//     }
// }