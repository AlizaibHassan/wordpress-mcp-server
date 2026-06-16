<?php
/**
 * Lightweight audit log for MCP tool calls.
 *
 * Stores the most recent entries in an option (capped) so admins can review
 * what the AI did. Sensitive parameter values are redacted before storage.
 *
 * @package WPMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit logger.
 */
class WPMCP_Logger {

	const OPTION   = 'wpmcp_audit_log';
	const MAX_ROWS = 200;

	/**
	 * Keys whose values must never be stored in plain text.
	 *
	 * @var string[]
	 */
	private static $sensitive = array( 'password', 'pass', 'pwd', 'api_key', 'token', 'secret', 'email', 'user_email', 'authorization' );

	/**
	 * Record a tool call.
	 *
	 * @param string $tool    Tool name.
	 * @param array  $args     Arguments (will be redacted).
	 * @param string $outcome  'success' or 'error'.
	 * @param string $actor    Who made the call (user login or 'api-key').
	 */
	public static function record( $tool, $args, $outcome, $actor ) {
		if ( ! WPMCP_Settings::get( 'audit_log', true ) ) {
			return;
		}

		$rows   = get_option( self::OPTION, array() );
		$rows   = is_array( $rows ) ? $rows : array();
		$rows[] = array(
			'time'    => gmdate( 'c' ),
			'tool'    => $tool,
			'actor'   => $actor,
			'ip'      => self::client_ip(),
			'outcome' => $outcome,
			'args'    => self::redact( $args ),
		);

		// Keep only the most recent rows.
		if ( count( $rows ) > self::MAX_ROWS ) {
			$rows = array_slice( $rows, -self::MAX_ROWS );
		}

		update_option( self::OPTION, $rows, false );
	}

	/**
	 * Read the log (newest first).
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public static function read( $limit = 50 ) {
		$rows = get_option( self::OPTION, array() );
		$rows = is_array( $rows ) ? array_reverse( $rows ) : array();
		return array_slice( $rows, 0, $limit );
	}

	/**
	 * Clear the log.
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}

	/**
	 * Recursively redact sensitive values, and truncate long strings.
	 *
	 * @param mixed $value Value to redact.
	 * @return mixed
	 */
	private static function redact( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				if ( is_string( $k ) && in_array( strtolower( $k ), self::$sensitive, true ) ) {
					$out[ $k ] = '[REDACTED]';
				} else {
					$out[ $k ] = self::redact( $v );
				}
			}
			return $out;
		}
		if ( is_string( $value ) && strlen( $value ) > 500 ) {
			return substr( $value, 0, 500 ) . '…[truncated]';
		}
		return $value;
	}

	/**
	 * Best-effort client IP.
	 *
	 * @return string
	 */
	public static function client_ip() {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return 'unknown';
	}
}
