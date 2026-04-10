/**
 * Smart Programme Finder - Admin JS.
 *
 * Handles the WPForms-style builder UI interactions:
 *   - Sidebar panel switching (Fields / Settings)
 *   - Tab switching within Fields panel
 *   - Settings sub-tab switching (content in right panel)
 *   - AJAX field add / delete / update (no page reload)
 *   - Field click-to-edit in preview
 *   - Conditional logic toggle + condition row add/remove
 *   - Field type toggle for options row
 *   - WordPress color picker initialisation
 *
 * @package SmartProgrammeFinder
 */

/* global jQuery, spfBuilder */
(function ($) {
    'use strict';

    var optionTypes = ['select', 'radio', 'checkbox'];

    /* Dirty-state tracking — true when there are unsaved field changes */
    var isDirty = false;

    function setDirty(dirty) {
        isDirty = dirty;
        if (dirty) {
            $('.spf-topbar-btn--save').addClass('spf-has-changes');
        } else {
            $('.spf-topbar-btn--save').removeClass('spf-has-changes');
        }
    }

    $(document).ready(function () {

        var $builder = $('#spf-builder');

        if ($builder.length) {
            initBuilderNav();
            initAjaxFields();
            initFieldClickEdit();
            initApplyField();
            initAjaxFieldDelete();
            initAjaxFieldDuplicate();
            initConfirmationLogic();
            initFieldTypeToggle();
            initChoiceEvents();
            initFieldConditionLogic();
            initSortablePreview();
            initDraggableAddFields();

            /* Warn before leaving with unsaved field changes */
            $(window).on('beforeunload', function () {
                if (isDirty) {
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });

            /* On top-level Save: AJAX save — no page reload */
            $(document).on('submit', '#spf-settings-form', function (e) {
                e.preventDefault();

                if (typeof spfBuilder === 'undefined') return;

                var $form   = $(this);
                var $btn    = $('.spf-topbar-btn--save');
                var formData = $form.serializeArray();

                /* Append all current fieldsData */
                var fields = getFieldsData();
                for (var i = 0; i < fields.length; i++) {
                    var f    = fields[i];
                    var base = 'spf_fields[' + i + ']';
                    var props = {
                        id:                f.id,
                        field_key:         f.field_key || '',
                        label:             f.label || '',
                        type:              f.type || 'text',
                        size:              f.size || 'medium',
                        placeholder:       f.placeholder || '',
                        required:          f.required ? '1' : '0',
                        options:           Array.isArray(f.options) ? f.options.join(', ') : '',
                        description:       f.description || '',
                        css_class:         f.css_class || '',
                        hide_label:        f.hide_label ? '1' : '0',
                        default_value:     f.default_value || '',
                        input_columns:     f.input_columns || '',
                        conditional_logic: f.conditional_logic ? '1' : '0',
                        conditional_type:  f.conditional_type || 'show',
                        conditionals:      JSON.stringify(f.conditionals || [])
                    };
                    for (var key in props) {
                        formData.push({ name: base + '[' + key + ']', value: props[key] });
                    }
                }

                /* Add action + nonce */
                formData.push({ name: 'action', value: 'spf_ajax_save_form' });
                formData.push({ name: 'nonce',  value: spfBuilder.nonce });
                formData.push({ name: 'form_id', value: $('#spf-builder').data('form-id') });

                $btn.prop('disabled', true).find('.dashicons')
                    .removeClass('dashicons-saved').addClass('dashicons-update spf-spin');

                $.post(spfBuilder.ajaxUrl, formData, function (res) {
                    $btn.prop('disabled', false).find('.dashicons')
                        .removeClass('dashicons-update spf-spin').addClass('dashicons-saved');

                    if (res.success) {
                        /* Update topbar form name if it changed */
                        var newName = $form.find('[name="spf_form_name"]').val();
                        if (newName) {
                            $('.spf-topbar-title strong').text(newName);
                        }

                        setDirty(false);
                        $(window).off('beforeunload');

                        /* Brief "Saved!" text feedback */
                        var $label = $btn.contents().filter(function () {
                            return this.nodeType === 3; /* text node */
                        });
                        var origText = $label.text().trim();
                        $label[0].nodeValue = ' Saved!';
                        setTimeout(function () {
                            $label[0].nodeValue = ' ' + origText;
                        }, 1500);
                    }
                }).fail(function () {
                    $btn.prop('disabled', false).find('.dashicons')
                        .removeClass('dashicons-update spf-spin').addClass('dashicons-saved');
                });
            });
        }

        /* Color Pickers */
        if ($.fn.wpColorPicker && $('.spf-color-picker').length) {
            $('.spf-color-picker').wpColorPicker();
        }

        /* Forms Overview: toggle Add New panel */
        $(document).on('click', '#spf-toggle-new-form', function (e) {
            e.preventDefault();
            var $panel = $('#spf-new-form');
            $panel.slideToggle(200, function () {
                if ($panel.is(':visible')) {
                    $panel.find('input[name="spf_form_name"]').focus();
                }
            });
        });
        $(document).on('click', '.spf-cancel-new-form', function () {
            $('#spf-new-form').slideUp(200);
        });
    });

    /* -------------------------------------------
     * Builder Navigation
     * ----------------------------------------- */
    function initBuilderNav() {

        /* Sidebar icon clicks: Fields / Settings */
        $(document).on('click', '.spf-sidebar-btn', function (e) {
            e.preventDefault();
            var panel = $(this).data('panel');

            /* Highlight sidebar button */
            $('.spf-sidebar-btn').removeClass('active');
            $(this).addClass('active');

            /* Show corresponding left panel */
            $('.spf-builder-panel').hide();
            $('#spf-panel-' + panel).show();

            /* Toggle right panel content */
            if (panel === 'settings') {
                $('#spf-preview-fields-content').hide();
                $('#spf-settings-content').show();

                /* Ensure at least one stab-content is visible */
                if ($('.spf-stab-content:visible').length === 0) {
                    $('.spf-subnav-link').removeClass('active');
                    $('.spf-subnav-link[data-stab="general"]').addClass('active');
                    $('#spf-stab-general').show();
                }

                /* Init color pickers that may not have been inited yet */
                if ($.fn.wpColorPicker) {
                    $('#spf-settings-content .spf-color-picker').not('.wp-color-picker').each(function () {
                        $(this).wpColorPicker();
                    });
                }
            } else {
                $('#spf-settings-content').hide();
                $('#spf-preview-fields-content').show();
            }
        });

        /* Fields panel tab switching: Add Fields / Field Options */
        $(document).on('click', '.spf-panel-tab', function (e) {
            e.preventDefault();
            var tab = $(this).data('tab');

            $('.spf-panel-tab').removeClass('active');
            $(this).addClass('active');

            $('.spf-panel-content').hide();
            $('#spf-tab-' + tab).show();
        });

        /* Settings sub-nav switching: General / Confirmations / Appearance */
        $(document).on('click', '.spf-subnav-link', function (e) {
            e.preventDefault();
            var stab = $(this).data('stab');

            /* Ensure Settings right panel is visible */
            $('#spf-preview-fields-content').hide();
            $('#spf-settings-content').show();

            /* Ensure the left settings panel & sidebar button are active */
            $('.spf-builder-panel').hide();
            $('#spf-panel-settings').show();
            $('.spf-sidebar-btn').removeClass('active');
            $('.spf-sidebar-btn[data-panel="settings"]').addClass('active');

            $('.spf-subnav-link').removeClass('active');
            $(this).addClass('active');

            $('.spf-stab-content').hide();
            $('#spf-stab-' + stab).show();

            /* Update hidden input so Save knows which tab was active */
            $('#spf-active-stab').val(stab);

            /* Init color pickers on appearance tab */
            if (stab === 'appearance' && $.fn.wpColorPicker) {
                $('#spf-stab-appearance .spf-color-picker').not('.wp-color-picker').each(function () {
                    $(this).wpColorPicker();
                });
            }
        });
    }

    /* -------------------------------------------
     * AJAX Add Field
     * ----------------------------------------- */
    function initAjaxFields() {
        $(document).on('click', '.spf-add-field-btn', function (e) {
            e.preventDefault();

            if (typeof spfBuilder === 'undefined') return;

            var $btn = $(this);
            var type = $btn.data('type');
            var formId = $('#spf-builder').data('form-id');

            $btn.prop('disabled', true).addClass('spf-loading');

            $.post(spfBuilder.ajaxUrl, {
                action: 'spf_ajax_add_field',
                nonce: spfBuilder.nonce,
                form_id: formId,
                field_type: type
            }, function (res) {
                $btn.prop('disabled', false).removeClass('spf-loading');

                if (res.success) {
                    /* Hide empty state, show grid + submit */
                    $('#spf-preview-empty').hide();
                    $('#spf-preview-fields-grid').show().append(res.data.preview_html);
                    $('#spf-preview-submit').show();

                    /* Update the local fields data */
                    var fields = getFieldsData();
                    fields.push(res.data.field);
                    setFieldsData(fields);

                    /* Switch to Field Options tab and select new field */
                    loadFieldOptions(res.data.field);

                    /* Refresh sortable so new field is draggable */
                    if ($.fn.sortable && $('#spf-preview-fields-grid').data('ui-sortable')) {
                        $('#spf-preview-fields-grid').sortable('refresh');
                    }

                    /* Flash the new field */
                    var $n = $('#spf-preview-fields-grid .spf-preview-field[data-field-id="' + res.data.field.id + '"]');
                    $n.addClass('spf-preview-field--flash');
                    setTimeout(function () { $n.removeClass('spf-preview-field--flash'); }, 800);

                    setDirty(true);
                }
            }).fail(function () {
                $btn.prop('disabled', false).removeClass('spf-loading');
            });
        });
    }

    /* -------------------------------------------
     * Click Field in Preview -> Edit
     * ----------------------------------------- */
    function initFieldClickEdit() {
        $(document).on('click', '.spf-preview-field-link', function (e) {
            e.preventDefault();
            var fieldId = $(this).data('field-id');
            var fields = getFieldsData();
            var field = null;

            for (var i = 0; i < fields.length; i++) {
                if (parseInt(fields[i].id) === parseInt(fieldId)) {
                    field = fields[i];
                    break;
                }
            }

            if (!field) return;

            /* Highlight active field in preview */
            $('.spf-preview-field').removeClass('spf-preview-field--active');
            $(this).closest('.spf-preview-field').addClass('spf-preview-field--active');

            /* Load field options */
            loadFieldOptions(field);
        });
    }

    /**
     * Populate the Field Options form from field data with General / Advanced / Smart Logic sub-tabs.
     */
    function loadFieldOptions(field) {
        var $wrap = $('#spf-field-options-wrap');
        var formId = $('#spf-builder').data('form-id');
        var typeLabels = {
            'select': 'Dropdown', 'text': 'Single Line Text', 'email': 'Email',
            'number': 'Numbers', 'textarea': 'Paragraph Text', 'radio': 'Multiple Choice', 'checkbox': 'Checkboxes'
        };
        var typeLabel = typeLabels[field.type] || field.type;
        var isOptionType = optionTypes.indexOf(field.type) !== -1;
        var isChoiceType = (field.type === 'radio' || field.type === 'checkbox');

        var html = '<form method="post" class="spf-field-options-form" id="spf-field-options-form" data-field-id="' + field.id + '">' +
            '<input type="hidden" name="spf_field_id" value="' + field.id + '">' +
            '<input type="hidden" name="spf_form_id" value="' + formId + '">' +

            /* Header */
            '<div class="spf-fo-header">' +
            '<span class="spf-fo-header-type">' + escAttr(typeLabel) + '</span>' +
            '<span class="spf-fo-header-id">(ID #' + field.id + ')</span>' +
            '</div>' +

            /* Hidden type field (used by save and toggle logic) */
            '<input type="hidden" name="spf_field_type" id="spf_field_type" value="' + escAttr(field.type) + '">' +

            /* Sub-tab navigation */
            '<div class="spf-fo-tabs">' +
            '<button type="button" class="spf-fo-tab spf-fo-tab--active" data-fo-tab="general">General</button>' +
            '<button type="button" class="spf-fo-tab" data-fo-tab="advanced">Advanced</button>' +
            '<button type="button" class="spf-fo-tab" data-fo-tab="smart-logic">Smart Logic</button>' +
            '</div>' +

            /* ── General pane ────────── */
            '<div class="spf-fo-pane spf-fo-pane--active" data-fo-pane="general">' +

            '<div class="spf-option-row">' +
            '<label>Label <span class="spf-opt-tooltip" title="Enter text for the form field label. Labels can be hidden in the Advanced tab.">?</span></label>' +
            '<input type="text" name="spf_field_label" value="' + escAttr(field.label) + '" required>' +
            '</div>' +

            '<div class="spf-option-row" id="spf-options-row"' + (!isOptionType ? ' style="display:none"' : '') + '>' +
            '<label>Choices <span class="spf-opt-tooltip" title="Add choices for the form field.">?</span></label>' +
            '<div class="spf-choices-list">' +
            buildChoicesHtml(field) +
            '</div>' +
            '<button type="button" class="button button-small spf-choice-add">+ Add Choice</button>' +
            '</div>' +

            '<div class="spf-option-row">' +
            '<label>Description <span class="spf-opt-tooltip" title="Enter text for the field description shown below the field.">?</span></label>' +
            '<textarea name="spf_field_description" rows="2" placeholder="Help text shown below the field">' + escAttr(field.description || '') + '</textarea>' +
            '</div>' +

            '<div class="spf-option-row spf-option-row--toggle">' +
            '<label><input type="checkbox" name="spf_field_required" value="1"' + (field.required ? ' checked' : '') + '>' +
            ' Required <span class="spf-opt-tooltip" title="A form will not submit unless all required fields are provided.">?</span></label>' +
            '</div>' +

            '</div>' +

            /* ── Advanced pane ────────── */
            '<div class="spf-fo-pane" data-fo-pane="advanced">' +

            '<div class="spf-option-row">' +
            '<label>Field Size <span class="spf-opt-tooltip" title="Select the default field size.">?</span></label>' +
            '<select name="spf_field_size">' +
            '<option value="small"' + (field.size === 'small' ? ' selected' : '') + '>Small</option>' +
            '<option value="medium"' + (field.size === 'medium' || !field.size ? ' selected' : '') + '>Medium</option>' +
            '<option value="large"' + (field.size === 'large' ? ' selected' : '') + '>Large</option>' +
            '</select>' +
            '</div>' +

            '<div class="spf-option-row">' +
            '<label>Placeholder Text <span class="spf-opt-tooltip" title="Text shown inside the field before the user types.">?</span></label>' +
            '<input type="text" name="spf_field_placeholder" value="' + escAttr(field.placeholder || '') + '">' +
            '</div>' +

            '<div class="spf-option-row"' + (!isOptionType && field.type !== 'textarea' ? '' : ' style="display:none"') + '>' +
            '<label>Default Value <span class="spf-opt-tooltip" title="Pre-fill the field with a default value.">?</span></label>' +
            '<input type="text" name="spf_field_default_value" value="' + escAttr(field.default_value || '') + '">' +
            '</div>' +

            '<div class="spf-option-row" id="spf-choice-layout-row"' + (!isChoiceType ? ' style="display:none"' : '') + '>' +
            '<label>Choice Layout <span class="spf-opt-tooltip" title="Select how choices are displayed.">?</span></label>' +
            '<select name="spf_field_input_columns">' +
            '<option value=""' + (!field.input_columns ? ' selected' : '') + '>One Column</option>' +
            '<option value="2"' + (field.input_columns === '2' ? ' selected' : '') + '>Two Columns</option>' +
            '<option value="3"' + (field.input_columns === '3' ? ' selected' : '') + '>Three Columns</option>' +
            '<option value="inline"' + (field.input_columns === 'inline' ? ' selected' : '') + '>Inline</option>' +
            '</select>' +
            '</div>' +

            '<div class="spf-option-row spf-option-row--separator"></div>' +

            '<div class="spf-option-row">' +
            '<label>CSS Classes <span class="spf-opt-tooltip" title="Enter CSS class names for the field container. Separate multiple classes with spaces.">?</span></label>' +
            '<input type="text" name="spf_field_css_class" value="' + escAttr(field.css_class || '') + '" placeholder="e.g. my-custom-class">' +
            '</div>' +

            '<div class="spf-option-row spf-option-row--toggle">' +
            '<label><input type="checkbox" name="spf_field_hide_label" value="1"' + (field.hide_label ? ' checked' : '') + '>' +
            ' Hide Label <span class="spf-opt-tooltip" title="Hide the field label (still accessible to screen readers).">?</span></label>' +
            '</div>' +

            '<div class="spf-option-row spf-option-row--meta">' +
            '<span class="spf-opt-meta-label">Field Key</span>' +
            '<code class="spf-opt-meta-value">' + escAttr(field.field_key) + '</code>' +
            '</div>' +

            '</div>' +

            /* ── Smart Logic pane ────────── */
            '<div class="spf-fo-pane" data-fo-pane="smart-logic">' +
            '<p class="spf-fo-pane-desc">Configure conditional logic to show or hide this field based on other field values.</p>' +
            '<div class="spf-option-row spf-option-row--toggle">' +
            '<label><input type="checkbox" class="spf-field-cond-toggle" name="spf_field_conditional" value="1"' + (field.conditional_logic ? ' checked' : '') + '> Enable Conditional Logic</label>' +
            '</div>' +
            '<div class="spf-field-conditions-wrap"' + (field.conditional_logic ? '' : ' style="display:none"') + '>' +
            '<div class="spf-option-row">' +
            '<select name="spf_field_conditional_type" class="spf-field-cond-type">' +
            '<option value="show"' + (field.conditional_type === 'show' || !field.conditional_type ? ' selected' : '') + '>Show this field if</option>' +
            '<option value="hide"' + (field.conditional_type === 'hide' ? ' selected' : '') + '>Hide this field if</option>' +
            '</select>' +
            '</div>' +
            '<div class="spf-field-condition-rows">' +
            buildFieldConditionRows(field) +
            '</div>' +
            '<button type="button" class="button spf-field-cond-add-row">+ Add Condition</button>' +
            '</div>' +
            '</div>' +

            '<div class="spf-option-actions">' +
            '<button type="button" class="button button-primary spf-apply-field-btn">Apply</button>' +
            '</div>' +
            '</form>';

        $wrap.html(html);

        /* Switch to Field Options tab */
        $('.spf-panel-tab').removeClass('active');
        $('.spf-panel-tab[data-tab="field-options"]').addClass('active');
        $('.spf-panel-content').hide();
        $('#spf-tab-field-options').show();

        /* Init the type toggle and sub-tab switching */
        initFieldTypeToggle();
        initFieldOptionsTabs();
        initTooltips();

        /* Init sortable on choices */
        if ($.fn.sortable && $('.spf-choices-list').length) {
            $('.spf-choices-list').sortable({
                handle: '.spf-choice-drag',
                axis: 'y',
                containment: 'parent',
                tolerance: 'pointer'
            });
        }

        /* Highlight field in preview */
        $('.spf-preview-field').removeClass('spf-preview-field--active');
        $('.spf-preview-field[data-field-id="' + field.id + '"]').addClass('spf-preview-field--active');
    }

    /* -------------------------------------------
     * Apply Field (local-state update only, no AJAX)
     * Changes are held in memory until the top Save is pressed.
     * ----------------------------------------- */
    function initApplyField() {
        $(document).on('click', '.spf-apply-field-btn', function () {
            var $form = $('#spf-field-options-form');
            if (!$form.length) return;

            var fieldId = parseInt($form.data('field-id'));

            /* Collect choices as an array */
            var choicesRaw = collectChoices(); // comma-separated string
            var optionsArr = choicesRaw
                ? choicesRaw.split(',').map(function (v) { return v.trim(); }).filter(Boolean)
                : [];

            var updatedField = {
                id:                fieldId,
                label:             $form.find('[name="spf_field_label"]').val() || '',
                type:              $form.find('[name="spf_field_type"]').val() || 'text',
                size:              $form.find('[name="spf_field_size"]').val() || 'medium',
                placeholder:       $form.find('[name="spf_field_placeholder"]').val() || '',
                required:          $form.find('[name="spf_field_required"]').is(':checked'),
                options:           optionsArr,
                description:       $form.find('[name="spf_field_description"]').val() || '',
                css_class:         $form.find('[name="spf_field_css_class"]').val() || '',
                hide_label:        $form.find('[name="spf_field_hide_label"]').is(':checked'),
                default_value:     $form.find('[name="spf_field_default_value"]').val() || '',
                input_columns:     $form.find('[name="spf_field_input_columns"]').val() || '',
                conditional_logic: $form.find('[name="spf_field_conditional"]').is(':checked'),
                conditional_type:  $form.find('[name="spf_field_conditional_type"]').val() || 'show',
                conditionals:      collectFieldConditions()
            };

            /* Merge into fieldsData, preserving form_id and field_key */
            var fields = getFieldsData();
            for (var i = 0; i < fields.length; i++) {
                if (parseInt(fields[i].id) === fieldId) {
                    updatedField.form_id   = fields[i].form_id;
                    updatedField.field_key = fields[i].field_key;
                    fields[i] = updatedField;
                    break;
                }
            }
            setFieldsData(fields);

            /* Update the preview card in the DOM */
            updatePreviewCard(updatedField);

            /* Mark unsaved changes */
            setDirty(true);

            /* Brief "Applied!" feedback */
            var $btn = $(this);
            $btn.prop('disabled', true).text('Applied!');
            setTimeout(function () {
                $btn.prop('disabled', false).text('Apply');
            }, 1000);
        });
    }

    /* Update a preview card's label and mockup without a page reload */
    function updatePreviewCard(field) {
        var $card = $('.spf-preview-field[data-field-id="' + field.id + '"]');
        if (!$card.length) return;

        var reqHtml = field.required ? ' <span class="spf-preview-req">*</span>' : '';
        $card.find('.spf-preview-label').html(escAttr(field.label) + reqHtml);
        $card.find('.spf-preview-field-mockup').html(buildMockupHtml(field));
    }

    /* Client-side mirror of PHP render_field_mockup() */
    function buildMockupHtml(field) {
        var type = field.type || 'text';
        var ph   = escAttr(field.placeholder || field.label);

        if (type === 'select') {
            return '<div class="spf-mockup-select"><span>' + ph + '</span>' +
                   '<span class="dashicons dashicons-arrow-down-alt2"></span></div>';
        }
        if (type === 'textarea') {
            return '<div class="spf-mockup-textarea">' + ph + '</div>';
        }
        if (type === 'radio' || type === 'checkbox') {
            var cls      = type === 'checkbox' ? 'spf-mockup-check' : 'spf-mockup-radio';
            var shapeCls = type === 'checkbox' ? 'spf-mockup-square' : 'spf-mockup-circle';
            var opts     = field.options || [];
            var html     = '<div class="spf-mockup-choices">';
            var limit    = Math.min(opts.length, 3);
            for (var i = 0; i < limit; i++) {
                html += '<label class="' + cls + '"><span class="' + shapeCls + '"></span>' + escAttr(opts[i]) + '</label>';
            }
            html += '</div>';
            return html;
        }
        return '<div class="spf-mockup-input">' + ph + '</div>';
    }

    /* -------------------------------------------
     * Sortable Preview Fields (drag to reorder)
     * ----------------------------------------- */
    function initSortablePreview() {
        var $grid = $('#spf-preview-fields-grid');
        if (!$grid.length || !$.fn.sortable) return;

        $grid.sortable({
            handle: '.spf-preview-field-drag',
            items: '> .spf-preview-field',
            placeholder: 'spf-sortable-placeholder',
            tolerance: 'pointer',
            cursor: 'grabbing',
            opacity: 0.8,
            start: function (e, ui) {
                ui.placeholder.height(ui.item.outerHeight());
            },
            stop: function () {
                saveFieldOrder();
            }
        });
    }

    function saveFieldOrder() {
        if (typeof spfBuilder === 'undefined') return;

        var order = [];
        $('#spf-preview-fields-grid .spf-preview-field').each(function () {
            order.push($(this).data('field-id'));
        });

        /* Reorder the local fields data to match the new drag order */
        var fields = getFieldsData();
        var reordered = [];
        for (var i = 0; i < order.length; i++) {
            for (var j = 0; j < fields.length; j++) {
                if (parseInt(fields[j].id) === parseInt(order[i])) {
                    reordered.push(fields[j]);
                    break;
                }
            }
        }
        setFieldsData(reordered);
        setDirty(true);
    }

    /* -------------------------------------------
     * Draggable Add-Field Buttons -> Preview
     * ----------------------------------------- */
    function initDraggableAddFields() {
        if (!$.fn.draggable || !$.fn.droppable) return;

        /* Make each "Add Field" button draggable */
        $('.spf-add-field-btn').draggable({
            helper: 'clone',
            appendTo: 'body',
            zIndex: 10000,
            cursor: 'grabbing',
            cursorAt: { top: 20, left: 60 },
            start: function (e, ui) {
                ui.helper.addClass('spf-drag-helper');
                $('#spf-preview-fields-grid').addClass('spf-drop-active');
            },
            stop: function () {
                $('#spf-preview-fields-grid').removeClass('spf-drop-active');
            }
        });

        /* Make the preview grid droppable */
        $('#spf-preview-fields-grid').droppable({
            accept: '.spf-add-field-btn',
            hoverClass: 'spf-drop-hover',
            tolerance: 'pointer',
            drop: function (e, ui) {
                var type = ui.draggable.data('type');
                if (!type) return;

                /* Trigger the same AJAX add as clicking the button */
                ui.draggable.trigger('click');
            }
        });

        /* Also make the empty-state area droppable */
        $('#spf-preview-empty').droppable({
            accept: '.spf-add-field-btn',
            hoverClass: 'spf-drop-hover',
            tolerance: 'pointer',
            drop: function (e, ui) {
                var type = ui.draggable.data('type');
                if (!type) return;
                ui.draggable.trigger('click');
            }
        });
    }

    /* -------------------------------------------
     * AJAX Delete Field
     * ----------------------------------------- */
    function initAjaxFieldDelete() {
        $(document).on('click', '.spf-preview-field-delete', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (typeof spfBuilder === 'undefined') return;
            if (!confirm('Delete this field?')) return;

            var $el = $(this);
            var fieldId = $el.data('field-id');
            var $field = $el.closest('.spf-preview-field');

            $field.css('opacity', '0.5');

            $.post(spfBuilder.ajaxUrl, {
                action: 'spf_ajax_delete_field',
                nonce: spfBuilder.nonce,
                field_id: fieldId
            }, function (res) {
                if (res.success) {
                    $field.slideUp(200, function () {
                        $(this).remove();

                        /* Update local fields data */
                        var fields = getFieldsData();
                        fields = fields.filter(function (f) {
                            return parseInt(f.id) !== parseInt(fieldId);
                        });
                        setFieldsData(fields);

                        /* If no more fields, show empty state */
                        if ($('#spf-preview-fields-grid .spf-preview-field').length === 0) {
                            $('#spf-preview-empty').show();
                            $('#spf-preview-fields-grid').hide();
                            $('#spf-preview-submit').hide();
                        }

                        /* If the deleted field was being edited, reset options panel */
                        var $optForm = $('#spf-field-options-form');
                        if ($optForm.length && parseInt($optForm.data('field-id')) === parseInt(fieldId)) {
                            $('#spf-field-options-wrap').html(
                                '<div class="spf-no-selection">' +
                                '<span class="dashicons dashicons-edit-large"></span>' +
                                '<p>Click a field in the preview to edit its options.</p>' +
                                '</div>'
                            );
                        }

                        setDirty(true);
                    });
                } else {
                    $field.css('opacity', '1');
                }
            }).fail(function () {
                $field.css('opacity', '1');
            });
        });
    }

    /* -------------------------------------------
     * AJAX Duplicate Field
     * ----------------------------------------- */
    function initAjaxFieldDuplicate() {
        $(document).on('click', '.spf-preview-field-duplicate', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (typeof spfBuilder === 'undefined') return;

            var $el = $(this);
            var fieldId = $el.data('field-id');
            var $field = $el.closest('.spf-preview-field');

            $el.find('.dashicons').removeClass('dashicons-admin-page').addClass('dashicons-update spf-spin');

            $.post(spfBuilder.ajaxUrl, {
                action: 'spf_ajax_duplicate_field',
                nonce: spfBuilder.nonce,
                field_id: fieldId,
                form_id: $('#spf-builder').data('form-id')
            }, function (res) {
                $el.find('.dashicons').removeClass('dashicons-update spf-spin').addClass('dashicons-admin-page');

                if (res.success) {
                    /* Insert the duplicate after the source field */
                    $field.after(res.data.preview_html);

                    /* Update local fields data */
                    var fields = getFieldsData();
                    var idx = -1;
                    for (var i = 0; i < fields.length; i++) {
                        if (parseInt(fields[i].id) === parseInt(fieldId)) {
                            idx = i;
                            break;
                        }
                    }
                    if (idx !== -1) {
                        fields.splice(idx + 1, 0, res.data.field);
                    } else {
                        fields.push(res.data.field);
                    }
                    setFieldsData(fields);

                    /* Refresh sortable */
                    if ($.fn.sortable && $('#spf-preview-fields-grid').data('ui-sortable')) {
                        $('#spf-preview-fields-grid').sortable('refresh');
                    }

                    /* Flash the new field */
                    var $n = $('.spf-preview-field[data-field-id="' + res.data.field.id + '"]');
                    $n.addClass('spf-preview-field--flash');
                    setTimeout(function () { $n.removeClass('spf-preview-field--flash'); }, 800);

                    setDirty(true);
                }
            }).fail(function () {
                $el.find('.dashicons').removeClass('dashicons-update spf-spin').addClass('dashicons-admin-page');
            });
        });
    }

    /* -------------------------------------------
     * Confirmation Conditional Logic
     * ----------------------------------------- */
    function initConfirmationLogic() {

        /* Toggle conditional logic visibility (new admin) */
        $(document).on('change', '.spf-conf-logic-toggle', function () {
            var $wrap = $(this).closest('.spf-conf-body, .spf-conf-card-body').find('.spf-conf-conditions');
            if ($(this).is(':checked')) {
                $wrap.slideDown(200);
            } else {
                $wrap.slideUp(200);
            }
        });

        /* Legacy toggle class */
        $(document).on('change', '.spf-conf-cond-toggle', function () {
            var $wrap = $(this).closest('.spf-conf-card-body').find('.spf-conf-conditions-wrap');
            if ($(this).is(':checked')) {
                $wrap.slideDown(200);
            } else {
                $wrap.slideUp(200);
            }
        });

        $(document).on('click', '#spf-add-conf-toggle', function () {
            $('#spf-add-conf-form').slideToggle(200);
        });

        /* Dynamic value dropdown: when field changes, populate choices */
        $(document).on('change', '.spf-cond-field', function () {
            var fieldKey = $(this).val();
            var $valueSelect = $(this).closest('.spf-condition-row, .spf-conf-condition-row').find('.spf-cond-value');
            populateCondValueSelect($valueSelect, fieldKey, '');
        });

        /* "And" button — clone row and reset */
        $(document).on('click', '.spf-cond-add', function () {
            var $container = $(this).closest('.spf-conditions-list, .spf-conf-condition-rows');
            var $row  = $(this).closest('.spf-condition-row, .spf-conf-condition-row');
            var $new  = $row.clone();
            var idx   = $container.find('.spf-condition-row, .spf-conf-condition-row').length;

            $new.find('[name]').each(function () {
                var name = $(this).attr('name');
                $(this).attr('name', name.replace(/\[\d+\]/, '[' + idx + ']'));
            });
            $new.find('.spf-cond-field').val('');
            $new.find('.spf-cond-op').val('is');
            /* Reset the value select to just the placeholder */
            var $valSelect = $new.find('.spf-cond-value');
            $valSelect.html('<option value="">\u2014 Select Choice \u2014</option>');
            $row.after($new);
            reindexConditions($container);
        });

        /* Remove condition row */
        $(document).on('click', '.spf-cond-remove', function () {
            var $container = $(this).closest('.spf-conditions-list, .spf-conf-condition-rows');
            if ($container.find('.spf-condition-row, .spf-conf-condition-row').length > 1) {
                $(this).closest('.spf-condition-row, .spf-conf-condition-row').remove();
                reindexConditions($container);
            }
        });

        /* "Add Condition" button */
        $(document).on('click', '.spf-add-condition-btn', function () {
            var $list = $(this).siblings('.spf-conditions-list');
            var $block = $(this).closest('.spf-confirmation-block');
            var ci = $block.data('index');
            var idx = $list.find('.spf-condition-row').length;
            var fields = getFieldsData();

            var fieldOpts = '<option value="">\u2014 Field \u2014</option>';
            for (var i = 0; i < fields.length; i++) {
                fieldOpts += '<option value="' + escAttr(fields[i].field_key) + '">' + escAttr(fields[i].label) + '</option>';
            }

            var rowHtml = '<div class="spf-condition-row">' +
                '<select name="spf_confirmations[' + ci + '][conditions][' + idx + '][field]" class="spf-cond-field">' + fieldOpts + '</select>' +
                '<select name="spf_confirmations[' + ci + '][conditions][' + idx + '][operator]" class="spf-cond-op">' +
                '<option value="is">is</option><option value="is_not">is not</option></select>' +
                '<select name="spf_confirmations[' + ci + '][conditions][' + idx + '][value]" class="spf-cond-value">' +
                '<option value="">\u2014 Select Choice \u2014</option></select>' +
                '<button type="button" class="spf-cond-add button-small" title="And">And</button>' +
                '<button type="button" class="spf-cond-remove" title="Remove"><span class="dashicons dashicons-trash"></span></button>' +
                '</div>';
            $list.append(rowHtml);
        });
    }

    /**
     * Populate a condition value <select> with choices from the given field.
     */
    function populateCondValueSelect($select, fieldKey, selectedValue) {
        var html = '<option value="">\u2014 Select Choice \u2014</option>';
        if (fieldKey) {
            var fields = getFieldsData();
            for (var i = 0; i < fields.length; i++) {
                if (fields[i].field_key === fieldKey) {
                    var options = fields[i].options || '';
                    if (typeof options === 'string' && options.length > 0) {
                        var choices = options.split(',');
                        for (var j = 0; j < choices.length; j++) {
                            var c = choices[j].trim();
                            if (c) {
                                var sel = (c === selectedValue) ? ' selected' : '';
                                html += '<option value="' + escAttr(c) + '"' + sel + '>' + escAttr(c) + '</option>';
                            }
                        }
                    } else if (Array.isArray(options)) {
                        for (var k = 0; k < options.length; k++) {
                            var o = String(options[k]).trim();
                            if (o) {
                                var s = (o === selectedValue) ? ' selected' : '';
                                html += '<option value="' + escAttr(o) + '"' + s + '>' + escAttr(o) + '</option>';
                            }
                        }
                    }
                    break;
                }
            }
        }
        $select.html(html);
    }

    function reindexConditions($container) {
        $container.find('.spf-condition-row, .spf-conf-condition-row').each(function (i) {
            $(this).find('[name]').each(function () {
                var name = $(this).attr('name');
                $(this).attr('name', name.replace(/\[\d+\]/, '[' + i + ']'));
            });
        });
    }

    /* -------------------------------------------
     * Field Options Sub-tab Switching
     * ----------------------------------------- */
    function initFieldOptionsTabs() {
        $('#spf-field-options-form').on('click', '.spf-fo-tab', function () {
            var tab = $(this).data('fo-tab');
            var $form = $(this).closest('#spf-field-options-form');
            $form.find('.spf-fo-tab').removeClass('spf-fo-tab--active');
            $(this).addClass('spf-fo-tab--active');
            $form.find('.spf-fo-pane').removeClass('spf-fo-pane--active');
            $form.find('.spf-fo-pane[data-fo-pane="' + tab + '"]').addClass('spf-fo-pane--active');
        });
    }

    /* -------------------------------------------
     * Field Type <-> Options Row Toggle
     * ----------------------------------------- */
    function initFieldTypeToggle() {
        var $typeSelect    = $('#spf_field_type');
        var $optionsRow    = $('#spf-options-row');
        var $choiceLayout  = $('#spf-choice-layout-row');
        var $defaultVal    = $('[name="spf_field_default_value"]').closest('.spf-option-row');
        var choiceTypes    = ['radio', 'checkbox'];

        if (!$typeSelect.length) return;

        function toggle() {
            var val = $typeSelect.val();
            var isOption = optionTypes.indexOf(val) !== -1;
            var isChoice = choiceTypes.indexOf(val) !== -1;

            if (isOption) { $optionsRow.slideDown(200); } else { $optionsRow.slideUp(200); }
            if (isChoice && $choiceLayout.length) { $choiceLayout.slideDown(200); } else if ($choiceLayout.length) { $choiceLayout.slideUp(200); }
            if (!isOption && val !== 'textarea' && $defaultVal.length) { $defaultVal.slideDown(200); } else if ($defaultVal.length) { $defaultVal.slideUp(200); }
        }

        toggle();
        $typeSelect.off('change.spfToggle').on('change.spfToggle', function () {
            toggle();
            refreshChoiceIndicators();
        });
    }

    /**
     * Choice list event handlers (add, remove, reorder).
     */
    function initChoiceEvents() {
        var $panel = $('#spf-field-options-wrap');

        /* Add choice via inline + button */
        $panel.on('click', '.spf-choice-add-btn', function () {
            var $row  = $(this).closest('.spf-choice-row');
            var type  = $('#spf_field_type').val();
            var indicator = (type === 'checkbox') ? 'checkbox' : 'radio';
            var idx   = $('.spf-choice-row').length;
            var $new  = $(buildSingleChoiceRow('', idx, indicator));
            $row.after($new);
            $new.find('.spf-choice-input').focus();
        });

        /* Add choice via bottom "Add Choice" button */
        $panel.on('click', '.spf-choice-add', function () {
            var type  = $('#spf_field_type').val();
            var indicator = (type === 'checkbox') ? 'checkbox' : 'radio';
            var idx   = $('.spf-choice-row').length;
            var $new  = $(buildSingleChoiceRow('', idx, indicator));
            $('.spf-choices-list').append($new);
            $new.find('.spf-choice-input').focus();
        });

        /* Remove choice (keep at least 1) */
        $panel.on('click', '.spf-choice-remove-btn', function () {
            var $rows = $('.spf-choice-row');
            if ($rows.length <= 1) return;
            $(this).closest('.spf-choice-row').remove();
        });

        /* Make choice list sortable */
        if ($.fn.sortable) {
            $panel.on('spf:choicesReady', function () {
                $('.spf-choices-list').sortable({
                    handle: '.spf-choice-drag',
                    axis: 'y',
                    containment: 'parent',
                    tolerance: 'pointer'
                });
            });
        }
    }

    /**
     * Initialise tooltips on option labels.
     */
    function initTooltips() {
        $('.spf-opt-tooltip').each(function () {
            var $tip = $(this);
            if ($tip.data('spf-tip-init')) return;
            $tip.data('spf-tip-init', true);
            $tip.on('mouseenter', function () {
                var text = $tip.attr('title');
                if (!text) return;
                $tip.removeAttr('title');
                $tip.data('spf-tip-text', text);
                var $bubble = $('<div class="spf-tooltip-bubble">' + escAttr(text) + '</div>');
                $('body').append($bubble);
                var offset = $tip.offset();
                $bubble.css({
                    top: offset.top + ($tip.outerHeight() / 2) - ($bubble.outerHeight() / 2),
                    left: offset.left + $tip.outerWidth() + 8
                });
                $tip.data('spf-bubble', $bubble);
            }).on('mouseleave', function () {
                var $bubble = $tip.data('spf-bubble');
                if ($bubble) { $bubble.remove(); }
                $tip.attr('title', $tip.data('spf-tip-text') || '');
            });
        });
    }

    /* -------------------------------------------
     * Helper functions
     * ----------------------------------------- */

    function getFieldsData() {
        try {
            return JSON.parse($('#spf-builder').attr('data-fields') || '[]');
        } catch (e) {
            return [];
        }
    }

    function setFieldsData(fields) {
        $('#spf-builder').attr('data-fields', JSON.stringify(fields));
    }

    function escAttr(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    /** Build option elements for the field type select */
    function buildTypeOptions(currentType) {
        var types = {
            'select': 'Dropdown',
            'text': 'Text',
            'email': 'Email',
            'number': 'Number',
            'textarea': 'Textarea',
            'radio': 'Radio',
            'checkbox': 'Checkbox'
        };
        var html = '';
        for (var key in types) {
            html += '<option value="' + key + '"' + (key === currentType ? ' selected' : '') + '>' + types[key] + '</option>';
        }
        return html;
    }

    /* -------------------------------------------
     * Choices UI helpers (WPForms-style rows)
     * ----------------------------------------- */

    /**
     * Build the individual choice rows HTML for a field.
     */
    function buildChoicesHtml(field) {
        var options = field.options || [];
        var indicator = (field.type === 'checkbox') ? 'checkbox' : 'radio';
        if (field.type === 'select') indicator = 'radio';
        var html = '';
        if (options.length === 0) {
            options = [''];
        }
        for (var i = 0; i < options.length; i++) {
            html += buildSingleChoiceRow(options[i], i, indicator);
        }
        return html;
    }

    /**
     * Build a single choice row.
     */
    function buildSingleChoiceRow(value, index, indicator) {
        return '<div class="spf-choice-row" data-index="' + index + '">' +
            '<span class="spf-choice-indicator spf-choice-indicator--' + indicator + '"></span>' +
            '<span class="spf-choice-drag dashicons dashicons-menu"></span>' +
            '<input type="text" class="spf-choice-input" value="' + escAttr(value) + '" placeholder="Choice ' + (index + 1) + '">' +
            '<button type="button" class="spf-choice-add-btn" title="Add choice">+</button>' +
            '<button type="button" class="spf-choice-remove-btn" title="Remove choice">&minus;</button>' +
            '</div>';
    }

    /**
     * Collect choices from the UI rows into a comma-separated string.
     */
    function collectChoices() {
        var vals = [];
        $('.spf-choice-row .spf-choice-input').each(function () {
            var v = $(this).val().trim();
            if (v) vals.push(v);
        });
        return vals.join(',');
    }

    /**
     * Refresh choice row indicators when field type changes.
     */
    function refreshChoiceIndicators() {
        var type = $('#spf_field_type').val();
        var indicator = (type === 'checkbox') ? 'checkbox' : 'radio';
        $('.spf-choice-indicator').removeClass('spf-choice-indicator--radio spf-choice-indicator--checkbox')
            .addClass('spf-choice-indicator--' + indicator);
    }

    /**
     * Build the field select options for conditional logic (excluding current field).
     */
    function buildFieldSelectOptions(currentFieldId, selectedKey) {
        var fields = getFieldsData();
        var html = '<option value="">— Select Field —</option>';
        for (var i = 0; i < fields.length; i++) {
            if (parseInt(fields[i].id) === parseInt(currentFieldId)) continue;
            var sel = (fields[i].field_key === selectedKey) ? ' selected' : '';
            html += '<option value="' + escAttr(fields[i].field_key) + '"' + sel + '>' + escAttr(fields[i].label) + '</option>';
        }
        return html;
    }

    /**
     * Build operator select options.
     */
    function buildOperatorOptions(selectedOp) {
        var ops = {
            'is': 'is',
            'is_not': 'is not',
            'contains': 'contains',
            'not_empty': 'is not empty',
            'empty': 'is empty'
        };
        var html = '';
        for (var key in ops) {
            var sel = (key === selectedOp) ? ' selected' : '';
            html += '<option value="' + key + '"' + sel + '>' + ops[key] + '</option>';
        }
        return html;
    }

    /**
     * Build condition rows HTML for the Smart Logic pane.
     */
    function buildFieldConditionRows(field) {
        var conditions = field.conditionals || [];
        var currentFieldId = field.id;
        var html = '';

        if (conditions.length === 0) {
            // Default empty row
            html += '<div class="spf-field-condition-row">' +
                '<select class="spf-fcond-field">' + buildFieldSelectOptions(currentFieldId, '') + '</select>' +
                '<select class="spf-fcond-op">' + buildOperatorOptions('is') + '</select>' +
                '<select class="spf-fcond-value"><option value="">\u2014 Select Choice \u2014</option></select>' +
                '<button type="button" class="button spf-fcond-remove" title="Remove">&times;</button>' +
                '</div>';
        } else {
            for (var i = 0; i < conditions.length; i++) {
                var c = conditions[i];
                var hideValue = (c.operator === 'not_empty' || c.operator === 'empty');
                html += '<div class="spf-field-condition-row">' +
                    '<select class="spf-fcond-field">' + buildFieldSelectOptions(currentFieldId, c.field_key) + '</select>' +
                    '<select class="spf-fcond-op">' + buildOperatorOptions(c.operator) + '</select>' +
                    '<select class="spf-fcond-value"' + (hideValue ? ' style="display:none"' : '') + '>' + buildCondValueOptionsHtml(c.field_key, c.value || '') + '</select>' +
                    '<button type="button" class="button spf-fcond-remove" title="Remove">&times;</button>' +
                    '</div>';
            }
        }
        return html;
    }

    /**
     * Build <option> HTML for a field's choices (used in condition value dropdowns).
     */
    function buildCondValueOptionsHtml(fieldKey, selectedValue) {
        var html = '<option value="">\u2014 Select Choice \u2014</option>';
        if (!fieldKey) return html;
        var fields = getFieldsData();
        for (var i = 0; i < fields.length; i++) {
            if (fields[i].field_key === fieldKey) {
                var options = fields[i].options || '';
                var choices = [];
                if (typeof options === 'string' && options.length > 0) {
                    choices = options.split(',');
                } else if (Array.isArray(options)) {
                    choices = options;
                }
                for (var j = 0; j < choices.length; j++) {
                    var c = String(choices[j]).trim();
                    if (c) {
                        var sel = (c === selectedValue) ? ' selected' : '';
                        html += '<option value="' + escAttr(c) + '"' + sel + '>' + escAttr(c) + '</option>';
                    }
                }
                break;
            }
        }
        return html;
    }

    /**
     * Collect conditional logic data from the Smart Logic pane.
     */
    function collectFieldConditions() {
        var conditions = [];
        $('#spf-field-options-form .spf-field-condition-row').each(function () {
            var fk = $(this).find('.spf-fcond-field').val();
            var op = $(this).find('.spf-fcond-op').val();
            var vl = $(this).find('.spf-fcond-value').val();
            if (fk) {
                conditions.push({ field_key: fk, operator: op, value: vl || '' });
            }
        });
        return conditions;
    }

    /**
     * Initialise Field Conditional Logic interactions (Smart Logic tab).
     */
    function initFieldConditionLogic() {
        /* Toggle conditions wrap */
        $(document).on('change', '.spf-field-cond-toggle', function () {
            var $wrap = $(this).closest('#spf-field-options-form').find('.spf-field-conditions-wrap');
            if ($(this).is(':checked')) {
                $wrap.slideDown(200);
            } else {
                $wrap.slideUp(200);
            }
        });

        /* Add condition row */
        $(document).on('click', '.spf-field-cond-add-row', function () {
            var $rows = $(this).siblings('.spf-field-condition-rows');
            var fieldId = $(this).closest('#spf-field-options-form').data('field-id');
            var rowHtml = '<div class="spf-field-condition-row">' +
                '<select class="spf-fcond-field">' + buildFieldSelectOptions(fieldId, '') + '</select>' +
                '<select class="spf-fcond-op">' + buildOperatorOptions('is') + '</select>' +
                '<select class="spf-fcond-value"><option value="">\u2014 Select Choice \u2014</option></select>' +
                '<button type="button" class="button spf-fcond-remove" title="Remove">&times;</button>' +
                '</div>';
            $rows.append(rowHtml);
        });

        /* Remove condition row */
        $(document).on('click', '.spf-fcond-remove', function () {
            var $rows = $(this).closest('.spf-field-condition-rows');
            if ($rows.find('.spf-field-condition-row').length > 1) {
                $(this).closest('.spf-field-condition-row').remove();
            }
        });

        /* Toggle value input visibility based on operator */
        $(document).on('change', '.spf-fcond-op', function () {
            var $val = $(this).siblings('.spf-fcond-value');
            if ($(this).val() === 'not_empty' || $(this).val() === 'empty') {
                $val.hide().val('');
            } else {
                $val.show();
            }
        });

        /* Dynamic value dropdown: when field changes, populate choices (Smart Logic) */
        $(document).on('change', '.spf-fcond-field', function () {
            var fieldKey = $(this).val();
            var $valueSelect = $(this).closest('.spf-field-condition-row').find('.spf-fcond-value');
            $valueSelect.html(buildCondValueOptionsHtml(fieldKey, ''));
        });
    }

})(jQuery);
