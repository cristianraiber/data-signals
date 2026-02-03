<?php
/**
 * Global functions for Data Signals
 */

namespace DataSignals;

/**
 * Get the upload directory for buffer files
 */
function get_upload_dir(): string {
    if (defined('DS_UPLOAD_DIR')) {
        return DS_UPLOAD_DIR;
    }
    
    $uploads = wp_upload_dir(null, false);
    return rtrim($uploads['basedir'], '/') . '/data-signals';
}

/**
 * Get the current buffer filename
 */
function get_buffer_filename(): string {
    $upload_dir = get_upload_dir();
    
    if (is_dir($upload_dir)) {
        $files = scandir($upload_dir);
        if (is_array($files)) {
            foreach ($files as $file) {
                if (str_starts_with($file, 'buffer-') && !str_ends_with($file, '.busy')) {
                    return "{$upload_dir}/{$file}";
                }
            }
        }
    }
    
    // Generate new random filename
    $filename = 'buffer-' . bin2hex(random_bytes(16)) . '.csv';
    return "{$upload_dir}/{$filename}";
}

/**
 * Get site timezone
 */
function get_site_timezone(): \DateTimeZone {
    if (defined('DS_TIMEZONE')) {
        return new \DateTimeZone(DS_TIMEZONE);
    }
    return wp_timezone();
}

/**
 * Get client IP address
 */
function get_client_ip(): string {
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = array_map('trim', explode(',', $_SERVER[$header]));
            foreach ($ips as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * Get realtime pageview count
 */
function get_realtime_pageview_count(string $since = '-1 hour'): int {
    $counts = get_option('ds_realtime_pageviews', []);
    $threshold = strtotime($since);
    $total = 0;
    
    foreach ($counts as $timestamp => $count) {
        if ((int) $timestamp >= $threshold) {
            $total += (int) $count;
        }
    }
    
    return $total;
}

/**
 * Get plugin settings
 */
function get_settings(): array {
    $defaults = [
        'default_view' => 'last_28_days',
        'is_dashboard_public' => false,
        'prune_data_after_months' => 24,
        'exclude_administrators' => true,
        'exclude_editors' => false,
        // Geolocation settings
        'geo_use_cloudflare' => false,
        'geo_api_fallback' => false,
        'geolite2_db_path' => '',
    ];
    
    // Try new REST API settings first, fallback to old
    $settings = get_option('data_signals_settings', []);
    if (empty($settings)) {
        $settings = get_option('ds_settings', []);
    }
    
    return array_merge($defaults, (array) $settings);
}

/**
 * Check if current request should be excluded from tracking
 */
function is_request_excluded(): bool {
    // Don't track admin requests
    if (is_admin()) {
        return true;
    }
    
    // Don't track logged-in users based on settings
    if (is_user_logged_in()) {
        $settings = get_settings();
        $user = wp_get_current_user();
        
        if ($settings['exclude_administrators'] && in_array('administrator', $user->roles)) {
            return true;
        }
        
        if ($settings['exclude_editors'] && in_array('editor', $user->roles)) {
            return true;
        }
    }
    
    // Don't track preview requests
    if (is_preview()) {
        return true;
    }
    
    // Don't track feeds
    if (is_feed()) {
        return true;
    }
    
    // Allow filtering
    return apply_filters('ds_is_request_excluded', false);
}
