<?php

namespace DataSignals;

class Stats {
    
    /**
     * Get the total date range available in the database
     */
    public function get_date_range(): array {
        global $wpdb;
        
        $result = $wpdb->get_row(
            "SELECT MIN(date) AS start, MAX(date) AS end 
             FROM {$wpdb->prefix}ds_site_stats 
             WHERE date IS NOT NULL"
        );
        
        if (!$result || !$result->start) {
            $today = new \DateTimeImmutable('now', get_site_timezone());
            return [$today, $today];
        }
        
        return [
            new \DateTimeImmutable($result->start, get_site_timezone()),
            new \DateTimeImmutable($result->end, get_site_timezone()),
        ];
    }
    
    /**
     * Get totals for a date range
     */
    public function get_totals(string $start, string $end, string $page = ''): object {
        global $wpdb;
        
        if ($page) {
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT COALESCE(SUM(s.visitors), 0) AS visitors, COALESCE(SUM(s.pageviews), 0) AS pageviews
                 FROM {$wpdb->prefix}ds_page_stats s
                 JOIN {$wpdb->prefix}ds_paths p ON p.id = s.path_id
                 WHERE s.date >= %s AND s.date <= %s AND p.path = %s",
                $start, $end, $page
            ));
        } else {
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT COALESCE(SUM(visitors), 0) AS visitors, COALESCE(SUM(pageviews), 0) AS pageviews
                 FROM {$wpdb->prefix}ds_site_stats
                 WHERE date >= %s AND date <= %s",
                $start, $end
            ));
        }
        
        if (!$result) {
            return (object) ['visitors' => 0, 'pageviews' => 0];
        }
        
        // Ensure at least 1 visitor if there are pageviews
        if ($result->visitors == 0 && $result->pageviews > 0) {
            $result->visitors = 1;
        }
        
        return $result;
    }
    
    /**
     * Get stats grouped by day/week/month
     */
    public function get_stats(string $start, string $end, string $group = 'day', string $page = ''): array {
        global $wpdb;
        
        $week_starts_on = (int) get_option('start_of_week', 0);
        $formats = [
            'day' => '%Y-%m-%d',
            'week' => $week_starts_on === 1 ? '%Y-%u' : '%Y-%U',
            'month' => '%Y-%m',
            'year' => '%Y',
        ];
        $date_format = $formats[$group] ?? $formats['day'];
        
        if ($page) {
            $sql = $wpdb->prepare(
                "SELECT d.date, COALESCE(SUM(s.visitors), 0) AS visitors, COALESCE(SUM(s.pageviews), 0) AS pageviews
                 FROM {$wpdb->prefix}ds_dates d
                 LEFT JOIN {$wpdb->prefix}ds_page_stats s 
                    JOIN {$wpdb->prefix}ds_paths p ON p.path = %s AND p.id = s.path_id
                    ON s.date = d.date
                 WHERE d.date >= %s AND d.date <= %s
                 GROUP BY DATE_FORMAT(d.date, %s)
                 ORDER BY d.date ASC",
                $page, $start, $end, $date_format
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT d.date, COALESCE(SUM(s.visitors), 0) AS visitors, COALESCE(SUM(s.pageviews), 0) AS pageviews
                 FROM {$wpdb->prefix}ds_dates d
                 LEFT JOIN {$wpdb->prefix}ds_site_stats s ON s.date = d.date
                 WHERE d.date >= %s AND d.date <= %s
                 GROUP BY DATE_FORMAT(d.date, %s)
                 ORDER BY d.date ASC",
                $start, $end, $date_format
            );
        }
        
        $results = $wpdb->get_results($sql);
        
        return array_map(function($row) {
            $row->visitors = (int) $row->visitors;
            $row->pageviews = (int) $row->pageviews;
            return $row;
        }, $results);
    }
    
    /**
     * Get top pages
     */
    public function get_pages(string $start, string $end, int $offset = 0, int $limit = 10): array {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT p.path, s.post_id, 
                    COALESCE(NULLIF(wp.post_title, ''), p.path) AS title,
                    SUM(s.visitors) AS visitors, 
                    SUM(s.pageviews) AS pageviews
             FROM {$wpdb->prefix}ds_page_stats s
             JOIN {$wpdb->prefix}ds_paths p ON p.id = s.path_id
             LEFT JOIN {$wpdb->prefix}posts wp ON wp.ID = s.post_id
             WHERE s.date >= %s AND s.date <= %s
             GROUP BY p.path, s.post_id
             ORDER BY pageviews DESC, visitors DESC
             LIMIT %d, %d",
            $start, $end, $offset, $limit
        ));
        
        return array_map(function($row) {
            $row->visitors = max(1, (int) $row->visitors);
            $row->pageviews = (int) $row->pageviews;
            $row->url = home_url($row->path);
            return $row;
        }, $results);
    }
    
    /**
     * Count total pages
     */
    public function count_pages(string $start, string $end): int {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT path_id) 
             FROM {$wpdb->prefix}ds_page_stats
             WHERE date >= %s AND date <= %s",
            $start, $end
        ));
    }
    
    /**
     * Get top referrers
     */
    public function get_referrers(string $start, string $end, int $offset = 0, int $limit = 10): array {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT r.url, SUM(s.visitors) AS visitors, SUM(s.pageviews) AS pageviews
             FROM {$wpdb->prefix}ds_referrer_stats s
             JOIN {$wpdb->prefix}ds_referrers r ON r.id = s.referrer_id
             WHERE s.date >= %s AND s.date <= %s
             GROUP BY s.referrer_id
             ORDER BY pageviews DESC, visitors DESC
             LIMIT %d, %d",
            $start, $end, $offset, $limit
        ));
        
        return array_map(function($row) {
            $row->visitors = max(1, (int) $row->visitors);
            $row->pageviews = (int) $row->pageviews;
            return $row;
        }, $results);
    }
    
    /**
     * Count total referrers
     */
    public function count_referrers(string $start, string $end): int {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT referrer_id) 
             FROM {$wpdb->prefix}ds_referrer_stats
             WHERE date >= %s AND date <= %s",
            $start, $end
        ));
    }
    
    /**
     * Get device stats
     */
    public function get_devices(string $start, string $end, string $group_by = 'device_type', int $limit = 20): array {
        global $wpdb;
        
        $valid_groups = ['device_type', 'browser', 'os'];
        if (!in_array($group_by, $valid_groups)) {
            $group_by = 'device_type';
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT {$group_by} AS label, SUM(visitors) AS visitors, SUM(pageviews) AS pageviews
             FROM {$wpdb->prefix}ds_device_stats
             WHERE date >= %s AND date <= %s
             GROUP BY {$group_by}
             ORDER BY visitors DESC
             LIMIT %d",
            $start, $end, $limit
        ));
        
        return array_map(function($row) {
            $row->visitors = (int) $row->visitors;
            $row->pageviews = (int) $row->pageviews;
            return $row;
        }, $results);
    }
    
    /**
     * Get device totals by type
     */
    public function get_device_totals(string $start, string $end): object {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN device_type = 'desktop' THEN visitors ELSE 0 END) AS desktop,
                SUM(CASE WHEN device_type = 'mobile' THEN visitors ELSE 0 END) AS mobile,
                SUM(CASE WHEN device_type = 'tablet' THEN visitors ELSE 0 END) AS tablet,
                SUM(visitors) AS total
             FROM {$wpdb->prefix}ds_device_stats
             WHERE date >= %s AND date <= %s",
            $start, $end
        ));
        
        if (!$result) {
            return (object) ['desktop' => 0, 'mobile' => 0, 'tablet' => 0, 'total' => 0];
        }
        
        return (object) [
            'desktop' => (int) $result->desktop,
            'mobile'  => (int) $result->mobile,
            'tablet'  => (int) $result->tablet,
            'total'   => (int) $result->total,
        ];
    }
    
    /**
     * Get geographic stats
     */
    public function get_countries(string $start, string $end, int $limit = 20): array {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT country_code, country_name, SUM(visitors) AS visitors, SUM(pageviews) AS pageviews
             FROM {$wpdb->prefix}ds_geo_stats
             WHERE date >= %s AND date <= %s AND country_code != ''
             GROUP BY country_code
             ORDER BY visitors DESC
             LIMIT %d",
            $start, $end, $limit
        ));
        
        return array_map(function($row) {
            $row->visitors = (int) $row->visitors;
            $row->pageviews = (int) $row->pageviews;
            return $row;
        }, $results);
    }
    
    /**
     * Get campaign stats
     */
    public function get_campaigns(string $start, string $end, int $limit = 20): array {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT utm_source, utm_medium, utm_campaign, utm_content, utm_term,
                    SUM(visitors) AS visitors, SUM(pageviews) AS pageviews
             FROM {$wpdb->prefix}ds_campaign_stats
             WHERE date >= %s AND date <= %s AND (utm_source != '' OR utm_campaign != '')
             GROUP BY utm_source, utm_medium, utm_campaign, utm_content, utm_term
             ORDER BY visitors DESC
             LIMIT %d",
            $start, $end, $limit
        ));
        
        return array_map(function($row) {
            $row->visitors = (int) $row->visitors;
            $row->pageviews = (int) $row->pageviews;
            return $row;
        }, $results);
    }
    
    /**
     * Get campaign totals by source
     */
    public function get_campaign_sources(string $start, string $end, int $limit = 10): array {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT utm_source AS source, SUM(visitors) AS visitors, SUM(pageviews) AS pageviews
             FROM {$wpdb->prefix}ds_campaign_stats
             WHERE date >= %s AND date <= %s AND utm_source != ''
             GROUP BY utm_source
             ORDER BY visitors DESC
             LIMIT %d",
            $start, $end, $limit
        ));
        
        return array_map(function($row) {
            $row->visitors = (int) $row->visitors;
            $row->pageviews = (int) $row->pageviews;
            return $row;
        }, $results);
    }
}
