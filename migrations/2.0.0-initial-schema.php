<?php
/**
 * Data Signals 2.0.0 - Initial database schema
 */

defined('ABSPATH') or exit;

global $wpdb;

// Site-wide daily stats
$wpdb->query(
    "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_site_stats (
        date DATE PRIMARY KEY NOT NULL,
        visitors INT UNSIGNED NOT NULL DEFAULT 0,
        pageviews INT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=ascii"
);

// Page/path stats
$wpdb->query(
    "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_page_stats (
        date DATE NOT NULL,
        path_id INT UNSIGNED NOT NULL,
        post_id BIGINT UNSIGNED DEFAULT 0,
        visitors INT UNSIGNED NOT NULL DEFAULT 0,
        pageviews INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (date, path_id),
        KEY post_id (post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=ascii"
);

// Paths lookup table
$wpdb->query(
    "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_paths (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        path VARCHAR(2000) NOT NULL,
        KEY path (path(191))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

// Referrer stats
$wpdb->query(
    "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_referrer_stats (
        date DATE NOT NULL,
        referrer_id INT UNSIGNED NOT NULL,
        visitors INT UNSIGNED NOT NULL DEFAULT 0,
        pageviews INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (date, referrer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=ascii"
);

// Referrers lookup table
$wpdb->query(
    "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_referrers (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        url VARCHAR(255) NOT NULL,
        UNIQUE KEY url (url)
    ) ENGINE=InnoDB DEFAULT CHARSET=ascii"
);

// Dates helper table (for LEFT JOINs to show zero-value days)
$wpdb->query(
    "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ds_dates (
        date DATE PRIMARY KEY NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=ascii"
);

// Populate dates table (5 years back, 2 years forward)
$start = new DateTimeImmutable('-5 years');
$end = new DateTimeImmutable('+2 years');
$interval = new DateInterval('P1D');
$period = new DatePeriod($start, $interval, $end);

$values = [];
foreach ($period as $date) {
    $values[] = "('" . $date->format('Y-m-d') . "')";
    
    // Insert in batches of 365
    if (count($values) >= 365) {
        $wpdb->query("INSERT IGNORE INTO {$wpdb->prefix}ds_dates (date) VALUES " . implode(',', $values));
        $values = [];
    }
}

if (!empty($values)) {
    $wpdb->query("INSERT IGNORE INTO {$wpdb->prefix}ds_dates (date) VALUES " . implode(',', $values));
}

// Grant capabilities
$role = get_role('administrator');
if ($role) {
    $role->add_cap('view_data_signals');
    $role->add_cap('manage_data_signals');
}
