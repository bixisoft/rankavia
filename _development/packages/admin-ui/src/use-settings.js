import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { __ } from '@wordpress/i18n';

const EMPTY_SCHEMA = { groups: [] };
const NO_ERRORS = {};

const same = ( a, b ) => JSON.stringify( a ) === JSON.stringify( b );

/**
 * Loads, edits and saves the settings declared by the PHP schema.
 *
 * @param {Object} options
 * @param {string} options.restNamespace REST namespace, for example "rankavia/v1".
 * @return {Object} { schema, values, saved, dirty, loading, saving, errors, loadError, setValue, save, discard,
 *                  reset, exportSettings, importSettings }
 */
export function useSettings( { restNamespace = 'rankavia/v1' } = {} ) {
	const base = `/${ restNamespace }/settings`;
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const [ schema, setSchema ] = useState( EMPTY_SCHEMA );
	const [ saved, setSaved ] = useState( NO_ERRORS );
	const [ values, setValues ] = useState( NO_ERRORS );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ errors, setErrors ] = useState( NO_ERRORS );
	const [ loadError, setLoadError ] = useState( null );

	const success = useCallback(
		( message ) => createSuccessNotice( message, { type: 'snackbar' } ),
		[ createSuccessNotice ]
	);
	const failure = useCallback(
		( message ) => createErrorNotice( message, { type: 'snackbar' } ),
		[ createErrorNotice ]
	);

	const adopt = useCallback( ( next ) => {
		setSaved( next );
		setValues( next );
		setErrors( NO_ERRORS );
	}, [] );

	useEffect( () => {
		let active = true;
		apiFetch( { path: `${ base }?include=schema` } )
			.then( ( data ) => {
				if ( ! active ) {
					return;
				}
				setSchema( data.schema ?? EMPTY_SCHEMA );
				adopt( data.values ?? {} );
				setLoading( false );
			} )
			.catch( ( err ) => {
				if ( ! active ) {
					return;
				}
				setLoadError(
					err?.message ||
						__( 'The settings could not be loaded.', 'rankavia' )
				);
				setLoading( false );
			} );
		return () => {
			active = false;
		};
	}, [ base, adopt ] );

	const changedKeys = useMemo(
		() =>
			Object.keys( values ).filter(
				( key ) => ! same( values[ key ], saved[ key ] )
			),
		[ values, saved ]
	);
	const dirty = changedKeys.length > 0;

	const setValue = useCallback( ( key, value ) => {
		setValues( ( current ) => ( { ...current, [ key ]: value } ) );
		setErrors( ( current ) => {
			if ( ! current[ key ] ) {
				return current;
			}
			const { [ key ]: removed, ...rest } = current;
			return rest;
		} );
	}, [] );

	const discard = useCallback( () => {
		setValues( saved );
		setErrors( NO_ERRORS );
	}, [ saved ] );

	/**
	 * Maps a failed request to per-field errors (400 rankavia_invalid_settings) plus a notice.
	 *
	 * @param {Object} err      Error body from apiFetch.
	 * @param {string} fallback Message when the server gave none.
	 * @return {Object} key => message, empty when the failure was not a validation error.
	 */
	const handleFailure = useCallback(
		( err, fallback ) => {
			const fieldErrors = err?.data?.errors;
			if ( fieldErrors && typeof fieldErrors === 'object' ) {
				setErrors( fieldErrors );
				failure(
					__(
						'Some settings are not valid. Fix the marked fields and try again.',
						'rankavia'
					)
				);
				return fieldErrors;
			}
			failure( err?.message || fallback );
			return NO_ERRORS;
		},
		[ failure ]
	);

	const save = useCallback( async () => {
		if ( ! changedKeys.length ) {
			return { ok: true, errors: NO_ERRORS };
		}
		const payload = {};
		changedKeys.forEach( ( key ) => {
			payload[ key ] = values[ key ];
		} );
		setSaving( true );
		setErrors( NO_ERRORS );
		try {
			const data = await apiFetch( {
				path: base,
				method: 'POST',
				data: payload,
			} );
			adopt( data.values ?? values );
			success( __( 'Settings saved.', 'rankavia' ) );
			return { ok: true, errors: NO_ERRORS };
		} catch ( err ) {
			return {
				ok: false,
				errors: handleFailure(
					err,
					__( 'The settings could not be saved.', 'rankavia' )
				),
			};
		} finally {
			setSaving( false );
		}
	}, [ changedKeys, values, base, adopt, success, handleFailure ] );

	const reset = useCallback(
		async ( group ) => {
			const message = group
				? __(
						'Reset this section to its default settings? Unsaved changes in it are lost.',
						'rankavia'
				  )
				: __(
						'Reset all settings to their defaults? This cannot be undone.',
						'rankavia'
				  );
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( message ) ) {
				return false;
			}
			setSaving( true );
			try {
				const data = await apiFetch( {
					path: `${ base }/reset`,
					method: 'POST',
					data: group ? { group } : {},
				} );
				adopt( data.values ?? {} );
				success( __( 'Settings reset to defaults.', 'rankavia' ) );
				return true;
			} catch ( err ) {
				failure(
					err?.message ||
						__( 'The settings could not be reset.', 'rankavia' )
				);
				return false;
			} finally {
				setSaving( false );
			}
		},
		[ base, adopt, success, failure ]
	);

	const exportSettings = useCallback( async () => {
		try {
			return await apiFetch( { path: `${ base }/export` } );
		} catch ( err ) {
			failure(
				err?.message ||
					__( 'The settings could not be exported.', 'rankavia' )
			);
			return null;
		}
	}, [ base, failure ] );

	const importSettings = useCallback(
		async ( payload ) => {
			setSaving( true );
			setErrors( NO_ERRORS );
			try {
				const data = await apiFetch( {
					path: `${ base }/import`,
					method: 'POST',
					data: payload,
				} );
				adopt( data.values ?? {} );
				success( __( 'Settings imported.', 'rankavia' ) );
				return { ok: true, errors: NO_ERRORS };
			} catch ( err ) {
				return {
					ok: false,
					errors: handleFailure(
						err,
						__( 'The settings could not be imported.', 'rankavia' )
					),
				};
			} finally {
				setSaving( false );
			}
		},
		[ base, adopt, success, handleFailure ]
	);

	return {
		schema,
		values,
		saved,
		dirty,
		loading,
		saving,
		errors,
		loadError,
		setValue,
		save,
		discard,
		reset,
		exportSettings,
		importSettings,
	};
}
