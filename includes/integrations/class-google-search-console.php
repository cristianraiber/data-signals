<?php
/**
 * Google Search Console Integration
 *
 * Handles OAuth flow, keyword syncing, and SEO revenue estimation
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals\Integrations;

use DataSignals\OAuth_Manager;
use Exception;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Google_Search_Console
 */
class Google_Search_Console {

	/**
	 * OAuth Manager instance
	 *
	 * @var OAuth_Manager
	 */
	private $oauth_manager;

	/**
	 * Google API Client ID
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * Google API Client Secret
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * Provider name for OAuth
	 *
	 * @var string
	 */
	private const PROVIDER = 'google_search_console';

	/**
	 * Google OAuth endpoint
	 *
	 * @var string
	 */
	private const OAUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

	/**
	 * Google token endpoint
	 *
	 * @var string
	 */
	private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	/**
	 * Google Search Console API endpoint
	 *
	 * @var string
	 */
	private const API_URL = 'https://searchconsole.googleapis.com/webmasters/v3';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->oauth_manager = new OAuth_Manager();
		$this->client_id     = get_option( 'ds_gsc_client_id', '' );
		$this->client_secret = get_option( 'ds_gsc_client_secret', '' );

		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'ds_gsc_daily_sync', array( $this, 'sync_keywords' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes(): void {
		// Authorization endpoint
		register_rest_route(
			'data-signals/v1',
			'/gsc/authorize',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_authorize' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// OAuth callback endpoint
		register_rest_route(
			'data-signals/v1',
			'/gsc/callback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_callback' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// Get keywords endpoint
		register_rest_route(
			'data-signals/v1',
			'/gsc/keywords',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_keywords' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// Revenue estimate endpoint
		register_rest_route(
			'data-signals/v1',
			'/gsc/revenue-estimate',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_revenue_estimate' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// Manual sync endpoint
		register_rest_route(
			'data-signals/v1',
			'/gsc/sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'manual_sync' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// Disconnect endpoint
		register_rest_route(
			'data-signals/v1',
			'/gsc/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_disconnect' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Handle authorization request
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_authorize( WP_REST_Request $request ) {
		if ( empty( $this->client_id ) || empty( $this->client_secret ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Google API credentials not configured.', 'data-signals' ),
				array( 'status' => 400 )
			);
		}

		$redirect_uri = rest_url( 'data-signals/v1/gsc/callback' );
		$state        = wp_create_nonce( 'gsc_oauth_state' );

		update_option( 'ds_gsc_oauth_state', $state, false );

		$auth_url = add_query_arg(
			array(
				'client_id'     => $this->client_id,
				'redirect_uri'  => $redirect_uri,
				'response_type' => 'code',
				'scope'         => 'https://www.googleapis.com/auth/webmasters.readonly',
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'state'         => $state,
			),
			self::OAUTH_URL
		);

		return new WP_REST_Response(
			array(
				'authorization_url' => $auth_url,
			),
			200
		);
	}

	/**
	 * Handle OAuth callback
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_callback( WP_REST_Request $request ) {
		$code  = $request->get_param( 'code' );
		$state = $request->get_param( 'state' );

		// Verify state
		$stored_state = get_option( 'ds_gsc_oauth_state' );
		if ( ! $state || ! wp_verify_nonce( $state, 'gsc_oauth_state' ) || $state !== $stored_state ) {
			return new WP_Error(
				'invalid_state',
				__( 'Invalid OAuth state parameter.', 'data-signals' ),
				array( 'status' => 400 )
			);
		}

		delete_option( 'ds_gsc_oauth_state' );

		if ( ! $code ) {
			return new WP_Error(
				'missing_code',
				__( 'Authorization code missing.', 'data-signals' ),
				array( 'status' => 400 )
			);
		}

		// Exchange code for tokens
		$tokens = $this->exchange_code_for_tokens( $code );

		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}

		// Store tokens
		$this->oauth_manager->store_tokens( self::PROVIDER, $tokens );

		// Schedule daily sync if not already scheduled
		if ( ! wp_next_scheduled( 'ds_gsc_daily_sync' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 2:00 AM' ), 'daily', 'ds_gsc_daily_sync' );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Successfully connected to Google Search Console.', 'data-signals' ),
			),
			200
		);
	}

	/**
	 * Exchange authorization code for tokens
	 *
	 * @param string $code Authorization code.
	 * @return array|WP_Error Token data or error.
	 */
	private function exchange_code_for_tokens( string $code ) {
		$redirect_uri = rest_url( 'data-signals/v1/gsc/callback' );

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'body' => array(
					'code'          => $code,
					'client_id'     => $this->client_id,
					'client_secret' => $this->client_secret,
					'redirect_uri'  => $redirect_uri,
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error(
				'token_exchange_failed',
				$body['error_description'] ?? $body['error'],
				array( 'status' => 400 )
			);
		}

		// Calculate expires_at
		if ( isset( $body['expires_in'] ) ) {
			$body['expires_at'] = time() + (int) $body['expires_in'];
		}

		return $body;
	}

	/**
	 * Refresh access token
	 *
	 * @param string $refresh_token Refresh token.
	 * @return array|null New tokens or null on failure.
	 */
	private function refresh_access_token( string $refresh_token ): ?array {
		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'body' => array(
					'refresh_token' => $refresh_token,
					'client_id'     => $this->client_id,
					'client_secret' => $this->client_secret,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return null;
		}

		return $body;
	}

	/**
	 * Get valid access token
	 *
	 * @return string|null Access token or null.
	 */
	private function get_access_token(): ?string {
		return $this->oauth_manager->get_valid_access_token(
			self::PROVIDER,
			array( $this, 'refresh_access_token' )
		);
	}

	/**
	 * Sync keywords from Google Search Console
	 */
	public function sync_keywords(): void {
		global $wpdb;

		$access_token = $this->get_access_token();
		if ( ! $access_token ) {
			error_log( 'Data Signals: GSC sync failed - no valid access token' );
			return;
		}

		// Get site URL from settings or use current site
		$site_url = get_option( 'ds_gsc_property_url', home_url() );

		$end_date   = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );

		$api_url = self::API_URL . '/sites/' . urlencode( $site_url ) . '/searchAnalytics/query';

		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'startDate'  => $start_date,
						'endDate'    => $end_date,
						'dimensions' => array( 'query', 'date' ),
						'rowLimit'   => 25000,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Data Signals: GSC API request failed - ' . $response->get_error_message() );
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['rows'] ) ) {
			error_log( 'Data Signals: GSC API returned no data' );
			return;
		}

		$table_name = $wpdb->prefix . 'ds_gsc_keywords';

		// Prepare batch insert
		$values       = array();
		$placeholders = array();

		foreach ( $body['rows'] as $row ) {
			$keyword     = $row['keys'][0] ?? '';
			$date        = $row['keys'][1] ?? '';
			$impressions = $row['impressions'] ?? 0;
			$clicks      = $row['clicks'] ?? 0;
			$position    = $row['position'] ?? 0;
			$ctr         = $row['ctr'] ?? 0;

			$placeholders[] = '(%s, %s, %d, %d, %f, %f)';
			$values[]       = $keyword;
			$values[]       = $date;
			$values[]       = $impressions;
			$values[]       = $clicks;
			$values[]       = $position;
			$values[]       = $ctr;
		}

		if ( ! empty( $placeholders ) ) {
			$query = "INSERT INTO {$table_name} (keyword, date, impressions, clicks, position, ctr) VALUES ";
			$query .= implode( ', ', $placeholders );
			$query .= ' ON DUPLICATE KEY UPDATE impressions = VALUES(impressions), clicks = VALUES(clicks), position = VALUES(position), ctr = VALUES(ctr)';

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $wpdb->prepare( $query, $values ) );

			error_log( 'Data Signals: Synced ' . count( $body['rows'] ) . ' GSC keyword records' );
		}

		// Update last sync time
		update_option( 'ds_gsc_last_sync', time(), false );
	}

	/**
	 * Get keywords
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_keywords( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_gsc_keywords';
		$limit      = absint( $request->get_param( 'limit' ) ?: 100 );
		$offset     = absint( $request->get_param( 'offset' ) ?: 0 );
		$order_by   = sanitize_text_field( $request->get_param( 'order_by' ) ?: 'revenue_estimate' );
		$order      = strtoupper( $request->get_param( 'order' ) ?: 'DESC' );

		$allowed_order_by = array( 'keyword', 'impressions', 'clicks', 'position', 'ctr', 'revenue_estimate', 'date' );
		if ( ! in_array( $order_by, $allowed_order_by, true ) ) {
			$order_by = 'revenue_estimate';
		}

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		// Get aggregated keywords (last 30 days)
		$query = $wpdb->prepare(
			"SELECT 
				keyword,
				SUM(impressions) as total_impressions,
				SUM(clicks) as total_clicks,
				AVG(position) as avg_position,
				AVG(ctr) as avg_ctr,
				AVG(revenue_estimate) as avg_revenue_estimate,
				MAX(date) as last_seen
			FROM {$table_name}
			WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
			GROUP BY keyword
			ORDER BY {$order_by} {$order}
			LIMIT %d OFFSET %d",
			$limit,
			$offset
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query, ARRAY_A );

		return new WP_REST_Response(
			array(
				'keywords' => $results,
				'count'    => count( $results ),
			),
			200
		);
	}

	/**
	 * Get revenue estimate
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_revenue_estimate( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_gsc_keywords';
		$days       = absint( $request->get_param( 'days' ) ?: 30 );

		$query = $wpdb->prepare(
			"SELECT 
				SUM(revenue_estimate) as total_revenue,
				SUM(clicks) as total_clicks,
				AVG(position) as avg_position
			FROM {$table_name}
			WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)",
			$days
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_row( $query, ARRAY_A );

		return new WP_REST_Response(
			array(
				'total_revenue' => floatval( $result['total_revenue'] ?? 0 ),
				'total_clicks'  => intval( $result['total_clicks'] ?? 0 ),
				'avg_position'  => floatval( $result['avg_position'] ?? 0 ),
				'days'          => $days,
			),
			200
		);
	}

	/**
	 * Manual sync trigger
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function manual_sync( WP_REST_Request $request ): WP_REST_Response {
		$this->sync_keywords();

		return new WP_REST_Response(
			array(
				'success'   => true,
				'message'   => __( 'Keyword sync completed.', 'data-signals' ),
				'last_sync' => get_option( 'ds_gsc_last_sync', 0 ),
			),
			200
		);
	}

	/**
	 * Handle disconnect request
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_disconnect( WP_REST_Request $request ): WP_REST_Response {
		$this->oauth_manager->delete_tokens( self::PROVIDER );
		
		// Clear scheduled sync
		wp_clear_scheduled_hook( 'ds_gsc_daily_sync' );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Disconnected from Google Search Console.', 'data-signals' ),
			),
			200
		);
	}

	/**
	 * Get site properties from GSC
	 *
	 * @return array|WP_Error List of properties or error.
	 */
	public function get_site_properties() {
		$access_token = $this->get_access_token();
		if ( ! $access_token ) {
			return new WP_Error( 'no_token', __( 'Not authorized.', 'data-signals' ) );
		}

		$response = wp_remote_get(
			self::API_URL . '/sites',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return $body['siteEntry'] ?? array();
	}
}
