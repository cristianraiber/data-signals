<?php
/**
 * Plugin Name: Data Signals
 * Plugin URI: https://datasignals.io
 * Description: Privacy-focused revenue analytics for WordPress. Track which content, campaigns, and traffic sources make you money.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Data Signals
 * Author URI: https://datasignals.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: data-signals
 * Domain Path: /languages
 *
 * @package DataSignals
 */

namespace DataSignals;

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'DATA_SIGNALS_VERSION', '1.0.0' );
define( 'DATA_SIGNALS_PLUGIN_FILE', __FILE__ );
define( 'DATA_SIGNALS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DATA_SIGNALS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DATA_SIGNALS_DB_VERSION', '1.0.0' );

// Require autoloader.
require_once DATA_SIGNALS_PLUGIN_DIR . 'includes/class-autoloader.php';

/**
 * Initialize the plugin.
 *
 * @return void
 */
function init(): void {
	// Initialize autoloader.
	Autoloader::register();

	// Initialize main plugin class.
	Plugin::get_instance();
}

/**
 * Activation hook.
 *
 * @return void
 */
function activate(): void {
	require_once DATA_SIGNALS_PLUGIN_DIR . 'includes/class-autoloader.php';
	Autoloader::register();

	require_once DATA_SIGNALS_PLUGIN_DIR . 'includes/class-installer.php';
	Installer::activate();
}

/**
 * Deactivation hook.
 *
 * @return void
 */
function deactivate(): void {
	// Clear scheduled cron events.
	wp_clear_scheduled_hook( 'data_signals_aggregate_stats' );
	wp_clear_scheduled_hook( 'data_signals_create_partitions' );
	wp_clear_scheduled_hook( 'data_signals_process_batch' );
}

// Register hooks.
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

// Initialize plugin.
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );
