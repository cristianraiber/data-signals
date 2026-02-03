<?php

namespace DataSignals;

use DateTimeImmutable;
use WP_REST_Request;
use WP_REST_Response;

class Rest {
    
    public function register_routes(): void {
        $namespace = 'data-signals/v1';
        
        register_rest_route($namespace, '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'start_date' => ['validate_callback' => [$this, 'validate_date']],
                'end_date' => ['validate_callback' => [$this, 'validate_date']],
                'group' => ['default' => 'day'],
            ],
        ]);
        
        register_rest_route($namespace, '/totals', [
            'methods' => 'GET',
            'callback' => [$this, 'get_totals'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'start_date' => ['validate_callback' => [$this, 'validate_date']],
                'end_date' => ['validate_callback' => [$this, 'validate_date']],
            ],
        ]);
        
        register_rest_route($namespace, '/pages', [
            'methods' => 'GET',
            'callback' => [$this, 'get_pages'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'start_date' => ['validate_callback' => [$this, 'validate_date']],
                'end_date' => ['validate_callback' => [$this, 'validate_date']],
                'offset' => ['default' => 0, 'sanitize_callback' => 'absint'],
                'limit' => ['default' => 10, 'sanitize_callback' => 'absint'],
            ],
        ]);
        
        register_rest_route($namespace, '/referrers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_referrers'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'start_date' => ['validate_callback' => [$this, 'validate_date']],
                'end_date' => ['validate_callback' => [$this, 'validate_date']],
                'offset' => ['default' => 0, 'sanitize_callback' => 'absint'],
                'limit' => ['default' => 10, 'sanitize_callback' => 'absint'],
            ],
        ]);
        
        register_rest_route($namespace, '/devices', [
            'methods' => 'GET',
            'callback' => [$this, 'get_devices'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'start_date' => ['validate_callback' => [$this, 'validate_date']],
                'end_date' => ['validate_callback' => [$this, 'validate_date']],
                'group_by' => ['default' => 'device_type'],
                'limit' => ['default' => 20, 'sanitize_callback' => 'absint'],
            ],
        ]);
        
        register_rest_route($namespace, '/countries', [
            'methods' => 'GET',
            'callback' => [$this, 'get_countries'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'start_date' => ['validate_callback' => [$this, 'validate_date']],
                'end_date' => ['validate_callback' => [$this, 'validate_date']],
                'limit' => ['default' => 20, 'sanitize_callback' => 'absint'],
            ],
        ]);
        
        register_rest_route($namespace, '/campaigns', [
            'methods' => 'GET',
            'callback' => [$this, 'get_campaigns'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'start_date' => ['validate_callback' => [$this, 'validate_date']],
                'end_date' => ['validate_callback' => [$this, 'validate_date']],
                'limit' => ['default' => 20, 'sanitize_callback' => 'absint'],
            ],
        ]);
        
        register_rest_route($namespace, '/realtime', [
            'methods' => 'GET',
            'callback' => [$this, 'get_realtime'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'since' => ['default' => '-1 hour'],
            ],
        ]);
        
        // Settings endpoints
        register_rest_route($namespace, '/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
        ]);
        
        // Diagnostics endpoint
        register_rest_route($namespace, '/diagnostics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_diagnostics'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
        
        // GeoLite2 download endpoint
        register_rest_route($namespace, '/geolite2/download', [
            'methods' => 'POST',
            'callback' => [$this, 'download_geolite2'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
    }
    
    public function check_permission(): bool {
        $settings = get_settings();
        return $settings['is_dashboard_public'] || current_user_can('view_data_signals');
    }
    
    public function check_admin_permission(): bool {
        return current_user_can('manage_options');
    }
    
    public function validate_date($param): bool {
        return strtotime($param) !== false;
    }
    
    private function get_date_params(WP_REST_Request $request): array {
        $tz = get_site_timezone();
        $now = new DateTimeImmutable('now', $tz);
        
        $start = $request->get_param('start_date') 
            ?? $now->modify('-28 days')->format('Y-m-d');
        $end = $request->get_param('end_date') 
            ?? $now->format('Y-m-d');
        
        return [$start, $end];
    }
    
    public function get_stats(WP_REST_Request $request): WP_REST_Response {
        [$start, $end] = $this->get_date_params($request);
        $group = $request->get_param('group');
        $page = $request->get_param('page') ?? '';
        
        $stats = new Stats();
        $result = $stats->get_stats($start, $end, $group, $page);
        
        return new WP_REST_Response($result);
    }
    
    public function get_totals(WP_REST_Request $request): WP_REST_Response {
        [$start, $end] = $this->get_date_params($request);
        $page = $request->get_param('page') ?? '';
        
        $stats = new Stats();
        $result = $stats->get_totals($start, $end, $page);
        
        return new WP_REST_Response($result);
    }
    
    public function get_pages(WP_REST_Request $request): WP_REST_Response {
        [$start, $end] = $this->get_date_params($request);
        $offset = $request->get_param('offset');
        $limit = min($request->get_param('limit'), 100);
        
        $stats = new Stats();
        $result = [
            'items' => $stats->get_pages($start, $end, $offset, $limit),
            'total' => $stats->count_pages($start, $end),
        ];
        
        return new WP_REST_Response($result);
    }
    
    public function get_referrers(WP_REST_Request $request): WP_REST_Response {
        [$start, $end] = $this->get_date_params($request);
        $offset = $request->get_param('offset');
        $limit = min($request->get_param('limit'), 100);
        
        $stats = new Stats();
        $result = [
            'items' => $stats->get_referrers($start, $end, $offset, $limit),
            'total' => $stats->count_referrers($start, $end),
        ];
        
        return new WP_REST_Response($result);
    }
    
    public function get_devices(WP_REST_Request $request): WP_REST_Response {
        [$start, $end] = $this->get_date_params($request);
        $group_by = $request->get_param('group_by');
        $limit = min($request->get_param('limit'), 50);
        
        $stats = new Stats();
        $result = [
            'items' => $stats->get_devices($start, $end, $group_by, $limit),
            'totals' => $stats->get_device_totals($start, $end),
        ];
        
        return new WP_REST_Response($result);
    }
    
    public function get_countries(WP_REST_Request $request): WP_REST_Response {
        [$start, $end] = $this->get_date_params($request);
        $limit = min($request->get_param('limit'), 100);
        
        $stats = new Stats();
        $result = [
            'items' => $stats->get_countries($start, $end, $limit),
        ];
        
        return new WP_REST_Response($result);
    }
    
    public function get_campaigns(WP_REST_Request $request): WP_REST_Response {
        [$start, $end] = $this->get_date_params($request);
        $limit = min($request->get_param('limit'), 100);
        
        $stats = new Stats();
        $result = [
            'items' => $stats->get_campaigns($start, $end, $limit),
            'sources' => $stats->get_campaign_sources($start, $end, 10),
        ];
        
        return new WP_REST_Response($result);
    }
    
    public function get_realtime(WP_REST_Request $request): WP_REST_Response {
        $since = $request->get_param('since');
        return new WP_REST_Response(['count' => get_realtime_pageview_count($since)]);
    }
    
    public function get_settings(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(get_settings());
    }
    
    public function update_settings(WP_REST_Request $request): WP_REST_Response {
        $data = $request->get_json_params();
        $settings = get_settings();
        
        $allowed = [
            'is_dashboard_public',
            'exclude_user_roles',
            'exclude_administrators',
            'exclude_editors',
            'prune_data_after_months',
            'default_view',
            'use_cookie',
            'cookie_notice_script',
            // Geo settings
            'geo_use_cloudflare',
            'geo_api_fallback',
            'geolite2_db_path',
            'maxmind_license_key',
        ];
        
        foreach ($allowed as $key) {
            if (isset($data[$key])) {
                $settings[$key] = $data[$key];
            }
        }
        
        update_option('data_signals_settings', $settings);
        
        return new WP_REST_Response(['success' => true, 'settings' => $settings]);
    }
    
    public function get_diagnostics(WP_REST_Request $request): WP_REST_Response {
        $settings = get_settings();
        
        return new WP_REST_Response([
            'cloudflare' => Geo_Locator::check_cloudflare_status(),
            'geolite2' => Geo_Locator::check_geolite2_status(),
            'geolite2_updater' => GeoLite2_Updater::get_status(),
            'buffer' => $this->check_buffer_status(),
            'settings' => [
                'geo_use_cloudflare' => $settings['geo_use_cloudflare'] ?? false,
                'geo_api_fallback' => $settings['geo_api_fallback'] ?? false,
                'geolite2_db_path' => $settings['geolite2_db_path'] ?? '',
                'has_license_key' => !empty($settings['maxmind_license_key']),
            ],
        ]);
    }
    
    public function download_geolite2(WP_REST_Request $request): WP_REST_Response {
        $result = GeoLite2_Updater::force_download();
        
        $status_code = $result['success'] ? 200 : 400;
        
        return new WP_REST_Response($result, $status_code);
    }
    
    private function check_buffer_status(): array {
        $buffer_file = get_buffer_filename();
        $dir = dirname($buffer_file);
        
        if (!is_dir($dir)) {
            return [
                'status' => 'no_dir',
                'message' => __('Buffer directory does not exist.', 'data-signals'),
                'ok' => false,
            ];
        }
        
        if (!is_writable($dir)) {
            return [
                'status' => 'not_writable',
                'message' => __('Buffer directory is not writable.', 'data-signals'),
                'ok' => false,
            ];
        }
        
        $size = file_exists($buffer_file) ? filesize($buffer_file) : 0;
        
        return [
            'status' => 'ok',
            'message' => sprintf(__('Buffer ready (%s pending)', 'data-signals'), size_format($size)),
            'ok' => true,
            'size' => $size,
            'path' => $buffer_file,
        ];
    }
}
