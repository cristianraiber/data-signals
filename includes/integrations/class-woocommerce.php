<?php
/**
 * WooCommerce Integration
 *
 * Tracks WooCommerce orders and attributes revenue to traffic sources.
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Integration Class
 */
class WooCommerce {
	/**
	 * Initialize the integration
	 */
	public function __construct() {
		// Only initialize if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks(): void {
		// Track completed orders
		add_action( 'woocommerce_payment_complete', array( $this, 'track_order' ), 10, 1 );
		
		// Track cart creation (for abandonment)
		add_action( 'woocommerce_add_to_cart', array( $this, 'track_cart_creation' ), 10, 6 );
		
		// Track cart updates
		add_action( 'woocommerce_cart_updated', array( $this, 'track_cart_update' ) );
		
		// Track checkout started
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'track_checkout_started' ), 10, 1 );
	}

	/**
	 * Track completed order
	 *
	 * @param int $order_id Order ID.
	 */
	public function track_order( int $order_id ): void {
		global $wpdb;

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$session_id = $this->get_session_id();
		if ( ! $session_id ) {
			return;
		}

		// Get order data
		$order_total = $order->get_total();
		$items       = $order->get_items();
		$products    = array();

		foreach ( $items as $item ) {
			$product = $item->get_product();
			if ( $product ) {
				$products[] = array(
					'product_id' => $product->get_id(),
					'name'       => $product->get_name(),
					'quantity'   => $item->get_quantity(),
					'subtotal'   => $item->get_subtotal(),
					'total'      => $item->get_total(),
				);
			}
		}

		// Store purchase event
		$wpdb->insert(
			$wpdb->prefix . 'ds_events',
			array(
				'session_id'  => $session_id,
				'event_type'  => 'purchase',
				'event_value' => $order_total,
				'page_id'     => get_the_ID(),
				'product_id'  => null,
				'metadata'    => wp_json_encode( array(
					'order_id'       => $order_id,
					'products'       => $products,
					'customer_email' => $order->get_billing_email(),
					'payment_method' => $order->get_payment_method(),
					'currency'       => $order->get_currency(),
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
			$order_total,
			current_time( 'mysql' ),
			$session_id
		) );

		// Attribute revenue
		$this->attribute_revenue( $order_id, $session_id, $order_total );

		// Track individual products
		foreach ( $products as $product ) {
			$wpdb->insert(
				$wpdb->prefix . 'ds_events',
				array(
					'session_id'  => $session_id,
					'event_type'  => 'product_purchase',
					'event_value' => $product['total'],
					'page_id'     => null,
					'product_id'  => $product['product_id'],
					'metadata'    => wp_json_encode( array(
						'order_id' => $order_id,
						'quantity' => $product['quantity'],
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
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id Product ID.
	 * @param int    $quantity Quantity.
	 * @param int    $variation_id Variation ID.
	 * @param array  $variation Variation data.
	 * @param array  $cart_item_data Cart item data.
	 */
	public function track_cart_creation( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ): void {
		global $wpdb;

		$session_id = $this->get_session_id();
		if ( ! $session_id ) {
			return;
		}

		// Check if this is the first item in cart
		$cart = WC()->cart;
		if ( $cart && $cart->get_cart_contents_count() === $quantity ) {
			// First item - track cart creation
			$wpdb->insert(
				$wpdb->prefix . 'ds_events',
				array(
					'session_id'  => $session_id,
					'event_type'  => 'cart_created',
					'event_value' => 0,
					'page_id'     => get_the_ID(),
					'product_id'  => $product_id,
					'metadata'    => wp_json_encode( array(
						'first_product_id' => $product_id,
						'quantity'         => $quantity,
					) ),
					'created_at'  => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%f', '%d', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Track cart update
	 */
	public function track_cart_update(): void {
		global $wpdb;

		$session_id = $this->get_session_id();
		if ( ! $session_id ) {
			return;
		}

		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}

		$cart_total = $cart->get_cart_contents_total();
		$item_count = $cart->get_cart_contents_count();

		$wpdb->insert(
			$wpdb->prefix . 'ds_events',
			array(
				'session_id'  => $session_id,
				'event_type'  => 'cart_updated',
				'event_value' => $cart_total,
				'page_id'     => get_the_ID(),
				'product_id'  => null,
				'metadata'    => wp_json_encode( array(
					'item_count' => $item_count,
					'cart_total' => $cart_total,
				) ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%f', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Track checkout started
	 *
	 * @param int $order_id Order ID.
	 */
	public function track_checkout_started( int $order_id ): void {
		global $wpdb;

		$session_id = $this->get_session_id();
		if ( ! $session_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$wpdb->insert(
			$wpdb->prefix . 'ds_events',
			array(
				'session_id'  => $session_id,
				'event_type'  => 'checkout_started',
				'event_value' => $order->get_total(),
				'page_id'     => get_the_ID(),
				'product_id'  => null,
				'metadata'    => wp_json_encode( array(
					'order_id' => $order_id,
				) ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%f', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Attribute revenue to traffic sources
	 *
	 * @param int    $order_id Order ID.
	 * @param string $session_id Session ID.
	 * @param float  $order_total Order total.
	 */
	private function attribute_revenue( int $order_id, string $session_id, float $order_total ): void {
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
				'order_id'         => $order_id,
				'session_id'       => $session_id,
				'page_id'          => $touchpoints[0]->page_id,
				'attribution_type' => 'first_click',
				'revenue_share'    => $order_total,
				'created_at'       => $now,
			),
			array( '%d', '%s', '%d', '%s', '%f', '%s' )
		);

		// Last-click attribution (100% to last touchpoint)
		$wpdb->insert(
			$wpdb->prefix . 'ds_revenue_attribution',
			array(
				'order_id'         => $order_id,
				'session_id'       => $session_id,
				'page_id'          => $touchpoints[ $total_touchpoints - 1 ]->page_id,
				'attribution_type' => 'last_click',
				'revenue_share'    => $order_total,
				'created_at'       => $now,
			),
			array( '%d', '%s', '%d', '%s', '%f', '%s' )
		);

		// Linear attribution (equal credit to all touchpoints)
		$linear_share = $order_total / $total_touchpoints;
		foreach ( $touchpoints as $touchpoint ) {
			$wpdb->insert(
				$wpdb->prefix . 'ds_revenue_attribution',
				array(
					'order_id'         => $order_id,
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
		$this->time_decay_attribution( $order_id, $session_id, $touchpoints, $order_total, $now );
	}

	/**
	 * Calculate time-decay attribution
	 *
	 * @param int    $order_id Order ID.
	 * @param string $session_id Session ID.
	 * @param array  $touchpoints Array of touchpoint objects.
	 * @param float  $order_total Order total.
	 * @param string $now Current timestamp.
	 */
	private function time_decay_attribution( int $order_id, string $session_id, array $touchpoints, float $order_total, string $now ): void {
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
			$revenue_share     = $order_total * $normalized_weight;

			$wpdb->insert(
				$wpdb->prefix . 'ds_revenue_attribution',
				array(
					'order_id'         => $order_id,
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

		// Try to get from WooCommerce session
		if ( WC()->session ) {
			$customer_id = WC()->session->get_customer_id();
			if ( $customer_id ) {
				return hash( 'sha256', $customer_id );
			}
		}

		return null;
	}
}
