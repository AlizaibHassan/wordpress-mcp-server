<?php
/**
 * Main plugin orchestrator.
 *
 * @package WPMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the REST routes and admin UI together.
 */
class WPMCP_Plugin {

	/**
	 * MCP server instance.
	 *
	 * @var WPMCP_Server
	 */
	private $server;

	/**
	 * Admin settings page.
	 *
	 * @var WPMCP_Admin
	 */
	private $admin;

	/**
	 * OAuth 2.0 server.
	 *
	 * @var WPMCP_OAuth
	 */
	private $oauth;

	/**
	 * Redirect manager.
	 *
	 * @var WPMCP_Redirects
	 */
	private $redirects;

	/**
	 * Hook everything in.
	 */
	public function init() {
		$this->server    = new WPMCP_Server();
		$this->admin     = new WPMCP_Admin();
		$this->oauth     = new WPMCP_OAuth();
		$this->redirects = new WPMCP_Redirects();

		add_action( 'rest_api_init', array( $this->server, 'register_routes' ) );
		$this->oauth->init();
		$this->redirects->init();

		if ( is_admin() ) {
			$this->admin->init();
		}

		load_plugin_textdomain( 'wp-mcp-content-manager', false, dirname( plugin_basename( WPMCP_PLUGIN_FILE ) ) . '/languages' );
	}
}
