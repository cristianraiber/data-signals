<?php

namespace DataSignals;

class Admin {
    
    /**
     * Default settings values
     */
    private const DEFAULTS = [
        'exclude_admins'       => true,
        'exclude_bots'         => true,
        'honor_dnt'            => true,
        'data_retention_days'  => '90',
        'default_period'       => '30',
    ];
    
    public function init(): void {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_settings_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    public function add_menu(): void {
        add_menu_page(
            __('Data Signals', 'data-signals'),
            __('Analytics', 'data-signals'),
            'view_data_signals',
            'data-signals',
            [$this, 'render_dashboard'],
            'dashicons-chart-line',
            30
        );
        
        add_submenu_page(
            'data-signals',
            __('Dashboard', 'data-signals'),
            __('Dashboard', 'data-signals'),
            'view_data_signals',
            'data-signals',
            [$this, 'render_dashboard']
        );
        
        add_submenu_page(
            'data-signals',
            __('Settings', 'data-signals'),
            __('Settings', 'data-signals'),
            'manage_data_signals',
            'data-signals-settings',
            [$this, 'render_settings']
        );
    }
    
    /**
     * Register REST API routes for settings
     */
    public function register_rest_routes(): void {
        register_rest_route('data-signals/v1', '/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_settings'],
                'permission_callback' => [$this, 'can_manage_settings'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'save_settings'],
                'permission_callback' => [$this, 'can_manage_settings'],
            ],
        ]);
    }
    
    /**
     * Permission callback for settings endpoints
     */
    public function can_manage_settings(): bool {
        return current_user_can('manage_data_signals');
    }
    
    /**
     * GET settings endpoint
     */
    public function get_settings(): \WP_REST_Response {
        $settings = get_option('data_signals_settings', []);
        $settings = wp_parse_args($settings, self::DEFAULTS);
        
        return new \WP_REST_Response($settings, 200);
    }
    
    /**
     * POST settings endpoint
     */
    public function save_settings(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params();
        
        // Sanitize settings
        $sanitized = [
            'exclude_admins'       => !empty($data['exclude_admins']),
            'exclude_bots'         => !empty($data['exclude_bots']),
            'honor_dnt'            => !empty($data['honor_dnt']),
            'data_retention_days'  => sanitize_text_field($data['data_retention_days'] ?? '90'),
            'default_period'       => sanitize_text_field($data['default_period'] ?? '30'),
        ];
        
        update_option('data_signals_settings', $sanitized);
        
        return new \WP_REST_Response($sanitized, 200);
    }
    
    /**
     * Get a single setting value
     */
    public static function get_setting(string $key, $default = null) {
        $settings = get_option('data_signals_settings', []);
        $settings = wp_parse_args($settings, self::DEFAULTS);
        
        return $settings[$key] ?? $default;
    }
    
    /**
     * Enqueue settings page assets (React app)
     */
    public function enqueue_settings_assets(string $hook): void {
        if ($hook !== 'analytics_page_data-signals-settings') {
            return;
        }
        
        $asset_file = DS_DIR . '/build/settings/index.asset.php';
        
        if (!file_exists($asset_file)) {
            return;
        }
        
        $asset = include $asset_file;
        
        wp_enqueue_script(
            'ds-settings',
            plugins_url('build/settings/index.js', DS_FILE),
            $asset['dependencies'],
            $asset['version'],
            true
        );
        
        wp_enqueue_style(
            'ds-settings',
            plugins_url('build/settings/style-index.css', DS_FILE),
            ['wp-components'],
            $asset['version']
        );
        
        // Pass data to JavaScript
        wp_localize_script('ds-settings', 'dataSignalsAdmin', [
            'restUrl'   => rest_url(),
            'restNonce' => wp_create_nonce('wp_rest'),
            'version'   => DS_VERSION,
        ]);
        
        wp_set_script_translations('ds-settings', 'data-signals');
    }
    
    public function render_dashboard(): void {
        (new Dashboard())->show();
    }
    
    public function render_settings(): void {
        $asset_file = DS_DIR . '/build/settings/index.asset.php';
        
        if (file_exists($asset_file)) {
            // React settings page
            echo '<div id="data-signals-settings"></div>';
        } else {
            // Fallback message
            echo '<div class="wrap"><h1>' . esc_html__('Settings', 'data-signals') . '</h1>';
            echo '<p>' . esc_html__('Please run npm install && npm run build to enable settings.', 'data-signals') . '</p></div>';
        }
    }
}
