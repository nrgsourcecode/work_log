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
