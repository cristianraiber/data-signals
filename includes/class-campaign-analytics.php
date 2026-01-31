<?php
/**
 * Campaign Analytics
 *
 * Calculate campaign performance metrics including ROI, CTR, conversion rates.
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals;

defined( 'ABSPATH' ) || exit;

class Campaign_Analytics {

	/**
	 * Get campaign performance metrics
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param string $campaign_id Campaign ID
	 * @param array  $date_range  Optional date range ['start' => 'Y-m-d', 'end' => 'Y-m-d']
	 * @return array Campaign performance data
	 */
	public static function get_campaign_performance( $campaign_id, $date_range = [] ) {
		global $wpdb;

		$clicks_table = $wpdb->prefix . 'ds_email_clicks';
		$sessions_table = $wpdb->prefix . 'ds_sessions';

		// Build date filter
		$date_filter = self::build_date_filter( 'clicked_at', $date_range );

		// Get click metrics
		$click_query = $wpdb->prepare(
			"SELECT 
				COUNT(*) as total_clicks,
				COUNT(DISTINCT session_id) as unique_clicks,
				SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions,
				SUM(revenue) as total_revenue
			FROM {$clicks_table}
			WHERE campaign_id = %s {$date_filter}",
			$campaign_id
		);

		$click_data = $wpdb->get_row( $click_query, ARRAY_A );

		// Get session data for this campaign
		$session_query = $wpdb->prepare(
			"SELECT 
				COUNT(*) as total_sessions,
				SUM(total_revenue) as session_revenue
			FROM {$sessions_table}
			WHERE utm_campaign = %s",
			$campaign_id
		);

		$session_data = $wpdb->get_row( $session_query, ARRAY_A );

		// Calculate metrics
		$total_clicks = intval( $click_data['total_clicks'] ?? 0 );
		$unique_clicks = intval( $click_data['unique_clicks'] ?? 0 );
		$conversions = intval( $click_data['conversions'] ?? 0 );
		$total_revenue = floatval( $click_data['total_revenue'] ?? 0 );
		$session_revenue = floatval( $session_data['session_revenue'] ?? 0 );
		$total_sessions = intval( $session_data['total_sessions'] ?? 0 );

		// Combined revenue (from clicks and sessions)
		$combined_revenue = $total_revenue + $session_revenue;

		return [
			'campaign_id'      => $campaign_id,
			'total_clicks'     => $total_clicks,
			'unique_clicks'    => $unique_clicks,
			'conversions'      => $conversions,
			'total_revenue'    => $combined_revenue,
			'conversion_rate'  => self::calculate_percentage( $conversions, $unique_clicks ),
			'revenue_per_click' => self::safe_divide( $combined_revenue, $unique_clicks ),
			'total_sessions'   => $total_sessions,
		];
	}

	/**
	 * Calculate campaign ROI
	 *
	 * @param string $campaign_id Campaign ID
	 * @param float  $cost        Campaign cost
	 * @param array  $date_range  Optional date range
	 * @return array ROI data
	 */
	public static function calculate_roi( $campaign_id, $cost, $date_range = [] ) {
		$performance = self::get_campaign_performance( $campaign_id, $date_range );
		$revenue = $performance['total_revenue'];

		$roi = 0;
		if ( $cost > 0 ) {
			$roi = ( ( $revenue - $cost ) / $cost ) * 100;
		}

		return [
			'campaign_id' => $campaign_id,
			'revenue'     => $revenue,
			'cost'        => $cost,
			'profit'      => $revenue - $cost,
			'roi'         => round( $roi, 2 ),
			'roas'        => self::safe_divide( $revenue, $cost ),
		];
	}

	/**
	 * Get revenue per email sent
	 *
	 * @param string $campaign_id  Campaign ID
	 * @param int    $emails_sent  Number of emails sent
	 * @param array  $date_range   Optional date range
	 * @return array Revenue per email data
	 */
	public static function get_revenue_per_email( $campaign_id, $emails_sent, $date_range = [] ) {
		$performance = self::get_campaign_performance( $campaign_id, $date_range );

		return [
			'campaign_id'       => $campaign_id,
			'emails_sent'       => $emails_sent,
			'total_clicks'      => $performance['total_clicks'],
			'unique_clicks'     => $performance['unique_clicks'],
			'conversions'       => $performance['conversions'],
			'total_revenue'     => $performance['total_revenue'],
			'ctr'               => self::calculate_percentage( $performance['unique_clicks'], $emails_sent ),
			'conversion_rate'   => $performance['conversion_rate'],
			'revenue_per_email' => self::safe_divide( $performance['total_revenue'], $emails_sent ),
		];
	}

	/**
	 * Get all campaigns performance
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param array $date_range Optional date range
	 * @param int   $limit      Limit results
	 * @return array Campaigns data
	 */
	public static function get_all_campaigns( $date_range = [], $limit = 50 ) {
		global $wpdb;

		$clicks_table = $wpdb->prefix . 'ds_email_clicks';
		$date_filter = self::build_date_filter( 'clicked_at', $date_range );

		$query = $wpdb->prepare(
			"SELECT 
				campaign_id,
				COUNT(*) as total_clicks,
				COUNT(DISTINCT session_id) as unique_clicks,
				SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions,
				SUM(revenue) as total_revenue,
				MIN(clicked_at) as first_click,
				MAX(clicked_at) as last_click
			FROM {$clicks_table}
			WHERE 1=1 {$date_filter}
			GROUP BY campaign_id
			ORDER BY total_revenue DESC
			LIMIT %d",
			$limit
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Calculate metrics for each campaign
		foreach ( $results as &$campaign ) {
			$unique_clicks = intval( $campaign['unique_clicks'] );
			$conversions = intval( $campaign['conversions'] );
			$revenue = floatval( $campaign['total_revenue'] );

			$campaign['conversion_rate'] = self::calculate_percentage( $conversions, $unique_clicks );
			$campaign['revenue_per_click'] = self::safe_divide( $revenue, $unique_clicks );
		}

		return $results;
	}

	/**
	 * Get customer acquisition cost (CAC)
	 *
	 * @param string $campaign_id Campaign ID
	 * @param float  $cost        Campaign cost
	 * @param array  $date_range  Optional date range
	 * @return array CAC data
	 */
	public static function get_cac( $campaign_id, $cost, $date_range = [] ) {
		$performance = self::get_campaign_performance( $campaign_id, $date_range );
		$conversions = $performance['conversions'];

		return [
			'campaign_id' => $campaign_id,
			'cost'        => $cost,
			'conversions' => $conversions,
			'cac'         => self::safe_divide( $cost, $conversions ),
		];
	}

	/**
	 * Get time-to-conversion metrics
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param string $campaign_id Campaign ID
	 * @return array Time metrics
	 */
	public static function get_time_to_conversion( $campaign_id ) {
		global $wpdb;

		$clicks_table = $wpdb->prefix . 'ds_email_clicks';

		$query = $wpdb->prepare(
			"SELECT 
				AVG(TIMESTAMPDIFF(MINUTE, clicked_at, NOW())) as avg_minutes,
				MIN(TIMESTAMPDIFF(MINUTE, clicked_at, NOW())) as min_minutes,
				MAX(TIMESTAMPDIFF(MINUTE, clicked_at, NOW())) as max_minutes
			FROM {$clicks_table}
			WHERE campaign_id = %s AND converted = 1",
			$campaign_id
		);

		$result = $wpdb->get_row( $query, ARRAY_A );

		return [
			'campaign_id'         => $campaign_id,
			'avg_time_minutes'    => round( floatval( $result['avg_minutes'] ?? 0 ), 2 ),
			'min_time_minutes'    => intval( $result['min_minutes'] ?? 0 ),
			'max_time_minutes'    => intval( $result['max_minutes'] ?? 0 ),
			'avg_time_formatted'  => self::format_minutes( floatval( $result['avg_minutes'] ?? 0 ) ),
		];
	}

	/**
	 * Compare campaign variants (A/B testing)
	 *
	 * @param array $campaign_ids Array of campaign IDs to compare
	 * @param array $date_range   Optional date range
	 * @return array Comparison data
	 */
	public static function compare_campaigns( $campaign_ids, $date_range = [] ) {
		$comparison = [];

		foreach ( $campaign_ids as $campaign_id ) {
			$comparison[] = self::get_campaign_performance( $campaign_id, $date_range );
		}

		return $comparison;
	}

	/**
	 * Build date filter SQL
	 *
	 * @param string $field      Date field name
	 * @param array  $date_range Date range array
	 * @return string SQL WHERE clause
	 */
	private static function build_date_filter( $field, $date_range ) {
		global $wpdb;

		$filter = '';

		if ( ! empty( $date_range['start'] ) ) {
			$start = sanitize_text_field( $date_range['start'] );
			$filter .= $wpdb->prepare( " AND {$field} >= %s", $start );
		}

		if ( ! empty( $date_range['end'] ) ) {
			$end = sanitize_text_field( $date_range['end'] );
			$filter .= $wpdb->prepare( " AND {$field} <= %s", $end . ' 23:59:59' );
		}

		return $filter;
	}

	/**
	 * Calculate percentage
	 *
	 * @param float $numerator   Numerator
	 * @param float $denominator Denominator
	 * @return float Percentage
	 */
	private static function calculate_percentage( $numerator, $denominator ) {
		if ( $denominator == 0 ) {
			return 0;
		}
		return round( ( $numerator / $denominator ) * 100, 2 );
	}

	/**
	 * Safe division
	 *
	 * @param float $numerator   Numerator
	 * @param float $denominator Denominator
	 * @return float Result
	 */
	private static function safe_divide( $numerator, $denominator ) {
		if ( $denominator == 0 ) {
			return 0;
		}
		return round( $numerator / $denominator, 2 );
	}

	/**
	 * Format minutes to human-readable string
	 *
	 * @param float $minutes Minutes
	 * @return string Formatted time
	 */
	private static function format_minutes( $minutes ) {
		if ( $minutes < 60 ) {
			return round( $minutes ) . ' minutes';
		}

		$hours = floor( $minutes / 60 );
		$mins = round( $minutes % 60 );

		if ( $hours < 24 ) {
			return "{$hours}h {$mins}m";
		}

		$days = floor( $hours / 24 );
		$remaining_hours = $hours % 24;

		return "{$days}d {$remaining_hours}h";
	}
}
