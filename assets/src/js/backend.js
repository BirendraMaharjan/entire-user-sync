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
				button: {text: config.i18n.uploadBtn},
				multiple: false,
				library: {type: 'image'},
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
						.attr( {name: name, rows: subField.rows || 3, placeholder: subField.placeholder || ''} )
						.addClass( 'large-text' );
				} else if ( subField.type === 'checkbox' ) {
					$input = $( '<input />' ).attr( {type: 'checkbox', name: name, value: '1'} );
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

	}( jQuery, entireAjax )
);
