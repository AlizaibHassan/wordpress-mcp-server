<?php
/**
 * Authentication & request gating for the MCP endpoint.
 *
 * Accepts, in order of preference:
 *   1. A plugin-managed API key as `Authorization: Bearer <key>` or `X-API-Key`.
 *   2. An OAuth 2.0 access token issued by WPMCP_OAuth (also Bearer).
 *   3. A logged-in WordPress user with `edit_pages` (covers Application Passwords).
 *
 * Also enforces the master on/off switch and per-IP rate limiting.
 *
 * @package WPMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auth helper.
 */
class WPMCP_Auth {

	const OPTION_KEY       = 'wpmcp_api_key';        // Now stores the SHA-256 hash of the key.
	const OPTION_HINT      = 'wpmcp_api_key_hint';   // Masked preview, safe to display.
	const REVEAL_TRANSIENT = 'wpmcp_api_key_reveal'; // One-time plaintext reveal channel.

	/**
	 * Who authenticated the current request ('api-key' or a user login).
	 *
	 * @var string
	 */
	private static $actor = 'unknown';

	/**
	 * Whether the current request authenticated via the trusted API key.
	 *
	 * @var bool
	 */
	private static $via_key = false;

	/**
	 * Create a default key on activation if none exists.
	 */
	public static function on_activation() {
		self::migrate();
		if ( ! get_option( self::OPTION_KEY ) ) {
			self::store_key( self::generate_key() );
		}
	}

	/**
	 * Generate a cryptographically strong key.
	 *
	 * @return string
	 */
	public static function generate_key() {
		return 'wpmcp_' . bin2hex( random_bytes( 24 ) );
	}

	/**
	 * Build a non-sensitive masked preview of a key, e.g. "wpmcp_b719…d9411".
	 *
	 * @param string $key Plaintext key.
	 * @return string
	 */
	private static function hint_for( $key ) {
		return substr( $key, 0, 10 ) . '…' . substr( $key, -4 );
	}

	/**
	 * Persist a key: store only its hash + a masked hint, and stash the
	 * plaintext in a short-lived, one-time reveal transient for display.
	 *
	 * @param string $key Plaintext key.
	 * @return string The plaintext key (so the caller can show it once).
	 */
	private static function store_key( $key ) {
		update_option( self::OPTION_KEY, hash( 'sha256', $key ) );
		update_option( self::OPTION_HINT, self::hint_for( $key ) );
		set_transient( self::REVEAL_TRANSIENT, $key, 15 * MINUTE_IN_SECONDS );
		return $key;
	}

	/**
	 * Migrate a legacy plaintext key (pre-1.3) to the hashed format in place,
	 * without breaking the existing connection.
	 */
	private static function migrate() {
		$stored = get_option( self::OPTION_KEY );
		if ( $stored && 0 === strpos( $stored, 'wpmcp_' ) ) {
			// Legacy plaintext key present — replace with its hash + hint.
			update_option( self::OPTION_HINT, self::hint_for( $stored ) );
			update_option( self::OPTION_KEY, hash( 'sha256', $stored ) );
		}
	}

	/**
	 * Verify a presented token against the stored hash (constant time).
	 *
	 * @param string $token Presented key.
	 * @return bool
	 */
	public static function verify_key( $token ) {
		self::migrate();
		$hash = get_option( self::OPTION_KEY );
		return $hash && hash_equals( $hash, hash( 'sha256', (string) $token ) );
	}

	/**
	 * Masked hint for display (never the real key).
	 *
	 * @return string
	 */
	public static function get_hint() {
		self::migrate();
		$hint = get_option( self::OPTION_HINT );
		return $hint ? $hint : '—';
	}

	/**
	 * One-time reveal of a freshly generated key (consumed on read).
	 *
	 * @return string|false Plaintext key if available this once, else false.
	 */
	public static function peek_reveal() {
		$key = get_transient( self::REVEAL_TRANSIENT );
		if ( $key ) {
			delete_transient( self::REVEAL_TRANSIENT );
		}
		return $key;
	}

	/**
	 * Rotate the API key.
	 *
	 * @return string The new plaintext key (shown once).
	 */
	public static function rotate_key() {
		return self::store_key( self::generate_key() );
	}

	/**
	 * Actor label for the current request.
	 *
	 * @return string
	 */
	public static function actor() {
		return self::$actor;
	}

	/**
	 * Whether the request used the trusted API key (full privileges).
	 *
	 * @return bool
	 */
	public static function is_trusted_key() {
		return self::$via_key;
	}

	/**
	 * Permission callback for the REST route.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error
	 */
	public static function check( $request ) {
		// Master switch.
		if ( ! WPMCP_Settings::get( 'enabled', true ) ) {
			return new WP_Error(
				'wpmcp_disabled',
				__( 'The MCP server is currently disabled by the site administrator.', 'wp-mcp-content-manager' ),
				array( 'status' => 503 )
			);
		}

		// Rate limit (per IP).
		$limit = (int) WPMCP_Settings::get( 'rate_limit', 60 );
		if ( self::throttle( 'mcp', $limit ) ) {
			return new WP_Error(
				'wpmcp_rate_limited',
				__( 'Rate limit exceeded. Slow down and try again shortly.', 'wp-mcp-content-manager' ),
				array( 'status' => 429 )
			);
		}

		$token = self::extract_token( $request );

		// 1. Trusted API key.
		if ( $token && self::verify_key( $token ) ) {
			self::$actor   = 'api-key';
			self::$via_key = true;
			return true;
		}

		// 2. OAuth access token.
		if ( $token ) {
			$user_id = WPMCP_OAuth::resolve_token( $token );
			if ( $user_id ) {
				wp_set_current_user( $user_id );
				$user        = wp_get_current_user();
				self::$actor = $user->user_login ? $user->user_login : ( 'user-' . $user_id );
				if ( ! current_user_can( 'edit_pages' ) ) {
					return self::challenge( __( 'Your account cannot edit content.', 'wp-mcp-content-manager' ), 403 );
				}
				return true;
			}
			// A token was supplied but matched nothing.
			return self::challenge( __( 'Invalid or expired token.', 'wp-mcp-content-manager' ), 401 );
		}

		// 3. Authenticated WordPress user (cookie or Application Password).
		if ( is_user_logged_in() && current_user_can( 'edit_pages' ) ) {
			$user        = wp_get_current_user();
			self::$actor = $user->user_login;
			return true;
		}

		return self::challenge( __( 'Authentication required.', 'wp-mcp-content-manager' ), 401 );
	}

	/**
	 * Build a 401/403 error and advertise the OAuth resource metadata so
	 * MCP clients can begin the connector flow automatically.
	 *
	 * @param string $message Error message.
	 * @param int    $status  HTTP status.
	 * @return WP_Error
	 */
	private static function challenge( $message, $status = 401 ) {
		if ( 401 === $status && WPMCP_Settings::get( 'oauth_enabled', true ) ) {
			$meta = home_url( '/.well-known/oauth-protected-resource' );
			// Sent directly so it survives the WP_Error → REST response path.
			if ( ! headers_sent() ) {
				header( sprintf( 'WWW-Authenticate: Bearer resource_metadata="%s"', esc_url_raw( $meta ) ), true );
			}
		}
		return new WP_Error(
			401 === $status ? 'wpmcp_unauthorized' : 'wpmcp_forbidden',
			$message,
			array( 'status' => $status )
		);
	}

	/**
	 * Per-IP, per-bucket rate limit using a 60-second transient window.
	 * Reusable across the /mcp endpoint and the OAuth endpoints (separate
	 * buckets so they don't share a budget).
	 *
	 * @param string $bucket Namespace for the counter (e.g. 'mcp', 'oauth').
	 * @param int    $limit  Max requests per minute (<= 0 disables limiting).
	 * @return bool True when the caller is over the limit.
	 */
	public static function throttle( $bucket, $limit ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			return false;
		}
		$key   = 'wpmcp_rl_' . preg_replace( '/[^a-z0-9]/', '', (string) $bucket ) . '_' . md5( WPMCP_Logger::client_ip() );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return true;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Pull the token out of the request headers.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return string Empty string when not present.
	 */
	private static function extract_token( $request ) {
		$auth = $request->get_header( 'authorization' );
		if ( $auth && stripos( $auth, 'bearer ' ) === 0 ) {
			return trim( substr( $auth, 7 ) );
		}

		$x_key = $request->get_header( 'x_api_key' );
		if ( $x_key ) {
			return trim( $x_key );
		}

		return '';
	}
}
