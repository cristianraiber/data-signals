/**
 * Content Performance Table
 * - Blog posts ranked by revenue generated
 * - Columns: Title, Visits, Pricing Clicks, Sales, Revenue, Conversion Rate
 * - Sort by each column
 * - Search/filter functionality
 * - "Money pages" highlight (top 10%)
 */
import { useState, useEffect } from '@wordpress/element';
import useAnalyticsStore from '../store/useAnalyticsStore';
import { getContentPerformance } from '../utils/api';
import { formatCurrency, formatNumber, formatPercentage, truncateText } from '../utils/formatters';

const ContentPerformance = () => {
	const { 
		dateRange, 
		contentSortBy,
		contentSortOrder,
		contentSearchQuery,
		setContentSort,
		setContentSearch,
		contentPerformanceData,
		setContentPerformanceData,
		isLoading,
		setLoading,
	} = useAnalyticsStore();

	useEffect(() => {
		const fetchData = async () => {
			setLoading('content', true);
			try {
				const data = await getContentPerformance({
					...dateRange,
					sortBy: contentSortBy,
					order: contentSortOrder,
					search: contentSearchQuery,
				});
				setContentPerformanceData(data);
			} catch (error) {
				console.error('Error fetching content performance:', error);
			} finally {
				setLoading('content', false);
			}
		};

		fetchData();
	}, [dateRange, contentSortBy, contentSortOrder, contentSearchQuery]);

	const handleSort = (column) => {
		if (contentSortBy === column) {
			// Toggle order
			setContentSort(column, contentSortOrder === 'desc' ? 'asc' : 'desc');
		} else {
			// New column, default to desc
			setContentSort(column, 'desc');
		}
	};

	const getSortIcon = (column) => {
		if (contentSortBy !== column) return '↕';
		return contentSortOrder === 'desc' ? '↓' : '↑';
	};

	const posts = contentPerformanceData?.posts || [];
	const topRevenueThreshold = contentPerformanceData?.topRevenueThreshold || 0;

	return (
		<div className="ds-content-performance">
			<div className="ds-header">
				<h1>Content Performance</h1>
				<div className="ds-search-box">
					<input
						type="text"
						placeholder="Search posts..."
						value={contentSearchQuery}
						onChange={(e) => setContentSearch(e.target.value)}
						className="ds-search-input"
					/>
				</div>
			</div>

			{isLoading.content ? (
				<div className="ds-loading-skeleton" style={{ height: '500px' }}></div>
			) : (
				<div className="ds-table-container">
					<table className="ds-content-table">
						<thead>
							<tr>
								<th onClick={() => handleSort('title')} className="ds-sortable">
									Title {getSortIcon('title')}
								</th>
								<th onClick={() => handleSort('visits')} className="ds-sortable">
									Visits {getSortIcon('visits')}
								</th>
								<th onClick={() => handleSort('pricing_clicks')} className="ds-sortable">
									Pricing Clicks {getSortIcon('pricing_clicks')}
								</th>
								<th onClick={() => handleSort('sales')} className="ds-sortable">
									Sales {getSortIcon('sales')}
								</th>
								<th onClick={() => handleSort('revenue')} className="ds-sortable">
									Revenue {getSortIcon('revenue')}
								</th>
								<th onClick={() => handleSort('conversion_rate')} className="ds-sortable">
									Conversion Rate {getSortIcon('conversion_rate')}
								</th>
							</tr>
						</thead>
						<tbody>
							{posts.length === 0 ? (
								<tr>
									<td colSpan="6" className="ds-empty-state">
										No content data available for this period
									</td>
								</tr>
							) : (
								posts.map((post, index) => {
									const isMoneyPage = post.revenue >= topRevenueThreshold;
									return (
										<tr 
											key={post.id} 
											className={isMoneyPage ? 'ds-money-page' : ''}
											title={isMoneyPage ? 'Top 10% revenue generator' : ''}
										>
											<td className="ds-post-title">
												{isMoneyPage && <span className="ds-star">⭐</span>}
												<a href={post.url} target="_blank" rel="noopener noreferrer">
													{truncateText(post.title, 60)}
												</a>
											</td>
											<td>{formatNumber(post.visits)}</td>
											<td>{formatNumber(post.pricingClicks)}</td>
											<td>{formatNumber(post.sales)}</td>
											<td className="ds-revenue-cell">
												{formatCurrency(post.revenue)}
											</td>
											<td>
												<span className={`ds-conversion-badge ${post.conversionRate >= 0.05 ? 'high' : post.conversionRate >= 0.02 ? 'medium' : 'low'}`}>
													{formatPercentage(post.conversionRate)}
												</span>
											</td>
										</tr>
									);
								})
							)}
						</tbody>
					</table>
				</div>
			)}

			{/* Summary Stats */}
			{!isLoading.content && posts.length > 0 && (
				<div className="ds-content-summary">
					<div className="ds-summary-card">
						<span className="ds-summary-label">Total Posts:</span>
						<span className="ds-summary-value">{posts.length}</span>
					</div>
					<div className="ds-summary-card">
						<span className="ds-summary-label">Money Pages:</span>
						<span className="ds-summary-value">
							{posts.filter(p => p.revenue >= topRevenueThreshold).length}
						</span>
					</div>
					<div className="ds-summary-card">
						<span className="ds-summary-label">Total Revenue:</span>
						<span className="ds-summary-value">
							{formatCurrency(posts.reduce((sum, p) => sum + p.revenue, 0))}
						</span>
					</div>
					<div className="ds-summary-card">
						<span className="ds-summary-label">Avg. Conversion Rate:</span>
						<span className="ds-summary-value">
							{formatPercentage(
								posts.reduce((sum, p) => sum + p.conversionRate, 0) / posts.length
							)}
						</span>
					</div>
				</div>
			)}
		</div>
	);
};

export default ContentPerformance;
