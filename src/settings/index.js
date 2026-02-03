/**
 * Data Signals Admin Settings
 */

import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import SettingsPage from './components/SettingsPage';
import './style.scss';

// Configure apiFetch
if ( window.dataSignalsAdmin ) {
	apiFetch.use(
		apiFetch.createNonceMiddleware( window.dataSignalsAdmin.restNonce )
	);
	apiFetch.use(
		apiFetch.createRootURLMiddleware( window.dataSignalsAdmin.restUrl )
	);
}

// Initialize
const settingsRoot = document.getElementById( 'data-signals-settings' );
if ( settingsRoot ) {
	createRoot( settingsRoot ).render( <SettingsPage /> );
}
