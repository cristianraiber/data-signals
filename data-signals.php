<?php
/**
 * Plugin Name: Data Signals
 * Plugin URI: https://github.com/raibercristian/data-signals
 * Description: Privacy-focused revenue analytics with WooCommerce and Easy Digital Downloads integration
 * Version: 1.0.0
 * Author: Raiber Cristian
 * Author URI: https://raibercristian.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: data-signals
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package DataSignals
 */

namespace DataSignals;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'DATA_SIGNALS_VERSION', '1.0.0' );
define( 'DATA_SIGNALS_PLUGIN_FILE', __FILE__ );
define( 'DATA_SIGNALS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DATA_SIGNALS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader for plugin classes
 *
 * @param string $class Class name.
 */
spl_autoload_register( function( $class ) {
	$prefix   = 'DataSignals\\';
	$base_dir = __DIR__ . '/includes/';

	// Check if the class uses the namespace prefix
	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	// Get the relative class name
	$relative_class = substr( $class, $len );

	// Convert namespace separators to directory separators
	$relative_class = str_replace( '\\', '/', $relative_class );

	// Convert class name to file name (e.g., WooCommerce -> class-woocommerce.php)
	$class_parts = explode( '/', $relative_class );
	$class_name  = array_pop( $class_parts );
	$class_name  = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

	// Build the file path
	if ( ! empty( $class_parts ) ) {
		$file = $base_dir . implode( '/', $class_parts ) . '/' . $class_name;
	} else {
		$file = $base_dir . $class_name;
	}

	// If the file exists, require it
	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Main Plugin Class
 */
class Plugin {
	/**
	 * Single instance of the plugin
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * WooCommerce integration
	 *
	 * @var Integrations\WooCommerce
	 */
	private $woocommerce;

	/**
	 * EDD integration
	 *
	 * @var Integrations\EDD
	 */
	private $edd;

	/**
	 * REST API
	 *
	 * @var REST_API
	 */
	private $rest_api;

	/**
	 * Get plugin instance
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
		$this->load_integrations();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks(): void {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Initialize plugin
	 */
	public function init(): void {
		// Load text domain
		load_plugin_textdomain( 'data-signals', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Initialize REST API
		$this->rest_api = new REST_API();
	}

	/**
	 * Load e-commerce integrations
	 */
	private function load_integrations(): void {
		// WooCommerce integration
		$this->woocommerce = new Integrations\WooCommerce();

		// Easy Digital Downloads integration
		$this->edd = new Integrations\EDD();
	}

	/**
	 * Plugin activation
	 */
	public function activate(): void {
		// Create database tables
		$this->create_tables();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate(): void {
		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Create database tables
	 */
	private function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Table: ds_pageviews
		$table_pageviews = $wpdb->prefix . 'ds_pageviews';
		$sql_pageviews   = "CREATE TABLE IF NOT EXISTS $table_pageviews (
			id BIGINT UNSIGNED AUTO_INCREMENT,
			session_id CHAR(32) NOT NULL,
			page_id BIGINT UNSIGNED,
			url VARCHAR(500),
			referrer VARCHAR(500),
			utm_source VARCHAR(100),
			utm_medium VARCHAR(100),
			utm_campaign VARCHAR(100),
			utm_content VARCHAR(100),
			utm_term VARCHAR(100),
			country_code CHAR(2),
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			INDEX idx_session (session_id, created_at),
			INDEX idx_page (page_id, created_at),
			INDEX idx_utm (utm_campaign, created_at)
		) $charset_collate;";

		// Table: ds_events
		$table_events = $wpdb->prefix . 'ds_events';
		$sql_events   = "CREATE TABLE IF NOT EXISTS $table_events (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			session_id CHAR(32) NOT NULL,
			event_type VARCHAR(50) NOT NULL,
			event_value DECIMAL(10,2),
			page_id BIGINT UNSIGNED,
			product_id BIGINT UNSIGNED,
			metadata JSON,
			created_at DATETIME NOT NULL,
			INDEX idx_session (session_id),
			INDEX idx_type (event_type, created_at),
			INDEX idx_product (product_id, created_at)
		) $charset_collate;";

		// Table: ds_sessions
		$table_sessions = $wpdb->prefix . 'ds_sessions';
		$sql_sessions   = "CREATE TABLE IF NOT EXISTS $table_sessions (
			session_id CHAR(32) PRIMARY KEY,
			first_page_id BIGINT UNSIGNED,
			first_referrer VARCHAR(500),
			utm_source VARCHAR(100),
			utm_medium VARCHAR(100),
			utm_campaign VARCHAR(100),
			country_code CHAR(2),
			total_pageviews SMALLINT UNSIGNED DEFAULT 1,
			total_revenue DECIMAL(10,2) DEFAULT 0,
			first_seen DATETIME NOT NULL,
			last_seen DATETIME NOT NULL,
			INDEX idx_campaign (utm_campaign),
			INDEX idx_source (utm_source),
			INDEX idx_revenue (total_revenue)
		) $charset_collate;";

		// Table: ds_revenue_attribution
		$table_attribution = $wpdb->prefix . 'ds_revenue_attribution';
		$sql_attribution   = "CREATE TABLE IF NOT EXISTS $table_attribution (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			order_id BIGINT UNSIGNED NOT NULL,
			session_id CHAR(32) NOT NULL,
			page_id BIGINT UNSIGNED,
			attribution_type ENUM('first_click', 'last_click', 'linear', 'time_decay') NOT NULL,
			revenue_share DECIMAL(10,2) NOT NULL,
			created_at DATETIME NOT NULL,
			INDEX idx_order (order_id),
			INDEX idx_session (session_id),
			INDEX idx_page (page_id, created_at)
		) $charset_collate;";

		// Table: ds_email_clicks
		$table_email = $wpdb->prefix . 'ds_email_clicks';
		$sql_email   = "CREATE TABLE IF NOT EXISTS $table_email (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			campaign_id VARCHAR(100) NOT NULL,
			link_url VARCHAR(500) NOT NULL,
			session_id CHAR(32),
			clicked_at DATETIME NOT NULL,
			converted BOOLEAN DEFAULT FALSE,
			revenue DECIMAL(10,2) DEFAULT 0,
			INDEX idx_campaign (campaign_id, clicked_at),
			INDEX idx_session (session_id)
		) $charset_collate;";

		// Table: ds_aggregates
		$table_aggregates = $wpdb->prefix . 'ds_aggregates';
		$sql_aggregates   = "CREATE TABLE IF NOT EXISTS $table_aggregates (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			date DATE NOT NULL,
			metric_type VARCHAR(50) NOT NULL,
			dimension VARCHAR(100),
			dimension_value VARCHAR(255),
			value DECIMAL(15,2) NOT NULL,
			UNIQUE KEY unique_metric (date, metric_type, dimension, dimension_value),
			INDEX idx_date (date, metric_type)
		) $charset_collate;";

		// Execute queries
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_pageviews );
		dbDelta( $sql_events );
		dbDelta( $sql_sessions );
		dbDelta( $sql_attribution );
		dbDelta( $sql_email );
		dbDelta( $sql_aggregates );

		// Store database version
		update_option( 'data_signals_db_version', DATA_SIGNALS_VERSION );
	}
}

// Initialize plugin
function data_signals() {
	return Plugin::instance();
}

data_signals();
