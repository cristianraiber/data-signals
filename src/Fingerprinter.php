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
     * Get visitor ID (public interface)
     */
    public function get_visitor_id(): string {
        return self::generate_visitor_id();
    }
    
    /**
     * Get session ID (public interface)
     */
    public function get_session_id(): string {
        return self::generate_session_id();
    }
    
    /**
     * Generate a privacy-friendly visitor ID based on:
     * - Daily rotating seed
     * - User agent (unless GDPR no_ua enabled)
     * - IP address (anonymized if GDPR enabled)
     */
    private static function generate_visitor_id(): string {
        $seed = self::get_seed();
        $user_agent = self::get_user_agent_for_fingerprint();
        $ip = self::get_ip_for_fingerprint();
        
        return hash('xxh64', "{$seed}-{$user_agent}-{$ip}");
    }
    
    /**
     * Generate a session ID
     */
    private static function generate_session_id(): string {
        $visitor_id = self::generate_visitor_id();
        $hour = date('YmdH');
        return hash('xxh64', "{$visitor_id}-{$hour}");
    }
    
    /**
     * Get user agent for fingerprinting (respects GDPR settings)
     */
    private static function get_user_agent_for_fingerprint(): string {
        $settings = get_option('data_signals_settings', []);
        
        // If GDPR mode and no_ua is enabled, return generic UA
        if (Admin::is_gdpr_enabled() && !empty($settings['gdpr_no_ua'])) {
            return 'anonymous';
        }
        
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * Get IP for fingerprinting (respects GDPR anonymization)
     */
    private static function get_ip_for_fingerprint(): string {
        $ip = get_client_ip();
        
        // If GDPR mode, anonymize IP by zeroing last octet(s)
        if (Admin::is_gdpr_enabled()) {
            $ip = self::anonymize_ip($ip);
        }
        
        return $ip;
    }
    
    /**
     * Anonymize IP address (zero last octet for IPv4, last 80 bits for IPv6)
     */
    public static function anonymize_ip(string $ip): string {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: zero last octet (e.g., 192.168.1.100 -> 192.168.1.0)
            return preg_replace('/\.\d+$/', '.0', $ip);
        }
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: zero last 80 bits (5 groups)
            $parts = explode(':', $ip);
            if (count($parts) >= 8) {
                return implode(':', array_slice($parts, 0, 3)) . ':0:0:0:0:0';
            }
        }
        
        return $ip;
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
