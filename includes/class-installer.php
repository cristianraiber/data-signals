<?php
/**
 * Plugin installation and database schema.
 *
 * @package DataSignals
 */

namespace DataSignals;

defined( 'ABSPATH' ) || exit;

/**
 * Installer class.
 */
class Installer {
	/**
	 * Run installation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_tables();
		self::create_initial_partitions();
		self::schedule_cron_events();
		self::update_db_version();
	}

	/**
	 * Create database tables.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_prefix    = $wpdb->prefix;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// 1. Pageviews table (partitioned by month).
		$sql_pageviews = "CREATE TABLE IF NOT EXISTS {$table_prefix}ds_pageviews (
			id BIGINT UNSIGNED AUTO_INCREMENT,
			session_id CHAR(64) NOT NULL,
			page_id BIGINT UNSIGNED DEFAULT NULL,
			url VARCHAR(500) NOT NULL,
			referrer VARCHAR(500) DEFAULT NULL,
			utm_source VARCHAR(100) DEFAULT NULL,
			utm_medium VARCHAR(100) DEFAULT NULL,
			utm_campaign VARCHAR(100) DEFAULT NULL,
			utm_content VARCHAR(100) DEFAULT NULL,
			utm_term VARCHAR(100) DEFAULT NULL,
			country_code CHAR(2) DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id, created_at),
			INDEX idx_session (session_id, created_at),
			INDEX idx_page (page_id, created_at),
			INDEX idx_utm_campaign (utm_campaign, created_at),
			INDEX idx_url (url(255), created_at)
		) $charset_collate
		PARTITION BY RANGE (TO_DAYS(created_at)) (
			PARTITION p202601 VALUES LESS THAN (TO_DAYS('2026-02-01')),
			PARTITION p202602 VALUES LESS THAN (TO_DAYS('2026-03-01')),
			PARTITION p_future VALUES LESS THAN MAXVALUE
		);";

		// 2. Events table.
		$sql_events = "CREATE TABLE IF NOT EXISTS {$table_prefix}ds_events (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			session_id CHAR(64) NOT NULL,
			event_type VARCHAR(50) NOT NULL,
			event_value DECIMAL(10,2) DEFAULT 0.00,
			page_id BIGINT UNSIGNED DEFAULT NULL,
			product_id BIGINT UNSIGNED DEFAULT NULL,
			metadata JSON DEFAULT NULL,
			created_at DATETIME NOT NULL,
			INDEX idx_session (session_id),
			INDEX idx_type (event_type, created_at),
			INDEX idx_product (product_id, created_at),
			INDEX idx_created (created_at)
		) $charset_collate;";

		// 3. Sessions table.
		$sql_sessions = "CREATE TABLE IF NOT EXISTS {$table_prefix}ds_sessions (
			session_id CHAR(64) PRIMARY KEY,
			first_page_id BIGINT UNSIGNED DEFAULT NULL,
			first_referrer VARCHAR(500) DEFAULT NULL,
			utm_source VARCHAR(100) DEFAULT NULL,
			utm_medium VARCHAR(100) DEFAULT NULL,
			utm_campaign VARCHAR(100) DEFAULT NULL,
			country_code CHAR(2) DEFAULT NULL,
			total_pageviews SMALLINT UNSIGNED DEFAULT 1,
			total_revenue DECIMAL(10,2) DEFAULT 0.00,
			first_seen DATETIME NOT NULL,
			last_seen DATETIME NOT NULL,
			INDEX idx_campaign (utm_campaign),
			INDEX idx_source (utm_source),
			INDEX idx_revenue (total_revenue DESC),
			INDEX idx_first_seen (first_seen)
		) $charset_collate;";

		// 4. Revenue attribution table.
		$sql_attribution = "CREATE TABLE IF NOT EXISTS {$table_prefix}ds_revenue_attribution (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			order_id BIGINT UNSIGNED NOT NULL,
			session_id CHAR(64) NOT NULL,
			page_id BIGINT UNSIGNED DEFAULT NULL,
			attribution_type ENUM('first_click', 'last_click', 'linear', 'time_decay') NOT NULL,
			revenue_share DECIMAL(10,2) NOT NULL,
			created_at DATETIME NOT NULL,
			INDEX idx_order (order_id),
			INDEX idx_session (session_id),
			INDEX idx_page (page_id, created_at),
			INDEX idx_created (created_at)
		) $charset_collate;";

		// 5. Email clicks table.
		$sql_email_clicks = "CREATE TABLE IF NOT EXISTS {$table_prefix}ds_email_clicks (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			campaign_id VARCHAR(100) NOT NULL,
			link_url VARCHAR(500) NOT NULL,
			session_id CHAR(64) DEFAULT NULL,
			clicked_at DATETIME NOT NULL,
			converted BOOLEAN DEFAULT FALSE,
			revenue DECIMAL(10,2) DEFAULT 0.00,
			INDEX idx_campaign (campaign_id, clicked_at),
			INDEX idx_session (session_id),
			INDEX idx_clicked (clicked_at)
		) $charset_collate;";

		// 6. Aggregates table.
		$sql_aggregates = "CREATE TABLE IF NOT EXISTS {$table_prefix}ds_aggregates (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			date DATE NOT NULL,
			metric_type VARCHAR(50) NOT NULL,
			dimension VARCHAR(100) DEFAULT NULL,
			dimension_value VARCHAR(255) DEFAULT NULL,
			value DECIMAL(15,2) NOT NULL,
			UNIQUE KEY unique_metric (date, metric_type, dimension, dimension_value),
			INDEX idx_date (date, metric_type)
		) $charset_collate;";

		// Execute table creation.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		dbDelta( $sql_pageviews );
		dbDelta( $sql_events );
		dbDelta( $sql_sessions );
		dbDelta( $sql_attribution );
		dbDelta( $sql_email_clicks );
		dbDelta( $sql_aggregates );

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Create initial partitions for the current and next 3 months.
	 *
	 * @return void
	 */
	private static function create_initial_partitions(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_pageviews';

		// Get existing partitions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT PARTITION_NAME 
				FROM INFORMATION_SCHEMA.PARTITIONS 
				WHERE TABLE_SCHEMA = %s 
				AND TABLE_NAME = %s 
				AND PARTITION_NAME != 'p_future'",
				DB_NAME,
				$table_name
			),
			ARRAY_A
		);

		$existing_partitions = array_column( $existing, 'PARTITION_NAME' );

		// Create partitions for next 3 months if they don't exist.
		for ( $i = 0; $i < 3; $i++ ) {
			$date           = new \DateTime( 'first day of this month' );
			$date->modify( "+{$i} month" );
			$partition_name = 'p' . $date->format( 'Ym' );

			if ( in_array( $partition_name, $existing_partitions, true ) ) {
				continue;
			}

			$next_month = clone $date;
			$next_month->modify( '+1 month' );
			$next_month_value = "TO_DAYS('" . $next_month->format( 'Y-m-d' ) . "')";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"ALTER TABLE {$table_name} 
				REORGANIZE PARTITION p_future INTO (
					PARTITION {$partition_name} VALUES LESS THAN ({$next_month_value}),
					PARTITION p_future VALUES LESS THAN MAXVALUE
				)"
			);
		}
	}

	/**
	 * Schedule cron events.
	 *
	 * @return void
	 */
	private static function schedule_cron_events(): void {
		if ( ! wp_next_scheduled( 'data_signals_aggregate_stats' ) ) {
			wp_schedule_event( time(), 'hourly', 'data_signals_aggregate_stats' );
		}

		if ( ! wp_next_scheduled( 'data_signals_create_partitions' ) ) {
			wp_schedule_event( time(), 'daily', 'data_signals_create_partitions' );
		}

		if ( ! wp_next_scheduled( 'data_signals_process_batch' ) ) {
			wp_schedule_event( time(), 'five_minutes', 'data_signals_process_batch' );
		}
	}

	/**
	 * Update database version.
	 *
	 * @return void
	 */
	private static function update_db_version(): void {
		update_option( 'data_signals_db_version', DATA_SIGNALS_DB_VERSION );
	}

	/**
	 * Check if database needs upgrade.
	 *
	 * @return bool
	 */
	public static function needs_upgrade(): bool {
		$current_version = get_option( 'data_signals_db_version', '0' );
		return version_compare( $current_version, DATA_SIGNALS_DB_VERSION, '<' );
	}

	/**
	 * Run database migration.
	 *
	 * @return void
	 */
	public static function migrate(): void {
		$current_version = get_option( 'data_signals_db_version', '0' );

		// Future migrations can be added here.
		// Example:
		// if ( version_compare( $current_version, '1.1.0', '<' ) ) {
		//     self::migrate_to_1_1_0();
		// }

		self::update_db_version();
	}
}
