/**
 * Main Settings Page Component
 */

import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { SnackbarList, ToggleControl, Button } from '@wordpress/components';

import SettingsCard from './SettingsCard';
import { gdprInfoItems, trackingFields, geoFields } from '../config/fields';

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
				{ [ 1, 2, 3 ].map( ( i ) => (
					<div key={ i } className="ds-skeleton-field">
						<div className="ds-skeleton ds-skeleton-label"></div>
						<div className="ds-skeleton ds-skeleton-toggle"></div>
					</div>
				) ) }
			</div>
		</div>
	</div>
);

/**
 * User Role Exclusion Toggles Component
 */
const UserRoleToggles = ( { roles, settings, onChange } ) => {
	if ( ! roles || roles.length === 0 ) {
		return null;
	}

	return (
		<div className="ds-role-toggles">
			<h4 style={ { margin: '0 0 12px', fontSize: '13px', fontWeight: 600, color: '#1e1e1e' } }>
				{ __( 'Exclude User Roles', 'data-signals' ) }
			</h4>
			<p style={ { margin: '0 0 16px', fontSize: '12px', color: '#757575' } }>
				{ __( 'Do not track pageviews from these logged-in users.', 'data-signals' ) }
			</p>
			{ roles.map( ( role ) => {
				const key = `exclude_role_${ role.slug }`;
				return (
					<ToggleControl
						key={ key }
						__nextHasNoMarginBottom
						label={ role.name }
						checked={ !! settings[ key ] }
						onChange={ ( value ) => onChange( { [ key ]: value } ) }
					/>
				);
			} ) }
		</div>
	);
};

/**
 * GDPR Toggle Component (for Tracking Options card)
 */
const GDPRToggle = ( { settings, onChange } ) => {
	const isEnabled = !! settings?.gdpr_mode;
	const detectedCountry = settings?._detected_country || '';
	const isEU = settings?._is_eu ?? false;

	return (
		<div className="ds-gdpr-section">
			<ToggleControl
				__nextHasNoMarginBottom
				label={
					<>
						{ __( 'Enable GDPR Mode', 'data-signals' ) }
						{ detectedCountry && (
							<span className="ds-detected-badge">
								{ isEU ? `${ detectedCountry } (EU)` : detectedCountry }
							</span>
						) }
					</>
				}
				help={ __( 'Privacy-compliant analytics for EU/EEA sites.', 'data-signals' ) }
				checked={ isEnabled }
				onChange={ ( value ) => onChange( { gdpr_mode: value } ) }
			/>
			{ isEnabled && (
				<div className="ds-gdpr-info">
					<ul>
						{ gdprInfoItems.map( ( item, i ) => (
							<li key={ i }>✓ { item }</li>
						) ) }
					</ul>
				</div>
			) }
		</div>
	);
};

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
			const toast = { id, status, content: message };
			setToasts( ( current ) => [ toast, ...current ].slice( 0, 5 ) );
			setTimeout( () => removeToast( id ), 6000 );
		},
		[ removeToast ]
	);

	const fetchSettings = useCallback( async () => {
		try {
			const response = await apiFetch( { path: '/data-signals/v1/settings' } );
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
					beforeFields={
						<UserRoleToggles
							roles={ settings._user_roles }
							settings={ settings }
							onChange={ handleChange }
						/>
					}
					afterFields={
						<GDPRToggle
							settings={ settings }
							onChange={ handleChange }
						/>
					}
				/>

				<SettingsCard
					title={ __( 'Geolocation', 'data-signals' ) }
					fields={ geoFields }
					data={ settings }
					onChange={ handleChange }
					onSave={ () => saveSettings( 'geo' ) }
					saving={ saving === 'geo' }
				/>
			</div>
		</div>
	);
};

export default SettingsPage;
