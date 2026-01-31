<?php
/**
 * Keyword Analyzer
 *
 * Analyzes keyword performance, tracks position changes, and identifies opportunities
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Keyword_Analyzer
 */
class Keyword_Analyzer {

	/**
	 * Position drop threshold for alerts
	 *
	 * @var int
	 */
	private const POSITION_DROP_THRESHOLD = 5;

	/**
	 * High impressions threshold
	 *
	 * @var int
	 */
	private const HIGH_IMPRESSIONS_THRESHOLD = 1000;

	/**
	 * Low CTR threshold
	 *
	 * @var float
	 */
	private const LOW_CTR_THRESHOLD = 0.02; // 2%

	/**
	 * Detect position drops for keywords
	 *
	 * @param int $days Number of days to analyze.
	 * @return array Keywords with significant position drops.
	 */
	public function detect_position_drops( int $days = 7 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_gsc_keywords';

		// Compare average position from last 7 days vs previous 7 days
		$query = $wpdb->prepare(
			"SELECT 
				keyword,
				AVG(CASE WHEN date >= DATE_SUB(CURDATE(), INTERVAL %d DAY) THEN position END) as recent_position,
				AVG(CASE WHEN date >= DATE_SUB(CURDATE(), INTERVAL %d DAY) AND date < DATE_SUB(CURDATE(), INTERVAL %d DAY) THEN position END) as previous_position,
				SUM(CASE WHEN date >= DATE_SUB(CURDATE(), INTERVAL %d DAY) THEN impressions END) as recent_impressions
			FROM {$table_name}
			WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
			GROUP BY keyword
			HAVING recent_position IS NOT NULL 
				AND previous_position IS NOT NULL
				AND (recent_position - previous_position) >= %d
			ORDER BY (recent_position - previous_position) DESC
			LIMIT 100",
			$days,
			$days * 2,
			$days,
			$days,
			$days * 2,
			self::POSITION_DROP_THRESHOLD
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query, ARRAY_A );

		return array_map(
			function ( $row ) {
				return array(
					'keyword'           => $row['keyword'],
					'recent_position'   => round( floatval( $row['recent_position'] ), 2 ),
					'previous_position' => round( floatval( $row['previous_position'] ), 2 ),
					'position_drop'     => round( floatval( $row['recent_position'] ) - floatval( $row['previous_position'] ), 2 ),
					'recent_impressions' => intval( $row['recent_impressions'] ),
					'severity'          => $this->calculate_drop_severity(
						floatval( $row['recent_position'] ) - floatval( $row['previous_position'] ),
						intval( $row['recent_impressions'] )
					),
				);
			},
			$results
		);
	}

	/**
	 * Calculate severity of position drop
	 *
	 * @param float $drop Position drop amount.
	 * @param int   $impressions Recent impressions.
	 * @return string Severity level (low, medium, high, critical).
	 */
	private function calculate_drop_severity( float $drop, int $impressions ): string {
		if ( $drop >= 20 && $impressions >= 5000 ) {
			return 'critical';
		} elseif ( $drop >= 10 && $impressions >= 1000 ) {
			return 'high';
		} elseif ( $drop >= 5 && $impressions >= 500 ) {
			return 'medium';
		}

		return 'low';
	}

	/**
	 * Identify high-opportunity keywords (high impressions, low CTR)
	 *
	 * @param int $days Number of days to analyze.
	 * @return array Opportunity keywords.
	 */
	public function identify_opportunities( int $days = 30 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_gsc_keywords';

		$query = $wpdb->prepare(
			"SELECT 
				keyword,
				SUM(impressions) as total_impressions,
				SUM(clicks) as total_clicks,
				AVG(ctr) as avg_ctr,
				AVG(position) as avg_position,
				AVG(revenue_estimate) as potential_revenue
			FROM {$table_name}
			WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
			GROUP BY keyword
			HAVING total_impressions >= %d
				AND avg_ctr < %f
			ORDER BY total_impressions DESC
			LIMIT 50",
			$days,
			self::HIGH_IMPRESSIONS_THRESHOLD,
			self::LOW_CTR_THRESHOLD
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query, ARRAY_A );

		return array_map(
			function ( $row ) {
				$current_ctr   = floatval( $row['avg_ctr'] );
				$avg_position  = floatval( $row['avg_position'] );
				$impressions   = intval( $row['total_impressions'] );

				// Estimate potential if CTR improved to position-appropriate level
				$target_ctr = $this->estimate_target_ctr( $avg_position );
				$potential_additional_clicks = $impressions * ( $target_ctr - $current_ctr );

				return array(
					'keyword'                     => $row['keyword'],
					'impressions'                 => $impressions,
					'clicks'                      => intval( $row['total_clicks'] ),
					'current_ctr'                 => round( $current_ctr, 4 ),
					'avg_position'                => round( $avg_position, 2 ),
					'target_ctr'                  => round( $target_ctr, 4 ),
					'potential_additional_clicks' => round( $potential_additional_clicks, 0 ),
					'opportunity_score'           => $this->calculate_opportunity_score( $impressions, $current_ctr, $target_ctr ),
				);
			},
			$results
		);
	}

	/**
	 * Estimate target CTR based on position
	 *
	 * Based on average CTR by position (industry benchmarks)
	 *
	 * @param float $position Average position.
	 * @return float Target CTR.
	 */
	private function estimate_target_ctr( float $position ): float {
		// Position 1-10 CTR benchmarks
		$ctr_by_position = array(
			1  => 0.316,
			2  => 0.156,
			3  => 0.107,
			4  => 0.077,
			5  => 0.059,
			6  => 0.047,
			7  => 0.039,
			8  => 0.033,
			9  => 0.029,
			10 => 0.025,
		);

		$rounded_position = round( $position );

		if ( $rounded_position <= 10 ) {
			return $ctr_by_position[ $rounded_position ] ?? 0.02;
		}

		// Beyond position 10, assume declining CTR
		return max( 0.01, 0.025 - ( ( $rounded_position - 10 ) * 0.002 ) );
	}

	/**
	 * Calculate opportunity score
	 *
	 * @param int   $impressions Total impressions.
	 * @param float $current_ctr Current CTR.
	 * @param float $target_ctr Target CTR.
	 * @return int Score (0-100).
	 */
	private function calculate_opportunity_score( int $impressions, float $current_ctr, float $target_ctr ): int {
		$ctr_gap        = $target_ctr - $current_ctr;
		$potential_gain = $impressions * $ctr_gap;

		// Normalize to 0-100 scale
		$score = min( 100, ( $potential_gain / 100 ) * 10 );

		return round( $score );
	}

	/**
	 * Track money keywords
	 *
	 * Keywords that have led to conversions/revenue
	 *
	 * @param int $days Number of days to analyze.
	 * @return array Money keywords with performance data.
	 */
	public function get_money_keywords( int $days = 30 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_gsc_keywords';

		$query = $wpdb->prepare(
			"SELECT 
				keyword,
				SUM(impressions) as total_impressions,
				SUM(clicks) as total_clicks,
				AVG(position) as avg_position,
				AVG(ctr) as avg_ctr,
				SUM(revenue_estimate) as total_revenue
			FROM {$table_name}
			WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
				AND revenue_estimate > 0
			GROUP BY keyword
			ORDER BY total_revenue DESC
			LIMIT 100",
			$days
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query, ARRAY_A );

		return array_map(
			function ( $row ) {
				return array(
					'keyword'      => $row['keyword'],
					'impressions'  => intval( $row['total_impressions'] ),
					'clicks'       => intval( $row['total_clicks'] ),
					'position'     => round( floatval( $row['avg_position'] ), 2 ),
					'ctr'          => round( floatval( $row['avg_ctr'] ), 4 ),
					'revenue'      => floatval( $row['total_revenue'] ),
					'revenue_per_click' => intval( $row['total_clicks'] ) > 0 
						? round( floatval( $row['total_revenue'] ) / intval( $row['total_clicks'] ), 2 )
						: 0,
				);
			},
			$results
		);
	}

	/**
	 * Get keyword trends
	 *
	 * @param string $keyword Specific keyword to analyze.
	 * @param int    $days Number of days of history.
	 * @return array Daily performance data.
	 */
	public function get_keyword_trend( string $keyword, int $days = 30 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_gsc_keywords';

		$query = $wpdb->prepare(
			"SELECT 
				date,
				impressions,
				clicks,
				position,
				ctr,
				revenue_estimate
			FROM {$table_name}
			WHERE keyword = %s
				AND date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
			ORDER BY date ASC",
			$keyword,
			$days
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query, ARRAY_A );

		return array_map(
			function ( $row ) {
				return array(
					'date'             => $row['date'],
					'impressions'      => intval( $row['impressions'] ),
					'clicks'           => intval( $row['clicks'] ),
					'position'         => round( floatval( $row['position'] ), 2 ),
					'ctr'              => round( floatval( $row['ctr'] ), 4 ),
					'revenue_estimate' => floatval( $row['revenue_estimate'] ),
				);
			},
			$results
		);
	}

	/**
	 * Get content gap analysis
	 *
	 * High-volume keywords with low content quality/presence
	 *
	 * @param int $days Number of days to analyze.
	 * @return array Content gap keywords.
	 */
	public function analyze_content_gaps( int $days = 30 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_gsc_keywords';

		// Find keywords with high impressions but low clicks and poor position
		$query = $wpdb->prepare(
			"SELECT 
				keyword,
				SUM(impressions) as total_impressions,
				SUM(clicks) as total_clicks,
				AVG(position) as avg_position,
				AVG(ctr) as avg_ctr
			FROM {$table_name}
			WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
			GROUP BY keyword
			HAVING total_impressions >= 100
				AND avg_position > 10
				AND total_clicks < 50
			ORDER BY total_impressions DESC
			LIMIT 50",
			$days
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query, ARRAY_A );

		return array_map(
			function ( $row ) {
				$impressions = intval( $row['total_impressions'] );
				$clicks      = intval( $row['total_clicks'] );
				$position    = floatval( $row['avg_position'] );

				// Estimate potential if ranking improved to position 3-5
				$target_position = 4;
				$target_ctr      = 0.08; // ~8% CTR for position 4
				$potential_clicks = $impressions * $target_ctr;

				return array(
					'keyword'          => $row['keyword'],
					'impressions'      => $impressions,
					'clicks'           => $clicks,
					'current_position' => round( $position, 2 ),
					'current_ctr'      => round( floatval( $row['avg_ctr'] ), 4 ),
					'target_position'  => $target_position,
					'target_ctr'       => $target_ctr,
					'potential_clicks' => round( $potential_clicks, 0 ),
					'gap_score'        => $this->calculate_gap_score( $impressions, $clicks, $position ),
				);
			},
			$results
		);
	}

	/**
	 * Calculate content gap score
	 *
	 * @param int   $impressions Total impressions.
	 * @param int   $clicks Total clicks.
	 * @param float $position Current position.
	 * @return int Score (0-100).
	 */
	private function calculate_gap_score( int $impressions, int $clicks, float $position ): int {
		// Higher impressions = more opportunity
		$impression_score = min( 50, ( $impressions / 1000 ) * 10 );

		// Worse position = bigger gap to fill
		$position_score = min( 30, ( $position / 20 ) * 30 );

		// Low clicks despite impressions = underperforming
		$ctr            = $clicks > 0 ? $clicks / $impressions : 0;
		$performance_score = max( 0, 20 - ( $ctr * 1000 ) );

		return round( $impression_score + $position_score + $performance_score );
	}

	/**
	 * Get keyword statistics summary
	 *
	 * @param int $days Number of days to analyze.
	 * @return array Summary statistics.
	 */
	public function get_keyword_stats( int $days = 30 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_gsc_keywords';

		$query = $wpdb->prepare(
			"SELECT 
				COUNT(DISTINCT keyword) as total_keywords,
				SUM(impressions) as total_impressions,
				SUM(clicks) as total_clicks,
				AVG(position) as avg_position,
				AVG(ctr) as avg_ctr,
				SUM(revenue_estimate) as total_revenue
			FROM {$table_name}
			WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)",
			$days
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_row( $query, ARRAY_A );

		return array(
			'total_keywords'   => intval( $result['total_keywords'] ?? 0 ),
			'total_impressions' => intval( $result['total_impressions'] ?? 0 ),
			'total_clicks'     => intval( $result['total_clicks'] ?? 0 ),
			'avg_position'     => round( floatval( $result['avg_position'] ?? 0 ), 2 ),
			'avg_ctr'          => round( floatval( $result['avg_ctr'] ?? 0 ), 4 ),
			'total_revenue'    => floatval( $result['total_revenue'] ?? 0 ),
			'days'             => $days,
		);
	}
}
