/**
 * Traffic Sources Dashboard
 * - Revenue by source (Organic, Paid, Social, Referral, Direct, Email)
 * - Traffic quality score (not just volume)
 * - Cost per acquisition (if ad spend data)
 * - ROAS calculator (Return on Ad Spend)
 */
import { useState, useEffect } from '@wordpress/element';
import { BarChart, Bar, RadarChart, PolarGrid, PolarAngleAxis, PolarRadiusAxis, Radar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import useAnalyticsStore from '../store/useAnalyticsStore';
import { getTrafficSources, calculateROAS } from '../utils/api';
import { formatCurrency, formatNumber, formatPercentage } from '../utils/formatters';

const SourceCard = ({ source }) => {
	const qualityScore = source.qualityScore || 0;
	const qualityClass = qualityScore >= 80 ? 'high' : qualityScore >= 60 ? 'medium' : 'low';

	return (
		<div className="ds-source-card">
			<div className="ds-source-header">
				<h3>{source.name}</h3>
				<div className={`ds-quality-score ${qualityClass}`}>
					Quality: {qualityScore}/100
				</div>
			</div>
			<div className="ds-source-metrics">
				<div className="ds-metric-row">
					<span className="label">Visits:</span>
					<span className="value">{formatNumber(source.visits)}</span>
				</div>
				<div className="ds-metric-row">
					<span className="label">Revenue:</span>
					<span className="value">{formatCurrency(source.revenue)}</span>
				</div>
				<div className="ds-metric-row">
					<span className="label">Conversions:</span>
					<span className="value">{formatNumber(source.conversions)}</span>
				</div>
				<div className="ds-metric-row">
					<span className="label">Conv. Rate:</span>
					<span className="value">{formatPercentage(source.conversionRate)}</span>
				</div>
				{source.cost > 0 && (
					<>
						<div className="ds-metric-row">
							<span className="label">Ad Spend:</span>
							<span className="value">{formatCurrency(source.cost)}</span>
						</div>
						<div className="ds-metric-row">
							<span className="label">CPA:</span>
							<span className="value">{formatCurrency(source.cpa)}</span>
						</div>
						<div className="ds-metric-row">
							<span className="label">ROAS:</span>
							<span className={`value ${source.roas >= 3 ? 'high' : source.roas >= 2 ? 'medium' : 'low'}`}>
								{source.roas.toFixed(2)}x
							</span>
						</div>
					</>
				)}
			</div>
		</div>
	);
};

const ROASCalculator = ({ onCalculate }) => {
	const [adSpend, setAdSpend] = useState('');
	const [result, setResult] = useState(null);
	const { dateRange } = useAnalyticsStore();

	const handleCalculate = async () => {
		if (!adSpend || adSpend <= 0) return;
		
		try {
			const data = await calculateROAS({
				...dateRange,
				adSpend: parseFloat(adSpend),
			});
			setResult(data);
		} catch (error) {
			console.error('Error calculating ROAS:', error);
		}
	};

	return (
		<div className="ds-roas-calculator">
			<h3>ROAS Calculator</h3>
			<div className="ds-calculator-form">
				<label>
					Ad Spend:
					<input
						type="number"
						value={adSpend}
						onChange={(e) => setAdSpend(e.target.value)}
						placeholder="Enter amount"
						step="0.01"
						min="0"
					/>
				</label>
				<button onClick={handleCalculate} className="ds-calculate-btn">
					Calculate
				</button>
			</div>
			{result && (
				<div className="ds-calculator-result">
					<div className="ds-result-row">
						<span>Total Revenue:</span>
						<strong>{formatCurrency(result.revenue)}</strong>
					</div>
					<div className="ds-result-row">
						<span>Ad Spend:</span>
						<strong>{formatCurrency(result.adSpend)}</strong>
					</div>
					<div className="ds-result-row">
						<span>Net Profit:</span>
						<strong className={result.profit >= 0 ? 'positive' : 'negative'}>
							{formatCurrency(result.profit)}
						</strong>
					</div>
					<div className="ds-result-row highlight">
						<span>ROAS:</span>
						<strong>{result.roas.toFixed(2)}x</strong>
					</div>
					<div className="ds-result-note">
						{result.roas >= 3 && '🎉 Excellent return on investment!'}
						{result.roas >= 2 && result.roas < 3 && '✅ Good performance'}
						{result.roas < 2 && '⚠️ Consider optimizing your campaigns'}
					</div>
				</div>
			)}
		</div>
	);
};

const TrafficSources = () => {
	const { 
		dateRange, 
		trafficSourcesData,
		setTrafficSourcesData,
		isLoading,
		setLoading,
	} = useAnalyticsStore();

	useEffect(() => {
		const fetchData = async () => {
			setLoading('trafficSources', true);
			try {
				const data = await getTrafficSources(dateRange);
				setTrafficSourcesData(data);
			} catch (error) {
				console.error('Error fetching traffic sources:', error);
			} finally {
				setLoading('trafficSources', false);
			}
		};

		fetchData();
	}, [dateRange]);

	const sources = trafficSourcesData?.sources || [];
	const qualityRadarData = sources.map(s => ({
		source: s.name,
		quality: s.qualityScore,
	}));

	return (
		<div className="ds-traffic-sources">
			<h1>Traffic Sources Analysis</h1>

			{isLoading.trafficSources ? (
				<div className="ds-loading-skeleton" style={{ height: '600px' }}></div>
			) : (
				<>
					{/* Source Cards Grid */}
					<div className="ds-sources-grid">
						{sources.map((source) => (
							<SourceCard key={source.name} source={source} />
						))}
					</div>

					{/* Revenue by Source Chart */}
					<div className="ds-chart-container">
						<h3>Revenue by Traffic Source</h3>
						<ResponsiveContainer width="100%" height={300}>
							<BarChart data={sources}>
								<CartesianGrid strokeDasharray="3 3" />
								<XAxis dataKey="name" />
								<YAxis tickFormatter={(value) => formatCurrency(value)} />
								<Tooltip formatter={(value) => formatCurrency(value)} />
								<Legend />
								<Bar dataKey="revenue" fill="#3b82f6" name="Revenue" />
							</BarChart>
						</ResponsiveContainer>
					</div>

					{/* Quality Score Radar */}
					<div className="ds-chart-container">
						<h3>Traffic Quality Comparison</h3>
						<ResponsiveContainer width="100%" height={400}>
							<RadarChart data={qualityRadarData}>
								<PolarGrid />
								<PolarAngleAxis dataKey="source" />
								<PolarRadiusAxis domain={[0, 100]} />
								<Radar 
									name="Quality Score" 
									dataKey="quality" 
									stroke="#10b981" 
									fill="#10b981" 
									fillOpacity={0.6} 
								/>
								<Tooltip />
								<Legend />
							</RadarChart>
						</ResponsiveContainer>
						<div className="ds-quality-note">
							Quality score is calculated based on conversion rate, bounce rate, time on site, and pages per visit.
						</div>
					</div>

					{/* ROAS Calculator */}
					<ROASCalculator />
				</>
			)}
		</div>
	);
};

export default TrafficSources;
