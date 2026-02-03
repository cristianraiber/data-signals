<?php

namespace DataSignals;

class Controller {
    
    public function init(): void {
        // Maybe run pending migrations
        $this->maybe_migrate();
        
        // Handle collect requests (fallback when no optimized endpoint)
        $this->maybe_collect();
        
        // Load frontend tracking script
        if (!is_admin()) {
            add_action('wp', function() {
                (new Script_Loader())->enqueue();
            });
        }
        
        // Admin hooks
        if (is_admin() && !wp_doing_ajax()) {
            (new Admin())->init();
        }
    }
    
    private function maybe_migrate(): void {
        $current = get_option('ds_version', '0');
        
        if (version_compare($current, DS_VERSION, '<')) {
            (new Migrations())->run();
            update_option('ds_version', DS_VERSION);
        }
    }
    
    private function maybe_collect(): void {
        if (($_GET['action'] ?? '') !== 'ds_collect') {
            return;
        }
        
        $this->collect_request();
    }
    
    private function collect_request(): void {
        // Ignore bots
        $detector = new Device_Detector();
        if ($detector->is_bot()) {
            $this->send_response(204);
        }
        
        // Check if excluded
        if (function_exists('DataSignals\\is_request_excluded') && is_request_excluded()) {
            $this->send_response(204);
        }
        
        $params = array_merge($_GET, $_POST);
        $data = $this->extract_pageview_data($params, $detector);
        
        if (empty($data)) {
            $this->send_response(400);
        }
        
        $success = $this->write_to_buffer($data);
        $this->send_response($success ? 200 : 500);
    }
    
    private function extract_pageview_data(array $params, Device_Detector $detector): array {
        if (!isset($params['p'])) {
            return [];
        }
        
        $path = substr(trim($params['p']), 0, 2000);
        $post_id = filter_var($params['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $referrer = !empty($params['r']) ? filter_var(trim($params['r']), FILTER_VALIDATE_URL) : '';
        
        if ($referrer === false) {
            $referrer = '';
        }
        
        // Validate path
        if (filter_var("https://localhost{$path}", FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED) === false) {
            return [];
        }
        
        // Determine visitor uniqueness via fingerprint
        $hash = hash('xxh64', $path);
        [$new_visitor, $unique_pageview] = Fingerprinter::determine_uniqueness($hash);
        
        // Get device info
        $device = $detector->detect();
        
        // Get geo info (async-safe with caching)
        $geo = Geo_Locator::get_country();
        
        // Get UTM params
        $utm = Campaign_Tracker::extract($params['u'] ?? ''); // 'u' = full URL with query
        
        return [
            'p',                                    // 0: type indicator
            time(),                                 // 1: unix timestamp
            $path,                                  // 2: page path
            $post_id,                               // 3: post ID
            $new_visitor ? 1 : 0,                   // 4: new visitor
            $unique_pageview ? 1 : 0,               // 5: unique pageview
            substr($referrer, 0, 255),              // 6: referrer URL
            $device['device_type'],                 // 7: device type
            $device['browser'],                     // 8: browser
            $device['os'],                          // 9: OS
            $geo['country_code'],                   // 10: country code
            $geo['country_name'],                   // 11: country name
            $utm['utm_source'],                     // 12: utm_source
            $utm['utm_medium'],                     // 13: utm_medium
            $utm['utm_campaign'],                   // 14: utm_campaign
            $utm['utm_content'],                    // 15: utm_content
            $utm['utm_term'],                       // 16: utm_term
        ];
    }
    
    private function write_to_buffer(array $data): bool {
        $filename = get_buffer_filename();
        $dir = dirname($filename);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $content = serialize($data) . PHP_EOL;
        return (bool) file_put_contents($filename, $content, FILE_APPEND);
    }
    
    private function send_response(int $code): void {
        $messages = [
            200 => 'OK',
            204 => 'No Content',
            400 => 'Bad Request',
            500 => 'Internal Server Error',
        ];
        
        header("{$_SERVER['SERVER_PROTOCOL']} {$code} " . ($messages[$code] ?? ''));
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        header('Tk: N'); // Do Not Track indicator
        exit;
    }
}
