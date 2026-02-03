<?php

namespace DataSignals;

class Aggregator {
    
    private array $site_stats = [];
    private array $page_stats = [];
    private array $referrer_stats = [];
    private array $device_stats = [];
    private array $geo_stats = [];
    private array $campaign_stats = [];
    private array $click_stats = [];
    private array $realtime = [];
    
    /**
     * Main aggregation method (called by cron)
     */
    public static function run(): void {
        $aggregator = new self();
        $aggregator->process_buffer();
    }
    
    /**
     * Process the buffer file
     */
    public function process_buffer(): void {
        $buffer_file = get_buffer_filename();
        
        if (!is_file($buffer_file) || filesize($buffer_file) === 0) {
            return;
        }
        
        // Mark file as busy
        $busy_file = $buffer_file . '.busy';
        if (!rename($buffer_file, $busy_file)) {
            return;
        }
        
        // Process each line
        $handle = fopen($busy_file, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                
                $data = @unserialize($line);
                if (!is_array($data) || empty($data)) {
                    continue;
                }
                
                $type = array_shift($data);
                $this->process_line($type, $data);
            }
            fclose($handle);
        }
        
        // Commit to database
        $this->commit();
        
        // Delete processed file
        unlink($busy_file);
    }
    
    /**
     * Process a single line from the buffer
     */
    private function process_line(string $type, array $params): void {
        if ($type === 'c') {
            $this->process_click($params);
            return;
        }
        
        if ($type !== 'p') {
            return;
        }
        
        // Extract params (handle both old and new format)
        $timestamp      = $params[0] ?? 0;
        $path           = $params[1] ?? '';
        $post_id        = $params[2] ?? 0;
        $new_visitor    = $params[3] ?? 0;
        $unique_pageview = $params[4] ?? 0;
        $referrer_url   = $params[5] ?? '';
        
        // Enhanced tracking data (may be missing in old buffer entries)
        $device_type    = $params[6] ?? 'desktop';
        $browser        = $params[7] ?? '';
        $os             = $params[8] ?? '';
        $country_code   = $params[9] ?? '';
        $country_name   = $params[10] ?? '';
        $utm_source     = $params[11] ?? '';
        $utm_medium     = $params[12] ?? '';
        $utm_campaign   = $params[13] ?? '';
        $utm_content    = $params[14] ?? '';
        $utm_term       = $params[15] ?? '';
        
        // Convert to local date
        $dt = new \DateTime('now', get_site_timezone());
        $dt->setTimestamp($timestamp);
        $date_key = $dt->format('Y-m-d');
        
        // Update site stats
        if (!isset($this->site_stats[$date_key])) {
            $this->site_stats[$date_key] = ['visitors' => 0, 'pageviews' => 0];
        }
        $this->site_stats[$date_key]['pageviews']++;
        if ($new_visitor) {
            $this->site_stats[$date_key]['visitors']++;
        }
        
        // Update page stats
        $path = $this->normalize_path($path);
        if (!isset($this->page_stats[$date_key])) {
            $this->page_stats[$date_key] = [];
        }
        if (!isset($this->page_stats[$date_key][$path])) {
            $this->page_stats[$date_key][$path] = [
                'visitors' => 0,
                'pageviews' => 0,
                'post_id' => $post_id,
            ];
        }
        $this->page_stats[$date_key][$path]['pageviews']++;
        if ($unique_pageview) {
            $this->page_stats[$date_key][$path]['visitors']++;
        }
        
        // Update referrer stats
        if (!empty($referrer_url)) {
            $referrer_url = $this->normalize_referrer($referrer_url);
            if ($referrer_url) {
                if (!isset($this->referrer_stats[$date_key])) {
                    $this->referrer_stats[$date_key] = [];
                }
                if (!isset($this->referrer_stats[$date_key][$referrer_url])) {
                    $this->referrer_stats[$date_key][$referrer_url] = ['visitors' => 0, 'pageviews' => 0];
                }
                $this->referrer_stats[$date_key][$referrer_url]['pageviews']++;
                if ($new_visitor) {
                    $this->referrer_stats[$date_key][$referrer_url]['visitors']++;
                }
            }
        }
        
        // Update device stats
        $device_key = "{$device_type}|{$browser}|{$os}";
        if (!isset($this->device_stats[$date_key])) {
            $this->device_stats[$date_key] = [];
        }
        if (!isset($this->device_stats[$date_key][$device_key])) {
            $this->device_stats[$date_key][$device_key] = [
                'device_type' => $device_type,
                'browser' => $browser,
                'os' => $os,
                'visitors' => 0,
                'pageviews' => 0,
            ];
        }
        $this->device_stats[$date_key][$device_key]['pageviews']++;
        if ($new_visitor) {
            $this->device_stats[$date_key][$device_key]['visitors']++;
        }
        
        // Update geo stats
        if (!empty($country_code)) {
            if (!isset($this->geo_stats[$date_key])) {
                $this->geo_stats[$date_key] = [];
            }
            if (!isset($this->geo_stats[$date_key][$country_code])) {
                $this->geo_stats[$date_key][$country_code] = [
                    'country_code' => $country_code,
                    'country_name' => $country_name,
                    'visitors' => 0,
                    'pageviews' => 0,
                ];
            }
            $this->geo_stats[$date_key][$country_code]['pageviews']++;
            if ($new_visitor) {
                $this->geo_stats[$date_key][$country_code]['visitors']++;
            }
        }
        
        // Update campaign stats
        if (!empty($utm_source) || !empty($utm_campaign)) {
            $campaign_key = "{$utm_source}|{$utm_medium}|{$utm_campaign}|{$utm_content}|{$utm_term}";
            if (!isset($this->campaign_stats[$date_key])) {
                $this->campaign_stats[$date_key] = [];
            }
            if (!isset($this->campaign_stats[$date_key][$campaign_key])) {
                $this->campaign_stats[$date_key][$campaign_key] = [
                    'utm_source' => $utm_source,
                    'utm_medium' => $utm_medium,
                    'utm_campaign' => $utm_campaign,
                    'utm_content' => $utm_content,
                    'utm_term' => $utm_term,
                    'visitors' => 0,
                    'pageviews' => 0,
                ];
            }
            $this->campaign_stats[$date_key][$campaign_key]['pageviews']++;
            if ($new_visitor) {
                $this->campaign_stats[$date_key][$campaign_key]['visitors']++;
            }
        }
        
        // Update realtime counter
        if ($timestamp > time() - 3600) {
            $key = (string) (floor($timestamp / 60) * 60);
            $this->realtime[$key] = ($this->realtime[$key] ?? 0) + 1;
        }
    }
    
    /**
     * Process a click event
     */
    private function process_click(array $params): void {
        $timestamp    = $params[0] ?? 0;
        $click_type   = $params[1] ?? '';
        $target_url   = $params[2] ?? '';
        $domain       = $params[3] ?? '';
        $unique_click = $params[4] ?? 0;
        
        if (empty($click_type) || empty($target_url)) {
            return;
        }
        
        // Convert to local date
        $dt = new \DateTime('now', get_site_timezone());
        $dt->setTimestamp($timestamp);
        $date_key = $dt->format('Y-m-d');
        
        // Build key
        $click_key = "{$click_type}|{$target_url}";
        
        if (!isset($this->click_stats[$date_key])) {
            $this->click_stats[$date_key] = [];
        }
        
        if (!isset($this->click_stats[$date_key][$click_key])) {
            $this->click_stats[$date_key][$click_key] = [
                'click_type' => $click_type,
                'target_url' => $target_url,
                'target_domain' => $domain,
                'clicks' => 0,
                'unique_clicks' => 0,
            ];
        }
        
        $this->click_stats[$date_key][$click_key]['clicks']++;
        if ($unique_click) {
            $this->click_stats[$date_key][$click_key]['unique_clicks']++;
        }
    }
    
    /**
     * Commit aggregated stats to database
     */
    private function commit(): void {
        $this->commit_site_stats();
        $this->commit_page_stats();
        $this->commit_referrer_stats();
        $this->commit_device_stats();
        $this->commit_geo_stats();
        $this->commit_campaign_stats();
        $this->commit_click_stats();
        $this->update_realtime();
    }
    
    private function commit_site_stats(): void {
        global $wpdb;
        
        foreach ($this->site_stats as $date => $stats) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}ds_site_stats (date, visitors, pageviews) 
                 VALUES (%s, %d, %d) 
                 ON DUPLICATE KEY UPDATE 
                 visitors = visitors + VALUES(visitors), 
                 pageviews = pageviews + VALUES(pageviews)",
                $date, $stats['visitors'], $stats['pageviews']
            ));
        }
        
        $this->site_stats = [];
    }
    
    private function commit_page_stats(): void {
        global $wpdb;
        
        foreach ($this->page_stats as $date => $pages) {
            $path_ids = Path_Repository::upsert(array_keys($pages));
            
            $values = [];
            foreach ($pages as $path => $stats) {
                $values[] = $wpdb->prepare(
                    '(%s, %d, %d, %d, %d)',
                    $date,
                    $path_ids[$path],
                    $stats['post_id'],
                    $stats['visitors'],
                    $stats['pageviews']
                );
            }
            
            if (!empty($values)) {
                $wpdb->query(
                    "INSERT INTO {$wpdb->prefix}ds_page_stats (date, path_id, post_id, visitors, pageviews) 
                     VALUES " . implode(',', $values) . "
                     ON DUPLICATE KEY UPDATE 
                     visitors = visitors + VALUES(visitors), 
                     pageviews = pageviews + VALUES(pageviews)"
                );
            }
        }
        
        $this->page_stats = [];
    }
    
    private function commit_referrer_stats(): void {
        global $wpdb;
        
        foreach ($this->referrer_stats as $date => $referrers) {
            $referrer_ids = Referrer_Repository::upsert(array_keys($referrers));
            
            $values = [];
            foreach ($referrers as $url => $stats) {
                $values[] = $wpdb->prepare(
                    '(%s, %d, %d, %d)',
                    $date,
                    $referrer_ids[$url],
                    $stats['visitors'],
                    $stats['pageviews']
                );
            }
            
            if (!empty($values)) {
                $wpdb->query(
                    "INSERT INTO {$wpdb->prefix}ds_referrer_stats (date, referrer_id, visitors, pageviews) 
                     VALUES " . implode(',', $values) . "
                     ON DUPLICATE KEY UPDATE 
                     visitors = visitors + VALUES(visitors), 
                     pageviews = pageviews + VALUES(pageviews)"
                );
            }
        }
        
        $this->referrer_stats = [];
    }
    
    private function commit_device_stats(): void {
        global $wpdb;
        
        foreach ($this->device_stats as $date => $devices) {
            $values = [];
            foreach ($devices as $stats) {
                $values[] = $wpdb->prepare(
                    '(%s, %s, %s, %s, %d, %d)',
                    $date,
                    $stats['device_type'],
                    $stats['browser'],
                    $stats['os'],
                    $stats['visitors'],
                    $stats['pageviews']
                );
            }
            
            if (!empty($values)) {
                $wpdb->query(
                    "INSERT INTO {$wpdb->prefix}ds_device_stats (date, device_type, browser, os, visitors, pageviews) 
                     VALUES " . implode(',', $values) . "
                     ON DUPLICATE KEY UPDATE 
                     visitors = visitors + VALUES(visitors), 
                     pageviews = pageviews + VALUES(pageviews)"
                );
            }
        }
        
        $this->device_stats = [];
    }
    
    private function commit_geo_stats(): void {
        global $wpdb;
        
        foreach ($this->geo_stats as $date => $countries) {
            $values = [];
            foreach ($countries as $stats) {
                $values[] = $wpdb->prepare(
                    '(%s, %s, %s, %d, %d)',
                    $date,
                    $stats['country_code'],
                    $stats['country_name'],
                    $stats['visitors'],
                    $stats['pageviews']
                );
            }
            
            if (!empty($values)) {
                $wpdb->query(
                    "INSERT INTO {$wpdb->prefix}ds_geo_stats (date, country_code, country_name, visitors, pageviews) 
                     VALUES " . implode(',', $values) . "
                     ON DUPLICATE KEY UPDATE 
                     visitors = visitors + VALUES(visitors), 
                     pageviews = pageviews + VALUES(pageviews)"
                );
            }
        }
        
        $this->geo_stats = [];
    }
    
    private function commit_campaign_stats(): void {
        global $wpdb;
        
        foreach ($this->campaign_stats as $date => $campaigns) {
            $values = [];
            foreach ($campaigns as $stats) {
                $values[] = $wpdb->prepare(
                    '(%s, %s, %s, %s, %s, %s, %d, %d)',
                    $date,
                    $stats['utm_source'],
                    $stats['utm_medium'],
                    $stats['utm_campaign'],
                    $stats['utm_content'],
                    $stats['utm_term'],
                    $stats['visitors'],
                    $stats['pageviews']
                );
            }
            
            if (!empty($values)) {
                $wpdb->query(
                    "INSERT INTO {$wpdb->prefix}ds_campaign_stats 
                     (date, utm_source, utm_medium, utm_campaign, utm_content, utm_term, visitors, pageviews) 
                     VALUES " . implode(',', $values) . "
                     ON DUPLICATE KEY UPDATE 
                     visitors = visitors + VALUES(visitors), 
                     pageviews = pageviews + VALUES(pageviews)"
                );
            }
        }
        
        $this->campaign_stats = [];
    }
    
    private function commit_click_stats(): void {
        global $wpdb;
        
        foreach ($this->click_stats as $date => $clicks) {
            $values = [];
            foreach ($clicks as $stats) {
                $values[] = $wpdb->prepare(
                    '(%s, %s, %s, %s, %d, %d)',
                    $date,
                    $stats['click_type'],
                    $stats['target_url'],
                    $stats['target_domain'],
                    $stats['clicks'],
                    $stats['unique_clicks']
                );
            }
            
            if (!empty($values)) {
                $wpdb->query(
                    "INSERT INTO {$wpdb->prefix}ds_click_stats 
                     (date, click_type, target_url, target_domain, clicks, unique_clicks) 
                     VALUES " . implode(',', $values) . "
                     ON DUPLICATE KEY UPDATE 
                     clicks = clicks + VALUES(clicks), 
                     unique_clicks = unique_clicks + VALUES(unique_clicks)"
                );
            }
        }
        
        $this->click_stats = [];
    }
    
    private function update_realtime(): void {
        $counts = (array) get_option('ds_realtime_pageviews', []);
        $one_hour_ago = time() - 3600;
        
        foreach ($counts as $ts => $unused) {
            if ((int) $ts < $one_hour_ago) {
                unset($counts[$ts]);
            }
        }
        
        foreach ($this->realtime as $ts => $count) {
            $counts[$ts] = ($counts[$ts] ?? 0) + $count;
        }
        
        update_option('ds_realtime_pageviews', $counts, false);
        $this->realtime = [];
    }
    
    private function normalize_path(string $path): string {
        $path = strtok($path, '?');
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        return $path;
    }
    
    private function normalize_referrer(string $url): string {
        $parsed = parse_url($url);
        if (!isset($parsed['host'])) {
            return '';
        }
        
        $site_host = parse_url(home_url(), PHP_URL_HOST);
        if ($parsed['host'] === $site_host) {
            return '';
        }
        
        $host = preg_replace('/^www\./', '', $parsed['host']);
        
        $search_engines = ['google', 'bing', 'yahoo', 'duckduckgo', 'baidu', 'yandex'];
        foreach ($search_engines as $se) {
            if (str_contains($host, $se)) {
                return $host;
            }
        }
        
        $path = $parsed['path'] ?? '';
        $path = rtrim($path, '/');
        
        return $host . $path;
    }
}
