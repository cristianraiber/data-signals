/**
 * Main Settings Page Component
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { SnackbarList, Notice, Spinner } from '@wordpress/components';

import SettingsCard from './SettingsCard';
import { trackingFields, geoFields, retentionFields, dashboardFields } from '../config/fields';

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
 * Status Badge Component
 */
const StatusBadge = ( { ok, label } ) => (
	<span
		className={ `ds-status-badge ${ ok ? 'ds-status-ok' : 'ds-status-error' }` }
	>
		{ ok ? '✓' : '✗' } { label }
	</span>
);

/**
 * Diagnostics Card Component
 */
const DiagnosticsCard = ( { diagnostics, loading } ) => {
	if ( loading ) {
		return (
			<div className="ds-settings-card">
				<div className="ds-settings-card-header">
					<h3>{ __( 'System Status', 'data-signals' ) }</h3>
				</div>
				<div className="ds-settings-card-body" style={ { textAlign: 'center', padding: '20px' } }>
					<Spinner />
				</div>
			</div>
		);
	}

	if ( ! diagnostics ) {
		return null;
	}

	const { cloudflare, geolite2, buffer } = diagnostics;

	return (
		<div className="ds-settings-card">
			<div className="ds-settings-card-header">
				<h3>{ __( 'System Status', 'data-signals' ) }</h3>
			</div>
			<div className="ds-settings-card-body ds-diagnostics">
				<div className="ds-diagnostic-row">
					<div className="ds-diagnostic-label">
						<strong>{ __( 'Cloudflare', 'data-signals' ) }</strong>
					</div>
					<div className="ds-diagnostic-value">
						<StatusBadge ok={ cloudflare?.ok } label={ cloudflare?.status } />
						<p className="ds-diagnostic-message">{ cloudflare?.message }</p>
					</div>
				</div>

				<div className="ds-diagnostic-row">
					<div className="ds-diagnostic-label">
						<strong>{ __( 'GeoLite2 Database', 'data-signals' ) }</strong>
					</div>
					<div className="ds-diagnostic-value">
						<StatusBadge ok={ geolite2?.ok } label={ geolite2?.status } />
						<p className="ds-diagnostic-message">{ geolite2?.message }</p>
						{ geolite2?.path && (
							<code className="ds-diagnostic-path">{ geolite2.path }</code>
						) }
					</div>
				</div>

				<div className="ds-diagnostic-row">
					<div className="ds-diagnostic-label">
						<strong>{ __( 'Buffer', 'data-signals' ) }</strong>
					</div>
					<div className="ds-diagnostic-value">
						<StatusBadge ok={ buffer?.ok } label={ buffer?.status } />
						<p className="ds-diagnostic-message">{ buffer?.message }</p>
					</div>
				</div>
			</div>
		</div>
	);
};

/**
 * Main Settings Page
 */
const SettingsPage = () => {
	const [ settings, setSettings ] = useState( {} );
	const [ diagnostics, setDiagnostics ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ diagLoading, setDiagLoading ] = useState( true );
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

	const fetchDiagnostics = useCallback( async () => {
		try {
			const response = await apiFetch( {
				path: '/data-signals/v1/diagnostics',
			} );
			setDiagnostics( response );
		} catch ( error ) {
			console.error( 'Error fetching diagnostics:', error );
		} finally {
			setDiagLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchSettings();
		fetchDiagnostics();
	}, [ fetchSettings, fetchDiagnostics ] );

	const saveSettings = async ( section ) => {
		setSaving( section );
		try {
			await apiFetch( {
				path: '/data-signals/v1/settings',
				method: 'POST',
				data: settings,
			} );
			pushToast( 'success', __( 'Settings saved!', 'data-signals' ) );
			// Refresh diagnostics after save (in case geo settings changed)
			fetchDiagnostics();
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
				<DiagnosticsCard diagnostics={ diagnostics } loading={ diagLoading } />
				
				<SettingsCard
					title={ __( 'Tracking Options', 'data-signals' ) }
					fields={ trackingFields }
					data={ settings }
					onChange={ handleChange }
					onSave={ () => saveSettings( 'tracking' ) }
					saving={ saving === 'tracking' }
				/>
				<SettingsCard
					title={ __( 'Geolocation', 'data-signals' ) }
					fields={ geoFields }
					data={ settings }
					onChange={ handleChange }
					onSave={ () => saveSettings( 'geo' ) }
					saving={ saving === 'geo' }
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
