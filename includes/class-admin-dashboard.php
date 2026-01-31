<?php
/**
 * Admin Dashboard Controller
 * Handles React dashboard registration and enqueuing
 *
 * @package DataSignals
 */

namespace DataSignals;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Dashboard Class
 */
class Admin_Dashboard {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Plugin base path
	 *
	 * @var string
	 */
	private $plugin_path;

	/**
	 * Plugin base URL
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Constructor
	 *
	 * @param string $version Plugin version.
	 * @param string $plugin_path Plugin base path.
	 * @param string $plugin_url Plugin base URL.
	 */
	public function __construct( $version = '1.0.0', $plugin_path = '', $plugin_url = '' ) {
		$this->version     = $version;
		$this->plugin_path = $plugin_path;
		$this->plugin_url  = $plugin_url;
	}

	/**
	 * Initialize hooks
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) );
	}

	/**
	 * Add admin menu item
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Data Signals', 'data-signals' ),
			__( 'Data Signals', 'data-signals' ),
			'manage_options',
			'data-signals-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-chart-area',
			30
		);

		// Add submenu pages
		add_submenu_page(
			'data-signals-dashboard',
			__( 'Dashboard', 'data-signals' ),
			__( 'Dashboard', 'data-signals' ),
			'manage_options',
			'data-signals-dashboard',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'data-signals-dashboard',
			__( 'Settings', 'data-signals' ),
			__( 'Settings', 'data-signals' ),
			'manage_options',
			'data-signals-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render main dashboard page
	 */
	public function render_dashboard_page() {
		?>
		<div class="wrap">
			<div id="data-signals-dashboard"></div>
		</div>
		<?php
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Data Signals Settings', 'data-signals' ); ?></h1>
			<div id="data-signals-settings"></div>
			<p><?php esc_html_e( 'Settings interface coming soon.', 'data-signals' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Enqueue dashboard assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_dashboard_assets( $hook ) {
		// Only load on our dashboard page
		if ( 'toplevel_page_data-signals-dashboard' !== $hook ) {
			return;
		}

		$asset_file = $this->plugin_path . '/assets/build/index.asset.php';
		
		// Check if the build file exists
		if ( ! file_exists( $asset_file ) ) {
			// Fallback if build doesn't exist yet
			$this->enqueue_dev_notice();
			return;
		}

		$asset = include $asset_file;

		// Enqueue the React app
		wp_enqueue_script(
			'data-signals-dashboard',
			$this->plugin_url . '/assets/build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue styles
		wp_enqueue_style(
			'data-signals-dashboard',
			$this->plugin_url . '/assets/build/index.css',
			array(),
			$asset['version']
		);

		// Localize script with API settings
		wp_localize_script(
			'data-signals-dashboard',
			'dataSignalsSettings',
			array(
				'apiUrl'      => esc_url_raw( rest_url( 'data-signals/v1' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'currentUser' => wp_get_current_user()->ID,
				'siteUrl'     => get_site_url(),
				'currency'    => $this->get_currency_settings(),
				'dateFormat'  => get_option( 'date_format', 'Y-m-d' ),
				'timeFormat'  => get_option( 'time_format', 'H:i:s' ),
			)
		);

		// Add WordPress components style
		wp_enqueue_style( 'wp-components' );
	}

	/**
	 * Show development notice if build doesn't exist
	 */
	private function enqueue_dev_notice() {
		?>
		<div class="wrap">
			<div class="notice notice-warning">
				<p>
					<strong><?php esc_html_e( 'Data Signals Dashboard:', 'data-signals' ); ?></strong>
					<?php esc_html_e( 'The dashboard assets need to be built. Run:', 'data-signals' ); ?>
				</p>
				<pre style="background: #f0f0f0; padding: 10px; border-radius: 4px;">
cd <?php echo esc_html( $this->plugin_path ); ?>
npm install
npm run build
				</pre>
			</div>
		</div>
		<?php
	}

	/**
	 * Get currency settings
	 *
	 * @return array Currency settings.
	 */
	private function get_currency_settings() {
		$currency = 'USD';
		$symbol   = '$';

		// Try to get from WooCommerce
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$currency = get_woocommerce_currency();
			$symbol   = get_woocommerce_currency_symbol( $currency );
		}

		// Try to get from EDD
		if ( function_exists( 'edd_get_currency' ) ) {
			$currency = edd_get_currency();
			$symbol   = edd_currency_symbol( $currency );
		}

		return array(
			'code'   => $currency,
			'symbol' => $symbol,
		);
	}

	/**
	 * Register REST API routes
	 */
	public function register_api_routes() {
		// This will be called from the main plugin file
		// Routes are defined in separate REST API controller classes
	}
}
