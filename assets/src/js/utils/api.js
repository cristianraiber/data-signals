/**
 * API utility functions using wp.apiFetch
 */
import apiFetch from '@wordpress/api-fetch';

const API_NAMESPACE = 'data-signals/v1';

/**
 * Get analytics data for a date range
 */
export const getAnalytics = async ({ startDate, endDate }) => {
	return apiFetch({
		path: `${API_NAMESPACE}/analytics?start=${startDate}&end=${endDate}`,
		method: 'GET',
	});
};

/**
 * Get revenue attribution data
 */
export const getRevenueAttribution = async ({ startDate, endDate, model = 'last_click', groupBy = 'source' }) => {
	return apiFetch({
		path: `${API_NAMESPACE}/revenue-attribution?start=${startDate}&end=${endDate}&model=${model}&group_by=${groupBy}`,
		method: 'GET',
	});
};

/**
 * Get content performance data
 */
export const getContentPerformance = async ({ startDate, endDate, sortBy = 'revenue', order = 'desc', search = '' }) => {
	const params = new URLSearchParams({
		start: startDate,
		end: endDate,
		sort_by: sortBy,
		order,
		search,
	});
	
	return apiFetch({
		path: `${API_NAMESPACE}/content-performance?${params.toString()}`,
		method: 'GET',
	});
};

/**
 * Get email campaign performance
 */
export const getEmailCampaigns = async ({ startDate, endDate }) => {
	return apiFetch({
		path: `${API_NAMESPACE}/email-campaigns?start=${startDate}&end=${endDate}`,
		method: 'GET',
	});
};

/**
 * Get traffic sources data
 */
export const getTrafficSources = async ({ startDate, endDate }) => {
	return apiFetch({
		path: `${API_NAMESPACE}/traffic-sources?start=${startDate}&end=${endDate}`,
		method: 'GET',
	});
};

/**
 * Get real-time statistics
 */
export const getRealTimeStats = async () => {
	return apiFetch({
		path: `${API_NAMESPACE}/realtime`,
		method: 'GET',
	});
};

/**
 * Get conversion funnel data
 */
export const getConversionFunnel = async ({ startDate, endDate }) => {
	return apiFetch({
		path: `${API_NAMESPACE}/conversion-funnel?start=${startDate}&end=${endDate}`,
		method: 'GET',
	});
};

/**
 * Get revenue trend data for charts
 */
export const getRevenueTrend = async ({ startDate, endDate, interval = 'day' }) => {
	return apiFetch({
		path: `${API_NAMESPACE}/revenue-trend?start=${startDate}&end=${endDate}&interval=${interval}`,
		method: 'GET',
	});
};

/**
 * Get email campaign journey map
 */
export const getEmailJourney = async ({ campaignId }) => {
	return apiFetch({
		path: `${API_NAMESPACE}/email-journey/${campaignId}`,
		method: 'GET',
	});
};

/**
 * Calculate ROAS (Return on Ad Spend)
 */
export const calculateROAS = async ({ startDate, endDate, adSpend }) => {
	return apiFetch({
		path: `${API_NAMESPACE}/calculate-roas`,
		method: 'POST',
		data: {
			start: startDate,
			end: endDate,
			ad_spend: adSpend,
		},
	});
};
