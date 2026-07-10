<?php

$chrome_url_path = sys_get_temp_dir() . '/work_log_chrome_url.txt';

function start_server()
{
    $output = [];
    $host = '127.0.0.1';
    $json = json_decode(file_get_contents(__DIR__ . '/setup/chrome-extension/config.json'), true);
    $port = $json['server']['port'];
    $document_root = __DIR__;

    $command = "php -S $host:$port -t $document_root > /dev/null 2>&1 &";
    exec($command . " 2>&1", $output);
}

function get_chrome_url()
{
    global $chrome_url_path;

    if (!file_exists($chrome_url_path)) {
        return null;
    }
    $url = file_get_contents($chrome_url_path);
    return trim($url);
}

$request_method = $_SERVER['REQUEST_METHOD'] ?? null;
$remote_address = $_SERVER['REMOTE_ADDR'] ?? null;

if ($request_method === 'POST' && $remote_address === '127.0.0.1') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    file_put_contents($chrome_url_path, $data['url'] ?? '');
}
