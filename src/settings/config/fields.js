/**
 * Data Signals Field Definitions
 * All field configurations for the admin settings
 */

import { __ } from '@wordpress/i18n';

/**
 * Field definitions for Tracking settings
 */
export const trackingFields = [
	{
		id: 'exclude_administrators',
		label: __( 'Exclude Logged-in Admins', 'data-signals' ),
		type: 'boolean',
		Edit: 'toggle',
		description: __(
			'Do not track pageviews from administrators.',
			'data-signals'
		),
	},
	{
		id: 'exclude_editors',
		label: __( 'Exclude Editors', 'data-signals' ),
		type: 'boolean',
		Edit: 'toggle',
		description: __(
			'Do not track pageviews from editors.',
			'data-signals'
		),
	},
];

/**
 * Field definitions for Geolocation settings
 */
export const geoFields = [
	{
		id: 'geo_use_cloudflare',
		label: __( 'Use Cloudflare IP Geolocation', 'data-signals' ),
		type: 'boolean',
		Edit: 'toggle',
		description: __(
			'Use Cloudflare CF-IPCountry header if available. Requires IP Geolocation enabled in Cloudflare.',
			'data-signals'
		),
	},
	{
		id: 'geo_api_fallback',
		label: __( 'Enable API Fallback (Dev Only)', 'data-signals' ),
		type: 'boolean',
		Edit: 'toggle',
		description: __(
			'Fall back to ip-api.com when GeoLite2 unavailable. Rate limited (45 req/min), not for production.',
			'data-signals'
		),
	},
	{
		id: 'geolite2_db_path',
		label: __( 'GeoLite2 Database Path', 'data-signals' ),
		type: 'text',
		Edit: 'input',
		description: __(
			'Custom path to GeoLite2-Country.mmdb. Leave empty to use default location (wp-content/uploads/data-signals/).',
			'data-signals'
		),
	},
];

/**
 * Field definitions for Data Retention settings
 */
export const retentionFields = [
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
];

/**
 * Field definitions for Dashboard settings
 */
export const dashboardFields = [
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
