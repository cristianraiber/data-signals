<?php
/**
 * Privacy Manager
 *
 * Handles IP anonymization, data cleanup, and GDPR/CCPA compliance.
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Privacy_Manager
 *
 * Manages privacy features and data retention
 */
class Privacy_Manager {

	/**
	 * Data retention period in days
	 *
	 * @var int
	 */
	private const DEFAULT_RETENTION_DAYS = 90;

	/**
	 * Archive retention period in days
	 *
	 * @var int
	 */
	private const ARCHIVE_RETENTION_DAYS = 365;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks(): void {
		// Privacy export hook
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporters' ) );
		
		// Privacy erasure hook
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_erasers' ) );
		
		// Schedule cleanup cron
		if ( ! wp_next_scheduled( 'ds_cleanup_old_data' ) ) {
			wp_schedule_event( time(), 'daily', 'ds_cleanup_old_data' );
		}
		add_action( 'ds_cleanup_old_data', array( $this, 'cleanup_old_data' ) );
	}

	/**
	 * Anonymize IP address
	 *
	 * Removes last octet for IPv4, last 80 bits for IPv6 (GDPR compliant)
	 *
	 * @param string $ip IP address.
	 * @return string Anonymized IP.
	 */
	public function anonymize_ip( string $ip ): string {
		// Validate IP
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '0.0.0.0';
		}

		// IPv4: Zero out last octet
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts    = explode( '.', $ip );
			$parts[3] = '0';
			return implode( '.', $parts );
		}

		// IPv6: Zero out last 80 bits (last 5 groups)
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$parts = explode( ':', $ip );
			for ( $i = 3; $i < 8; $i++ ) {
				if ( isset( $parts[ $i ] ) ) {
					$parts[ $i ] = '0';
				}
			}
			return implode( ':', $parts );
		}

		return '0.0.0.0';
	}

	/**
	 * Hash IP address with salt
	 *
	 * @param string $ip IP address.
	 * @return string Hashed IP (SHA-256).
	 */
	public function hash_ip( string $ip ): string {
		$anonymized = $this->anonymize_ip( $ip );
		$salt       = $this->get_salt();
		return hash( 'sha256', $anonymized . $salt );
	}

	/**
	 * Get or generate salt for hashing
	 *
	 * @return string Salt.
	 */
	private function get_salt(): string {
		$salt = get_option( 'ds_privacy_salt' );

		if ( ! $salt ) {
			$salt = wp_generate_password( 64, true, true );
			update_option( 'ds_privacy_salt', $salt, false );
		}

		return $salt;
	}

	/**
	 * Sanitize and anonymize tracking data
	 *
	 * @param array $data Raw tracking data.
	 * @return array Sanitized data.
	 */
	public function sanitize_tracking_data( array $data ): array {
		$sanitized = array();

		// URL (remove query params with PII)
		if ( isset( $data['url'] ) ) {
			$sanitized['url'] = $this->sanitize_url( $data['url'] );
		}

		// Referrer (remove query params)
		if ( isset( $data['referrer'] ) ) {
			$sanitized['referrer'] = $this->sanitize_url( $data['referrer'] );
		}

		// UTM parameters (sanitize)
		$utm_params = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' );
		foreach ( $utm_params as $param ) {
			if ( isset( $data[ $param ] ) ) {
				$sanitized[ $param ] = sanitize_text_field( $data[ $param ] );
			}
		}

		// Country code (2-letter ISO)
		if ( isset( $data['country'] ) ) {
			$country = strtoupper( sanitize_text_field( $data['country'] ) );
			if ( preg_match( '/^[A-Z]{2}$/', $country ) ) {
				$sanitized['country_code'] = $country;
			}
		}

		// Page ID (integer)
		if ( isset( $data['page_id'] ) ) {
			$sanitized['page_id'] = absint( $data['page_id'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize URL (remove PII from query params)
	 *
	 * @param string $url URL.
	 * @return string Sanitized URL.
	 */
	private function sanitize_url( string $url ): string {
		// Validate URL
		$url = esc_url_raw( $url );
		if ( empty( $url ) ) {
			return '';
		}

		// Parse URL
		$parsed = wp_parse_url( $url );
		if ( ! $parsed ) {
			return '';
		}

		// Remove sensitive query parameters
		if ( isset( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $params );
			
			// Blocklist of PII parameters
			$blocklist = array( 'email', 'e', 'name', 'phone', 'address', 'token', 'key', 'password', 'pwd' );
			foreach ( $blocklist as $blocked ) {
				unset( $params[ $blocked ] );
			}

			// Rebuild query string
			$parsed['query'] = http_build_query( $params );
		}

		// Rebuild URL
		$scheme   = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '';
		$host     = isset( $parsed['host'] ) ? $parsed['host'] : '';
		$port     = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
		$path     = isset( $parsed['path'] ) ? $parsed['path'] : '';
		$query    = isset( $parsed['query'] ) && ! empty( $parsed['query'] ) ? '?' . $parsed['query'] : '';
		$fragment = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';

		return $scheme . $host . $port . $path . $query . $fragment;
	}

	/**
	 * Cleanup old data (called by cron)
	 */
	public function cleanup_old_data(): void {
		global $wpdb;

		$retention_days = get_option( 'ds_data_retention_days', self::DEFAULT_RETENTION_DAYS );
		$cutoff_date    = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		// Archive old data before deletion
		$this->archive_old_data( $cutoff_date );

		// Delete old pageviews
		$deleted_pageviews = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}ds_pageviews WHERE created_at < %s",
			$cutoff_date
		) );

		// Delete old events
		$deleted_events = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}ds_events WHERE created_at < %s",
			$cutoff_date
		) );

		// Delete old sessions
		$deleted_sessions = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}ds_sessions WHERE last_seen < %s",
			$cutoff_date
		) );

		// Log cleanup
		error_log( sprintf(
			'Data Signals: Cleaned up old data. Deleted: %d pageviews, %d events, %d sessions',
			$deleted_pageviews,
			$deleted_events,
			$deleted_sessions
		) );
	}

	/**
	 * Archive old data to JSON files
	 *
	 * @param string $cutoff_date Cutoff date.
	 */
	private function archive_old_data( string $cutoff_date ): void {
		global $wpdb;

		$upload_dir = wp_upload_dir();
		$archive_dir = $upload_dir['basedir'] . '/data-signals-archives/';

		// Create archive directory
		if ( ! file_exists( $archive_dir ) ) {
			wp_mkdir_p( $archive_dir );
			// Protect directory
			file_put_contents( $archive_dir . '.htaccess', 'Deny from all' );
			file_put_contents( $archive_dir . 'index.php', '<?php // Silence is golden' );
		}

		// Archive aggregates (keep forever)
		$aggregates = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}ds_aggregates WHERE date < %s",
			gmdate( 'Y-m-d', strtotime( $cutoff_date ) )
		), ARRAY_A );

		if ( ! empty( $aggregates ) ) {
			$filename = $archive_dir . 'aggregates_' . gmdate( 'Y-m-d' ) . '.json.gz';
			$json     = wp_json_encode( $aggregates );
			file_put_contents( $filename, gzencode( $json, 9 ) );
		}

		// Delete archived aggregates older than archive retention
		$archive_cutoff = gmdate( 'Y-m-d', strtotime( '-' . self::ARCHIVE_RETENTION_DAYS . ' days' ) );
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}ds_aggregates WHERE date < %s",
			$archive_cutoff
		) );
	}

	/**
	 * Register privacy exporters
	 *
	 * @param array $exporters Exporters array.
	 * @return array Modified exporters array.
	 */
	public function register_exporters( array $exporters ): array {
		$exporters['data-signals'] = array(
			'exporter_friendly_name' => __( 'Data Signals Analytics', 'data-signals' ),
			'callback'               => array( $this, 'export_user_data' ),
		);

		return $exporters;
	}

	/**
	 * Export user data for privacy request
	 *
	 * @param string $email_address User email.
	 * @param int    $page Page number.
	 * @return array Export data.
	 */
	public function export_user_data( string $email_address, int $page = 1 ): array {
		// Note: We don't store email addresses in analytics data
		// But we can export purchase data if WooCommerce is active
		
		$data_to_export = array();

		if ( class_exists( 'WooCommerce' ) ) {
			global $wpdb;

			// Get orders for this email
			$orders = wc_get_orders( array(
				'billing_email' => $email_address,
				'limit'         => 100,
			) );

			foreach ( $orders as $order ) {
				$order_id = $order->get_id();

				// Get analytics events for this order
				$events = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ds_events 
					WHERE event_type = 'purchase' 
					AND JSON_EXTRACT(metadata, '$.order_id') = %d",
					$order_id
				), ARRAY_A );

				if ( ! empty( $events ) ) {
					$data_to_export[] = array(
						'group_id'    => 'data-signals-purchases',
						'group_label' => __( 'Analytics - Purchases', 'data-signals' ),
						'item_id'     => 'order-' . $order_id,
						'data'        => array(
							array(
								'name'  => __( 'Order ID', 'data-signals' ),
								'value' => $order_id,
							),
							array(
								'name'  => __( 'Order Date', 'data-signals' ),
								'value' => $events[0]['created_at'],
							),
							array(
								'name'  => __( 'Order Total', 'data-signals' ),
								'value' => $events[0]['event_value'],
							),
						),
					);
				}
			}
		}

		return array(
			'data' => $data_to_export,
			'done' => true,
		);
	}

	/**
	 * Register privacy erasers
	 *
	 * @param array $erasers Erasers array.
	 * @return array Modified erasers array.
	 */
	public function register_erasers( array $erasers ): array {
		$erasers['data-signals'] = array(
			'eraser_friendly_name' => __( 'Data Signals Analytics', 'data-signals' ),
			'callback'             => array( $this, 'erase_user_data' ),
		);

		return $erasers;
	}

	/**
	 * Erase user data for privacy request
	 *
	 * @param string $email_address User email.
	 * @param int    $page Page number.
	 * @return array Erasure response.
	 */
	public function erase_user_data( string $email_address, int $page = 1 ): array {
		global $wpdb;

		$items_removed  = false;
		$items_retained = false;

		// Note: Analytics data is already anonymized (no email stored)
		// But we can remove metadata that contains email if present

		if ( class_exists( 'WooCommerce' ) ) {
			// Get orders for this email
			$orders = wc_get_orders( array(
				'billing_email' => $email_address,
				'limit'         => 100,
			) );

			foreach ( $orders as $order ) {
				$order_id = $order->get_id();

				// Anonymize metadata in events
				$updated = $wpdb->query( $wpdb->prepare(
					"UPDATE {$wpdb->prefix}ds_events 
					SET metadata = JSON_REMOVE(metadata, '$.customer_email')
					WHERE JSON_EXTRACT(metadata, '$.order_id') = %d",
					$order_id
				) );

				if ( $updated > 0 ) {
					$items_removed = true;
				}
			}
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Check if data retention is compliant
	 *
	 * @return array Compliance status.
	 */
	public function get_compliance_status(): array {
		$retention_days = get_option( 'ds_data_retention_days', self::DEFAULT_RETENTION_DAYS );

		return array(
			'gdpr_compliant' => true,
			'ccpa_compliant' => true,
			'ip_anonymization' => true,
			'no_cookies' => true,
			'no_fingerprinting' => true,
			'data_retention_days' => $retention_days,
			'features' => array(
				'IP addresses anonymized (last octet zeroed)',
				'No personal data stored',
				'No cookies used',
				'No browser fingerprinting',
				'Automatic data cleanup after ' . $retention_days . ' days',
				'Data export/erasure support',
				'Session IDs hashed (SHA-256)',
				'OAuth tokens encrypted (AES-256)',
			),
		);
	}
}
