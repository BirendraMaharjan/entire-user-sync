import '../scss/backend.scss';

/* global entireAjax, wp */
(
	function ( $, config ) {
		'use strict';

		// -------------------------------------------------------------------------
		// Reset Section
		// -------------------------------------------------------------------------

		$( document ).on( 'click', '.entire-setting-reset', function () {
			const $btn = $( this );
			const section = $btn.data( 'section' );
			const $wrap = $btn.closest( '.entire-setting-section' );
			const $notice = $wrap.find( '.entire-setting-reset-notice' );

			if ( ! window.confirm( config.i18n.confirmReset ) ) {
				return;
			}

			$btn.prop( 'disabled', true );
			$notice.hide();

			$.post( config.ajaxUrl, {
				action: 'entire_reset_section',
				nonce: config.nonce,
				section: section,
			} )
			 .done( function ( response ) {
				 if ( ! response.success ) {
					 window.alert( config.i18n.resetError );
					 return;
				 }

				 $.each( response.data.defaults, function ( key, value ) {
					 const $field = $wrap.find( '#' + key );

					 if ( ! $field.length ) {
						 return;
					 }

					 const type = $field.attr( 'type' );

					 if ( 'checkbox' === type ) {
						 $field.prop( 'checked', '1' === String( value ) );

					 } else if ( 'radio' === type ) {
						 $wrap.find( 'input[name="' + $field.attr( 'name' ) + '"]' )
						      .prop( 'checked', false )
						      .filter( '[value="' + value + '"]' )
						      .prop( 'checked', true );

					 } else if ( $field.is( 'select[multiple]' ) ) {
						 const selected = Array.isArray( value ) ? value : [];
						 $field.find( 'option' ).prop( 'selected', false );
						 $.each( selected, function ( i, v ) {
							 $field.find( 'option[value="' + v + '"]' ).prop( 'selected', true );
						 } );

					 } else if ( 'hidden' === type ) {
						 // Image field — clear preview.
						 $field.val( '' );
						 $wrap.find( '.entire-image-preview' ).empty();
						 $wrap.find( '.entire-remove-image' ).hide();

					 } else {
						 $field.val( value );
					 }
				 } );

				 $notice.text( config.i18n.resetSuccess ).css( 'color', 'green' ).fadeIn().delay( 3000 ).fadeOut();
			 } )
			 .fail( function () {
				 window.alert( config.i18n.resetError );
			 } )
			 .always( function () {
				 $btn.prop( 'disabled', false );
			 } );
		} );

		// -------------------------------------------------------------------------
		// Image Upload (wp.media)
		// -------------------------------------------------------------------------

		$( document ).on( 'click', '.entire-upload-image', function ( e ) {
			e.preventDefault();

			const $btn = $( this );
			const fieldId = $btn.data( 'field' );
			const $wrap = $btn.closest( '.entire-image-field' );

			const frame = wp.media( {
				title: config.i18n.uploadTitle,
				button: { text: config.i18n.uploadBtn },
				multiple: false,
				library: { type: 'image' },
			} );

			frame.on( 'select', function () {
				const attachment = frame.state().get( 'selection' ).first().toJSON();

				$wrap.find( '#' + fieldId ).val( attachment.id );
				$wrap.find( '.entire-image-preview' ).html(
					'<img src="' + attachment.sizes.thumbnail.url + '" style="max-width:150px;display:block;margin-bottom:8px;"  alt=""/>'
				);
				$wrap.find( '.entire-remove-image' ).show();
			} );

			frame.open();
		} );

		// -------------------------------------------------------------------------
		// Image Remove
		// -------------------------------------------------------------------------

		$( document ).on( 'click', '.entire-remove-image', function ( e ) {
			e.preventDefault();

			var fieldId = $( this ).data( 'field' );
			var $wrap = $( this ).closest( '.entire-image-field' );

			$wrap.find( '#' + fieldId ).val( '' );
			$wrap.find( '.entire-image-preview' ).empty();
			$( this ).hide();
		} );

		// =========================================================================
		// REPEATER
		// =========================================================================

		$( document ).on( 'click', '.entire-add-row', function () {
			const $btn = $( this );
			const $rows = $btn.siblings( '.entire-repeater-rows' );
			const nameBase = $btn.closest( '.entire-repeater' ).data( 'name-base' );
			const subFields = $btn.data( 'sub-fields' );
			const index = $rows.children( '.entire-repeater-row' ).length;

			const $row = $( '<div class="entire-repeater-row"></div>' );

			$.each( subFields, function ( subKey, subField ) {
				const name = nameBase + '[' + index + '][' + subKey + ']';
				const $col = $( '<div class="entire-repeater-col"></div>' );
				const label = $( '<label></label>' ).text( subField.label );
				let $input;

				if ( subField.type === 'select' ) {
					$input = $( '<select></select>' ).attr( 'name', name );
					$.each( subField.options, function ( val, label ) {
						$input.append( $( '<option></option>' ).val( val ).text( label ) );
					} );
				} else if ( subField.type === 'textarea' ) {
					$input = $( '<textarea></textarea>' )
						.attr( { name: name, rows: subField.rows || 3, placeholder: subField.placeholder || '' } )
						.addClass( 'large-text' );
				} else if ( subField.type === 'checkbox' ) {
					$input = $( '<input />' ).attr( { type: 'checkbox', name: name, value: '1' } );
				} else {
					$input = $( '<input />' ).attr( {
						type: subField.type || 'text',
						name: name,
						placeholder: subField.placeholder || '',
						class: 'regular-text'
					} );
				}

				$col.append( label, $input );
				$row.append( $col );
			} );

			$row.append( '<button type="button" class="button entire-remove-row">&#x2715;</button>' );
			$rows.append( $row );
		} );

		$( document ).on( 'click', '.entire-remove-row', function () {
			$( this ).closest( '.entire-repeater-row' ).remove();
		} );

		// =========================================================================
		// SELECT2 — multiselect tag-style
		// =========================================================================

		$( function () {
			$( '.entire-select2' ).select2( {
				width: 'resolve',
				allowClear: true,
				closeOnSelect: false,
			} );
		} );

	}( jQuery, entireUsBackendAjax )
);


(
	function ( $, config, i18n ) {
		'use strict';

		// =========================================================================
		// Constants
		// =========================================================================

		const AJAX_URL = config.ajaxUrl;
		const NONCE = config.nonce;

		const CSS = {
			// States
			ERROR: 'entire-error',
			SUCCESS: 'entire-success',
			LOADING: 'entire-loading',
			ACTIVE: 'entire-active',
			SHOW: 'entire-show',
			HIDE: 'entire-hide',

			// Components
			POPUP_CONTAINER: 'entire-popup-container',
			POPUP: 'entire-popup',
			MODAL_OVERLAY: 'entire-modal-overlay',
			MODAL: 'entire-modal',
			MODAL_CLOSE: 'entire-modal-close',
			MODAL_OK: 'entire-modal-ok',
			MODAL_BODY: 'entire-modal-body',
		};

		// Build selector map from CSS class map (e.g. CSS.ERROR -> SEL.ERROR = '.entire-error')
		const SEL = Object.fromEntries(
			Object.entries( CSS ).map(
				( [ key, val ] ) => [ key, `.${ val }` ]
			)
		);

		// =========================================================================
		// Utils
		// =========================================================================

		const Utils = {
			/**
			 * Delay execution until a function stops being called.
			 *
			 * @param {Function} fn
			 * @param {number}   delay  Milliseconds.
			 * @return {Function}
			 */
			debounce( fn, delay ) {
				let timer;
				return function ( ...args ) {
					clearTimeout( timer );
					timer = setTimeout( () => fn.apply( this, args ), delay );
				};
			},

			/**
			 * Post data to the WP AJAX endpoint and return a Promise.
			 *
			 * @param {string} action
			 * @param {Object} data
			 * @return {Promise}
			 */
			ajax( action, data = {} ) {
				return $.ajax( {
					url: AJAX_URL,
					type: 'POST',
					data: { action, nonce: NONCE, ...data },
				} );
			},

			/**
			 * Scroll the viewport to an element with an optional offset.
			 *
			 * @param {jQuery} $el
			 * @param {number} offset  Pixels from the top. Default 150.
			 */
			scrollTo( $el, offset = 150 ) {
				if ( ! $el.length ) {
					return;
				}
				const offsetTop = $el.offset();
				if ( ! offsetTop ) {
					return;
				}
				$( 'html, body' ).stop().animate(
					{
						scrollTop: offsetTop.top - offset,
					},
					500
				);
			},
		};

		// =========================================================================
		// Popup (toast notifications)
		// =========================================================================

		const Popup = {
			/**
			 * Display a transient toast notification.
			 *
			 * @param {string} message
			 * @param {string} type  'success' | 'error'
			 */
			show( message, type = 'success' ) {
				let $container = $( SEL.POPUP_CONTAINER );

				if ( ! $container.length ) {
					$container = $( '<div>', {
						class: CSS.POPUP_CONTAINER,
					} ).appendTo( 'body' );
				}

				const $popup = $( '<div>', {
					class: `${ CSS.POPUP } ${ type }`,
					text: message,
				} ).appendTo( $container );

				setTimeout( () => {
					$popup.addClass( CSS.SHOW );
				}, 10 );

				setTimeout( () => {
					$popup.removeClass( CSS.SHOW );
					setTimeout( () => {
						$popup.remove();
					}, 500 );
				}, 10000 );
			},
		};

		// =========================================================================
		// Modal (blocking dialog)
		// =========================================================================

		const Modal = {

			/** * Create the modal if it does not already exist. */
			create() {
				if ( $( SEL.MODAL_OVERLAY ).length ) {
					return;
				}

				const $modal = $( `
					<div class="${ CSS.MODAL_OVERLAY }"> 
						<div class="${ CSS.MODAL }"> 
							<h4>${ i18n.__( 'Payload', 'entire-user-sync' ) }</h4>
							<button 
							type="button" 
							class="${ CSS.MODAL_CLOSE }" 
							aria-label="${ i18n.__( 'Close', 'entire-user-sync' ) }" 
							>&times;</button>
							<div class="${ CSS.MODAL_BODY }"></div> 
							<button type="button" class="${ CSS.MODAL_OK }" >
								${ i18n.__( 'OK', 'entire-user-sync' ) }
							</button> 
						</div>
					</div> 
				` );
				$modal.appendTo( 'body' );
			},

			/**
			 * Show the modal.
			 *
			 * @param {string} message Modal message.
			 * @param {string} type  'success' | 'error'
			 */
			show( message, type = 'success' ) {
				this.create();

				$( SEL.MODAL )
					.removeClass( `${ CSS.SUCCESS } ${ CSS.ERROR }` )
					.addClass( type );

				$( SEL.MODAL_BODY ).text( message );

				$( SEL.MODAL_OVERLAY )
					.addClass( CSS.ACTIVE )
					.stop( true, true )
					.fadeIn( 200 );
			},

			/**
			 * Close the modal dialog.
			 */
			close() {
				$( SEL.MODAL_OVERLAY )
					.removeClass( CSS.ACTIVE )
					.stop( true, true )
					.fadeOut( 200 );
			},

			/**
			 * Format a JSON payload for display.
			 *
			 * @param {string} payload Raw JSON payload.
			 *
			 * @return {string} Formatted payload.
			 */
			formatPayload( payload ) {
				if ( ! payload ) {
					return '';
				}

				try {
					return JSON.stringify( JSON.parse( payload ), null, 2 );
				} catch ( error ) {
					return payload;
				}
			},

			/**
			 * Initialize modal events.
			 */
			init() {

				$( document )
					// View payload.
					.on( 'click', '.entireus-view-payload', function ( event ) {
						event.preventDefault();
						const payload = $( this ).attr( 'data-payload' ) || '';
						const formattedPayload = Modal.formatPayload( payload );
						Modal.show( formattedPayload, 'success' );
					} )
					// Close button.
					.on( 'click', SEL.MODAL_CLOSE, Modal.close )
					// OK button.
					.on( 'click', SEL.MODAL_OK, Modal.close )
					// Close when clicking the overlay.
					.on( 'click', SEL.MODAL_OVERLAY, function ( event ) {
						if ( event.target === this ) {
							Modal.close();
						}
					} );
			},
		};

		const Forms = {
			/**
			 *  Initialize form events.
			 */
			init() {
				const form = document.querySelector( '#entireus-logs-filter' );
				if ( ! form ) {
					return;
				}
				form.addEventListener( 'submit', function ( event ) {
					const action = form.querySelector( 'select[name="action"]' );
					const action2 = form.querySelector( 'select[name="action2"]' );
					const isDelete = ( action && 'delete' === action.value ) ||
					                 ( action2 && 'delete' === action2.value );
					if (
						isDelete &&
						! window.confirm(
							i18n.__( 'Delete the selected log entries? This cannot be undone.', 'entire-user-sync' )
						)
					) {
						event.preventDefault();
					}
				} );
			},
		};

		// =========================================================================
		// Bootstrap
		// =========================================================================
		$( () => {
			Modal.init();
			Forms.init();
		} );

	}
)( jQuery, entireUsBackendAjax, wp.i18n );