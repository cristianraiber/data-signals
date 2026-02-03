<?php
/**
 * Migration: Revenue Aggregation for Multi-Currency Tracking
 * 
 * @version 2.4.1
 */

defined('ABSPATH') or exit;

global $wpdb;

$charset = $wpdb->get_charset_collate();

// Revenue stats aggregated by day and currency
$wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_revenue_stats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    event_name VARCHAR(100) NOT NULL DEFAULT 'purchase',
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    transaction_count INT UNSIGNED NOT NULL DEFAULT 0,
    avg_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    min_amount DECIMAL(15,2) DEFAULT NULL,
    max_amount DECIMAL(15,2) DEFAULT NULL,
    UNIQUE KEY date_event_currency (date, event_name, currency),
    KEY date (date),
    KEY currency (currency),
    KEY event_name (event_name)
) {$charset}");

// Product-level revenue stats
$wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_product_revenue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    product_id VARCHAR(100) NOT NULL,
    product_name VARCHAR(255) DEFAULT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    transaction_count INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY date_product_currency (date, product_id, currency),
    KEY date (date),
    KEY product_id (product_id)
) {$charset}");
