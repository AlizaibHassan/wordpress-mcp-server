<?php
/**
 * OAuth 2.0 authorization server for the MCP endpoint.
 *
 * Implements the pieces the MCP authorization spec needs so the Claude
 * desktop/web app can connect with one click (no manual key paste):
 *
 *   - RFC 9728  Protected Resource Metadata  (/.well-known/oauth-protected-resource)
 *   - RFC 8414  Authorization Server Metadata (/.well-known/oauth-authorization-server)
 *   - RFC 7591  Dynamic Client Registration  (/wpmcp-oauth/register)
 *   - OAuth 2.1 Authorization Code + PKCE     (/wpmcp-oauth/authorize, /wpmcp-oauth/token)
 *
 * Endpoints are matched on REQUEST_URI in an `init` handler so they work
 * regardless of permalink configuration.
 *
 * @package WPMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal, PKCE-only OAuth 2.0 server.
 */
class WPMCP_OAuth {

	const CLIENTS_OPTION = 'wpmcp_oauth_clients';
	const CODE_TTL       = 600;            // 10 minutes.
	const TOKEN_TTL      = 2592000;        // 30 days.
	const REFRESH_TTL    = 7776000;        // 90 days.
	const MAX_CLIENTS    = 50;

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'init', array( $this, 'route' ) );
	}

	/**
	 * Base path for the OAuth endpoints.
	 *
	 * @return string
	 */
	private function base() {
		return home_url( '/wpmcp-oauth' );
	}

	/**
	 * Match the request path and dispatch.
	 */
	public function route() {
		if ( ! WPMCP_Settings::get( 'oauth_enabled', true ) || ! WPMCP_Settings::get( 'enabled', true ) ) {
			return;
		}

		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		$path = untrailingslashit( $path );

		// The OAuth endpoints live under the WordPress home path (handles
		// subdirectory installs); the well-known docs must sit at the domain root.
		$home = untrailingslashit( wp_parse_url( home_url( '/wpmcp-oauth' ), PHP_URL_PATH ) );

		$endpoints = array(
			'/.well-known/oauth-protected-resource'   => 'protected_resource_metadata',
			'/.well-known/oauth-authorization-server' => 'authorization_server_metadata',
			$home . '/register'                       => 'register_client',
			$home . '/authorize'                      => 'authorize',
			$home . '/token'                          => 'token',
		);

		if ( ! isset( $endpoints[ $path ] ) ) {
			return; // Not one of ours — leave the request alone.
		}

		// Per-IP rate limit (separate bucket from /mcp) to curb registration
		// spam, token guessing and discovery hammering.
		$limit = (int) WPMCP_Settings::get( 'rate_limit', 60 );
		if ( WPMCP_Auth::throttle( 'oauth', $limit ) ) {
			$this->error_json( 'temporarily_unavailable', 'Rate limit exceeded. Please slow down and try again shortly.', 429 );
		}

		$handler = $endpoints[ $path ];
		$this->$handler();
	}

	/* --------------------------------------------------------------------- */
	/* Discovery                                                             */
	/* --------------------------------------------------------------------- */

	/**
	 * RFC 9728 — tells the client which authorization server protects /mcp.
	 */
	private function protected_resource_metadata() {
		$this->json(
			array(
				'resource'                 => rest_url( WPMCP_REST_NAMESPACE . '/mcp' ),
				'authorization_servers'    => array( home_url( '/' ) ),
				'bearer_methods_supported' => array( 'header' ),
				'scopes_supported'         => array( 'wordpress.edit' ),
			)
		);
	}

	/**
	 * RFC 8414 — advertises the OAuth endpoints and capabilities.
	 */
	private function authorization_server_metadata() {
		$this->json(
			array(
				'issuer'                                => home_url( '/' ),
				'authorization_endpoint'                => $this->base() . '/authorize',
				'token_endpoint'                        => $this->base() . '/token',
				'registration_endpoint'                 => $this->base() . '/register',
				'response_types_supported'              => array( 'code' ),
				'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
				'code_challenge_methods_supported'      => array( 'S256' ),
				'token_endpoint_auth_methods_supported' => array( 'none' ),
				'scopes_supported'                      => array( 'wordpress.edit' ),
			)
		);
	}

	/* --------------------------------------------------------------------- */
	/* Dynamic client registration (RFC 7591)                                */
	/* --------------------------------------------------------------------- */

	/**
	 * Register a public (PKCE) client and return its client_id.
	 */
	private function register_client() {
		if ( 'POST' !== $this->method() ) {
			$this->error_json( 'invalid_request', 'POST required.', 405 );
		}

		$body         = $this->json_body();
		$redirect_uris = isset( $body['redirect_uris'] ) && is_array( $body['redirect_uris'] ) ? $body['redirect_uris'] : array();
		$redirect_uris = array_values( array_filter( array_map( 'esc_url_raw', $redirect_uris ) ) );

		if ( empty( $redirect_uris ) ) {
			$this->error_json( 'invalid_redirect_uri', 'At least one redirect_uri is required.', 400 );
		}

		$client_id = 'wpmcp_client_' . bin2hex( random_bytes( 16 ) );
		$record    = array(
			'client_id'     => $client_id,
			'client_name'   => isset( $body['client_name'] ) ? sanitize_text_field( $body['client_name'] ) : 'MCP Client',
			'redirect_uris' => $redirect_uris,
			'created'       => time(),
		);

		$clients = $this->clients();
		// Evict oldest if at capacity.
		if ( count( $clients ) >= self::MAX_CLIENTS ) {
			uasort( $clients, function ( $a, $b ) {
				return $a['created'] <=> $b['created'];
			} );
			array_shift( $clients );
		}
		$clients[ $client_id ] = $record;
		update_option( self::CLIENTS_OPTION, $clients, false );

		$this->json(
			array(
				'client_id'                  => $client_id,
				'client_id_issued_at'        => $record['created'],
				'client_name'                => $record['client_name'],
				'redirect_uris'              => $redirect_uris,
				'grant_types'                => array( 'authorization_code', 'refresh_token' ),
				'response_types'             => array( 'code' ),
				'token_endpoint_auth_method' => 'none',
			),
			201
		);
	}

	/* --------------------------------------------------------------------- */
	/* Authorization endpoint                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * Authorize: log the WordPress user in, show consent, issue a code.
	 */
	private function authorize() {
		$client_id     = isset( $_GET['client_id'] ) ? sanitize_text_field( wp_unslash( $_GET['client_id'] ) ) : '';
		$redirect_uri  = isset( $_GET['redirect_uri'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_uri'] ) ) : '';
		$state         = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$challenge     = isset( $_GET['code_challenge'] ) ? sanitize_text_field( wp_unslash( $_GET['code_challenge'] ) ) : '';
		$challenge_m   = isset( $_GET['code_challenge_method'] ) ? sanitize_text_field( wp_unslash( $_GET['code_challenge_method'] ) ) : '';
		$response_type = isset( $_GET['response_type'] ) ? sanitize_text_field( wp_unslash( $_GET['response_type'] ) ) : '';

		$client = $this->client( $client_id );
		if ( ! $client ) {
			wp_die( esc_html__( 'Unknown OAuth client.', 'wp-mcp-content-manager' ), 400 );
		}
		if ( ! in_array( $redirect_uri, $client['redirect_uris'], true ) ) {
			wp_die( esc_html__( 'redirect_uri does not match the registered client.', 'wp-mcp-content-manager' ), 400 );
		}
		if ( 'code' !== $response_type ) {
			$this->redirect_error( $redirect_uri, 'unsupported_response_type', $state );
		}
		if ( 'S256' !== $challenge_m || '' === $challenge ) {
			$this->redirect_error( $redirect_uri, 'invalid_request', $state );
		}

		// Require a logged-in WordPress user able to edit content.
		if ( ! is_user_logged_in() ) {
			auth_redirect();
			exit;
		}
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'Your WordPress account does not have permission to edit content.', 'wp-mcp-content-manager' ), 403 );
		}

		// Handle consent submission.
		if ( 'POST' === $this->method() && isset( $_POST['wpmcp_consent'] ) ) {
			check_admin_referer( 'wpmcp_oauth_consent' );

			if ( 'allow' !== sanitize_key( wp_unslash( $_POST['wpmcp_consent'] ) ) ) {
				$this->redirect_error( $redirect_uri, 'access_denied', $state );
			}

			$code = bin2hex( random_bytes( 24 ) );
			set_transient(
				'wpmcp_oauth_code_' . hash( 'sha256', $code ),
				array(
					'client_id'     => $client_id,
					'user_id'       => get_current_user_id(),
					'redirect_uri'  => $redirect_uri,
					'code_challenge' => $challenge,
				),
				self::CODE_TTL
			);

			$sep = ( false === strpos( $redirect_uri, '?' ) ) ? '?' : '&';
			$location = $redirect_uri . $sep . http_build_query(
				array(
					'code'  => $code,
					'state' => $state,
				)
			);
			wp_redirect( $location );
			exit;
		}

		$this->render_consent( $client, $state );
	}

	/**
	 * Render the consent screen.
	 *
	 * @param array  $client Client record.
	 * @param string $state  OAuth state to echo back.
	 */
	private function render_consent( $client, $state ) {
		$user = wp_get_current_user();
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="utf-8" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<meta name="robots" content="noindex, nofollow" />
			<title><?php esc_html_e( 'Authorize MCP access', 'wp-mcp-content-manager' ); ?></title>
			<style>
				body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f0f0f1;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
				.card{background:#fff;max-width:420px;padding:32px;border-radius:10px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
				h1{font-size:20px;margin:0 0 8px}
				p{color:#444;line-height:1.5}
				.who{background:#f6f7f7;border-radius:6px;padding:12px;font-size:14px;margin:16px 0}
				button{font-size:15px;padding:10px 18px;border-radius:6px;border:0;cursor:pointer}
				.allow{background:#2271b1;color:#fff}
				.deny{background:transparent;color:#646970;border:1px solid #c3c4c7;margin-left:8px}
			</style>
		</head>
		<body>
			<div class="card">
				<h1><?php esc_html_e( 'Authorize access', 'wp-mcp-content-manager' ); ?></h1>
				<p>
					<strong><?php echo esc_html( $client['client_name'] ); ?></strong>
					<?php esc_html_e( 'is requesting permission to read and update content on this WordPress site on your behalf.', 'wp-mcp-content-manager' ); ?>
				</p>
				<div class="who">
					<?php printf( esc_html__( 'Signed in as %s', 'wp-mcp-content-manager' ), '<strong>' . esc_html( $user->display_name ) . '</strong>' ); ?>
				</div>
				<form method="post">
					<?php wp_nonce_field( 'wpmcp_oauth_consent' ); ?>
					<button class="allow" type="submit" name="wpmcp_consent" value="allow"><?php esc_html_e( 'Allow', 'wp-mcp-content-manager' ); ?></button>
					<button class="deny" type="submit" name="wpmcp_consent" value="deny"><?php esc_html_e( 'Deny', 'wp-mcp-content-manager' ); ?></button>
				</form>
			</div>
		</body>
		</html>
		<?php
		exit;
	}

	/* --------------------------------------------------------------------- */
	/* Token endpoint                                                        */
	/* --------------------------------------------------------------------- */

	/**
	 * Exchange an authorization code (or refresh token) for an access token.
	 */
	private function token() {
		if ( 'POST' !== $this->method() ) {
			$this->error_json( 'invalid_request', 'POST required.', 405 );
		}

		$params     = $this->form_or_json();
		$grant_type = isset( $params['grant_type'] ) ? $params['grant_type'] : '';

		if ( 'authorization_code' === $grant_type ) {
			$this->grant_authorization_code( $params );
		} elseif ( 'refresh_token' === $grant_type ) {
			$this->grant_refresh_token( $params );
		} else {
			$this->error_json( 'unsupported_grant_type', 'Unsupported grant_type.', 400 );
		}
	}

	/**
	 * Authorization code grant with PKCE verification.
	 *
	 * @param array $params Token request params.
	 */
	private function grant_authorization_code( $params ) {
		$code         = isset( $params['code'] ) ? $params['code'] : '';
		$verifier     = isset( $params['code_verifier'] ) ? $params['code_verifier'] : '';
		$client_id    = isset( $params['client_id'] ) ? $params['client_id'] : '';
		$redirect_uri = isset( $params['redirect_uri'] ) ? $params['redirect_uri'] : '';

		$data = get_transient( 'wpmcp_oauth_code_' . hash( 'sha256', $code ) );
		if ( ! $data ) {
			$this->error_json( 'invalid_grant', 'Authorization code is invalid or expired.', 400 );
		}
		delete_transient( 'wpmcp_oauth_code_' . hash( 'sha256', $code ) );

		if ( $data['client_id'] !== $client_id || $data['redirect_uri'] !== $redirect_uri ) {
			$this->error_json( 'invalid_grant', 'Client or redirect mismatch.', 400 );
		}

		// PKCE: base64url(sha256(verifier)) must equal the stored challenge.
		$expected = $this->base64url( hash( 'sha256', $verifier, true ) );
		if ( ! hash_equals( $data['code_challenge'], $expected ) ) {
			$this->error_json( 'invalid_grant', 'PKCE verification failed.', 400 );
		}

		$this->issue_tokens( (int) $data['user_id'], $client_id );
	}

	/**
	 * Refresh token grant.
	 *
	 * @param array $params Token request params.
	 */
	private function grant_refresh_token( $params ) {
		$refresh   = isset( $params['refresh_token'] ) ? $params['refresh_token'] : '';
		$client_id = isset( $params['client_id'] ) ? $params['client_id'] : '';

		$data = get_transient( 'wpmcp_oauth_refresh_' . hash( 'sha256', $refresh ) );
		if ( ! $data || ( $client_id && $data['client_id'] !== $client_id ) ) {
			$this->error_json( 'invalid_grant', 'Refresh token is invalid or expired.', 400 );
		}
		$this->issue_tokens( (int) $data['user_id'], $data['client_id'] );
	}

	/**
	 * Mint and return access + refresh tokens.
	 *
	 * @param int    $user_id   WordPress user.
	 * @param string $client_id OAuth client.
	 */
	private function issue_tokens( $user_id, $client_id ) {
		$access  = bin2hex( random_bytes( 32 ) );
		$refresh = bin2hex( random_bytes( 32 ) );

		set_transient(
			'wpmcp_oauth_tok_' . hash( 'sha256', $access ),
			array(
				'user_id'   => $user_id,
				'client_id' => $client_id,
			),
			self::TOKEN_TTL
		);
		set_transient(
			'wpmcp_oauth_refresh_' . hash( 'sha256', $refresh ),
			array(
				'user_id'   => $user_id,
				'client_id' => $client_id,
			),
			self::REFRESH_TTL
		);

		$this->json(
			array(
				'access_token'  => $access,
				'token_type'    => 'Bearer',
				'expires_in'    => self::TOKEN_TTL,
				'refresh_token' => $refresh,
				'scope'         => 'wordpress.edit',
			)
		);
	}

	/* --------------------------------------------------------------------- */
	/* Token validation (used by WPMCP_Auth)                                 */
	/* --------------------------------------------------------------------- */

	/**
	 * Resolve an OAuth bearer token to a WordPress user id.
	 *
	 * @param string $token Access token.
	 * @return int User id, or 0 if invalid.
	 */
	public static function resolve_token( $token ) {
		if ( ! $token ) {
			return 0;
		}
		$data = get_transient( 'wpmcp_oauth_tok_' . hash( 'sha256', $token ) );
		if ( ! $data || empty( $data['user_id'] ) ) {
			return 0;
		}
		return (int) $data['user_id'];
	}

	/* --------------------------------------------------------------------- */
	/* Helpers                                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Registered clients.
	 *
	 * @return array
	 */
	private function clients() {
		$clients = get_option( self::CLIENTS_OPTION, array() );
		return is_array( $clients ) ? $clients : array();
	}

	/**
	 * Single client.
	 *
	 * @param string $client_id Client id.
	 * @return array|null
	 */
	private function client( $client_id ) {
		$clients = $this->clients();
		return isset( $clients[ $client_id ] ) ? $clients[ $client_id ] : null;
	}

	/**
	 * Request method.
	 *
	 * @return string
	 */
	private function method() {
		return isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	}

	/**
	 * Decode a JSON request body.
	 *
	 * @return array
	 */
	private function json_body() {
		$raw     = file_get_contents( 'php://input' );
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Token endpoint accepts form-encoded or JSON.
	 *
	 * @return array
	 */
	private function form_or_json() {
		if ( ! empty( $_POST ) ) {
			return wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return $this->json_body();
	}

	/**
	 * base64url encode.
	 *
	 * @param string $bin Binary input.
	 * @return string
	 */
	private function base64url( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	/**
	 * Emit JSON and exit.
	 *
	 * @param array $data   Payload.
	 * @param int   $status HTTP status.
	 */
	private function json( $data, $status = 200 ) {
		nocache_headers();
		status_header( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		echo wp_json_encode( $data );
		exit;
	}

	/**
	 * Emit an OAuth JSON error and exit.
	 *
	 * @param string $code    Error code.
	 * @param string $message Description.
	 * @param int    $status  HTTP status.
	 */
	private function error_json( $code, $message, $status = 400 ) {
		$this->json(
			array(
				'error'             => $code,
				'error_description' => $message,
			),
			$status
		);
	}

	/**
	 * Redirect back to the client with an error.
	 *
	 * @param string $redirect_uri Client redirect.
	 * @param string $code         Error code.
	 * @param string $state        OAuth state.
	 */
	private function redirect_error( $redirect_uri, $code, $state ) {
		$sep = ( false === strpos( $redirect_uri, '?' ) ) ? '?' : '&';
		wp_redirect(
			$redirect_uri . $sep . http_build_query(
				array(
					'error' => $code,
					'state' => $state,
				)
			)
		);
		exit;
	}
}
