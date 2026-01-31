/**
 * Formatting utilities for currency, percentages, and numbers
 */

/**
 * Format number as currency
 */
export const formatCurrency = (amount, currency = 'USD') => {
	if (amount === null || amount === undefined) {
		return '$0.00';
	}

	return new Intl.NumberFormat('en-US', {
		style: 'currency',
		currency: currency,
		minimumFractionDigits: 2,
		maximumFractionDigits: 2,
	}).format(amount);
};

/**
 * Format number as percentage
 */
export const formatPercentage = (value, decimals = 2) => {
	if (value === null || value === undefined) {
		return '0%';
	}

	return `${(value * 100).toFixed(decimals)}%`;
};

/**
 * Format large numbers with K, M, B suffix
 */
export const formatCompactNumber = (num) => {
	if (num === null || num === undefined) {
		return '0';
	}

	if (num >= 1000000000) {
		return `${(num / 1000000000).toFixed(1)}B`;
	}
	if (num >= 1000000) {
		return `${(num / 1000000).toFixed(1)}M`;
	}
	if (num >= 1000) {
		return `${(num / 1000).toFixed(1)}K`;
	}
	return num.toString();
};

/**
 * Format number with thousand separators
 */
export const formatNumber = (num, decimals = 0) => {
	if (num === null || num === undefined) {
		return '0';
	}

	return new Intl.NumberFormat('en-US', {
		minimumFractionDigits: decimals,
		maximumFractionDigits: decimals,
	}).format(num);
};

/**
 * Calculate percentage change between two values
 */
export const percentageChange = (current, previous) => {
	if (!previous || previous === 0) {
		return current > 0 ? 100 : 0;
	}
	return ((current - previous) / previous) * 100;
};

/**
 * Format percentage change with + or - sign
 */
export const formatPercentageChange = (current, previous) => {
	const change = percentageChange(current, previous);
	const sign = change >= 0 ? '+' : '';
	return `${sign}${change.toFixed(1)}%`;
};

/**
 * Format date range for display
 */
export const formatDateRange = (startDate, endDate) => {
	const options = { month: 'short', day: 'numeric', year: 'numeric' };
	const start = new Date(startDate).toLocaleDateString('en-US', options);
	const end = new Date(endDate).toLocaleDateString('en-US', options);
	return `${start} - ${end}`;
};

/**
 * Get date range presets
 */
export const getDatePreset = (preset) => {
	const end = new Date();
	const start = new Date();

	switch (preset) {
		case 'today':
			start.setHours(0, 0, 0, 0);
			end.setHours(23, 59, 59, 999);
			break;
		case '7d':
			start.setDate(end.getDate() - 7);
			break;
		case '30d':
			start.setDate(end.getDate() - 30);
			break;
		case '90d':
			start.setDate(end.getDate() - 90);
			break;
		default:
			start.setDate(end.getDate() - 7);
	}

	return {
		startDate: start.toISOString().split('T')[0],
		endDate: end.toISOString().split('T')[0],
	};
};

/**
 * Format seconds to readable duration
 */
export const formatDuration = (seconds) => {
	if (seconds < 60) {
		return `${Math.round(seconds)}s`;
	}
	if (seconds < 3600) {
		return `${Math.round(seconds / 60)}m`;
	}
	if (seconds < 86400) {
		return `${Math.round(seconds / 3600)}h`;
	}
	return `${Math.round(seconds / 86400)}d`;
};

/**
 * Truncate text to specified length
 */
export const truncateText = (text, maxLength = 50) => {
	if (!text || text.length <= maxLength) {
		return text;
	}
	return `${text.substring(0, maxLength)}...`;
};

/**
 * Get color for metric change (green for positive, red for negative)
 */
export const getChangeColor = (value) => {
	if (value > 0) return '#10b981'; // green
	if (value < 0) return '#ef4444'; // red
	return '#6b7280'; // gray
};
