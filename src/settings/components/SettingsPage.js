/**
 * Main Settings Page Component
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { SnackbarList } from '@wordpress/components';

// Import components
import SettingsCard from './SettingsCard';
import { trackingFields, retentionFields, dashboardFields } from '../config/fields';

/**
 * Loading Skeleton Component
 */
const LoadingSkeleton = () => (
	<div className="ds-settings-wrap">
		<div className="ds-settings-header">
			<div className="ds-settings-header-inner">
				<div className="ds-skeleton ds-skeleton-logo"></div>
				<div className="ds-skeleton ds-skeleton-title"></div>
			</div>
		</div>
		<div className="ds-settings-content">
			<div className="ds-skeleton-content">
				<div className="ds-skeleton-field">
					<div className="ds-skeleton ds-skeleton-label"></div>
					<div className="ds-skeleton ds-skeleton-toggle"></div>
				</div>
				<div className="ds-skeleton-field">
					<div className="ds-skeleton ds-skeleton-label"></div>
					<div className="ds-skeleton ds-skeleton-toggle"></div>
				</div>
				<div className="ds-skeleton-field">
					<div className="ds-skeleton ds-skeleton-label"></div>
					<div className="ds-skeleton ds-skeleton-toggle"></div>
				</div>
			</div>
		</div>
	</div>
);

/**
 * Main Settings Page
 */
const SettingsPage = () => {
	const [ settings, setSettings ] = useState( {} );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ saving, setSaving ] = useState( null );
	const [ toasts, setToasts ] = useState( [] );

	const removeToast = useCallback( ( id ) => {
		setToasts( ( current ) => current.filter( ( t ) => t.id !== id ) );
	}, [] );

	const pushToast = useCallback(
		( status, message ) => {
			const id = `${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
			const toast = {
				id,
				status,
				content: message,
			};

			setToasts( ( current ) => [ toast, ...current ].slice( 0, 5 ) );

			// Auto-dismiss.
			setTimeout( () => removeToast( id ), 6000 );
		},
		[ removeToast ]
	);

	const fetchSettings = useCallback( async () => {
		try {
			const response = await apiFetch( {
				path: '/data-signals/v1/settings',
			} );
			setSettings( response );
		} catch ( error ) {
			pushToast( 'error', __( 'Error loading settings.', 'data-signals' ) );
		} finally {
			setIsLoading( false );
		}
	}, [ pushToast ] );

	useEffect( () => {
		fetchSettings();
	}, [ fetchSettings ] );

	const saveSettings = async ( section ) => {
		setSaving( section );
		try {
			await apiFetch( {
				path: '/data-signals/v1/settings',
				method: 'POST',
				data: settings,
			} );
			pushToast( 'success', __( 'Settings saved!', 'data-signals' ) );
		} catch ( error ) {
			pushToast( 'error', __( 'Error saving settings.', 'data-signals' ) );
		} finally {
			setSaving( null );
		}
	};

	const handleChange = ( changes ) => {
		setSettings( ( prev ) => ( { ...prev, ...changes } ) );
	};

	if ( isLoading ) {
		return <LoadingSkeleton />;
	}

	return (
		<div className="ds-settings-wrap">
			<div className="ds-settings-header">
				<div className="ds-settings-header-inner">
					<span className="ds-icon">📊</span>
					<span className="ds-settings-title">Data Signals</span>
				</div>
			</div>

			<div className="ds-toast-container">
				<SnackbarList notices={ toasts } onRemove={ removeToast } />
			</div>

			<div className="ds-settings-content">
				<SettingsCard
					title={ __( 'Tracking Options', 'data-signals' ) }
					fields={ trackingFields }
					data={ settings }
					onChange={ handleChange }
					onSave={ () => saveSettings( 'tracking' ) }
					saving={ saving === 'tracking' }
				/>
				<SettingsCard
					title={ __( 'Data Retention', 'data-signals' ) }
					fields={ retentionFields }
					data={ settings }
					onChange={ handleChange }
					onSave={ () => saveSettings( 'retention' ) }
					saving={ saving === 'retention' }
				/>
				<SettingsCard
					title={ __( 'Dashboard Options', 'data-signals' ) }
					fields={ dashboardFields }
					data={ settings }
					onChange={ handleChange }
					onSave={ () => saveSettings( 'dashboard' ) }
					saving={ saving === 'dashboard' }
				/>
			</div>
		</div>
	);
};

export default SettingsPage;
