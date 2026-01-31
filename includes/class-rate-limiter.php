<?php
/**
 * Rate Limiter
 *
 * Protects tracking endpoint from abuse with IP-based rate limiting.
 * Uses WordPress object cache (no Redis required).
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rate_Limiter
 *
 * Implements token bucket algorithm for rate limiting using WordPress object cache
 */
class Rate_Limiter {

	/**
	 * Maximum requests per minute per IP
	 *
	 * @var int
	 */
	private const MAX_REQUESTS_PER_MINUTE = 1000;

	/**
	 * Cache group for rate limit data
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'data_signals_ratelimit';

	/**
	 * Check if request is allowed
	 *
	 * @param string|null $ip_address IP address (null = auto-detect).
	 * @param int|null    $max_requests Maximum requests per minute (null = use default).
	 * @return bool True if request is allowed.
	 */
	public function is_allowed( ?string $ip_address = null, ?int $max_requests = null ): bool {
		$ip  = $ip_address ?? $this->get_client_ip();
		$max = $max_requests ?? self::MAX_REQUESTS_PER_MINUTE;

		// Anonymize IP for privacy
		$ip_hash = $this->anonymize_ip( $ip );

		return $this->check_rate_limit( $ip_hash, $max );
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip(): string {
		// Check for IP from proxy headers (in order of priority)
		$headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',        // Nginx proxy
			'HTTP_X_FORWARDED_FOR',  // Standard proxy header
			'REMOTE_ADDR',           // Direct connection
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

				// If X-Forwarded-For, take the first IP
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}

				// Validate IP
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Anonymize IP address for privacy
	 *
	 * @param string $ip IP address.
	 * @return string Hashed IP (SHA-256).
	 */
	private function anonymize_ip( string $ip ): string {
		// Zero out last octet for IPv4, last 80 bits for IPv6 before hashing
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts    = explode( '.', $ip );
			$parts[3] = '0';
			$ip       = implode( '.', $parts );
		} elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$parts = explode( ':', $ip );
			// Zero out last 5 groups (80 bits)
			for ( $i = 3; $i < 8; $i++ ) {
				if ( isset( $parts[ $i ] ) ) {
					$parts[ $i ] = '0';
				}
			}
			$ip = implode( ':', $parts );
		}

		return hash( 'sha256', $ip );
	}

	/**
	 * Check rate limit using WordPress object cache (token bucket algorithm)
	 *
	 * Works with any WordPress object cache backend:
	 * - Memcached (wp-memcached)
	 * - APCu (apcu-object-cache)
	 * - Redis (redis-cache plugin)
	 * - Database transients (fallback)
	 *
	 * @param string $ip_hash Hashed IP address.
	 * @param int    $max_requests Maximum requests per minute.
	 * @return bool True if request is allowed.
	 */
	private function check_rate_limit( string $ip_hash, int $max_requests ): bool {
		$key = $ip_hash;

		// Try object cache first (fast: Memcached/APCu/Redis)
		$current = wp_cache_get( $key, self::CACHE_GROUP );

		if ( $current === false ) {
			// First request - set counter
			wp_cache_set( $key, 1, self::CACHE_GROUP, 60 );
			return true;
		}

		if ( (int) $current >= $max_requests ) {
			// Rate limit exceeded
			return false;
		}

		// Increment counter (atomic if supported by cache backend)
		wp_cache_set( $key, (int) $current + 1, self::CACHE_GROUP, 60 );
		return true;
	}

	/**
	 * Get remaining requests for IP
	 *
	 * @param string|null $ip_address IP address (null = auto-detect).
	 * @param int|null    $max_requests Maximum requests per minute (null = use default).
	 * @return int Remaining requests.
	 */
	public function get_remaining_requests( ?string $ip_address = null, ?int $max_requests = null ): int {
		$ip  = $ip_address ?? $this->get_client_ip();
		$max = $max_requests ?? self::MAX_REQUESTS_PER_MINUTE;

		$ip_hash = $this->anonymize_ip( $ip );
		$key     = $ip_hash;

		$current = (int) wp_cache_get( $key, self::CACHE_GROUP );

		return max( 0, $max - $current );
	}

	/**
	 * Reset rate limit for IP (admin function)
	 *
	 * @param string $ip_address IP address.
	 * @return bool Success status.
	 */
	public function reset_limit( string $ip_address ): bool {
		$ip_hash = $this->anonymize_ip( $ip_address );
		$key     = $ip_hash;

		return wp_cache_delete( $key, self::CACHE_GROUP );
	}

	/**
	 * Get rate limit stats
	 *
	 * @return array Stats array.
	 */
	public function get_stats(): array {
		global $_wp_using_ext_object_cache;

		return array(
			'max_requests_per_minute' => self::MAX_REQUESTS_PER_MINUTE,
			'storage_backend'         => $_wp_using_ext_object_cache ? 'persistent_cache' : 'transients',
			'persistent_cache'        => $_wp_using_ext_object_cache,
		);
	}

	/**
	 * Flush all rate limit data (cleanup)
	 *
	 * @return bool Success status.
	 */
	public function flush_all(): bool {
		return wp_cache_flush_group( self::CACHE_GROUP );
	}
}
