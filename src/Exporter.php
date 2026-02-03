<?php
/**
 * CSV Export functionality
 */

namespace DataSignals;

class Exporter {
    
    /**
     * Export stats to CSV
     */
    public static function export(string $type, string $start, string $end): void {
        $stats = new Stats();
        $data = [];
        $headers = [];
        $filename = "data-signals-{$type}-{$start}-to-{$end}.csv";
        
        switch ($type) {
            case 'overview':
                $headers = ['Date', 'Visitors', 'Pageviews'];
                $rows = $stats->get_stats($start, $end, 'day');
                foreach ($rows as $row) {
                    $data[] = [$row->date, $row->visitors, $row->pageviews];
                }
                break;
                
            case 'pages':
                $headers = ['Page', 'URL', 'Visitors', 'Pageviews'];
                $rows = $stats->get_pages($start, $end, 0, 1000);
                foreach ($rows as $row) {
                    $data[] = [$row->title ?: $row->path, $row->url, $row->visitors, $row->pageviews];
                }
                break;
                
            case 'referrers':
                $headers = ['Referrer', 'Visitors', 'Pageviews'];
                $rows = $stats->get_referrers($start, $end, 0, 1000);
                foreach ($rows as $row) {
                    $data[] = [$row->url, $row->visitors, $row->pageviews];
                }
                break;
                
            case 'devices':
                $headers = ['Device Type', 'Browser', 'OS', 'Visitors', 'Pageviews'];
                global $wpdb;
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT device_type, browser, os, SUM(visitors) as visitors, SUM(pageviews) as pageviews
                     FROM {$wpdb->prefix}ds_device_stats
                     WHERE date >= %s AND date <= %s
                     GROUP BY device_type, browser, os
                     ORDER BY visitors DESC",
                    $start, $end
                ));
                foreach ($rows as $row) {
                    $data[] = [$row->device_type, $row->browser, $row->os, $row->visitors, $row->pageviews];
                }
                break;
                
            case 'countries':
                $headers = ['Country Code', 'Country', 'Visitors', 'Pageviews'];
                $rows = $stats->get_countries($start, $end, 500);
                foreach ($rows as $row) {
                    $data[] = [$row->country_code, $row->country_name, $row->visitors, $row->pageviews];
                }
                break;
                
            case 'campaigns':
                $headers = ['Source', 'Medium', 'Campaign', 'Content', 'Term', 'Visitors', 'Pageviews'];
                $rows = $stats->get_campaigns($start, $end, 1000);
                foreach ($rows as $row) {
                    $data[] = [
                        $row->utm_source, $row->utm_medium, $row->utm_campaign,
                        $row->utm_content, $row->utm_term, $row->visitors, $row->pageviews
                    ];
                }
                break;
                
            case 'clicks':
                $headers = ['Type', 'URL', 'Domain', 'Clicks', 'Unique Clicks'];
                $rows = $stats->get_clicks($start, $end, '', 1000);
                foreach ($rows as $row) {
                    $data[] = [$row->click_type, $row->target_url, $row->target_domain, $row->clicks, $row->unique_clicks];
                }
                break;
                
            case 'events':
                $headers = ['Date', 'Event', 'Category', 'Page URL', 'Properties', 'Country', 'Device'];
                global $wpdb;
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT event_name, event_category, event_data, page_url, country_code, device_type, created_at
                     FROM {$wpdb->prefix}ds_events
                     WHERE created_at >= %s AND created_at <= %s
                     ORDER BY created_at DESC
                     LIMIT 5000",
                    $start . ' 00:00:00', $end . ' 23:59:59'
                ));
                foreach ($rows as $row) {
                    $data[] = [
                        $row->created_at,
                        $row->event_name,
                        $row->event_category,
                        $row->page_url,
                        $row->event_data,
                        $row->country_code,
                        $row->device_type
                    ];
                }
                break;
                
            default:
                wp_die(__('Invalid export type.', 'data-signals'));
        }
        
        self::output_csv($filename, $headers, $data);
    }
    
    /**
     * Output CSV file
     */
    private static function output_csv(string $filename, array $headers, array $data): void {
        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Handle export request
     */
    public static function handle_request(): void {
        if (!isset($_GET['ds_export']) || !current_user_can('view_data_signals')) {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'ds_export')) {
            wp_die(__('Security check failed.', 'data-signals'));
        }
        
        $type = sanitize_key($_GET['ds_export']);
        $start = sanitize_text_field($_GET['start_date'] ?? date('Y-m-d', strtotime('-28 days')));
        $end = sanitize_text_field($_GET['end_date'] ?? date('Y-m-d'));
        
        self::export($type, $start, $end);
    }
}
