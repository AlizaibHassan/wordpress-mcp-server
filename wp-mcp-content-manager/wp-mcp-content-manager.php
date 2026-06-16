<?php
/**
 * Plugin Name:       WP MCP Server By AZ
 * Plugin URI:        https://github.com/brandnorth/wp-mcp-server-by-az
 * Description:       Turn any WordPress site into a Model Context Protocol (MCP) server — let Claude and other AI agents read and update pages, posts, custom post types, ACF fields, media and SEO, single or in bulk, with OAuth and built-in safety controls.
 * Version:           1.3.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Brand North
 * Author URI:        https://brandnorth.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-mcp-content-manager
 *
 * @package WPMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'WPMCP_VERSION', '1.3.0' );
define( 'WPMCP_PLUGIN_FILE', __FILE__ );
define( 'WPMCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPMCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPMCP_REST_NAMESPACE', 'wpmcp/v1' );

require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-settings.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-logger.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-auth.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-oauth.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-redirects.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-tools.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-server.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-admin.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-plugin.php';

/**
 * Boot the plugin.
 */
function wpmcp() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new WPMCP_Plugin();
		$instance->init();
	}
	return $instance;
}

add_action( 'plugins_loaded', 'wpmcp' );

// Generate a default API key on activation so the endpoint is never wide open.
register_activation_hook( __FILE__, array( 'WPMCP_Auth', 'on_activation' ) );
