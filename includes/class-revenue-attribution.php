<?php
/**
 * Revenue Attribution
 *
 * Handles revenue attribution logic and provides methods for querying revenue data.
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Revenue Attribution Class
 */
class Revenue_Attribution {
	/**
	 * Get revenue by source
	 *
	 * @param array $args Query arguments.
	 * @return array Revenue data by source.
	 */
	public function get_revenue_by_source( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'start_date'       => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date'         => gmdate( 'Y-m-d' ),
			'attribution_type' => 'last_click',
			'group_by'         => 'utm_source', // utm_source, utm_medium, utm_campaign, referrer
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate attribution type
		$valid_types = array( 'first_click', 'last_click', 'linear', 'time_decay' );
		if ( ! in_array( $args['attribution_type'], $valid_types, true ) ) {
			$args['attribution_type'] = 'last_click';
		}

		// Validate group_by
		$valid_groups = array( 'utm_source', 'utm_medium', 'utm_campaign', 'referrer', 'country_code' );
		if ( ! in_array( $args['group_by'], $valid_groups, true ) ) {
			$args['group_by'] = 'utm_source';
		}

		// Build query
		$query = $wpdb->prepare(
			"SELECT 
				s.{$args['group_by']} AS source,
				COUNT(DISTINCT ra.order_id) AS orders,
				SUM(ra.revenue_share) AS total_revenue,
				AVG(ra.revenue_share) AS avg_revenue,
				COUNT(DISTINCT ra.session_id) AS sessions
			FROM {$wpdb->prefix}ds_revenue_attribution ra
			INNER JOIN {$wpdb->prefix}ds_sessions s ON ra.session_id = s.session_id
			WHERE ra.attribution_type = %s
			AND ra.created_at >= %s
			AND ra.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
			GROUP BY s.{$args['group_by']}
			ORDER BY total_revenue DESC",
			$args['attribution_type'],
			$args['start_date'],
			$args['end_date']
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Calculate conversion rate for each source
		foreach ( $results as &$result ) {
			$source = $result['source'] ?? 'direct';
			
			// Get total sessions for this source
			$total_sessions = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT session_id)
				FROM {$wpdb->prefix}ds_sessions
				WHERE {$args['group_by']} = %s
				AND first_seen >= %s
				AND first_seen < DATE_ADD(%s, INTERVAL 1 DAY)",
				$source,
				$args['start_date'],
				$args['end_date']
			) );

			$result['total_sessions']   = (int) $total_sessions;
			$result['conversion_rate']  = $total_sessions > 0 ? ( $result['sessions'] / $total_sessions ) * 100 : 0;
			$result['revenue_per_visit'] = $total_sessions > 0 ? $result['total_revenue'] / $total_sessions : 0;
		}

		return $results;
	}

	/**
	 * Get revenue by page
	 *
	 * @param array $args Query arguments.
	 * @return array Revenue data by page.
	 */
	public function get_revenue_by_page( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'start_date'       => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date'         => gmdate( 'Y-m-d' ),
			'attribution_type' => 'linear',
			'limit'            => 50,
		);

		$args = wp_parse_args( $args, $defaults );

		$query = $wpdb->prepare(
			"SELECT 
				ra.page_id,
				p.post_title AS page_title,
				pv.url AS page_url,
				COUNT(DISTINCT ra.order_id) AS orders,
				SUM(ra.revenue_share) AS total_revenue,
				AVG(ra.revenue_share) AS avg_revenue
			FROM {$wpdb->prefix}ds_revenue_attribution ra
			LEFT JOIN {$wpdb->posts} p ON ra.page_id = p.ID
			LEFT JOIN {$wpdb->prefix}ds_pageviews pv ON ra.page_id = pv.page_id
			WHERE ra.attribution_type = %s
			AND ra.created_at >= %s
			AND ra.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
			GROUP BY ra.page_id
			ORDER BY total_revenue DESC
			LIMIT %d",
			$args['attribution_type'],
			$args['start_date'],
			$args['end_date'],
			$args['limit']
		);

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get customer journey for a specific order
	 *
	 * @param int $order_id Order ID.
	 * @return array Customer journey data.
	 */
	public function get_customer_journey( int $order_id ): array {
		global $wpdb;

		// Get session ID for this order
		$session_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT session_id 
			FROM {$wpdb->prefix}ds_revenue_attribution 
			WHERE order_id = %d 
			LIMIT 1",
			$order_id
		) );

		if ( ! $session_id ) {
			return array();
		}

		// Get all touchpoints
		$touchpoints = $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				pv.page_id,
				pv.url,
				pv.referrer,
				pv.utm_source,
				pv.utm_medium,
				pv.utm_campaign,
				pv.created_at,
				p.post_title
			FROM {$wpdb->prefix}ds_pageviews pv
			LEFT JOIN {$wpdb->posts} p ON pv.page_id = p.ID
			WHERE pv.session_id = %s
			ORDER BY pv.created_at ASC",
			$session_id
		), ARRAY_A );

		// Get all events
		$events = $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				event_type,
				event_value,
				page_id,
				product_id,
				metadata,
				created_at
			FROM {$wpdb->prefix}ds_events
			WHERE session_id = %s
			ORDER BY created_at ASC",
			$session_id
		), ARRAY_A );

		// Get attribution data
		$attribution = $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				page_id,
				attribution_type,
				revenue_share
			FROM {$wpdb->prefix}ds_revenue_attribution
			WHERE order_id = %d",
			$order_id
		), ARRAY_A );

		return array(
			'session_id'  => $session_id,
			'touchpoints' => $touchpoints,
			'events'      => $events,
			'attribution' => $attribution,
		);
	}

	/**
	 * Get time to conversion statistics
	 *
	 * @param array $args Query arguments.
	 * @return array Time to conversion data.
	 */
	public function get_time_to_conversion( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'start_date' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date'   => gmdate( 'Y-m-d' ),
		);

		$args = wp_parse_args( $args, $defaults );

		$query = $wpdb->prepare(
			"SELECT 
				TIMESTAMPDIFF(HOUR, s.first_seen, e.created_at) AS hours_to_conversion,
				COUNT(*) AS conversions
			FROM {$wpdb->prefix}ds_events e
			INNER JOIN {$wpdb->prefix}ds_sessions s ON e.session_id = s.session_id
			WHERE e.event_type IN ('purchase', 'edd_purchase')
			AND e.created_at >= %s
			AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
			GROUP BY hours_to_conversion
			ORDER BY hours_to_conversion ASC",
			$args['start_date'],
			$args['end_date']
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Calculate statistics
		$total_conversions = array_sum( wp_list_pluck( $results, 'conversions' ) );
		$total_hours       = 0;

		foreach ( $results as $result ) {
			$total_hours += $result['hours_to_conversion'] * $result['conversions'];
		}

		$avg_hours = $total_conversions > 0 ? $total_hours / $total_conversions : 0;

		return array(
			'distribution'      => $results,
			'total_conversions' => $total_conversions,
			'avg_hours'         => round( $avg_hours, 2 ),
			'avg_days'          => round( $avg_hours / 24, 2 ),
		);
	}

	/**
	 * Get Revenue Per Visitor (RPV) by source
	 *
	 * @param array $args Query arguments.
	 * @return array RPV data.
	 */
	public function get_rpv_by_source( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'start_date' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date'   => gmdate( 'Y-m-d' ),
		);

		$args = wp_parse_args( $args, $defaults );

		$query = $wpdb->prepare(
			"SELECT 
				s.utm_source AS source,
				COUNT(DISTINCT s.session_id) AS total_visitors,
				SUM(s.total_revenue) AS total_revenue,
				SUM(s.total_revenue) / COUNT(DISTINCT s.session_id) AS rpv,
				COUNT(DISTINCT CASE WHEN s.total_revenue > 0 THEN s.session_id END) AS converted_visitors,
				(COUNT(DISTINCT CASE WHEN s.total_revenue > 0 THEN s.session_id END) / COUNT(DISTINCT s.session_id)) * 100 AS conversion_rate
			FROM {$wpdb->prefix}ds_sessions s
			WHERE s.first_seen >= %s
			AND s.first_seen < DATE_ADD(%s, INTERVAL 1 DAY)
			GROUP BY s.utm_source
			ORDER BY rpv DESC",
			$args['start_date'],
			$args['end_date']
		);

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Compare attribution models
	 *
	 * @param int $order_id Order ID.
	 * @return array Attribution comparison data.
	 */
	public function compare_attribution_models( int $order_id ): array {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				attribution_type,
				page_id,
				revenue_share,
				p.post_title AS page_title
			FROM {$wpdb->prefix}ds_revenue_attribution ra
			LEFT JOIN {$wpdb->posts} p ON ra.page_id = p.ID
			WHERE ra.order_id = %d
			ORDER BY attribution_type, revenue_share DESC",
			$order_id
		), ARRAY_A );

		// Group by attribution type
		$grouped = array();
		foreach ( $results as $result ) {
			$type = $result['attribution_type'];
			if ( ! isset( $grouped[ $type ] ) ) {
				$grouped[ $type ] = array();
			}
			$grouped[ $type ][] = $result;
		}

		return $grouped;
	}

	/**
	 * Get multi-touch attribution summary
	 *
	 * @param array $args Query arguments.
	 * @return array Multi-touch attribution summary.
	 */
	public function get_multitouch_summary( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'start_date' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date'   => gmdate( 'Y-m-d' ),
		);

		$args = wp_parse_args( $args, $defaults );

		// Get average number of touchpoints before conversion
		$avg_touchpoints = $wpdb->get_var( $wpdb->prepare(
			"SELECT AVG(touchpoint_count) 
			FROM (
				SELECT session_id, COUNT(*) AS touchpoint_count
				FROM {$wpdb->prefix}ds_pageviews
				WHERE session_id IN (
					SELECT DISTINCT session_id 
					FROM {$wpdb->prefix}ds_events 
					WHERE event_type IN ('purchase', 'edd_purchase')
					AND created_at >= %s
					AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)
				)
				GROUP BY session_id
			) AS t",
			$args['start_date'],
			$args['end_date']
		) );

		// Get distribution of touchpoint counts
		$distribution = $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				touchpoint_count,
				COUNT(*) AS conversions
			FROM (
				SELECT session_id, COUNT(*) AS touchpoint_count
				FROM {$wpdb->prefix}ds_pageviews
				WHERE session_id IN (
					SELECT DISTINCT session_id 
					FROM {$wpdb->prefix}ds_events 
					WHERE event_type IN ('purchase', 'edd_purchase')
					AND created_at >= %s
					AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)
				)
				GROUP BY session_id
			) AS t
			GROUP BY touchpoint_count
			ORDER BY touchpoint_count ASC",
			$args['start_date'],
			$args['end_date']
		), ARRAY_A );

		return array(
			'avg_touchpoints' => round( (float) $avg_touchpoints, 2 ),
			'distribution'    => $distribution,
		);
	}
}
