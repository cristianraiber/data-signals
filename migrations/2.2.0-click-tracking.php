<?php
/**
 * Migration: Click Tracking
 * Version: 2.2.0
 */

namespace DataSignals;

defined('ABSPATH') or exit;

global $wpdb;
$charset = $wpdb->get_charset_collate();

// Click events table
$wpdb->query("
    CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_click_stats (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        click_type VARCHAR(20) NOT NULL DEFAULT 'outbound',
        target_url VARCHAR(500) NOT NULL DEFAULT '',
        target_domain VARCHAR(255) NOT NULL DEFAULT '',
        clicks INT UNSIGNED NOT NULL DEFAULT 0,
        unique_clicks INT UNSIGNED NOT NULL DEFAULT 0,
        UNIQUE KEY unique_click (date, click_type, target_url(191)),
        KEY idx_date (date),
        KEY idx_type (click_type),
        KEY idx_domain (target_domain)
    ) {$charset}
");

// Update db version
update_option('ds_db_version', '2.2.0');
