/**
 * Main Dashboard View
 * - Key metrics cards: Total Revenue, RPV, Conversions, Visits
 * - Date range selector
 * - Revenue trend chart
 * - Traffic sources pie chart
 */
import { useState, useEffect } from '@wordpress/element';
import { LineChart, Line, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import useAnalyticsStore from '../store/useAnalyticsStore';
import { getAnalytics, getRevenueTrend } from '../utils/api';
import { formatCurrency, formatNumber, formatPercentage, formatCompactNumber } from '../utils/formatters';

const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];

const MetricCard = ({ title, value, format = 'number', isLoading }) => (
	<div className="ds-metric-card">
		<div className="ds-metric-title">{title}</div>
		{isLoading ? (
			<div className="ds-loading-skeleton"></div>
		) : (
			<div className="ds-metric-value">
				{format === 'currency' && formatCurrency(value)}
				{format === 'number' && formatCompactNumber(value)}
				{format === 'percentage' && formatPercentage(value)}
			</div>
		)}
	</div>
);

const DateRangeSelector = () => {
	const { datePreset, setDatePreset, dateRange, setDateRange } = useAnalyticsStore();
	const [showCustom, setShowCustom] = useState(false);

	const presets = [
		{ label: 'Today', value: 'today' },
		{ label: 'Last 7 days', value: '7d' },
		{ label: 'Last 30 days', value: '30d' },
		{ label: 'Last 90 days', value: '90d' },
	];

	return (
		<div className="ds-date-selector">
			{presets.map((preset) => (
				<button
					key={preset.value}
					className={`ds-date-btn ${datePreset === preset.value ? 'active' : ''}`}
					onClick={() => setDatePreset(preset.value)}
				>
					{preset.label}
				</button>
			))}
			<button
				className={`ds-date-btn ${datePreset === 'custom' ? 'active' : ''}`}
				onClick={() => setShowCustom(!showCustom)}
			>
				Custom
			</button>
			
			{showCustom && (
				<div className="ds-custom-date">
					<input
						type="date"
						value={dateRange.startDate}
						onChange={(e) => setDateRange(e.target.value, dateRange.endDate)}
					/>
					<span>to</span>
					<input
						type="date"
						value={dateRange.endDate}
						onChange={(e) => setDateRange(dateRange.startDate, e.target.value)}
					/>
				</div>
			)}
		</div>
	);
};

const Dashboard = () => {
	const { 
		dateRange, 
		analyticsData, 
		setAnalyticsData,
		revenueTrendData,
		setRevenueTrendData,
		isLoading,
		setLoading,
	} = useAnalyticsStore();

	useEffect(() => {
		const fetchData = async () => {
			setLoading('analytics', true);
			try {
				const [analytics, trend] = await Promise.all([
					getAnalytics(dateRange),
					getRevenueTrend({ ...dateRange, interval: 'day' }),
				]);
				setAnalyticsData(analytics);
				setRevenueTrendData(trend);
			} catch (error) {
				console.error('Error fetching dashboard data:', error);
			} finally {
				setLoading('analytics', false);
			}
		};

		fetchData();
	}, [dateRange]);

	const metrics = analyticsData?.metrics || {};
	const trafficSources = analyticsData?.trafficSources || [];

	return (
		<div className="ds-dashboard">
			<div className="ds-dashboard-header">
				<h1>Analytics Dashboard</h1>
				<DateRangeSelector />
			</div>

			{/* Key Metrics Cards */}
			<div className="ds-metrics-grid">
				<MetricCard 
					title="Total Revenue" 
					value={metrics.totalRevenue || 0}
					format="currency"
					isLoading={isLoading.analytics}
				/>
				<MetricCard 
					title="Revenue per Visitor (RPV)" 
					value={metrics.rpv || 0}
					format="currency"
					isLoading={isLoading.analytics}
				/>
				<MetricCard 
					title="Conversions" 
					value={metrics.conversions || 0}
					format="number"
					isLoading={isLoading.analytics}
				/>
				<MetricCard 
					title="Visits" 
					value={metrics.visits || 0}
					format="number"
					isLoading={isLoading.analytics}
				/>
			</div>

			{/* Revenue Trend Chart */}
			<div className="ds-chart-container">
				<h2>Revenue Trend</h2>
				{isLoading.analytics ? (
					<div className="ds-loading-skeleton" style={{ height: '300px' }}></div>
				) : (
					<ResponsiveContainer width="100%" height={300}>
						<LineChart data={revenueTrendData || []}>
							<CartesianGrid strokeDasharray="3 3" />
							<XAxis dataKey="date" />
							<YAxis tickFormatter={(value) => formatCurrency(value)} />
							<Tooltip 
								formatter={(value) => formatCurrency(value)}
								labelFormatter={(label) => `Date: ${label}`}
							/>
							<Legend />
							<Line 
								type="monotone" 
								dataKey="revenue" 
								stroke="#3b82f6" 
								strokeWidth={2}
								name="Revenue"
							/>
						</LineChart>
					</ResponsiveContainer>
				)}
			</div>

			{/* Traffic Sources Pie Chart */}
			<div className="ds-chart-container">
				<h2>Revenue by Traffic Source</h2>
				{isLoading.analytics ? (
					<div className="ds-loading-skeleton" style={{ height: '300px' }}></div>
				) : (
					<ResponsiveContainer width="100%" height={300}>
						<PieChart>
							<Pie
								data={trafficSources}
								dataKey="revenue"
								nameKey="source"
								cx="50%"
								cy="50%"
								outerRadius={100}
								label={(entry) => `${entry.source}: ${formatCurrency(entry.revenue)}`}
							>
								{trafficSources.map((entry, index) => (
									<Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
								))}
							</Pie>
							<Tooltip formatter={(value) => formatCurrency(value)} />
							<Legend />
						</PieChart>
					</ResponsiveContainer>
				)}
			</div>
		</div>
	);
};

export default Dashboard;
