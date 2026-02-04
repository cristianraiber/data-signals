/**
 * Settings Card Component with individual save
 */

import { __ } from '@wordpress/i18n';
import { Button, ToggleControl, SelectControl, TextControl, RadioControl } from '@wordpress/components';

const SettingsCard = ( { title, fields, data, onChange, onSave, saving, hideHeader = false, hideFooter = false, beforeFields = null, afterFields = null } ) => {
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

		// Radio field
		if ( field.type === 'radio' && field.elements ) {
			return (
				<RadioControl
					key={ field.id }
					label={ field.label }
					help={ field.description }
					selected={ value || field.elements[ 0 ]?.value }
					options={ field.elements }
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

		// Text input field
		if ( field.type === 'text' && field.Edit === 'input' ) {
			return (
				<TextControl
					key={ field.id }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ field.label }
					help={ field.description }
					value={ value || '' }
					onChange={ ( newValue ) =>
						onChange( { [ field.id ]: newValue } )
					}
					placeholder={ field.placeholder || '' }
				/>
			);
		}

		return null;
	};

	return (
		<div className={ `ds-settings-card${ hideHeader ? ' ds-settings-card-inline' : '' }` }>
			{ ! hideHeader && (
				<div className="ds-settings-card-header">
					<h3>{ title }</h3>
				</div>
			) }
			<div className="ds-settings-card-body">
				{ beforeFields }
				{ fields.map( renderField ) }
				{ afterFields }
			</div>
			{ ! hideFooter && (
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
			) }
		</div>
	);
};

export default SettingsCard;
