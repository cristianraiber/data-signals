<?php
/**
 * UTM Campaign Parameter Tracking
 */

namespace DataSignals;

class Campaign_Tracker {
    
    private const UTM_PARAMS = [
        'utm_source',
        'utm_medium', 
        'utm_campaign',
        'utm_content',
        'utm_term',
    ];
    
    /**
     * Extract UTM parameters from URL or request
     */
    public static function extract(string $url = ''): array {
        $params = [];
        
        // Parse from URL if provided
        if (!empty($url)) {
            $query = parse_url($url, PHP_URL_QUERY);
            if ($query) {
                parse_str($query, $params);
            }
        }
        
        // Merge with GET params (for current request)
        $params = array_merge($_GET, $params);
        
        // Extract only UTM params
        $utm = [];
        foreach (self::UTM_PARAMS as $param) {
            $value = $params[$param] ?? '';
            $utm[$param] = self::sanitize($value);
        }
        
        return $utm;
    }
    
    /**
     * Check if request has any UTM parameters
     */
    public static function has_utm(array $params = []): bool {
        if (empty($params)) {
            $params = self::extract();
        }
        
        foreach ($params as $value) {
            if (!empty($value)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Sanitize UTM parameter value
     */
    private static function sanitize(string $value): string {
        $value = sanitize_text_field($value);
        $value = substr($value, 0, 100); // Max length
        return $value;
    }
    
    /**
     * Get UTM from session/cookie (for multi-page attribution)
     */
    public static function get_from_session(): array {
        // First check current request
        $current = self::extract();
        
        if (self::has_utm($current)) {
            // Store in session for attribution
            self::store_in_session($current);
            return $current;
        }
        
        // Fall back to stored session
        return self::retrieve_from_session();
    }
    
    /**
     * Store UTM in session cookie
     */
    private static function store_in_session(array $utm): void {
        if (headers_sent()) {
            return;
        }
        
        $encoded = base64_encode(json_encode($utm));
        setcookie('ds_utm', $encoded, [
            'expires'  => time() + (30 * DAY_IN_SECONDS), // 30 days
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    
    /**
     * Retrieve UTM from session cookie
     */
    private static function retrieve_from_session(): array {
        if (empty($_COOKIE['ds_utm'])) {
            return self::empty_utm();
        }
        
        $decoded = base64_decode($_COOKIE['ds_utm']);
        $utm = json_decode($decoded, true);
        
        if (!is_array($utm)) {
            return self::empty_utm();
        }
        
        // Ensure all keys exist
        return array_merge(self::empty_utm(), $utm);
    }
    
    /**
     * Return empty UTM array
     */
    public static function empty_utm(): array {
        return [
            'utm_source'   => '',
            'utm_medium'   => '',
            'utm_campaign' => '',
            'utm_content'  => '',
            'utm_term'     => '',
        ];
    }
    
    /**
     * Get common source labels
     */
    public static function get_source_label(string $source): string {
        $labels = [
            'google'    => 'Google',
            'facebook'  => 'Facebook',
            'twitter'   => 'Twitter/X',
            'linkedin'  => 'LinkedIn',
            'instagram' => 'Instagram',
            'youtube'   => 'YouTube',
            'tiktok'    => 'TikTok',
            'email'     => 'Email',
            'newsletter'=> 'Newsletter',
        ];
        
        $source_lower = strtolower($source);
        return $labels[$source_lower] ?? ucfirst($source);
    }
    
    /**
     * Get medium labels
     */
    public static function get_medium_label(string $medium): string {
        $labels = [
            'cpc'     => 'Paid Search',
            'ppc'     => 'Paid Search',
            'organic' => 'Organic',
            'social'  => 'Social',
            'email'   => 'Email',
            'referral'=> 'Referral',
            'display' => 'Display Ads',
            'affiliate'=> 'Affiliate',
        ];
        
        $medium_lower = strtolower($medium);
        return $labels[$medium_lower] ?? ucfirst($medium);
    }
}
