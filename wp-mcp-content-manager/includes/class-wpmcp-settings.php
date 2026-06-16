<?php
/**
 * Centralised plugin settings with sane defaults.
 *
 * Stored as a single option array `wpmcp_settings`.
 *
 * @package WPMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings accessor.
 */
class WPMCP_Settings {

	const OPTION = 'wpmcp_settings';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'       => true,  // Master on/off switch.
			'allow_write'   => true,  // Permit create/update tools.
			'allow_delete'  => false, // Permit delete tools (off by default — safer).
			'rate_limit'    => 60,    // Requests per minute per IP (0 = unlimited).
			'audit_log'     => true,  // Record tool calls.
			'oauth_enabled' => true,  // Allow OAuth 2.0 connector flow.
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Persist settings (only known keys).
	 *
	 * @param array $values Values to merge.
	 */
	public static function update( $values ) {
		$current = self::all();
		foreach ( self::defaults() as $key => $default ) {
			if ( array_key_exists( $key, $values ) ) {
				if ( is_bool( $default ) ) {
					$current[ $key ] = (bool) $values[ $key ];
				} elseif ( is_int( $default ) ) {
					$current[ $key ] = max( 0, (int) $values[ $key ] );
				} else {
					$current[ $key ] = $values[ $key ];
				}
			}
		}
		update_option( self::OPTION, $current );
	}
}
