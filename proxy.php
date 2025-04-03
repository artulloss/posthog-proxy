<?php

namespace PosthogProxy;

defined('ABSPATH') || exit;

/**
 * Reverse proxy to PostHog domains from /ingest/ path.
 * - /ingest/static/* -> us-assets.i.posthog.com/static/*
 * - /ingest/*        -> us.i.posthog.com/*
 */

function handle_proxy_request(): void {
    // Get the path passed via rewrite rule
    $path = get_query_var('posthog_proxy_path') ?: '';
    $proxied_path = '/' . ltrim($path, '/');

    // Preserve all query parameters except the internal one
    $params = $_GET;
    unset($params['posthog_proxy_path']);
    $query_string = http_build_query($params);

    // Determine target host and base URL
    if (strpos($proxied_path, '/static/') === 0) {
        $target_url = "https://us-assets.i.posthog.com" . $proxied_path;
        $host_header = "us-assets.i.posthog.com";
    } else {
        $target_url = "https://us.i.posthog.com" . $proxied_path;
        $host_header = "us.i.posthog.com";
    }

    if ($query_string) {
        $target_url .= '?' . $query_string;
    }

    $ch = curl_init($target_url);

    // Forward all headers except Host
    $headers = [];
    foreach (getallheaders() as $key => $value) {
        if (strtolower($key) !== 'host') {
            $headers[] = "$key: $value";
        }
    }
    $headers[] = "Host: $host_header";

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);

    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
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

    // Set content-type if available
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    if ($content_type) {
        header("Content-Type: $content_type");
    }

    // Forward other headers (skip encoding/content-length since we set them)
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
