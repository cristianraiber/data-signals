<?php
/**
 * Plugin Name: Data Signals
 * Plugin URI: https://github.com/cristianraiber/data-signals
 * Description: Privacy-friendly, lightweight analytics for WordPress. No cookies, no external services.
 * Version: 2.0.0
 * Author: Cristian Raiber
 * Author URI: https://github.com/cristianraiber
 * License: GPL-3.0-or-later
 * Text Domain: data-signals
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

namespace DataSignals;

if (!defined('ABSPATH')) {
    exit;
}

define('DS_VERSION', '2.0.0');
define('DS_FILE', __FILE__);
define('DS_DIR', __DIR__);

// Autoloader
require __DIR__ . '/autoload.php';

// Global functions
require __DIR__ . '/src/functions.php';

// Initialize main controller
add_action('init', function() {
    (new Controller())->init();
}, 0);

// Cron hooks
add_filter('cron_schedules', function($schedules) {
    $schedules['ds_every_minute'] = [
        'interval' => 60,
        'display' => __('Every minute', 'data-signals'),
    ];
    return $schedules;
});

add_action('ds_aggregate_stats', [Aggregator::class, 'run']);
add_action('ds_prune_data', [Pruner::class, 'run']);
add_action('ds_rotate_seed', [Fingerprinter::class, 'rotate_daily_seed']);

// REST API
add_action('rest_api_init', function() {
    (new Rest())->register_routes();
    (new Admin())->register_rest_routes();
});

// Activation/Deactivation
register_activation_hook(__FILE__, [Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Plugin::class, 'deactivate']);
