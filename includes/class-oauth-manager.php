<?php
/**
 * OAuth Manager
 *
 * Handles OAuth 2.0 flows for external integrations (Google Search Console, etc.)
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OAuth_Manager
 *
 * Manages OAuth 2.0 token storage, encryption, and refresh logic
 */
class OAuth_Manager {

	/**
	 * Encryption key for token storage
	 *
	 * @var string
	 */
	private $encryption_key;

	/**
	 * Option prefix for token storage
	 *
	 * @var string
	 */
	private const OPTION_PREFIX = 'ds_oauth_';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->encryption_key = $this->get_encryption_key();
	}

	/**
	 * Get or generate encryption key
	 *
	 * @return string
	 */
	private function get_encryption_key(): string {
		$key = get_option( 'ds_oauth_encryption_key' );

		if ( ! $key ) {
			$key = bin2hex( random_bytes( 32 ) );
			update_option( 'ds_oauth_encryption_key', $key, false );
		}

		return $key;
	}

	/**
	 * Encrypt token data
	 *
	 * @param mixed $data Data to encrypt.
	 * @return string Encrypted data.
	 */
	private function encrypt( $data ): string {
		$json = wp_json_encode( $data );
		$iv   = random_bytes( 16 );

		$encrypted = openssl_encrypt(
			$json,
			'AES-256-CBC',
			hex2bin( $this->encryption_key ),
			OPENSSL_RAW_DATA,
			$iv
		);

		// Combine IV and encrypted data
		$result = base64_encode( $iv . $encrypted );

		return $result;
	}

	/**
	 * Decrypt token data
	 *
	 * @param string $encrypted_data Encrypted data.
	 * @return mixed Decrypted data.
	 */
	private function decrypt( string $encrypted_data ) {
		$data = base64_decode( $encrypted_data );
		$iv   = substr( $data, 0, 16 );
		$encrypted = substr( $data, 16 );

		$decrypted = openssl_decrypt(
			$encrypted,
			'AES-256-CBC',
			hex2bin( $this->encryption_key ),
			OPENSSL_RAW_DATA,
			$iv
		);

		return json_decode( $decrypted, true );
	}

	/**
	 * Store OAuth tokens
	 *
	 * @param string $provider Provider name (e.g., 'google_search_console').
	 * @param array  $tokens Token data (access_token, refresh_token, expires_in, etc.).
	 * @return bool Success status.
	 */
	public function store_tokens( string $provider, array $tokens ): bool {
		$encrypted = $this->encrypt( $tokens );
		return update_option( self::OPTION_PREFIX . $provider, $encrypted, false );
	}

	/**
	 * Get OAuth tokens
	 *
	 * @param string $provider Provider name.
	 * @return array|null Token data or null if not found.
	 */
	public function get_tokens( string $provider ): ?array {
		$encrypted = get_option( self::OPTION_PREFIX . $provider );

		if ( ! $encrypted ) {
			return null;
		}

		return $this->decrypt( $encrypted );
	}

	/**
	 * Delete OAuth tokens
	 *
	 * @param string $provider Provider name.
	 * @return bool Success status.
	 */
	public function delete_tokens( string $provider ): bool {
		return delete_option( self::OPTION_PREFIX . $provider );
	}

	/**
	 * Check if tokens are expired
	 *
	 * @param array $tokens Token data.
	 * @return bool True if expired or about to expire (within 5 minutes).
	 */
	public function is_token_expired( array $tokens ): bool {
		if ( ! isset( $tokens['expires_at'] ) ) {
			return true;
		}

		// Consider expired if less than 5 minutes remaining
		return time() >= ( $tokens['expires_at'] - 300 );
	}

	/**
	 * Refresh OAuth token
	 *
	 * @param string   $provider Provider name.
	 * @param string   $refresh_token Refresh token.
	 * @param callable $refresh_callback Callback to refresh token (should return new tokens array).
	 * @return array|null New tokens or null on failure.
	 * @throws Exception If refresh fails.
	 */
	public function refresh_token( string $provider, string $refresh_token, callable $refresh_callback ): ?array {
		try {
			$new_tokens = call_user_func( $refresh_callback, $refresh_token );

			if ( $new_tokens && isset( $new_tokens['access_token'] ) ) {
				// Calculate expires_at timestamp
				if ( isset( $new_tokens['expires_in'] ) ) {
					$new_tokens['expires_at'] = time() + (int) $new_tokens['expires_in'];
				}

				// Preserve refresh token if not provided in response
				if ( ! isset( $new_tokens['refresh_token'] ) ) {
					$new_tokens['refresh_token'] = $refresh_token;
				}

				$this->store_tokens( $provider, $new_tokens );
				return $new_tokens;
			}

			return null;
		} catch ( Exception $e ) {
			error_log( 'OAuth token refresh failed for ' . $provider . ': ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Get valid access token (refresh if expired)
	 *
	 * @param string   $provider Provider name.
	 * @param callable $refresh_callback Callback to refresh token.
	 * @return string|null Valid access token or null.
	 */
	public function get_valid_access_token( string $provider, callable $refresh_callback ): ?string {
		$tokens = $this->get_tokens( $provider );

		if ( ! $tokens ) {
			return null;
		}

		// Refresh if expired
		if ( $this->is_token_expired( $tokens ) ) {
			if ( ! isset( $tokens['refresh_token'] ) ) {
				return null;
			}

			try {
				$tokens = $this->refresh_token( $provider, $tokens['refresh_token'], $refresh_callback );
			} catch ( Exception $e ) {
				return null;
			}
		}

		return $tokens['access_token'] ?? null;
	}

	/**
	 * Check if provider is authorized
	 *
	 * @param string $provider Provider name.
	 * @return bool True if tokens exist.
	 */
	public function is_authorized( string $provider ): bool {
		return $this->get_tokens( $provider ) !== null;
	}
}
