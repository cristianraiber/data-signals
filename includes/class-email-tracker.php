<?php
/**
 * Email Click Tracker
 *
 * Handles email link click tracking with redirect and session association.
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals;

defined( 'ABSPATH' ) || exit;

class Email_Tracker {

	/**
	 * Table name for email clicks
	 *
	 * @var string
	 */
	private static $table_name;

	/**
	 * Initialize the tracker
	 */
	public static function init() {
		global $wpdb;
		self::$table_name = $wpdb->prefix . 'ds_email_clicks';

		// Register public redirect endpoint
		add_action( 'init', [ __CLASS__, 'register_rewrite_rules' ] );
		add_action( 'template_redirect', [ __CLASS__, 'handle_email_redirect' ] );
	}

	/**
	 * Register rewrite rules for email tracking
	 */
	public static function register_rewrite_rules() {
		add_rewrite_rule(
			'^ds-track/email/?',
			'index.php?ds_email_track=1',
			'top'
		);
		add_filter( 'query_vars', [ __CLASS__, 'add_query_vars' ] );
	}

	/**
	 * Add custom query vars
	 *
	 * @param array $vars Query vars
	 * @return array Modified query vars
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'ds_email_track';
		return $vars;
	}

	/**
	 * Handle email redirect and tracking
	 */
	public static function handle_email_redirect() {
		if ( ! get_query_var( 'ds_email_track' ) ) {
			return;
		}

		// Rate limiting check
		if ( ! self::check_rate_limit() ) {
			wp_die( 'Too many requests', 'Rate Limit Exceeded', [ 'response' => 429 ] );
		}

		// Sanitize parameters
		$url = isset( $_GET['url'] ) ? esc_url_raw( $_GET['url'] ) : '';
		$campaign = isset( $_GET['campaign'] ) ? sanitize_text_field( $_GET['campaign'] ) : '';

		if ( empty( $url ) ) {
			wp_die( 'Missing URL parameter', 'Bad Request', [ 'response' => 400 ] );
		}

		// Validate URL (must be internal or whitelisted)
		if ( ! self::is_valid_redirect_url( $url ) ) {
			wp_die( 'Invalid redirect URL', 'Bad Request', [ 'response' => 400 ] );
		}

		// Get or create session
		$session_id = self::get_or_create_session();

		// Log the click
		self::log_click( $campaign, $url, $session_id );

		// Redirect
		wp_redirect( $url, 302 );
		exit;
	}

	/**
	 * Check rate limit (100 requests per minute per IP)
	 *
	 * @return bool True if within limit, false otherwise
	 */
	private static function check_rate_limit() {
		$ip = self::get_client_ip();
		$cache_key = 'ds_email_rate_' . md5( $ip );
		$current_count = get_transient( $cache_key );

		if ( false === $current_count ) {
			set_transient( $cache_key, 1, 60 );
			return true;
		}

		if ( $current_count >= 100 ) {
			return false;
		}

		set_transient( $cache_key, $current_count + 1, 60 );
		return true;
	}

	/**
	 * Get client IP address (anonymized)
	 *
	 * @return string Anonymized IP
	 */
	private static function get_client_ip() {
		$ip = '';

		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$ip = explode( ',', $ip )[0];
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// Anonymize IP (zero last octet for IPv4)
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts = explode( '.', $ip );
			$parts[3] = '0';
			$ip = implode( '.', $parts );
		}

		return $ip;
	}

	/**
	 * Validate redirect URL
	 *
	 * @param string $url URL to validate
	 * @return bool True if valid, false otherwise
	 */
	private static function is_valid_redirect_url( $url ) {
		// Must be absolute URL
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$parsed_url = wp_parse_url( $url );
		$site_url = wp_parse_url( home_url() );

		// Allow same domain or WordPress.com/org domains
		$allowed_hosts = [
			$site_url['host'],
			'wordpress.com',
			'wordpress.org',
		];

		$allowed_hosts = apply_filters( 'data_signals_allowed_redirect_hosts', $allowed_hosts );

		return in_array( $parsed_url['host'], $allowed_hosts, true );
	}

	/**
	 * Get or create session ID
	 *
	 * @return string Session ID
	 */
	private static function get_or_create_session() {
		// Check for existing session cookie
		$session_id = isset( $_COOKIE['ds_session'] ) ? sanitize_text_field( $_COOKIE['ds_session'] ) : '';

		if ( empty( $session_id ) || strlen( $session_id ) !== 32 ) {
			$session_id = md5( uniqid( '', true ) . wp_rand() );
			setcookie( 'ds_session', $session_id, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN );
		}

		return $session_id;
	}

	/**
	 * Log email click to database
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param string $campaign   Campaign ID
	 * @param string $link_url   Clicked URL
	 * @param string $session_id Session ID
	 * @return int|false Insert ID on success, false on failure
	 */
	public static function log_click( $campaign, $link_url, $session_id ) {
		global $wpdb;

		$data = [
			'campaign_id' => sanitize_text_field( $campaign ),
			'link_url'    => esc_url_raw( $link_url ),
			'session_id'  => sanitize_text_field( $session_id ),
			'clicked_at'  => current_time( 'mysql', true ),
		];

		$result = $wpdb->insert(
			self::$table_name,
			$data,
			[ '%s', '%s', '%s', '%s' ]
		);

		if ( $result ) {
			do_action( 'data_signals_email_click_logged', $wpdb->insert_id, $campaign, $session_id );
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update click with conversion data
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param string $session_id Session ID
	 * @param float  $revenue    Revenue amount
	 * @return bool True on success, false on failure
	 */
	public static function mark_converted( $session_id, $revenue = 0 ) {
		global $wpdb;

		$result = $wpdb->update(
			self::$table_name,
			[
				'converted' => 1,
				'revenue'   => $revenue,
			],
			[ 'session_id' => $session_id ],
			[ '%d', '%f' ],
			[ '%s' ]
		);

		return $result !== false;
	}

	/**
	 * Get clicks by campaign
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param string $campaign_id Campaign ID
	 * @param int    $limit       Limit results
	 * @return array Click records
	 */
	public static function get_clicks_by_campaign( $campaign_id, $limit = 100 ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM " . self::$table_name . "
			WHERE campaign_id = %s
			ORDER BY clicked_at DESC
			LIMIT %d",
			$campaign_id,
			$limit
		);

		return $wpdb->get_results( $query );
	}

	/**
	 * Build tracking URL
	 *
	 * @param string $destination_url Destination URL
	 * @param string $campaign_id     Campaign ID
	 * @return string Tracking URL
	 */
	public static function build_tracking_url( $destination_url, $campaign_id ) {
		$base_url = home_url( '/ds-track/email/' );

		return add_query_arg(
			[
				'url'      => rawurlencode( $destination_url ),
				'campaign' => sanitize_text_field( $campaign_id ),
			],
			$base_url
		);
	}

	/**
	 * Create database table
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 */
	public static function create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name = self::$table_name ?? $wpdb->prefix . 'ds_email_clicks';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			campaign_id VARCHAR(100) NOT NULL,
			link_url VARCHAR(500) NOT NULL,
			session_id CHAR(32),
			clicked_at DATETIME NOT NULL,
			converted BOOLEAN DEFAULT FALSE,
			revenue DECIMAL(10,2) DEFAULT 0,
			INDEX idx_campaign (campaign_id, clicked_at),
			INDEX idx_session (session_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
