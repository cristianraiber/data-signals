<?php
/**
 * Google Search Console Settings Page
 *
 * Admin settings interface for GSC integration
 *
 * @package DataSignals
 * @since 1.0.0
 */

namespace DataSignals\Integrations;

use DataSignals\OAuth_Manager;
use DataSignals\Keyword_Analyzer;
use DataSignals\SEO_Revenue_Estimator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GSC_Settings
 */
class GSC_Settings {

	/**
	 * OAuth Manager instance
	 *
	 * @var OAuth_Manager
	 */
	private $oauth_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->oauth_manager = new OAuth_Manager();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add settings page to admin menu
	 */
	public function add_settings_page(): void {
		add_submenu_page(
			'data-signals',
			__( 'Google Search Console', 'data-signals' ),
			__( 'Search Console', 'data-signals' ),
			'manage_options',
			'data-signals-gsc',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings(): void {
		register_setting( 'ds_gsc_settings', 'ds_gsc_client_id' );
		register_setting( 'ds_gsc_settings', 'ds_gsc_client_secret' );
		register_setting( 'ds_gsc_settings', 'ds_gsc_property_url' );

		add_settings_section(
			'ds_gsc_api_credentials',
			__( 'API Credentials', 'data-signals' ),
			array( $this, 'render_api_credentials_section' ),
			'ds_gsc_settings'
		);

		add_settings_field(
			'ds_gsc_client_id',
			__( 'Client ID', 'data-signals' ),
			array( $this, 'render_client_id_field' ),
			'ds_gsc_settings',
			'ds_gsc_api_credentials'
		);

		add_settings_field(
			'ds_gsc_client_secret',
			__( 'Client Secret', 'data-signals' ),
			array( $this, 'render_client_secret_field' ),
			'ds_gsc_settings',
			'ds_gsc_api_credentials'
		);

		add_settings_field(
			'ds_gsc_property_url',
			__( 'Property URL', 'data-signals' ),
			array( $this, 'render_property_url_field' ),
			'ds_gsc_settings',
			'ds_gsc_api_credentials'
		);
	}

	/**
	 * Render API credentials section description
	 */
	public function render_api_credentials_section(): void {
		echo '<p>' . esc_html__( 'Enter your Google API credentials. Get them from the Google Cloud Console.', 'data-signals' ) . '</p>';
		echo '<p><a href="https://console.cloud.google.com/" target="_blank">' . esc_html__( 'Go to Google Cloud Console →', 'data-signals' ) . '</a></p>';
	}

	/**
	 * Render client ID field
	 */
	public function render_client_id_field(): void {
		$value = get_option( 'ds_gsc_client_id', '' );
		echo '<input type="text" name="ds_gsc_client_id" value="' . esc_attr( $value ) . '" class="regular-text" />';
	}

	/**
	 * Render client secret field
	 */
	public function render_client_secret_field(): void {
		$value = get_option( 'ds_gsc_client_secret', '' );
		echo '<input type="password" name="ds_gsc_client_secret" value="' . esc_attr( $value ) . '" class="regular-text" />';
	}

	/**
	 * Render property URL field
	 */
	public function render_property_url_field(): void {
		$value = get_option( 'ds_gsc_property_url', home_url() );
		echo '<input type="text" name="ds_gsc_property_url" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Your verified property URL from Search Console (e.g., https://example.com or sc-domain:example.com)', 'data-signals' ) . '</p>';
	}

	/**
	 * Enqueue scripts
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( 'data-signals_page_data-signals-gsc' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'ds-gsc-settings', DATA_SIGNALS_PLUGIN_URL . 'assets/css/gsc-settings.css', array(), DATA_SIGNALS_VERSION );
		wp_enqueue_script( 'ds-gsc-settings', DATA_SIGNALS_PLUGIN_URL . 'assets/js/gsc-settings.js', array( 'jquery' ), DATA_SIGNALS_VERSION, true );

		wp_localize_script(
			'ds-gsc-settings',
			'dsGscSettings',
			array(
				'restUrl'   => rest_url( 'data-signals/v1/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'isAuthorized' => $this->oauth_manager->is_authorized( 'google_search_console' ),
			)
		);
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page(): void {
		$is_authorized = $this->oauth_manager->is_authorized( 'google_search_console' );
		$last_sync     = get_option( 'ds_gsc_last_sync', 0 );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Google Search Console Integration', 'data-signals' ); ?></h1>

			<?php if ( $is_authorized ) : ?>
				<div class="notice notice-success">
					<p>
						<strong><?php esc_html_e( '✓ Connected to Google Search Console', 'data-signals' ); ?></strong>
						<?php if ( $last_sync ) : ?>
							<br>
							<?php
							printf(
								/* translators: %s: Time since last sync */
								esc_html__( 'Last synced: %s', 'data-signals' ),
								esc_html( human_time_diff( $last_sync ) . ' ago' )
							);
							?>
						<?php endif; ?>
					</p>
				</div>
			<?php else : ?>
				<div class="notice notice-warning">
					<p><strong><?php esc_html_e( 'Not connected to Google Search Console', 'data-signals' ); ?></strong></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'ds_gsc_settings' );
				do_settings_sections( 'ds_gsc_settings' );
				submit_button();
				?>
			</form>

			<?php if ( ! empty( get_option( 'ds_gsc_client_id' ) ) && ! empty( get_option( 'ds_gsc_client_secret' ) ) ) : ?>
				<hr>

				<h2><?php esc_html_e( 'Authorization', 'data-signals' ); ?></h2>

				<?php if ( ! $is_authorized ) : ?>
					<p><?php esc_html_e( 'Click the button below to authorize Data Signals to access your Google Search Console data.', 'data-signals' ); ?></p>
					<button type="button" id="ds-gsc-authorize" class="button button-primary">
						<?php esc_html_e( 'Connect to Google Search Console', 'data-signals' ); ?>
					</button>
				<?php else : ?>
					<p><?php esc_html_e( 'Your site is connected. You can disconnect below.', 'data-signals' ); ?></p>
					<button type="button" id="ds-gsc-disconnect" class="button button-secondary">
						<?php esc_html_e( 'Disconnect', 'data-signals' ); ?>
					</button>
					<button type="button" id="ds-gsc-sync-now" class="button">
						<?php esc_html_e( 'Sync Now', 'data-signals' ); ?>
					</button>
				<?php endif; ?>

				<div id="ds-gsc-status" style="margin-top: 15px;"></div>
			<?php endif; ?>

			<?php if ( $is_authorized ) : ?>
				<hr>
				<?php $this->render_dashboard(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render dashboard widgets
	 */
	private function render_dashboard(): void {
		$analyzer  = new Keyword_Analyzer();
		$estimator = new SEO_Revenue_Estimator();

		// Get stats
		$stats       = $analyzer->get_keyword_stats( 30 );
		$seo_value   = $estimator->calculate_seo_value( 30 );
		$money_keywords = $analyzer->get_money_keywords( 30 );
		$position_drops = $analyzer->detect_position_drops( 7 );
		$opportunities  = $analyzer->identify_opportunities( 30 );

		?>
		<h2><?php esc_html_e( 'SEO Performance Dashboard', 'data-signals' ); ?></h2>

		<div class="ds-gsc-dashboard">
			<!-- Stats Overview -->
			<div class="ds-gsc-stats-grid">
				<div class="ds-gsc-stat-card">
					<h3><?php esc_html_e( 'Total SEO Value', 'data-signals' ); ?></h3>
					<p class="ds-stat-value">$<?php echo esc_html( number_format( $seo_value['total_seo_value'], 2 ) ); ?></p>
					<p class="ds-stat-meta"><?php echo esc_html( ucfirst( $seo_value['confidence'] ) ); ?> confidence</p>
				</div>

				<div class="ds-gsc-stat-card">
					<h3><?php esc_html_e( 'Total Keywords', 'data-signals' ); ?></h3>
					<p class="ds-stat-value"><?php echo esc_html( number_format( $stats['total_keywords'] ) ); ?></p>
					<p class="ds-stat-meta"><?php esc_html_e( 'Last 30 days', 'data-signals' ); ?></p>
				</div>

				<div class="ds-gsc-stat-card">
					<h3><?php esc_html_e( 'Total Clicks', 'data-signals' ); ?></h3>
					<p class="ds-stat-value"><?php echo esc_html( number_format( $stats['total_clicks'] ) ); ?></p>
					<p class="ds-stat-meta"><?php echo esc_html( number_format( $stats['total_impressions'] ) ); ?> impressions</p>
				</div>

				<div class="ds-gsc-stat-card">
					<h3><?php esc_html_e( 'Avg Position', 'data-signals' ); ?></h3>
					<p class="ds-stat-value"><?php echo esc_html( number_format( $stats['avg_position'], 1 ) ); ?></p>
					<p class="ds-stat-meta"><?php echo esc_html( number_format( $stats['avg_ctr'] * 100, 2 ) ); ?>% CTR</p>
				</div>
			</div>

			<!-- Top Money Keywords -->
			<div class="ds-gsc-section">
				<h3><?php esc_html_e( 'Top Revenue Keywords', 'data-signals' ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Keyword', 'data-signals' ); ?></th>
							<th><?php esc_html_e( 'Revenue', 'data-signals' ); ?></th>
							<th><?php esc_html_e( 'Clicks', 'data-signals' ); ?></th>
							<th><?php esc_html_e( 'Position', 'data-signals' ); ?></th>
							<th><?php esc_html_e( 'Rev/Click', 'data-signals' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$displayed = 0;
						foreach ( $money_keywords as $kw ) :
							if ( ++$displayed > 10 ) {
								break;
							}
							?>
							<tr>
								<td><strong><?php echo esc_html( $kw['keyword'] ); ?></strong></td>
								<td>$<?php echo esc_html( number_format( $kw['revenue'], 2 ) ); ?></td>
								<td><?php echo esc_html( number_format( $kw['clicks'] ) ); ?></td>
								<td><?php echo esc_html( number_format( $kw['position'], 1 ) ); ?></td>
								<td>$<?php echo esc_html( number_format( $kw['revenue_per_click'], 2 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Position Drops Alert -->
			<?php if ( ! empty( $position_drops ) ) : ?>
				<div class="ds-gsc-section">
					<h3><?php esc_html_e( '⚠️ Position Drops (Last 7 Days)', 'data-signals' ); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Keyword', 'data-signals' ); ?></th>
								<th><?php esc_html_e( 'Previous', 'data-signals' ); ?></th>
								<th><?php esc_html_e( 'Current', 'data-signals' ); ?></th>
								<th><?php esc_html_e( 'Drop', 'data-signals' ); ?></th>
								<th><?php esc_html_e( 'Severity', 'data-signals' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$displayed = 0;
							foreach ( $position_drops as $drop ) :
								if ( ++$displayed > 10 ) {
									break;
								}
								$severity_class = 'ds-severity-' . $drop['severity'];
								?>
								<tr class="<?php echo esc_attr( $severity_class ); ?>">
									<td><strong><?php echo esc_html( $drop['keyword'] ); ?></strong></td>
									<td><?php echo esc_html( number_format( $drop['previous_position'], 1 ) ); ?></td>
									<td><?php echo esc_html( number_format( $drop['recent_position'], 1 ) ); ?></td>
									<td><span class="ds-drop-indicator">↓ <?php echo esc_html( number_format( $drop['position_drop'], 1 ) ); ?></span></td>
									<td><span class="ds-severity-badge ds-severity-<?php echo esc_attr( $drop['severity'] ); ?>"><?php echo esc_html( ucfirst( $drop['severity'] ) ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<!-- Opportunities -->
			<?php if ( ! empty( $opportunities ) ) : ?>
				<div class="ds-gsc-section">
					<h3><?php esc_html_e( '💡 Content Opportunities (High Impressions, Low CTR)', 'data-signals' ); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Keyword', 'data-signals' ); ?></th>
								<th><?php esc_html_e( 'Impressions', 'data-signals' ); ?></th>
								<th><?php esc_html_e( 'Current CTR', 'data-signals' ); ?></th>
								<th><?php esc_html_e( 'Target CTR', 'data-signals' ); ?></th>
								<th><?php esc_html_e( 'Potential Clicks', 'data-signals' ); ?></th>
								<th><?php esc_html_e( 'Score', 'data-signals' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$displayed = 0;
							foreach ( $opportunities as $opp ) :
								if ( ++$displayed > 10 ) {
									break;
								}
								?>
								<tr>
									<td><strong><?php echo esc_html( $opp['keyword'] ); ?></strong></td>
									<td><?php echo esc_html( number_format( $opp['impressions'] ) ); ?></td>
									<td><?php echo esc_html( number_format( $opp['current_ctr'] * 100, 2 ) ); ?>%</td>
									<td><?php echo esc_html( number_format( $opp['target_ctr'] * 100, 2 ) ); ?>%</td>
									<td>+<?php echo esc_html( number_format( $opp['potential_additional_clicks'] ) ); ?></td>
									<td><span class="ds-opportunity-score"><?php echo esc_html( $opp['opportunity_score'] ); ?>/100</span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>

		<style>
			.ds-gsc-stats-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 20px;
				margin: 20px 0;
			}
			.ds-gsc-stat-card {
				background: #fff;
				border: 1px solid #ccd0d4;
				padding: 20px;
				border-radius: 4px;
			}
			.ds-gsc-stat-card h3 {
				margin: 0 0 10px 0;
				font-size: 13px;
				color: #646970;
				text-transform: uppercase;
			}
			.ds-stat-value {
				font-size: 32px;
				font-weight: 600;
				margin: 0;
				color: #1d2327;
			}
			.ds-stat-meta {
				font-size: 12px;
				color: #646970;
				margin: 5px 0 0 0;
			}
			.ds-gsc-section {
				margin: 30px 0;
			}
			.ds-severity-critical { background-color: #fee; }
			.ds-severity-high { background-color: #fff0e0; }
			.ds-severity-medium { background-color: #fffbcc; }
			.ds-severity-badge {
				display: inline-block;
				padding: 3px 8px;
				border-radius: 3px;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
			}
			.ds-severity-badge.ds-severity-critical { background: #d63638; color: #fff; }
			.ds-severity-badge.ds-severity-high { background: #ff8c00; color: #fff; }
			.ds-severity-badge.ds-severity-medium { background: #f0b323; color: #000; }
			.ds-severity-badge.ds-severity-low { background: #ddd; color: #000; }
			.ds-drop-indicator { color: #d63638; font-weight: 600; }
			.ds-opportunity-score { font-weight: 600; color: #2271b1; }
		</style>
		<?php
	}
}
