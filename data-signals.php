<?php
/**
 * Plugin Name: Data Signals
 * Plugin URI: https://example.com/data-signals
 * Description: Privacy-focused revenue analytics for WordPress. Track which content, campaigns, and traffic sources generate revenue.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: data-signals
 * Domain Path: /languages
 *
 * @package DataSignals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants
define( 'DATA_SIGNALS_VERSION', '1.0.0' );
define( 'DATA_SIGNALS_PLUGIN_FILE', __FILE__ );
define( 'DATA_SIGNALS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DATA_SIGNALS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DATA_SIGNALS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class Data_Signals {

	/**
	 * Single instance of the class
	 *
	 * @var Data_Signals
	 */
	private static $instance = null;

	/**
	 * Admin Dashboard instance
	 *
	 * @var DataSignals\Admin_Dashboard
	 */
	private $admin_dashboard;

	/**
	 * Get singleton instance
	 *
	 * @return Data_Signals
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize the plugin
	 */
	private function init() {
		// Load autoloader
		$this->load_autoloader();

		// Initialize components
		add_action( 'plugins_loaded', array( $this, 'init_components' ) );

		// Activation/deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Load autoloader
	 */
	private function load_autoloader() {
		// Simple autoloader for plugin classes
		spl_autoload_register( function ( $class ) {
			$prefix = 'DataSignals\\';
			$base_dir = DATA_SIGNALS_PLUGIN_DIR . 'includes/';

			$len = strlen( $prefix );
			if ( strncmp( $prefix, $class, $len ) !== 0 ) {
				return;
			}

			$relative_class = substr( $class, $len );
			$file = $base_dir . 'class-' . str_replace( '\\', '/', strtolower( str_replace( '_', '-', $relative_class ) ) ) . '.php';

			if ( file_exists( $file ) ) {
				require $file;
			}
		} );
	}

	/**
	 * Initialize plugin components
	 */
	public function init_components() {
		// Load text domain
		load_plugin_textdomain( 'data-signals', false, dirname( DATA_SIGNALS_PLUGIN_BASENAME ) . '/languages' );

		// Initialize admin dashboard
		if ( is_admin() ) {
			require_once DATA_SIGNALS_PLUGIN_DIR . 'includes/class-admin-dashboard.php';
			$this->admin_dashboard = new \DataSignals\Admin_Dashboard(
				DATA_SIGNALS_VERSION,
				DATA_SIGNALS_PLUGIN_DIR,
				DATA_SIGNALS_PLUGIN_URL
			);
			$this->admin_dashboard->init();
		}

		// Initialize REST API
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Load integrations
		$this->load_integrations();
	}

	/**
	 * Load integrations
	 */
	private function load_integrations() {
		// Google Search Console integration
		new \DataSignals\Integrations\Google_Search_Console();
		
		// GSC Settings page
		if ( is_admin() ) {
			new \DataSignals\Integrations\GSC_Settings();
		}

		// WooCommerce integration
		if ( class_exists( 'WooCommerce' ) ) {
			$wc_file = DATA_SIGNALS_PLUGIN_DIR . 'includes/integrations/class-woocommerce.php';
			if ( file_exists( $wc_file ) ) {
				require_once $wc_file;
				// Initialize WooCommerce integration
			}
		}

		// Easy Digital Downloads integration
		if ( class_exists( 'Easy_Digital_Downloads' ) ) {
			$edd_file = DATA_SIGNALS_PLUGIN_DIR . 'includes/integrations/class-edd.php';
			if ( file_exists( $edd_file ) ) {
				require_once $edd_file;
				// Initialize EDD integration
			}
		}
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		// Register REST API routes
		// These will be implemented in separate task modules

		// Example endpoint registration:
		register_rest_route(
			'data-signals/v1',
			'/analytics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_analytics_data' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);

		register_rest_route(
			'data-signals/v1',
			'/revenue-attribution',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_revenue_attribution' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);

		register_rest_route(
			'data-signals/v1',
			'/content-performance',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_content_performance' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);

		register_rest_route(
			'data-signals/v1',
			'/email-campaigns',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_email_campaigns' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);

		register_rest_route(
			'data-signals/v1',
			'/traffic-sources',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_traffic_sources' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);

		register_rest_route(
			'data-signals/v1',
			'/realtime',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_realtime_stats' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);

		register_rest_route(
			'data-signals/v1',
			'/revenue-trend',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_revenue_trend' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);

		register_rest_route(
			'data-signals/v1',
			'/conversion-funnel',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_conversion_funnel' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);

		register_rest_route(
			'data-signals/v1',
			'/email-journey/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_email_journey' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);

		register_rest_route(
			'data-signals/v1',
			'/calculate-roas',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'calculate_roas' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);
	}

	/**
	 * Check admin permissions for API
	 *
	 * @return bool
	 */
	public function check_admin_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * API Endpoint: Get analytics data
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_analytics_data( $request ) {
		// TODO: Implement actual data fetching
		return new \WP_REST_Response(
			array(
				'metrics'        => array(
					'totalRevenue' => 12500.00,
					'rpv'          => 2.50,
					'conversions'  => 125,
					'visits'       => 5000,
				),
				'trafficSources' => array(
					array( 'source' => 'Organic', 'revenue' => 5000 ),
					array( 'source' => 'Direct', 'revenue' => 3500 ),
					array( 'source' => 'Paid', 'revenue' => 2500 ),
					array( 'source' => 'Social', 'revenue' => 1000 ),
					array( 'source' => 'Email', 'revenue' => 500 ),
				),
			),
			200
		);
	}

	/**
	 * API Endpoint: Get revenue attribution
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_revenue_attribution( $request ) {
		// TODO: Implement actual data fetching
		return new \WP_REST_Response(
			array(
				'data' => array(
					array(
						'name'           => 'Google Organic',
						'revenue'        => 5000,
						'conversions'    => 50,
						'avgOrderValue'  => 100,
						'conversionRate' => 0.05,
					),
				),
			),
			200
		);
	}

	/**
	 * API Endpoint: Get content performance
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_content_performance( $request ) {
		// TODO: Implement actual data fetching
		return new \WP_REST_Response(
			array(
				'posts'               => array(),
				'topRevenueThreshold' => 500,
			),
			200
		);
	}

	/**
	 * API Endpoint: Get email campaigns
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_email_campaigns( $request ) {
		// TODO: Implement actual data fetching
		return new \WP_REST_Response(
			array(
				'campaigns' => array(),
				'allLinks'  => array(),
			),
			200
		);
	}

	/**
	 * API Endpoint: Get traffic sources
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_traffic_sources( $request ) {
		// TODO: Implement actual data fetching
		return new \WP_REST_Response(
			array(
				'sources' => array(),
			),
			200
		);
	}

	/**
	 * API Endpoint: Get realtime stats
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_realtime_stats( $request ) {
		// TODO: Implement actual data fetching
		return new \WP_REST_Response(
			array(
				'liveVisitors'      => 0,
				'revenueToday'      => 0,
				'conversionsToday'  => 0,
				'recentConversions' => array(),
				'activePages'       => array(),
			),
			200
		);
	}

	/**
	 * API Endpoint: Get revenue trend
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_revenue_trend( $request ) {
		// TODO: Implement actual data fetching
		return new \WP_REST_Response( array(), 200 );
	}

	/**
	 * API Endpoint: Get conversion funnel
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_conversion_funnel( $request ) {
		// TODO: Implement actual data fetching
		return new \WP_REST_Response( array(), 200 );
	}

	/**
	 * API Endpoint: Get email journey
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_email_journey( $request ) {
		// TODO: Implement actual data fetching
		return new \WP_REST_Response( array(), 200 );
	}

	/**
	 * API Endpoint: Calculate ROAS
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function calculate_roas( $request ) {
		// TODO: Implement actual calculation
		return new \WP_REST_Response( array(), 200 );
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Create database tables
		// Set default options
		// Schedule cron jobs
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Clean up scheduled events
		flush_rewrite_rules();
	}
}

/**
 * Initialize the plugin
 */
function data_signals() {
	return Data_Signals::instance();
}

// Start the plugin
data_signals();
