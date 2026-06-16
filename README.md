# WP MCP Server By AZ — Control WordPress with Claude & AI Agents (Model Context Protocol)

> **WP MCP Server By AZ** is a free, open-source WordPress plugin that turns any site into a secure **Model Context Protocol (MCP)** server. Connect any MCP-compatible AI agent — and read, create, and update **pages, posts, custom post types, ACF fields, media, taxonomies, and SEO meta** using natural language, one item at a time or in bulk.

[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net/)
[![MCP](https://img.shields.io/badge/MCP-Streamable_HTTP-7c3aed.svg)](https://modelcontextprotocol.io/)
[![PRs welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](#contributing)

WP MCP Server By AZ lets an AI assistant manage your WordPress content the way a human editor would — but through a single, authenticated API endpoint. Ask Claude to *"update the hero heading on the homepage,"* *"fix the meta description on every service page,"* or *"consolidate these five pages and redirect the old URLs,"* and it happens on your site.

---

## Table of contents

- [Why WP MCP Server By AZ](#why-wp-mcp-server-by-az)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Connecting Claude](#connecting-claude)
- [Available tools](#available-tools)
- [Usage examples](#usage-examples)
- [Security & safety controls](#security--safety-controls)
- [How it works](#how-it-works)
- [FAQ](#faq)
- [Contributing](#contributing)
- [License](#license)
- [Disclaimer](#disclaimer)

## Why WP MCP Server By AZ

Most "AI for WordPress" plugins bolt a chatbot onto the admin. WP MCP Server By AZ does the opposite: it exposes WordPress **to the AI client you already use**, over the open Model Context Protocol. That means:

- **Bring your own AI** — Claude Code, the Claude desktop/web app, or any MCP client. No vendor lock-in.
- **Real editing, not suggestions** — the AI calls typed tools that map to native WordPress and ACF APIs.
- **Safe by design** — API-key or OAuth auth, granular write/delete switches, rate limiting, and a full audit log.

## Features

- 📝 **Content** — create/update title, body (HTML/blocks), excerpt, slug, status, parent, template
- 🧩 **ACF** — read/write any Advanced Custom Fields value (incl. repeaters & groups) and discover field groups
- 🗂️ **Any custom field** — generic post-meta tools work with MetaBox, Pods, JetEngine, CPT UI and core meta
- 🖼️ **Media** — upload from URL or base64, set alt text/caption, assign featured images
- 🏷️ **Taxonomies** — list categories/tags/custom taxonomies and assign terms
- 🔎 **SEO** — read/write SEO title, description, focus keyword, canonical — auto-detects **Yoast** or **Rank Math**
- 🔁 **Bulk** — update many items in one call, or find-and-replace across the site (dry-run by default)
- ↪️ **Redirects** — built-in 301/302 manager for content consolidation
- ⏮️ **Revisions** — list and roll back to any previous version
- 🔐 **OAuth 2.0** — one-click connection from the Claude app (Dynamic Client Registration + PKCE)
- 🛡️ **Safety** — master switch, write/delete toggles, per-IP rate limiting, audit log with secret redaction

Works with **any theme** and **custom post types**, and detects ACF / Yoast / Rank Math / WooCommerce automatically.

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress   | 5.8+ |
| PHP         | 7.4+ |
| ACF         | Optional (only for custom-field editing) |
| AI client   | Claude Code, Claude desktop/web app, or any MCP client (Streamable HTTP) |

## Installation

### From a release ZIP
1. Download `wp-mcp-content-manager.zip` from the [Releases](../../releases) page.
2. WP Admin → **Plugins → Add New → Upload Plugin** → choose the ZIP → **Install** → **Activate**.
3. Open **Settings → WP MCP** for your endpoint URL and API key (generated automatically on activation).

### From source (Git)
```bash
git clone https://github.com/brandnorth/wp-mcp-server-by-az.git
# copy the plugin folder into your site
cp -r wp-mcp-server-by-az/wp-mcp-content-manager /path/to/wp-content/plugins/
```
Then activate it in WP Admin.

## Connecting Claude

### Claude desktop / web app (one-click OAuth — recommended)
1. **Claude → Settings → Connectors → Add custom connector**
2. URL: `https://YOUR-SITE.com/wp-json/wpmcp/v1/mcp`
3. Claude registers itself, then sends you to a WordPress sign-in + consent screen. Approve — done.

### Claude Code (CLI)
```bash
claude mcp add --transport http wordpress \
  https://YOUR-SITE.com/wp-json/wpmcp/v1/mcp \
  --header "Authorization: Bearer YOUR_API_KEY"
```

### Verify
```bash
curl https://YOUR-SITE.com/wp-json/wpmcp/v1/health
```

## Available tools

| Tool | Type | Purpose |
|------|------|---------|
| `list_post_types`, `list_content`, `search_content`, `get_content` | read | Discover & read content |
| `get_site_info` | read | Site name, theme, version, active integrations |
| `update_content`, `create_content`, `bulk_update_content`, `search_replace_content` | write | Edit & create content |
| `delete_content` | delete | Trash or permanently delete |
| `get_acf_fields`, `list_acf_field_groups` | read | Read ACF & discover field groups |
| `update_acf_fields` | write | Update ACF fields |
| `get_post_meta`, `update_post_meta` | read / write | Any custom field (MetaBox, Pods, JetEngine, CPT UI, core) |
| `list_media`, `upload_media`, `update_media`, `set_featured_image` | read / write | Media library |
| `list_taxonomies`, `get_terms`, `set_post_terms` | read / write | Taxonomies & terms |
| `list_revisions`, `restore_revision` | read / write | Version history & rollback |
| `get_seo_meta`, `update_seo_meta` | read / write | SEO meta (Yoast / Rank Math) |
| `list_redirects`, `add_redirect`, `delete_redirect` | read / write | 301/302 redirect manager |

> **Type** maps to the admin safety toggles: `write` tools require *Allow write operations*; `delete` tools require *Allow delete operations* (off by default).

## Usage examples

- *"Find the 'About Us' page and rewrite the intro to be friendlier."*
- *"Update the `cta_heading` ACF field on the homepage to 'Book a free demo'."*
- *"Set the Rank Math meta description on every service page to mention free delivery."*
- *"Consolidate these 10 status-check pages into one guide and 301-redirect the old URLs."*
- *"Upload this image from a URL, set its alt text, and make it the featured image of post 412."*

## Security & safety controls

- **Three auth methods** — plugin API key (`Authorization: Bearer` / `X-API-Key`), OAuth 2.0 access tokens (PKCE), or a WordPress Application Password / logged-in user. Tokens compared in constant time (`hash_equals`).
- **Capability checks** — OAuth/user sessions enforce per-post `edit_post`; the OAuth consent screen requires `edit_pages`.
- **Master + write/delete toggles** — go read-only, or block deletes (off by default).
- **Per-IP rate limiting** — configurable requests/minute on **both** the `/mcp` endpoint and the OAuth endpoints (separate buckets; HTTP 429 when exceeded).
- **SSRF protection** — `upload_media` only fetches public `http(s)` URLs; private, local and reserved addresses (e.g. `127.0.0.1`, `169.254.169.254`, RFC1918) and non-standard ports are blocked via `wp_http_validate_url()`.
- **OAuth hardening** — PKCE **S256 only** (no `plain` downgrade), strict `redirect_uri` allowlist (no open redirect), single-use authorization codes, `state` preserved.
- **Audit log** — every tool call recorded (actor, IP, outcome); passwords/tokens/emails redacted.
- **Always serve over HTTPS** so tokens never travel in clear text.

### Data storage (at rest)

Nothing usable is stored in plaintext in the database:

| Item | How it's stored |
|------|-----------------|
| **API key** | **SHA-256 hash only**, plus a masked hint (`wpmcp_b719…d9411`). The full key is revealed **once** at generation/rotation via a short-lived transient, then never shown again — rotate to get a new one. |
| **OAuth access & refresh tokens** | **SHA-256 hash** (raw token never persisted) |
| **OAuth authorization codes** | **SHA-256 hash**, single-use, 10-minute TTL |
| **OAuth clients** | client id, name, redirect URIs (public PKCE clients — no secret) |
| **Audit log** | tool calls with sensitive values (`password`, `token`, `api_key`, `email`) redacted and long strings truncated |
| **Settings / redirects** | non-sensitive config only |

> Because the API key is hashed, it cannot be retrieved later — not even by an admin. If you lose it, click **Rotate API Key** to generate (and copy) a new one.

## How it works

WP MCP Server By AZ registers one REST route — `/wp-json/wpmcp/v1/mcp` — speaking **JSON-RPC 2.0** over MCP's **Streamable HTTP** transport (`initialize`, `tools/list`, `tools/call`). Each tool maps to native WordPress/ACF APIs. OAuth discovery (`/.well-known/oauth-protected-resource`, `/.well-known/oauth-authorization-server`) and the authorize/token/register endpoints enable one-click client setup.

```
Claude / MCP client ──HTTP POST (JSON-RPC)──► /wp-json/wpmcp/v1/mcp ──► WordPress + ACF + SEO
```

## FAQ

### What is an MCP server for WordPress?
It's a server-side endpoint implementing the Model Context Protocol, letting AI agents discover and call typed tools to read and modify your WordPress site through a single authenticated URL.

### Do I need Advanced Custom Fields?
No — standard content works without it. ACF is required only for custom-field editing and is detected automatically.

### Which AI clients are supported?
Claude Code and the Claude desktop/web apps (via custom connector), plus any MCP client using the Streamable HTTP transport.

### Is it safe to let AI edit my live site?
It enforces API-key/OAuth auth, capability checks, and admin-controlled write/delete switches. Test on staging first, keep backups, and use the `search_replace_content` dry-run to preview bulk edits.

### How do I revoke access?
Rotate the API key, disable OAuth, or deactivate the plugin — any of these cuts off access immediately.

### Does it work with custom post types and WooCommerce?
Yes to custom post types (any public type). WooCommerce is detected; dedicated commerce tools are on the roadmap.

## Contributing

Contributions are welcome! 🎉

1. Fork the repo and create a feature branch.
2. Follow WordPress PHP coding standards; keep functions small and documented.
3. Run `php -l` on changed files (and `phpcs` if you have the WordPress ruleset).
4. Open a pull request describing the change and how you tested it.

Found a bug or have an idea? Please [open an issue](../../issues). For security reports, please disclose privately rather than in a public issue.

## License

Released under the **GPL-2.0-or-later** license — see [LICENSE](LICENSE). You're free to use, modify, and redistribute it, including commercially, under the same license.

## Disclaimer

WP MCP Server By AZ grants programmatic write access to your WordPress content. You are responsible for who you give the API key/OAuth access to and for the changes an AI makes on your behalf. Always keep backups and test on a staging environment before production use. Provided "as is", without warranty of any kind.
