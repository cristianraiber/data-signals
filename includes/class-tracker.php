<?php
/**
 * Session tracking with cookieless SHA-256 session IDs.
 *
 * @package DataSignals
 */

namespace DataSignals;

defined( 'ABSPATH' ) || exit;

/**
 * Tracker class.
 */
class Tracker {
	/**
	 * Session ID length (SHA-256 hash).
	 *
	 * @var int
	 */
	private const SESSION_ID_LENGTH = 64;

	/**
	 * Session duration in seconds (30 minutes).
	 *
	 * @var int
	 */
	private const SESSION_DURATION = 1800;

	/**
	 * Current session ID.
	 *
	 * @var string|null
	 */
	private ?string $session_id = null;

	/**
	 * Get or create session ID.
	 *
	 * @return string
	 */
	public function get_session_id(): string {
		if ( $this->session_id !== null ) {
			return $this->session_id;
		}

		// Try to get from transient (server-side storage).
		$transient_key = $this->get_transient_key();
		$session_id    = get_transient( $transient_key );

		if ( $session_id ) {
			$this->session_id = $session_id;
			// Extend session.
			set_transient( $transient_key, $session_id, self::SESSION_DURATION );
			return $session_id;
		}

		// Generate new session ID.
		$this->session_id = $this->generate_session_id();
		set_transient( $transient_key, $this->session_id, self::SESSION_DURATION );

		return $this->session_id;
	}

	/**
	 * Generate a unique session ID using SHA-256.
	 *
	 * @return string
	 */
	private function generate_session_id(): string {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$ip_address = $this->get_anonymized_ip();
		$timestamp  = (string) time();
		$random     = wp_generate_password( 32, true, true );

		$data = $user_agent . $ip_address . $timestamp . $random;

		return hash( 'sha256', $data );
	}

	/**
	 * Get anonymized IP address (last octet zeroed for IPv4, /64 for IPv6).
	 *
	 * @return string
	 */
	private function get_anonymized_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( empty( $ip ) ) {
			return '0.0.0.0';
		}

		// IPv4.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts    = explode( '.', $ip );
			$parts[3] = '0';
			return implode( '.', $parts );
		}

		// IPv6.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$parts = explode( ':', $ip );
			// Keep first 4 segments (/64 network).
			return implode( ':', array_slice( $parts, 0, 4 ) ) . '::';
		}

		return '0.0.0.0';
	}

	/**
	 * Get transient key for session storage.
	 *
	 * @return string
	 */
	private function get_transient_key(): string {
		$ip         = $this->get_anonymized_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return 'ds_session_' . md5( $ip . $user_agent );
	}

	/**
	 * Parse UTM parameters from URL.
	 *
	 * @param string $url URL to parse.
	 * @return array<string, string|null>
	 */
	public function parse_utm_params( string $url ): array {
		$utm_params = array(
			'utm_source'   => null,
			'utm_medium'   => null,
			'utm_campaign' => null,
			'utm_content'  => null,
			'utm_term'     => null,
		);

		$parsed_url = wp_parse_url( $url );

		if ( ! isset( $parsed_url['query'] ) ) {
			return $utm_params;
		}

		parse_str( $parsed_url['query'], $query_params );

		foreach ( $utm_params as $key => $value ) {
			if ( isset( $query_params[ $key ] ) ) {
				$utm_params[ $key ] = sanitize_text_field( $query_params[ $key ] );
			}
		}

		return $utm_params;
	}

	/**
	 * Get country code from IP address (placeholder - requires GeoIP database).
	 *
	 * @return string|null
	 */
	public function get_country_code(): ?string {
		// TODO: Implement GeoIP lookup.
		// For now, return null. Can integrate MaxMind GeoLite2 or similar.
		return null;
	}

	/**
	 * Track pageview.
	 *
	 * @param array<string, mixed> $data Pageview data.
	 * @return bool
	 */
	public function track_pageview( array $data ): bool {
		global $wpdb;

		$session_id = $this->get_session_id();

		// Parse UTM parameters.
		$url        = isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '';
		$utm_params = $this->parse_utm_params( $url );

		// Prepare data.
		$pageview_data = array(
			'session_id'   => $session_id,
			'page_id'      => isset( $data['page_id'] ) ? absint( $data['page_id'] ) : null,
			'url'          => $url,
			'referrer'     => isset( $data['referrer'] ) ? esc_url_raw( $data['referrer'] ) : null,
			'utm_source'   => $utm_params['utm_source'],
			'utm_medium'   => $utm_params['utm_medium'],
			'utm_campaign' => $utm_params['utm_campaign'],
			'utm_content'  => $utm_params['utm_content'],
			'utm_term'     => $utm_params['utm_term'],
			'country_code' => $this->get_country_code(),
			'created_at'   => current_time( 'mysql' ),
		);

		// Add to batch processor.
		Batch_Processor::add_to_queue( 'pageview', $pageview_data );

		// Update or create session.
		$this->update_session( $pageview_data );

		return true;
	}

	/**
	 * Track event (conversion, click, etc.).
	 *
	 * @param string               $event_type Event type.
	 * @param array<string, mixed> $data       Event data.
	 * @return bool
	 */
	public function track_event( string $event_type, array $data = array() ): bool {
		$session_id = $this->get_session_id();

		$event_data = array(
			'session_id'  => $session_id,
			'event_type'  => sanitize_text_field( $event_type ),
			'event_value' => isset( $data['value'] ) ? floatval( $data['value'] ) : 0.00,
			'page_id'     => isset( $data['page_id'] ) ? absint( $data['page_id'] ) : null,
			'product_id'  => isset( $data['product_id'] ) ? absint( $data['product_id'] ) : null,
			'metadata'    => isset( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : null,
			'created_at'  => current_time( 'mysql' ),
		);

		// Add to batch processor.
		Batch_Processor::add_to_queue( 'event', $event_data );

		return true;
	}

	/**
	 * Update or create session record.
	 *
	 * @param array<string, mixed> $pageview_data Pageview data.
	 * @return void
	 */
	private function update_session( array $pageview_data ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_sessions';
		$session_id = $pageview_data['session_id'];

		// Check if session exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT session_id FROM {$table_name} WHERE session_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$session_id
			)
		);

		if ( $exists ) {
			// Update existing session.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} SET total_pageviews = total_pageviews + 1, last_seen = %s WHERE session_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					current_time( 'mysql' ),
					$session_id
				)
			);
		} else {
			// Create new session.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$table_name,
				array(
					'session_id'     => $session_id,
					'first_page_id'  => $pageview_data['page_id'],
					'first_referrer' => $pageview_data['referrer'],
					'utm_source'     => $pageview_data['utm_source'],
					'utm_medium'     => $pageview_data['utm_medium'],
					'utm_campaign'   => $pageview_data['utm_campaign'],
					'country_code'   => $pageview_data['country_code'],
					'first_seen'     => current_time( 'mysql' ),
					'last_seen'      => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}
}
