<?php
/**
 * Elementor widget — Programme Finder.
 *
 * Full Content / Style / Advanced tabs with visual controls
 * mirroring a WPForms-like editing experience.
 *
 * @package SmartProgrammeFinder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Border;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;

class SPF_Elementor_Widget extends Widget_Base {

    public function get_name(): string {
        return 'spf_programme_finder';
    }

    public function get_title(): string {
        return __( 'Programme Finder', 'smart-programme-finder' );
    }

    public function get_icon(): string {
        return 'eicon-search';
    }

    public function get_categories(): array {
        return array( 'general' );
    }

    public function get_keywords(): array {
        return array( 'programme', 'finder', 'recommendation', 'form', 'quiz' );
    }

    public function get_style_depends(): array {
        return array( 'spf-frontend' );
    }

    public function get_script_depends(): array {
        return array( 'spf-frontend' );
    }

    /* ══════════════════════════════════════════
     * CONTROLS REGISTRATION
     * ══════════════════════════════════════════ */
    protected function register_controls(): void {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    /* ── CONTENT TAB ───────────────────────── */
    private function register_content_controls(): void {

        /* -- Form ----------------------------- */
        $this->start_controls_section( 'section_form', array(
            'label' => __( 'Form', 'smart-programme-finder' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ) );

        $forms   = get_option( 'spf_forms', array() );
        $options = array();
        $default = 0;
        foreach ( $forms as $form ) {
            $fid             = (int) $form['id'];
            $options[ $fid ] = $form['name'];
            if ( 0 === $default ) {
                $default = $fid;
            }
        }
        if ( empty( $options ) ) {
            $options[0] = __( 'No forms available', 'smart-programme-finder' );
        }

        $this->add_control( 'form_id', array(
            'label'   => __( 'Form', 'smart-programme-finder' ),
            'type'    => Controls_Manager::SELECT,
            'default' => $default,
            'options' => $options,
        ) );

        $this->add_control( 'form_edit_link', array(
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => '<p style="color:#93c5fd;font-size:12px;margin:0 0 8px;">' . esc_html__( 'Need to make changes?', 'smart-programme-finder' )
                                 . ' <a href="' . esc_url( admin_url( 'admin.php?page=spf-form-edit&form_id=' ) ) . '" class="spf-edit-form-link" style="color:#60a5fa;">'
                                 . esc_html__( 'Edit the selected form.', 'smart-programme-finder' ) . '</a></p>',
            'content_classes' => 'elementor-descriptor',
        ) );

        $this->end_controls_section();

        /* -- Display Options ------------------ */
        $this->start_controls_section( 'section_display', array(
            'label' => __( 'Display Options', 'smart-programme-finder' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ) );

        $this->add_control( 'show_form_name', array(
            'label'        => __( 'Form Name', 'smart-programme-finder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Show', 'smart-programme-finder' ),
            'label_off'    => __( 'Hide', 'smart-programme-finder' ),
            'return_value' => 'yes',
            'default'      => '',
        ) );

        $this->add_control( 'show_form_desc', array(
            'label'        => __( 'Form Description', 'smart-programme-finder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Show', 'smart-programme-finder' ),
            'label_off'    => __( 'Hide', 'smart-programme-finder' ),
            'return_value' => 'yes',
            'default'      => '',
        ) );

        $this->add_control( 'hide_labels', array(
            'label'        => __( 'Hide Field Labels', 'smart-programme-finder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', 'smart-programme-finder' ),
            'label_off'    => __( 'No', 'smart-programme-finder' ),
            'return_value' => 'yes',
            'default'      => '',
            'selectors'    => array(
                '{{WRAPPER}} .spf-label' => 'display: none;',
            ),
        ) );

        $this->add_responsive_control( 'columns', array(
            'label'   => __( 'Columns', 'smart-programme-finder' ),
            'type'    => Controls_Manager::SELECT,
            'default' => '1',
            'mobile_default' => '1',
            'tablet_default' => '1',
            'options' => array(
                '1' => '1',
                '2' => '2',
                '3' => '3',
                '4' => '4',
            ),
            'selectors' => array(
                '{{WRAPPER}} .spf-fields-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
            ),
            'prefix_class' => 'spf-cols-',
            'separator'    => 'before',
        ) );

        $this->add_control( 'button_text', array(
            'label'       => __( 'Button Text', 'smart-programme-finder' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => __( 'Find My Programme', 'smart-programme-finder' ),
            'placeholder' => __( 'Find My Programme', 'smart-programme-finder' ),
        ) );

        $this->add_control( 'button_position', array(
            'label'   => __( 'Button Position', 'smart-programme-finder' ),
            'type'    => Controls_Manager::CHOOSE,
            'options' => array(
                'left'   => array( 'title' => __( 'Left', 'smart-programme-finder' ), 'icon' => 'eicon-text-align-left' ),
                'center' => array( 'title' => __( 'Center', 'smart-programme-finder' ), 'icon' => 'eicon-text-align-center' ),
                'right'  => array( 'title' => __( 'Right', 'smart-programme-finder' ), 'icon' => 'eicon-text-align-right' ),
                'full'   => array( 'title' => __( 'Full Width', 'smart-programme-finder' ), 'icon' => 'eicon-text-align-justify' ),
                'inline' => array( 'title' => __( 'End of Last Row', 'smart-programme-finder' ), 'icon' => 'eicon-h-align-right' ),
            ),
            'default' => 'full',
        ) );

        $this->end_controls_section();
    }

    /* ── STYLE TAB ─────────────────────────── */
    private function register_style_controls(): void {

        /* -- Themes --------------------------- */
        $this->start_controls_section( 'style_themes', array(
            'label' => __( 'Themes', 'smart-programme-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'theme_preset', array(
            'label'        => __( 'Theme', 'smart-programme-finder' ),
            'type'         => Controls_Manager::SELECT,
            'default'      => 'default',
            'options'      => array(
                'default'  => __( 'Default', 'smart-programme-finder' ),
                'classic'  => __( 'Classic — Bordered', 'smart-programme-finder' ),
                'modern'   => __( 'Modern — Shadow', 'smart-programme-finder' ),
                'elegant'  => __( 'Elegant — Square', 'smart-programme-finder' ),
                'minimal'  => __( 'Minimal — Clean', 'smart-programme-finder' ),
            ),
            'prefix_class' => 'spf-theme-',
            'description'  => __( 'Style controls below will override theme defaults.', 'smart-programme-finder' ),
        ) );

        $this->end_controls_section();

        /* -- Field Styles --------------------- */
        $this->start_controls_section( 'style_fields', array(
            'label' => __( 'Field Styles', 'smart-programme-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'field_size', array(
            'label'   => __( 'Size', 'smart-programme-finder' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'medium',
            'options' => array(
                'small'  => __( 'Small', 'smart-programme-finder' ),
                'medium' => __( 'Medium', 'smart-programme-finder' ),
                'large'  => __( 'Large', 'smart-programme-finder' ),
            ),
            'prefix_class' => 'spf-field-size-',
        ) );

        $this->add_control( 'field_border_type', array(
            'label'   => __( 'Border', 'smart-programme-finder' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'solid',
            'options' => array(
                'none'   => __( 'None', 'smart-programme-finder' ),
                'solid'  => __( 'Solid', 'smart-programme-finder' ),
                'dashed' => __( 'Dashed', 'smart-programme-finder' ),
                'dotted' => __( 'Dotted', 'smart-programme-finder' ),
            ),
            'selectors' => array(
                '{{WRAPPER}} .spf-input, {{WRAPPER}} .spf-select, {{WRAPPER}} .spf-textarea' => 'border-style: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'field_border_width', array(
            'label'      => __( 'Border Width (px)', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 10 ) ),
            'default'    => array( 'unit' => 'px', 'size' => 1 ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-input, {{WRAPPER}} .spf-select, {{WRAPPER}} .spf-textarea' => 'border-width: {{SIZE}}{{UNIT}};',
            ),
            'condition' => array(
                'field_border_type!' => 'none',
            ),
        ) );

        $this->add_control( 'input_border_radius', array(
            'label'      => __( 'Border Radius (px)', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-input, {{WRAPPER}} .spf-select, {{WRAPPER}} .spf-textarea' => 'border-radius: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'input_bg', array(
            'label'     => __( 'Background', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array( 'default' => Global_Colors::COLOR_SECONDARY ),
            'selectors' => array(
                '{{WRAPPER}} .spf-input, {{WRAPPER}} .spf-select, {{WRAPPER}} .spf-textarea' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'input_border_color', array(
            'label'     => __( 'Border Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array(),
            'selectors' => array(
                '{{WRAPPER}} .spf-input, {{WRAPPER}} .spf-select, {{WRAPPER}} .spf-textarea' => 'border-color: {{VALUE}};',
            ),
            'condition' => array(
                'field_border_type!' => 'none',
            ),
        ) );

        $this->add_control( 'input_text', array(
            'label'     => __( 'Text Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array( 'default' => Global_Colors::COLOR_TEXT ),
            'selectors' => array(
                '{{WRAPPER}} .spf-input, {{WRAPPER}} .spf-select, {{WRAPPER}} .spf-textarea' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'placeholder_color', array(
            'label'     => __( 'Placeholder Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array(),
            'selectors' => array(
                '{{WRAPPER}} .spf-input::placeholder'    => 'color: {{VALUE}};',
                '{{WRAPPER}} .spf-textarea::placeholder' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'input_focus_border', array(
            'label'     => __( 'Focus Border Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array( 'default' => Global_Colors::COLOR_PRIMARY ),
            'selectors' => array(
                '{{WRAPPER}} .spf-input:focus, {{WRAPPER}} .spf-select:focus, {{WRAPPER}} .spf-textarea:focus' => 'border-color: {{VALUE}};',
            ),
            'condition' => array(
                'field_border_type!' => 'none',
            ),
        ) );

        $this->add_responsive_control( 'field_spacing', array(
            'label'      => __( 'Field Spacing', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 60, 'step' => 2 ) ),
            'default'    => array( 'unit' => 'px', 'size' => 20 ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-field-group' => 'margin-bottom: {{SIZE}}{{UNIT}} !important;',
                '{{WRAPPER}} .spf-fields-grid' => 'gap: {{SIZE}}{{UNIT}} !important;',
            ),
            'separator' => 'before',
        ) );

        $this->add_responsive_control( 'field_padding', array(
            'label'      => __( 'Field Padding', 'smart-programme-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-input, {{WRAPPER}} .spf-select, {{WRAPPER}} .spf-textarea' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'input_typography',
            'label'    => __( 'Typography', 'smart-programme-finder' ),
            'selector' => '{{WRAPPER}} .spf-input, {{WRAPPER}} .spf-select, {{WRAPPER}} .spf-textarea',
        ) );

        $this->end_controls_section();

        /* -- Dropdown Arrow Styles ------------ */
        $this->start_controls_section( 'style_dropdown_arrow', array(
            'label' => __( 'Dropdown Arrow', 'smart-programme-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'arrow_show', array(
            'label'        => __( 'Show Arrow', 'smart-programme-finder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', 'smart-programme-finder' ),
            'label_off'    => __( 'No', 'smart-programme-finder' ),
            'return_value' => 'yes',
            'default'      => 'yes',
        ) );

        $this->add_control( 'arrow_icon', array(
            'label'     => __( 'Icon', 'smart-programme-finder' ),
            'type'      => Controls_Manager::ICONS,
            'default'   => array(
                'value'   => 'fas fa-chevron-down',
                'library' => 'fa-solid',
            ),
            'condition' => array( 'arrow_show' => 'yes' ),
        ) );

        $this->add_control( 'arrow_color', array(
            'label'     => __( 'Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array( 'active' => true ),
            'default'   => '#64748b',
            'selectors' => array(
                '{{WRAPPER}} .spf-select-wrap' => '--spf-arrow-color: {{VALUE}};',
            ),
            'condition' => array( 'arrow_show' => 'yes' ),
        ) );

        $this->add_responsive_control( 'arrow_size', array(
            'label'      => __( 'Size', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 8, 'max' => 40, 'step' => 1 ) ),
            'default'    => array( 'unit' => 'px', 'size' => 14 ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-select-wrap' => '--spf-arrow-size: {{SIZE}}{{UNIT}};',
            ),
            'condition' => array( 'arrow_show' => 'yes' ),
        ) );

        $this->add_responsive_control( 'arrow_offset', array(
            'label'      => __( 'Right Offset', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 4, 'max' => 40 ) ),
            'default'    => array( 'unit' => 'px', 'size' => 14 ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-select-wrap' => '--spf-arrow-offset: {{SIZE}}{{UNIT}};',
            ),
            'condition' => array( 'arrow_show' => 'yes' ),
        ) );

        $this->end_controls_section();

        /* -- Label Styles --------------------- */
        $this->start_controls_section( 'style_labels', array(
            'label' => __( 'Label Styles', 'smart-programme-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'label_size', array(
            'label'   => __( 'Size', 'smart-programme-finder' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'medium',
            'options' => array(
                'small'  => __( 'Small', 'smart-programme-finder' ),
                'medium' => __( 'Medium', 'smart-programme-finder' ),
                'large'  => __( 'Large', 'smart-programme-finder' ),
            ),
            'prefix_class' => 'spf-label-size-',
        ) );

        $this->add_control( 'label_color', array(
            'label'     => __( 'Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array( 'default' => Global_Colors::COLOR_TEXT ),
            'selectors' => array(
                '{{WRAPPER}} .spf-label' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'label_typography',
            'selector' => '{{WRAPPER}} .spf-label',
        ) );

        $this->end_controls_section();

        /* -- Button Styles -------------------- */
        $this->start_controls_section( 'style_button', array(
            'label' => __( 'Button Styles', 'smart-programme-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'btn_size', array(
            'label'   => __( 'Size', 'smart-programme-finder' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'medium',
            'options' => array(
                'small'  => __( 'Small', 'smart-programme-finder' ),
                'medium' => __( 'Medium', 'smart-programme-finder' ),
                'large'  => __( 'Large', 'smart-programme-finder' ),
            ),
            'prefix_class' => 'spf-btn-size-',
        ) );

        $this->add_control( 'btn_border_type', array(
            'label'   => __( 'Border', 'smart-programme-finder' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'none',
            'options' => array(
                'none'   => __( 'None', 'smart-programme-finder' ),
                'solid'  => __( 'Solid', 'smart-programme-finder' ),
                'dashed' => __( 'Dashed', 'smart-programme-finder' ),
                'dotted' => __( 'Dotted', 'smart-programme-finder' ),
            ),
            'selectors' => array(
                '{{WRAPPER}} .spf-submit-btn' => 'border-style: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_border_radius', array(
            'label'      => __( 'Border Radius (px)', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 50 ) ),
            'default'    => array( 'unit' => 'px', 'size' => 0 ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-submit-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->start_controls_tabs( 'tabs_button_colors' );

        $this->start_controls_tab( 'tab_button_normal', array(
            'label' => __( 'Normal', 'smart-programme-finder' ),
        ) );

        $this->add_control( 'btn_bg', array(
            'label'     => __( 'Background', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array( 'default' => Global_Colors::COLOR_PRIMARY ),
            'selectors' => array(
                '{{WRAPPER}} .spf-submit-btn' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_color', array(
            'label'     => __( 'Text', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array(),
            'selectors' => array(
                '{{WRAPPER}} .spf-submit-btn' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_border_color', array(
            'label'     => __( 'Border Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array(),
            'selectors' => array(
                '{{WRAPPER}} .spf-submit-btn' => 'border-color: {{VALUE}};',
            ),
            'condition' => array(
                'btn_border_type!' => 'none',
            ),
        ) );

        $this->end_controls_tab();

        $this->start_controls_tab( 'tab_button_hover', array(
            'label' => __( 'Hover', 'smart-programme-finder' ),
        ) );

        $this->add_control( 'btn_bg_hover', array(
            'label'     => __( 'Background', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array( 'default' => Global_Colors::COLOR_ACCENT ),
            'selectors' => array(
                '{{WRAPPER}} .spf-submit-btn:hover' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_color_hover', array(
            'label'     => __( 'Text', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array(),
            'selectors' => array(
                '{{WRAPPER}} .spf-submit-btn:hover' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_border_color_hover', array(
            'label'     => __( 'Border Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array(),
            'selectors' => array(
                '{{WRAPPER}} .spf-submit-btn:hover' => 'border-color: {{VALUE}};',
            ),
            'condition' => array(
                'btn_border_type!' => 'none',
            ),
        ) );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'      => 'btn_typography',
            'selector'  => '{{WRAPPER}} .spf-submit-btn',
            'separator' => 'before',
        ) );

        $this->add_responsive_control( 'btn_padding', array(
            'label'      => __( 'Padding', 'smart-programme-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em', 'rem', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-form-wrapper .spf-submit-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
            ),
        ) );

        $this->add_responsive_control( 'btn_width_type', array(
            'label'   => __( 'Width', 'smart-programme-finder' ),
            'type'    => Controls_Manager::CHOOSE,
            'options' => array(
                '100%' => array(
                    'title' => __( 'Full Width', 'smart-programme-finder' ),
                    'icon'  => 'eicon-text-align-justify',
                ),
                'auto' => array(
                    'title' => __( 'Content Width', 'smart-programme-finder' ),
                    'icon'  => 'eicon-text-align-center',
                ),
            ),
            'selectors' => array(
                '{{WRAPPER}} .spf-form-wrapper .spf-submit-btn' => 'width: {{VALUE}} !important;',
            ),
        ) );

        $this->add_responsive_control( 'btn_height', array(
            'label'      => __( 'Height', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em', 'rem' ),
            'range'      => array(
                'px'  => array( 'min' => 20, 'max' => 200, 'step' => 1 ),
                'em'  => array( 'min' => 1, 'max' => 10, 'step' => 0.1 ),
                'rem' => array( 'min' => 1, 'max' => 10, 'step' => 0.1 ),
            ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-form-wrapper .spf-submit-btn' => 'height: {{SIZE}}{{UNIT}} !important; min-height: unset !important;',
            ),
        ) );

        $this->add_responsive_control( 'btn_text_align', array(
            'label'   => __( 'Text Alignment', 'smart-programme-finder' ),
            'type'    => Controls_Manager::CHOOSE,
            'options' => array(
                'flex-start' => array(
                    'title' => __( 'Left', 'smart-programme-finder' ),
                    'icon'  => 'eicon-text-align-left',
                ),
                'center'     => array(
                    'title' => __( 'Center', 'smart-programme-finder' ),
                    'icon'  => 'eicon-text-align-center',
                ),
                'flex-end'   => array(
                    'title' => __( 'Right', 'smart-programme-finder' ),
                    'icon'  => 'eicon-text-align-right',
                ),
            ),
            'default'   => 'center',
            'selectors' => array(
                '{{WRAPPER}} .spf-form-wrapper .spf-submit-btn' => 'justify-content: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'btn_margin', array(
            'label'      => __( 'Margin', 'smart-programme-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-form-wrapper .spf-submit-group' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
            ),
        ) );

        $this->add_responsive_control( 'btn_vertical_align', array(
            'label'   => __( 'Vertical Alignment', 'smart-programme-finder' ),
            'type'    => Controls_Manager::CHOOSE,
            'options' => array(
                'flex-start' => array(
                    'title' => __( 'Top', 'smart-programme-finder' ),
                    'icon'  => 'eicon-v-align-top',
                ),
                'center'     => array(
                    'title' => __( 'Middle', 'smart-programme-finder' ),
                    'icon'  => 'eicon-v-align-middle',
                ),
                'flex-end'   => array(
                    'title' => __( 'Bottom', 'smart-programme-finder' ),
                    'icon'  => 'eicon-v-align-bottom',
                ),
            ),
            'selectors' => array(
                '{{WRAPPER}} .spf-form-wrapper .spf-submit-group' => 'align-self: {{VALUE}} !important;',
            ),
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'btn_shadow',
            'selector' => '{{WRAPPER}} .spf-submit-btn',
        ) );

        /* -- Loading State ------------------- */
        $this->add_control( 'heading_btn_loading', array(
            'label'     => __( 'Loading State', 'smart-programme-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->start_controls_tabs( 'tabs_btn_loading' );

        $this->start_controls_tab( 'tab_btn_loading_bg', array(
            'label' => __( 'Button', 'smart-programme-finder' ),
        ) );

        $this->add_control( 'btn_loading_bg', array(
            'label'     => __( 'Background Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-submit-btn.spf-loading' => 'background-color: {{VALUE}} !important;',
            ),
        ) );

        $this->add_control( 'btn_loading_color', array(
            'label'     => __( 'Text Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-submit-btn.spf-loading .spf-btn-text' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_loading_opacity', array(
            'label'     => __( 'Opacity', 'smart-programme-finder' ),
            'type'      => Controls_Manager::SLIDER,
            'range'     => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
            'selectors' => array(
                '{{WRAPPER}} .spf-submit-btn.spf-loading' => 'opacity: {{SIZE}} !important;',
            ),
        ) );

        $this->end_controls_tab();

        $this->start_controls_tab( 'tab_btn_loading_spinner', array(
            'label' => __( 'Spinner', 'smart-programme-finder' ),
        ) );

        $this->add_control( 'btn_spinner_color', array(
            'label'     => __( 'Spinner Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-submit-btn.spf-loading .spf-btn-loader' => 'border-top-color: {{VALUE}}; border-right-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_spinner_track_color', array(
            'label'     => __( 'Spinner Track Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-submit-btn.spf-loading .spf-btn-loader' => 'border-bottom-color: {{VALUE}}; border-left-color: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'btn_spinner_size', array(
            'label'      => __( 'Spinner Size', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 10, 'max' => 40, 'step' => 1 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-submit-btn.spf-loading .spf-btn-loader' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();

        /* -- Container Styles ----------------- */
        $this->start_controls_section( 'style_container', array(
            'label' => __( 'Container Styles', 'smart-programme-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'form_align', array(
            'label'   => __( 'Form Alignment', 'smart-programme-finder' ),
            'type'    => Controls_Manager::CHOOSE,
            'options' => array(
                'flex-start' => array( 'title' => __( 'Left', 'smart-programme-finder' ), 'icon' => 'eicon-text-align-left' ),
                'center'     => array( 'title' => __( 'Center', 'smart-programme-finder' ), 'icon' => 'eicon-text-align-center' ),
                'flex-end'   => array( 'title' => __( 'Right', 'smart-programme-finder' ), 'icon' => 'eicon-text-align-right' ),
            ),
            'default'   => 'center',
            'selectors' => array(
                '{{WRAPPER}} .spf-elementor-wrap' => 'display: flex; justify-content: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'form_width', array(
            'label'      => __( 'Max Width', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', '%' ),
            'range'      => array(
                'px' => array( 'min' => 200, 'max' => 1400, 'step' => 10 ),
                '%'  => array( 'min' => 20, 'max' => 100 ),
            ),
            'default'    => array( 'unit' => 'px', 'size' => 560 ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-form-wrapper' => 'max-width: {{SIZE}}{{UNIT}} !important;',
            ),
        ) );

        $this->add_responsive_control( 'form_padding', array(
            'label'      => __( 'Padding', 'smart-programme-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-form-wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
            ),
        ) );

        $this->add_control( 'form_border_radius', array(
            'label'      => __( 'Border Radius', 'smart-programme-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px' ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-form-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
            ),
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'form_border',
            'selector' => '{{WRAPPER}} .spf-form-wrapper',
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'form_shadow',
            'selector' => '{{WRAPPER}} .spf-form-wrapper',
        ) );

        $this->end_controls_section();

        /* -- Background Styles ---------------- */
        $this->start_controls_section( 'style_background', array(
            'label' => __( 'Background Styles', 'smart-programme-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'form_bg', array(
            'label'     => __( 'Background Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-form-wrapper' => 'background-color: {{VALUE}} !important;',
            ),
        ) );

        $this->add_control( 'form_bg_image_notice', array(
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => '<p style="font-size:12px;color:#94a3b8;margin:0;">' . esc_html__( 'For background images and gradients, use the Advanced tab > Background section.', 'smart-programme-finder' ) . '</p>',
            'content_classes' => 'elementor-descriptor',
        ) );

        $this->end_controls_section();

        /* -- Other Styles --------------------- */
        $this->start_controls_section( 'style_other', array(
            'label' => __( 'Other Styles', 'smart-programme-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'heading_radio_checkbox', array(
            'label' => __( 'Radio & Checkbox', 'smart-programme-finder' ),
            'type'  => Controls_Manager::HEADING,
        ) );

        $this->add_control( 'option_border_color', array(
            'label'     => __( 'Border Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-radio-label, {{WRAPPER}} .spf-checkbox-label' => 'border-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'option_hover_bg', array(
            'label'     => __( 'Hover Background', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-radio-label:hover, {{WRAPPER}} .spf-checkbox-label:hover' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'option_accent', array(
            'label'     => __( 'Accent Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-radio, {{WRAPPER}} .spf-checkbox' => 'accent-color: {{VALUE}};',
            ),
        ) );

        $this->end_controls_section();

        /* ─────────────────────────────────────
         * Confirmation Styles — Popup Modal
         * ───────────────────────────────────── */
        $this->start_controls_section( 'style_confirmation_popup', array(
            'label' => __( 'Confirmation — Popup', 'smart-programme-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'popup_desc', array(
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => '<p style="font-size:12px;color:#94a3b8;margin:0 0 10px;">' . esc_html__( 'Styles for the modal popup that appears after form submission.', 'smart-programme-finder' ) . '</p>',
            'content_classes' => 'elementor-descriptor',
        ) );

        $this->add_control( 'popup_overlay_color', array(
            'label'     => __( 'Overlay Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-modal-overlay' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'popup_bg', array(
            'label'     => __( 'Background', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-modal' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'popup_icon_color', array(
            'label'     => __( 'Icon Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-modal-icon' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'popup_icon_size', array(
            'label'      => __( 'Icon Size', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 24, 'max' => 80, 'step' => 2 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-modal-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'popup_title_color', array(
            'label'     => __( 'Title Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-modal-title' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'popup_title_typography',
            'label'    => __( 'Title Typography', 'smart-programme-finder' ),
            'selector' => '{{WRAPPER}} .spf-modal-title',
        ) );

        $this->add_control( 'popup_body_color', array(
            'label'     => __( 'Body Text Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-modal-body' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'popup_body_typography',
            'label'    => __( 'Body Typography', 'smart-programme-finder' ),
            'selector' => '{{WRAPPER}} .spf-modal-body',
        ) );

        $this->add_control( 'popup_border_radius', array(
            'label'      => __( 'Border Radius', 'smart-programme-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px' ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-modal' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
            'separator' => 'before',
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'popup_border',
            'selector' => '{{WRAPPER}} .spf-modal',
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'popup_shadow',
            'selector' => '{{WRAPPER}} .spf-modal',
        ) );

        $this->add_responsive_control( 'popup_padding', array(
            'label'      => __( 'Padding', 'smart-programme-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-modal' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
            ),
        ) );

        $this->add_control( 'popup_preview', array(
            'label'        => __( 'Preview Popup in Editor', 'smart-programme-finder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Show', 'smart-programme-finder' ),
            'label_off'    => __( 'Hide', 'smart-programme-finder' ),
            'return_value' => 'yes',
            'default'      => '',
            'prefix_class' => 'spf-popup-preview-',
            'separator'    => 'before',
        ) );

        $this->add_responsive_control( 'popup_max_width', array(
            'label'      => __( 'Max Width', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', '%', 'vw', 'em', 'rem' ),
            'range'      => array(
                'px'  => array( 'min' => 200, 'max' => 1200, 'step' => 10 ),
                '%'   => array( 'min' => 10,  'max' => 100,  'step' => 1 ),
                'vw'  => array( 'min' => 10,  'max' => 100,  'step' => 1 ),
                'em'  => array( 'min' => 10,  'max' => 80,   'step' => 0.5 ),
                'rem' => array( 'min' => 10,  'max' => 80,   'step' => 0.5 ),
            ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-modal' => 'max-width: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'popup_lock_scroll', array(
            'label'        => __( 'Lock Page Scroll While Open', 'smart-programme-finder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', 'smart-programme-finder' ),
            'label_off'    => __( 'No', 'smart-programme-finder' ),
            'return_value' => '1',
            'default'      => '1',
            'separator'    => 'before',
        ) );

        /* Close Icon */
        $this->add_control( 'heading_close_icon', array(
            'label'     => __( 'Close Icon', 'smart-programme-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->start_controls_tabs( 'tabs_close_icon' );

        $this->start_controls_tab( 'tab_close_icon_normal', array(
            'label' => __( 'Normal', 'smart-programme-finder' ),
        ) );

        $this->add_control( 'close_icon_color', array(
            'label'     => __( 'Icon Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array( 'active' => true ),
            'selectors' => array(
                '{{WRAPPER}} .spf-modal-close' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'close_icon_bg', array(
            'label'     => __( 'Background', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-modal-close' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->end_controls_tab();

        $this->start_controls_tab( 'tab_close_icon_hover', array(
            'label' => __( 'Hover', 'smart-programme-finder' ),
        ) );

        $this->add_control( 'close_icon_color_hover', array(
            'label'     => __( 'Icon Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'global'    => array( 'active' => true ),
            'selectors' => array(
                '{{WRAPPER}} .spf-modal-close:hover' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'close_icon_bg_hover', array(
            'label'     => __( 'Background', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-modal-close:hover' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_control( 'close_icon_size', array(
            'label'      => __( 'Icon Size', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em' ),
            'range'      => array( 'px' => array( 'min' => 10, 'max' => 48, 'step' => 1 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-modal-close' => 'font-size: {{SIZE}}{{UNIT}};',
            ),
            'separator' => 'before',
        ) );

        $this->add_control( 'close_icon_border_radius', array(
            'label'      => __( 'Border Radius', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', '%' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ), '%' => array( 'min' => 0, 'max' => 50 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-modal-close' => 'border-radius: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'close_icon_padding', array(
            'label'      => __( 'Padding', 'smart-programme-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-modal-close' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'close_icon_position_top', array(
            'label'      => __( 'Position — Top', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'rem' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-modal-close' => 'top: {{SIZE}}{{UNIT}};',
            ),
            'separator' => 'before',
        ) );

        $this->add_responsive_control( 'close_icon_position_right', array(
            'label'      => __( 'Position — Right', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'rem' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-modal-close' => 'right: {{SIZE}}{{UNIT}};',
            ),
        ) );

        /* Popup Button — Normal / Hover Tabs */
        $this->add_control( 'heading_popup_btn', array(
            'label'     => __( 'Button', 'smart-programme-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->start_controls_tabs( 'tabs_popup_btn' );

        $this->start_controls_tab( 'tab_popup_btn_normal', array(
            'label' => __( 'Normal', 'smart-programme-finder' ),
        ) );

        $this->add_control( 'popup_btn_bg', array(
            'label'     => __( 'Background', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-modal-btn--reset' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'popup_btn_color', array(
            'label'     => __( 'Text Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-modal-btn--reset' => 'color: {{VALUE}};',
            ),
        ) );

        $this->end_controls_tab();

        $this->start_controls_tab( 'tab_popup_btn_hover', array(
            'label' => __( 'Hover', 'smart-programme-finder' ),
        ) );

        $this->add_control( 'popup_btn_bg_hover', array(
            'label'     => __( 'Background', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-modal-btn--reset:hover' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'popup_btn_color_hover', array(
            'label'     => __( 'Text Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-modal-btn--reset:hover' => 'color: {{VALUE}};',
            ),
        ) );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_control( 'popup_btn_border_radius', array(
            'label'      => __( 'Button Border Radius', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-modal-btn--reset' => 'border-radius: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'popup_btn_padding', array(
            'label'      => __( 'Button Padding', 'smart-programme-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-modal-btn--reset' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
            ),
        ) );

        $this->end_controls_section();

        /* ─────────────────────────────────────
         * Confirmation Styles — Inline Message
         * ───────────────────────────────────── */
        $this->start_controls_section( 'style_confirmation_inline', array(
            'label' => __( 'Confirmation — Inline', 'smart-programme-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'inline_desc', array(
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => '<p style="font-size:12px;color:#94a3b8;margin:0 0 10px;">' . esc_html__( 'Styles for the below-form confirmation message.', 'smart-programme-finder' ) . '</p>',
            'content_classes' => 'elementor-descriptor',
        ) );

        $this->add_control( 'inline_preview', array(
            'label'        => __( 'Preview Inline Confirmation in Editor', 'smart-programme-finder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Show', 'smart-programme-finder' ),
            'label_off'    => __( 'Hide', 'smart-programme-finder' ),
            'return_value' => 'yes',
            'default'      => '',
            'prefix_class' => 'spf-inline-preview-',
            'separator'    => 'after',
        ) );

        $this->add_control( 'inline_bg', array(
            'label'     => __( 'Background', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-confirmation-inline' => 'background: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'inline_icon_color', array(
            'label'     => __( 'Icon Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-inline-icon' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'inline_icon_size', array(
            'label'      => __( 'Icon Size', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 20, 'max' => 64, 'step' => 2 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-inline-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'inline_title_color', array(
            'label'     => __( 'Title Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-inline-title' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'inline_title_typography',
            'label'    => __( 'Title Typography', 'smart-programme-finder' ),
            'selector' => '{{WRAPPER}} .spf-inline-title',
        ) );

        $this->add_control( 'inline_body_color', array(
            'label'     => __( 'Body Text Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-inline-body' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'inline_body_typography',
            'label'    => __( 'Body Typography', 'smart-programme-finder' ),
            'selector' => '{{WRAPPER}} .spf-inline-body',
        ) );

        $this->add_control( 'inline_border_radius', array(
            'label'      => __( 'Border Radius', 'smart-programme-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px' ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-confirmation-inline' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
            'separator' => 'before',
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'inline_border',
            'selector' => '{{WRAPPER}} .spf-confirmation-inline',
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'inline_shadow',
            'selector' => '{{WRAPPER}} .spf-confirmation-inline',
        ) );

        $this->add_responsive_control( 'inline_padding', array(
            'label'      => __( 'Padding', 'smart-programme-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-confirmation-inline' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        /* Inline Button — Normal / Hover Tabs */
        $this->add_control( 'heading_inline_btn', array(
            'label'     => __( 'Button', 'smart-programme-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->start_controls_tabs( 'tabs_inline_btn' );

        $this->start_controls_tab( 'tab_inline_btn_normal', array(
            'label' => __( 'Normal', 'smart-programme-finder' ),
        ) );

        $this->add_control( 'inline_btn_bg', array(
            'label'     => __( 'Background', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-inline-btn--reset' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'inline_btn_color', array(
            'label'     => __( 'Text Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-inline-btn--reset' => 'color: {{VALUE}};',
            ),
        ) );

        $this->end_controls_tab();

        $this->start_controls_tab( 'tab_inline_btn_hover', array(
            'label' => __( 'Hover', 'smart-programme-finder' ),
        ) );

        $this->add_control( 'inline_btn_bg_hover', array(
            'label'     => __( 'Background', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-inline-btn--reset:hover' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'inline_btn_color_hover', array(
            'label'     => __( 'Text Color', 'smart-programme-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .spf-inline-btn--reset:hover' => 'color: {{VALUE}};',
            ),
        ) );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_control( 'inline_btn_border_radius', array(
            'label'      => __( 'Button Border Radius', 'smart-programme-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-inline-btn--reset' => 'border-radius: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'inline_btn_padding', array(
            'label'      => __( 'Button Padding', 'smart-programme-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .spf-inline-btn--reset' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();
    }

    /* ══════════════════════════════════════════
     * RENDER
     * ══════════════════════════════════════════ */
    protected function render(): void {
        $s       = $this->get_settings_for_display();
        $form_id = absint( $s['form_id'] ?? 1 );

        if ( 0 === $form_id ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div style="padding:40px;text-align:center;background:#f8fafc;border:2px dashed #cbd5e1;border-radius:8px;">';
                echo '<p style="font-size:16px;color:#64748b;margin:0;"><strong>' . esc_html__( 'Programme Finder', 'smart-programme-finder' ) . '</strong></p>';
                echo '<p style="font-size:13px;color:#94a3b8;margin:8px 0 0;">' . esc_html__( 'Select a form from the Content tab.', 'smart-programme-finder' ) . '</p>';
                echo '</div>';
            } else {
                echo '<p>' . esc_html__( 'Please select a form in the widget settings.', 'smart-programme-finder' ) . '</p>';
            }
            return;
        }

        $columns     = $s['columns'] ?? '1';
        $btn_pos     = $s['button_position'] ?? 'full';
        $btn_text    = $s['button_text'] ?? '';
        $hide_labels = $s['hide_labels'] ?? '';
        $show_name   = $s['show_form_name'] ?? '';
        $show_desc   = $s['show_form_desc'] ?? '';

        $this->add_render_attribute( 'wrapper', 'class', 'spf-elementor-wrap' );
        $this->add_render_attribute( 'wrapper', 'data-columns', $columns );
        $this->add_render_attribute( 'wrapper', 'data-btn-position', $btn_pos );
        if ( '' !== $btn_text ) {
            $this->add_render_attribute( 'wrapper', 'data-btn-text', $btn_text );
        }
        if ( 'yes' === $hide_labels ) {
            $this->add_render_attribute( 'wrapper', 'data-hide-labels', '1' );
        }

        /* Resolve form name/description */
        $form_name = '';
        $form_desc = '';
        if ( 'yes' === $show_name || 'yes' === $show_desc ) {
            $forms = get_option( 'spf_forms', array() );
            foreach ( $forms as $f ) {
                if ( (int) $f['id'] === $form_id ) {
                    $form_name = $f['name'] ?? '';
                    $form_desc = $f['description'] ?? '';
                    break;
                }
            }
        }
        ?>
        <div <?php $this->print_render_attribute_string( 'wrapper' ); ?>>
            <?php if ( 'yes' === $show_name && '' !== $form_name ) : ?>
                <h3 class="spf-form-title"><?php echo esc_html( $form_name ); ?></h3>
            <?php endif; ?>
            <?php if ( 'yes' === $show_desc && '' !== $form_desc ) : ?>
                <p class="spf-form-description"><?php echo esc_html( $form_desc ); ?></p>
            <?php endif; ?>
            <?php
            /* Pass arrow icon to the form template via global.
             * Wrap in <span class="spf-select-arrow-icon"> so our CSS class
             * is always present regardless of how Icons_Manager renders the tag.
             */
            $arrow_icon_html = '';
            $arrow_hide      = false;
            if ( 'yes' === ( $s['arrow_show'] ?? 'yes' ) ) {
                if ( ! empty( $s['arrow_icon']['value'] ) ) {
                    ob_start();
                    \Elementor\Icons_Manager::render_icon( $s['arrow_icon'], array( 'aria-hidden' => 'true' ) );
                    $inner = ob_get_clean();
                    $arrow_icon_html = '<span class="spf-select-arrow-icon" aria-hidden="true">' . $inner . '</span>';
                }
                // else: no icon selected → fall through to default CSS ::after chevron
            } else {
                $arrow_hide = true; // arrow_show = 'no' → suppress ::after too
            }
            $GLOBALS['spf_elementor_arrow_icon'] = $arrow_icon_html;
            $GLOBALS['spf_elementor_arrow_hide'] = $arrow_hide;
            $GLOBALS['spf_elementor_btn_pos']    = $btn_pos;
            $GLOBALS['spf_elementor_lock_scroll'] = ( '1' === ( $s['popup_lock_scroll'] ?? '1' ) ) ? '1' : '0';
            echo do_shortcode( '[spf_form id="' . esc_attr( $form_id ) . '"]' );
            $GLOBALS['spf_elementor_arrow_icon'] = '';
            $GLOBALS['spf_elementor_arrow_hide'] = false;
            $GLOBALS['spf_elementor_btn_pos']    = '';
            $GLOBALS['spf_elementor_lock_scroll'] = '';
            ?>
        </div>
        <?php
    }
}
