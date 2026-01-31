<?php
/**
 * Link Tracker
 *
 * Track individual link performance within email campaigns.
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals;

defined( 'ABSPATH' ) || exit;

class Link_Tracker {

	/**
	 * Get link performance for a campaign
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param string $campaign_id Campaign ID
	 * @param array  $date_range  Optional date range
	 * @return array Link performance data
	 */
	public static function get_campaign_links( $campaign_id, $date_range = [] ) {
		global $wpdb;

		$clicks_table = $wpdb->prefix . 'ds_email_clicks';
		$date_filter = self::build_date_filter( 'clicked_at', $date_range );

		$query = $wpdb->prepare(
			"SELECT 
				link_url,
				COUNT(*) as total_clicks,
				COUNT(DISTINCT session_id) as unique_clicks,
				SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions,
				SUM(revenue) as total_revenue,
				MIN(clicked_at) as first_click,
				MAX(clicked_at) as last_click
			FROM {$clicks_table}
			WHERE campaign_id = %s {$date_filter}
			GROUP BY link_url
			ORDER BY total_clicks DESC",
			$campaign_id
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Calculate metrics for each link
		foreach ( $results as &$link ) {
			$unique_clicks = intval( $link['unique_clicks'] );
			$conversions = intval( $link['conversions'] );
			$revenue = floatval( $link['total_revenue'] );

			$link['conversion_rate'] = self::calculate_percentage( $conversions, $unique_clicks );
			$link['revenue_per_click'] = self::safe_divide( $revenue, $unique_clicks );
			$link['link_label'] = self::extract_link_label( $link['link_url'] );
		}

		return $results;
	}

	/**
	 * Get best performing links across all campaigns
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param string $metric     Metric to sort by (clicks|conversions|revenue)
	 * @param int    $limit      Limit results
	 * @param array  $date_range Optional date range
	 * @return array Top links
	 */
	public static function get_top_links( $metric = 'revenue', $limit = 10, $date_range = [] ) {
		global $wpdb;

		$clicks_table = $wpdb->prefix . 'ds_email_clicks';
		$date_filter = self::build_date_filter( 'clicked_at', $date_range );

		// Determine sort column
		$sort_column = match ( $metric ) {
			'clicks'      => 'total_clicks',
			'conversions' => 'conversions',
			default       => 'total_revenue',
		};

		$query = $wpdb->prepare(
			"SELECT 
				link_url,
				campaign_id,
				COUNT(*) as total_clicks,
				COUNT(DISTINCT session_id) as unique_clicks,
				SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions,
				SUM(revenue) as total_revenue
			FROM {$clicks_table}
			WHERE 1=1 {$date_filter}
			GROUP BY link_url, campaign_id
			ORDER BY {$sort_column} DESC
			LIMIT %d",
			$limit
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		foreach ( $results as &$link ) {
			$link['conversion_rate'] = self::calculate_percentage(
				intval( $link['conversions'] ),
				intval( $link['unique_clicks'] )
			);
			$link['link_label'] = self::extract_link_label( $link['link_url'] );
		}

		return $results;
	}

	/**
	 * Get CTA performance comparison
	 *
	 * @param string $campaign_id Campaign ID
	 * @param array  $cta_urls    Array of CTA URLs to compare
	 * @param array  $date_range  Optional date range
	 * @return array CTA comparison data
	 */
	public static function compare_ctas( $campaign_id, $cta_urls, $date_range = [] ) {
		$comparison = [];

		foreach ( $cta_urls as $url ) {
			$performance = self::get_link_performance( $campaign_id, $url, $date_range );
			$comparison[] = $performance;
		}

		return $comparison;
	}

	/**
	 * Get performance for a specific link
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param string $campaign_id Campaign ID
	 * @param string $link_url    Link URL
	 * @param array  $date_range  Optional date range
	 * @return array Link performance data
	 */
	public static function get_link_performance( $campaign_id, $link_url, $date_range = [] ) {
		global $wpdb;

		$clicks_table = $wpdb->prefix . 'ds_email_clicks';
		$date_filter = self::build_date_filter( 'clicked_at', $date_range );

		$query = $wpdb->prepare(
			"SELECT 
				COUNT(*) as total_clicks,
				COUNT(DISTINCT session_id) as unique_clicks,
				SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions,
				SUM(revenue) as total_revenue
			FROM {$clicks_table}
			WHERE campaign_id = %s AND link_url = %s {$date_filter}",
			$campaign_id,
			$link_url
		);

		$result = $wpdb->get_row( $query, ARRAY_A );

		$unique_clicks = intval( $result['unique_clicks'] ?? 0 );
		$conversions = intval( $result['conversions'] ?? 0 );
		$revenue = floatval( $result['total_revenue'] ?? 0 );

		return [
			'campaign_id'       => $campaign_id,
			'link_url'          => $link_url,
			'link_label'        => self::extract_link_label( $link_url ),
			'total_clicks'      => intval( $result['total_clicks'] ?? 0 ),
			'unique_clicks'     => $unique_clicks,
			'conversions'       => $conversions,
			'total_revenue'     => $revenue,
			'conversion_rate'   => self::calculate_percentage( $conversions, $unique_clicks ),
			'revenue_per_click' => self::safe_divide( $revenue, $unique_clicks ),
		];
	}

	/**
	 * Get link click timeline
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param string $campaign_id Campaign ID
	 * @param string $link_url    Link URL
	 * @param string $interval    Interval (hour|day|week)
	 * @return array Timeline data
	 */
	public static function get_link_timeline( $campaign_id, $link_url, $interval = 'day' ) {
		global $wpdb;

		$clicks_table = $wpdb->prefix . 'ds_email_clicks';

		// Determine date format based on interval
		$date_format = match ( $interval ) {
			'hour' => '%Y-%m-%d %H:00:00',
			'week' => '%Y-%U',
			default => '%Y-%m-%d',
		};

		$query = $wpdb->prepare(
			"SELECT 
				DATE_FORMAT(clicked_at, %s) as time_period,
				COUNT(*) as clicks,
				SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions,
				SUM(revenue) as revenue
			FROM {$clicks_table}
			WHERE campaign_id = %s AND link_url = %s
			GROUP BY time_period
			ORDER BY time_period ASC",
			$date_format,
			$campaign_id,
			$link_url
		);

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get link attribution (which links drove sales)
	 *
	 * @global wpdb $wpdb WordPress database abstraction object
	 * @param string $campaign_id Campaign ID
	 * @param array  $date_range  Optional date range
	 * @return array Attribution data
	 */
	public static function get_link_attribution( $campaign_id, $date_range = [] ) {
		global $wpdb;

		$clicks_table = $wpdb->prefix . 'ds_email_clicks';
		$date_filter = self::build_date_filter( 'clicked_at', $date_range );

		$query = $wpdb->prepare(
			"SELECT 
				link_url,
				COUNT(CASE WHEN converted = 1 THEN 1 END) as sales_count,
				SUM(CASE WHEN converted = 1 THEN revenue ELSE 0 END) as sales_revenue,
				COUNT(*) as total_clicks,
				(COUNT(CASE WHEN converted = 1 THEN 1 END) / COUNT(*)) * 100 as conversion_rate
			FROM {$clicks_table}
			WHERE campaign_id = %s {$date_filter}
			GROUP BY link_url
			HAVING sales_count > 0
			ORDER BY sales_revenue DESC",
			$campaign_id
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		foreach ( $results as &$link ) {
			$link['link_label'] = self::extract_link_label( $link['link_url'] );
		}

		return $results;
	}

	/**
	 * Extract readable label from URL
	 *
	 * @param string $url URL
	 * @return string Label
	 */
	private static function extract_link_label( $url ) {
		$parsed = wp_parse_url( $url );
		$path = $parsed['path'] ?? '';

		// Remove leading/trailing slashes
		$path = trim( $path, '/' );

		// If path is empty, use domain
		if ( empty( $path ) ) {
			return $parsed['host'] ?? $url;
		}

		// Get last segment
		$segments = explode( '/', $path );
		$last_segment = end( $segments );

		// Clean up the segment
		$label = str_replace( [ '-', '_' ], ' ', $last_segment );
		$label = ucwords( $label );

		return $label ?: 'Homepage';
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

		// Whitelist valid field names to prevent SQL injection
		$allowed_fields = array( 'clicked_at', 'created_at', 'timestamp', 'date', 'first_seen', 'last_seen' );
		if ( ! in_array( $field, $allowed_fields, true ) ) {
			return ''; // Invalid field, return empty filter
		}

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
}
