<?php
/**
 * WooCommerce Integration
 *
 * Links email campaign clicks to WooCommerce purchases for revenue attribution.
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals\Integrations;

use DataSignals\Email_Tracker;

defined( 'ABSPATH' ) || exit;

class WooCommerce_Integration {

	/**
	 * Initialize integration
	 */
	public static function init() {
		// Track order completion
		add_action( 'woocommerce_payment_complete', [ __CLASS__, 'track_order_completion' ] );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'track_order_completion' ] );

		// Track add to cart from email campaign
		add_action( 'woocommerce_add_to_cart', [ __CLASS__, 'track_add_to_cart' ], 10, 6 );
	}

	/**
	 * Track order completion and attribute to email campaign
	 *
	 * @param int $order_id Order ID
	 */
	public static function track_order_completion( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Get session ID
		$session_id = self::get_session_id();

		if ( empty( $session_id ) ) {
			return;
		}

		// Get order total
		$revenue = floatval( $order->get_total() );

		// Mark email click as converted
		Email_Tracker::mark_converted( $session_id, $revenue );

		// Store session ID in order meta for future reference
		$order->update_meta_data( '_ds_session_id', $session_id );
		$order->save();

		// Update session revenue
		self::update_session_revenue( $session_id, $revenue );

		// Log event
		self::log_event( $session_id, 'purchase', $revenue, $order_id );
	}

	/**
	 * Track add to cart events
	 *
	 * @param string $cart_item_key Cart item key
	 * @param int    $product_id    Product ID
	 * @param int    $quantity      Quantity
	 * @param int    $variation_id  Variation ID
	 * @param array  $variation     Variation data
	 * @param array  $cart_item_data Cart item data
	 */
	public static function track_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		$session_id = self::get_session_id();

		if ( empty( $session_id ) ) {
			return;
		}

		// Get product
		$product = wc_get_product( $variation_id ?: $product_id );

		if ( ! $product ) {
			return;
		}

		$value = floatval( $product->get_price() ) * $quantity;

		// Log event
		self::log_event( $session_id, 'add_to_cart', $value, $product_id );
	}

	/**
	 * Get current session ID
	 *
	 * @return string|null Session ID
	 */
	private static function get_session_id() {
		if ( isset( $_COOKIE['ds_session'] ) ) {
			return sanitize_text_field( $_COOKIE['ds_session'] );
		}
		return null;
	}

	/**
	 * Update session revenue
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param string $session_id Session ID
	 * @param float  $revenue    Revenue amount
	 */
	private static function update_session_revenue( $session_id, $revenue ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_sessions';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name} 
				SET total_revenue = total_revenue + %f,
					last_seen = %s
				WHERE session_id = %s",
				$revenue,
				current_time( 'mysql', true ),
				$session_id
			)
		);
	}

	/**
	 * Log event to events table
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param string $session_id   Session ID
	 * @param string $event_type   Event type
	 * @param float  $event_value  Event value
	 * @param int    $reference_id Reference ID (order/product)
	 */
	private static function log_event( $session_id, $event_type, $event_value = 0, $reference_id = 0 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_events';

		$wpdb->insert(
			$table_name,
			[
				'session_id'  => $session_id,
				'event_type'  => $event_type,
				'event_value' => $event_value,
				'product_id'  => $reference_id,
				'created_at'  => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%f', '%d', '%s' ]
		);
	}
}

// Initialize integration
WooCommerce_Integration::init();
