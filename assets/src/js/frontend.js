/**
 * VI Account Manager Scripts
 *
 * @package EntireAccountManager
 * @since   1.0.0
 */
const {__} = wp.i18n;
import '../scss/frontend.scss';

( function ( $ ) {
	'use strict';

	// =========================================================================
	// Constants
	// =========================================================================

	const AJAX_URL = entireAjax.ajaxUrl;
	const NONCE    = entireAjax.nonce;

	const CSS = {
		// States
		ERROR:    'entire-error',
		SUCCESS:  'entire-success',
		LOADING:  'entire-loading',
		ACTIVE:   'active',
		SHOW:     'show',
		HIDE:     'entire-hide',

		// Components
		POPUP_CONTAINER:       'entire-popup-container',
		POPUP:                 'entire-popup',
		MODAL_OVERLAY:         'entire-modal-overlay',
		MODAL:                 'entire-modal',
		MODAL_CLOSE:           'entire-modal-close',
		MODAL_OK:              'entire-modal-ok',
		MODAL_MESSAGE:         'entire-modal-message',
		FORM:                  'entire-form',
		FORM_CONTAINER:        'entire-form-container',
		MESSAGE:               'entire-message',
		VALIDATION_MESSAGE:    'entire-validation-message',
		PASSWORD_STRENGTH_BAR: 'entire-password-strength-bar',
		RESEND_VERIFICATION:   'entire-resend-verification',
		LOST_PASSWORD:         'entire-lost-password',
		BACK_TO_LOGIN:         'entire-back-to-login',
		LOGIN_LINK:            'entire-login-link',
		LOST_PW_CONTAINER:     'entire-lost-password-form-container',
		LOGIN_CONTAINER:       'entire-login-form-container',
		RESET_PW_CONTAINER:    'entire-reset-password-form-container',
	};

	// Build selector map from CSS class map (e.g. CSS.ERROR -> SEL.ERROR = '.entire-error')
	const SEL = Object.fromEntries(
		Object.entries( CSS ).map( ( [ key, val ] ) => [ key, `.${ val }` ] )
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
				url:  AJAX_URL,
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
			if ( $el.length && $el.offset() ) {
				$( 'html, body' ).stop().animate(
					{ scrollTop: $el.offset().top - offset },
					500
				);
			}
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
				$container = $( `<div class="${ CSS.POPUP_CONTAINER }"></div>` )
					.appendTo( 'body' );
			}

			const $popup = $( `<div class="${ CSS.POPUP } ${ type }">${ message }</div>` )
				.appendTo( $container );

			setTimeout( () => $popup.addClass( CSS.SHOW ), 10 );

			setTimeout( () => {
				$popup.removeClass( CSS.SHOW );
				setTimeout( () => $popup.remove(), 5000 );
			}, 10000 );
		},
	};

	// =========================================================================
	// Modal (blocking dialog)
	// =========================================================================

	const Modal = {
		/**
		 * Display a blocking modal dialog.
		 *
		 * @param {string} message
		 * @param {string} type  'success' | 'error'
		 */
		show( message, type = 'success' ) {
			if ( ! $( SEL.MODAL_OVERLAY ).length ) {
				$( `
					<div class="${ CSS.MODAL_OVERLAY }">
						<div class="${ CSS.MODAL }">
							<button class="${ CSS.MODAL_CLOSE }">×</button>
							<div class="entire-modal-icon"></div>
							<div class="${ CSS.MODAL_MESSAGE }"></div>
							<button class="${ CSS.MODAL_OK }">OK</button>
						</div>
					</div>
				` ).appendTo( 'body' );
			}

			$( SEL.MODAL )
				.removeClass( `${ CSS.SUCCESS } ${ CSS.ERROR }` )
				.addClass( type );

			$( SEL.MODAL_MESSAGE ).text( message );

			$( SEL.MODAL_OVERLAY ).addClass( CSS.ACTIVE ).fadeIn( 200 );
		},

		/**
		 * Close the modal dialog.
		 */
		close() {
			$( SEL.MODAL_OVERLAY ).removeClass( CSS.ACTIVE ).fadeOut( 200 );
		},

		/**
		 * Bind modal events.
		 */
		init() {
			$( document )
				.on( 'click', SEL.MODAL_CLOSE, Modal.close )
				.on( 'click', SEL.MODAL_OK,    Modal.close )
				.on( 'click', SEL.MODAL_OVERLAY, function ( e ) {
					if ( $( e.target ).is( SEL.MODAL_OVERLAY ) ) {
						Modal.close();
					}
				} );
		},
	};

	// =========================================================================
	// FormValidator
	// =========================================================================

	const FormValidator = {
		/**
		 * Attach validation event listeners.
		 */
		init() {
			$( document )
				.on(
					'input',
					'[data-validate]',
					Utils.debounce( FormValidator._onFieldInput, 400 )
				)
				.on(
					'input',
					'[data-validate="password"]',
					Utils.debounce( FormValidator._onPasswordInput, 400 )
				)
				.on(
					'click',
					SEL.RESEND_VERIFICATION,
					Utils.debounce( FormValidator._onResendVerification, 400 )
				);
		},

		/**
		 * Validate a single field via AJAX.
		 *
		 * @private
		 */
		_onFieldInput() {
			const $field   = $( this );
			const type     = $field.data( 'validate' );
			const value    = $field.val().trim();
			const $message = $field.siblings( SEL.VALIDATION_MESSAGE );

			if ( ! value ) {
				$field.removeClass( `${ CSS.ERROR } ${ CSS.SUCCESS }` );
				$message.removeClass( `${ CSS.ERROR } ${ CSS.SUCCESS }` ).text( '' );
				return;
			}

			$field.addClass( CSS.LOADING );

			Utils.ajax( `entire_validate_${ type }`, { [ type ]: value } )
			     .then( ( response ) => {
				     const ok = response?.success;
				     $field
					     .removeClass( CSS.LOADING )
					     .removeClass( ok ? CSS.ERROR   : CSS.SUCCESS )
					     .addClass(    ok ? CSS.SUCCESS : CSS.ERROR );
				     $message
					     .removeClass( ok ? CSS.ERROR   : CSS.SUCCESS )
					     .addClass(    ok ? CSS.SUCCESS : CSS.ERROR )
					     .text( response?.data?.message ?? '' );
			     } )
			     .catch( () => {
				     $field.removeClass( CSS.LOADING );
			     } );
		},

		/**
		 * Update the password-strength bar.
		 *
		 * @private
		 */
		_onPasswordInput() {
			const password     = $( this ).val();
			const $strengthBar = $( SEL.PASSWORD_STRENGTH_BAR );

			if ( ! password ) {
				$strengthBar.attr( 'data-strength', 0 ).css( 'width', 0 );
				return;
			}

			let strength = 0;
			if ( password.length >= 8 )                                  { strength++; }
			if ( password.length >= 12 )                                  { strength++; }
			if ( /[a-z]/.test( password ) && /[A-Z]/.test( password ) ) { strength++; }
			if ( /\d/.test( password ) )                                  { strength++; }
			if ( /[^a-zA-Z\d]/.test( password ) )                        { strength++; }

			$strengthBar.attr( 'data-strength', Math.min( strength, 4 ) );
		},

		/**
		 * Resend the email-verification link.
		 *
		 * @private
		 * @param {Event} e
		 */
		_onResendVerification( e ) {
			e.preventDefault();

			const $button = $( this );
			const email   = $button.data( 'email' );

			$button
				.prop( 'disabled', true )
				.text( __( 'Sending...', 'entire-account-manager' ) );

			Utils.ajax( 'entire_resend_verification', { email } )
			     .then( ( response ) => {
				     if ( response?.success ) {
					     Popup.show( response.data.message, 'success' );
					     $button.text( __( 'Email Sent!', 'entire-account-manager' ) );
				     } else {
					     Popup.show( response?.data?.message, 'error' );
					     $button
						     .prop( 'disabled', false )
						     .text( __( 'Resend Verification Email', 'entire-account-manager' ) );
				     }
			     } )
			     .catch( () => {
				     Popup.show(
					     __( 'An error occurred. Please try again.', 'entire-account-manager' ),
					     'error'
				     );
				     $button
					     .prop( 'disabled', false )
					     .text( __( 'Resend Verification Email', 'entire-account-manager' ) );
			     } );
		},
	};

	// =========================================================================
	// FormSubmission
	// =========================================================================

	const FormSubmission = {
		/**
		 * Attach form-submit event listener.
		 */
		init() {
			$( document ).on( 'submit', SEL.FORM, FormSubmission._onSubmit );
		},

		/**
		 * Handle form submission.
		 *
		 * @private
		 * @param {Event} e
		 */
		async _onSubmit( e ) {
			e.preventDefault();

			const $form      = $( this );
			const $container = $form.closest( SEL.FORM_CONTAINER );
			const $button    = $container.find( 'button[type="submit"]' );

			// Build post body – encodeURIComponent guards special chars in 'type'
			const formData = $form.serialize()
			                 + '&action=entire_send_form'
			                 + '&type='  + encodeURIComponent( $form.data( 'type' ) )
			                 + '&nonce=' + NONCE;

			FormSubmission._setLoading( $container, $button, true );

			const $msg = FormSubmission._getMessageContainer( $container );

			try {
				const response = await $.ajax( {
					url:  AJAX_URL,
					type: 'POST',
					data: formData,
				} );

				if ( response?.success ) {
					FormSubmission._showMessage(
						$msg,
						response.data?.message || __( 'Success!', 'entire-account-manager' ),
						'success'
					);
					$form[ 0 ].reset();
					$( SEL.PASSWORD_STRENGTH_BAR ).attr( 'data-strength', '0' );

				} else if ( response?.data?.errors ) {
					FormSubmission._applyFieldErrors( $form, $msg, response.data.errors );

				} else {
					FormSubmission._showMessage(
						$msg,
						response?.data?.message || __( 'An unexpected error occurred.', 'entire-account-manager' ),
						'error'
					);
				}

			} catch ( err ) {
				FormSubmission._showMessage(
					$msg,
					__( 'An error occurred. Please try again.', 'entire-account-manager' ),
					'error'
				);

			} finally {
				FormSubmission._setLoading( $container, $button, false );
				Utils.scrollTo( $container.find( `${ SEL.MESSAGE }, ${ SEL.ERROR }` ).first() );
			}
		},

		// ---- Private helpers ------------------------------------------------

		/**
		 * Toggle the loading state on the submit button.
		 * Clears all feedback messages when entering the loading state.
		 *
		 * @param {jQuery}  $container
		 * @param {jQuery}  $button
		 * @param {boolean} loading
		 */
		_setLoading( $container, $button, loading ) {
			$button.prop( 'disabled', loading ).toggleClass( CSS.LOADING, loading );

			if ( loading ) {
				$container
					.find( `${ SEL.MESSAGE }, ${ SEL.VALIDATION_MESSAGE }` )
					.removeClass( `${ CSS.ERROR } ${ CSS.SUCCESS }` )
					.text( '' );
				$container
					.find( `${ SEL.ERROR }, ${ SEL.SUCCESS }` )
					.removeClass( `${ CSS.ERROR } ${ CSS.SUCCESS }` );
			}
		},

		/**
		 * Return (or lazily create) the shared message container.
		 *
		 * @param  {jQuery} $container
		 * @return {jQuery}
		 */
		_getMessageContainer( $container ) {
			let $msg = $container.find( SEL.MESSAGE );

			if ( ! $msg.length ) {
				$msg = $( `<div class="${ CSS.MESSAGE }"></div>` ).prependTo( $container );
			}

			return $msg;
		},

		/**
		 * Render a success or error message into the shared message element.
		 *
		 * @param {jQuery} $msg
		 * @param {string} text
		 * @param {string} type  'success' | 'error'
		 */
		_showMessage( $msg, text, type ) {
			$msg
				.addClass( type === 'success' ? CSS.SUCCESS : CSS.ERROR )
				.html( `<p>${ text }</p>` );
		},

		/**
		 * Map server-side field errors onto their matching inputs.
		 * Falls back to the shared message container for unknown fields.
		 *
		 * @param {jQuery} $form
		 * @param {jQuery} $msg
		 * @param {Object} errors  { fieldName: 'Error text', … }
		 */
		_applyFieldErrors( $form, $msg, errors ) {
			Object.entries( errors ).forEach( ( [ field, message ] ) => {
				const $field = $form.find( `[name="${ field }"]` );

				if ( $field.length ) {
					$field
						.addClass( CSS.ERROR )
						.closest( 'div' )
						.find( SEL.VALIDATION_MESSAGE )
						.addClass( CSS.ERROR )
						.text( message );
				} else {
					FormSubmission._showMessage( $msg, message, 'error' );
				}
			} );
		},
	};

	// =========================================================================
	// MessageHandler
	// =========================================================================

	const MessageHandler = {
		/**
		 * Initialise message behaviour.
		 * Auto-dismiss is disabled by default; uncomment _autoDismiss() to enable.
		 */
		init() {
			// this._autoDismiss();
		},

		/**
		 * Fade out and remove success messages after a delay.
		 *
		 * @private
		 */
		_autoDismiss() {
			$( `${ SEL.MESSAGE }.${ CSS.SUCCESS }` ).each( function () {
				const $message = $( this );
				setTimeout( () => {
					$message.fadeOut( 300, function () {
						$( this ).remove();
					} );
				}, 10000 );
			} );
		},
	};

	// =========================================================================
	// FormToggle
	// =========================================================================

	const FormToggle = {
		/**
		 * Bind toggle-link click events.
		 */
		init() {
			$( document )
				.on( 'click', SEL.LOST_PASSWORD, ( e ) => { e.preventDefault(); FormToggle.toggle( true );  } )
				.on( 'click', SEL.BACK_TO_LOGIN, ( e ) => { e.preventDefault(); FormToggle.toggle( false ); } )
				.on( 'click', SEL.LOGIN_LINK,    ( e ) => { e.preventDefault(); FormToggle.toggle( false ); } );
		},

		/**
		 * Switch between the forgotten-password panel and the login panel.
		 *
		 * @param {boolean} showForgotten  True → show forgotten-password view.
		 */
		toggle( showForgotten ) {
			$( SEL.LOST_PW_CONTAINER  ).toggleClass( CSS.HIDE, ! showForgotten );
			$( SEL.LOGIN_CONTAINER    ).toggleClass( CSS.HIDE,   showForgotten );
			$( SEL.RESET_PW_CONTAINER ).toggleClass( CSS.HIDE,   showForgotten );
		},
	};

	// =========================================================================
	// Bootstrap
	// =========================================================================

	$( document ).ready( () => {
		FormValidator.init();
		FormSubmission.init();
		MessageHandler.init();
		Modal.init();
		FormToggle.init();
	} );

} )( jQuery, wp.i18n );