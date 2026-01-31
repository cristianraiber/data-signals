<?php
/**
 * Easy Digital Downloads Integration
 *
 * Tracks EDD purchases and attributes revenue to traffic sources.
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Easy Digital Downloads Integration Class
 */
class EDD {
	/**
	 * Initialize the integration
	 */
	public function __construct() {
		// Only initialize if EDD is active
		if ( ! function_exists( 'EDD' ) ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks(): void {
		// Track completed purchases
		add_action( 'edd_complete_purchase', array( $this, 'track_purchase' ), 10, 1 );
		
		// Track cart creation (for abandonment)
		add_action( 'edd_post_add_to_cart', array( $this, 'track_cart_creation' ), 10, 2 );
		
		// Track checkout started
		add_action( 'edd_checkout_before_gateway', array( $this, 'track_checkout_started' ) );
		
		// Track download views
		add_action( 'template_redirect', array( $this, 'track_download_view' ) );
	}

	/**
	 * Track completed purchase
	 *
	 * @param int $payment_id Payment ID.
	 */
	public function track_purchase( int $payment_id ): void {
		global $wpdb;

		$payment = edd_get_payment( $payment_id );
		if ( ! $payment ) {
			return;
		}

		$session_id = $this->get_session_id();
		if ( ! $session_id ) {
			return;
		}

		// Get payment data
		$payment_total = edd_get_payment_amount( $payment_id );
		$downloads     = edd_get_payment_meta_downloads( $payment_id );
		$products      = array();

		foreach ( $downloads as $download ) {
			$download_id = isset( $download['id'] ) ? $download['id'] : 0;
			$price_id    = isset( $download['options']['price_id'] ) ? $download['options']['price_id'] : null;
			
			$products[] = array(
				'download_id' => $download_id,
				'price_id'    => $price_id,
				'name'        => get_the_title( $download_id ),
				'quantity'    => isset( $download['quantity'] ) ? $download['quantity'] : 1,
				'item_price'  => isset( $download['item_price'] ) ? $download['item_price'] : 0,
				'subtotal'    => isset( $download['subtotal'] ) ? $download['subtotal'] : 0,
			);
		}

		// Store purchase event
		$wpdb->insert(
			$wpdb->prefix . 'ds_events',
			array(
				'session_id'  => $session_id,
				'event_type'  => 'edd_purchase',
				'event_value' => $payment_total,
				'page_id'     => get_the_ID(),
				'product_id'  => null,
				'metadata'    => wp_json_encode( array(
					'payment_id'     => $payment_id,
					'downloads'      => $products,
					'customer_email' => edd_get_payment_user_email( $payment_id ),
					'payment_method' => edd_get_payment_gateway( $payment_id ),
					'currency'       => edd_get_payment_currency_code( $payment_id ),
				) ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%f', '%d', '%d', '%s', '%s' )
		);

		// Update session revenue
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}ds_sessions 
			SET total_revenue = total_revenue + %f,
			    last_seen = %s
			WHERE session_id = %s",
			$payment_total,
			current_time( 'mysql' ),
			$session_id
		) );

		// Attribute revenue
		$this->attribute_revenue( $payment_id, $session_id, $payment_total );

		// Track individual downloads
		foreach ( $products as $product ) {
			$wpdb->insert(
				$wpdb->prefix . 'ds_events',
				array(
					'session_id'  => $session_id,
					'event_type'  => 'download_purchase',
					'event_value' => $product['subtotal'],
					'page_id'     => null,
					'product_id'  => $product['download_id'],
					'metadata'    => wp_json_encode( array(
						'payment_id' => $payment_id,
						'price_id'   => $product['price_id'],
						'quantity'   => $product['quantity'],
					) ),
					'created_at'  => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%f', '%d', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Track cart creation (first add to cart)
	 *
	 * @param int   $download_id Download ID.
	 * @param array $options Options array.
	 */
	public function track_cart_creation( int $download_id, array $options ): void {
		global $wpdb;

		$session_id = $this->get_session_id();
		if ( ! $session_id ) {
			return;
		}

		// Check if this is the first item in cart
		$cart_contents = edd_get_cart_contents();
		if ( count( $cart_contents ) === 1 ) {
			// First item - track cart creation
			$wpdb->insert(
				$wpdb->prefix . 'ds_events',
				array(
					'session_id'  => $session_id,
					'event_type'  => 'edd_cart_created',
					'event_value' => 0,
					'page_id'     => get_the_ID(),
					'product_id'  => $download_id,
					'metadata'    => wp_json_encode( array(
						'first_download_id' => $download_id,
						'options'           => $options,
					) ),
					'created_at'  => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%f', '%d', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Track checkout started
	 */
	public function track_checkout_started(): void {
		global $wpdb;

		$session_id = $this->get_session_id();
		if ( ! $session_id ) {
			return;
		}

		$cart_total = edd_get_cart_total();

		$wpdb->insert(
			$wpdb->prefix . 'ds_events',
			array(
				'session_id'  => $session_id,
				'event_type'  => 'edd_checkout_started',
				'event_value' => $cart_total,
				'page_id'     => get_the_ID(),
				'product_id'  => null,
				'metadata'    => wp_json_encode( array(
					'cart_total' => $cart_total,
					'item_count' => edd_get_cart_quantity(),
				) ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%f', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Track download product view
	 */
	public function track_download_view(): void {
		if ( ! is_singular( 'download' ) ) {
			return;
		}

		global $wpdb;

		$session_id = $this->get_session_id();
		if ( ! $session_id ) {
			return;
		}

		$download_id = get_the_ID();

		$wpdb->insert(
			$wpdb->prefix . 'ds_events',
			array(
				'session_id'  => $session_id,
				'event_type'  => 'download_view',
				'event_value' => 0,
				'page_id'     => $download_id,
				'product_id'  => $download_id,
				'metadata'    => wp_json_encode( array(
					'download_id' => $download_id,
					'price'       => edd_get_download_price( $download_id ),
				) ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%f', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Attribute revenue to traffic sources
	 *
	 * @param int    $payment_id Payment ID.
	 * @param string $session_id Session ID.
	 * @param float  $payment_total Payment total.
	 */
	private function attribute_revenue( int $payment_id, string $session_id, float $payment_total ): void {
		global $wpdb;

		// Get all touchpoints for this session
		$touchpoints = $wpdb->get_results( $wpdb->prepare(
			"SELECT page_id, url, created_at 
			FROM {$wpdb->prefix}ds_pageviews 
			WHERE session_id = %s 
			ORDER BY created_at ASC",
			$session_id
		) );

		if ( empty( $touchpoints ) ) {
			return;
		}

		$total_touchpoints = count( $touchpoints );
		$now               = current_time( 'mysql' );

		// First-click attribution (100% to first touchpoint)
		$wpdb->insert(
			$wpdb->prefix . 'ds_revenue_attribution',
			array(
				'order_id'         => $payment_id,
				'session_id'       => $session_id,
				'page_id'          => $touchpoints[0]->page_id,
				'attribution_type' => 'first_click',
				'revenue_share'    => $payment_total,
				'created_at'       => $now,
			),
			array( '%d', '%s', '%d', '%s', '%f', '%s' )
		);

		// Last-click attribution (100% to last touchpoint)
		$wpdb->insert(
			$wpdb->prefix . 'ds_revenue_attribution',
			array(
				'order_id'         => $payment_id,
				'session_id'       => $session_id,
				'page_id'          => $touchpoints[ $total_touchpoints - 1 ]->page_id,
				'attribution_type' => 'last_click',
				'revenue_share'    => $payment_total,
				'created_at'       => $now,
			),
			array( '%d', '%s', '%d', '%s', '%f', '%s' )
		);

		// Linear attribution (equal credit to all touchpoints)
		$linear_share = $payment_total / $total_touchpoints;
		foreach ( $touchpoints as $touchpoint ) {
			$wpdb->insert(
				$wpdb->prefix . 'ds_revenue_attribution',
				array(
					'order_id'         => $payment_id,
					'session_id'       => $session_id,
					'page_id'          => $touchpoint->page_id,
					'attribution_type' => 'linear',
					'revenue_share'    => $linear_share,
					'created_at'       => $now,
				),
				array( '%d', '%s', '%d', '%s', '%f', '%s' )
			);
		}

		// Time-decay attribution (more credit to recent touchpoints)
		$this->time_decay_attribution( $payment_id, $session_id, $touchpoints, $payment_total, $now );
	}

	/**
	 * Calculate time-decay attribution
	 *
	 * @param int    $payment_id Payment ID.
	 * @param string $session_id Session ID.
	 * @param array  $touchpoints Array of touchpoint objects.
	 * @param float  $payment_total Payment total.
	 * @param string $now Current timestamp.
	 */
	private function time_decay_attribution( int $payment_id, string $session_id, array $touchpoints, float $payment_total, string $now ): void {
		global $wpdb;

		// Calculate weights using exponential decay (half-life = 7 days)
		$half_life_days = 7;
		$weights        = array();
		$total_weight   = 0;
		$current_time   = strtotime( $now );

		foreach ( $touchpoints as $touchpoint ) {
			$touchpoint_time = strtotime( $touchpoint->created_at );
			$days_ago        = ( $current_time - $touchpoint_time ) / DAY_IN_SECONDS;
			
			// Exponential decay: weight = 2^(-days_ago / half_life)
			$weight = pow( 2, -$days_ago / $half_life_days );
			
			$weights[]     = $weight;
			$total_weight += $weight;
		}

		// Normalize weights and attribute revenue
		foreach ( $touchpoints as $index => $touchpoint ) {
			$normalized_weight = $weights[ $index ] / $total_weight;
			$revenue_share     = $payment_total * $normalized_weight;

			$wpdb->insert(
				$wpdb->prefix . 'ds_revenue_attribution',
				array(
					'order_id'         => $payment_id,
					'session_id'       => $session_id,
					'page_id'          => $touchpoint->page_id,
					'attribution_type' => 'time_decay',
					'revenue_share'    => $revenue_share,
					'created_at'       => $now,
				),
				array( '%d', '%s', '%d', '%s', '%f', '%s' )
			);
		}
	}

	/**
	 * Get current session ID
	 *
	 * @return string|null Session ID or null if not found.
	 */
	private function get_session_id(): ?string {
		// Try to get from cookie
		if ( isset( $_COOKIE['ds_session'] ) ) {
			return sanitize_text_field( $_COOKIE['ds_session'] );
		}

		// Try to get from EDD session
		if ( function_exists( 'EDD' ) && EDD()->session ) {
			$customer_id = EDD()->session->get( 'customer_id' );
			if ( $customer_id ) {
				return hash( 'sha256', $customer_id );
			}
		}

		return null;
	}
}
