<?php
/**
 * Frontend form template.
 *
 * Available variables:
 *   $form_id       — integer form identifier.
 *   $form_settings — array of appearance settings from admin.
 *
 * Renders all supported field types:
 *   select, text, email, number, textarea, radio, checkbox.
 *
 * @package SmartProgrammeFinder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Defaults if the template is loaded standalone.
$form_settings = $form_settings ?? SPF_Admin::get_form_settings( (int) $form_id );

// Load fields for this specific form.
$all_fields = get_option( 'spf_fields', array() );
$spf_fields = array_values( array_filter( $all_fields, function ( $f ) use ( $form_id ) {
    return (int) ( $f['form_id'] ?? 0 ) === (int) $form_id;
} ) );

if ( empty( $spf_fields ) ) {
    if ( current_user_can( 'manage_options' ) ) {
        echo '<p class="spf-admin-notice">' . esc_html__( 'Smart Programme Finder: No fields configured for this form. Visit the Form Builder in your dashboard.', 'smart-programme-finder' ) . '</p>';
    }
    return;
}

// Build inline CSS custom properties from settings.
$s   = $form_settings;
$css = '';
if ( ! empty( $s['primary_color'] ) )  { $css .= '--spf-primary:' . esc_attr( $s['primary_color'] ) . ';'; }
if ( ! empty( $s['primary_hover'] ) )  { $css .= '--spf-primary-hover:' . esc_attr( $s['primary_hover'] ) . ';'; }
if ( ! empty( $s['label_color'] ) )    { $css .= '--spf-text:' . esc_attr( $s['label_color'] ) . ';'; }
if ( ! empty( $s['input_bg'] ) )       { $css .= '--spf-bg:' . esc_attr( $s['input_bg'] ) . ';'; }
if ( ! empty( $s['input_border'] ) )   { $css .= '--spf-border:' . esc_attr( $s['input_border'] ) . ';'; }
if ( ! empty( $s['input_text'] ) )     { $css .= '--spf-input-text:' . esc_attr( $s['input_text'] ) . ';'; }
if ( ! empty( $s['input_radius'] ) )   { $css .= '--spf-radius:' . esc_attr( $s['input_radius'] ) . 'px;'; }
if ( ! empty( $s['btn_text_color'] ) ) { $css .= '--spf-btn-text:' . esc_attr( $s['btn_text_color'] ) . ';'; }
if ( ! empty( $s['btn_radius'] ) )     { $css .= '--spf-btn-radius:' . esc_attr( $s['btn_radius'] ) . 'px;'; }
if ( ! empty( $s['form_width'] ) && '0' !== $s['form_width'] ) { $css .= 'max-width:' . esc_attr( $s['form_width'] ) . 'px;'; }
if ( ! empty( $s['form_bg'] ) )        { $css .= 'background-color:' . esc_attr( $s['form_bg'] ) . ';'; }
if ( ! empty( $s['form_padding'] ) && '0' !== $s['form_padding'] ) { $css .= 'padding:' . esc_attr( $s['form_padding'] ) . 'px;'; }
if ( ! empty( $s['form_border_radius'] ) && '0' !== $s['form_border_radius'] ) { $css .= 'border-radius:' . esc_attr( $s['form_border_radius'] ) . 'px;'; }

$columns  = $s['columns'] ?? '1';
$btn_pos  = $GLOBALS['spf_elementor_btn_pos'] ?? ( $s['button_position'] ?? 'full' );
$btn_text = $s['button_text'] ?? 'Find My Programme';
$spacing  = $s['field_spacing'] ?? '20';

// Fetch general settings for processing text.
$spf_forms_all   = get_option( 'spf_forms', array() );
$spf_general     = array();
foreach ( $spf_forms_all as $spf_f ) {
    if ( (int) ( $spf_f['id'] ?? 0 ) === (int) $form_id ) {
        $spf_general = $spf_f['general'] ?? array();
        break;
    }
}
$processing_text = $spf_general['button_processing_text'] ?? ( $spf_general['submit_processing'] ?? 'Finding your best match...' );
$conf_btn_text   = $conf_btn_text ?? ( $spf_general['conf_btn_text'] ?? 'Try Again' );


?>

<div class="spf-form-wrapper" data-form-id="<?php echo esc_attr( $form_id ); ?>" style="<?php echo esc_attr( $css ); ?>">
    <form class="spf-form" id="spf-form-<?php echo esc_attr( $form_id ); ?>" novalidate>
        <input type="hidden" name="action" value="spf_submit_form">
        <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'spf_submit_nonce' ) ); ?>">
        <input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">

        <div class="spf-fields-grid spf-fields-grid--<?php echo esc_attr( $columns ); ?>" style="gap:<?php echo esc_attr( $spacing ); ?>px;">
        <?php foreach ( $spf_fields as $field ) :
            $field_key    = esc_attr( $field['field_key'] );
            $field_id       = 'spf-' . $field_key . '-' . $form_id;
            $field_type     = $field['type'] ?? 'text';
            $field_size     = $field['size'] ?? 'medium';
            $is_required    = ! empty( $field['required'] );
            $placeholder    = isset( $field['placeholder'] ) ? esc_attr( $field['placeholder'] ) : '';
            $default_value  = isset( $field['default_value'] ) ? esc_attr( $field['default_value'] ) : '';
            $input_columns  = $field['input_columns'] ?? '';
            $has_cond       = ! empty( $field['conditional_logic'] );
            $cond_type      = $field['conditional_type'] ?? 'show';
            $conditionals   = $field['conditionals'] ?? array();
        ?>
        <div class="spf-field-group spf-field--<?php echo esc_attr( $field_size ); ?>"
             data-field-type="<?php echo esc_attr( $field_type ); ?>"
             data-field-key="<?php echo esc_attr( $field_key ); ?>"
             <?php if ( $has_cond && ! empty( $conditionals ) ) : ?>
             data-conditional-type="<?php echo esc_attr( $cond_type ); ?>"
             data-conditionals="<?php echo esc_attr( wp_json_encode( $conditionals ) ); ?>"
             <?php endif; ?>
        >
            <?php $hide_label = ! empty( $field['hide_label'] ); ?>
            <label <?php echo ( 'checkbox' !== $field_type && 'radio' !== $field_type ) ? 'for="' . esc_attr( $field_id ) . '"' : ''; ?> class="spf-label<?php echo $hide_label ? ' spf-label--hidden' : ''; ?>">
                <?php echo esc_html( $field['label'] ); ?>
                <?php if ( $is_required ) : ?>
                    <span class="spf-required" aria-label="<?php esc_attr_e( 'required', 'smart-programme-finder' ); ?>">*</span>
                <?php endif; ?>
            </label>

            <?php
            switch ( $field_type ) :

                /* ── Dropdown (Select) ──────────── */
                case 'select':
                    $spf_arrow_icon = $GLOBALS['spf_elementor_arrow_icon'] ?? '';
                    $spf_arrow_hide = $GLOBALS['spf_elementor_arrow_hide'] ?? false;
                    $spf_wrap_class = 'spf-select-wrap';
                    if ( $spf_arrow_icon )  { $spf_wrap_class .= ' spf-has-arrow-icon'; }
                    elseif ( $spf_arrow_hide ) { $spf_wrap_class .= ' spf-no-arrow'; }
                    ?>
                    <div class="<?php echo esc_attr( $spf_wrap_class ); ?>">
                    <select
                        name="<?php echo esc_attr( $field_key ); ?>"
                        id="<?php echo esc_attr( $field_id ); ?>"
                        class="spf-select"
                        <?php echo $is_required ? 'required' : ''; ?>
                    >
                        <option value=""><?php echo $placeholder ? esc_html( $placeholder ) : esc_html__( '— Select an option —', 'smart-programme-finder' ); ?></option>
                        <?php foreach ( $field['options'] as $option ) : ?>
                        <option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php
                    if ( $spf_arrow_icon ) {
                        // Pass through wp_kses with an SVG-aware allowlist — output originates
                        // from Elementor Icons_Manager but we defensively re-validate it here.
                        $spf_svg_kses = array_merge(
                            wp_kses_allowed_html( 'post' ),
                            array(
                                'svg'  => array( 'xmlns' => true, 'viewbox' => true, 'width' => true, 'height' => true, 'aria-hidden' => true, 'focusable' => true, 'class' => true ),
                                'path' => array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true ),
                                'use'  => array( 'xlink:href' => true, 'href' => true ),
                            )
                        );
                        echo wp_kses( $spf_arrow_icon, $spf_svg_kses );
                    }
                    ?>
                    </div>
                <?php break;

                /* ── Text Input ─────────────────── */
                case 'text': ?>
                    <input
                        type="text"
                        name="<?php echo esc_attr( $field_key ); ?>"
                        id="<?php echo esc_attr( $field_id ); ?>"
                        class="spf-input"
                        <?php if ( $placeholder ) : ?>placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php endif; ?>                        <?php if ( $default_value ) : ?>value="<?php echo esc_attr( $default_value ); ?>"<?php endif; ?>                        <?php echo $is_required ? 'required' : ''; ?>
                    >
                <?php break;

                /* ── Email Input ────────────────── */
                case 'email': ?>
                    <input
                        type="email"
                        name="<?php echo esc_attr( $field_key ); ?>"
                        id="<?php echo esc_attr( $field_id ); ?>"
                        class="spf-input"
                        <?php if ( $placeholder ) : ?>placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php endif; ?>                        <?php if ( $default_value ) : ?>value="<?php echo esc_attr( $default_value ); ?>"<?php endif; ?>                        <?php echo $is_required ? 'required' : ''; ?>
                    >
                <?php break;

                /* ── Number Input ───────────────── */
                case 'number': ?>
                    <input
                        type="number"
                        name="<?php echo esc_attr( $field_key ); ?>"
                        id="<?php echo esc_attr( $field_id ); ?>"
                        class="spf-input"
                        <?php if ( $placeholder ) : ?>placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php endif; ?>                        <?php if ( $default_value ) : ?>value="<?php echo esc_attr( $default_value ); ?>"<?php endif; ?>                        <?php echo $is_required ? 'required' : ''; ?>
                    >
                <?php break;

                /* ── Textarea ───────────────────── */
                case 'textarea': ?>
                    <textarea
                        name="<?php echo esc_attr( $field_key ); ?>"
                        id="<?php echo esc_attr( $field_id ); ?>"
                        class="spf-textarea"
                        rows="4"
                        <?php if ( $placeholder ) : ?>placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php endif; ?>
                        <?php echo $is_required ? 'required' : ''; ?>
                    ></textarea>
                <?php break;

                /* ── Radio Buttons ──────────────── */
                case 'radio':
                    $radio_class = 'spf-radio-group';
                    if ( '2' === $input_columns )      { $radio_class .= ' spf-choices-2col'; }
                    elseif ( '3' === $input_columns )   { $radio_class .= ' spf-choices-3col'; }
                    elseif ( 'inline' === $input_columns ) { $radio_class .= ' spf-choices-inline'; }
                ?>
                    <div class="<?php echo esc_attr( $radio_class ); ?>" role="radiogroup" aria-labelledby="<?php echo esc_attr( $field_id ); ?>-legend">
                        <?php foreach ( $field['options'] as $i => $option ) :
                            $opt_id = $field_id . '-' . $i;
                        ?>
                        <label class="spf-radio-label" for="<?php echo esc_attr( $opt_id ); ?>">
                            <input
                                type="radio"
                                name="<?php echo esc_attr( $field_key ); ?>"
                                id="<?php echo esc_attr( $opt_id ); ?>"
                                value="<?php echo esc_attr( $option ); ?>"
                                class="spf-radio"
                                <?php echo $is_required ? 'required' : ''; ?>
                            >
                            <span><?php echo esc_html( $option ); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php break;

                /* ── Checkboxes ─────────────────── */
                case 'checkbox':
                    $cb_class = 'spf-checkbox-group';
                    if ( '2' === $input_columns )      { $cb_class .= ' spf-choices-2col'; }
                    elseif ( '3' === $input_columns )   { $cb_class .= ' spf-choices-3col'; }
                    elseif ( 'inline' === $input_columns ) { $cb_class .= ' spf-choices-inline'; }
                ?>
                    <div class="<?php echo esc_attr( $cb_class ); ?>" data-required="<?php echo $is_required ? '1' : '0'; ?>">
                        <?php foreach ( $field['options'] as $i => $option ) :
                            $opt_id = $field_id . '-' . $i;
                        ?>
                        <label class="spf-checkbox-label" for="<?php echo esc_attr( $opt_id ); ?>">
                            <input
                                type="checkbox"
                                name="<?php echo esc_attr( $field_key ); ?>[]"
                                id="<?php echo esc_attr( $opt_id ); ?>"
                                value="<?php echo esc_attr( $option ); ?>"
                                class="spf-checkbox"
                            >
                            <span><?php echo esc_html( $option ); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php break;

            endswitch;
            ?>

            <span class="spf-error-message" role="alert" aria-live="polite"></span>
        </div>
        <?php endforeach; ?>
        <?php if ( 'inline' === $btn_pos ) : ?>
            <div class="spf-submit-group spf-submit-group--inline">
                <button type="submit" class="spf-submit-btn" data-processing-text="<?php echo esc_attr( $processing_text ); ?>">
                    <span class="spf-btn-text"><?php echo esc_html( $btn_text ); ?></span>
                    <span class="spf-btn-loader" aria-hidden="true"></span>
                </button>
            </div>
        <?php endif; ?>
        </div><!-- /.spf-fields-grid -->

        <?php if ( 'inline' !== $btn_pos ) : ?>
        <div class="spf-submit-group spf-submit-group--<?php echo esc_attr( $btn_pos ); ?>">
            <button type="submit" class="spf-submit-btn" data-processing-text="<?php echo esc_attr( $processing_text ); ?>">
                <span class="spf-btn-text"><?php echo esc_html( $btn_text ); ?></span>
                <span class="spf-btn-loader" aria-hidden="true"></span>
            </button>
        </div>
        <?php endif; ?>
    </form>

    <!-- Inline confirmation (below-form mode) -->
    <div class="spf-confirmation-inline" hidden>
        <div class="spf-inline-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>
        <h3 class="spf-inline-title"><?php esc_html_e( 'Your Recommendation', 'smart-programme-finder' ); ?></h3>
        <div class="spf-inline-body">
            <!-- Populated dynamically via JS -->
        </div>
        <div class="spf-inline-actions">
            <button type="button" class="spf-inline-btn spf-inline-btn--reset">
                <?php echo esc_html( $conf_btn_text ); ?>
            </button>
        </div>
    </div>
</div>
