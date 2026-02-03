<?php
/**
 * Rate Limiter for public endpoints
 * 
 * Prevents abuse of public tracking endpoints using transients.
 * 
 * @package DataSignals
 */

namespace DataSignals;

defined('ABSPATH') or exit;

class Rate_Limiter {
    
    /** @var int Max events per minute per IP */
    private const EVENTS_PER_MINUTE = 30;
    
    /** @var int Max events per hour per IP */
    private const EVENTS_PER_HOUR = 300;
    
    /** @var int Max payload size in bytes */
    private const MAX_PAYLOAD_SIZE = 4096;
    
    /**
     * Check if request should be rate limited
     * 
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    public static function check(): array {
        $ip = self::get_client_ip();
        $ip_hash = md5($ip . wp_salt('auth'));
        
        // Check minute limit
        $minute_key = "ds_rate_min_{$ip_hash}";
        $minute_count = (int) get_transient($minute_key);
        
        if ($minute_count >= self::EVENTS_PER_MINUTE) {
            return [
                'allowed' => false,
                'reason' => 'rate_limit_minute',
                'retry_after' => 60,
            ];
        }
        
        // Check hourly limit
        $hour_key = "ds_rate_hour_{$ip_hash}";
        $hour_count = (int) get_transient($hour_key);
        
        if ($hour_count >= self::EVENTS_PER_HOUR) {
            return [
                'allowed' => false,
                'reason' => 'rate_limit_hour',
                'retry_after' => 3600,
            ];
        }
        
        // Increment counters
        set_transient($minute_key, $minute_count + 1, 60);
        set_transient($hour_key, $hour_count + 1, 3600);
        
        return ['allowed' => true, 'reason' => null];
    }
    
    /**
     * Validate request origin
     * 
     * @return bool True if origin is valid
     */
    public static function validate_origin(): bool {
        // Get site URL
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        
        // Check Origin header
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin) {
            $origin_host = wp_parse_url($origin, PHP_URL_HOST);
            if ($origin_host && $origin_host === $site_host) {
                return true;
            }
        }
        
        // Check Referer header
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer) {
            $referer_host = wp_parse_url($referer, PHP_URL_HOST);
            if ($referer_host && $referer_host === $site_host) {
                return true;
            }
        }
        
        // Allow if no headers (might be server-side tracking)
        // But only if there's a valid visitor_id format
        return empty($origin) && empty($referer);
    }
    
    /**
     * Validate payload size
     * 
     * @param array $data Event data
     * @return bool
     */
    public static function validate_payload_size($data): bool {
        $json = wp_json_encode($data);
        return strlen($json) <= self::MAX_PAYLOAD_SIZE;
    }
    
    /**
     * Full validation for event tracking
     * 
     * @param \WP_REST_Request $request
     * @return array ['valid' => bool, 'error' => string|null, 'code' => int]
     */
    public static function validate_event_request($request): array {
        // 1. Rate limiting
        $rate_check = self::check();
        if (!$rate_check['allowed']) {
            return [
                'valid' => false,
                'error' => 'Too many requests. Please slow down.',
                'code' => 429,
                'retry_after' => $rate_check['retry_after'] ?? 60,
            ];
        }
        
        // 2. Origin validation (relaxed - warn but allow)
        // We don't block because server-side tracking has no origin
        
        // 3. Payload size
        $data = $request->get_param('data') ?: [];
        if (!self::validate_payload_size($data)) {
            return [
                'valid' => false,
                'error' => 'Payload too large',
                'code' => 413,
            ];
        }
        
        // 4. Basic bot detection - check for suspicious patterns
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (self::is_suspicious_request($user_agent, $request)) {
            return [
                'valid' => false,
                'error' => 'Request blocked',
                'code' => 403,
            ];
        }
        
        return ['valid' => true, 'error' => null, 'code' => 200];
    }
    
    /**
     * Check for suspicious bot patterns
     */
    private static function is_suspicious_request(string $user_agent, $request): bool {
        // Empty user agent is suspicious
        if (empty($user_agent)) {
            return true;
        }
        
        // Known bad bot patterns
        $bad_patterns = [
            'curl/', 'wget/', 'python-requests', 'scrapy', 'httpclient',
            'java/', 'libwww', 'lwp-trivial', 'nikto', 'sqlmap',
        ];
        
        $ua_lower = strtolower($user_agent);
        foreach ($bad_patterns as $pattern) {
            if (strpos($ua_lower, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get client IP
     */
    private static function get_client_ip(): string {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
