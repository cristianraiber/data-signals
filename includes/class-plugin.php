<?php
/**
 * Main plugin controller.
 *
 * @package DataSignals
 */

namespace DataSignals;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin class (Singleton).
 */
class Plugin {
	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Tracker instance.
	 *
	 * @var Tracker|null
	 */
	private ?Tracker $tracker = null;

	/**
	 * Get plugin instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
		$this->check_db_version();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Cron jobs.
		add_action( 'data_signals_aggregate_stats', array( $this, 'aggregate_stats' ) );
		add_action( 'data_signals_create_partitions', array( $this, 'create_partitions' ) );
		add_action( 'data_signals_process_batch', array( Batch_Processor::class, 'process_queue' ) );

		// Admin.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add custom cron schedule.
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
	}

	/**
	 * Check database version and run migrations if needed.
	 *
	 * @return void
	 */
	private function check_db_version(): void {
		if ( Installer::needs_upgrade() ) {
			Installer::migrate();
		}
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'data-signals/v1',
			'/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_track_request' ),
				'permission_callback' => '__return_true', // Public endpoint.
				'args'                => array(
					'type' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'pageview', 'event' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'data' => array(
						'required' => true,
						'type'     => 'object',
					),
				),
			)
		);

		// Stats endpoint (admin only).
		register_rest_route(
			'data-signals/v1',
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_stats_request' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Handle track request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_track_request( \WP_REST_Request $request ): \WP_REST_Response {
		$type = $request->get_param( 'type' );
		$data = $request->get_param( 'data' );

		if ( ! $this->tracker ) {
			$this->tracker = new Tracker();
		}

		$success = false;

		if ( $type === 'pageview' ) {
			$success = $this->tracker->track_pageview( $data );
		} elseif ( $type === 'event' ) {
			$event_type = isset( $data['event_type'] ) ? sanitize_text_field( $data['event_type'] ) : '';
			$success    = $this->tracker->track_event( $event_type, $data );
		}

		return new \WP_REST_Response(
			array(
				'success' => $success,
				'session' => $this->tracker->get_session_id(),
			),
			200
		);
	}

	/**
	 * Handle stats request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_stats_request( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		// Get queue size.
		$queue_size = Batch_Processor::get_queue_size();

		// Get table counts.
		$pageviews_table = $wpdb->prefix . 'ds_pageviews';
		$events_table    = $wpdb->prefix . 'ds_events';
		$sessions_table  = $wpdb->prefix . 'ds_sessions';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$pageviews_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$pageviews_table}" );
		$events_count    = $wpdb->get_var( "SELECT COUNT(*) FROM {$events_table}" );
		$sessions_count  = $wpdb->get_var( "SELECT COUNT(*) FROM {$sessions_table}" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return new \WP_REST_Response(
			array(
				'queue'     => $queue_size,
				'totals'    => array(
					'pageviews' => (int) $pageviews_count,
					'events'    => (int) $events_count,
					'sessions'  => (int) $sessions_count,
				),
				'db_version' => get_option( 'data_signals_db_version', '0' ),
			),
			200
		);
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'Data Signals', 'data-signals' ),
			__( 'Data Signals', 'data-signals' ),
			'manage_options',
			'data-signals',
			array( $this, 'render_admin_page' ),
			'dashicons-chart-line',
			30
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		echo '<div id="data-signals-app"></div>';
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		if ( $hook !== 'toplevel_page_data-signals' ) {
			return;
		}

		// TODO: Enqueue React app (will be created in Task 5).
		wp_enqueue_style( 'data-signals-admin', DATA_SIGNALS_PLUGIN_URL . 'assets/css/admin.css', array(), DATA_SIGNALS_VERSION );
	}

	/**
	 * Aggregate stats (cron job).
	 *
	 * @return void
	 */
	public function aggregate_stats(): void {
		// TODO: Implement daily aggregation logic.
		// Will compute daily metrics and store in wp_ds_aggregates table.
	}

	/**
	 * Create partitions (cron job).
	 *
	 * @return void
	 */
	public function create_partitions(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ds_pageviews';

		// Create partition for next month.
		$date           = new \DateTime( 'first day of next month' );
		$partition_name = 'p' . $date->format( 'Ym' );

		$next_month       = clone $date;
		$next_month->modify( '+1 month' );
		$next_month_value = "TO_DAYS('" . $next_month->format( 'Y-m-d' ) . "')";

		// Check if partition already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT PARTITION_NAME 
				FROM INFORMATION_SCHEMA.PARTITIONS 
				WHERE TABLE_SCHEMA = %s 
				AND TABLE_NAME = %s 
				AND PARTITION_NAME = %s",
				DB_NAME,
				$table_name,
				$partition_name
			)
		);

		if ( ! $exists ) {
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
	 * Add custom cron schedules.
	 *
	 * @param array<string, array<string, int|string>> $schedules Existing schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public function add_cron_schedules( array $schedules ): array {
		$schedules['five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes', 'data-signals' ),
		);

		return $schedules;
	}
}
