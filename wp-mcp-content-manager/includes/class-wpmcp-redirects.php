<?php
/**
 * Lightweight 301/302 redirect manager.
 *
 * Stores redirects in a single option and performs them on `template_redirect`,
 * independent of any SEO plugin. Used for content consolidation (pointing
 * merged/old URLs at their surviving canonical page).
 *
 * @package WPMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect store + runtime handler.
 */
class WPMCP_Redirects {

	const OPTION = 'wpmcp_redirects';

	/**
	 * Hook the runtime redirect handler.
	 */
	public function init() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
	}

	/**
	 * All stored redirects.
	 *
	 * @return array List of [ from, to, code ].
	 */
	public static function all() {
		$rows = get_option( self::OPTION, array() );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Normalise a path: leading slash, no trailing slash, lowercased, no query.
	 *
	 * @param string $path Raw path or URL.
	 * @return string
	 */
	public static function normalize( $path ) {
		$path = (string) $path;
		if ( preg_match( '#^https?://#i', $path ) ) {
			$path = wp_parse_url( $path, PHP_URL_PATH );
		}
		$path = strtok( (string) $path, '?' );
		$path = '/' . ltrim( (string) $path, '/' );
		$path = rtrim( $path, '/' );
		return strtolower( '' === $path ? '/' : $path );
	}

	/**
	 * Add or update a redirect.
	 *
	 * @param string $from Source path.
	 * @param string $to   Destination URL or path.
	 * @param int    $code 301 or 302.
	 * @return array The stored row.
	 */
	public static function add( $from, $to, $code = 301 ) {
		$from = self::normalize( $from );
		$code = in_array( (int) $code, array( 301, 302, 307, 308 ), true ) ? (int) $code : 301;

		$rows  = self::all();
		$found = false;
		foreach ( $rows as &$row ) {
			if ( $row['from'] === $from ) {
				$row['to']   = $to;
				$row['code'] = $code;
				$found       = true;
				break;
			}
		}
		unset( $row );
		if ( ! $found ) {
			$rows[] = array(
				'from' => $from,
				'to'   => $to,
				'code' => $code,
			);
		}
		update_option( self::OPTION, array_values( $rows ), false );
		return array(
			'from' => $from,
			'to'   => $to,
			'code' => $code,
		);
	}

	/**
	 * Delete a redirect by source path.
	 *
	 * @param string $from Source path.
	 * @return bool True if removed.
	 */
	public static function delete( $from ) {
		$from = self::normalize( $from );
		$rows = self::all();
		$new  = array_values(
			array_filter(
				$rows,
				function ( $row ) use ( $from ) {
					return $row['from'] !== $from;
				}
			)
		);
		update_option( self::OPTION, $new, false );
		return count( $new ) !== count( $rows );
	}

	/**
	 * Perform a redirect if the current request path matches.
	 */
	public function maybe_redirect() {
		if ( is_admin() ) {
			return;
		}
		$rows = self::all();
		if ( empty( $rows ) ) {
			return;
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		$current = self::normalize( $request );

		foreach ( $rows as $row ) {
			if ( $row['from'] === $current ) {
				$to = $row['to'];
				if ( ! preg_match( '#^https?://#i', $to ) ) {
					$to = home_url( $to );
				}
				wp_safe_redirect( $to, (int) $row['code'] );
				exit;
			}
		}
	}
}
