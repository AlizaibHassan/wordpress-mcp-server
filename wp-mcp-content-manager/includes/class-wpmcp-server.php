<?php
/**
 * MCP server: speaks JSON-RPC 2.0 over the WordPress REST API using the
 * Streamable HTTP transport (single POST endpoint, JSON responses).
 *
 * Endpoint: /wp-json/wpmcp/v1/mcp
 *
 * @package WPMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the MCP protocol handshake and tool dispatch.
 */
class WPMCP_Server {

	const PROTOCOL_VERSION = '2025-06-18';

	/**
	 * Tool registry / executor.
	 *
	 * @var WPMCP_Tools
	 */
	private $tools;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->tools = new WPMCP_Tools();
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			WPMCP_REST_NAMESPACE,
			'/mcp',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle' ),
					'permission_callback' => array( 'WPMCP_Auth', 'check' ),
				),
				// A bare GET is used by some clients to probe the endpoint.
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'handle_probe' ),
					'permission_callback' => array( 'WPMCP_Auth', 'check' ),
				),
			)
		);

		// Lightweight unauthenticated health check.
		register_rest_route(
			WPMCP_REST_NAMESPACE,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					return new WP_REST_Response(
						array(
							'status'      => 'ok',
							'server'      => 'wp-mcp-server-by-az',
							'version'     => WPMCP_VERSION,
							'protocol'    => self::PROTOCOL_VERSION,
							'acf_active'  => function_exists( 'get_field' ),
						),
						200
					);
				},
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * GET probe — the Streamable HTTP transport allows an empty 200 here.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_probe() {
		return new WP_REST_Response( null, 200 );
	}

	/**
	 * Main JSON-RPC dispatcher.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function handle( $request ) {
		$body = $request->get_json_params();

		// Batched requests.
		if ( is_array( $body ) && isset( $body[0] ) ) {
			$responses = array();
			foreach ( $body as $message ) {
				$resp = $this->dispatch( $message );
				if ( null !== $resp ) {
					$responses[] = $resp;
				}
			}
			return new WP_REST_Response( $responses, 200 );
		}

		$response = $this->dispatch( is_array( $body ) ? $body : array() );

		// Notifications return no body.
		if ( null === $response ) {
			return new WP_REST_Response( null, 202 );
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Route a single JSON-RPC message.
	 *
	 * @param array $message Decoded JSON-RPC message.
	 * @return array|null Response array, or null for notifications.
	 */
	private function dispatch( $message ) {
		$method = isset( $message['method'] ) ? $message['method'] : '';
		$id     = isset( $message['id'] ) ? $message['id'] : null;
		$params = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : array();

		// Notifications (no id) — acknowledge silently.
		if ( null === $id && 0 === strpos( $method, 'notifications/' ) ) {
			return null;
		}

		switch ( $method ) {
			case 'initialize':
				return $this->result(
					$id,
					array(
						'protocolVersion' => isset( $params['protocolVersion'] ) ? $params['protocolVersion'] : self::PROTOCOL_VERSION,
						'capabilities'    => array(
							'tools' => array( 'listChanged' => false ),
						),
						'serverInfo'      => array(
							'name'    => 'wp-mcp-server-by-az',
							'version' => WPMCP_VERSION,
						),
						'instructions'    => 'Read and update WordPress content. Use list_content / search_content to find items, get_content to inspect a page (including ACF fields), then update_content, update_acf_fields, or bulk_update_content to make changes.',
					)
				);

			case 'ping':
				return $this->result( $id, array() );

			case 'tools/list':
				return $this->result( $id, array( 'tools' => $this->tools->definitions() ) );

			case 'tools/call':
				return $this->handle_tool_call( $id, $params );

			default:
				return $this->error( $id, -32601, 'Method not found: ' . $method );
		}
	}

	/**
	 * Execute a tool call and wrap the result for MCP.
	 *
	 * @param mixed $id     JSON-RPC id.
	 * @param array $params Call params (name, arguments).
	 * @return array
	 */
	private function handle_tool_call( $id, $params ) {
		$name = isset( $params['name'] ) ? $params['name'] : '';
		$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

		// Enforce the write / delete safety toggles before doing any work.
		$category = $this->tools->category( $name );
		if ( 'write' === $category && ! WPMCP_Settings::get( 'allow_write', true ) ) {
			WPMCP_Logger::record( $name, $args, 'blocked', WPMCP_Auth::actor() );
			return $this->tool_error( $id, __( 'Write operations are disabled by the site administrator.', 'wp-mcp-content-manager' ) );
		}
		if ( 'delete' === $category && ! WPMCP_Settings::get( 'allow_delete', false ) ) {
			WPMCP_Logger::record( $name, $args, 'blocked', WPMCP_Auth::actor() );
			return $this->tool_error( $id, __( 'Delete operations are disabled by the site administrator.', 'wp-mcp-content-manager' ) );
		}

		$result = $this->tools->call( $name, $args );

		WPMCP_Logger::record( $name, $args, is_wp_error( $result ) ? 'error' : 'success', WPMCP_Auth::actor() );

		if ( is_wp_error( $result ) ) {
			// Tool-level error: return as a successful JSON-RPC response with isError.
			return $this->result(
				$id,
				array(
					'content' => array(
						array(
							'type' => 'text',
							'text' => $result->get_error_message(),
						),
					),
					'isError' => true,
				)
			);
		}

		return $this->result(
			$id,
			array(
				'content'           => array(
					array(
						'type' => 'text',
						'text' => wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
					),
				),
				'structuredContent' => $result,
				'isError'           => false,
			)
		);
	}

	/**
	 * Build a tool-level error result (valid JSON-RPC success with isError=true).
	 *
	 * @param mixed  $id      Request id.
	 * @param string $message Error text.
	 * @return array
	 */
	private function tool_error( $id, $message ) {
		return $this->result(
			$id,
			array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => $message,
					),
				),
				'isError' => true,
			)
		);
	}

	/**
	 * Build a JSON-RPC success envelope.
	 *
	 * @param mixed $id     Request id.
	 * @param mixed $result Result payload.
	 * @return array
	 */
	private function result( $id, $result ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * Build a JSON-RPC error envelope.
	 *
	 * @param mixed  $id      Request id.
	 * @param int    $code    Error code.
	 * @param string $message Error message.
	 * @return array
	 */
	private function error( $id, $code, $message ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}
}
