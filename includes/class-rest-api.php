<?php
/**
 * REST API Controller
 *
 * Registers and handles REST API endpoints for revenue analytics.
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Class
 */
class REST_API {
	/**
	 * API namespace
	 *
	 * @var string
	 */
	private const NAMESPACE = 'data-signals/v1';

	/**
	 * Revenue Attribution instance
	 *
	 * @var Revenue_Attribution
	 */
	private $revenue_attribution;

	/**
	 * Purchase Funnel instance
	 *
	 * @var Purchase_Funnel
	 */
	private $purchase_funnel;

	/**
	 * Initialize REST API
	 */
	public function __construct() {
		$this->revenue_attribution = new Revenue_Attribution();
		$this->purchase_funnel     = new Purchase_Funnel();

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes(): void {
		// Revenue by source
		register_rest_route(
			self::NAMESPACE,
			'/revenue/by-source',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_revenue_by_source' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_common_args(),
			)
		);

		// Revenue by page
		register_rest_route(
			self::NAMESPACE,
			'/revenue/by-page',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_revenue_by_page' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_common_args(),
			)
		);

		// Customer journey
		register_rest_route(
			self::NAMESPACE,
			'/revenue/customer-journey/(?P<order_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_customer_journey' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'order_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Time to conversion
		register_rest_route(
			self::NAMESPACE,
			'/revenue/time-to-conversion',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_time_to_conversion' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_date_args(),
			)
		);

		// RPV by source
		register_rest_route(
			self::NAMESPACE,
			'/revenue/rpv',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_rpv_by_source' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_date_args(),
			)
		);

		// Multi-touch summary
		register_rest_route(
			self::NAMESPACE,
			'/revenue/multitouch-summary',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_multitouch_summary' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_date_args(),
			)
		);

		// Products performance
		register_rest_route(
			self::NAMESPACE,
			'/products/performance',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_products_performance' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array_merge(
					$this->get_date_args(),
					array(
						'source' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'limit'  => array(
							'type'              => 'integer',
							'default'           => 50,
							'sanitize_callback' => 'absint',
						),
					)
				),
			)
		);

		// Funnel analysis
		register_rest_route(
			self::NAMESPACE,
			'/funnel/analysis',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_funnel_analysis' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array_merge(
					$this->get_date_args(),
					array(
						'source' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					)
				),
			)
		);

		// Cart abandonment
		register_rest_route(
			self::NAMESPACE,
			'/funnel/cart-abandonment',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_cart_abandonment' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array_merge(
					$this->get_date_args(),
					array(
						'abandonment_hours' => array(
							'type'              => 'integer',
							'default'           => 24,
							'sanitize_callback' => 'absint',
						),
					)
				),
			)
		);

		// AOV by channel
		register_rest_route(
			self::NAMESPACE,
			'/funnel/aov-by-channel',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_aov_by_channel' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array_merge(
					$this->get_date_args(),
					array(
						'group_by' => array(
							'type'              => 'string',
							'default'           => 'utm_source',
							'enum'              => array( 'utm_source', 'utm_medium', 'utm_campaign' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					)
				),
			)
		);

		// Drop-off points
		register_rest_route(
			self::NAMESPACE,
			'/funnel/dropoff-points',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_dropoff_points' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_date_args(),
			)
		);
	}

	/**
	 * Get revenue by source
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_revenue_by_source( \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'start_date'       => $request->get_param( 'start_date' ),
			'end_date'         => $request->get_param( 'end_date' ),
			'attribution_type' => $request->get_param( 'attribution_type' ),
			'group_by'         => $request->get_param( 'group_by' ),
		);

		$data = $this->revenue_attribution->get_revenue_by_source( $args );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Get revenue by page
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_revenue_by_page( \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'start_date'       => $request->get_param( 'start_date' ),
			'end_date'         => $request->get_param( 'end_date' ),
			'attribution_type' => $request->get_param( 'attribution_type' ),
			'limit'            => $request->get_param( 'limit' ),
		);

		$data = $this->revenue_attribution->get_revenue_by_page( $args );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Get customer journey
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_customer_journey( \WP_REST_Request $request ): \WP_REST_Response {
		$order_id = $request->get_param( 'order_id' );
		$data     = $this->revenue_attribution->get_customer_journey( $order_id );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Get time to conversion
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_time_to_conversion( \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'start_date' => $request->get_param( 'start_date' ),
			'end_date'   => $request->get_param( 'end_date' ),
		);

		$data = $this->revenue_attribution->get_time_to_conversion( $args );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Get RPV by source
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_rpv_by_source( \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'start_date' => $request->get_param( 'start_date' ),
			'end_date'   => $request->get_param( 'end_date' ),
		);

		$data = $this->revenue_attribution->get_rpv_by_source( $args );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Get multi-touch summary
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_multitouch_summary( \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'start_date' => $request->get_param( 'start_date' ),
			'end_date'   => $request->get_param( 'end_date' ),
		);

		$data = $this->revenue_attribution->get_multitouch_summary( $args );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Get products performance
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_products_performance( \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'start_date' => $request->get_param( 'start_date' ),
			'end_date'   => $request->get_param( 'end_date' ),
			'source'     => $request->get_param( 'source' ),
			'limit'      => $request->get_param( 'limit' ),
		);

		$data = $this->purchase_funnel->get_product_performance( $args );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Get funnel analysis
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_funnel_analysis( \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'start_date' => $request->get_param( 'start_date' ),
			'end_date'   => $request->get_param( 'end_date' ),
			'source'     => $request->get_param( 'source' ),
		);

		$data = $this->purchase_funnel->get_funnel_analysis( $args );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Get cart abandonment
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_cart_abandonment( \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'start_date'        => $request->get_param( 'start_date' ),
			'end_date'          => $request->get_param( 'end_date' ),
			'abandonment_hours' => $request->get_param( 'abandonment_hours' ),
		);

		$data = $this->purchase_funnel->get_cart_abandonment( $args );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Get AOV by channel
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_aov_by_channel( \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'start_date' => $request->get_param( 'start_date' ),
			'end_date'   => $request->get_param( 'end_date' ),
			'group_by'   => $request->get_param( 'group_by' ),
		);

		$data = $this->purchase_funnel->get_aov_by_channel( $args );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Get drop-off points
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_dropoff_points( \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'start_date' => $request->get_param( 'start_date' ),
			'end_date'   => $request->get_param( 'end_date' ),
		);

		$data = $this->purchase_funnel->get_dropoff_points( $args );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Check permission
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get common arguments for endpoints
	 *
	 * @return array
	 */
	private function get_common_args(): array {
		return array_merge(
			$this->get_date_args(),
			array(
				'attribution_type' => array(
					'type'              => 'string',
					'default'           => 'last_click',
					'enum'              => array( 'first_click', 'last_click', 'linear', 'time_decay' ),
					'sanitize_callback' => 'sanitize_text_field',
				),
				'group_by'         => array(
					'type'              => 'string',
					'default'           => 'utm_source',
					'enum'              => array( 'utm_source', 'utm_medium', 'utm_campaign', 'referrer', 'country_code' ),
					'sanitize_callback' => 'sanitize_text_field',
				),
				'limit'            => array(
					'type'              => 'integer',
					'default'           => 50,
					'sanitize_callback' => 'absint',
				),
			)
		);
	}

	/**
	 * Get date arguments
	 *
	 * @return array
	 */
	private function get_date_args(): array {
		return array(
			'start_date' => array(
				'type'              => 'string',
				'default'           => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return $this->validate_date( $param );
				},
			),
			'end_date'   => array(
				'type'              => 'string',
				'default'           => gmdate( 'Y-m-d' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return $this->validate_date( $param );
				},
			),
		);
	}

	/**
	 * Validate date format
	 *
	 * @param string $date Date string.
	 * @return bool
	 */
	private function validate_date( string $date ): bool {
		$d = \DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}
}
