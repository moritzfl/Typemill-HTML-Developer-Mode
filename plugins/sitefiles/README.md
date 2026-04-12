# Site Files — Typemill Plugin

A Typemill plugin that serves a public `robots.txt` and exposes the generated Typemill sitemap at the conventional root
path `/sitemap.xml`.

## Installation

See [Installation in the project README](../../README.md#installation).

## What It Does

The plugin registers two public frontend routes:

| Route          | Output                                                                 |
|----------------|------------------------------------------------------------------------|
| `/robots.txt`  | A plain-text robots file generated from the current site base URL      |
| `/sitemap.xml` | The Typemill sitemap XML served from the root path instead of `/cache` |

This is useful because Typemill itself generates the sitemap in `cache/sitemap.xml`, but many crawlers, tools, and
hosting setups expect the sitemap at `/sitemap.xml`.

## Usage

Activate the plugin in **Plugins**. No further setup is required.

After activation, the following URLs should work immediately:

```text
https://yoursite.com/robots.txt
https://yoursite.com/sitemap.xml
```

## Robots.txt Output

The generated `robots.txt` contains:

```text
User-agent: *
Allow: /
Disallow: /tm/

Sitemap: https://yoursite.com/sitemap.xml
```

The sitemap URL is derived from Typemill's current `baseurl`, so it follows the active site domain automatically.

## Configuration

The plugin provides one setting:

| Setting            | Purpose                                                          |
|--------------------|------------------------------------------------------------------|
| `extra_rules`      | Appends additional raw lines to `robots.txt`                     |

Example `extra_rules` value:

```text
Disallow: /private/
Crawl-delay: 10
```

Each non-empty line is appended verbatim below the default rules.

The admin area path `/tm/` is always disallowed by the plugin.

## Sitemap Behavior

The plugin does not replace Typemill's sitemap generator. Instead it:

1. Tries to read the existing sitemap from Typemill's cache folder.
2. If the sitemap file is missing, it triggers Typemill's own sitemap generation once.
3. Returns the XML at `/sitemap.xml`.

This keeps the sitemap content aligned with Typemill core while making it available at a more standard public URL.

## Notes

- The plugin only adds public frontend routes. It does not change Typemill's editor or page rendering.
- Responses are served with a short public cache header (`max-age=300`).
