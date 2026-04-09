/**
 * Smart Programme Finder — Frontend AJAX & UI.
 *
 * Handles form validation for all field types, AJAX submission,
 * result modal, and infinite reset/resubmission.
 *
 * @package SmartProgrammeFinder
 */

/* global jQuery, spf_ajax */
(function ($) {
    'use strict';

    /**
     * Initialise each form instance on the page.
     */
    function init() {
        $('.spf-form').each(function () {
            var $form    = $(this);
            var formId   = $form.closest('.spf-form-wrapper').data('form-id');
            var $modal   = $('#spf-modal-' + formId);
            var $wrapper = $form.closest('.spf-form-wrapper');
            var $inline  = $wrapper.find('.spf-confirmation-inline');

            // Guard against double-binding
            if ($form.data('spf-bound')) {
                return;
            }
            $form.data('spf-bound', true);

            /* ── Form Submit ───────────────────── */
            $form.on('submit', function (e) {
                e.preventDefault();

                if (!validateForm($form)) {
                    return;
                }

                // Disable button & show loader
                var $btn = $form.find('.spf-submit-btn');
                var $btnText = $btn.find('.spf-btn-text');
                var originalText = $btnText.text();
                var processingText = $btn.data('processing-text');
                $btn.prop('disabled', true).addClass('spf-loading');
                if (processingText) {
                    $btnText.text(processingText);
                }

                $.ajax({
                    url:      spf_ajax.ajax_url,
                    type:     'POST',
                    data:     $form.serialize(),
                    dataType: 'json',
                    success: function (response) {
                        var message = '';
                        var confType = 'popup';

                        if (response.success && response.data) {
                            message  = response.data.message || '';
                            confType = response.data.confirmation_type || 'popup';
                        } else if (response.data && response.data.message) {
                            message = response.data.message;
                        } else {
                            message = 'Something went wrong. Please try again.';
                        }

                        if (confType === 'message') {
                            showInlineConfirmation($form, $inline, message);
                        } else {
                            showModal($modal, message);
                        }
                    },
                    error: function () {
                        showModal($modal, 'A network error occurred. Please try again.');
                    },
                    complete: function () {
                        $btn.prop('disabled', false).removeClass('spf-loading');
                        if (processingText) {
                            $btnText.text(originalText);
                        }
                    }
                });
            });

            /* ── Clear validation on change ────── */
            $form.on('change input', 'select, input, textarea', function () {
                var $field = $(this);
                var $group = $field.closest('.spf-field-group');
                var $error = $group.find('.spf-error-message');

                $group.find('.spf-invalid').removeClass('spf-invalid');
                $group.removeClass('spf-group-invalid');
                $error.text('').removeClass('spf-visible');

                /* Re-evaluate conditional logic on value change */
                evaluateConditionalLogic($form);
            });

            /* ── Initial conditional logic evaluation ── */
            evaluateConditionalLogic($form);

            /* ── Modal close button ────────────── */
            $modal.on('click', '.spf-modal-close', function () {
                hideModal($modal);
            });

            /* ── Overlay click to close ────────── */
            $modal.on('click', function (e) {
                if ($(e.target).hasClass('spf-modal-overlay')) {
                    hideModal($modal);
                }
            });

            /* ── "Try Again" resets & closes ───── */
            $modal.on('click', '.spf-modal-btn--reset', function () {
                hideModal($modal);
                resetForm($form);
            });

            /* ── Inline "Try Again" — hide message & reset ── */
            $inline.on('click', '.spf-inline-btn--reset', function () {
                hideInlineConfirmation($form, $inline);
                resetForm($form);
            });

            /* ── Escape key ────────────────────── */
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $modal.is(':visible')) {
                    hideModal($modal);
                }
            });
        });
    }

    /* ══════════════════════════════════════════
     * Validation
     * ══════════════════════════════════════════ */

    /**
     * Validate all fields in the form.
     *
     * @param {jQuery} $form The form element.
     * @return {boolean} True if valid.
     */
    function validateForm($form) {
        var valid = true;

        $form.find('.spf-field-group').each(function () {
            var $group = $(this);
            var fieldType = $group.data('field-type');
            var $error = $group.find('.spf-error-message');

            // Skip hidden conditional fields
            if ($group.hasClass('spf-field-hidden')) return;

            // Reset previous state
            $group.find('.spf-invalid').removeClass('spf-invalid');
            $group.removeClass('spf-group-invalid');
            $error.text('').removeClass('spf-visible');

            switch (fieldType) {

                /* ── Checkbox group ─────────────── */
                case 'checkbox':
                    var isRequired = $group.find('.spf-checkbox-group').data('required');
                    if (isRequired && $group.find('.spf-checkbox:checked').length === 0) {
                        valid = false;
                        $group.addClass('spf-group-invalid');
                        $error.text('Please select at least one option.').addClass('spf-visible');
                    }
                    break;

                /* ── Radio group ───────────────── */
                case 'radio':
                    var $radios = $group.find('.spf-radio');
                    if ($radios.first().prop('required') && $group.find('.spf-radio:checked').length === 0) {
                        valid = false;
                        $group.addClass('spf-group-invalid');
                        $error.text('Please select an option.').addClass('spf-visible');
                    }
                    break;

                /* ── All single-value fields ───── */
                default:
                    var $field = $group.find('select, input, textarea').first();
                    if ($field.length && $field.prop('required') && !$field.val()) {
                        valid = false;
                        $field.addClass('spf-invalid');
                        $error.text('This field is required.').addClass('spf-visible');
                    }
                    // Email format check
                    if ($field.length && $field.attr('type') === 'email' && $field.val()) {
                        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailPattern.test($field.val())) {
                            valid = false;
                            $field.addClass('spf-invalid');
                            $error.text('Please enter a valid email address.').addClass('spf-visible');
                        }
                    }
                    break;
            }
        });

        return valid;
    }

    /* ══════════════════════════════════════════
     * Modal helpers
     * ══════════════════════════════════════════ */
    function showModal($modal, message) {
        $modal.find('.spf-modal-body').html(message);
        $modal.removeAttr('hidden').addClass('spf-modal-visible');

        // Focus trap — move focus into modal
        $modal.find('.spf-modal-close').trigger('focus');
    }

    function hideModal($modal) {
        $modal.removeClass('spf-modal-visible');
        setTimeout(function () {
            $modal.attr('hidden', '');
        }, 300);
    }

    /* ══════════════════════════════════════════
     * Inline Confirmation helpers
     * ══════════════════════════════════════════ */
    function showInlineConfirmation($form, $inline, message) {
        $inline.find('.spf-inline-body').html(message);
        $form.slideUp(300, function () {
            $inline.removeAttr('hidden').slideDown(300, function () {
                // Scroll to the confirmation message
                $('html, body').animate({
                    scrollTop: $inline.offset().top - 100
                }, 600);
            });
        });
    }

    function hideInlineConfirmation($form, $inline) {
        $inline.slideUp(300, function () {
            $inline.attr('hidden', '');
            $form.slideDown(300);
        });
    }

    function resetForm($form) {
        $form[0].reset();
        $form.find('.spf-invalid').removeClass('spf-invalid');
        $form.find('.spf-group-invalid').removeClass('spf-group-invalid');
        $form.find('.spf-error-message').text('').removeClass('spf-visible');
    }

    /**
     * Basic HTML escaping for user-facing content.
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    /* ══════════════════════════════════════════
     * Conditional Logic Evaluation
     * ══════════════════════════════════════════ */

    /**
     * Evaluate conditional logic for all fields in a form.
     * Shows/hides fields based on their conditions.
     *
     * @param {jQuery} $form The form element.
     */
    function evaluateConditionalLogic($form) {
        $form.find('.spf-field-group[data-conditionals]').each(function () {
            var $group       = $(this);
            var condType     = $group.data('conditional-type') || 'show';
            var conditionals = $group.data('conditionals');

            if (!conditionals || !conditionals.length) return;

            var allMatch = true;
            for (var i = 0; i < conditionals.length; i++) {
                var cond     = conditionals[i];
                var fieldKey = cond.field_key;
                var operator = cond.operator;
                var expected = cond.value || '';

                // Get the current value of the referenced field
                var fieldVal = getFieldValue($form, fieldKey);

                var match = false;
                switch (operator) {
                    case 'is':
                        match = (fieldVal === expected);
                        break;
                    case 'is_not':
                        match = (fieldVal !== expected);
                        break;
                    case 'contains':
                        match = (fieldVal.indexOf(expected) !== -1);
                        break;
                    case 'not_empty':
                        match = (fieldVal !== '');
                        break;
                    case 'empty':
                        match = (fieldVal === '');
                        break;
                    default:
                        match = (fieldVal === expected);
                }

                if (!match) {
                    allMatch = false;
                    break;
                }
            }

            // Determine visibility: "show" = visible when matched; "hide" = hidden when matched
            var shouldShow = (condType === 'show') ? allMatch : !allMatch;

            if (shouldShow) {
                $group.slideDown(200).removeClass('spf-field-hidden');
                $group.find('select, input, textarea').prop('disabled', false);
            } else {
                $group.slideUp(200).addClass('spf-field-hidden');
                // Disable hidden fields so they don't get submitted
                $group.find('select, input, textarea').prop('disabled', true);
            }
        });
    }

    /**
     * Get the current value of a field by its field_key.
     *
     * @param {jQuery} $form
     * @param {string} fieldKey
     * @return {string}
     */
    function getFieldValue($form, fieldKey) {
        // Try single-value fields first
        var $input = $form.find('[name="' + fieldKey + '"]');

        if ($input.length === 0) {
            // Try checkbox array
            $input = $form.find('[name="' + fieldKey + '[]"]');
            if ($input.length) {
                var vals = [];
                $input.filter(':checked').each(function () {
                    vals.push($(this).val());
                });
                return vals.join(', ');
            }
            return '';
        }

        // Radio buttons
        if ($input.attr('type') === 'radio') {
            return $input.filter(':checked').val() || '';
        }

        // Checkbox (single)
        if ($input.attr('type') === 'checkbox') {
            return $input.is(':checked') ? $input.val() : '';
        }

        return $input.val() || '';
    }

    /* ══════════════════════════════════════════
     * Dynamic last-field spanning
     *
     * Reads the actual rendered column count so it adapts
     * to Elementor responsive breakpoints and window resizes.
     * ══════════════════════════════════════════ */
    function adjustLastFieldSpan() {
        $('.spf-fields-grid').each(function () {
            var grid    = this;
            var $grid   = $(grid);
            var $fields = $grid.children('.spf-field-group:visible');
            var $inline = $grid.children('.spf-submit-group--inline');

            // Reset previous spans
            $fields.css('grid-column', '');
            $inline.css('grid-column', '');

            if (!$inline.length) { return; }

            // Actual rendered columns from computed style
            var cols = window.getComputedStyle(grid).gridTemplateColumns.split(' ').length;

            if (cols <= 1) { return; }

            // Pin button to last column
            $inline.css('grid-column-end', '-1');

            var totalItems    = $fields.length + 1; // fields + button
            var onLastRow     = totalItems % cols;
            if (onLastRow === 0) { onLastRow = cols; }

            // Stretch the last visible field to fill remaining cells
            if (onLastRow > 1 && onLastRow < cols) {
                var span = cols - onLastRow + 1;
                $fields.last().css('grid-column', 'span ' + span);
            }
        });
    }

    /* ══════════════════════════════════════════
     * Elementor wrapper overrides
     * ══════════════════════════════════════════ */
    function applyElementorOverrides() {
        $('.spf-elementor-wrap').each(function () {
            var $wrap = $(this);
            var $form = $wrap.find('.spf-form-wrapper');

            if (!$form.length) {
                return;
            }

            // Columns
            var cols = $wrap.data('columns');
            if (cols) {
                $form.find('.spf-fields-grid')
                    .removeClass('spf-fields-grid--1 spf-fields-grid--2 spf-fields-grid--3 spf-fields-grid--4')
                    .addClass('spf-fields-grid--' + cols);
            }

            // Button position
            var btnPos = $wrap.data('btn-position');
            if (btnPos) {
                $form.find('.spf-submit-group')
                    .removeClass('spf-submit-group--left spf-submit-group--center spf-submit-group--right spf-submit-group--full spf-submit-group--inline')
                    .addClass('spf-submit-group--' + btnPos);

                // Inline: move submit into grid; otherwise move it out
                var $grid = $form.find('.spf-fields-grid');
                var $submit = $form.find('.spf-submit-group');
                if (btnPos === 'inline') {
                    $grid.append($submit);
                } else if ($submit.parent().hasClass('spf-fields-grid')) {
                    $grid.after($submit);
                }
            }

            // Button text
            var btnText = $wrap.data('btn-text');
            if (btnText) {
                $form.find('.spf-btn-text').text(btnText);
            }

            // Hide labels
            var hideLabels = $wrap.data('hide-labels');
            if (hideLabels) {
                $form.addClass('spf-labels-hidden');
            }
        });
    }

    /* ── Bootstrap ─────────────────────────── */
    $(document).ready(function () {
        init();
        applyElementorOverrides();
        adjustLastFieldSpan();

        var resizeTimer;
        $(window).on('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(adjustLastFieldSpan, 150);
        });
    });

})(jQuery);
