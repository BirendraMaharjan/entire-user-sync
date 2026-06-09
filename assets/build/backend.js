/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./assets/src/scss/backend.scss"
/*!**************************************!*\
  !*** ./assets/src/scss/backend.scss ***!
  \**************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!**********************************!*\
  !*** ./assets/src/js/backend.js ***!
  \**********************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _scss_backend_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../scss/backend.scss */ "./assets/src/scss/backend.scss");


/* global entireAjax, wp */
(function ($, config) {
  'use strict';

  // -------------------------------------------------------------------------
  // Reset Section
  // -------------------------------------------------------------------------
  $(document).on('click', '.your-plugin-reset', function () {
    const $btn = $(this);
    const section = $btn.data('section');
    const $wrap = $btn.closest('.your-plugin-section');
    const $notice = $wrap.find('.your-plugin-reset-notice');
    if (!window.confirm(config.i18n.confirmReset)) {
      return;
    }
    $btn.prop('disabled', true);
    $notice.hide();
    $.post(config.ajaxUrl, {
      action: 'your_plugin_reset_section',
      nonce: config.nonce,
      section: section
    }).done(function (response) {
      if (!response.success) {
        window.alert(config.i18n.resetError);
        return;
      }
      $.each(response.data.defaults, function (key, value) {
        const $field = $wrap.find('#' + key);
        if (!$field.length) {
          return;
        }
        const type = $field.attr('type');
        if ('checkbox' === type) {
          $field.prop('checked', '1' === String(value));
        } else if ('radio' === type) {
          $wrap.find('input[name="' + $field.attr('name') + '"]').prop('checked', false).filter('[value="' + value + '"]').prop('checked', true);
        } else if ($field.is('select[multiple]')) {
          const selected = Array.isArray(value) ? value : [];
          $field.find('option').prop('selected', false);
          $.each(selected, function (i, v) {
            $field.find('option[value="' + v + '"]').prop('selected', true);
          });
        } else if ('hidden' === type) {
          // Image field — clear preview.
          $field.val('');
          $wrap.find('.your-plugin-image-preview').empty();
          $wrap.find('.your-plugin-remove-image').hide();
        } else {
          $field.val(value);
        }
      });
      $notice.text(config.i18n.resetSuccess).css('color', 'green').fadeIn().delay(3000).fadeOut();
    }).fail(function () {
      window.alert(config.i18n.resetError);
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });

  // -------------------------------------------------------------------------
  // Image Upload (wp.media)
  // -------------------------------------------------------------------------

  $(document).on('click', '.your-plugin-upload-image', function (e) {
    e.preventDefault();
    const $btn = $(this);
    const fieldId = $btn.data('field');
    const $wrap = $btn.closest('.your-plugin-image-field');
    const frame = wp.media({
      title: config.i18n.uploadTitle,
      button: {
        text: config.i18n.uploadBtn
      },
      multiple: false,
      library: {
        type: 'image'
      }
    });
    frame.on('select', function () {
      const attachment = frame.state().get('selection').first().toJSON();
      $wrap.find('#' + fieldId).val(attachment.id);
      $wrap.find('.your-plugin-image-preview').html('<img src="' + attachment.sizes.thumbnail.url + '" style="max-width:150px;display:block;margin-bottom:8px;"  alt=""/>');
      $wrap.find('.your-plugin-remove-image').show();
    });
    frame.open();
  });

  // -------------------------------------------------------------------------
  // Image Remove
  // -------------------------------------------------------------------------

  $(document).on('click', '.your-plugin-remove-image', function (e) {
    e.preventDefault();
    var fieldId = $(this).data('field');
    var $wrap = $(this).closest('.your-plugin-image-field');
    $wrap.find('#' + fieldId).val('');
    $wrap.find('.your-plugin-image-preview').empty();
    $(this).hide();
  });

  // =========================================================================
  // REPEATER
  // =========================================================================

  $(document).on('click', '.your-plugin-add-row', function () {
    const $btn = $(this);
    const $rows = $btn.siblings('.your-plugin-repeater-rows');
    const nameBase = $btn.closest('.your-plugin-repeater').data('name-base');
    const subFields = $btn.data('sub-fields');
    const index = $rows.children('.your-plugin-repeater-row').length;
    const $row = $('<div class="your-plugin-repeater-row"></div>');
    $.each(subFields, function (subKey, subField) {
      const name = nameBase + '[' + index + '][' + subKey + ']';
      const $col = $('<div class="your-plugin-repeater-col"></div>');
      const label = $('<label></label>').text(subField.label);
      let $input;
      if (subField.type === 'select') {
        $input = $('<select></select>').attr('name', name);
        $.each(subField.options, function (val, label) {
          $input.append($('<option></option>').val(val).text(label));
        });
      } else if (subField.type === 'textarea') {
        $input = $('<textarea></textarea>').attr({
          name: name,
          rows: subField.rows || 3,
          placeholder: subField.placeholder || ''
        }).addClass('large-text');
      } else if (subField.type === 'checkbox') {
        $input = $('<input />').attr({
          type: 'checkbox',
          name: name,
          value: '1'
        });
      } else {
        $input = $('<input />').attr({
          type: subField.type || 'text',
          name: name,
          placeholder: subField.placeholder || '',
          class: 'regular-text'
        });
      }
      $col.append(label, $input);
      $row.append($col);
    });
    $row.append('<button type="button" class="button your-plugin-remove-row">&#x2715;</button>');
    $rows.append($row);
  });
  $(document).on('click', '.your-plugin-remove-row', function () {
    $(this).closest('.your-plugin-repeater-row').remove();
  });

  // =========================================================================
  // SELECT2 — multiselect tag-style
  // =========================================================================

  $(function () {
    $('.your-plugin-select2').select2({
      width: 'resolve',
      allowClear: true,
      closeOnSelect: false // keep dropdown open for multi-pick.
    });
  });
})(jQuery, entireAjax);
})();

/******/ })()
;
//# sourceMappingURL=backend.js.map