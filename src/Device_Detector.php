<?php
/**
 * Device Detection from User-Agent
 * Lightweight parser without external dependencies
 */

namespace DataSignals;

class Device_Detector {
    
    private string $ua;
    
    public function __construct(string $user_agent = '') {
        $this->ua = $user_agent ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');
    }
    
    /**
     * Get all device info
     */
    public function detect(): array {
        return [
            'device_type' => $this->get_device_type(),
            'browser'     => $this->get_browser(),
            'os'          => $this->get_os(),
        ];
    }
    
    /**
     * Detect device type: desktop, mobile, tablet
     */
    public function get_device_type(): string {
        $ua = strtolower($this->ua);
        
        // Tablets (check before mobile as tablets also match mobile patterns)
        if (preg_match('/ipad|tablet|playbook|silk|kindle|(?!.*mobile)android/i', $this->ua)) {
            return 'tablet';
        }
        
        // Mobile
        if (preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|opera mobi|iemobile|wpdesktop|windows phone/i', $this->ua)) {
            return 'mobile';
        }
        
        // Default to desktop
        return 'desktop';
    }
    
    /**
     * Detect browser name
     */
    public function get_browser(): string {
        $browsers = [
            'Edge'      => '/edg(?:e|a|ios)?\/[\d.]+/i',
            'Opera'     => '/(?:opera|opr)\/[\d.]+/i',
            'Chrome'    => '/chrome\/[\d.]+/i',
            'Safari'    => '/version\/[\d.]+ .*safari/i',
            'Firefox'   => '/firefox\/[\d.]+/i',
            'IE'        => '/(?:msie |trident.*rv:)[\d.]+/i',
            'Samsung'   => '/samsungbrowser\/[\d.]+/i',
            'UC'        => '/ucbrowser\/[\d.]+/i',
            'Brave'     => '/brave\/[\d.]+/i',
            'Vivaldi'   => '/vivaldi\/[\d.]+/i',
            'Arc'       => '/arc\/[\d.]+/i',
        ];
        
        foreach ($browsers as $name => $pattern) {
            if (preg_match($pattern, $this->ua)) {
                return $name;
            }
        }
        
        // Check for Safari without version (fallback)
        if (stripos($this->ua, 'safari') !== false && stripos($this->ua, 'chrome') === false) {
            return 'Safari';
        }
        
        return 'Other';
    }
    
    /**
     * Detect operating system
     */
    public function get_os(): string {
        $os_patterns = [
            'Windows 11'   => '/windows nt 10.*build.*(2[2-9]\d{3}|[3-9]\d{4})/i',
            'Windows 10'   => '/windows nt 10/i',
            'Windows 8.1'  => '/windows nt 6\.3/i',
            'Windows 8'    => '/windows nt 6\.2/i',
            'Windows 7'    => '/windows nt 6\.1/i',
            'Windows'      => '/windows/i',
            'macOS'        => '/mac os x|macintosh/i',
            'iOS'          => '/iphone|ipad|ipod/i',
            'Android'      => '/android/i',
            'Linux'        => '/linux/i',
            'Chrome OS'    => '/cros/i',
        ];
        
        foreach ($os_patterns as $name => $pattern) {
            if (preg_match($pattern, $this->ua)) {
                return $name;
            }
        }
        
        return 'Other';
    }
    
    /**
     * Check if the UA is a bot
     */
    public function is_bot(): bool {
        return (bool) preg_match('/bot|crawl|spider|seo|lighthouse|facebookexternalhit|preview|slurp|yahoo|bing|baidu|yandex|duckduck/i', $this->ua);
    }
}
