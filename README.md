# Posthog Proxy

Reverse Proxy for PostHog, installable as a WordPress plugin

## Usage

1. Install and enable the plugin
1. Update PostHog initalization

    ```json
    posthog.init('API_KEY',
        {
            api_host: '/ingest',
            ...
        }
    );
    ```
1. Save Permalinks (Settings -> Permalinks)

## [Why use a reverse proxy for PostHog?](https://posthog.com/docs/advanced/proxy#:~:text=Using%20a%20reverse%20proxy%20means,complete%20view%20of%20your%20users.)

> Using a reverse proxy means that events are less likely to be intercepted by tracking blockers.
> You'll be able to capture more usage data without having to self-host PostHog, ensuring you get a complete view of your users.
