<?php
/**
 * Migration: Enhanced Tracking (Devices, Geo, Campaigns)
 * Version: 2.1.0
 */

namespace DataSignals;

defined('ABSPATH') or exit;

global $wpdb;
$charset = $wpdb->get_charset_collate();

// Device stats table
$wpdb->query("
    CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_device_stats (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        device_type VARCHAR(20) NOT NULL DEFAULT 'desktop',
        browser VARCHAR(50) NOT NULL DEFAULT '',
        os VARCHAR(50) NOT NULL DEFAULT '',
        visitors INT UNSIGNED NOT NULL DEFAULT 0,
        pageviews INT UNSIGNED NOT NULL DEFAULT 0,
        UNIQUE KEY unique_device (date, device_type, browser, os),
        KEY idx_date (date),
        KEY idx_device_type (device_type)
    ) {$charset}
");

// Geographic stats table
$wpdb->query("
    CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_geo_stats (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        country_code CHAR(2) NOT NULL DEFAULT '',
        country_name VARCHAR(100) NOT NULL DEFAULT '',
        visitors INT UNSIGNED NOT NULL DEFAULT 0,
        pageviews INT UNSIGNED NOT NULL DEFAULT 0,
        UNIQUE KEY unique_geo (date, country_code),
        KEY idx_date (date),
        KEY idx_country (country_code)
    ) {$charset}
");

// Campaign stats table (UTM tracking)
$wpdb->query("
    CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_campaign_stats (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        utm_source VARCHAR(100) NOT NULL DEFAULT '',
        utm_medium VARCHAR(100) NOT NULL DEFAULT '',
        utm_campaign VARCHAR(100) NOT NULL DEFAULT '',
        utm_content VARCHAR(100) NOT NULL DEFAULT '',
        utm_term VARCHAR(100) NOT NULL DEFAULT '',
        visitors INT UNSIGNED NOT NULL DEFAULT 0,
        pageviews INT UNSIGNED NOT NULL DEFAULT 0,
        UNIQUE KEY unique_campaign (date, utm_source, utm_medium, utm_campaign, utm_content, utm_term),
        KEY idx_date (date),
        KEY idx_source (utm_source),
        KEY idx_campaign (utm_campaign)
    ) {$charset}
");

// Update db version
update_option('ds_db_version', '2.1.0');
