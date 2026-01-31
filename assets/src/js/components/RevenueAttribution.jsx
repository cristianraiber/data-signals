/**
 * Revenue Attribution View
 * - Multi-tab: By Source, By Campaign, By Page, By Product
 * - Attribution model selector
 * - Revenue breakdown table with drill-down
 * - Conversion funnel visualization
 */
import { useState, useEffect } from '@wordpress/element';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, FunnelChart, Funnel, LabelList } from 'recharts';
import useAnalyticsStore from '../store/useAnalyticsStore';
import { getRevenueAttribution, getConversionFunnel } from '../utils/api';
import { formatCurrency, formatNumber, formatPercentage } from '../utils/formatters';

const AttributionModelSelector = () => {
	const { attributionModel, setAttributionModel } = useAnalyticsStore();

	const models = [
		{ label: 'First Click', value: 'first_click' },
		{ label: 'Last Click', value: 'last_click' },
		{ label: 'Linear', value: 'linear' },
		{ label: 'Time Decay', value: 'time_decay' },
	];

	return (
		<div className="ds-attribution-selector">
			<label>Attribution Model:</label>
			<select 
				value={attributionModel}
				onChange={(e) => setAttributionModel(e.target.value)}
				className="ds-select"
			>
				{models.map((model) => (
					<option key={model.value} value={model.value}>
						{model.label}
					</option>
				))}
			</select>
		</div>
	);
};

const AttributionTable = ({ data, groupBy }) => {
	const [expandedRow, setExpandedRow] = useState(null);

	const columns = {
		source: 'Source',
		campaign: 'Campaign',
		page: 'Page',
		product: 'Product',
	};

	const toggleRow = (index) => {
		setExpandedRow(expandedRow === index ? null : index);
	};

	return (
		<div className="ds-attribution-table">
			<table>
				<thead>
					<tr>
						<th>{columns[groupBy]}</th>
						<th>Revenue</th>
						<th>Conversions</th>
						<th>Avg. Order Value</th>
						<th>Conversion Rate</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					{data.map((row, index) => (
						<>
							<tr key={index} onClick={() => toggleRow(index)} className="ds-clickable-row">
								<td>{row.name}</td>
								<td>{formatCurrency(row.revenue)}</td>
								<td>{formatNumber(row.conversions)}</td>
								<td>{formatCurrency(row.avgOrderValue)}</td>
								<td>{formatPercentage(row.conversionRate)}</td>
								<td>
									<span className="ds-expand-icon">
										{expandedRow === index ? '▼' : '▶'}
									</span>
								</td>
							</tr>
							{expandedRow === index && row.breakdown && (
								<tr className="ds-expanded-row">
									<td colSpan="6">
										<div className="ds-breakdown">
											<h4>Breakdown</h4>
											<ul>
												{row.breakdown.map((item, idx) => (
													<li key={idx}>
														<span>{item.label}:</span>
														<span>{formatCurrency(item.value)}</span>
													</li>
												))}
											</ul>
										</div>
									</td>
								</tr>
							)}
						</>
					))}
				</tbody>
			</table>
		</div>
	);
};

const ConversionFunnel = ({ data }) => {
	return (
		<div className="ds-funnel-container">
			<h3>Conversion Funnel</h3>
			<ResponsiveContainer width="100%" height={400}>
				<FunnelChart>
					<Tooltip formatter={(value) => formatNumber(value)} />
					<Funnel
						dataKey="value"
						data={data}
						isAnimationActive
					>
						<LabelList position="right" fill="#000" stroke="none" dataKey="name" />
					</Funnel>
				</FunnelChart>
			</ResponsiveContainer>
		</div>
	);
};

const RevenueAttribution = () => {
	const [activeTab, setActiveTab] = useState('source');
	const { 
		dateRange, 
		attributionModel,
		revenueAttributionData,
		setRevenueAttributionData,
		conversionFunnelData,
		setConversionFunnelData,
		isLoading,
		setLoading,
	} = useAnalyticsStore();

	useEffect(() => {
		const fetchData = async () => {
			setLoading('attribution', true);
			try {
				const [attribution, funnel] = await Promise.all([
					getRevenueAttribution({ 
						...dateRange, 
						model: attributionModel,
						groupBy: activeTab,
					}),
					getConversionFunnel(dateRange),
				]);
				setRevenueAttributionData(attribution);
				setConversionFunnelData(funnel);
			} catch (error) {
				console.error('Error fetching attribution data:', error);
			} finally {
				setLoading('attribution', false);
			}
		};

		fetchData();
	}, [dateRange, attributionModel, activeTab]);

	const tabs = [
		{ label: 'By Source', value: 'source' },
		{ label: 'By Campaign', value: 'campaign' },
		{ label: 'By Page', value: 'page' },
		{ label: 'By Product', value: 'product' },
	];

	return (
		<div className="ds-revenue-attribution">
			<div className="ds-header">
				<h1>Revenue Attribution</h1>
				<AttributionModelSelector />
			</div>

			{/* Tabs */}
			<div className="ds-tabs">
				{tabs.map((tab) => (
					<button
						key={tab.value}
						className={`ds-tab ${activeTab === tab.value ? 'active' : ''}`}
						onClick={() => setActiveTab(tab.value)}
					>
						{tab.label}
					</button>
				))}
			</div>

			{/* Attribution Table */}
			{isLoading.attribution ? (
				<div className="ds-loading-skeleton" style={{ height: '400px' }}></div>
			) : (
				<AttributionTable 
					data={revenueAttributionData?.data || []}
					groupBy={activeTab}
				/>
			)}

			{/* Conversion Funnel */}
			{!isLoading.attribution && conversionFunnelData && (
				<ConversionFunnel data={conversionFunnelData} />
			)}
		</div>
	);
};

export default RevenueAttribution;
