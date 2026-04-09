<?php
/**
 * Admin screens — WPForms-style form editor with sidebar navigation.
 *
 * @package SmartProgrammeFinder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPF_Admin {

    /* ──────────────────────────────────────────
     * Constants
     * ──────────────────────────────────────── */

    private const FIELD_TYPES = array(
        'text'     => array( 'label' => 'Single Line Text',  'icon' => 'dashicons-editor-textcolor' ),
        'textarea' => array( 'label' => 'Paragraph Text',    'icon' => 'dashicons-editor-paragraph' ),
        'select'   => array( 'label' => 'Dropdown',          'icon' => 'dashicons-arrow-down-alt2' ),
        'radio'    => array( 'label' => 'Multiple Choice',   'icon' => 'dashicons-marker' ),
        'checkbox' => array( 'label' => 'Checkboxes',        'icon' => 'dashicons-yes-alt' ),
        'number'   => array( 'label' => 'Numbers',           'icon' => 'dashicons-editor-ol' ),
        'email'    => array( 'label' => 'Email',             'icon' => 'dashicons-email' ),
    );

    private const OPTION_TYPES = array( 'select', 'radio', 'checkbox' );

    public const DEFAULT_APPEARANCE = array(
        'button_text'        => 'Find My Programme',
        'button_position'    => 'full',
        'columns'            => '1',
        'form_width'         => '560',
        'primary_color'      => '#2563eb',
        'primary_hover'      => '#1d4ed8',
        'label_color'        => '#1e293b',
        'input_bg'           => '#ffffff',
        'input_border'       => '#e2e8f0',
        'input_text'         => '#1e293b',
        'input_radius'       => '8',
        'btn_text_color'     => '#ffffff',
        'btn_radius'         => '8',
        'field_spacing'      => '20',
        'form_bg'            => '',
        'form_padding'       => '0',
        'form_border_radius' => '0',
    );

    public const DEFAULT_GENERAL = array(
        'form_description'   => '',
        'submit_text'        => 'Find My Programme',
        'submit_processing'  => 'Finding your best match...',
        'conf_btn_text'      => 'Try Again',
        'enable_ajax'        => true,
    );

    public const DEFAULT_CONFIRMATION = array(
        'id'                 => 1,
        'name'               => 'Default Confirmation',
        'type'               => 'message',
        'message'            => 'Based on the information provided, we recommend the following programme for you.',
        'redirect_url'       => '',
        'conditional_logic'  => false,
        'conditions'         => array(),
    );

    public const DEFAULT_NOTIFICATION = array(
        'id'                 => 1,
        'name'               => 'Default Notification',
        'enabled'            => false,
        'email_to'           => '{admin_email}',
        'email_subject'      => 'New Programme Finder Submission',
        'email_from_name'    => '',
        'email_from_email'   => '',
        'email_reply_to'     => '',
        'email_message'      => '{all_fields}',
    );

    /* ──────────────────────────────────────────
     * Constructor
     * ──────────────────────────────────────── */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_form_actions' ) );
    }

    /* ══════════════════════════════════════════
     * Menu registration
     * ══════════════════════════════════════════ */
    public function register_menu(): void {
        add_menu_page(
            __( 'Programme Finder', 'smart-programme-finder' ),
            __( 'Programme Finder', 'smart-programme-finder' ),
            'manage_options',
            'spf-dashboard',
            array( $this, 'render_dashboard' ),
            'dashicons-search',
            30
        );

        add_submenu_page(
            'spf-dashboard',
            __( 'All Forms', 'smart-programme-finder' ),
            __( 'All Forms', 'smart-programme-finder' ),
            'manage_options',
            'spf-forms',
            array( $this, 'render_forms' )
        );

        // Hidden page — form editor (WPForms-style)
        add_submenu_page(
            null,
            __( 'Edit Form', 'smart-programme-finder' ),
            __( 'Edit Form', 'smart-programme-finder' ),
            'manage_options',
            'spf-form-edit',
            array( $this, 'render_form_edit' )
        );

        add_submenu_page(
            'spf-dashboard',
            __( 'Rule Builder', 'smart-programme-finder' ),
            __( 'Rule Builder', 'smart-programme-finder' ),
            'manage_options',
            'spf-rule-builder',
            array( $this, 'render_rule_builder' )
        );

        add_submenu_page(
            'spf-dashboard',
            __( 'Settings', 'smart-programme-finder' ),
            __( 'Settings', 'smart-programme-finder' ),
            'manage_options',
            'spf-settings',
            array( $this, 'render_settings' )
        );
    }

    /* ══════════════════════════════════════════
     * Admin asset enqueue
     * ══════════════════════════════════════════ */
    public function enqueue_admin_assets( string $hook ): void {
        $plugin_pages = array(
            'toplevel_page_spf-dashboard',
            'programme-finder_page_spf-forms',
            'admin_page_spf-form-edit',
            'programme-finder_page_spf-rule-builder',
            'programme-finder_page_spf-settings',
        );

        if ( ! in_array( $hook, $plugin_pages, true ) ) {
            return;
        }

        wp_enqueue_style(
            'spf-admin',
            SPF_PLUGIN_URL . 'assets/css/admin.css',
            array( 'dashicons' ),
            SPF_VERSION
        );

        wp_enqueue_script(
            'spf-admin',
            SPF_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'wp-util' ),
            SPF_VERSION,
            true
        );

        // Form editor page — load color picker + localize data
        if ( 'admin_page_spf-form-edit' === $hook ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
            $form    = $this->get_form( $form_id );

            if ( $form ) {
                wp_localize_script( 'spf-admin', 'spf_editor', array(
                    'ajax_url'    => admin_url( 'admin-ajax.php' ),
                    'nonce'       => wp_create_nonce( 'spf_editor_nonce' ),
                    'form_id'     => $form_id,
                    'form'        => $form,
                    'fields'      => $this->get_fields_for_form( $form_id ),
                    'field_types' => self::FIELD_TYPES,
                    'option_types'=> self::OPTION_TYPES,
                    'forms_url'   => admin_url( 'admin.php?page=spf-forms' ),
                ));
            }
        }
    }

    /* ══════════════════════════════════════════
     * POST / GET dispatcher
     * ══════════════════════════════════════════ */
    public function handle_form_actions(): void {
        if ( isset( $_POST['spf_create_form'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_create_form_action', 'spf_form_nonce' );
            $this->create_form();
        }

        if ( isset( $_GET['spf_delete_form'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_delete_form_' . intval( $_GET['spf_delete_form'] ) );
            $this->delete_form( intval( $_GET['spf_delete_form'] ) );
        }

        if ( isset( $_POST['spf_save_form_editor'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_save_form_editor_action', 'spf_editor_nonce' );
            $this->save_form_editor();
        }

        if ( isset( $_POST['spf_save_rule'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_save_rule_action', 'spf_rule_nonce' );
            $this->save_rule();
        }

        if ( isset( $_GET['spf_delete_rule'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_delete_rule_' . intval( $_GET['spf_delete_rule'] ) );
            $this->delete_rule( intval( $_GET['spf_delete_rule'] ) );
        }

        if ( isset( $_POST['spf_save_settings'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_save_settings_action', 'spf_settings_nonce' );
            $this->save_settings();
        }
    }

    private function require_admin_cap(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'smart-programme-finder' ) );
        }
    }

    /* ══════════════════════════════════════════
     * FORM persistence
     * ══════════════════════════════════════════ */
    private function create_form(): void {
        $name = isset( $_POST['spf_form_name'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_form_name'] ) ) : '';

        if ( '' === $name ) {
            add_settings_error( 'spf_messages', 'spf_error', __( 'Form name is required.', 'smart-programme-finder' ), 'error' );
            return;
        }

        $forms   = get_option( 'spf_forms', array() );
        $form_id = count( $forms ) > 0 ? max( array_column( $forms, 'id' ) ) + 1 : 1;

        $forms[] = array(
            'id'            => $form_id,
            'name'          => $name,
            'created_at'    => current_time( 'mysql' ),
            'settings'      => self::DEFAULT_APPEARANCE,
            'general'       => self::DEFAULT_GENERAL,
            'confirmations' => array( self::DEFAULT_CONFIRMATION ),
            'notifications' => array( self::DEFAULT_NOTIFICATION ),
        );

        update_option( 'spf_forms', $forms );

        wp_safe_redirect( admin_url( 'admin.php?page=spf-form-edit&form_id=' . $form_id ) );
        exit;
    }

    private function delete_form( int $form_id ): void {
        $forms = get_option( 'spf_forms', array() );
        $forms = array_values( array_filter( $forms, function ( $f ) use ( $form_id ) {
            return (int) $f['id'] !== $form_id;
        } ) );
        update_option( 'spf_forms', $forms );

        $fields = get_option( 'spf_fields', array() );
        $fields = array_values( array_filter( $fields, function ( $f ) use ( $form_id ) {
            return (int) ( $f['form_id'] ?? 0 ) !== $form_id;
        } ) );
        update_option( 'spf_fields', $fields );

        $rules = get_option( 'spf_rules', array() );
        $rules = array_values( array_filter( $rules, function ( $r ) use ( $form_id ) {
            return (int) ( $r['form_id'] ?? 0 ) !== $form_id;
        } ) );
        update_option( 'spf_rules', $rules );

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'spf-forms',
            'message' => 'form_deleted',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /* ══════════════════════════════════════════
     * FORM EDITOR — unified save (fields + settings)
     * ══════════════════════════════════════════ */
    private function save_form_editor(): void {
        $form_id = isset( $_POST['spf_form_id'] ) ? absint( $_POST['spf_form_id'] ) : 0;
        if ( 0 === $form_id ) {
            return;
        }

        $forms = get_option( 'spf_forms', array() );

        foreach ( $forms as &$form ) {
            if ( (int) $form['id'] !== $form_id ) {
                continue;
            }

            // ── General settings ──
            $form['name'] = isset( $_POST['spf_form_name'] )
                ? sanitize_text_field( wp_unslash( $_POST['spf_form_name'] ) )
                : $form['name'];

            $general = array(
                'form_description'  => isset( $_POST['spf_form_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['spf_form_description'] ) ) : '',
                'submit_text'       => isset( $_POST['spf_submit_text'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_submit_text'] ) ) : 'Find My Programme',
                'submit_processing' => isset( $_POST['spf_submit_processing'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_submit_processing'] ) ) : 'Finding your best match...',
                'conf_btn_text'     => isset( $_POST['spf_conf_btn_text'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_conf_btn_text'] ) ) : 'Try Again',
                'enable_ajax'       => ! empty( $_POST['spf_enable_ajax'] ),
            );
            $form['general'] = $general;

            // Also sync submit text to appearance
            $settings = $form['settings'] ?? self::DEFAULT_APPEARANCE;
            $settings['button_text'] = $general['submit_text'];

            // ── Appearance settings ──
            $appearance_keys = array_keys( self::DEFAULT_APPEARANCE );
            foreach ( $appearance_keys as $key ) {
                $post_key = 'spf_' . $key;
                if ( isset( $_POST[ $post_key ] ) ) {
                    $settings[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
                }
            }
            $form['settings'] = $settings;

            // ── Confirmations ──
            $confirmations = array();
            if ( ! empty( $_POST['spf_confirmations'] ) && is_array( $_POST['spf_confirmations'] ) ) {
                foreach ( $_POST['spf_confirmations'] as $i => $conf ) {
                    $c = array(
                        'id'                => absint( $conf['id'] ?? ( $i + 1 ) ),
                        'name'              => sanitize_text_field( wp_unslash( $conf['name'] ?? 'Confirmation' ) ),
                        'type'              => sanitize_text_field( wp_unslash( $conf['type'] ?? 'message' ) ),
                        'message'           => wp_kses_post( wp_unslash( $conf['message'] ?? '' ) ),
                        'redirect_url'      => esc_url_raw( wp_unslash( $conf['redirect_url'] ?? '' ) ),
                        'conditional_logic' => ! empty( $conf['conditional_logic'] ),
                        'conditions'        => array(),
                    );

                    // Parse conditions
                    if ( ! empty( $conf['conditions'] ) && is_array( $conf['conditions'] ) ) {
                        foreach ( $conf['conditions'] as $cond ) {
                            $c['conditions'][] = array(
                                'field'    => sanitize_text_field( wp_unslash( $cond['field'] ?? '' ) ),
                                'operator' => sanitize_text_field( wp_unslash( $cond['operator'] ?? 'is' ) ),
                                'value'    => sanitize_text_field( wp_unslash( $cond['value'] ?? '' ) ),
                            );
                        }
                    }

                    $confirmations[] = $c;
                }
            }
            if ( empty( $confirmations ) ) {
                $confirmations = array( self::DEFAULT_CONFIRMATION );
            }
            $form['confirmations'] = $confirmations;

            // ── Notifications ──
            $notifications = array();
            if ( ! empty( $_POST['spf_notifications'] ) && is_array( $_POST['spf_notifications'] ) ) {
                foreach ( $_POST['spf_notifications'] as $i => $notif ) {
                    $notifications[] = array(
                        'id'               => absint( $notif['id'] ?? ( $i + 1 ) ),
                        'name'             => sanitize_text_field( wp_unslash( $notif['name'] ?? 'Notification' ) ),
                        'enabled'          => ! empty( $notif['enabled'] ),
                        'email_to'         => sanitize_text_field( wp_unslash( $notif['email_to'] ?? '{admin_email}' ) ),
                        'email_subject'    => sanitize_text_field( wp_unslash( $notif['email_subject'] ?? '' ) ),
                        'email_from_name'  => sanitize_text_field( wp_unslash( $notif['email_from_name'] ?? '' ) ),
                        'email_from_email' => sanitize_email( wp_unslash( $notif['email_from_email'] ?? '' ) ),
                        'email_reply_to'   => sanitize_email( wp_unslash( $notif['email_reply_to'] ?? '' ) ),
                        'email_message'    => wp_kses_post( wp_unslash( $notif['email_message'] ?? '{all_fields}' ) ),
                    );
                }
            }
            if ( empty( $notifications ) ) {
                $notifications = array( self::DEFAULT_NOTIFICATION );
            }
            $form['notifications'] = $notifications;

            break;
        }
        unset( $form );

        update_option( 'spf_forms', $forms );

        // ── Save fields ──
        $this->save_fields_from_editor( $form_id );

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'spf-form-edit',
            'form_id' => $form_id,
            'message' => 'form_saved',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private function save_fields_from_editor( int $form_id ): void {
        // Remove existing fields for this form
        $all_fields = get_option( 'spf_fields', array() );
        $other_fields = array_values( array_filter( $all_fields, function ( $f ) use ( $form_id ) {
            return (int) ( $f['form_id'] ?? 0 ) !== $form_id;
        } ) );

        $new_fields = array();
        if ( ! empty( $_POST['spf_fields'] ) && is_array( $_POST['spf_fields'] ) ) {
            $max_id = 0;
            foreach ( $other_fields as $f ) {
                if ( (int) $f['id'] > $max_id ) {
                    $max_id = (int) $f['id'];
                }
            }

            foreach ( $_POST['spf_fields'] as $field_data ) {
                $max_id++;
                $label = sanitize_text_field( wp_unslash( $field_data['label'] ?? '' ) );
                $type  = sanitize_text_field( wp_unslash( $field_data['type'] ?? 'text' ) );
                $size  = sanitize_text_field( wp_unslash( $field_data['size'] ?? 'medium' ) );

                if ( ! array_key_exists( $type, self::FIELD_TYPES ) ) {
                    $type = 'text';
                }
                if ( ! in_array( $size, array( 'small', 'medium', 'large' ), true ) ) {
                    $size = 'medium';
                }

                $field_key = ! empty( $field_data['field_key'] )
                    ? sanitize_title( $field_data['field_key'] )
                    : sanitize_title( $label ) . '_' . $max_id;

                $parsed_options = array();
                if ( in_array( $type, self::OPTION_TYPES, true ) && ! empty( $field_data['options'] ) ) {
                    $parsed_options = array_values( array_filter( array_map( 'trim', explode( ',', sanitize_textarea_field( wp_unslash( $field_data['options'] ) ) ) ) ) );
                }

                $new_fields[] = array(
                    'id'          => isset( $field_data['id'] ) ? absint( $field_data['id'] ) : $max_id,
                    'form_id'     => $form_id,
                    'field_key'   => $field_key,
                    'label'       => $label,
                    'type'        => $type,
                    'size'        => $size,
                    'placeholder' => sanitize_text_field( wp_unslash( $field_data['placeholder'] ?? '' ) ),
                    'options'     => $parsed_options,
                    'required'    => ! empty( $field_data['required'] ),
                );
            }
        }

        update_option( 'spf_fields', array_merge( $other_fields, $new_fields ) );
    }

    /* ══════════════════════════════════════════
     * RULE persistence
     * ══════════════════════════════════════════ */
    private function save_rule(): void {
        $form_id   = isset( $_POST['spf_form_id'] ) ? absint( $_POST['spf_form_id'] ) : 0;
        $field_key = isset( $_POST['spf_rule_field'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_rule_field'] ) ) : '';
        $value     = isset( $_POST['spf_rule_value'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_rule_value'] ) ) : '';
        $result    = isset( $_POST['spf_rule_result'] ) ? sanitize_textarea_field( wp_unslash( $_POST['spf_rule_result'] ) ) : '';

        if ( '' === $field_key || '' === $value || '' === $result || 0 === $form_id ) {
            add_settings_error( 'spf_messages', 'spf_error', __( 'All rule fields are required.', 'smart-programme-finder' ), 'error' );
            return;
        }

        $rules   = get_option( 'spf_rules', array() );
        $rule_id = count( $rules ) > 0 ? max( array_column( $rules, 'id' ) ) + 1 : 1;

        $rules[] = array(
            'id'        => $rule_id,
            'form_id'   => $form_id,
            'field_key' => $field_key,
            'value'     => $value,
            'result'    => $result,
            'priority'  => $rule_id,
            'status'    => 'active',
        );

        update_option( 'spf_rules', $rules );

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'spf-rule-builder',
            'form_id' => $form_id,
            'message' => 'rule_saved',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private function delete_rule( int $index ): void {
        $rules   = get_option( 'spf_rules', array() );
        $form_id = 0;

        foreach ( $rules as $r ) {
            if ( (int) $r['id'] === $index ) {
                $form_id = (int) ( $r['form_id'] ?? 0 );
                break;
            }
        }

        $rules = array_values( array_filter( $rules, function ( $rule ) use ( $index ) {
            return (int) $rule['id'] !== $index;
        } ) );
        update_option( 'spf_rules', $rules );

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'spf-rule-builder',
            'form_id' => $form_id,
            'message' => 'rule_deleted',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /* ══════════════════════════════════════════
     * Settings persistence
     * ══════════════════════════════════════════ */
    private function save_settings(): void {
        $fallback = isset( $_POST['spf_fallback_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['spf_fallback_message'] ) ) : '';

        update_option( 'spf_settings', array(
            'fallback_message' => $fallback,
        ) );

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'spf-settings',
            'message' => 'settings_saved',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /* ══════════════════════════════════════════
     * HELPERS
     * ══════════════════════════════════════════ */
    private function get_forms(): array {
        return get_option( 'spf_forms', array() );
    }

    private function get_form( int $form_id ): ?array {
        foreach ( $this->get_forms() as $form ) {
            if ( (int) $form['id'] === $form_id ) {
                return $form;
            }
        }
        return null;
    }

    public static function get_form_settings( int $form_id ): array {
        $forms = get_option( 'spf_forms', array() );
        foreach ( $forms as $form ) {
            if ( (int) $form['id'] === $form_id ) {
                return wp_parse_args( $form['settings'] ?? array(), self::DEFAULT_APPEARANCE );
            }
        }
        return self::DEFAULT_APPEARANCE;
    }

    private function get_fields_for_form( int $form_id ): array {
        $all = get_option( 'spf_fields', array() );
        return array_values( array_filter( $all, function ( $f ) use ( $form_id ) {
            return (int) ( $f['form_id'] ?? 0 ) === $form_id;
        } ) );
    }

    private function get_rules_for_form( int $form_id ): array {
        $all = get_option( 'spf_rules', array() );
        return array_values( array_filter( $all, function ( $r ) use ( $form_id ) {
            return (int) ( $r['form_id'] ?? 0 ) === $form_id;
        } ) );
    }

    private function get_current_form_id(): int {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
        if ( $id > 0 && $this->get_form( $id ) ) {
            return $id;
        }
        $forms = $this->get_forms();
        return ! empty( $forms ) ? (int) $forms[0]['id'] : 0;
    }

    private function render_form_selector( string $current_page, int $selected_id ): void {
        $forms = $this->get_forms();
        if ( empty( $forms ) ) {
            echo '<div class="notice notice-warning"><p>';
            esc_html_e( 'No forms created yet. ', 'smart-programme-finder' );
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=spf-forms' ) ) . '">';
            esc_html_e( 'Create a form first →', 'smart-programme-finder' );
            echo '</a></p></div>';
            return;
        }
        ?>
        <div class="spf-form-selector">
            <label for="spf-form-select"><strong><?php esc_html_e( 'Select Form:', 'smart-programme-finder' ); ?></strong></label>
            <select id="spf-form-select" onchange="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=' . $current_page ) ); ?>&form_id='+this.value;">
                <?php foreach ( $forms as $form ) : ?>
                <option value="<?php echo esc_attr( $form['id'] ); ?>" <?php selected( (int) $form['id'], $selected_id ); ?>>
                    <?php echo esc_html( $form['name'] ); ?> (ID: <?php echo esc_html( $form['id'] ); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════
     * PAGE RENDERERS
     * ══════════════════════════════════════════ */

    /* ── Dashboard ──────────────────────────── */
    public function render_dashboard(): void {
        $forms  = $this->get_forms();
        $fields = get_option( 'spf_fields', array() );
        $rules  = get_option( 'spf_rules', array() );
        ?>
        <div class="wrap spf-admin-wrap">
            <h1><?php esc_html_e( 'Smart Programme Finder', 'smart-programme-finder' ); ?></h1>
            <p class="spf-subtitle"><?php esc_html_e( 'Build recommendation forms and guide visitors to the right programme — no coding required.', 'smart-programme-finder' ); ?></p>

            <div class="spf-dashboard-cards">
                <div class="spf-card">
                    <h2><?php esc_html_e( 'Forms', 'smart-programme-finder' ); ?></h2>
                    <p><?php echo esc_html( sprintf( _n( '%d form created.', '%d forms created.', count( $forms ), 'smart-programme-finder' ), count( $forms ) ) ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-forms' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Manage Forms', 'smart-programme-finder' ); ?></a>
                </div>

                <div class="spf-card">
                    <h2><?php esc_html_e( 'Fields', 'smart-programme-finder' ); ?></h2>
                    <p><?php echo esc_html( sprintf( _n( '%d field created.', '%d fields created.', count( $fields ), 'smart-programme-finder' ), count( $fields ) ) ); ?></p>
                </div>

                <div class="spf-card">
                    <h2><?php esc_html_e( 'Rules', 'smart-programme-finder' ); ?></h2>
                    <p><?php echo esc_html( sprintf( _n( '%d rule defined.', '%d rules defined.', count( $rules ), 'smart-programme-finder' ), count( $rules ) ) ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-rule-builder' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Rule Builder', 'smart-programme-finder' ); ?></a>
                </div>

                <div class="spf-card">
                    <h2><?php esc_html_e( 'Embed', 'smart-programme-finder' ); ?></h2>
                    <p><?php esc_html_e( 'Use a shortcode to embed any form:', 'smart-programme-finder' ); ?></p>
                    <?php if ( ! empty( $forms ) ) : ?>
                        <?php foreach ( $forms as $form ) : ?>
                        <code class="spf-shortcode-display">[spf_form id="<?php echo esc_html( $form['id'] ); ?>"]</code>
                        <span class="spf-hint"><?php echo esc_html( $form['name'] ); ?></span><br>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="spf-hint"><?php esc_html_e( 'Create a form first to get a shortcode.', 'smart-programme-finder' ); ?></p>
                    <?php endif; ?>
                    <p class="spf-hint" style="margin-top:8px;"><?php esc_html_e( 'Or use the Elementor widget "Programme Finder".', 'smart-programme-finder' ); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /* ── All Forms list ──────────────────────── */
    public function render_forms(): void {
        $forms = $this->get_forms();
        ?>
        <div class="wrap spf-admin-wrap">
            <h1><?php esc_html_e( 'All Forms', 'smart-programme-finder' ); ?></h1>

            <?php $this->render_admin_notices(); ?>

            <div class="spf-card spf-card--form">
                <h2><?php esc_html_e( 'Create New Form', 'smart-programme-finder' ); ?></h2>
                <form method="post">
                    <?php wp_nonce_field( 'spf_create_form_action', 'spf_form_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="spf_form_name"><?php esc_html_e( 'Form Name', 'smart-programme-finder' ); ?></label></th>
                            <td><input type="text" id="spf_form_name" name="spf_form_name" class="regular-text" required placeholder="<?php esc_attr_e( 'e.g. Undergraduate Finder', 'smart-programme-finder' ); ?>"></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" name="spf_create_form" class="button button-primary"><?php esc_html_e( 'Create Form', 'smart-programme-finder' ); ?></button>
                    </p>
                </form>
            </div>

            <?php if ( ! empty( $forms ) ) : ?>
            <div class="spf-card">
                <h2><?php esc_html_e( 'Your Forms', 'smart-programme-finder' ); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ID', 'smart-programme-finder' ); ?></th>
                            <th><?php esc_html_e( 'Name', 'smart-programme-finder' ); ?></th>
                            <th><?php esc_html_e( 'Fields', 'smart-programme-finder' ); ?></th>
                            <th><?php esc_html_e( 'Rules', 'smart-programme-finder' ); ?></th>
                            <th><?php esc_html_e( 'Shortcode', 'smart-programme-finder' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'smart-programme-finder' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $forms as $form ) :
                            $fid = (int) $form['id'];
                        ?>
                        <tr>
                            <td><?php echo esc_html( $fid ); ?></td>
                            <td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-form-edit&form_id=' . $fid ) ); ?>"><?php echo esc_html( $form['name'] ); ?></a></strong></td>
                            <td><?php echo esc_html( count( $this->get_fields_for_form( $fid ) ) ); ?></td>
                            <td><?php echo esc_html( count( $this->get_rules_for_form( $fid ) ) ); ?></td>
                            <td><code>[spf_form id="<?php echo esc_html( $fid ); ?>"]</code></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-form-edit&form_id=' . $fid ) ); ?>"><?php esc_html_e( 'Edit', 'smart-programme-finder' ); ?></a>
                                &nbsp;|&nbsp;
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-rule-builder&form_id=' . $fid ) ); ?>"><?php esc_html_e( 'Rules', 'smart-programme-finder' ); ?></a>
                                &nbsp;|&nbsp;
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'spf-forms', 'spf_delete_form' => $fid ), admin_url( 'admin.php' ) ), 'spf_delete_form_' . $fid ) ); ?>" class="spf-delete-link" onclick="return confirm('<?php esc_attr_e( 'Delete this form and all its fields & rules?', 'smart-programme-finder' ); ?>');"><?php esc_html_e( 'Delete', 'smart-programme-finder' ); ?></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else : ?>
                <div class="spf-card spf-card--empty">
                    <p><?php esc_html_e( 'No forms created yet. Create your first form above to get started.', 'smart-programme-finder' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════
     * FORM EDITOR — WPForms-style full-screen
     * ══════════════════════════════════════════ */
    public function render_form_edit(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
        $form    = $form_id > 0 ? $this->get_form( $form_id ) : null;

        if ( ! $form ) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Form not found.', 'smart-programme-finder' ) . '</p></div></div>';
            return;
        }

        $fields        = $this->get_fields_for_form( $form_id );
        $general       = wp_parse_args( $form['general'] ?? array(), self::DEFAULT_GENERAL );
        $confirmations = ! empty( $form['confirmations'] ) ? $form['confirmations'] : array( self::DEFAULT_CONFIRMATION );
        $notifications = ! empty( $form['notifications'] ) ? $form['notifications'] : array( self::DEFAULT_NOTIFICATION );
        $settings      = wp_parse_args( $form['settings'] ?? array(), self::DEFAULT_APPEARANCE );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $saved = isset( $_GET['message'] ) && 'form_saved' === $_GET['message'];
        ?>
        <div id="spf-builder" class="spf-builder-wrap">

            <!-- ═══ TOP BAR ═══ -->
            <div class="spf-builder-topbar">
                <div class="spf-topbar-left">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-forms' ) ); ?>" class="spf-topbar-close" title="<?php esc_attr_e( 'Back to All Forms', 'smart-programme-finder' ); ?>">&times;</a>
                    <span class="spf-topbar-editing"><?php esc_html_e( 'Now editing', 'smart-programme-finder' ); ?> <strong><?php echo esc_html( $form['name'] ); ?></strong></span>
                </div>
                <div class="spf-topbar-right">
                    <button type="button" class="spf-topbar-btn spf-topbar-btn--embed" data-shortcode='[spf_form id="<?php echo esc_attr( $form_id ); ?>"]'>
                        <span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Embed', 'smart-programme-finder' ); ?>
                    </button>
                    <button type="submit" form="spf-editor-form" class="spf-topbar-btn spf-topbar-btn--save">
                        <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save', 'smart-programme-finder' ); ?>
                    </button>
                </div>
            </div>

            <?php if ( $saved ) : ?>
            <div class="spf-builder-notice spf-builder-notice--success">
                <?php esc_html_e( 'Form saved successfully.', 'smart-programme-finder' ); ?>
            </div>
            <?php endif; ?>

            <form id="spf-editor-form" method="post" class="spf-builder-body">
                <?php wp_nonce_field( 'spf_save_form_editor_action', 'spf_editor_nonce' ); ?>
                <input type="hidden" name="spf_form_id" value="<?php echo esc_attr( $form_id ); ?>">
                <input type="hidden" name="spf_save_form_editor" value="1">

                <!-- ═══ LEFT SIDEBAR ═══ -->
                <div class="spf-builder-sidebar">

                    <!-- Icon nav -->
                    <div class="spf-sidebar-nav">
                        <button type="button" class="spf-nav-btn spf-nav-btn--active" data-panel="fields" title="<?php esc_attr_e( 'Fields', 'smart-programme-finder' ); ?>">
                            <span class="dashicons dashicons-feedback"></span>
                            <span class="spf-nav-label"><?php esc_html_e( 'Fields', 'smart-programme-finder' ); ?></span>
                        </button>
                        <button type="button" class="spf-nav-btn" data-panel="settings" title="<?php esc_attr_e( 'Settings', 'smart-programme-finder' ); ?>">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <span class="spf-nav-label"><?php esc_html_e( 'Settings', 'smart-programme-finder' ); ?></span>
                        </button>
                        <button type="button" class="spf-nav-btn" data-panel="appearance" title="<?php esc_attr_e( 'Appearance', 'smart-programme-finder' ); ?>">
                            <span class="dashicons dashicons-admin-appearance"></span>
                            <span class="spf-nav-label"><?php esc_html_e( 'Appearance', 'smart-programme-finder' ); ?></span>
                        </button>
                    </div>

                    <!-- Panel contents -->
                    <div class="spf-sidebar-panels">

                        <!-- ═══ FIELDS PANEL ═══ -->
                        <div class="spf-panel spf-panel--active" data-panel="fields">
                            <div class="spf-panel-tabs">
                                <button type="button" class="spf-panel-tab spf-panel-tab--active" data-tab="add-fields">
                                    <span class="dashicons dashicons-welcome-add-page"></span> <?php esc_html_e( 'Add Fields', 'smart-programme-finder' ); ?>
                                </button>
                                <button type="button" class="spf-panel-tab" data-tab="field-options">
                                    <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Field Options', 'smart-programme-finder' ); ?>
                                </button>
                            </div>

                            <!-- Add Fields sub-tab -->
                            <div class="spf-panel-content spf-panel-content--active" data-tab="add-fields">
                                <h3 class="spf-panel-section-title"><?php esc_html_e( 'Standard Fields', 'smart-programme-finder' ); ?></h3>
                                <div class="spf-field-buttons">
                                    <?php foreach ( self::FIELD_TYPES as $type_key => $type_info ) : ?>
                                    <button type="button" class="spf-add-field-btn" data-type="<?php echo esc_attr( $type_key ); ?>">
                                        <span class="dashicons <?php echo esc_attr( $type_info['icon'] ); ?>"></span>
                                        <?php echo esc_html( $type_info['label'] ); ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Field Options sub-tab -->
                            <div class="spf-panel-content" data-tab="field-options">
                                <div class="spf-field-options-empty">
                                    <p><?php esc_html_e( 'Click a field in the preview to edit its options.', 'smart-programme-finder' ); ?></p>
                                </div>
                                <div class="spf-field-options-form" style="display:none;">
                                    <h3 class="spf-editing-field-title"></h3>
                                    <div class="spf-option-group">
                                        <label><?php esc_html_e( 'Label', 'smart-programme-finder' ); ?></label>
                                        <input type="text" class="spf-fo-label" />
                                    </div>
                                    <div class="spf-option-group">
                                        <label><?php esc_html_e( 'Type', 'smart-programme-finder' ); ?></label>
                                        <select class="spf-fo-type">
                                            <?php foreach ( self::FIELD_TYPES as $type_key => $type_info ) : ?>
                                            <option value="<?php echo esc_attr( $type_key ); ?>"><?php echo esc_html( $type_info['label'] ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="spf-option-group">
                                        <label><?php esc_html_e( 'Size', 'smart-programme-finder' ); ?></label>
                                        <select class="spf-fo-size">
                                            <option value="small"><?php esc_html_e( 'Small', 'smart-programme-finder' ); ?></option>
                                            <option value="medium"><?php esc_html_e( 'Medium', 'smart-programme-finder' ); ?></option>
                                            <option value="large"><?php esc_html_e( 'Large', 'smart-programme-finder' ); ?></option>
                                        </select>
                                    </div>
                                    <div class="spf-option-group">
                                        <label><?php esc_html_e( 'Placeholder', 'smart-programme-finder' ); ?></label>
                                        <input type="text" class="spf-fo-placeholder" />
                                    </div>
                                    <div class="spf-option-group spf-option-group--checkbox">
                                        <label>
                                            <input type="checkbox" class="spf-fo-required" checked />
                                            <?php esc_html_e( 'Required', 'smart-programme-finder' ); ?>
                                        </label>
                                    </div>
                                    <div class="spf-option-group spf-fo-options-group" style="display:none;">
                                        <label><?php esc_html_e( 'Options (comma-separated)', 'smart-programme-finder' ); ?></label>
                                        <textarea class="spf-fo-options" rows="3"></textarea>
                                    </div>
                                    <button type="button" class="button spf-fo-apply"><?php esc_html_e( 'Apply Changes', 'smart-programme-finder' ); ?></button>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ SETTINGS PANEL ═══ -->
                        <div class="spf-panel" data-panel="settings">
                            <div class="spf-settings-nav">
                                <a href="#" class="spf-settings-nav-item spf-settings-nav-item--active" data-settings-tab="general">
                                    <?php esc_html_e( 'General', 'smart-programme-finder' ); ?>
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </a>
                                <a href="#" class="spf-settings-nav-item" data-settings-tab="confirmations">
                                    <?php esc_html_e( 'Confirmations', 'smart-programme-finder' ); ?>
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </a>
                                <a href="#" class="spf-settings-nav-item" data-settings-tab="notifications">
                                    <?php esc_html_e( 'Notifications', 'smart-programme-finder' ); ?>
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </a>
                            </div>

                            <!-- General -->
                            <div class="spf-settings-content spf-settings-content--active" data-settings-tab="general">
                                <h3><?php esc_html_e( 'General', 'smart-programme-finder' ); ?></h3>

                                <div class="spf-option-group">
                                    <label for="spf_form_name_edit"><?php esc_html_e( 'Form Name', 'smart-programme-finder' ); ?></label>
                                    <input type="text" id="spf_form_name_edit" name="spf_form_name" value="<?php echo esc_attr( $form['name'] ); ?>" class="spf-input-full" />
                                </div>

                                <div class="spf-option-group">
                                    <label for="spf_form_description"><?php esc_html_e( 'Form Description', 'smart-programme-finder' ); ?></label>
                                    <textarea id="spf_form_description" name="spf_form_description" rows="3" class="spf-input-full"><?php echo esc_textarea( $general['form_description'] ); ?></textarea>
                                </div>

                                <div class="spf-option-group">
                                    <label for="spf_submit_text"><?php esc_html_e( 'Submit Button Text', 'smart-programme-finder' ); ?></label>
                                    <input type="text" id="spf_submit_text" name="spf_submit_text" value="<?php echo esc_attr( $general['submit_text'] ); ?>" class="spf-input-full" />
                                </div>

                                <div class="spf-option-group">
                                    <label for="spf_submit_processing"><?php esc_html_e( 'Submit Button Processing Text', 'smart-programme-finder' ); ?></label>
                                    <input type="text" id="spf_submit_processing" name="spf_submit_processing" value="<?php echo esc_attr( $general['submit_processing'] ); ?>" class="spf-input-full" />
                                </div>

                                <div class="spf-option-group">
                                    <label for="spf_conf_btn_text"><?php esc_html_e( 'Confirmation Button Text', 'smart-programme-finder' ); ?></label>
                                    <input type="text" id="spf_conf_btn_text" name="spf_conf_btn_text" value="<?php echo esc_attr( $general['conf_btn_text'] ); ?>" class="spf-input-full" />
                                    <p class="description"><?php esc_html_e( 'Text for the &ldquo;Try Again&rdquo; button shown in the confirmation popup / inline message.', 'smart-programme-finder' ); ?></p>
                                </div>

                                <div class="spf-option-group spf-option-group--checkbox">
                                    <label>
                                        <input type="checkbox" name="spf_enable_ajax" value="1" <?php checked( $general['enable_ajax'] ); ?> />
                                        <?php esc_html_e( 'Enable AJAX form submission', 'smart-programme-finder' ); ?>
                                    </label>
                                </div>
                            </div>

                            <!-- Confirmations -->
                            <div class="spf-settings-content" data-settings-tab="confirmations">
                                <div class="spf-settings-header-row">
                                    <h3><?php esc_html_e( 'Confirmations', 'smart-programme-finder' ); ?></h3>
                                    <button type="button" class="button spf-add-confirmation-btn"><?php esc_html_e( 'Add New Confirmation', 'smart-programme-finder' ); ?></button>
                                </div>

                                <div id="spf-confirmations-list">
                                    <?php foreach ( $confirmations as $ci => $conf ) :
                                        $conf = wp_parse_args( $conf, self::DEFAULT_CONFIRMATION );
                                    ?>
                                    <div class="spf-confirmation-block" data-index="<?php echo esc_attr( $ci ); ?>">
                                        <div class="spf-conf-header">
                                            <span class="spf-conf-name"><?php echo esc_html( $conf['name'] ); ?></span>
                                            <span class="spf-conf-toggle dashicons dashicons-arrow-down"></span>
                                            <?php if ( $ci > 0 ) : ?>
                                            <button type="button" class="spf-conf-delete dashicons dashicons-trash" title="<?php esc_attr_e( 'Delete', 'smart-programme-finder' ); ?>"></button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="spf-conf-body">
                                            <input type="hidden" name="spf_confirmations[<?php echo esc_attr( $ci ); ?>][id]" value="<?php echo esc_attr( $conf['id'] ); ?>" />

                                            <div class="spf-option-group">
                                                <label><?php esc_html_e( 'Confirmation Name', 'smart-programme-finder' ); ?></label>
                                                <input type="text" name="spf_confirmations[<?php echo esc_attr( $ci ); ?>][name]" value="<?php echo esc_attr( $conf['name'] ); ?>" class="spf-input-full" />
                                            </div>

                                            <div class="spf-option-group">
                                                <label><?php esc_html_e( 'Confirmation Type', 'smart-programme-finder' ); ?></label>
                                                <select name="spf_confirmations[<?php echo esc_attr( $ci ); ?>][type]" class="spf-conf-type-select spf-input-full">
                                                    <option value="message" <?php selected( $conf['type'], 'message' ); ?>><?php esc_html_e( 'Message', 'smart-programme-finder' ); ?></option>
                                                    <option value="redirect" <?php selected( $conf['type'], 'redirect' ); ?>><?php esc_html_e( 'Redirect URL', 'smart-programme-finder' ); ?></option>
                                                </select>
                                            </div>

                                            <div class="spf-option-group spf-conf-message-group" <?php echo 'redirect' === $conf['type'] ? 'style="display:none;"' : ''; ?>>
                                                <label><?php esc_html_e( 'Confirmation Message', 'smart-programme-finder' ); ?></label>
                                                <textarea name="spf_confirmations[<?php echo esc_attr( $ci ); ?>][message]" rows="4" class="spf-input-full"><?php echo esc_textarea( $conf['message'] ); ?></textarea>
                                            </div>

                                            <div class="spf-option-group spf-conf-redirect-group" <?php echo 'message' === $conf['type'] ? 'style="display:none;"' : ''; ?>>
                                                <label><?php esc_html_e( 'Redirect URL', 'smart-programme-finder' ); ?></label>
                                                <input type="url" name="spf_confirmations[<?php echo esc_attr( $ci ); ?>][redirect_url]" value="<?php echo esc_attr( $conf['redirect_url'] ); ?>" class="spf-input-full" />
                                            </div>

                                            <div class="spf-option-group spf-option-group--checkbox">
                                                <label>
                                                    <input type="checkbox" name="spf_confirmations[<?php echo esc_attr( $ci ); ?>][conditional_logic]" value="1" class="spf-conf-logic-toggle" <?php checked( $conf['conditional_logic'] ); ?> />
                                                    <?php esc_html_e( 'Enable Conditional Logic', 'smart-programme-finder' ); ?>
                                                </label>
                                            </div>

                                            <div class="spf-conf-conditions" <?php echo empty( $conf['conditional_logic'] ) ? 'style="display:none;"' : ''; ?>>
                                                <p class="spf-conditions-label"><?php esc_html_e( 'Use this confirmation if:', 'smart-programme-finder' ); ?></p>
                                                <div class="spf-conditions-list">
                                                    <?php if ( ! empty( $conf['conditions'] ) ) :
                                                        foreach ( $conf['conditions'] as $cond_i => $cond ) : ?>
                                                    <div class="spf-condition-row">
                                                        <select name="spf_confirmations[<?php echo esc_attr( $ci ); ?>][conditions][<?php echo esc_attr( $cond_i ); ?>][field]" class="spf-cond-field">
                                                            <option value=""><?php esc_html_e( '— Field —', 'smart-programme-finder' ); ?></option>
                                                            <?php foreach ( $fields as $f ) : ?>
                                                            <option value="<?php echo esc_attr( $f['field_key'] ); ?>" <?php selected( $cond['field'] ?? '', $f['field_key'] ); ?>><?php echo esc_html( $f['label'] ); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <select name="spf_confirmations[<?php echo esc_attr( $ci ); ?>][conditions][<?php echo esc_attr( $cond_i ); ?>][operator]" class="spf-cond-op">
                                                            <option value="is" <?php selected( $cond['operator'] ?? 'is', 'is' ); ?>><?php esc_html_e( 'is', 'smart-programme-finder' ); ?></option>
                                                            <option value="is_not" <?php selected( $cond['operator'] ?? '', 'is_not' ); ?>><?php esc_html_e( 'is not', 'smart-programme-finder' ); ?></option>
                                                        </select>
                                                        <?php
                                                        // Find the selected field's choices for the value dropdown
                                                        $cond_field_key = $cond['field'] ?? '';
                                                        $cond_field_options = array();
                                                        if ( $cond_field_key ) {
                                                            foreach ( $fields as $cf ) {
                                                                if ( $cf['field_key'] === $cond_field_key && ! empty( $cf['options'] ) ) {
                                                                    $cond_field_options = is_array( $cf['options'] )
                                                                        ? $cf['options']
                                                                        : array_map( 'trim', explode( ',', $cf['options'] ) );
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                        ?>
                                                        <select name="spf_confirmations[<?php echo esc_attr( $ci ); ?>][conditions][<?php echo esc_attr( $cond_i ); ?>][value]" class="spf-cond-value">
                                                            <option value=""><?php esc_html_e( '— Select Choice —', 'smart-programme-finder' ); ?></option>
                                                            <?php foreach ( $cond_field_options as $opt ) : ?>
                                                            <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $cond['value'] ?? '', $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="button" class="spf-cond-add button-small" title="<?php esc_attr_e( 'And', 'smart-programme-finder' ); ?>"><?php esc_html_e( 'And', 'smart-programme-finder' ); ?></button>
                                                        <button type="button" class="spf-cond-remove" title="<?php esc_attr_e( 'Remove', 'smart-programme-finder' ); ?>"><span class="dashicons dashicons-trash"></span></button>
                                                    </div>
                                                    <?php endforeach;
                                                    endif; ?>
                                                </div>
                                                <button type="button" class="button spf-add-condition-btn"><?php esc_html_e( 'Add Condition', 'smart-programme-finder' ); ?></button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Notifications -->
                            <div class="spf-settings-content" data-settings-tab="notifications">
                                <div class="spf-settings-header-row">
                                    <h3><?php esc_html_e( 'Notifications', 'smart-programme-finder' ); ?></h3>
                                    <button type="button" class="button spf-add-notification-btn"><?php esc_html_e( 'Add New Notification', 'smart-programme-finder' ); ?></button>
                                </div>

                                <div id="spf-notifications-list">
                                    <?php foreach ( $notifications as $ni => $notif ) :
                                        $notif = wp_parse_args( $notif, self::DEFAULT_NOTIFICATION );
                                    ?>
                                    <div class="spf-notification-block" data-index="<?php echo esc_attr( $ni ); ?>">
                                        <div class="spf-notif-header">
                                            <span class="spf-notif-name"><?php echo esc_html( $notif['name'] ); ?></span>
                                            <span class="spf-notif-toggle dashicons dashicons-arrow-down"></span>
                                            <?php if ( $ni > 0 ) : ?>
                                            <button type="button" class="spf-notif-delete dashicons dashicons-trash" title="<?php esc_attr_e( 'Delete', 'smart-programme-finder' ); ?>"></button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="spf-notif-body">
                                            <input type="hidden" name="spf_notifications[<?php echo esc_attr( $ni ); ?>][id]" value="<?php echo esc_attr( $notif['id'] ); ?>" />

                                            <div class="spf-option-group spf-option-group--checkbox">
                                                <label>
                                                    <input type="checkbox" name="spf_notifications[<?php echo esc_attr( $ni ); ?>][enabled]" value="1" <?php checked( $notif['enabled'] ); ?> />
                                                    <?php esc_html_e( 'Enable this notification', 'smart-programme-finder' ); ?>
                                                </label>
                                            </div>

                                            <div class="spf-option-group">
                                                <label><?php esc_html_e( 'Notification Name', 'smart-programme-finder' ); ?></label>
                                                <input type="text" name="spf_notifications[<?php echo esc_attr( $ni ); ?>][name]" value="<?php echo esc_attr( $notif['name'] ); ?>" class="spf-input-full" />
                                            </div>

                                            <div class="spf-option-group">
                                                <label><?php esc_html_e( 'Send To Email Address', 'smart-programme-finder' ); ?></label>
                                                <input type="text" name="spf_notifications[<?php echo esc_attr( $ni ); ?>][email_to]" value="<?php echo esc_attr( $notif['email_to'] ); ?>" class="spf-input-full" />
                                                <p class="description"><?php esc_html_e( 'Use {admin_email} for the site admin email.', 'smart-programme-finder' ); ?></p>
                                            </div>

                                            <div class="spf-option-group">
                                                <label><?php esc_html_e( 'Email Subject', 'smart-programme-finder' ); ?></label>
                                                <input type="text" name="spf_notifications[<?php echo esc_attr( $ni ); ?>][email_subject]" value="<?php echo esc_attr( $notif['email_subject'] ); ?>" class="spf-input-full" />
                                            </div>

                                            <div class="spf-option-group">
                                                <label><?php esc_html_e( 'From Name', 'smart-programme-finder' ); ?></label>
                                                <input type="text" name="spf_notifications[<?php echo esc_attr( $ni ); ?>][email_from_name]" value="<?php echo esc_attr( $notif['email_from_name'] ); ?>" class="spf-input-full" />
                                            </div>

                                            <div class="spf-option-group">
                                                <label><?php esc_html_e( 'From Email', 'smart-programme-finder' ); ?></label>
                                                <input type="email" name="spf_notifications[<?php echo esc_attr( $ni ); ?>][email_from_email]" value="<?php echo esc_attr( $notif['email_from_email'] ); ?>" class="spf-input-full" />
                                            </div>

                                            <div class="spf-option-group">
                                                <label><?php esc_html_e( 'Reply-To', 'smart-programme-finder' ); ?></label>
                                                <input type="email" name="spf_notifications[<?php echo esc_attr( $ni ); ?>][email_reply_to]" value="<?php echo esc_attr( $notif['email_reply_to'] ); ?>" class="spf-input-full" />
                                            </div>

                                            <div class="spf-option-group">
                                                <label><?php esc_html_e( 'Email Message', 'smart-programme-finder' ); ?></label>
                                                <textarea name="spf_notifications[<?php echo esc_attr( $ni ); ?>][email_message]" rows="5" class="spf-input-full"><?php echo esc_textarea( $notif['email_message'] ); ?></textarea>
                                                <p class="description"><?php esc_html_e( 'Use {all_fields} to include all submitted field values.', 'smart-programme-finder' ); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ APPEARANCE PANEL ═══ -->
                        <div class="spf-panel" data-panel="appearance">
                            <h3 class="spf-panel-title"><?php esc_html_e( 'Appearance', 'smart-programme-finder' ); ?></h3>

                            <div class="spf-appearance-section">
                                <h4><?php esc_html_e( 'Layout', 'smart-programme-finder' ); ?></h4>

                                <div class="spf-option-group">
                                    <label><?php esc_html_e( 'Form Max-Width (px)', 'smart-programme-finder' ); ?></label>
                                    <input type="number" name="spf_form_width" value="<?php echo esc_attr( $settings['form_width'] ); ?>" min="200" max="1400" step="10" class="spf-input-full" />
                                </div>
                                <div class="spf-option-group">
                                    <label><?php esc_html_e( 'Columns', 'smart-programme-finder' ); ?></label>
                                    <select name="spf_columns" class="spf-input-full">
                                        <?php for ( $c = 1; $c <= 4; $c++ ) : ?>
                                        <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $settings['columns'], (string) $c ); ?>><?php echo esc_html( $c ); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="spf-option-group">
                                    <label><?php esc_html_e( 'Field Spacing (px)', 'smart-programme-finder' ); ?></label>
                                    <input type="number" name="spf_field_spacing" value="<?php echo esc_attr( $settings['field_spacing'] ); ?>" min="0" max="60" step="2" class="spf-input-full" />
                                </div>
                                <div class="spf-option-group">
                                    <label><?php esc_html_e( 'Form Padding (px)', 'smart-programme-finder' ); ?></label>
                                    <input type="number" name="spf_form_padding" value="<?php echo esc_attr( $settings['form_padding'] ); ?>" min="0" max="60" step="2" class="spf-input-full" />
                                </div>
                                <div class="spf-option-group">
                                    <label><?php esc_html_e( 'Form Border Radius (px)', 'smart-programme-finder' ); ?></label>
                                    <input type="number" name="spf_form_border_radius" value="<?php echo esc_attr( $settings['form_border_radius'] ); ?>" min="0" max="30" step="1" class="spf-input-full" />
                                </div>
                            </div>

                            <div class="spf-appearance-section">
                                <h4><?php esc_html_e( 'Button', 'smart-programme-finder' ); ?></h4>

                                <div class="spf-option-group">
                                    <label><?php esc_html_e( 'Button Border Radius (px)', 'smart-programme-finder' ); ?></label>
                                    <input type="number" name="spf_btn_radius" value="<?php echo esc_attr( $settings['btn_radius'] ); ?>" min="0" max="50" step="1" class="spf-input-full" />
                                </div>
                            </div>

                            <div class="spf-appearance-section">
                                <h4><?php esc_html_e( 'Colors', 'smart-programme-finder' ); ?></h4>

                                <?php
                                $color_fields = array(
                                    'primary_color' => __( 'Primary / Button BG', 'smart-programme-finder' ),
                                    'primary_hover' => __( 'Primary Hover', 'smart-programme-finder' ),
                                    'btn_text_color'=> __( 'Button Text', 'smart-programme-finder' ),
                                    'label_color'   => __( 'Label Color', 'smart-programme-finder' ),
                                    'input_bg'      => __( 'Input Background', 'smart-programme-finder' ),
                                    'input_text'    => __( 'Input Text', 'smart-programme-finder' ),
                                    'input_border'  => __( 'Input Border', 'smart-programme-finder' ),
                                    'form_bg'       => __( 'Form Background', 'smart-programme-finder' ),
                                );
                                foreach ( $color_fields as $key => $clabel ) : ?>
                                <div class="spf-option-group spf-option-group--color">
                                    <label><?php echo esc_html( $clabel ); ?></label>
                                    <input type="text" name="spf_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $settings[ $key ] ); ?>" class="spf-color-picker" />
                                </div>
                                <?php endforeach; ?>

                                <div class="spf-option-group">
                                    <label><?php esc_html_e( 'Input Border Radius (px)', 'smart-programme-finder' ); ?></label>
                                    <input type="number" name="spf_input_radius" value="<?php echo esc_attr( $settings['input_radius'] ); ?>" min="0" max="30" step="1" class="spf-input-full" />
                                </div>
                            </div>
                        </div>

                    </div><!-- /.spf-sidebar-panels -->
                </div><!-- /.spf-builder-sidebar -->

                <!-- ═══ PREVIEW AREA ═══ -->
                <div class="spf-builder-preview">
                    <div class="spf-preview-header">
                        <h2 class="spf-preview-title"><?php echo esc_html( $form['name'] ); ?></h2>
                    </div>

                    <div class="spf-preview-canvas" id="spf-preview-canvas">
                        <?php if ( ! empty( $fields ) ) : ?>
                            <?php foreach ( $fields as $fi => $field ) : ?>
                            <div class="spf-preview-field" data-index="<?php echo esc_attr( $fi ); ?>" data-field-key="<?php echo esc_attr( $field['field_key'] ); ?>">
                                <div class="spf-preview-field-inner">
                                    <span class="spf-pf-icon dashicons <?php echo esc_attr( self::FIELD_TYPES[ $field['type'] ]['icon'] ?? 'dashicons-editor-textcolor' ); ?>"></span>
                                    <span class="spf-pf-label"><?php echo esc_html( $field['label'] ); ?></span>
                                    <?php if ( ! empty( $field['required'] ) ) : ?>
                                    <span class="spf-pf-required">*</span>
                                    <?php endif; ?>
                                </div>
                                <div class="spf-pf-type"><?php echo esc_html( self::FIELD_TYPES[ $field['type'] ]['label'] ?? ucfirst( $field['type'] ) ); ?></div>
                                <div class="spf-preview-field-actions">
                                    <button type="button" class="spf-pf-delete" title="<?php esc_attr_e( 'Delete field', 'smart-programme-finder' ); ?>"><span class="dashicons dashicons-trash"></span></button>
                                </div>
                                <!-- Hidden inputs for form submission -->
                                <input type="hidden" name="spf_fields[<?php echo esc_attr( $fi ); ?>][id]" value="<?php echo esc_attr( $field['id'] ); ?>" />
                                <input type="hidden" name="spf_fields[<?php echo esc_attr( $fi ); ?>][field_key]" value="<?php echo esc_attr( $field['field_key'] ); ?>" />
                                <input type="hidden" name="spf_fields[<?php echo esc_attr( $fi ); ?>][label]" value="<?php echo esc_attr( $field['label'] ); ?>" />
                                <input type="hidden" name="spf_fields[<?php echo esc_attr( $fi ); ?>][type]" value="<?php echo esc_attr( $field['type'] ); ?>" />
                                <input type="hidden" name="spf_fields[<?php echo esc_attr( $fi ); ?>][size]" value="<?php echo esc_attr( $field['size'] ?? 'medium' ); ?>" />
                                <input type="hidden" name="spf_fields[<?php echo esc_attr( $fi ); ?>][placeholder]" value="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>" />
                                <input type="hidden" name="spf_fields[<?php echo esc_attr( $fi ); ?>][options]" value="<?php echo esc_attr( implode( ', ', $field['options'] ?? array() ) ); ?>" />
                                <input type="hidden" name="spf_fields[<?php echo esc_attr( $fi ); ?>][required]" value="<?php echo ! empty( $field['required'] ) ? '1' : '0'; ?>" />
                            </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="spf-preview-empty" id="spf-preview-empty">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <p><?php esc_html_e( 'Add fields from the left panel to get started.', 'smart-programme-finder' ); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="spf-preview-footer">
                        <div class="spf-preview-btn-mock">
                            <button type="button" class="spf-mock-submit" disabled><?php echo esc_html( $general['submit_text'] ); ?></button>
                        </div>
                        <div class="spf-preview-field-actions-bar">
                            <span class="dashicons dashicons-move"></span>
                            <span><?php esc_html_e( 'Drag fields to reorder', 'smart-programme-finder' ); ?></span>
                        </div>
                    </div>
                </div><!-- /.spf-builder-preview -->

            </form><!-- /#spf-editor-form -->
        </div><!-- /#spf-builder -->
        <?php
    }

    /* ── Rule Builder ──────────────────────── */
    public function render_rule_builder(): void {
        $current_form_id = $this->get_current_form_id();
        $fields          = $current_form_id > 0 ? $this->get_fields_for_form( $current_form_id ) : array();
        $rules           = $current_form_id > 0 ? $this->get_rules_for_form( $current_form_id ) : array();
        ?>
        <div class="wrap spf-admin-wrap">
            <h1><?php esc_html_e( 'Rule Builder', 'smart-programme-finder' ); ?></h1>

            <?php $this->render_admin_notices(); ?>
            <?php $this->render_form_selector( 'spf-rule-builder', $current_form_id ); ?>

            <?php if ( 0 === $current_form_id ) { return; } ?>

            <?php if ( empty( $fields ) ) : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e( 'You need to create at least one field in the Form Editor before adding rules.', 'smart-programme-finder' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-form-edit&form_id=' . $current_form_id ) ); ?>"><?php esc_html_e( 'Go to Form Editor →', 'smart-programme-finder' ); ?></a>
                    </p>
                </div>
            <?php else : ?>
            <div class="spf-card spf-card--form">
                <h2><?php esc_html_e( 'Add New Rule', 'smart-programme-finder' ); ?></h2>
                <form method="post">
                    <?php wp_nonce_field( 'spf_save_rule_action', 'spf_rule_nonce' ); ?>
                    <input type="hidden" name="spf_form_id" value="<?php echo esc_attr( $current_form_id ); ?>">
                    <table class="form-table">
                        <tr>
                            <th><label for="spf_rule_field"><?php esc_html_e( 'IF Field', 'smart-programme-finder' ); ?></label></th>
                            <td>
                                <select id="spf_rule_field" name="spf_rule_field" required>
                                    <option value=""><?php esc_html_e( '— Select Field —', 'smart-programme-finder' ); ?></option>
                                    <?php foreach ( $fields as $field ) : ?>
                                    <option value="<?php echo esc_attr( $field['field_key'] ); ?>"><?php echo esc_html( $field['label'] ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="spf_rule_value"><?php esc_html_e( 'Equals Value', 'smart-programme-finder' ); ?></label></th>
                            <td>
                                <input type="text" id="spf_rule_value" name="spf_rule_value" class="regular-text" required placeholder="<?php esc_attr_e( 'e.g. Business', 'smart-programme-finder' ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="spf_rule_result"><?php esc_html_e( 'THEN Show Result', 'smart-programme-finder' ); ?></label></th>
                            <td>
                                <textarea id="spf_rule_result" name="spf_rule_result" class="large-text" rows="4" required></textarea>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" name="spf_save_rule" class="button button-primary"><?php esc_html_e( 'Add Rule', 'smart-programme-finder' ); ?></button>
                    </p>
                </form>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $rules ) ) : ?>
            <div class="spf-card">
                <h2><?php esc_html_e( 'Current Rules', 'smart-programme-finder' ); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ID', 'smart-programme-finder' ); ?></th>
                            <th><?php esc_html_e( 'IF Field', 'smart-programme-finder' ); ?></th>
                            <th><?php esc_html_e( 'Equals', 'smart-programme-finder' ); ?></th>
                            <th><?php esc_html_e( 'THEN Result', 'smart-programme-finder' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'smart-programme-finder' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rules as $rule ) : ?>
                        <tr>
                            <td><?php echo esc_html( $rule['id'] ); ?></td>
                            <td><code><?php echo esc_html( $rule['field_key'] ); ?></code></td>
                            <td><?php echo esc_html( $rule['value'] ); ?></td>
                            <td><?php echo esc_html( wp_trim_words( $rule['result'], 15, '…' ) ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'spf-rule-builder', 'form_id' => $current_form_id, 'spf_delete_rule' => $rule['id'] ), admin_url( 'admin.php' ) ), 'spf_delete_rule_' . $rule['id'] ) ); ?>" class="spf-delete-link" onclick="return confirm('<?php esc_attr_e( 'Delete this rule?', 'smart-programme-finder' ); ?>');"><?php esc_html_e( 'Delete', 'smart-programme-finder' ); ?></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ── Global Settings ───────────────────── */
    public function render_settings(): void {
        $settings = get_option( 'spf_settings', array() );
        $fallback = $settings['fallback_message'] ?? '';
        ?>
        <div class="wrap spf-admin-wrap">
            <h1><?php esc_html_e( 'Settings', 'smart-programme-finder' ); ?></h1>

            <?php $this->render_admin_notices(); ?>

            <div class="spf-card spf-card--form">
                <h2><?php esc_html_e( 'Default Fallback Message', 'smart-programme-finder' ); ?></h2>
                <p class="description"><?php esc_html_e( 'This message is shown when no rule matches the visitor\'s answers.', 'smart-programme-finder' ); ?></p>
                <form method="post">
                    <?php wp_nonce_field( 'spf_save_settings_action', 'spf_settings_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="spf_fallback_message"><?php esc_html_e( 'Fallback Message', 'smart-programme-finder' ); ?></label></th>
                            <td><textarea id="spf_fallback_message" name="spf_fallback_message" class="large-text" rows="4"><?php echo esc_textarea( $fallback ); ?></textarea></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" name="spf_save_settings" class="button button-primary"><?php esc_html_e( 'Save Settings', 'smart-programme-finder' ); ?></button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /* ── Admin notices ─────────────────────── */
    private function render_admin_notices(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';

        $notices = array(
            'form_created'     => __( 'Form created successfully.', 'smart-programme-finder' ),
            'form_deleted'     => __( 'Form deleted.', 'smart-programme-finder' ),
            'form_saved'       => __( 'Form saved successfully.', 'smart-programme-finder' ),
            'rule_saved'       => __( 'Rule added successfully.', 'smart-programme-finder' ),
            'rule_deleted'     => __( 'Rule deleted.', 'smart-programme-finder' ),
            'settings_saved'   => __( 'Settings saved.', 'smart-programme-finder' ),
        );

        if ( isset( $notices[ $message ] ) ) {
            printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $notices[ $message ] ) );
        }

        settings_errors( 'spf_messages' );
    }
}
