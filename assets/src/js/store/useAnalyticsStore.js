/**
 * Zustand store for analytics dashboard state
 */
import { create } from 'zustand';
import { getDatePreset } from '../utils/formatters';

const useAnalyticsStore = create((set, get) => ({
	// Date range state
	dateRange: getDatePreset('7d'),
	datePreset: '7d',
	
	// Loading states
	isLoading: {
		analytics: false,
		attribution: false,
		content: false,
		emailCampaigns: false,
		trafficSources: false,
		realTime: false,
	},
	
	// Data states
	analyticsData: null,
	revenueAttributionData: null,
	contentPerformanceData: null,
	emailCampaignsData: null,
	trafficSourcesData: null,
	realTimeData: null,
	revenueTrendData: null,
	conversionFunnelData: null,
	
	// Filter states
	attributionModel: 'last_click',
	attributionGroupBy: 'source',
	contentSortBy: 'revenue',
	contentSortOrder: 'desc',
	contentSearchQuery: '',
	
	// Actions
	setDateRange: (startDate, endDate) => {
		set({ 
			dateRange: { startDate, endDate },
			datePreset: 'custom',
		});
	},
	
	setDatePreset: (preset) => {
		const dateRange = getDatePreset(preset);
		set({ 
			dateRange,
			datePreset: preset,
		});
	},
	
	setLoading: (key, value) => {
		set((state) => ({
			isLoading: { ...state.isLoading, [key]: value },
		}));
	},
	
	setAnalyticsData: (data) => set({ analyticsData: data }),
	setRevenueAttributionData: (data) => set({ revenueAttributionData: data }),
	setContentPerformanceData: (data) => set({ contentPerformanceData: data }),
	setEmailCampaignsData: (data) => set({ emailCampaignsData: data }),
	setTrafficSourcesData: (data) => set({ trafficSourcesData: data }),
	setRealTimeData: (data) => set({ realTimeData: data }),
	setRevenueTrendData: (data) => set({ revenueTrendData: data }),
	setConversionFunnelData: (data) => set({ conversionFunnelData: data }),
	
	setAttributionModel: (model) => set({ attributionModel: model }),
	setAttributionGroupBy: (groupBy) => set({ attributionGroupBy: groupBy }),
	
	setContentSort: (sortBy, order) => {
		set({ 
			contentSortBy: sortBy,
			contentSortOrder: order,
		});
	},
	
	setContentSearch: (query) => set({ contentSearchQuery: query }),
	
	// Helper to get current date range
	getCurrentDateRange: () => {
		const { dateRange } = get();
		return dateRange;
	},
	
	// Reset all data
	resetData: () => {
		set({
			analyticsData: null,
			revenueAttributionData: null,
			contentPerformanceData: null,
			emailCampaignsData: null,
			trafficSourcesData: null,
			realTimeData: null,
			revenueTrendData: null,
			conversionFunnelData: null,
		});
	},
}));

export default useAnalyticsStore;
