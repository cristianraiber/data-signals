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
        
        register_rest_route($namespace, '/realtime', [
            'methods' => 'GET',
            'callback' => [$this, 'get_realtime'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'since' => ['default' => '-1 hour'],
            ],
        ]);
    }
    
    public function check_permission(): bool {
        $settings = get_settings();
        return $settings['is_dashboard_public'] || current_user_can('view_data_signals');
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
    
    public function get_realtime(WP_REST_Request $request): WP_REST_Response {
        $since = $request->get_param('since');
        return new WP_REST_Response(['count' => get_realtime_pageview_count($since)]);
    }
}
