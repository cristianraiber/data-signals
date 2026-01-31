<?php
/**
 * UTM Parameter Parser
 *
 * Extracts and validates UTM parameters from URLs and stores them in sessions.
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals;

defined( 'ABSPATH' ) || exit;

class UTM_Parser {

	/**
	 * Valid UTM parameters
	 *
	 * @var array
	 */
	private static $utm_params = [
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_content',
		'utm_term',
	];

	/**
	 * Extract UTM parameters from URL or current request
	 *
	 * @param string|null $url Optional URL to parse. If null, uses current request.
	 * @return array Associative array of UTM parameters
	 */
	public static function extract( $url = null ) {
		$utm_data = [];

		if ( $url !== null ) {
			// Parse from provided URL
			$parsed_url = wp_parse_url( $url );
			if ( isset( $parsed_url['query'] ) ) {
				parse_str( $parsed_url['query'], $query_params );
				foreach ( self::$utm_params as $param ) {
					if ( isset( $query_params[ $param ] ) ) {
						$utm_data[ $param ] = self::sanitize_utm_value( $query_params[ $param ] );
					}
				}
			}
		} else {
			// Extract from current request
			foreach ( self::$utm_params as $param ) {
				if ( isset( $_GET[ $param ] ) ) {
					$utm_data[ $param ] = self::sanitize_utm_value( $_GET[ $param ] );
				}
			}
		}

		return $utm_data;
	}

	/**
	 * Sanitize UTM parameter value
	 *
	 * @param string $value Raw UTM value
	 * @return string Sanitized value
	 */
	private static function sanitize_utm_value( $value ) {
		// Remove any HTML tags and scripts
		$value = wp_strip_all_tags( $value );
		// Limit length to 100 characters
		$value = substr( $value, 0, 100 );
		// Sanitize for database
		return sanitize_text_field( $value );
	}

	/**
	 * Store UTM parameters in session
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param string $session_id Session ID
	 * @param array  $utm_data   UTM parameters
	 * @return bool True on success, false on failure
	 */
	public static function store_in_session( $session_id, $utm_data ) {
		global $wpdb;

		if ( empty( $session_id ) || empty( $utm_data ) ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'ds_sessions';

		// Prepare update data
		$update_data = [];
		foreach ( self::$utm_params as $param ) {
			if ( isset( $utm_data[ $param ] ) ) {
				$update_data[ $param ] = $utm_data[ $param ];
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		// Update existing session or fail silently
		$result = $wpdb->update(
			$table_name,
			$update_data,
			[ 'session_id' => $session_id ],
			array_fill( 0, count( $update_data ), '%s' ),
			[ '%s' ]
		);

		return $result !== false;
	}

	/**
	 * Get UTM parameters from session
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param string $session_id Session ID
	 * @return array|null UTM parameters or null if not found
	 */
	public static function get_from_session( $session_id ) {
		global $wpdb;

		if ( empty( $session_id ) ) {
			return null;
		}

		$table_name = $wpdb->prefix . 'ds_sessions';

		$fields = implode( ', ', self::$utm_params );
		$query = $wpdb->prepare(
			"SELECT {$fields} FROM {$table_name} WHERE session_id = %s LIMIT 1",
			$session_id
		);

		$result = $wpdb->get_row( $query, ARRAY_A );

		if ( ! $result ) {
			return null;
		}

		// Remove null values
		return array_filter( $result, function( $value ) {
			return $value !== null;
		} );
	}

	/**
	 * Validate UTM parameters
	 *
	 * @param array $utm_data UTM parameters to validate
	 * @return array Validation errors (empty if valid)
	 */
	public static function validate( $utm_data ) {
		$errors = [];

		foreach ( $utm_data as $key => $value ) {
			if ( ! in_array( $key, self::$utm_params, true ) ) {
				$errors[] = sprintf( 'Invalid UTM parameter: %s', $key );
			}

			if ( strlen( $value ) > 100 ) {
				$errors[] = sprintf( 'UTM parameter %s exceeds 100 characters', $key );
			}
		}

		return $errors;
	}

	/**
	 * Build UTM URL
	 *
	 * @param string $base_url  Base URL
	 * @param array  $utm_data  UTM parameters
	 * @return string URL with UTM parameters
	 */
	public static function build_url( $base_url, $utm_data ) {
		$utm_data = array_intersect_key( $utm_data, array_flip( self::$utm_params ) );

		if ( empty( $utm_data ) ) {
			return $base_url;
		}

		return add_query_arg( $utm_data, $base_url );
	}

	/**
	 * Get all valid UTM parameter names
	 *
	 * @return array UTM parameter names
	 */
	public static function get_utm_params() {
		return self::$utm_params;
	}
}
