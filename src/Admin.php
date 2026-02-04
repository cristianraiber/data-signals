<?php

namespace DataSignals;

class Admin {
    
    /**
     * EU/EEA country codes for GDPR auto-detection
     */
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 
        'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 
        'SI', 'ES', 'SE', // EU
        'IS', 'LI', 'NO', // EEA
        'GB', 'CH', // UK & Switzerland (similar privacy laws)
    ];
    
    /**
     * Default settings values
     */
    private const DEFAULTS = [
        'exclude_admins'       => true,
        'exclude_bots'         => true,
        'honor_dnt'            => true,
        'data_retention_days'  => '90',
        'default_period'       => '30',
        // GDPR settings
        'gdpr_mode'            => null, // null = auto-detect
        'gdpr_anonymize_ip'    => true,
        'gdpr_no_geo'          => false,
        'gdpr_no_ua'           => false,
        'gdpr_retention_days'  => '180',
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
        
        // Auto-detect GDPR mode if not explicitly set
        if ($settings['gdpr_mode'] === null) {
            $settings['gdpr_mode'] = self::is_eu_site();
        }
        
        // Add detection info for UI
        $settings['_detected_country'] = self::detect_site_country();
        $settings['_is_eu'] = self::is_eu_site();
        
        // Add available user roles for dynamic toggles
        $settings['_user_roles'] = self::get_user_roles();
        
        return new \WP_REST_Response($settings, 200);
    }
    
    /**
     * Get all registered user roles with labels
     */
    public static function get_user_roles(): array {
        global $wp_roles;
        
        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }
        
        $roles = [];
        foreach ($wp_roles->roles as $role_slug => $role_data) {
            // Skip subscriber - usually not worth excluding
            if ($role_slug === 'subscriber') {
                continue;
            }
            
            $roles[] = [
                'slug'  => $role_slug,
                'name'  => translate_user_role($role_data['name']),
            ];
        }
        
        return $roles;
    }
    
    /**
     * POST settings endpoint
     */
    public function save_settings(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params();
        
        // Sanitize settings
        $sanitized = [
            'exclude_bots'         => !empty($data['exclude_bots']),
            'honor_dnt'            => !empty($data['honor_dnt']),
            'data_retention_days'  => sanitize_text_field($data['data_retention_days'] ?? '90'),
            'default_period'       => sanitize_text_field($data['default_period'] ?? '30'),
            // GDPR settings
            'gdpr_mode'            => isset($data['gdpr_mode']) ? (bool) $data['gdpr_mode'] : null,
            'gdpr_anonymize_ip'    => !empty($data['gdpr_anonymize_ip']),
            'gdpr_no_geo'          => !empty($data['gdpr_no_geo']),
            'gdpr_no_ua'           => !empty($data['gdpr_no_ua']),
            'gdpr_retention_days'  => sanitize_text_field($data['gdpr_retention_days'] ?? '180'),
        ];
        
        // Handle dynamic role exclusion settings
        $roles = self::get_user_roles();
        foreach ($roles as $role) {
            $key = 'exclude_role_' . $role['slug'];
            $sanitized[$key] = !empty($data[$key]);
        }
        
        update_option('data_signals_settings', $sanitized);
        
        return new \WP_REST_Response($sanitized, 200);
    }
    
    /**
     * Detect site country from locale/timezone
     */
    public static function detect_site_country(): string {
        // Try WooCommerce country first
        if (function_exists('wc_get_base_location')) {
            $location = wc_get_base_location();
            if (!empty($location['country'])) {
                return strtoupper($location['country']);
            }
        }
        
        // Try locale (e.g., de_DE, fr_FR, ro_RO)
        $locale = get_locale();
        if (preg_match('/^[a-z]{2}_([A-Z]{2})/', $locale, $matches)) {
            return $matches[1];
        }
        
        // Try timezone
        $timezone = wp_timezone_string();
        $country_map = [
            'Europe/London' => 'GB', 'Europe/Berlin' => 'DE', 'Europe/Paris' => 'FR',
            'Europe/Rome' => 'IT', 'Europe/Madrid' => 'ES', 'Europe/Amsterdam' => 'NL',
            'Europe/Brussels' => 'BE', 'Europe/Vienna' => 'AT', 'Europe/Warsaw' => 'PL',
            'Europe/Prague' => 'CZ', 'Europe/Budapest' => 'HU', 'Europe/Bucharest' => 'RO',
            'Europe/Sofia' => 'BG', 'Europe/Athens' => 'GR', 'Europe/Helsinki' => 'FI',
            'Europe/Stockholm' => 'SE', 'Europe/Oslo' => 'NO', 'Europe/Copenhagen' => 'DK',
            'Europe/Dublin' => 'IE', 'Europe/Lisbon' => 'PT', 'Europe/Zurich' => 'CH',
            'America/New_York' => 'US', 'America/Los_Angeles' => 'US', 'America/Chicago' => 'US',
        ];
        
        if (isset($country_map[$timezone])) {
            return $country_map[$timezone];
        }
        
        // Default to US if can't detect
        return 'US';
    }
    
    /**
     * Check if site is in EU/EEA
     */
    public static function is_eu_site(): bool {
        $country = self::detect_site_country();
        return in_array($country, self::EU_COUNTRIES, true);
    }
    
    /**
     * Check if GDPR mode is enabled (for use in tracking)
     */
    public static function is_gdpr_enabled(): bool {
        $settings = get_option('data_signals_settings', []);
        
        // If explicitly set, use that value
        if (isset($settings['gdpr_mode']) && $settings['gdpr_mode'] !== null) {
            return (bool) $settings['gdpr_mode'];
        }
        
        // Auto-detect based on site country
        return self::is_eu_site();
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
        
        // Always render GDPR card at top
        $this->render_gdpr_card();
        
        if (file_exists($asset_file)) {
            // React settings page
            echo '<div id="data-signals-settings"></div>';
        } else {
            // PHP fallback settings page
            $this->render_php_settings();
        }
    }
    
    /**
     * Render GDPR compliance card - integrated into settings
     */
    private function render_gdpr_card(): void {
        // GDPR is now handled via React settings or PHP fallback
        // This method is kept empty - GDPR toggle moved to main settings
    }
    
    /**
     * Render PHP-based settings page (fallback when React not built)
     */
    private function render_php_settings(): void {
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('ds_settings_save')) {
            $this->save_settings_from_post();
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'data-signals') . '</p></div>';
        }
        
        $settings = get_option('data_signals_settings', []);
        $settings = wp_parse_args($settings, self::DEFAULTS);
        
        // Auto-detect GDPR if not set
        $gdpr_enabled = $settings['gdpr_mode'] ?? self::is_eu_site();
        $detected_country = self::detect_site_country();
        $is_eu = self::is_eu_site();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Data Signals Settings', 'data-signals'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('ds_settings_save'); ?>
                
                <!-- GDPR Section -->
                <div class="ds-settings-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 24px; border-radius: 8px; margin: 20px 0;">
                    <h2 style="color: #fff; margin-top: 0; display: flex; align-items: center; gap: 10px;">
                        🛡️ <?php esc_html_e('GDPR Compliance', 'data-signals'); ?>
                        <?php if ($is_eu): ?>
                            <span style="background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 4px; font-size: 12px;">
                                <?php printf(esc_html__('Auto-detected: %s (EU)', 'data-signals'), $detected_country); ?>
                            </span>
                        <?php endif; ?>
                    </h2>
                    <p style="opacity: 0.9; margin-bottom: 20px;">
                        <?php esc_html_e('Enable GDPR mode for privacy-compliant analytics in EU/EEA countries.', 'data-signals'); ?>
                    </p>
                    
                    <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; background: rgba(255,255,255,0.15); padding: 16px; border-radius: 6px;">
                        <input type="checkbox" name="gdpr_mode" value="1" <?php checked($gdpr_enabled); ?> 
                               style="width: 20px; height: 20px;">
                        <span style="font-size: 16px; font-weight: 500;">
                            <?php esc_html_e('Enable GDPR Mode', 'data-signals'); ?>
                        </span>
                    </label>
                    
                    <div style="margin-top: 16px; padding: 16px; background: rgba(0,0,0,0.1); border-radius: 6px; font-size: 13px;">
                        <strong><?php esc_html_e('When enabled:', 'data-signals'); ?></strong>
                        <ul style="margin: 8px 0 0 20px; opacity: 0.9;">
                            <li>✓ <?php esc_html_e('IP addresses are anonymized before processing', 'data-signals'); ?></li>
                            <li>✓ <?php esc_html_e('Do Not Track (DNT) header is respected', 'data-signals'); ?></li>
                            <li>✓ <?php esc_html_e('No cookies are used (already enabled)', 'data-signals'); ?></li>
                            <li>✓ <?php esc_html_e('Data retention is limited to 6 months', 'data-signals'); ?></li>
                            <li>✓ <?php esc_html_e('No personal data is stored', 'data-signals'); ?></li>
                        </ul>
                    </div>
                </div>
                
                <!-- General Settings -->
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Exclude User Roles', 'data-signals'); ?></th>
                        <td>
                            <p class="description" style="margin-bottom: 10px;"><?php esc_html_e('Do not track pageviews from these logged-in users.', 'data-signals'); ?></p>
                            <?php foreach (self::get_user_roles() as $role): 
                                $key = 'exclude_role_' . $role['slug'];
                            ?>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked($settings[$key] ?? ($role['slug'] === 'administrator')); ?>>
                                <?php echo esc_html($role['name']); ?>
                            </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Data Retention', 'data-signals'); ?></th>
                        <td>
                            <select name="data_retention_days">
                                <option value="30" <?php selected($settings['data_retention_days'], '30'); ?>>30 <?php esc_html_e('days', 'data-signals'); ?></option>
                                <option value="90" <?php selected($settings['data_retention_days'], '90'); ?>>90 <?php esc_html_e('days', 'data-signals'); ?></option>
                                <option value="180" <?php selected($settings['data_retention_days'], '180'); ?>>180 <?php esc_html_e('days', 'data-signals'); ?></option>
                                <option value="365" <?php selected($settings['data_retention_days'], '365'); ?>>1 <?php esc_html_e('year', 'data-signals'); ?></option>
                                <option value="730" <?php selected($settings['data_retention_days'], '730'); ?>>2 <?php esc_html_e('years', 'data-signals'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Automatically delete data older than this.', 'data-signals'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Default Dashboard Period', 'data-signals'); ?></th>
                        <td>
                            <select name="default_period">
                                <option value="7" <?php selected($settings['default_period'], '7'); ?>>7 <?php esc_html_e('days', 'data-signals'); ?></option>
                                <option value="14" <?php selected($settings['default_period'], '14'); ?>>14 <?php esc_html_e('days', 'data-signals'); ?></option>
                                <option value="28" <?php selected($settings['default_period'], '28'); ?>>28 <?php esc_html_e('days', 'data-signals'); ?></option>
                                <option value="30" <?php selected($settings['default_period'], '30'); ?>>30 <?php esc_html_e('days', 'data-signals'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save settings from POST request
     */
    private function save_settings_from_post(): void {
        $sanitized = [
            'data_retention_days'  => sanitize_text_field($_POST['data_retention_days'] ?? '90'),
            'default_period'       => sanitize_text_field($_POST['default_period'] ?? '30'),
            'gdpr_mode'            => !empty($_POST['gdpr_mode']),
        ];
        
        // Handle dynamic role exclusion settings
        foreach (self::get_user_roles() as $role) {
            $key = 'exclude_role_' . $role['slug'];
            $sanitized[$key] = !empty($_POST[$key]);
        }
        
        update_option('data_signals_settings', $sanitized);
    }
}
