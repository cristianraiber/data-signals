<?php
/**
 * GeoIP Location from IP Address
 * Supports: Cloudflare headers, GeoLite2 database, API fallback
 */

namespace DataSignals;

class Geo_Locator {
    
    private const CACHE_GROUP = 'ds_geo';
    private const CACHE_TTL = DAY_IN_SECONDS;
    
    /**
     * Get country from IP address
     */
    public static function get_country(string $ip = ''): array {
        $settings = get_settings();
        $ip = $ip ?: self::get_client_ip();
        
        // 1. Try Cloudflare headers first (if enabled)
        if (!empty($settings['geo_use_cloudflare'])) {
            $cf_result = self::get_from_cloudflare();
            if ($cf_result['country_code']) {
                return $cf_result;
            }
        }
        
        // Skip lookup for local IPs
        if (empty($ip) || self::is_local_ip($ip)) {
            return self::default_result();
        }
        
        // Check cache
        $cache_key = 'geo_' . md5($ip);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached;
        }
        
        $transient = get_transient($cache_key);
        if ($transient !== false) {
            wp_cache_set($cache_key, $transient, self::CACHE_GROUP, self::CACHE_TTL);
            return $transient;
        }
        
        // 2. Try GeoLite2 database
        $result = self::get_from_geolite2($ip);
        
        // 3. Fallback to API (dev/testing only)
        if (empty($result['country_code']) && !empty($settings['geo_api_fallback'])) {
            $result = self::get_from_api($ip);
        }
        
        // Cache result
        if ($result['country_code']) {
            wp_cache_set($cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL);
            set_transient($cache_key, $result, self::CACHE_TTL);
        }
        
        return $result;
    }
    
    /**
     * Get country from Cloudflare headers
     */
    public static function get_from_cloudflare(): array {
        $code = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
        
        if (empty($code) || $code === 'XX' || $code === 'T1') {
            return self::default_result();
        }
        
        $code = strtoupper(substr($code, 0, 2));
        
        return [
            'country_code' => $code,
            'country_name' => self::get_country_name($code),
            'source' => 'cloudflare',
        ];
    }
    
    /**
     * Check if Cloudflare is properly configured
     */
    public static function check_cloudflare_status(): array {
        $has_cf_ip = !empty($_SERVER['HTTP_CF_CONNECTING_IP']);
        $has_cf_country = !empty($_SERVER['HTTP_CF_IPCOUNTRY']);
        $has_cf_ray = !empty($_SERVER['HTTP_CF_RAY']);
        
        if (!$has_cf_ray && !$has_cf_ip) {
            return [
                'status' => 'not_detected',
                'message' => __('Cloudflare not detected. Site may not be proxied through Cloudflare.', 'data-signals'),
                'ok' => false,
            ];
        }
        
        if ($has_cf_ray && !$has_cf_country) {
            return [
                'status' => 'no_geo',
                'message' => __('Cloudflare detected but IP Geolocation is disabled. Enable it in Cloudflare Dashboard → Network → IP Geolocation.', 'data-signals'),
                'ok' => false,
            ];
        }
        
        if ($has_cf_country) {
            $country = $_SERVER['HTTP_CF_IPCOUNTRY'];
            return [
                'status' => 'ok',
                'message' => sprintf(__('Cloudflare IP Geolocation active. Your country: %s', 'data-signals'), $country),
                'ok' => true,
                'country' => $country,
            ];
        }
        
        return [
            'status' => 'unknown',
            'message' => __('Cloudflare status unknown.', 'data-signals'),
            'ok' => false,
        ];
    }
    
    /**
     * Get country from GeoLite2 database
     */
    public static function get_from_geolite2(string $ip): array {
        $db_path = self::get_geolite2_path();
        
        if (!$db_path || !file_exists($db_path)) {
            return self::default_result();
        }
        
        try {
            $reader = new GeoLite2_Reader($db_path);
            $record = $reader->country($ip);
            
            if ($record && isset($record['country']['iso_code'])) {
                return [
                    'country_code' => $record['country']['iso_code'],
                    'country_name' => $record['country']['names']['en'] ?? '',
                    'source' => 'geolite2',
                ];
            }
        } catch (\Exception $e) {
            // Log error but don't break
            error_log('Data Signals GeoLite2 error: ' . $e->getMessage());
        }
        
        return self::default_result();
    }
    
    /**
     * Get GeoLite2 database path
     */
    public static function get_geolite2_path(): string {
        $settings = get_settings();
        
        // Custom path from settings
        if (!empty($settings['geolite2_db_path'])) {
            $path = $settings['geolite2_db_path'];
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Default locations
        $paths = [
            WP_CONTENT_DIR . '/uploads/data-signals/GeoLite2-Country.mmdb',
            WP_CONTENT_DIR . '/GeoLite2-Country.mmdb',
            ABSPATH . 'GeoLite2-Country.mmdb',
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return '';
    }
    
    /**
     * Check GeoLite2 database status
     */
    public static function check_geolite2_status(): array {
        $path = self::get_geolite2_path();
        
        if (!$path) {
            return [
                'status' => 'not_found',
                'message' => __('GeoLite2 database not found. Download from MaxMind and upload to wp-content/uploads/data-signals/GeoLite2-Country.mmdb', 'data-signals'),
                'ok' => false,
                'path' => '',
            ];
        }
        
        $size = filesize($path);
        $modified = filemtime($path);
        $age_days = floor((time() - $modified) / DAY_IN_SECONDS);
        
        // Test the database
        try {
            $reader = new GeoLite2_Reader($path);
            $meta = $reader->metadata();
            
            return [
                'status' => 'ok',
                'message' => sprintf(
                    __('GeoLite2 database found (%s, updated %d days ago)', 'data-signals'),
                    size_format($size),
                    $age_days
                ),
                'ok' => true,
                'path' => $path,
                'size' => $size,
                'age_days' => $age_days,
                'build_epoch' => $meta['build_epoch'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => sprintf(__('GeoLite2 database error: %s', 'data-signals'), $e->getMessage()),
                'ok' => false,
                'path' => $path,
            ];
        }
    }
    
    /**
     * Fallback: Get country from API (rate limited, dev only)
     */
    private static function get_from_api(string $ip): array {
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
            'source' => 'api',
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
                
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
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
            'source' => '',
        ];
    }
    
    /**
     * Get country name from code
     */
    public static function get_country_name(string $code): string {
        $countries = self::get_countries();
        return $countries[strtoupper($code)] ?? $code;
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
