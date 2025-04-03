<?php

namespace PosthogProxy;

defined('ABSPATH') || exit;

/**
 * Handles the proxy request.
 *
 * This function reconstructs the target URL based on the query var set by the rewrite rule,
 * forwards the incoming headers (adjusting the Host header), and returns the response.
 */
function handle_proxy_request(): void {
    // Get the proxy path (e.g. "static/recorder.js")
    $path = get_query_var('posthog_proxy_path') ?: '';
    $proxied_path = '/' . ltrim($path, '/');

    // Rebuild query string from remaining GET parameters (if any)
    $params = $_GET;
    unset($params['posthog_proxy_path']);
    $query_string = http_build_query($params);

    // Decide which hostname to use based on the path
    $hostname = (strpos($proxied_path, '/static/') === 0)
        ? 'us-assets.i.posthog.com'
        : 'us.i.posthog.com';

    // Build the target URL
    $target_url = "https://{$hostname}{$proxied_path}" . ($query_string ? '?' . $query_string : '');

    // Uncomment for debugging:
    // error_log("PostHog Proxy: Target URL = " . $target_url);

    $ch = curl_init($target_url);

    // Forward all headers except "host" and add our Host header.
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
        $body = file_get_contents('php://input');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);

    if ($response === false) {
        status_header(500);
        echo 'Proxy error: ' . curl_error($ch);
        curl_close($ch);
        exit;
    }

    $header_size    = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $raw_headers    = substr($response, 0, $header_size);
    $response_body  = substr($response, $header_size);

    // Determine content type – first try the cURL info, then fallback based on file extension.
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    if ($content_type) {
        header("Content-Type: " . $content_type);
    } else {
        $ext = strtolower(pathinfo($proxied_path, PATHINFO_EXTENSION));
        $mime_types = [
            'js'   => 'application/javascript',
            'css'  => 'text/css',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
        ];
        if (isset($mime_types[$ext])) {
            header("Content-Type: " . $mime_types[$ext]);
        }
    }

    // Forward other headers (skip Transfer-Encoding, Content-Length, and Content-Type already set)
    $response_headers = explode("\r\n", $raw_headers);
    foreach ($response_headers as $header) {
        if (
            stripos($header, 'Transfer-Encoding:') === false &&
            stripos($header, 'Content-Length:') === false &&
            stripos($header, 'Content-Type:') === false &&
            !empty($header)
        ) {
            header($header, false);
        }
    }
    header('Content-Length: ' . strlen($response_body));

    curl_close($ch);
    echo $response_body;
    exit;
}

handle_proxy_request();
