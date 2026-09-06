<?php

$chromeUrlPath = sys_get_temp_dir() . '/work_log_chrome_url.txt';

function startServer()
{
    $output = [];
    $host = '127.0.0.1';
    $json = json_decode(file_get_contents(__DIR__ . '/setup/chrome-extension/config.json'), true);
    $port = $json['server']['port'];
    $documentRoot = __DIR__;

    $command = "php -S $host:$port -t $documentRoot > /dev/null 2>&1 &";
    exec($command . " 2>&1", $output);
}

function getChromeUrl()
{
    global $chromeUrlPath;

    if (!file_exists($chromeUrlPath)) {
        return null;
    }
    $url = file_get_contents($chromeUrlPath);
    return trim($url);
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;

if ($requestMethod === 'POST' && $remoteAddress === '127.0.0.1') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    file_put_contents($chromeUrlPath, $data['url'] ?? '');
}
