<?php
/**
 * Email Campaign REST API Controller
 *
 * Handles REST API endpoints for email campaign tracking.
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals;

defined( 'ABSPATH' ) || exit;

class Email_API {

	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	private static $namespace = 'data-signals/v1';

	/**
	 * Register REST API routes
	 */
	public static function register_routes() {
		// Track email click (authenticated)
		register_rest_route(
			self::$namespace,
			'/track/email-click',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'track_email_click' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'campaign_id' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'link_url'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					],
					'session_id'  => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// Get campaign performance
		register_rest_route(
			self::$namespace,
			'/campaigns/performance',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_campaigns_performance' ],
				'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
				'args'                => [
					'start_date' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'end_date'   => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'limit'      => [
						'type'              => 'integer',
						'default'           => 50,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// Get campaign links performance
		register_rest_route(
			self::$namespace,
			'/campaigns/(?P<id>[a-zA-Z0-9_-]+)/links',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_campaign_links' ],
				'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
				'args'                => [
					'id'         => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'start_date' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'end_date'   => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// Get campaign revenue attribution
		register_rest_route(
			self::$namespace,
			'/campaigns/(?P<id>[a-zA-Z0-9_-]+)/revenue',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_campaign_revenue' ],
				'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
				'args'                => [
					'id'         => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'cost'       => [
						'type'              => 'number',
						'default'           => 0,
						'sanitize_callback' => 'floatval',
					],
					'emails_sent' => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'start_date' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'end_date'   => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Track email click endpoint
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response Response object
	 */
	public static function track_email_click( $request ) {
		$campaign_id = $request->get_param( 'campaign_id' );
		$link_url = $request->get_param( 'link_url' );
		$session_id = $request->get_param( 'session_id' );

		// Generate session ID if not provided
		if ( empty( $session_id ) ) {
			$session_id = md5( uniqid( '', true ) . wp_rand() );
		}

		// Log the click
		$click_id = Email_Tracker::log_click( $campaign_id, $link_url, $session_id );

		if ( ! $click_id ) {
			return new \WP_REST_Response(
				[ 'error' => 'Failed to log click' ],
				500
			);
		}

		return new \WP_REST_Response(
			[
				'success'    => true,
				'click_id'   => $click_id,
				'session_id' => $session_id,
			],
			201
		);
	}

	/**
	 * Get campaigns performance endpoint
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response Response object
	 */
	public static function get_campaigns_performance( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date = $request->get_param( 'end_date' );
		$limit = $request->get_param( 'limit' );

		$date_range = [];
		if ( $start_date ) {
			$date_range['start'] = $start_date;
		}
		if ( $end_date ) {
			$date_range['end'] = $end_date;
		}

		$campaigns = Campaign_Analytics::get_all_campaigns( $date_range, $limit );

		return new \WP_REST_Response(
			[
				'success'   => true,
				'campaigns' => $campaigns,
				'total'     => count( $campaigns ),
			],
			200
		);
	}

	/**
	 * Get campaign links endpoint
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response Response object
	 */
	public static function get_campaign_links( $request ) {
		$campaign_id = $request->get_param( 'id' );
		$start_date = $request->get_param( 'start_date' );
		$end_date = $request->get_param( 'end_date' );

		$date_range = [];
		if ( $start_date ) {
			$date_range['start'] = $start_date;
		}
		if ( $end_date ) {
			$date_range['end'] = $end_date;
		}

		$links = Link_Tracker::get_campaign_links( $campaign_id, $date_range );

		return new \WP_REST_Response(
			[
				'success'     => true,
				'campaign_id' => $campaign_id,
				'links'       => $links,
				'total'       => count( $links ),
			],
			200
		);
	}

	/**
	 * Get campaign revenue endpoint
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response Response object
	 */
	public static function get_campaign_revenue( $request ) {
		$campaign_id = $request->get_param( 'id' );
		$cost = $request->get_param( 'cost' );
		$emails_sent = $request->get_param( 'emails_sent' );
		$start_date = $request->get_param( 'start_date' );
		$end_date = $request->get_param( 'end_date' );

		$date_range = [];
		if ( $start_date ) {
			$date_range['start'] = $start_date;
		}
		if ( $end_date ) {
			$date_range['end'] = $end_date;
		}

		// Get base performance
		$performance = Campaign_Analytics::get_campaign_performance( $campaign_id, $date_range );

		// Calculate ROI if cost provided
		$roi = null;
		if ( $cost > 0 ) {
			$roi = Campaign_Analytics::calculate_roi( $campaign_id, $cost, $date_range );
		}

		// Calculate revenue per email if emails_sent provided
		$revenue_per_email = null;
		if ( $emails_sent > 0 ) {
			$revenue_per_email = Campaign_Analytics::get_revenue_per_email( $campaign_id, $emails_sent, $date_range );
		}

		// Get CAC if cost provided
		$cac = null;
		if ( $cost > 0 ) {
			$cac = Campaign_Analytics::get_cac( $campaign_id, $cost, $date_range );
		}

		// Get time to conversion
		$time_to_conversion = Campaign_Analytics::get_time_to_conversion( $campaign_id );

		// Get link attribution
		$link_attribution = Link_Tracker::get_link_attribution( $campaign_id, $date_range );

		return new \WP_REST_Response(
			[
				'success'            => true,
				'campaign_id'        => $campaign_id,
				'performance'        => $performance,
				'roi'                => $roi,
				'revenue_per_email'  => $revenue_per_email,
				'cac'                => $cac,
				'time_to_conversion' => $time_to_conversion,
				'link_attribution'   => $link_attribution,
			],
			200
		);
	}

	/**
	 * Check if user can track (public endpoint - always allowed with rate limiting)
	 *
	 * @return bool True if allowed
	 */
	public static function check_permission() {
		// Rate limiting is handled by Email_Tracker class
		return true;
	}

	/**
	 * Check if user has admin permission
	 *
	 * @return bool True if user is admin
	 */
	public static function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}
}
