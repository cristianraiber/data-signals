/**
 * Email Campaigns View
 * - Campaign list with ROI calculation
 * - Link-level performance (which CTAs convert)
 * - Email → Sale journey map
 * - Best-performing campaigns spotlight
 */
import { useState, useEffect } from '@wordpress/element';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import useAnalyticsStore from '../store/useAnalyticsStore';
import { getEmailCampaigns, getEmailJourney } from '../utils/api';
import { formatCurrency, formatNumber, formatPercentage } from '../utils/formatters';

const CampaignCard = ({ campaign, onViewJourney }) => {
	const roi = campaign.cost > 0 ? ((campaign.revenue - campaign.cost) / campaign.cost) * 100 : 0;
	const roiClass = roi >= 100 ? 'high' : roi >= 50 ? 'medium' : 'low';

	return (
		<div className={`ds-campaign-card ${campaign.isBestPerformer ? 'best-performer' : ''}`}>
			{campaign.isBestPerformer && (
				<div className="ds-best-badge">🏆 Best Performer</div>
			)}
			<h3>{campaign.name}</h3>
			<div className="ds-campaign-metrics">
				<div className="ds-metric">
					<span className="label">Clicks:</span>
					<span className="value">{formatNumber(campaign.clicks)}</span>
				</div>
				<div className="ds-metric">
					<span className="label">Conversions:</span>
					<span className="value">{formatNumber(campaign.conversions)}</span>
				</div>
				<div className="ds-metric">
					<span className="label">Revenue:</span>
					<span className="value">{formatCurrency(campaign.revenue)}</span>
				</div>
				<div className="ds-metric">
					<span className="label">ROI:</span>
					<span className={`value ${roiClass}`}>
						{roi > 0 ? '+' : ''}{roi.toFixed(0)}%
					</span>
				</div>
			</div>
			<button 
				className="ds-view-journey-btn"
				onClick={() => onViewJourney(campaign.id)}
			>
				View Journey Map
			</button>
		</div>
	);
};

const LinkPerformance = ({ links }) => {
	return (
		<div className="ds-link-performance">
			<h3>Link-Level Performance</h3>
			<table>
				<thead>
					<tr>
						<th>Link/CTA</th>
						<th>Clicks</th>
						<th>Conversions</th>
						<th>Revenue</th>
						<th>Conv. Rate</th>
					</tr>
				</thead>
				<tbody>
					{links.map((link, index) => (
						<tr key={index}>
							<td className="ds-link-url">{link.label || link.url}</td>
							<td>{formatNumber(link.clicks)}</td>
							<td>{formatNumber(link.conversions)}</td>
							<td>{formatCurrency(link.revenue)}</td>
							<td>
								<span className={`ds-conversion-badge ${link.conversionRate >= 0.1 ? 'high' : link.conversionRate >= 0.05 ? 'medium' : 'low'}`}>
									{formatPercentage(link.conversionRate)}
								</span>
							</td>
						</tr>
					))}
				</tbody>
			</table>
		</div>
	);
};

const JourneyMap = ({ journey, onClose }) => {
	if (!journey) return null;

	return (
		<div className="ds-journey-modal">
			<div className="ds-journey-content">
				<div className="ds-journey-header">
					<h2>Email → Sale Journey: {journey.campaignName}</h2>
					<button onClick={onClose} className="ds-close-btn">×</button>
				</div>
				
				<div className="ds-journey-steps">
					{journey.steps.map((step, index) => (
						<div key={index} className="ds-journey-step">
							<div className="ds-step-number">{index + 1}</div>
							<div className="ds-step-content">
								<div className="ds-step-title">{step.action}</div>
								<div className="ds-step-details">
									<span>{step.page}</span>
									<span className="ds-step-time">{step.timeFromPrevious}</span>
								</div>
							</div>
							{index < journey.steps.length - 1 && (
								<div className="ds-step-arrow">↓</div>
							)}
						</div>
					))}
				</div>

				<div className="ds-journey-summary">
					<div className="ds-summary-item">
						<span>Total Time to Conversion:</span>
						<strong>{journey.totalTime}</strong>
					</div>
					<div className="ds-summary-item">
						<span>Pages Visited:</span>
						<strong>{journey.pagesVisited}</strong>
					</div>
					<div className="ds-summary-item">
						<span>Final Revenue:</span>
						<strong>{formatCurrency(journey.revenue)}</strong>
					</div>
				</div>
			</div>
		</div>
	);
};

const EmailCampaigns = () => {
	const [selectedJourney, setSelectedJourney] = useState(null);
	const [journeyData, setJourneyData] = useState(null);
	
	const { 
		dateRange, 
		emailCampaignsData,
		setEmailCampaignsData,
		isLoading,
		setLoading,
	} = useAnalyticsStore();

	useEffect(() => {
		const fetchData = async () => {
			setLoading('emailCampaigns', true);
			try {
				const data = await getEmailCampaigns(dateRange);
				setEmailCampaignsData(data);
			} catch (error) {
				console.error('Error fetching email campaigns:', error);
			} finally {
				setLoading('emailCampaigns', false);
			}
		};

		fetchData();
	}, [dateRange]);

	const handleViewJourney = async (campaignId) => {
		try {
			const journey = await getEmailJourney({ campaignId });
			setJourneyData(journey);
			setSelectedJourney(campaignId);
		} catch (error) {
			console.error('Error fetching journey:', error);
		}
	};

	const campaigns = emailCampaignsData?.campaigns || [];
	const allLinks = emailCampaignsData?.allLinks || [];

	return (
		<div className="ds-email-campaigns">
			<h1>Email Campaign Performance</h1>

			{isLoading.emailCampaigns ? (
				<div className="ds-loading-skeleton" style={{ height: '600px' }}></div>
			) : (
				<>
					{/* Campaign Cards */}
					<div className="ds-campaigns-grid">
						{campaigns.map((campaign) => (
							<CampaignCard 
								key={campaign.id}
								campaign={campaign}
								onViewJourney={handleViewJourney}
							/>
						))}
					</div>

					{/* Link Performance Table */}
					{allLinks.length > 0 && (
						<LinkPerformance links={allLinks} />
					)}

					{/* ROI Chart */}
					{campaigns.length > 0 && (
						<div className="ds-chart-container">
							<h3>Campaign ROI Comparison</h3>
							<ResponsiveContainer width="100%" height={300}>
								<BarChart data={campaigns}>
									<CartesianGrid strokeDasharray="3 3" />
									<XAxis dataKey="name" />
									<YAxis />
									<Tooltip 
										formatter={(value, name) => {
											if (name === 'revenue' || name === 'cost') {
												return formatCurrency(value);
											}
											return value;
										}}
									/>
									<Legend />
									<Bar dataKey="revenue" fill="#10b981" name="Revenue" />
									<Bar dataKey="cost" fill="#ef4444" name="Cost" />
								</BarChart>
							</ResponsiveContainer>
						</div>
					)}
				</>
			)}

			{/* Journey Map Modal */}
			{selectedJourney && (
				<JourneyMap 
					journey={journeyData}
					onClose={() => setSelectedJourney(null)}
				/>
			)}
		</div>
	);
};

export default EmailCampaigns;
