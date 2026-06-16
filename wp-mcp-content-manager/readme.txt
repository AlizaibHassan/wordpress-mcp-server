=== WP MCP Server By AZ ===
Contributors: brandnorth
Tags: mcp, claude, ai, acf, content editing, model context protocol, rest api
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn WordPress into a Model Context Protocol (MCP) server so Claude can read and update pages, posts, custom post types and ACF fields — single or in bulk.

== Description ==

WP MCP Server By AZ exposes a secure MCP endpoint on your WordPress site. Connect Claude (Claude Code or the desktop/web app via a custom connector) and edit content in natural language.

Features:

* Update page/post content: title, body, excerpt, slug, status, parent, template
* Read and write ACF (Advanced Custom Fields) values, including repeaters, groups and field-group discovery
* Edit any custom field (MetaBox, Pods, JetEngine, CPT UI, core meta) via generic post-meta tools
* Manage media: upload from URL or base64, set alt text, assign featured images
* Manage taxonomies: list categories/tags/custom taxonomies and assign terms
* Edit SEO metadata with Yoast or Rank Math auto-detection
* Bulk update many pages in a single instruction
* Search and replace across the site with a safe dry-run preview
* List and restore post revisions
* Create and delete pages/posts
* One-click OAuth 2.0 connection (Dynamic Client Registration + PKCE) for the Claude app
* Safety controls: master switch, write/delete toggles, per-IP rate limiting, audit log
* Works with any theme and custom post types

== Installation ==

1. Upload the `wp-mcp-content-manager` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu.
3. Visit Settings -> WP MCP to copy your endpoint URL and API key.
4. Connect Claude using the provided command or custom-connector instructions.

== Frequently Asked Questions ==

= Do I need ACF? =
Only for editing custom fields. Standard content works without it. ACF is detected automatically.

= Is it secure? =
Every request requires a Bearer API key, an OAuth token, or a WordPress Application Password. The API key is stored only as a SHA-256 hash (shown once at generation), OAuth tokens and codes are hashed, media uploads are SSRF-guarded, and per-IP rate limiting applies. Always use HTTPS.

= Where is my API key? Why can't I see it again? =
For security the key is stored hashed and shown only once when generated or rotated. If you lose it, click Rotate API Key to generate a new one.

= Which Claude products work? =
Claude Code and the Claude desktop/web apps via custom connectors, plus any MCP client using the Streamable HTTP transport.

== Changelog ==

= 1.3.0 =
* Security: API key now stored as a SHA-256 hash with a one-time reveal (masked preview thereafter); existing keys migrated automatically.
* Security: OAuth authorization codes hashed at rest; SSRF guard on media upload; per-IP rate limiting extended to OAuth endpoints.

= 1.2.0 =
* Added 301/302 redirect manager and tools for content consolidation.

= 1.1.0 =
* Added OAuth 2.0 server (Dynamic Client Registration + PKCE) for one-click Claude app connection.
* New tools: media upload/management, taxonomies & terms, post revisions (list/restore), SEO meta (Yoast/Rank Math), generic post meta, ACF field-group discovery, site info.
* Added safety controls: master switch, write/delete toggles, per-IP rate limiting, audit log.

= 1.0.0 =
* Initial release: MCP server, content + ACF tools, bulk update, search/replace, admin settings.
