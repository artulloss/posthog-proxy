<?php

/**
 * Plugin Name: PostHog Proxy
 * Description: Reverse proxy for forwarding analytics requests to PostHog from `/ingest/*`.
 * Version: 1.0
 * Author: Adam Tulloss
 */

namespace PosthogProxy;

defined('ABSPATH') || exit;

final class Plugin
{

    public static function init(): void
    {
        add_action('init', [self::class, 'add_rewrite_rule']);
        add_filter('query_vars', [self::class, 'register_query_var']);
        add_action('template_redirect', [self::class, 'maybe_handle_proxy']);
    }

    public static function add_rewrite_rule(): void
    {
        add_rewrite_rule(
            '^ingest/(.*)',
            'index.php?posthog_proxy_path=$matches[1]',
            'top'
        );
    }

    /**
     * @param array $vars
     * @return array
     */
    public static function register_query_var(array $vars): array
    {
        $vars[] = 'posthog_proxy_path';
        return $vars;
    }

    public static function maybe_handle_proxy(): void
    {
        $path = get_query_var('posthog_proxy_path');
        if ($path !== '') {
            include plugin_dir_path(__FILE__) . 'proxy.php';
            exit;
        }
    }
}

Plugin::init();