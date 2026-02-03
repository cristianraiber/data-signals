<?php
/**
 * Custom Event Tracking System
 * 
 * Provides API for 3rd party plugins to register and track custom events.
 * 
 * @package DataSignals
 */

namespace DataSignals;

defined('ABSPATH') or exit;

class Event_Tracker {
    
    /** @var array Registered event definitions (runtime cache) */
    private static $registered_events = [];
    
    /** @var bool Whether events have been loaded from DB */
    private static $loaded = false;
    
    /**
     * Initialize the event tracker
     */
    public static function init() {
        // Load registered events on init
        add_action('init', [__CLASS__, 'load_registered_events'], 5);
        
        // Allow plugins to register events
        add_action('init', [__CLASS__, 'do_register_events'], 10);
    }
    
    /**
     * Load registered events from database
     */
    public static function load_registered_events() {
        if (self::$loaded) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'ds_event_definitions';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            self::$loaded = true;
            return;
        }
        
        $events = $wpdb->get_results("SELECT * FROM {$table} WHERE is_active = 1");
        
        foreach ($events as $event) {
            self::$registered_events[$event->event_name] = [
                'name' => $event->event_name,
                'label' => $event->event_label,
                'category' => $event->event_category,
                'plugin' => $event->plugin_slug,
                'description' => $event->description,
                'schema' => $event->schema_json ? json_decode($event->schema_json, true) : [],
            ];
        }
        
        self::$loaded = true;
    }
    
    /**
     * Fire action for plugins to register their events
     */
    public static function do_register_events() {
        /**
         * Action: Register custom events
         * 
         * @example
         * add_action('data_signals_register_events', function() {
         *     ds_register_event('form_submitted', [
         *         'label' => 'Form Submitted',
         *         'category' => 'engagement',
         *         'plugin' => 'my-forms-plugin',
         *         'schema' => [
         *             'form_id' => 'integer',
         *             'form_name' => 'string',
         *         ]
         *     ]);
         * });
         */
        do_action('data_signals_register_events');
    }
    
    /**
     * Register a custom event type
     * 
     * @param string $event_name Unique event identifier (snake_case recommended)
     * @param array $args {
     *     @type string $label       Human-readable label
     *     @type string $category    Event category (engagement, ecommerce, custom, etc.)
     *     @type string $plugin      Plugin slug that registers this event
     *     @type string $description Optional description
     *     @type array  $schema      Expected properties and their types
     * }
     * @return bool Success
     */
    public static function register_event($event_name, $args = []) {
        global $wpdb;
        
        $event_name = sanitize_key($event_name);
        if (empty($event_name) || strlen($event_name) > 100) {
            return false;
        }
        
        $defaults = [
            'label' => ucwords(str_replace('_', ' ', $event_name)),
            'category' => 'custom',
            'plugin' => null,
            'description' => '',
            'schema' => [],
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Add to runtime cache
        self::$registered_events[$event_name] = [
            'name' => $event_name,
            'label' => $args['label'],
            'category' => $args['category'],
            'plugin' => $args['plugin'],
            'description' => $args['description'],
            'schema' => $args['schema'],
        ];
        
        // Persist to database
        $table = $wpdb->prefix . 'ds_event_definitions';
        
        $wpdb->replace($table, [
            'event_name' => $event_name,
            'event_label' => sanitize_text_field($args['label']),
            'event_category' => sanitize_key($args['category']),
            'plugin_slug' => $args['plugin'] ? sanitize_key($args['plugin']) : null,
            'description' => sanitize_textarea_field($args['description']),
            'schema_json' => !empty($args['schema']) ? wp_json_encode($args['schema']) : null,
            'is_active' => 1,
            'created_at' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']);
        
        return $wpdb->insert_id > 0 || $wpdb->rows_affected > 0;
    }
    
    /**
     * Unregister an event type
     * 
     * @param string $event_name Event identifier
     * @return bool Success
     */
    public static function unregister_event($event_name) {
        global $wpdb;
        
        unset(self::$registered_events[$event_name]);
        
        return $wpdb->update(
            $wpdb->prefix . 'ds_event_definitions',
            ['is_active' => 0],
            ['event_name' => $event_name],
            ['%d'],
            ['%s']
        ) !== false;
    }
    
    /**
     * Get all registered events
     * 
     * @param string|null $category Filter by category
     * @return array
     */
    public static function get_registered_events($category = null) {
        self::load_registered_events();
        
        if ($category) {
            return array_filter(self::$registered_events, function($event) use ($category) {
                return $event['category'] === $category;
            });
        }
        
        return self::$registered_events;
    }
    
    /**
     * Check if an event is registered
     * 
     * @param string $event_name Event identifier
     * @return bool
     */
    public static function is_registered($event_name) {
        self::load_registered_events();
        return isset(self::$registered_events[$event_name]);
    }
    
    /**
     * Get event definition
     * 
     * @param string $event_name Event identifier
     * @return array|null
     */
    public static function get_event($event_name) {
        self::load_registered_events();
        return self::$registered_events[$event_name] ?? null;
    }
    
    /**
     * Track an event (from PHP)
     * 
     * @param string $event_name Event identifier
     * @param array $data Event properties
     * @param array $context Optional context (visitor_id, page_url, etc.)
     * @return bool Success
     */
    public static function track($event_name, $data = [], $context = []) {
        global $wpdb;
        
        $event_name = sanitize_key($event_name);
        
        // Get event definition for category
        $definition = self::get_event($event_name);
        $category = $definition ? $definition['category'] : 'custom';
        
        // Build event record
        $event = [
            'event_name' => $event_name,
            'event_category' => $category,
            'event_data' => !empty($data) ? wp_json_encode($data) : null,
            'visitor_id' => $context['visitor_id'] ?? null,
            'session_id' => $context['session_id'] ?? null,
            'page_url' => isset($context['page_url']) ? esc_url_raw($context['page_url']) : null,
            'page_title' => isset($context['page_title']) ? sanitize_text_field($context['page_title']) : null,
            'referrer' => isset($context['referrer']) ? esc_url_raw($context['referrer']) : null,
            'ip_hash' => $context['ip_hash'] ?? self::hash_ip(),
            'user_agent' => isset($context['user_agent']) ? sanitize_text_field(substr($context['user_agent'], 0, 500)) : null,
            'country_code' => $context['country_code'] ?? null,
            'device_type' => $context['device_type'] ?? null,
            'created_at' => current_time('mysql'),
        ];
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ds_events',
            $event,
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($result) {
            /**
             * Action: After event tracked
             * 
             * @param string $event_name Event identifier
             * @param array $data Event properties
             * @param int $event_id Database ID
             */
            do_action('data_signals_event_tracked', $event_name, $data, $wpdb->insert_id);
        }
        
        return $result !== false;
    }
    
    /**
     * Track event from REST API request
     * 
     * @param \WP_REST_Request $request
     * @return array Result
     */
    public static function track_from_request($request) {
        $event_name = sanitize_key($request->get_param('event'));
        $data = $request->get_param('data') ?: [];
        
        if (empty($event_name)) {
            return ['success' => false, 'error' => 'Missing event name'];
        }
        
        // Sanitize data recursively
        $data = self::sanitize_event_data($data);
        
        // Build context from request
        $context = [
            'visitor_id' => sanitize_key($request->get_param('visitor_id') ?: ''),
            'session_id' => sanitize_key($request->get_param('session_id') ?: ''),
            'page_url' => esc_url_raw($request->get_param('url') ?: ''),
            'page_title' => sanitize_text_field($request->get_param('title') ?: ''),
            'referrer' => esc_url_raw($request->get_param('referrer') ?: ''),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ];
        
        // Get geo/device if available
        if (class_exists('\DataSignals\Geo_Locator')) {
            $geo = Geo_Locator::get_country();
            $context['country_code'] = $geo['country_code'] ?? null;
        }
        if (class_exists('\DataSignals\Device_Detector')) {
            $detector = new Device_Detector($context['user_agent'] ?? '');
            $device = $detector->detect();
            $context['device_type'] = $device['device_type'] ?? null;
        }
        
        $success = self::track($event_name, $data, $context);
        
        return ['success' => $success];
    }
    
    /**
     * Sanitize event data recursively
     * 
     * @param mixed $data
     * @return mixed
     */
    private static function sanitize_event_data($data) {
        if (is_array($data)) {
            return array_map([__CLASS__, 'sanitize_event_data'], $data);
        }
        if (is_string($data)) {
            return sanitize_text_field($data);
        }
        if (is_numeric($data)) {
            return $data;
        }
        if (is_bool($data)) {
            return $data;
        }
        return null;
    }
    
    /**
     * Hash IP address for privacy
     * 
     * @return string|null
     */
    private static function hash_ip() {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if (empty($ip)) {
            return null;
        }
        // Take first IP if multiple
        $ip = explode(',', $ip)[0];
        $ip = trim($ip);
        
        // Daily rotating salt for privacy
        $salt = wp_salt('auth') . date('Y-m-d');
        return hash('sha256', $ip . $salt);
    }
    
    /**
     * Get event stats for dashboard
     * 
     * @param \DateTimeImmutable $start
     * @param \DateTimeImmutable $end
     * @param int $limit
     * @return array
     */
    public static function get_stats($start, $end, $limit = 20) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'ds_events';
        
        // Top events by count
        $top_events = $wpdb->get_results($wpdb->prepare("
            SELECT 
                event_name,
                event_category,
                COUNT(*) as total_count,
                COUNT(DISTINCT visitor_id) as unique_visitors
            FROM {$events_table}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY event_name, event_category
            ORDER BY total_count DESC
            LIMIT %d
        ", $start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59'), $limit));
        
        // Events by category
        $by_category = $wpdb->get_results($wpdb->prepare("
            SELECT 
                event_category,
                COUNT(*) as total_count,
                COUNT(DISTINCT event_name) as event_types
            FROM {$events_table}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY event_category
            ORDER BY total_count DESC
        ", $start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')));
        
        // Daily trend
        $daily = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
            FROM {$events_table}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", $start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')));
        
        // Total counts
        $totals = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_events,
                COUNT(DISTINCT event_name) as unique_events,
                COUNT(DISTINCT visitor_id) as unique_visitors
            FROM {$events_table}
            WHERE created_at BETWEEN %s AND %s
        ", $start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')));
        
        return [
            'top_events' => $top_events,
            'by_category' => $by_category,
            'daily' => $daily,
            'totals' => $totals,
        ];
    }
    
    /**
     * Get recent events for live feed
     * 
     * @param int $limit
     * @return array
     */
    public static function get_recent($limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                event_name,
                event_category,
                event_data,
                page_url,
                page_title,
                country_code,
                device_type,
                created_at
            FROM {$wpdb->prefix}ds_events
            ORDER BY created_at DESC
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Get events for a specific event name
     * 
     * @param string $event_name
     * @param \DateTimeImmutable $start
     * @param \DateTimeImmutable $end
     * @param int $limit
     * @return array
     */
    public static function get_event_details($event_name, $start, $end, $limit = 100) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}ds_events
            WHERE event_name = %s
            AND created_at BETWEEN %s AND %s
            ORDER BY created_at DESC
            LIMIT %d
        ", $event_name, $start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59'), $limit));
    }
    
    /**
     * Get all event categories
     * 
     * @return array
     */
    public static function get_categories() {
        return [
            'engagement' => __('Engagement', 'data-signals'),
            'ecommerce' => __('E-commerce', 'data-signals'),
            'form' => __('Forms', 'data-signals'),
            'video' => __('Video', 'data-signals'),
            'download' => __('Downloads', 'data-signals'),
            'social' => __('Social', 'data-signals'),
            'custom' => __('Custom', 'data-signals'),
        ];
    }
}
