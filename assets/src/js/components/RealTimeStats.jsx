/**
 * Real-Time Stats Widget
 * - Live visitors count
 * - Recent conversions (last 10)
 * - Active pages
 * - Revenue today counter
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import useAnalyticsStore from '../store/useAnalyticsStore';
import { getRealTimeStats } from '../utils/api';
import { formatCurrency, formatNumber, truncateText } from '../utils/formatters';

const LiveCounter = ({ value, label, format = 'number' }) => (
	<div className="ds-live-counter">
		<div className="ds-live-value">
			{format === 'currency' && formatCurrency(value)}
			{format === 'number' && formatNumber(value)}
		</div>
		<div className="ds-live-label">{label}</div>
	</div>
);

const RecentConversion = ({ conversion }) => {
	const timeAgo = (timestamp) => {
		const seconds = Math.floor((new Date() - new Date(timestamp)) / 1000);
		if (seconds < 60) return `${seconds}s ago`;
		if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
		return `${Math.floor(seconds / 3600)}h ago`;
	};

	return (
		<div className="ds-recent-conversion">
			<div className="ds-conversion-icon">💰</div>
			<div className="ds-conversion-details">
				<div className="ds-conversion-product">
					{truncateText(conversion.productName, 40)}
				</div>
				<div className="ds-conversion-meta">
					<span className="ds-conversion-amount">
						{formatCurrency(conversion.amount)}
					</span>
					<span className="ds-conversion-time">
						{timeAgo(conversion.timestamp)}
					</span>
				</div>
			</div>
		</div>
	);
};

const ActivePage = ({ page }) => (
	<div className="ds-active-page">
		<div className="ds-page-visitors">
			<span className="ds-visitor-count">{page.visitors}</span>
			<span className="ds-visitor-icon">👥</span>
		</div>
		<div className="ds-page-url">
			{truncateText(page.title || page.url, 50)}
		</div>
	</div>
);

const RealTimeStats = () => {
	const { 
		realTimeData,
		setRealTimeData,
		isLoading,
		setLoading,
	} = useAnalyticsStore();

	const intervalRef = useRef(null);

	const fetchRealTimeData = async () => {
		try {
			const data = await getRealTimeStats();
			setRealTimeData(data);
		} catch (error) {
			console.error('Error fetching real-time stats:', error);
		}
	};

	useEffect(() => {
		// Initial fetch
		setLoading('realTime', true);
		fetchRealTimeData().then(() => setLoading('realTime', false));

		// Poll every 5 seconds
		intervalRef.current = setInterval(fetchRealTimeData, 5000);

		return () => {
			if (intervalRef.current) {
				clearInterval(intervalRef.current);
			}
		};
	}, []);

	const stats = realTimeData || {
		liveVisitors: 0,
		revenueToday: 0,
		conversionsToday: 0,
		recentConversions: [],
		activePages: [],
	};

	return (
		<div className="ds-realtime-stats">
			<div className="ds-realtime-header">
				<h2>Real-Time Stats</h2>
				<div className="ds-live-indicator">
					<span className="ds-pulse"></span>
					<span>Live</span>
				</div>
			</div>

			{isLoading.realTime ? (
				<div className="ds-loading-skeleton" style={{ height: '400px' }}></div>
			) : (
				<>
					{/* Live Counters */}
					<div className="ds-live-counters">
						<LiveCounter 
							value={stats.liveVisitors} 
							label="Live Visitors"
							format="number"
						/>
						<LiveCounter 
							value={stats.revenueToday} 
							label="Revenue Today"
							format="currency"
						/>
						<LiveCounter 
							value={stats.conversionsToday} 
							label="Conversions Today"
							format="number"
						/>
					</div>

					{/* Recent Conversions */}
					<div className="ds-realtime-section">
						<h3>Recent Conversions</h3>
						<div className="ds-conversions-list">
							{stats.recentConversions.length === 0 ? (
								<div className="ds-empty-state">
									No recent conversions
								</div>
							) : (
								stats.recentConversions.slice(0, 10).map((conversion, index) => (
									<RecentConversion key={index} conversion={conversion} />
								))
							)}
						</div>
					</div>

					{/* Active Pages */}
					<div className="ds-realtime-section">
						<h3>Active Pages</h3>
						<div className="ds-active-pages-list">
							{stats.activePages.length === 0 ? (
								<div className="ds-empty-state">
									No active pages
								</div>
							) : (
								stats.activePages.map((page, index) => (
									<ActivePage key={index} page={page} />
								))
							)}
						</div>
					</div>
				</>
			)}
		</div>
	);
};

export default RealTimeStats;
