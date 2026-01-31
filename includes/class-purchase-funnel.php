<?php
/**
 * Purchase Funnel
 *
 * Handles purchase funnel analysis and cart abandonment tracking.
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Purchase Funnel Class
 */
class Purchase_Funnel {
	/**
	 * Get funnel analysis
	 *
	 * @param array $args Query arguments.
	 * @return array Funnel analysis data.
	 */
	public function get_funnel_analysis( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'start_date' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date'   => gmdate( 'Y-m-d' ),
			'source'     => null, // Filter by traffic source
		);

		$args = wp_parse_args( $args, $defaults );

		// Build WHERE clause for source filter
		$source_where = '';
		$source_params = array( $args['start_date'], $args['end_date'] );
		
		if ( $args['source'] ) {
			$source_where = ' AND s.utm_source = %s';
			$source_params[] = $args['source'];
		}

		// Step 1: Product Views
		$product_views_query = $wpdb->prepare(
			"SELECT COUNT(DISTINCT e.session_id) AS count
			FROM {$wpdb->prefix}ds_events e
			INNER JOIN {$wpdb->prefix}ds_sessions s ON e.session_id = s.session_id
			WHERE e.event_type IN ('download_view', 'product_view')
			AND e.created_at >= %s
			AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)" . $source_where,
			...$source_params
		);
		$product_views = (int) $wpdb->get_var( $product_views_query );

		// Step 2: Cart Created
		$cart_created_params = $source_params;
		$cart_created_query = $wpdb->prepare(
			"SELECT COUNT(DISTINCT e.session_id) AS count
			FROM {$wpdb->prefix}ds_events e
			INNER JOIN {$wpdb->prefix}ds_sessions s ON e.session_id = s.session_id
			WHERE e.event_type IN ('cart_created', 'edd_cart_created')
			AND e.created_at >= %s
			AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)" . $source_where,
			...$cart_created_params
		);
		$cart_created = (int) $wpdb->get_var( $cart_created_query );

		// Step 3: Checkout Started
		$checkout_started_params = $source_params;
		$checkout_started_query = $wpdb->prepare(
			"SELECT COUNT(DISTINCT e.session_id) AS count
			FROM {$wpdb->prefix}ds_events e
			INNER JOIN {$wpdb->prefix}ds_sessions s ON e.session_id = s.session_id
			WHERE e.event_type IN ('checkout_started', 'edd_checkout_started')
			AND e.created_at >= %s
			AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)" . $source_where,
			...$checkout_started_params
		);
		$checkout_started = (int) $wpdb->get_var( $checkout_started_query );

		// Step 4: Purchase Completed
		$purchase_completed_params = $source_params;
		$purchase_completed_query = $wpdb->prepare(
			"SELECT COUNT(DISTINCT e.session_id) AS count
			FROM {$wpdb->prefix}ds_events e
			INNER JOIN {$wpdb->prefix}ds_sessions s ON e.session_id = s.session_id
			WHERE e.event_type IN ('purchase', 'edd_purchase')
			AND e.created_at >= %s
			AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)" . $source_where,
			...$purchase_completed_params
		);
		$purchase_completed = (int) $wpdb->get_var( $purchase_completed_query );

		// Calculate conversion rates and drop-offs
		$funnel = array(
			array(
				'step'            => 'Product View',
				'sessions'        => $product_views,
				'conversion_rate' => 100,
				'drop_off'        => 0,
				'drop_off_count'  => 0,
			),
			array(
				'step'            => 'Cart Created',
				'sessions'        => $cart_created,
				'conversion_rate' => $product_views > 0 ? ( $cart_created / $product_views ) * 100 : 0,
				'drop_off'        => $product_views > 0 ? ( ( $product_views - $cart_created ) / $product_views ) * 100 : 0,
				'drop_off_count'  => $product_views - $cart_created,
			),
			array(
				'step'            => 'Checkout Started',
				'sessions'        => $checkout_started,
				'conversion_rate' => $product_views > 0 ? ( $checkout_started / $product_views ) * 100 : 0,
				'drop_off'        => $cart_created > 0 ? ( ( $cart_created - $checkout_started ) / $cart_created ) * 100 : 0,
				'drop_off_count'  => $cart_created - $checkout_started,
			),
			array(
				'step'            => 'Purchase Completed',
				'sessions'        => $purchase_completed,
				'conversion_rate' => $product_views > 0 ? ( $purchase_completed / $product_views ) * 100 : 0,
				'drop_off'        => $checkout_started > 0 ? ( ( $checkout_started - $purchase_completed ) / $checkout_started ) * 100 : 0,
				'drop_off_count'  => $checkout_started - $purchase_completed,
			),
		);

		// Calculate overall metrics
		$overall_conversion = $product_views > 0 ? ( $purchase_completed / $product_views ) * 100 : 0;
		$total_drop_off     = $product_views - $purchase_completed;

		return array(
			'funnel'              => $funnel,
			'overall_conversion'  => round( $overall_conversion, 2 ),
			'total_drop_off'      => $total_drop_off,
			'total_sessions'      => $product_views,
			'total_conversions'   => $purchase_completed,
		);
	}

	/**
	 * Get cart abandonment data
	 *
	 * @param array $args Query arguments.
	 * @return array Cart abandonment data.
	 */
	public function get_cart_abandonment( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'start_date'   => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date'     => gmdate( 'Y-m-d' ),
			'abandonment_hours' => 24, // Consider cart abandoned after this many hours
		);

		$args = wp_parse_args( $args, $defaults );

		// Find sessions with cart created but no purchase
		$query = $wpdb->prepare(
			"SELECT 
				s.session_id,
				s.utm_source,
				s.utm_medium,
				s.utm_campaign,
				s.first_referrer,
				e_cart.created_at AS cart_created_at,
				e_cart.event_value AS cart_value,
				e_cart.metadata AS cart_metadata
			FROM {$wpdb->prefix}ds_sessions s
			INNER JOIN {$wpdb->prefix}ds_events e_cart ON s.session_id = e_cart.session_id
			WHERE e_cart.event_type IN ('cart_created', 'edd_cart_created')
			AND e_cart.created_at >= %s
			AND e_cart.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
			AND NOT EXISTS (
				SELECT 1 
				FROM {$wpdb->prefix}ds_events e_purchase
				WHERE e_purchase.session_id = s.session_id
				AND e_purchase.event_type IN ('purchase', 'edd_purchase')
			)
			AND TIMESTAMPDIFF(HOUR, e_cart.created_at, NOW()) >= %d
			ORDER BY e_cart.created_at DESC",
			$args['start_date'],
			$args['end_date'],
			$args['abandonment_hours']
		);

		$abandoned_carts = $wpdb->get_results( $query, ARRAY_A );

		// Calculate statistics by source
		$by_source = array();
		$total_value = 0;

		foreach ( $abandoned_carts as $cart ) {
			$source = $cart['utm_source'] ?? 'direct';
			
			if ( ! isset( $by_source[ $source ] ) ) {
				$by_source[ $source ] = array(
					'source'        => $source,
					'abandoned'     => 0,
					'total_value'   => 0,
					'avg_value'     => 0,
				);
			}

			$by_source[ $source ]['abandoned']++;
			$by_source[ $source ]['total_value'] += (float) $cart['cart_value'];
			$total_value += (float) $cart['cart_value'];
		}

		// Calculate averages
		foreach ( $by_source as $source => &$data ) {
			$data['avg_value'] = $data['abandoned'] > 0 ? $data['total_value'] / $data['abandoned'] : 0;
		}

		// Calculate abandonment rate
		$total_carts = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT session_id)
			FROM {$wpdb->prefix}ds_events
			WHERE event_type IN ('cart_created', 'edd_cart_created')
			AND created_at >= %s
			AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)",
			$args['start_date'],
			$args['end_date']
		) );

		$abandonment_rate = $total_carts > 0 ? ( count( $abandoned_carts ) / $total_carts ) * 100 : 0;

		return array(
			'abandoned_carts'   => $abandoned_carts,
			'by_source'         => array_values( $by_source ),
			'total_abandoned'   => count( $abandoned_carts ),
			'total_value'       => $total_value,
			'avg_cart_value'    => count( $abandoned_carts ) > 0 ? $total_value / count( $abandoned_carts ) : 0,
			'total_carts'       => (int) $total_carts,
			'abandonment_rate'  => round( $abandonment_rate, 2 ),
		);
	}

	/**
	 * Get product performance analytics
	 *
	 * @param array $args Query arguments.
	 * @return array Product performance data.
	 */
	public function get_product_performance( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'start_date' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date'   => gmdate( 'Y-m-d' ),
			'limit'      => 50,
			'source'     => null,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build source filter
		$source_join = '';
		$source_where = '';
		$params = array( $args['start_date'], $args['end_date'], $args['limit'] );

		if ( $args['source'] ) {
			$source_join = "INNER JOIN {$wpdb->prefix}ds_sessions s ON e.session_id = s.session_id";
			$source_where = " AND s.utm_source = %s";
			array_splice( $params, 2, 0, array( $args['source'] ) );
		}

		// Get product performance data
		$query = $wpdb->prepare(
			"SELECT 
				e.product_id,
				p.post_title AS product_name,
				COUNT(DISTINCT CASE WHEN e.event_type IN ('product_view', 'download_view') THEN e.session_id END) AS views,
				COUNT(DISTINCT CASE WHEN e.event_type IN ('cart_created', 'edd_cart_created') THEN e.session_id END) AS add_to_carts,
				COUNT(DISTINCT CASE WHEN e.event_type IN ('product_purchase', 'download_purchase') THEN e.session_id END) AS purchases,
				SUM(CASE WHEN e.event_type IN ('product_purchase', 'download_purchase') THEN e.event_value ELSE 0 END) AS total_revenue,
				AVG(CASE WHEN e.event_type IN ('product_purchase', 'download_purchase') THEN e.event_value ELSE NULL END) AS avg_price
			FROM {$wpdb->prefix}ds_events e
			LEFT JOIN {$wpdb->posts} p ON e.product_id = p.ID
			{$source_join}
			WHERE e.product_id IS NOT NULL
			AND e.created_at >= %s
			AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
			{$source_where}
			GROUP BY e.product_id
			ORDER BY total_revenue DESC
			LIMIT %d",
			...$params
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Calculate conversion rates
		foreach ( $results as &$result ) {
			$result['view_to_cart_rate'] = $result['views'] > 0 ? ( $result['add_to_carts'] / $result['views'] ) * 100 : 0;
			$result['cart_to_purchase_rate'] = $result['add_to_carts'] > 0 ? ( $result['purchases'] / $result['add_to_carts'] ) * 100 : 0;
			$result['overall_conversion_rate'] = $result['views'] > 0 ? ( $result['purchases'] / $result['views'] ) * 100 : 0;
			$result['revenue_per_view'] = $result['views'] > 0 ? $result['total_revenue'] / $result['views'] : 0;
		}

		return $results;
	}

	/**
	 * Get Average Order Value (AOV) by channel
	 *
	 * @param array $args Query arguments.
	 * @return array AOV data by channel.
	 */
	public function get_aov_by_channel( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'start_date' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date'   => gmdate( 'Y-m-d' ),
			'group_by'   => 'utm_source', // utm_source, utm_medium, utm_campaign
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate group_by
		$valid_groups = array( 'utm_source', 'utm_medium', 'utm_campaign' );
		if ( ! in_array( $args['group_by'], $valid_groups, true ) ) {
			$args['group_by'] = 'utm_source';
		}

		$query = $wpdb->prepare(
			"SELECT 
				s.{$args['group_by']} AS channel,
				COUNT(DISTINCT e.session_id) AS orders,
				SUM(e.event_value) AS total_revenue,
				AVG(e.event_value) AS aov,
				MIN(e.event_value) AS min_order,
				MAX(e.event_value) AS max_order
			FROM {$wpdb->prefix}ds_events e
			INNER JOIN {$wpdb->prefix}ds_sessions s ON e.session_id = s.session_id
			WHERE e.event_type IN ('purchase', 'edd_purchase')
			AND e.created_at >= %s
			AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
			GROUP BY s.{$args['group_by']}
			ORDER BY aov DESC",
			$args['start_date'],
			$args['end_date']
		);

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get drop-off points analysis
	 *
	 * @param array $args Query arguments.
	 * @return array Drop-off points data.
	 */
	public function get_dropoff_points( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'start_date' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date'   => gmdate( 'Y-m-d' ),
		);

		$args = wp_parse_args( $args, $defaults );

		// Find sessions that reached each step but didn't proceed
		$drop_offs = array();

		// Product view → Cart
		$product_no_cart = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT e.session_id)
			FROM {$wpdb->prefix}ds_events e
			WHERE e.event_type IN ('product_view', 'download_view')
			AND e.created_at >= %s
			AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->prefix}ds_events e2
				WHERE e2.session_id = e.session_id
				AND e2.event_type IN ('cart_created', 'edd_cart_created')
			)",
			$args['start_date'],
			$args['end_date']
		) );

		// Cart → Checkout
		$cart_no_checkout = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT e.session_id)
			FROM {$wpdb->prefix}ds_events e
			WHERE e.event_type IN ('cart_created', 'edd_cart_created')
			AND e.created_at >= %s
			AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->prefix}ds_events e2
				WHERE e2.session_id = e.session_id
				AND e2.event_type IN ('checkout_started', 'edd_checkout_started')
			)",
			$args['start_date'],
			$args['end_date']
		) );

		// Checkout → Purchase
		$checkout_no_purchase = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT e.session_id)
			FROM {$wpdb->prefix}ds_events e
			WHERE e.event_type IN ('checkout_started', 'edd_checkout_started')
			AND e.created_at >= %s
			AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->prefix}ds_events e2
				WHERE e2.session_id = e.session_id
				AND e2.event_type IN ('purchase', 'edd_purchase')
			)",
			$args['start_date'],
			$args['end_date']
		) );

		return array(
			array(
				'step'      => 'Product View → Cart',
				'drop_offs' => (int) $product_no_cart,
			),
			array(
				'step'      => 'Cart → Checkout',
				'drop_offs' => (int) $cart_no_checkout,
			),
			array(
				'step'      => 'Checkout → Purchase',
				'drop_offs' => (int) $checkout_no_purchase,
			),
		);
	}
}
