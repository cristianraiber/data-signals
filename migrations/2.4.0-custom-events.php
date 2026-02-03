<?php
/**
 * Migration: Custom Events System
 * 
 * @version 2.4.0
 */

defined('ABSPATH') or exit;

global $wpdb;

$charset = $wpdb->get_charset_collate();

// Event definitions - registered by plugins
$wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_event_definitions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL,
    event_label VARCHAR(200) NOT NULL,
    event_category VARCHAR(50) NOT NULL DEFAULT 'custom',
    plugin_slug VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    schema_json TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    UNIQUE KEY event_name (event_name),
    KEY category (event_category),
    KEY plugin_slug (plugin_slug)
) {$charset}");

// Events log - actual tracked events
$wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL,
    event_category VARCHAR(50) NOT NULL DEFAULT 'custom',
    event_data JSON DEFAULT NULL,
    visitor_id VARCHAR(32) DEFAULT NULL,
    session_id VARCHAR(32) DEFAULT NULL,
    page_url VARCHAR(500) DEFAULT NULL,
    page_title VARCHAR(200) DEFAULT NULL,
    referrer VARCHAR(500) DEFAULT NULL,
    ip_hash VARCHAR(64) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    country_code CHAR(2) DEFAULT NULL,
    device_type VARCHAR(20) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    KEY event_name (event_name),
    KEY event_category (event_category),
    KEY visitor_id (visitor_id),
    KEY created_at (created_at),
    KEY event_date (event_name, created_at)
) {$charset}");

// Aggregated event stats per day
$wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_event_stats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_id INT UNSIGNED NOT NULL,
    event_name VARCHAR(100) NOT NULL,
    event_category VARCHAR(50) NOT NULL DEFAULT 'custom',
    count INT UNSIGNED NOT NULL DEFAULT 0,
    unique_visitors INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY date_event (date_id, event_name),
    KEY event_name (event_name),
    KEY date_id (date_id)
) {$charset}");
