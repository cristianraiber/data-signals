<?php

namespace DataSignals;

class Aggregator {
    
    private array $site_stats = [];
    private array $page_stats = [];
    private array $referrer_stats = [];
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
        if ($type !== 'p') {
            return; // Only pageviews for now
        }
        
        [$timestamp, $path, $post_id, $new_visitor, $unique_pageview, $referrer_url] = $params;
        
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
                    $this->referrer_stats[$date_key][$referrer_url] = [
                        'visitors' => 0,
                        'pageviews' => 0,
                    ];
                }
                $this->referrer_stats[$date_key][$referrer_url]['pageviews']++;
                if ($new_visitor) {
                    $this->referrer_stats[$date_key][$referrer_url]['visitors']++;
                }
            }
        }
        
        // Update realtime counter
        if ($timestamp > time() - 3600) {
            $key = (string) (floor($timestamp / 60) * 60);
            $this->realtime[$key] = ($this->realtime[$key] ?? 0) + 1;
        }
    }
    
    /**
     * Commit aggregated stats to database
     */
    private function commit(): void {
        $this->commit_site_stats();
        $this->commit_page_stats();
        $this->commit_referrer_stats();
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
            // Get or create path IDs
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
            // Get or create referrer IDs
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
    
    private function update_realtime(): void {
        $counts = (array) get_option('ds_realtime_pageviews', []);
        $one_hour_ago = time() - 3600;
        
        // Remove old data
        foreach ($counts as $ts => $unused) {
            if ((int) $ts < $one_hour_ago) {
                unset($counts[$ts]);
            }
        }
        
        // Add new counts
        foreach ($this->realtime as $ts => $count) {
            $counts[$ts] = ($counts[$ts] ?? 0) + $count;
        }
        
        update_option('ds_realtime_pageviews', $counts, false);
        $this->realtime = [];
    }
    
    private function normalize_path(string $path): string {
        // Remove query string
        $path = strtok($path, '?');
        
        // Remove trailing slash (except for root)
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
        
        // Skip same-site referrers
        $site_host = parse_url(home_url(), PHP_URL_HOST);
        if ($parsed['host'] === $site_host) {
            return '';
        }
        
        // Remove www prefix
        $host = preg_replace('/^www\./', '', $parsed['host']);
        
        // For search engines, just keep the domain
        $search_engines = ['google', 'bing', 'yahoo', 'duckduckgo', 'baidu', 'yandex'];
        foreach ($search_engines as $se) {
            if (str_contains($host, $se)) {
                return $host;
            }
        }
        
        // Keep host + path for other referrers
        $path = $parsed['path'] ?? '';
        $path = rtrim($path, '/');
        
        return $host . $path;
    }
}
