<?php
/**
 * GeoLite2 Database Auto-Updater
 * Downloads and updates the MaxMind GeoLite2-Country database
 */

namespace DataSignals;

class GeoLite2_Updater {
    
    private const DOWNLOAD_URL = 'https://download.maxmind.com/app/geoip_download';
    private const EDITION_ID = 'GeoLite2-Country';
    private const CRON_HOOK = 'ds_geolite2_update';
    
    /**
     * Initialize the updater
     */
    public static function init(): void {
        // Register cron hook
        add_action(self::CRON_HOOK, [self::class, 'maybe_update']);
        
        // Schedule weekly update if not scheduled
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'weekly', self::CRON_HOOK);
        }
    }
    
    /**
     * Unschedule cron on deactivation
     */
    public static function deactivate(): void {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
    
    /**
     * Check if update is needed and run it
     */
    public static function maybe_update(): array {
        $settings = get_settings();
        
        if (empty($settings['maxmind_license_key'])) {
            return [
                'success' => false,
                'message' => __('MaxMind license key not configured.', 'data-signals'),
            ];
        }
        
        // Check if update is needed (older than 7 days)
        $db_path = self::get_db_path();
        if (file_exists($db_path)) {
            $age = time() - filemtime($db_path);
            if ($age < WEEK_IN_SECONDS) {
                return [
                    'success' => true,
                    'message' => __('Database is up to date.', 'data-signals'),
                    'skipped' => true,
                ];
            }
        }
        
        return self::download($settings['maxmind_license_key']);
    }
    
    /**
     * Force download (manual trigger)
     */
    public static function force_download(): array {
        $settings = get_settings();
        
        if (empty($settings['maxmind_license_key'])) {
            return [
                'success' => false,
                'message' => __('MaxMind license key not configured. Get a free key at maxmind.com', 'data-signals'),
            ];
        }
        
        return self::download($settings['maxmind_license_key']);
    }
    
    /**
     * Download and extract the database
     */
    public static function download(string $license_key): array {
        $license_key = trim($license_key);
        
        if (empty($license_key)) {
            return [
                'success' => false,
                'message' => __('License key is empty.', 'data-signals'),
            ];
        }
        
        // Build download URL
        $url = add_query_arg([
            'edition_id' => self::EDITION_ID,
            'license_key' => $license_key,
            'suffix' => 'tar.gz',
        ], self::DOWNLOAD_URL);
        
        // Download file
        $temp_file = download_url($url, 300); // 5 min timeout
        
        if (is_wp_error($temp_file)) {
            $error_msg = $temp_file->get_error_message();
            
            // Check for common errors
            if (strpos($error_msg, '401') !== false || strpos($error_msg, 'Unauthorized') !== false) {
                return [
                    'success' => false,
                    'message' => __('Invalid license key. Check your MaxMind account.', 'data-signals'),
                ];
            }
            
            return [
                'success' => false,
                'message' => sprintf(__('Download failed: %s', 'data-signals'), $error_msg),
            ];
        }
        
        // Extract the database
        $result = self::extract_database($temp_file);
        
        // Cleanup temp file
        @unlink($temp_file);
        
        if ($result['success']) {
            // Update last download time
            update_option('ds_geolite2_last_update', time());
        }
        
        return $result;
    }
    
    /**
     * Extract .mmdb from tar.gz
     */
    private static function extract_database(string $tar_gz_path): array {
        // Ensure upload directory exists
        $upload_dir = self::get_upload_dir();
        if (!is_dir($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }
        
        // Protect directory
        $htaccess = $upload_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
        
        $index = $upload_dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php // Silence is golden\n");
        }
        
        // Try PharData first (most reliable)
        if (class_exists('PharData')) {
            try {
                $phar = new \PharData($tar_gz_path);
                
                // Find the .mmdb file in the archive
                $mmdb_found = false;
                foreach (new \RecursiveIteratorIterator($phar) as $file) {
                    if (substr($file->getFilename(), -5) === '.mmdb') {
                        $content = file_get_contents($file->getPathname());
                        $dest = self::get_db_path();
                        
                        if (file_put_contents($dest, $content) !== false) {
                            $mmdb_found = true;
                            break;
                        }
                    }
                }
                
                if ($mmdb_found) {
                    return [
                        'success' => true,
                        'message' => __('GeoLite2 database updated successfully.', 'data-signals'),
                        'path' => self::get_db_path(),
                        'size' => filesize(self::get_db_path()),
                    ];
                }
            } catch (\Exception $e) {
                // Fall through to shell method
            }
        }
        
        // Fallback: use shell commands
        if (function_exists('exec') && self::can_use_shell()) {
            $temp_dir = sys_get_temp_dir() . '/ds_geolite2_' . uniqid();
            mkdir($temp_dir, 0755, true);
            
            // Extract tar.gz
            $escaped_tar = escapeshellarg($tar_gz_path);
            $escaped_dir = escapeshellarg($temp_dir);
            exec("tar -xzf {$escaped_tar} -C {$escaped_dir} 2>&1", $output, $return);
            
            if ($return === 0) {
                // Find .mmdb file
                $mmdb_files = glob($temp_dir . '/*/*.mmdb');
                if (!empty($mmdb_files)) {
                    $dest = self::get_db_path();
                    if (copy($mmdb_files[0], $dest)) {
                        // Cleanup
                        self::rmdir_recursive($temp_dir);
                        
                        return [
                            'success' => true,
                            'message' => __('GeoLite2 database updated successfully.', 'data-signals'),
                            'path' => $dest,
                            'size' => filesize($dest),
                        ];
                    }
                }
            }
            
            // Cleanup on failure
            self::rmdir_recursive($temp_dir);
        }
        
        return [
            'success' => false,
            'message' => __('Failed to extract database. PharData or shell access required.', 'data-signals'),
        ];
    }
    
    /**
     * Check if shell commands are available
     */
    private static function can_use_shell(): bool {
        if (!function_exists('exec')) {
            return false;
        }
        
        $disabled = explode(',', ini_get('disable_functions'));
        $disabled = array_map('trim', $disabled);
        
        return !in_array('exec', $disabled);
    }
    
    /**
     * Recursively remove directory
     */
    private static function rmdir_recursive(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::rmdir_recursive($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    /**
     * Get upload directory for GeoLite2 database
     */
    public static function get_upload_dir(): string {
        $uploads = wp_upload_dir(null, false);
        return rtrim($uploads['basedir'], '/') . '/data-signals';
    }
    
    /**
     * Get database file path
     */
    public static function get_db_path(): string {
        return self::get_upload_dir() . '/GeoLite2-Country.mmdb';
    }
    
    /**
     * Get update status for display
     */
    public static function get_status(): array {
        $settings = get_settings();
        $db_path = self::get_db_path();
        $last_update = get_option('ds_geolite2_last_update', 0);
        
        $status = [
            'has_license_key' => !empty($settings['maxmind_license_key']),
            'db_exists' => file_exists($db_path),
            'db_path' => $db_path,
            'db_size' => file_exists($db_path) ? filesize($db_path) : 0,
            'db_age_days' => file_exists($db_path) ? floor((time() - filemtime($db_path)) / DAY_IN_SECONDS) : null,
            'last_update' => $last_update ? date('Y-m-d H:i:s', $last_update) : null,
            'next_scheduled' => wp_next_scheduled(self::CRON_HOOK),
        ];
        
        return $status;
    }
}
