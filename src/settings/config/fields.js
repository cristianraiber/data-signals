/**
 * Data Signals Field Definitions
 * All field configurations for the admin settings
 */

import { __ } from '@wordpress/i18n';

/**
 * GDPR info items shown when enabled
 */
export const gdprInfoItems = [
	__( 'IP addresses are anonymized before processing', 'data-signals' ),
	__( 'Do Not Track (DNT) browser header is respected', 'data-signals' ),
	__( 'No cookies are used (fingerprint-based sessions)', 'data-signals' ),
	__( 'Session data rotates daily for privacy', 'data-signals' ),
	__( 'No personal identifiable information is stored', 'data-signals' ),
];

/**
 * Field definitions for Tracking settings (includes retention & dashboard)
 * Note: User role exclusion toggles are generated dynamically from _user_roles
 */
export const trackingFields = [
	{
		id: 'prune_data_after_months',
		label: __( 'Data Retention Period', 'data-signals' ),
		type: 'text',
		elements: [
			{ value: '6', label: __( '6 months', 'data-signals' ) },
			{ value: '12', label: __( '1 year', 'data-signals' ) },
			{ value: '24', label: __( '2 years', 'data-signals' ) },
			{ value: '36', label: __( '3 years', 'data-signals' ) },
			{ value: '0', label: __( 'Forever', 'data-signals' ) },
		],
		description: __(
			'How long to keep analytics data before automatic deletion.',
			'data-signals'
		),
	},
	{
		id: 'default_view',
		label: __( 'Default Date Range', 'data-signals' ),
		type: 'text',
		elements: [
			{ value: 'last_7_days', label: __( 'Last 7 days', 'data-signals' ) },
			{ value: 'last_14_days', label: __( 'Last 14 days', 'data-signals' ) },
			{ value: 'last_28_days', label: __( 'Last 28 days', 'data-signals' ) },
			{ value: 'this_month', label: __( 'This month', 'data-signals' ) },
		],
		description: __(
			'Default time period shown on the dashboard.',
			'data-signals'
		),
	},
	{
		id: 'is_dashboard_public',
		label: __( 'Public Dashboard', 'data-signals' ),
		type: 'boolean',
		Edit: 'toggle',
		description: __(
			'Allow anyone with the REST API endpoint to view stats (no auth required).',
			'data-signals'
		),
	},
];

/**
 * Field definitions for Geolocation settings
 */
export const geoFields = [
	{
		id: 'geo_method',
		label: __( 'Geolocation Method', 'data-signals' ),
		type: 'radio',
		elements: [
			{ value: 'geolite2', label: __( 'GeoLite2 Database (recommended)', 'data-signals' ) },
			{ value: 'cloudflare', label: __( 'Cloudflare IP Geolocation', 'data-signals' ) },
			{ value: 'none', label: __( 'Disabled', 'data-signals' ) },
		],
		description: __(
			'Choose how to detect visitor country. GeoLite2 requires MaxMind license key.',
			'data-signals'
		),
	},
	{
		id: 'maxmind_license_key',
		label: __( 'MaxMind License Key', 'data-signals' ),
		type: 'text',
		Edit: 'input',
		description: __(
			'Free license key from maxmind.com. Required for GeoLite2 database downloads.',
			'data-signals'
		),
		placeholder: 'xxxxxxxxxxxxxxxx',
	},
	{
		id: 'geolite2_db_path',
		label: __( 'GeoLite2 Database Path (Optional)', 'data-signals' ),
		type: 'text',
		Edit: 'input',
		description: __(
			'Custom path to GeoLite2-Country.mmdb. Leave empty for automatic download.',
			'data-signals'
		),
	},
];

// Data Retention and Dashboard fields are now merged into trackingFields
