<?php
$incoming_path = $_SERVER['REQUEST_URI'];
$proxied_path = preg_replace('#^/ingest#', '', $incoming_path);

$hostname = strpos($proxied_path, '/static/') === 0
    ? 'us-assets.i.posthog.com'
    : 'us.i.posthog.com';

$target_url = "https://{$hostname}{$proxied_path}";

$ch = curl_init($target_url);

$headers = [];
foreach (getallheaders() as $key => $value) {
    if (strtolower($key) !== 'host') {
        $headers[] = "$key: $value";
    }
}
$headers[] = "Host: $hostname";

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
}

$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers_raw = substr($response, 0, $header_size);
$body = substr($response, $header_size);

$headers_array = explode("\r\n", $headers_raw);
foreach ($headers_array as $header) {
    if (stripos($header, 'Transfer-Encoding:') === false && stripos($header, 'Content-Length:') === false) {
        header($header, false);
    }
}
header('Content-Length: ' . strlen($body));

curl_close($ch);

echo $body;