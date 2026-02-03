<?php
/**
 * Migration: eCommerce Tracking
 * Version: 2.3.0
 */

namespace DataSignals;

defined('ABSPATH') or exit;

global $wpdb;
$charset = $wpdb->get_charset_collate();

// eCommerce events table
$wpdb->query("
    CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_ecommerce_stats (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        event_type VARCHAR(30) NOT NULL DEFAULT '',
        product_views INT UNSIGNED NOT NULL DEFAULT 0,
        add_to_cart INT UNSIGNED NOT NULL DEFAULT 0,
        remove_from_cart INT UNSIGNED NOT NULL DEFAULT 0,
        purchases INT UNSIGNED NOT NULL DEFAULT 0,
        revenue DECIMAL(10,2) NOT NULL DEFAULT 0,
        UNIQUE KEY unique_event (date, event_type),
        KEY idx_date (date)
    ) {$charset}
");

// Product stats table
$wpdb->query("
    CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_product_stats (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        product_id BIGINT UNSIGNED NOT NULL,
        product_name VARCHAR(255) NOT NULL DEFAULT '',
        views INT UNSIGNED NOT NULL DEFAULT 0,
        add_to_cart INT UNSIGNED NOT NULL DEFAULT 0,
        purchases INT UNSIGNED NOT NULL DEFAULT 0,
        revenue DECIMAL(10,2) NOT NULL DEFAULT 0,
        UNIQUE KEY unique_product (date, product_id),
        KEY idx_date (date),
        KEY idx_product (product_id)
    ) {$charset}
");

update_option('ds_db_version', '2.3.0');
