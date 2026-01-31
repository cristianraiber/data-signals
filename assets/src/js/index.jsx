/**
 * Main entry point for Data Signals Dashboard
 */
import { render, useState } from '@wordpress/element';
import Dashboard from './components/Dashboard';
import RevenueAttribution from './components/RevenueAttribution';
import ContentPerformance from './components/ContentPerformance';
import EmailCampaigns from './components/EmailCampaigns';
import TrafficSources from './components/TrafficSources';
import RealTimeStats from './components/RealTimeStats';
import '../css/dashboard.css';

const App = () => {
	const [activeView, setActiveView] = useState('dashboard');

	const views = [
		{ id: 'dashboard', label: '📊 Dashboard', component: Dashboard },
		{ id: 'attribution', label: '💰 Revenue Attribution', component: RevenueAttribution },
		{ id: 'content', label: '📝 Content Performance', component: ContentPerformance },
		{ id: 'email', label: '📧 Email Campaigns', component: EmailCampaigns },
		{ id: 'traffic', label: '🚦 Traffic Sources', component: TrafficSources },
		{ id: 'realtime', label: '⚡ Real-Time', component: RealTimeStats },
	];

	const ActiveComponent = views.find(v => v.id === activeView)?.component || Dashboard;

	return (
		<div className="ds-app">
			{/* Navigation */}
			<nav className="ds-nav">
				<div className="ds-nav-header">
					<h1>Data Signals</h1>
					<p className="ds-nav-subtitle">Privacy-Focused Revenue Analytics</p>
				</div>
				<ul className="ds-nav-menu">
					{views.map((view) => (
						<li key={view.id}>
							<button
								className={`ds-nav-item ${activeView === view.id ? 'active' : ''}`}
								onClick={() => setActiveView(view.id)}
							>
								{view.label}
							</button>
						</li>
					))}
				</ul>
			</nav>

			{/* Main Content */}
			<main className="ds-main">
				<ActiveComponent />
			</main>
		</div>
	);
};

// Initialize the app
document.addEventListener('DOMContentLoaded', () => {
	const rootElement = document.getElementById('data-signals-dashboard');
	if (rootElement) {
		render(<App />, rootElement);
	}
});
