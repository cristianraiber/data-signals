<?php
/**
 * Rate Limiter
 *
 * Protects tracking endpoint from abuse with IP-based rate limiting.
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
 * Implements token bucket algorithm for rate limiting
 */
class Rate_Limiter {

	/**
	 * Maximum requests per minute per IP
	 *
	 * @var int
	 */
	private const MAX_REQUESTS_PER_MINUTE = 1000;

	/**
	 * Redis connection
	 *
	 * @var \Redis|null
	 */
	private $redis;

	/**
	 * Fallback to transients if Redis unavailable
	 *
	 * @var bool
	 */
	private $use_transients = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_redis();
	}

	/**
	 * Initialize Redis connection
	 */
	private function init_redis(): void {
		if ( ! class_exists( 'Redis' ) ) {
			$this->use_transients = true;
			return;
		}

		try {
			$this->redis = new \Redis();
			$connected   = $this->redis->connect( '127.0.0.1', 6379 );

			if ( ! $connected ) {
				$this->use_transients = true;
				$this->redis          = null;
			}
		} catch ( \Exception $e ) {
			$this->use_transients = true;
			$this->redis          = null;
			error_log( 'Rate Limiter: Redis connection failed, using transients: ' . $e->getMessage() );
		}
	}

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
	 * Check rate limit using token bucket algorithm
	 *
	 * @param string $ip_hash Hashed IP address.
	 * @param int    $max_requests Maximum requests per minute.
	 * @return bool True if request is allowed.
	 */
	private function check_rate_limit( string $ip_hash, int $max_requests ): bool {
		$key = 'ds_ratelimit_' . $ip_hash;

		if ( $this->use_transients ) {
			return $this->check_rate_limit_transients( $key, $max_requests );
		}

		return $this->check_rate_limit_redis( $key, $max_requests );
	}

	/**
	 * Check rate limit using Redis
	 *
	 * @param string $key Cache key.
	 * @param int    $max_requests Maximum requests per minute.
	 * @return bool True if request is allowed.
	 */
	private function check_rate_limit_redis( string $key, int $max_requests ): bool {
		if ( ! $this->redis ) {
			return true; // Fail open if Redis is unavailable
		}

		try {
			$current = $this->redis->get( $key );

			if ( $current === false ) {
				// First request - set counter
				$this->redis->setex( $key, 60, 1 );
				return true;
			}

			if ( (int) $current >= $max_requests ) {
				// Rate limit exceeded
				return false;
			}

			// Increment counter
			$this->redis->incr( $key );
			return true;

		} catch ( \Exception $e ) {
			error_log( 'Rate Limiter: Redis error: ' . $e->getMessage() );
			return true; // Fail open on errors
		}
	}

	/**
	 * Check rate limit using WordPress transients (fallback)
	 *
	 * @param string $key Cache key.
	 * @param int    $max_requests Maximum requests per minute.
	 * @return bool True if request is allowed.
	 */
	private function check_rate_limit_transients( string $key, int $max_requests ): bool {
		$current = get_transient( $key );

		if ( $current === false ) {
			// First request
			set_transient( $key, 1, 60 );
			return true;
		}

		if ( (int) $current >= $max_requests ) {
			// Rate limit exceeded
			return false;
		}

		// Increment counter
		set_transient( $key, (int) $current + 1, 60 );
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
		$key     = 'ds_ratelimit_' . $ip_hash;

		if ( $this->use_transients ) {
			$current = (int) get_transient( $key );
		} else {
			try {
				$current = (int) $this->redis->get( $key );
			} catch ( \Exception $e ) {
				$current = 0;
			}
		}

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
		$key     = 'ds_ratelimit_' . $ip_hash;

		if ( $this->use_transients ) {
			return delete_transient( $key );
		}

		try {
			return (bool) $this->redis->del( $key );
		} catch ( \Exception $e ) {
			error_log( 'Rate Limiter: Failed to reset limit: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get rate limit stats
	 *
	 * @return array Stats array.
	 */
	public function get_stats(): array {
		return array(
			'max_requests_per_minute' => self::MAX_REQUESTS_PER_MINUTE,
			'storage_backend'         => $this->use_transients ? 'transients' : 'redis',
			'redis_available'         => ! $this->use_transients,
		);
	}
}
