<?php
/**
 * Data Signals Uninstall
 * 
 * Removes all plugin data when the plugin is deleted.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete options
delete_option('ds_version');
delete_option('ds_db_version');
delete_option('ds_settings');
delete_option('ds_realtime_pageviews');

// Drop tables
$tables = [
    'ds_site_stats',
    'ds_page_stats',
    'ds_paths',
    'ds_referrer_stats',
    'ds_referrers',
    'ds_dates',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
}

// Remove capabilities
$role = get_role('administrator');
if ($role) {
    $role->remove_cap('view_data_signals');
    $role->remove_cap('manage_data_signals');
}

// Delete upload directory
$upload_dir = wp_upload_dir(null, false);
$ds_dir = $upload_dir['basedir'] . '/data-signals';

if (is_dir($ds_dir)) {
    // Recursively delete
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($ds_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    
    rmdir($ds_dir);
}
