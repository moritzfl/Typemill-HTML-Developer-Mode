<?php

namespace Plugins\htmldeveloper;

use Typemill\Plugin;

class htmldeveloper extends Plugin
{
    public static function getSubscribedEvents()
    {
        return [
            'onCspLoaded'  => ['onCspLoaded', 0],
            'onHtmlLoaded' => ['onHtmlLoaded', 0],
        ];
    }

    /**
     * Add domains to Typemill's Content Security Policy so that external
     * resources inside rawhtml blocks are allowed to load.
     *
     * Configure allowed domains in the plugin settings in the Typemill admin
     * (Plugins → HTML Developer Mode → Allowed External Domains).
     *
     * One domain per line, or comma-separated. Use 'https:' to allow all
     * external HTTPS resources at once.
     */
    public function onCspLoaded($event)
    {
        $settings = $this->getPluginSettings();
        $raw      = isset($settings['csp_domains']) ? trim($settings['csp_domains']) : '';

        if ($raw === '') {
            return;
        }

        $candidates = array_filter(array_map('trim', explode("\n", str_replace(',', "\n", $raw))));
        $domains    = array_values(array_filter($candidates, [$this, 'isValidCspSource']));

        if (empty($domains)) {
            return;
        }

        $event->setData(array_merge($event->getData(), $domains));
    }

    /**
     * Accept only well-formed CSP source expressions:
     *   - CSP keywords:  'self', 'none', 'unsafe-inline', 'unsafe-eval'
     *   - Scheme-only:   https: http: data: blob:
     *   - Host with optional scheme and/or leading wildcard subdomain:
     *     example.com  *.example.com  https://example.com  https://*.example.com:8080
     */
    private function isValidCspSource(string $source): bool
    {
        if (in_array($source, ["'self'", "'none'", "'unsafe-inline'", "'unsafe-eval'"], true)) {
            return true;
        }

        // scheme-only (e.g. "https:" or "data:")
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*:$/', $source)) {
            return true;
        }

        // optional scheme + optional wildcard subdomain + hostname + optional port
        return (bool) preg_match(
            '/^(?:[a-zA-Z][a-zA-Z0-9+\-.]*:\/\/)?(?:\*\.)?[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*(?::\d{1,5})?$/',
            $source
        );
    }

    /**
     * Find every <pre><code class="language-rawhtml">…</code></pre> block
     * in the final HTML and replace it with the decoded raw HTML content.
     *
     * Parsedown always renders fenced code blocks as <pre><code> regardless
     * of safe mode, so this is the most reliable interception point.
     */
    public function onHtmlLoaded($event)
    {
        $html = $event->getData();

        if (!is_string($html) || strpos($html, 'language-rawhtml') === false) {
            return;
        }

        $result = preg_replace_callback(
            '/<pre[^>]*>\s*<code[^>]*class="[^"]*language-rawhtml[^"]*"[^>]*>(.*?)<\/code>\s*<\/pre>/s',
            function ($matches) {
                return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            },
            $html
        );

        if ($result !== null && $result !== $html) {
            $event->setData($result);
        }
    }
}
