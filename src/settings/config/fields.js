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
		id: 'exclude_admins',
		label: __( 'Exclude Logged-in Admins', 'data-signals' ),
		type: 'boolean',
		Edit: 'toggle',
		description: __(
			'Do not track pageviews from administrators.',
			'data-signals'
		),
	},
	{
		id: 'exclude_bots',
		label: __( 'Exclude Known Bots', 'data-signals' ),
		type: 'boolean',
		Edit: 'toggle',
		description: __(
			'Filter out known search engine crawlers and bots.',
			'data-signals'
		),
	},
	{
		id: 'honor_dnt',
		label: __( 'Honor Do Not Track', 'data-signals' ),
		type: 'boolean',
		Edit: 'toggle',
		description: __(
			'Respect the browser Do Not Track (DNT) setting.',
			'data-signals'
		),
	},
];

/**
 * Field definitions for Data Retention settings
 */
export const retentionFields = [
	{
		id: 'data_retention_days',
		label: __( 'Data Retention Period', 'data-signals' ),
		type: 'text',
		elements: [
			{ value: '30', label: __( '30 days', 'data-signals' ) },
			{ value: '60', label: __( '60 days', 'data-signals' ) },
			{ value: '90', label: __( '90 days', 'data-signals' ) },
			{ value: '180', label: __( '180 days', 'data-signals' ) },
			{ value: '365', label: __( '1 year', 'data-signals' ) },
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
		id: 'default_period',
		label: __( 'Default Date Range', 'data-signals' ),
		type: 'text',
		elements: [
			{ value: '7', label: __( 'Last 7 days', 'data-signals' ) },
			{ value: '30', label: __( 'Last 30 days', 'data-signals' ) },
			{ value: '90', label: __( 'Last 90 days', 'data-signals' ) },
		],
		description: __(
			'Default time period shown on the dashboard.',
			'data-signals'
		),
	},
];
