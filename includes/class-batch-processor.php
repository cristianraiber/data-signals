<?php
/**
 * Batch insert optimization for high-volume tracking.
 *
 * @package DataSignals
 */

namespace DataSignals;

defined( 'ABSPATH' ) || exit;

/**
 * Batch_Processor class.
 */
class Batch_Processor {
	/**
	 * Batch size (number of records to insert per query).
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 100;

	/**
	 * Queue option name.
	 *
	 * @var string
	 */
	private const QUEUE_OPTION = 'data_signals_batch_queue';

	/**
	 * Maximum queue size before auto-processing.
	 *
	 * @var int
	 */
	private const MAX_QUEUE_SIZE = 500;

	/**
	 * Add item to batch queue.
	 *
	 * @param string               $type Type of record ('pageview' or 'event').
	 * @param array<string, mixed> $data Record data.
	 * @return void
	 */
	public static function add_to_queue( string $type, array $data ): void {
		$queue = get_option( self::QUEUE_OPTION, array( 'pageviews' => array(), 'events' => array() ) );

		if ( ! is_array( $queue ) ) {
			$queue = array( 'pageviews' => array(), 'events' => array() );
		}

		// Add to appropriate queue.
		if ( $type === 'pageview' ) {
			$queue['pageviews'][] = $data;
		} elseif ( $type === 'event' ) {
			$queue['events'][] = $data;
		}

		update_option( self::QUEUE_OPTION, $queue, false );

		// Auto-process if queue is too large.
		$total_size = count( $queue['pageviews'] ) + count( $queue['events'] );
		if ( $total_size >= self::MAX_QUEUE_SIZE ) {
			self::process_queue();
		}
	}

	/**
	 * Process the batch queue.
	 *
	 * @return array<string, int> Number of records processed.
	 */
	public static function process_queue(): array {
		global $wpdb;

		$queue = get_option( self::QUEUE_OPTION, array( 'pageviews' => array(), 'events' => array() ) );

		if ( ! is_array( $queue ) ) {
			$queue = array( 'pageviews' => array(), 'events' => array() );
		}

		$processed = array(
			'pageviews' => 0,
			'events'    => 0,
		);

		// Process pageviews.
		if ( ! empty( $queue['pageviews'] ) ) {
			$processed['pageviews'] = self::batch_insert_pageviews( $queue['pageviews'] );
		}

		// Process events.
		if ( ! empty( $queue['events'] ) ) {
			$processed['events'] = self::batch_insert_events( $queue['events'] );
		}

		// Clear queue.
		update_option( self::QUEUE_OPTION, array( 'pageviews' => array(), 'events' => array() ), false );

		return $processed;
	}

	/**
	 * Batch insert pageviews.
	 *
	 * @param array<int, array<string, mixed>> $pageviews Array of pageview records.
	 * @return int Number of records inserted.
	 */
	private static function batch_insert_pageviews( array $pageviews ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_pageviews';
		$inserted   = 0;

		// Process in batches.
		$batches = array_chunk( $pageviews, self::BATCH_SIZE );

		foreach ( $batches as $batch ) {
			$values       = array();
			$placeholders = array();

			foreach ( $batch as $record ) {
				$placeholders[] = '(%s, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s)';
				$values[]       = $record['session_id'];
				$values[]       = $record['page_id'];
				$values[]       = $record['url'];
				$values[]       = $record['referrer'];
				$values[]       = $record['utm_source'];
				$values[]       = $record['utm_medium'];
				$values[]       = $record['utm_campaign'];
				$values[]       = $record['utm_content'];
				$values[]       = $record['utm_term'];
				$values[]       = $record['country_code'];
				$values[]       = $record['created_at'];
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = "INSERT INTO {$table_name} 
				(session_id, page_id, url, referrer, utm_source, utm_medium, utm_campaign, utm_content, utm_term, country_code, created_at) 
				VALUES " . implode( ', ', $placeholders );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$result = $wpdb->query( $wpdb->prepare( $query, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

			if ( $result ) {
				$inserted += $result;
			}
		}

		return $inserted;
	}

	/**
	 * Batch insert events.
	 *
	 * @param array<int, array<string, mixed>> $events Array of event records.
	 * @return int Number of records inserted.
	 */
	private static function batch_insert_events( array $events ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_events';
		$inserted   = 0;

		// Process in batches.
		$batches = array_chunk( $events, self::BATCH_SIZE );

		foreach ( $batches as $batch ) {
			$values       = array();
			$placeholders = array();

			foreach ( $batch as $record ) {
				$placeholders[] = '(%s, %s, %f, %d, %d, %s, %s)';
				$values[]       = $record['session_id'];
				$values[]       = $record['event_type'];
				$values[]       = $record['event_value'];
				$values[]       = $record['page_id'];
				$values[]       = $record['product_id'];
				$values[]       = $record['metadata'];
				$values[]       = $record['created_at'];
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = "INSERT INTO {$table_name} 
				(session_id, event_type, event_value, page_id, product_id, metadata, created_at) 
				VALUES " . implode( ', ', $placeholders );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$result = $wpdb->query( $wpdb->prepare( $query, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

			if ( $result ) {
				$inserted += $result;
			}
		}

		return $inserted;
	}

	/**
	 * Get queue size.
	 *
	 * @return array<string, int>
	 */
	public static function get_queue_size(): array {
		$queue = get_option( self::QUEUE_OPTION, array( 'pageviews' => array(), 'events' => array() ) );

		if ( ! is_array( $queue ) ) {
			return array(
				'pageviews' => 0,
				'events'    => 0,
				'total'     => 0,
			);
		}

		return array(
			'pageviews' => count( $queue['pageviews'] ),
			'events'    => count( $queue['events'] ),
			'total'     => count( $queue['pageviews'] ) + count( $queue['events'] ),
		);
	}
}
