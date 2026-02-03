<?php

namespace DataSignals;

class Fingerprinter {
    
    /**
     * Initialize the daily seed file
     */
    public static function init_seed(): void {
        $seed_file = self::get_seed_file();
        
        if (!file_exists($seed_file)) {
            self::rotate_daily_seed();
        }
    }
    
    /**
     * Rotate the daily seed (called by cron at midnight)
     */
    public static function rotate_daily_seed(): void {
        $sessions_dir = get_upload_dir() . '/sessions';
        
        if (!is_dir($sessions_dir)) {
            mkdir($sessions_dir, 0755, true);
        }
        
        // Generate new seed
        $seed = bin2hex(random_bytes(32));
        file_put_contents(self::get_seed_file(), $seed);
        
        // Clean old session files
        self::cleanup_old_sessions($sessions_dir);
    }
    
    /**
     * Determine if this is a unique visitor/pageview
     */
    public static function determine_uniqueness(string $page_hash): array {
        $visitor_id = self::get_visitor_id();
        $session_file = get_upload_dir() . "/sessions/{$visitor_id}";
        $things = [];
        
        // Read existing session data if valid
        if (is_file($session_file)) {
            $midnight = (new \DateTimeImmutable('today midnight', get_site_timezone()))->getTimestamp();
            
            if (filemtime($session_file) < $midnight) {
                // Session file is from before today, delete it
                unlink($session_file);
            } else {
                $things = file($session_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            }
        }
        
        // Check if this is a new visitor (no 'p' indicator in session)
        $new_visitor = !in_array('p', $things);
        
        // Check if this is a unique pageview for this page
        $unique_pageview = $new_visitor || !in_array($page_hash, $things);
        
        // Update session file
        $append = '';
        if ($new_visitor) {
            $append .= "p\n";
        }
        if ($unique_pageview) {
            $append .= "{$page_hash}\n";
        }
        
        if ($append !== '') {
            file_put_contents($session_file, $append, FILE_APPEND);
        }
        
        return [$new_visitor, $unique_pageview];
    }
    
    /**
     * Generate a privacy-friendly visitor ID based on:
     * - Daily rotating seed
     * - User agent
     * - IP address
     */
    private static function get_visitor_id(): string {
        $seed = self::get_seed();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = get_client_ip();
        
        return hash('xxh64', "{$seed}-{$user_agent}-{$ip}");
    }
    
    /**
     * Get the current daily seed
     */
    private static function get_seed(): string {
        $seed_file = self::get_seed_file();
        
        if (!file_exists($seed_file)) {
            self::init_seed();
        }
        
        return file_get_contents($seed_file) ?: '';
    }
    
    /**
     * Get seed file path
     */
    private static function get_seed_file(): string {
        return get_upload_dir() . '/sessions/.daily_seed';
    }
    
    /**
     * Clean up old session files
     */
    private static function cleanup_old_sessions(string $dir): void {
        $files = glob($dir . '/*');
        $midnight = (new \DateTimeImmutable('today midnight', get_site_timezone()))->getTimestamp();
        
        foreach ($files as $file) {
            if (is_file($file) && !str_starts_with(basename($file), '.')) {
                if (filemtime($file) < $midnight) {
                    unlink($file);
                }
            }
        }
    }
}
