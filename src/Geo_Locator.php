<?php
/**
 * GeoIP Location from IP Address
 * Uses WordPress HTTP API with caching
 */

namespace DataSignals;

class Geo_Locator {
    
    private const CACHE_GROUP = 'ds_geo';
    private const CACHE_TTL = DAY_IN_SECONDS;
    
    /**
     * Get country from IP address
     */
    public static function get_country(string $ip = ''): array {
        $ip = $ip ?: self::get_client_ip();
        
        if (empty($ip) || self::is_local_ip($ip)) {
            return self::default_result();
        }
        
        // Check cache first
        $cache_key = 'geo_' . md5($ip);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Check transient (persistent cache)
        $transient = get_transient($cache_key);
        if ($transient !== false) {
            wp_cache_set($cache_key, $transient, self::CACHE_GROUP, self::CACHE_TTL);
            return $transient;
        }
        
        // Lookup via API
        $result = self::lookup_ip($ip);
        
        // Cache result
        wp_cache_set($cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL);
        set_transient($cache_key, $result, self::CACHE_TTL);
        
        return $result;
    }
    
    /**
     * Lookup IP via free API
     * Using ip-api.com (free, 45 req/min limit)
     */
    private static function lookup_ip(string $ip): array {
        $url = "http://ip-api.com/json/{$ip}?fields=status,countryCode,country";
        
        $response = wp_remote_get($url, [
            'timeout' => 2,
            'headers' => ['Accept' => 'application/json'],
        ]);
        
        if (is_wp_error($response)) {
            return self::default_result();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || ($data['status'] ?? '') !== 'success') {
            return self::default_result();
        }
        
        return [
            'country_code' => strtoupper($data['countryCode'] ?? ''),
            'country_name' => $data['country'] ?? '',
        ];
    }
    
    /**
     * Get client IP address
     */
    public static function get_client_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy
            'HTTP_X_REAL_IP',            // nginx
            'REMOTE_ADDR',               // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated list (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * Check if IP is local/private
     */
    private static function is_local_ip(string $ip): bool {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
    
    /**
     * Default result when lookup fails
     */
    private static function default_result(): array {
        return [
            'country_code' => '',
            'country_name' => '',
        ];
    }
    
    /**
     * Get country name from code
     */
    public static function get_country_name(string $code): string {
        $countries = self::get_countries();
        return $countries[$code] ?? $code;
    }
    
    /**
     * Country codes to names mapping
     */
    public static function get_countries(): array {
        return [
            'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AD' => 'Andorra',
            'AO' => 'Angola', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AU' => 'Australia',
            'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas', 'BH' => 'Bahrain',
            'BD' => 'Bangladesh', 'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize',
            'BJ' => 'Benin', 'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BA' => 'Bosnia',
            'BW' => 'Botswana', 'BR' => 'Brazil', 'BN' => 'Brunei', 'BG' => 'Bulgaria',
            'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'CL' => 'Chile',
            'CN' => 'China', 'CO' => 'Colombia', 'CR' => 'Costa Rica', 'HR' => 'Croatia',
            'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czechia', 'DK' => 'Denmark',
            'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador',
            'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FI' => 'Finland', 'FR' => 'France',
            'DE' => 'Germany', 'GH' => 'Ghana', 'GR' => 'Greece', 'GT' => 'Guatemala',
            'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland',
            'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq',
            'IE' => 'Ireland', 'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica',
            'JP' => 'Japan', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya',
            'KR' => 'South Korea', 'KW' => 'Kuwait', 'LV' => 'Latvia', 'LB' => 'Lebanon',
            'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MY' => 'Malaysia', 'MX' => 'Mexico',
            'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'MA' => 'Morocco',
            'MM' => 'Myanmar', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'NZ' => 'New Zealand',
            'NG' => 'Nigeria', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan',
            'PA' => 'Panama', 'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines',
            'PL' => 'Poland', 'PT' => 'Portugal', 'QA' => 'Qatar', 'RO' => 'Romania',
            'RU' => 'Russia', 'SA' => 'Saudi Arabia', 'RS' => 'Serbia', 'SG' => 'Singapore',
            'SK' => 'Slovakia', 'SI' => 'Slovenia', 'ZA' => 'South Africa', 'ES' => 'Spain',
            'LK' => 'Sri Lanka', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'TW' => 'Taiwan',
            'TH' => 'Thailand', 'TR' => 'Turkey', 'UA' => 'Ukraine', 'AE' => 'UAE',
            'GB' => 'United Kingdom', 'US' => 'United States', 'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan', 'VE' => 'Venezuela', 'VN' => 'Vietnam', 'ZW' => 'Zimbabwe',
        ];
    }
}
