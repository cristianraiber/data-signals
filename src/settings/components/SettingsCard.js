/**
 * Settings Card Component with individual save
 */

import { __ } from '@wordpress/i18n';
import { Button, ToggleControl, SelectControl } from '@wordpress/components';

const SettingsCard = ( { title, fields, data, onChange, onSave, saving } ) => {
	const renderField = ( field ) => {
		const value = data[ field.id ];

		// Toggle field
		if ( field.type === 'boolean' ) {
			return (
				<ToggleControl
					key={ field.id }
					__nextHasNoMarginBottom
					label={ field.label }
					help={ field.description }
					checked={ !! value }
					onChange={ ( newValue ) =>
						onChange( { [ field.id ]: newValue } )
					}
				/>
			);
		}

		// Select field (has elements array)
		if ( field.elements && field.elements.length > 0 ) {
			return (
				<SelectControl
					key={ field.id }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ field.label }
					help={ field.description }
					value={ value || '' }
					options={ field.elements }
					onChange={ ( newValue ) =>
						onChange( { [ field.id ]: newValue } )
					}
				/>
			);
		}

		return null;
	};

	return (
		<div className="ds-settings-card">
			<div className="ds-settings-card-header">
				<h3>{ title }</h3>
			</div>
			<div className="ds-settings-card-body">
				{ fields.map( renderField ) }
			</div>
			<div className="ds-settings-card-footer">
				<Button
					variant="primary"
					onClick={ onSave }
					isBusy={ saving }
					disabled={ saving }
				>
					{ saving
						? __( 'Saving…', 'data-signals' )
						: __( 'Save', 'data-signals' ) }
				</Button>
			</div>
		</div>
	);
};

export default SettingsCard;
