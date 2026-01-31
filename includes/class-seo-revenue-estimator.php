<?php
/**
 * SEO Revenue Estimator
 *
 * Estimates revenue from organic search keywords based on conversion tracking
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Revenue_Estimator
 */
class SEO_Revenue_Estimator {

	/**
	 * Update revenue estimates for all keywords
	 *
	 * Correlates keyword clicks with conversions/revenue
	 */
	public function update_revenue_estimates(): void {
		global $wpdb;

		$keywords_table = $wpdb->prefix . 'ds_gsc_keywords';
		$sessions_table = $wpdb->prefix . 'ds_sessions';
		$events_table   = $wpdb->prefix . 'ds_events';

		// Calculate average revenue per organic click
		$avg_revenue_query = "
			SELECT 
				AVG(s.total_revenue / s.total_pageviews) as avg_revenue_per_click
			FROM {$sessions_table} s
			WHERE s.utm_medium = 'organic'
				AND s.total_revenue > 0
				AND s.first_seen >= DATE_SUB(NOW(), INTERVAL 30 DAY)
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$avg_revenue_result = $wpdb->get_row( $avg_revenue_query, ARRAY_A );
		$avg_revenue_per_click = floatval( $avg_revenue_result['avg_revenue_per_click'] ?? 0 );

		// If no conversion data yet, use a default estimate (or skip)
		if ( $avg_revenue_per_click <= 0 ) {
			error_log( 'Data Signals: No organic revenue data available for estimation' );
			return;
		}

		// Update keyword revenue estimates based on clicks
		$update_query = $wpdb->prepare(
			"UPDATE {$keywords_table}
			SET revenue_estimate = clicks * %f
			WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
			$avg_revenue_per_click
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $update_query );

		error_log( sprintf(
			'Data Signals: Updated revenue estimates (avg: $%.2f per click)',
			$avg_revenue_per_click
		) );
	}

	/**
	 * Estimate revenue for specific keyword
	 *
	 * @param string $keyword Keyword to estimate.
	 * @param int    $days Number of days to analyze.
	 * @return array Revenue estimation data.
	 */
	public function estimate_keyword_revenue( string $keyword, int $days = 30 ): array {
		global $wpdb;

		$keywords_table = $wpdb->prefix . 'ds_gsc_keywords';
		$sessions_table = $wpdb->prefix . 'ds_sessions';
		$pageviews_table = $wpdb->prefix . 'ds_pageviews';

		// Get keyword performance
		$keyword_query = $wpdb->prepare(
			"SELECT 
				SUM(clicks) as total_clicks,
				SUM(impressions) as total_impressions,
				AVG(position) as avg_position,
				AVG(ctr) as avg_ctr
			FROM {$keywords_table}
			WHERE keyword = %s
				AND date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)",
			$keyword,
			$days
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$keyword_data = $wpdb->get_row( $keyword_query, ARRAY_A );

		if ( ! $keyword_data || intval( $keyword_data['total_clicks'] ) === 0 ) {
			return array(
				'keyword'          => $keyword,
				'estimated_revenue' => 0,
				'confidence'       => 'none',
				'message'          => __( 'No click data available for this keyword.', 'data-signals' ),
			);
		}

		// Try to find actual conversions from this keyword (via referrer matching)
		$conversion_query = $wpdb->prepare(
			"SELECT 
				COUNT(DISTINCT s.session_id) as conversions,
				SUM(s.total_revenue) as actual_revenue
			FROM {$sessions_table} s
			WHERE s.first_referrer LIKE %s
				AND s.total_revenue > 0
				AND s.first_seen >= DATE_SUB(NOW(), INTERVAL %d DAY)",
			'%' . $wpdb->esc_like( $keyword ) . '%',
			$days
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$conversion_data = $wpdb->get_row( $conversion_query, ARRAY_A );

		$conversions    = intval( $conversion_data['conversions'] ?? 0 );
		$actual_revenue = floatval( $conversion_data['actual_revenue'] ?? 0 );
		$total_clicks   = intval( $keyword_data['total_clicks'] );

		if ( $conversions > 0 ) {
			// Direct attribution available
			$revenue_per_click = $actual_revenue / $total_clicks;
			$estimated_revenue = $actual_revenue;
			$confidence        = $conversions >= 10 ? 'high' : ( $conversions >= 3 ? 'medium' : 'low' );
		} else {
			// Use average organic conversion rate
			$avg_revenue_per_click = $this->get_average_organic_revenue_per_click( $days );
			$revenue_per_click     = $avg_revenue_per_click;
			$estimated_revenue     = $total_clicks * $avg_revenue_per_click;
			$confidence            = 'estimated';
		}

		return array(
			'keyword'           => $keyword,
			'clicks'            => $total_clicks,
			'impressions'       => intval( $keyword_data['total_impressions'] ),
			'position'          => round( floatval( $keyword_data['avg_position'] ), 2 ),
			'ctr'               => round( floatval( $keyword_data['avg_ctr'] ), 4 ),
			'conversions'       => $conversions,
			'actual_revenue'    => $actual_revenue,
			'estimated_revenue' => round( $estimated_revenue, 2 ),
			'revenue_per_click' => round( $revenue_per_click, 2 ),
			'confidence'        => $confidence,
		);
	}

	/**
	 * Get average revenue per organic click
	 *
	 * @param int $days Number of days to analyze.
	 * @return float Average revenue per click.
	 */
	private function get_average_organic_revenue_per_click( int $days = 30 ): float {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'ds_sessions';

		$query = $wpdb->prepare(
			"SELECT 
				SUM(total_revenue) / SUM(total_pageviews) as avg_revenue_per_click
			FROM {$sessions_table}
			WHERE utm_medium = 'organic'
				AND total_revenue > 0
				AND first_seen >= DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_var( $query );

		return floatval( $result ?? 0 );
	}

	/**
	 * Calculate keyword-to-conversion correlation
	 *
	 * Which keywords lead to purchases
	 *
	 * @param int $days Number of days to analyze.
	 * @return array Top converting keywords.
	 */
	public function get_top_converting_keywords( int $days = 30 ): array {
		global $wpdb;

		$keywords_table = $wpdb->prefix . 'ds_gsc_keywords';
		$sessions_table = $wpdb->prefix . 'ds_sessions';

		// This requires matching sessions with organic referrers
		// For now, we'll use revenue estimates
		$query = $wpdb->prepare(
			"SELECT 
				keyword,
				SUM(clicks) as total_clicks,
				SUM(revenue_estimate) as estimated_revenue,
				AVG(position) as avg_position,
				(SUM(revenue_estimate) / SUM(clicks)) as revenue_per_click
			FROM {$keywords_table}
			WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
				AND revenue_estimate > 0
			GROUP BY keyword
			ORDER BY estimated_revenue DESC
			LIMIT 50",
			$days
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query, ARRAY_A );

		return array_map(
			function ( $row ) {
				return array(
					'keyword'           => $row['keyword'],
					'clicks'            => intval( $row['total_clicks'] ),
					'estimated_revenue' => round( floatval( $row['estimated_revenue'] ), 2 ),
					'revenue_per_click' => round( floatval( $row['revenue_per_click'] ), 2 ),
					'avg_position'      => round( floatval( $row['avg_position'] ), 2 ),
				);
			},
			$results
		);
	}

	/**
	 * Track organic click attribution
	 *
	 * Links keyword clicks to session conversions
	 *
	 * @param string $session_id Session ID.
	 * @param string $keyword Keyword from referrer.
	 */
	public function track_keyword_attribution( string $session_id, string $keyword ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_keyword_attribution';

		// Store attribution for later correlation
		$wpdb->insert(
			$table_name,
			array(
				'session_id' => $session_id,
				'keyword'    => $keyword,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);
	}

	/**
	 * Calculate SEO value (total estimated revenue from organic)
	 *
	 * @param int $days Number of days to analyze.
	 * @return array SEO value metrics.
	 */
	public function calculate_seo_value( int $days = 30 ): array {
		global $wpdb;

		$keywords_table = $wpdb->prefix . 'ds_gsc_keywords';
		$sessions_table = $wpdb->prefix . 'ds_sessions';

		// Get keyword-based estimate
		$keyword_estimate_query = $wpdb->prepare(
			"SELECT 
				SUM(revenue_estimate) as estimated_revenue,
				SUM(clicks) as total_clicks
			FROM {$keywords_table}
			WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)",
			$days
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$keyword_estimate = $wpdb->get_row( $keyword_estimate_query, ARRAY_A );

		// Get actual organic revenue (session-based)
		$actual_revenue_query = $wpdb->prepare(
			"SELECT 
				SUM(total_revenue) as actual_revenue,
				COUNT(DISTINCT session_id) as organic_sessions
			FROM {$sessions_table}
			WHERE utm_medium = 'organic'
				AND first_seen >= DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$actual_revenue = $wpdb->get_row( $actual_revenue_query, ARRAY_A );

		$estimated = floatval( $keyword_estimate['estimated_revenue'] ?? 0 );
		$actual    = floatval( $actual_revenue['actual_revenue'] ?? 0 );

		// Use actual if available, otherwise estimated
		$seo_value = $actual > 0 ? $actual : $estimated;

		return array(
			'total_seo_value'   => round( $seo_value, 2 ),
			'estimated_revenue' => round( $estimated, 2 ),
			'actual_revenue'    => round( $actual, 2 ),
			'total_clicks'      => intval( $keyword_estimate['total_clicks'] ?? 0 ),
			'organic_sessions'  => intval( $actual_revenue['organic_sessions'] ?? 0 ),
			'confidence'        => $actual > 0 ? 'high' : 'estimated',
			'days'              => $days,
		);
	}

	/**
	 * Calculate revenue opportunity from position improvements
	 *
	 * @param int $days Number of days to analyze.
	 * @return array Opportunity data.
	 */
	public function calculate_position_opportunity( int $days = 30 ): array {
		global $wpdb;

		$keywords_table = $wpdb->prefix . 'ds_gsc_keywords';

		// Find keywords ranking 4-20 that could improve
		$query = $wpdb->prepare(
			"SELECT 
				keyword,
				SUM(impressions) as total_impressions,
				SUM(clicks) as current_clicks,
				AVG(position) as current_position,
				AVG(ctr) as current_ctr,
				SUM(revenue_estimate) as current_revenue
			FROM {$keywords_table}
			WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
			GROUP BY keyword
			HAVING current_position BETWEEN 4 AND 20
				AND total_impressions >= 100
			ORDER BY total_impressions DESC
			LIMIT 100",
			$days
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query, ARRAY_A );

		$opportunities = array_map(
			function ( $row ) {
				$impressions       = intval( $row['total_impressions'] );
				$current_position  = floatval( $row['current_position'] );
				$current_clicks    = intval( $row['current_clicks'] );
				$current_revenue   = floatval( $row['current_revenue'] );

				// Simulate improvement to position 3
				$target_position = 3;
				$target_ctr      = 0.107; // 10.7% CTR for position 3
				$potential_clicks = $impressions * $target_ctr;
				$additional_clicks = $potential_clicks - $current_clicks;

				// Estimate additional revenue
				$revenue_per_click    = $current_clicks > 0 ? $current_revenue / $current_clicks : 1;
				$additional_revenue   = $additional_clicks * $revenue_per_click;

				return array(
					'keyword'            => $row['keyword'],
					'current_position'   => round( $current_position, 2 ),
					'target_position'    => $target_position,
					'impressions'        => $impressions,
					'current_clicks'     => $current_clicks,
					'potential_clicks'   => round( $potential_clicks, 0 ),
					'additional_clicks'  => round( $additional_clicks, 0 ),
					'current_revenue'    => round( $current_revenue, 2 ),
					'potential_revenue'  => round( $current_revenue + $additional_revenue, 2 ),
					'additional_revenue' => round( $additional_revenue, 2 ),
				);
			},
			$results
		);

		$total_opportunity = array_sum( array_column( $opportunities, 'additional_revenue' ) );

		return array(
			'total_opportunity' => round( $total_opportunity, 2 ),
			'opportunities'     => $opportunities,
			'count'             => count( $opportunities ),
		);
	}

	/**
	 * Create keyword attribution table
	 *
	 * Stores keyword-to-session mapping for conversion tracking
	 */
	public static function create_attribution_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'ds_keyword_attribution';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			session_id CHAR(32) NOT NULL,
			keyword VARCHAR(255) NOT NULL,
			created_at DATETIME NOT NULL,
			INDEX idx_session (session_id),
			INDEX idx_keyword (keyword),
			INDEX idx_created (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
