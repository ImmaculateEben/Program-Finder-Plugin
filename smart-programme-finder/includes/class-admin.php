<?php
/**
 * Admin screens - Dashboard, Forms list, WPForms-style Form Editor, & Global Settings.
 *
 * @package SmartProgrammeFinder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPF_Admin {

    /* 
     * Constants
     *  */

    private const FIELD_TYPES = array(
        'text'     => 'Single Line Text',
        'textarea' => 'Paragraph Text',
        'select'   => 'Dropdown',
        'radio'    => 'Multiple Choice',
        'checkbox' => 'Checkboxes',
        'number'   => 'Numbers',
        'email'    => 'Email',
    );

    private const FIELD_ICONS = array(
        'text'     => 'dashicons-editor-textcolor',
        'textarea' => 'dashicons-editor-paragraph',
        'select'   => 'dashicons-arrow-down-alt2',
        'radio'    => 'dashicons-marker',
        'checkbox' => 'dashicons-yes-alt',
        'number'   => 'dashicons-editor-ol',
        'email'    => 'dashicons-email',
    );

    private const OPTION_TYPES = array( 'select', 'radio', 'checkbox' );

    public const DEFAULT_APPEARANCE = array(
        'button_text'        => 'Find My Programme',
        'button_position'    => 'full',
        'columns'            => '1',
        'form_width'         => '',
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
        'description'            => '',
        'button_processing_text' => 'Finding your best match...',
        'conf_btn_text'          => 'Try Again',
        'form_css_class'         => '',
        'button_css_class'       => '',
    );

    /* 
     * Bootstrap
     *  */

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_form_actions' ) );

        // AJAX endpoints for builder.
        add_action( 'wp_ajax_spf_ajax_add_field', array( $this, 'ajax_add_field' ) );
        add_action( 'wp_ajax_spf_ajax_delete_field', array( $this, 'ajax_delete_field' ) );
        add_action( 'wp_ajax_spf_ajax_update_field', array( $this, 'ajax_update_field' ) );
        add_action( 'wp_ajax_spf_ajax_reorder_fields', array( $this, 'ajax_reorder_fields' ) );
        add_action( 'wp_ajax_spf_ajax_duplicate_field', array( $this, 'ajax_duplicate_field' ) );
        add_action( 'wp_ajax_spf_ajax_save_form', array( $this, 'ajax_save_form' ) );
    }

    /* 
     * Menu registration
     *  */

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

        add_submenu_page(
            'spf-dashboard',
            __( 'Entries', 'smart-programme-finder' ),
            __( 'Entries', 'smart-programme-finder' ),
            'manage_options',
            'spf-entries',
            array( $this, 'render_entries' )
        );

        add_submenu_page(
            'spf-dashboard',
            __( 'Settings', 'smart-programme-finder' ),
            __( 'Settings', 'smart-programme-finder' ),
            'manage_options',
            'spf-settings',
            array( $this, 'render_settings' )
        );

        // Hidden form editor - accessed via Edit link on forms list.
        add_submenu_page(
            '',
            __( 'Edit Form', 'smart-programme-finder' ),
            '',
            'manage_options',
            'spf-form-edit',
            array( $this, 'render_form_edit' )
        );
    }

    /* 
     * Admin assets
     *  */

    public function enqueue_admin_assets( string $hook ): void {
        $plugin_pages = array(
            'toplevel_page_spf-dashboard',
            'programme-finder_page_spf-forms',
            'programme-finder_page_spf-entries',
            'programme-finder_page_spf-settings',
            'admin_page_spf-form-edit',
        );

        // Fallback: also match by page query param in case hook suffix differs.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_page  = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $plugin_slugs  = array( 'spf-dashboard', 'spf-forms', 'spf-entries', 'spf-settings', 'spf-form-edit' );
        $is_our_page   = in_array( $hook, $plugin_pages, true ) || in_array( $current_page, $plugin_slugs, true );

        if ( ! $is_our_page ) {
            return;
        }

        $is_form_edit = ( 'admin_page_spf-form-edit' === $hook || 'spf-form-edit' === $current_page );

        wp_enqueue_style(
            'spf-admin',
            SPF_PLUGIN_URL . 'assets/css/admin.css',
            array( 'dashicons' ),
            SPF_VERSION
        );

        wp_enqueue_script(
            'spf-admin',
            SPF_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            SPF_VERSION,
            true
        );

        // Color picker + jQuery UI on form edit page.
        if ( $is_form_edit ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
            wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_script( 'jquery-ui-draggable' );
            wp_enqueue_script( 'jquery-ui-droppable' );

            // Pass data to JS for AJAX-based builder.
            wp_localize_script( 'spf-admin', 'spfBuilder', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'spf_builder_ajax' ),
            ) );
        }
    }

    /* 
     * POST / GET dispatcher
     *  */

    public function handle_form_actions(): void {

        /* Create form ------------------------ */
        if ( isset( $_POST['spf_create_form'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_create_form_action', 'spf_form_nonce' );
            $this->create_form();
        }

        /* Delete form ------------------------ */
        if ( isset( $_GET['spf_delete_form'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_delete_form_' . intval( $_GET['spf_delete_form'] ) );
            $this->delete_form( intval( $_GET['spf_delete_form'] ) );
        }

        /* Add field (builder) ---------------- */
        if ( isset( $_POST['spf_add_field_builder'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_add_field_builder_action', 'spf_builder_field_nonce' );
            $this->add_field_from_builder();
        }

        /* Update field ----------------------- */
        if ( isset( $_POST['spf_update_field'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_update_field_action', 'spf_update_field_nonce' );
            $this->update_field();
        }

        /* Delete field ----------------------- */
        if ( isset( $_GET['spf_delete_field'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_delete_field_' . intval( $_GET['spf_delete_field'] ) );
            $this->delete_field_from_builder( intval( $_GET['spf_delete_field'] ) );
        }

        /* Save form settings (general + appearance) */
        if ( isset( $_POST['spf_save_form_edit'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_save_form_edit_action', 'spf_edit_nonce' );
            $this->save_form_edit();
        }

        /* Save confirmation ------------------ */
        if ( isset( $_POST['spf_save_confirmation'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_save_confirmation_action', 'spf_conf_nonce' );
            $this->save_confirmation();
        }

        /* Delete confirmation ---------------- */
        if ( isset( $_GET['spf_delete_confirmation'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_delete_confirmation_' . intval( $_GET['spf_delete_confirmation'] ) );
            $this->delete_confirmation_handler( intval( $_GET['spf_delete_confirmation'] ) );
        }

        /* Global settings -------------------- */
        if ( isset( $_POST['spf_save_settings'] ) ) {
            $this->require_admin_cap();
            check_admin_referer( 'spf_save_settings_action', 'spf_settings_nonce' );
            $this->save_settings();
        }

        /* Delete entry ----------------------- */
        if ( isset( $_GET['spf_delete_entry'] ) ) {
            $this->require_admin_cap();
            $eid = intval( $_GET['spf_delete_entry'] );
            check_admin_referer( 'spf_delete_entry_' . $eid );
            $entries = get_option( 'spf_entries', array() );
            $entries = array_values( array_filter( $entries, function ( $e ) use ( $eid ) {
                return (int) ( $e['id'] ?? 0 ) !== $eid;
            } ) );
            update_option( 'spf_entries', $entries );
            add_settings_error( 'spf_messages', 'spf_entry_deleted', __( 'Entry deleted.', 'smart-programme-finder' ), 'updated' );
            wp_safe_redirect( admin_url( 'admin.php?page=spf-entries' ) );
            exit;
        }

        /* Export entries to CSV --------------- */
        if ( isset( $_GET['spf_export_csv'] ) ) {
            $this->require_admin_cap();
            $export_form_id = absint( $_GET['spf_export_csv'] );
            check_admin_referer( 'spf_export_csv_' . $export_form_id );
            $this->export_entries_csv( $export_form_id );
            exit;
        }
    }

    private function require_admin_cap(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'smart-programme-finder' ) );
        }
    }

    /**
     * Export form entries to CSV and stream as download.
     */
    private function export_entries_csv( int $form_id ): void {
        $entries = $this->get_entries_for_form( $form_id );
        $fields  = $this->get_fields_for_form( $form_id );
        $form    = $this->get_form( $form_id );

        $form_name = $form ? sanitize_file_name( $form['name'] ) : 'form-' . $form_id;
        $filename  = 'spf-entries-' . $form_name . '-' . gmdate( 'Y-m-d' ) . '.csv';

        // Build ordered field keys and labels.
        $field_keys   = array();
        $field_labels = array();
        foreach ( $fields as $f ) {
            $field_keys[]   = $f['field_key'];
            $field_labels[] = $f['label'];
        }

        // Collect any extra field keys from entries not in current fields (deleted fields).
        foreach ( $entries as $entry ) {
            if ( ! empty( $entry['fields'] ) && is_array( $entry['fields'] ) ) {
                foreach ( array_keys( $entry['fields'] ) as $key ) {
                    if ( ! in_array( $key, $field_keys, true ) ) {
                        $field_keys[]   = $key;
                        $field_labels[] = $key;
                    }
                }
            }
        }

        // Headers.
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
        header( 'X-Content-Type-Options: nosniff' );

        $output = fopen( 'php://output', 'w' );

        // BOM for Excel UTF-8 compatibility.
        fwrite( $output, "\xEF\xBB\xBF" );

        // Header row.
        $header = array_merge( array( 'Entry ID', 'Date' ), $field_labels, array( 'Result', 'Matched', 'IP Address' ) );
        fputcsv( $output, $header );

        // Data rows.
        foreach ( $entries as $entry ) {
            $row = array(
                $entry['id'] ?? '',
                $entry['created_at'] ?? '',
            );

            foreach ( $field_keys as $key ) {
                $val = $entry['fields'][ $key ] ?? '';
                if ( is_array( $val ) ) {
                    $val = implode( ', ', $val );
                }
                $row[] = $this->sanitize_csv_cell( (string) $val );
            }

            $row[] = $this->sanitize_csv_cell( wp_strip_all_tags( $entry['result'] ?? '' ) );
            $row[] = ! empty( $entry['matched'] ) ? 'Yes' : 'No';
            $row[] = $this->sanitize_csv_cell( $entry['ip'] ?? '' );

            fputcsv( $output, $row );
        }

        fclose( $output );
    }

    /* 
     * FORM persistence
     *  */

    private function create_form(): void {
        $name = isset( $_POST['spf_form_name'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_form_name'] ) ) : '';

        if ( '' === $name ) {
            add_settings_error( 'spf_messages', 'spf_error', __( 'Form name is required.', 'smart-programme-finder' ), 'error' );
            return;
        }

        $forms   = get_option( 'spf_forms', array() );
        $form_id = count( $forms ) > 0 ? max( array_column( $forms, 'id' ) ) + 1 : 1;

        $forms[] = array(
            'id'         => $form_id,
            'name'       => $name,
            'created_at' => current_time( 'mysql' ),
            'settings'   => self::DEFAULT_APPEARANCE,
            'general'    => self::DEFAULT_GENERAL,
        );

        update_option( 'spf_forms', $forms );

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'spf-form-edit',
            'form_id' => $form_id,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private function delete_form( int $form_id ): void {
        $forms = get_option( 'spf_forms', array() );
        $forms = array_values( array_filter( $forms, function ( $f ) use ( $form_id ) {
            return (int) $f['id'] !== $form_id;
        } ) );
        update_option( 'spf_forms', $forms );

        // Clean up associated data.
        $fields = get_option( 'spf_fields', array() );
        $fields = array_values( array_filter( $fields, function ( $f ) use ( $form_id ) {
            return (int) ( $f['form_id'] ?? 0 ) !== $form_id;
        } ) );
        update_option( 'spf_fields', $fields );

        $confs = get_option( 'spf_confirmations', array() );
        $confs = array_values( array_filter( $confs, function ( $c ) use ( $form_id ) {
            return (int) ( $c['form_id'] ?? 0 ) !== $form_id;
        } ) );
        update_option( 'spf_confirmations', $confs );

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'spf-forms',
            'message' => 'form_deleted',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /* 
     * FIELD persistence (builder)
     *  */

    private function add_field_from_builder(): void {
        $form_id = isset( $_POST['spf_form_id'] ) ? absint( $_POST['spf_form_id'] ) : 0;
        $type    = isset( $_POST['spf_field_type'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_field_type'] ) ) : 'text';

        if ( 0 === $form_id || ! array_key_exists( $type, self::FIELD_TYPES ) ) {
            return;
        }

        $fields   = get_option( 'spf_fields', array() );
        $field_id = count( $fields ) > 0 ? max( array_column( $fields, 'id' ) ) + 1 : 1;
        $label    = self::FIELD_TYPES[ $type ] . ' ' . $field_id;
        $field_key = sanitize_title( $label ) . '_' . $field_id;

        $default_options = array();
        if ( in_array( $type, self::OPTION_TYPES, true ) ) {
            $default_options = array( 'Option 1', 'Option 2', 'Option 3' );
        }

        $fields[] = array(
            'id'               => $field_id,
            'form_id'          => $form_id,
            'field_key'        => $field_key,
            'label'            => $label,
            'type'             => $type,
            'size'             => 'medium',
            'placeholder'      => '',
            'options'          => $default_options,
            'required'         => true,
            'description'      => '',
            'css_class'        => '',
            'hide_label'       => false,
            'conditional_logic' => false,
            'conditional_type'  => 'show',
            'conditionals'      => array(),
        );

        update_option( 'spf_fields', $fields );

        wp_safe_redirect( add_query_arg( array(
            'page'     => 'spf-form-edit',
            'form_id'  => $form_id,
            'panel'    => 'fields',
            'tab'      => 'field-options',
            'field_id' => $field_id,
            'message'  => 'field_added',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private function update_field(): void {
        $field_id    = isset( $_POST['spf_field_id'] ) ? absint( $_POST['spf_field_id'] ) : 0;
        $form_id     = isset( $_POST['spf_form_id'] ) ? absint( $_POST['spf_form_id'] ) : 0;
        $label       = isset( $_POST['spf_field_label'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_field_label'] ) ) : '';
        $type        = isset( $_POST['spf_field_type'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_field_type'] ) ) : 'text';
        $size        = isset( $_POST['spf_field_size'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_field_size'] ) ) : 'medium';
        $placeholder = isset( $_POST['spf_field_placeholder'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_field_placeholder'] ) ) : '';
        $required    = ! empty( $_POST['spf_field_required'] );
        $options_raw = isset( $_POST['spf_field_options'] ) ? sanitize_textarea_field( wp_unslash( $_POST['spf_field_options'] ) ) : '';
        $description = isset( $_POST['spf_field_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['spf_field_description'] ) ) : '';
        $css_class   = isset( $_POST['spf_field_css_class'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_field_css_class'] ) ) : '';
        $hide_label  = ! empty( $_POST['spf_field_hide_label'] );

        if ( 0 === $field_id || '' === $label ) {
            return;
        }

        if ( ! array_key_exists( $type, self::FIELD_TYPES ) ) {
            $type = 'text';
        }
        if ( ! in_array( $size, array( 'small', 'medium', 'large' ), true ) ) {
            $size = 'medium';
        }

        $parsed_options = array();
        if ( in_array( $type, self::OPTION_TYPES, true ) ) {
            $parsed_options = array_values( array_filter( array_map( 'trim', explode( ',', $options_raw ) ) ) );
        }

        $fields = get_option( 'spf_fields', array() );
        foreach ( $fields as &$f ) {
            if ( (int) $f['id'] === $field_id ) {
                $f['label']             = $label;
                $f['type']              = $type;
                $f['size']              = $size;
                $f['placeholder']       = $placeholder;
                $f['required']          = $required;
                $f['options']           = $parsed_options;
                $f['description']       = $description;
                $f['css_class']         = $css_class;
                $f['hide_label']        = $hide_label;
                break;
            }
        }
        unset( $f );
        update_option( 'spf_fields', $fields );

        wp_safe_redirect( add_query_arg( array(
            'page'     => 'spf-form-edit',
            'form_id'  => $form_id,
            'panel'    => 'fields',
            'tab'      => 'field-options',
            'field_id' => $field_id,
            'message'  => 'field_updated',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private function delete_field_from_builder( int $field_id ): void {
        $fields  = get_option( 'spf_fields', array() );
        $form_id = 0;

        foreach ( $fields as $f ) {
            if ( (int) $f['id'] === $field_id ) {
                $form_id = (int) ( $f['form_id'] ?? 0 );
                break;
            }
        }

        $fields = array_values( array_filter( $fields, function ( $field ) use ( $field_id ) {
            return (int) $field['id'] !== $field_id;
        } ) );
        update_option( 'spf_fields', $fields );

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'spf-form-edit',
            'form_id' => $form_id,
            'panel'   => 'fields',
            'message' => 'field_deleted',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /* 
     * CONFIRMATION persistence
     *  */

    private function save_confirmation(): void {
        $form_id    = isset( $_POST['spf_form_id'] ) ? absint( $_POST['spf_form_id'] ) : 0;
        $conf_id    = isset( $_POST['spf_conf_id'] ) ? absint( $_POST['spf_conf_id'] ) : 0;
        $name       = isset( $_POST['spf_conf_name'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_conf_name'] ) ) : '';
        $message    = isset( $_POST['spf_conf_message'] ) ? wp_kses_post( wp_unslash( $_POST['spf_conf_message'] ) ) : '';
        $cond_logic = ! empty( $_POST['spf_conf_conditional'] );
        $logic_type = isset( $_POST['spf_conf_logic_type'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_conf_logic_type'] ) ) : 'use';
        $conf_type  = isset( $_POST['spf_conf_type'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_conf_type'] ) ) : 'popup';
        $conf_type  = in_array( $conf_type, array( 'popup', 'message' ), true ) ? $conf_type : 'popup';

        if ( 0 === $form_id || '' === $name || '' === $message ) {
            add_settings_error( 'spf_messages', 'spf_error', __( 'Name and message are required.', 'smart-programme-finder' ), 'error' );
            return;
        }

        $conditions = array();
        if ( $cond_logic && isset( $_POST['spf_conf_conditions'] ) && is_array( $_POST['spf_conf_conditions'] ) ) {
            foreach ( $_POST['spf_conf_conditions'] as $cond ) {
                $fk = isset( $cond['field_key'] ) ? sanitize_text_field( wp_unslash( $cond['field_key'] ) ) : '';
                $op = isset( $cond['operator'] ) ? sanitize_text_field( wp_unslash( $cond['operator'] ) ) : 'is';
                $vl = isset( $cond['value'] ) ? sanitize_text_field( wp_unslash( $cond['value'] ) ) : '';
                if ( '' !== $fk && '' !== $vl ) {
                    $conditions[] = array(
                        'field_key' => $fk,
                        'operator'  => in_array( $op, array( 'is', 'is_not' ), true ) ? $op : 'is',
                        'value'     => $vl,
                    );
                }
            }
        }

        $confs = get_option( 'spf_confirmations', array() );

        if ( $conf_id > 0 ) {
            // Update existing.
            foreach ( $confs as &$c ) {
                if ( (int) $c['id'] === $conf_id ) {
                    $c['name']              = $name;
                    $c['message']           = $message;
                    $c['confirmation_type'] = $conf_type;
                    $c['conditional_logic'] = $cond_logic;
                    $c['logic_type']        = $logic_type;
                    $c['conditions']        = $conditions;
                    break;
                }
            }
            unset( $c );
        } else {
            // Create new.
            $new_id = count( $confs ) > 0 ? max( array_column( $confs, 'id' ) ) + 1 : 1;
            $confs[] = array(
                'id'                => $new_id,
                'form_id'           => $form_id,
                'name'              => $name,
                'message'           => $message,
                'confirmation_type' => $conf_type,
                'conditional_logic' => $cond_logic,
                'logic_type'        => $logic_type,
                'conditions'        => $conditions,
            );
            $conf_id = $new_id;
        }

        update_option( 'spf_confirmations', $confs );

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'spf-form-edit',
            'form_id' => $form_id,
            'panel'   => 'settings',
            'stab'    => 'confirmations',
            'message' => 'confirmation_saved',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private function delete_confirmation_handler( int $conf_id ): void {
        $confs   = get_option( 'spf_confirmations', array() );
        $form_id = 0;

        foreach ( $confs as $c ) {
            if ( (int) $c['id'] === $conf_id ) {
                $form_id = (int) ( $c['form_id'] ?? 0 );
                break;
            }
        }

        $confs = array_values( array_filter( $confs, function ( $c ) use ( $conf_id ) {
            return (int) $c['id'] !== $conf_id;
        } ) );
        update_option( 'spf_confirmations', $confs );

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'spf-form-edit',
            'form_id' => $form_id,
            'panel'   => 'settings',
            'stab'    => 'confirmations',
            'message' => 'confirmation_deleted',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /* 
     * FORM SETTINGS persistence (general + appearance)
     *  */

    private function save_form_edit(): void {
        $form_id = isset( $_POST['spf_form_id'] ) ? absint( $_POST['spf_form_id'] ) : 0;
        if ( 0 === $form_id ) {
            return;
        }

        $forms = get_option( 'spf_forms', array() );

        foreach ( $forms as &$form ) {
            if ( (int) $form['id'] !== $form_id ) {
                continue;
            }

            // General settings.
            $form['name'] = isset( $_POST['spf_form_name'] )
                ? sanitize_text_field( wp_unslash( $_POST['spf_form_name'] ) )
                : $form['name'];

            $general = array();
            foreach ( array_keys( self::DEFAULT_GENERAL ) as $gk ) {
                $post_key      = 'spf_general_' . $gk;
                $general[ $gk ] = isset( $_POST[ $post_key ] )
                    ? sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) )
                    : ( $form['general'][ $gk ] ?? self::DEFAULT_GENERAL[ $gk ] );
            }
            $form['general'] = $general;

            // Appearance settings.
            $settings = array();
            foreach ( array_keys( self::DEFAULT_APPEARANCE ) as $key ) {
                $post_key        = 'spf_' . $key;
                $settings[ $key ] = isset( $_POST[ $post_key ] )
                    ? sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) )
                    : ( $form['settings'][ $key ] ?? self::DEFAULT_APPEARANCE[ $key ] );
            }
            $form['settings'] = $settings;
            break;
        }
        unset( $form );

        update_option( 'spf_forms', $forms );

        // Save fields submitted from the builder (deferred field edits).
        if ( ! empty( $_POST['spf_fields'] ) && is_array( $_POST['spf_fields'] ) ) {
            $all_fields = get_option( 'spf_fields', array() );

            // Keep fields belonging to other forms.
            $other_fields = array_values( array_filter( $all_fields, function ( $f ) use ( $form_id ) {
                return (int) ( $f['form_id'] ?? 0 ) !== $form_id;
            } ) );

            $new_fields = array();
            foreach ( $_POST['spf_fields'] as $fd ) {
                if ( ! is_array( $fd ) ) {
                    continue;
                }

                $type = sanitize_text_field( wp_unslash( $fd['type'] ?? 'text' ) );
                if ( ! array_key_exists( $type, self::FIELD_TYPES ) ) {
                    $type = 'text';
                }

                $size = sanitize_text_field( wp_unslash( $fd['size'] ?? 'medium' ) );
                if ( ! in_array( $size, array( 'small', 'medium', 'large' ), true ) ) {
                    $size = 'medium';
                }

                $field_id  = isset( $fd['id'] ) ? absint( $fd['id'] ) : 0;
                $label     = sanitize_text_field( wp_unslash( $fd['label'] ?? '' ) );
                $field_key = ! empty( $fd['field_key'] )
                    ? sanitize_title( wp_unslash( $fd['field_key'] ) )
                    : sanitize_title( $label ) . '_' . $field_id;

                $options_raw = sanitize_textarea_field( wp_unslash( $fd['options'] ?? '' ) );
                $options     = in_array( $type, self::OPTION_TYPES, true )
                    ? array_values( array_filter( array_map( 'trim', explode( ',', $options_raw ) ) ) )
                    : array();

                // Decode JSON conditionals array.
                $conditionals     = array();
                $conditionals_raw = wp_unslash( $fd['conditionals'] ?? '[]' );
                $decoded          = json_decode( $conditionals_raw, true );
                if ( is_array( $decoded ) ) {
                    foreach ( $decoded as $cond ) {
                        if ( ! is_array( $cond ) ) {
                            continue;
                        }
                        $conditionals[] = array(
                            'field_key' => sanitize_text_field( $cond['field_key'] ?? '' ),
                            'operator'  => sanitize_text_field( $cond['operator'] ?? 'is' ),
                            'value'     => sanitize_text_field( $cond['value'] ?? '' ),
                        );
                    }
                }

                $new_fields[] = array(
                    'id'                => $field_id,
                    'form_id'           => $form_id,
                    'field_key'         => $field_key,
                    'label'             => $label,
                    'type'              => $type,
                    'size'              => $size,
                    'placeholder'       => sanitize_text_field( wp_unslash( $fd['placeholder'] ?? '' ) ),
                    'options'           => $options,
                    'required'          => ! empty( $fd['required'] ) && '0' !== (string) $fd['required'],
                    'description'       => sanitize_textarea_field( wp_unslash( $fd['description'] ?? '' ) ),
                    'css_class'         => sanitize_text_field( wp_unslash( $fd['css_class'] ?? '' ) ),
                    'hide_label'        => ! empty( $fd['hide_label'] ) && '0' !== (string) $fd['hide_label'],
                    'default_value'     => sanitize_text_field( wp_unslash( $fd['default_value'] ?? '' ) ),
                    'input_columns'     => sanitize_text_field( wp_unslash( $fd['input_columns'] ?? '' ) ),
                    'conditional_logic' => ! empty( $fd['conditional_logic'] ) && '0' !== (string) $fd['conditional_logic'],
                    'conditional_type'  => sanitize_text_field( wp_unslash( $fd['conditional_type'] ?? 'show' ) ),
                    'conditionals'      => $conditionals,
                );
            }

            update_option( 'spf_fields', array_merge( $other_fields, $new_fields ) );
        }

        // Determine which sub-tab was active.
        $stab = isset( $_POST['spf_active_stab'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_active_stab'] ) ) : 'general';

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'spf-form-edit',
            'form_id' => $form_id,
            'panel'   => 'settings',
            'stab'    => $stab,
            'message' => 'settings_saved',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /* 
     * GLOBAL SETTINGS persistence
     *  */

    private function save_settings(): void {
        $fallback = isset( $_POST['spf_fallback_message'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['spf_fallback_message'] ) )
            : '';

        update_option( 'spf_settings', array(
            'fallback_message' => $fallback,
        ) );

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'spf-settings',
            'message' => 'settings_saved',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /* 
     * Migration: old rules ' confirmations
     *  */

    private function maybe_migrate_rules( int $form_id ): void {
        $confs = get_option( 'spf_confirmations', array() );
        $existing = array_filter( $confs, function ( $c ) use ( $form_id ) {
            return (int) ( $c['form_id'] ?? 0 ) === $form_id;
        } );

        if ( ! empty( $existing ) ) {
            return; // Already has confirmations.
        }

        $rules      = get_option( 'spf_rules', array() );
        $form_rules = array_filter( $rules, function ( $r ) use ( $form_id ) {
            return (int) ( $r['form_id'] ?? 0 ) === $form_id;
        } );

        if ( empty( $form_rules ) ) {
            return;
        }

        foreach ( $form_rules as $rule ) {
            $new_id = count( $confs ) > 0 ? max( array_column( $confs, 'id' ) ) + 1 : 1;
            $confs[] = array(
                'id'                => $new_id,
                'form_id'           => $form_id,
                'name'              => 'Rule #' . ( $rule['id'] ?? $new_id ),
                'message'           => $rule['result'] ?? '',
                'conditional_logic' => true,
                'logic_type'        => 'use',
                'conditions'        => array(
                    array(
                        'field_key' => $rule['field_key'] ?? '',
                        'operator'  => 'is',
                        'value'     => $rule['value'] ?? '',
                    ),
                ),
            );
        }

        update_option( 'spf_confirmations', $confs );
    }

    /* 
     * Helpers
     *  */

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

    private function get_confirmations_for_form( int $form_id ): array {
        $all = get_option( 'spf_confirmations', array() );
        return array_values( array_filter( $all, function ( $c ) use ( $form_id ) {
            return (int) ( $c['form_id'] ?? 0 ) === $form_id;
        } ) );
    }

    private function get_entries_for_form( int $form_id ): array {
        $all = get_option( 'spf_entries', array() );
        return array_values( array_filter( $all, function ( $e ) use ( $form_id ) {
            return (int) ( $e['form_id'] ?? 0 ) === $form_id;
        } ) );
    }

    private function get_entries_since( int $form_id, string $since ): int {
        $entries = $this->get_entries_for_form( $form_id );
        $count   = 0;
        foreach ( $entries as $e ) {
            if ( ( $e['created_at'] ?? '' ) >= $since ) {
                $count++;
            }
        }
        return $count;
    }

    private function get_last_entry_date( int $form_id ): string {
        $entries = $this->get_entries_for_form( $form_id );
        if ( empty( $entries ) ) {
            return '';
        }
        $last = end( $entries );
        return $last['created_at'] ?? '';
    }

    /* 
     * PAGE : Dashboard
     *  */

    public function render_dashboard(): void {
        $forms   = $this->get_forms();
        $fields  = get_option( 'spf_fields', array() );
        $confs   = get_option( 'spf_confirmations', array() );
        $entries = get_option( 'spf_entries', array() );
        ?>
        <div class="wrap spf-admin-wrap">
            <h1><?php esc_html_e( 'Smart Programme Finder', 'smart-programme-finder' ); ?></h1>
            <p class="spf-subtitle"><?php esc_html_e( 'Build recommendation forms and guide visitors to the right programme.', 'smart-programme-finder' ); ?></p>

            <div class="spf-dashboard-cards">
                <div class="spf-card">
                    <h2><?php esc_html_e( 'Forms', 'smart-programme-finder' ); ?></h2>
                    <p><?php echo esc_html( count( $forms ) ); ?> <?php esc_html_e( 'forms created.', 'smart-programme-finder' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-forms' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Manage Forms', 'smart-programme-finder' ); ?></a>
                </div>
                <div class="spf-card">
                    <h2><?php esc_html_e( 'Entries', 'smart-programme-finder' ); ?></h2>
                    <p><?php echo esc_html( count( $entries ) ); ?> <?php esc_html_e( 'total submissions.', 'smart-programme-finder' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-entries' ) ); ?>" class="button"><?php esc_html_e( 'View Entries', 'smart-programme-finder' ); ?></a>
                </div>
                <div class="spf-card">
                    <h2><?php esc_html_e( 'Embed', 'smart-programme-finder' ); ?></h2>
                    <p><?php esc_html_e( 'Use a shortcode or the Elementor widget:', 'smart-programme-finder' ); ?></p>
                    <?php foreach ( $forms as $form ) : ?>
                        <code class="spf-shortcode-display">[spf_form id="<?php echo esc_html( $form['id'] ); ?>"]</code>
                        <span class="spf-hint"><?php echo esc_html( $form['name'] ); ?></span><br>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /* 
     * PAGE : All Forms
     *  */

    public function render_forms(): void {
        $forms       = $this->get_forms();
        $total       = count( $forms );
        $all_entries = get_option( 'spf_entries', array() );
        ?>
        <div class="wrap spf-forms-overview">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Forms Overview', 'smart-programme-finder' ); ?></h1>
            <a href="#spf-new-form" class="page-title-action spf-add-new-btn" id="spf-toggle-new-form"><?php esc_html_e( '+ Add New', 'smart-programme-finder' ); ?></a>
            <hr class="wp-header-end">

            <?php $this->render_admin_notices(); ?>

            <!-- Create form (hidden by default, toggled by Add New) -->
            <div id="spf-new-form" class="spf-new-form-panel" style="display:none;">
                <form method="post">
                    <?php wp_nonce_field( 'spf_create_form_action', 'spf_form_nonce' ); ?>
                    <input type="text" name="spf_form_name" class="regular-text" required placeholder="<?php esc_attr_e( 'Enter form name...', 'smart-programme-finder' ); ?>">
                    <button type="submit" name="spf_create_form" class="button button-primary"><?php esc_html_e( 'Create Form', 'smart-programme-finder' ); ?></button>
                    <button type="button" class="button spf-cancel-new-form"><?php esc_html_e( 'Cancel', 'smart-programme-finder' ); ?></button>
                </form>
            </div>

            <!-- Subsubsub filter tabs -->
            <ul class="subsubsub">
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-forms' ) ); ?>" class="current"><?php esc_html_e( 'All', 'smart-programme-finder' ); ?> <span class="count">(<?php echo esc_html( $total ); ?>)</span></a></li>
            </ul>

            <div class="tablenav top">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf( esc_html( _n( '%s item', '%s items', $total, 'smart-programme-finder' ) ), esc_html( $total ) ); ?></span>
                </div>
                <br class="clear">
            </div>

            <table class="wp-list-table widefat fixed striped table-view-list spf-forms-table">
                <thead>
                    <tr>
                        <th scope="col" class="column-name column-primary"><?php esc_html_e( 'Name', 'smart-programme-finder' ); ?></th>
                        <th scope="col" class="column-shortcode"><?php esc_html_e( 'Shortcode', 'smart-programme-finder' ); ?></th>
                        <th scope="col" class="column-author"><?php esc_html_e( 'Fields', 'smart-programme-finder' ); ?></th>
                        <th scope="col" class="column-date"><?php esc_html_e( 'Date', 'smart-programme-finder' ); ?></th>
                        <th scope="col" class="column-entries"><?php esc_html_e( 'Entries', 'smart-programme-finder' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $forms ) ) : ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e( 'No forms found. Click "+ Add New" to create one.', 'smart-programme-finder' ); ?></td>
                    </tr>
                    <?php else : foreach ( $forms as $form ) :
                        $fid         = (int) $form['id'];
                        $field_count = count( $this->get_fields_for_form( $fid ) );
                        $entry_count = count( array_filter( $all_entries, function( $e ) use ( $fid ) {
                            return (int) ( $e['form_id'] ?? 0 ) === $fid;
                        } ) );
                        $created_at  = $form['created_at'] ?? '';
                        $edit_url    = admin_url( 'admin.php?page=spf-form-edit&form_id=' . $fid );
                        $entries_url = admin_url( 'admin.php?page=spf-entries&view=form&form_id=' . $fid );
                        $delete_url  = wp_nonce_url( add_query_arg( array( 'page' => 'spf-forms', 'spf_delete_form' => $fid ), admin_url( 'admin.php' ) ), 'spf_delete_form_' . $fid );
                    ?>
                    <tr>
                        <td class="column-name column-primary" data-colname="Name">
                            <a href="<?php echo esc_url( $edit_url ); ?>" class="row-title"><strong><?php echo esc_html( $form['name'] ); ?></strong></a>
                            <div class="row-actions">
                                <span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'smart-programme-finder' ); ?></a> | </span>
                                <span class="entries"><a href="<?php echo esc_url( $entries_url ); ?>"><?php esc_html_e( 'Entries', 'smart-programme-finder' ); ?></a> | </span>
                                <span class="trash"><a href="<?php echo esc_url( $delete_url ); ?>" class="submitdelete" onclick="return confirm('<?php esc_attr_e( 'Delete this form and all its data?', 'smart-programme-finder' ); ?>');"><?php esc_html_e( 'Delete', 'smart-programme-finder' ); ?></a></span>
                            </div>
                        </td>
                        <td class="column-shortcode" data-colname="Shortcode"><code>[spf_form id="<?php echo esc_html( $fid ); ?>"]</code></td>
                        <td class="column-author" data-colname="Fields"><?php echo esc_html( $field_count ); ?></td>
                        <td class="column-date" data-colname="Date">
                            <?php if ( $created_at ) : ?>
                            <span class="spf-date-label"><?php esc_html_e( 'Created', 'smart-programme-finder' ); ?></span><br>
                            <span class="spf-date-value"><?php echo esc_html( wp_date( 'F j, Y \a\t g:i a', strtotime( $created_at ) ) ); ?></span>
                            <?php else : ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td class="column-entries" data-colname="Entries">
                            <a href="<?php echo esc_url( $entries_url ); ?>"><?php echo esc_html( $entry_count ); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="col" class="column-name column-primary"><?php esc_html_e( 'Name', 'smart-programme-finder' ); ?></th>
                        <th scope="col" class="column-shortcode"><?php esc_html_e( 'Shortcode', 'smart-programme-finder' ); ?></th>
                        <th scope="col" class="column-author"><?php esc_html_e( 'Fields', 'smart-programme-finder' ); ?></th>
                        <th scope="col" class="column-date"><?php esc_html_e( 'Date', 'smart-programme-finder' ); ?></th>
                        <th scope="col" class="column-entries"><?php esc_html_e( 'Entries', 'smart-programme-finder' ); ?></th>
                    </tr>
                </tfoot>
            </table>

            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf( esc_html( _n( '%s item', '%s items', $total, 'smart-programme-finder' ) ), esc_html( $total ) ); ?></span>
                </div>
                <br class="clear">
            </div>
        </div>
        <?php
    }

    /* 
     * PAGE : Entries
     *  */

    public function render_entries(): void {
        $forms   = $this->get_forms();
        $view    = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : '';
        $form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0;

        /* ---------- Single entry detail ---------- */
        if ( 'detail' === $view && isset( $_GET['entry_id'] ) ) {
            $entry_id = (int) $_GET['entry_id'];
            $all      = get_option( 'spf_entries', array() );
            $entry    = null;
            foreach ( $all as $e ) {
                if ( (int) ( $e['id'] ?? 0 ) === $entry_id ) {
                    $entry = $e;
                    break;
                }
            }
            if ( ! $entry ) {
                echo '<div class="wrap"><h1>' . esc_html__( 'Entry Not Found', 'smart-programme-finder' ) . '</h1></div>';
                return;
            }
            $form_name = '';
            foreach ( $forms as $f ) {
                if ( (int) $f['id'] === (int) $entry['form_id'] ) {
                    $form_name = $f['name'];
                    break;
                }
            }
            ?>
            <div class="wrap spf-admin-wrap">
                <h1>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-entries&view=form&form_id=' . (int) $entry['form_id'] ) ); ?>" class="page-title-action" style="margin-right:10px;">&larr; <?php esc_html_e( 'Back', 'smart-programme-finder' ); ?></a>
                    <?php printf( esc_html__( 'Entry #%d', 'smart-programme-finder' ), $entry_id ); ?>
                </h1>
                <div class="spf-card">
                    <table class="widefat striped">
                        <tbody>
                            <tr><th><?php esc_html_e( 'Form', 'smart-programme-finder' ); ?></th><td><?php echo esc_html( $form_name ); ?></td></tr>
                            <tr><th><?php esc_html_e( 'Date', 'smart-programme-finder' ); ?></th><td><?php echo esc_html( $entry['created_at'] ?? '' ); ?></td></tr>
                            <tr><th><?php esc_html_e( 'Result', 'smart-programme-finder' ); ?></th><td><?php echo wp_kses_post( $entry['result'] ?? '' ); ?></td></tr>
                            <tr><th><?php esc_html_e( 'Matched', 'smart-programme-finder' ); ?></th><td><?php echo $entry['matched'] ? esc_html__( 'Yes', 'smart-programme-finder' ) : esc_html__( 'No', 'smart-programme-finder' ); ?></td></tr>
                            <tr><th><?php esc_html_e( 'IP Address', 'smart-programme-finder' ); ?></th><td><?php echo esc_html( $entry['ip'] ?? '' ); ?></td></tr>
                            <?php if ( ! empty( $entry['fields'] ) ) : foreach ( $entry['fields'] as $key => $val ) : ?>
                            <tr><th><?php echo esc_html( $key ); ?></th><td><?php echo esc_html( is_array( $val ) ? implode( ', ', $val ) : $val ); ?></td></tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
            return;
        }

        /* ---------- Single form entries list ---------- */
        if ( 'form' === $view && $form_id ) {
            $entries   = $this->get_entries_for_form( $form_id );
            $form_name = '';
            foreach ( $forms as $f ) {
                if ( (int) $f['id'] === $form_id ) {
                    $form_name = $f['name'];
                    break;
                }
            }
            $entries = array_reverse( $entries ); // newest first
            ?>
            <div class="wrap spf-admin-wrap">
                <h1>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-entries' ) ); ?>" class="page-title-action" style="margin-right:10px;">&larr; <?php esc_html_e( 'All Forms', 'smart-programme-finder' ); ?></a>
                    <?php printf( esc_html__( 'Entries: %s', 'smart-programme-finder' ), esc_html( $form_name ) ); ?>
                    <span class="spf-entry-count"><?php echo esc_html( count( $entries ) ); ?></span>
                </h1>
                <?php $this->render_admin_notices(); ?>

                <?php if ( ! empty( $entries ) ) : ?>
                <div class="spf-entries-toolbar">
                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array(
                        'page'           => 'spf-entries',
                        'spf_export_csv' => $form_id,
                    ), admin_url( 'admin.php' ) ), 'spf_export_csv_' . $form_id ) ); ?>" class="button">
                        <span class="dashicons dashicons-download" style="vertical-align:middle;margin-right:4px;"></span>
                        <?php esc_html_e( 'Export CSV', 'smart-programme-finder' ); ?>
                    </a>
                </div>
                <?php endif; ?>

                <?php if ( empty( $entries ) ) : ?>
                    <div class="spf-card"><p><?php esc_html_e( 'No entries yet for this form.', 'smart-programme-finder' ); ?></p></div>
                <?php else : ?>
                <div class="spf-card">
                    <table class="widefat striped spf-entries-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( '#', 'smart-programme-finder' ); ?></th>
                                <th><?php esc_html_e( 'Date', 'smart-programme-finder' ); ?></th>
                                <th><?php esc_html_e( 'Result', 'smart-programme-finder' ); ?></th>
                                <th><?php esc_html_e( 'Matched', 'smart-programme-finder' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'smart-programme-finder' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $entries as $entry ) :
                                $eid = (int) $entry['id'];
                            ?>
                            <tr>
                                <td><?php echo esc_html( $eid ); ?></td>
                                <td><?php echo esc_html( $entry['created_at'] ?? '' ); ?></td>
                                <td><?php echo wp_kses_post( wp_trim_words( wp_strip_all_tags( $entry['result'] ?? '' ), 12 ) ); ?></td>
                                <td><?php echo $entry['matched'] ? '<span class="spf-badge spf-badge--yes">&#10003;</span>' : '<span class="spf-badge spf-badge--no">&#10007;</span>'; ?></td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-entries&view=detail&entry_id=' . $eid . '&form_id=' . $form_id ) ); ?>"><?php esc_html_e( 'View', 'smart-programme-finder' ); ?></a>
                                    |
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=spf-entries&spf_delete_entry=' . $eid ), 'spf_delete_entry_' . $eid ) ); ?>" class="spf-delete-link" onclick="return confirm('<?php esc_attr_e( 'Delete this entry?', 'smart-programme-finder' ); ?>');"><?php esc_html_e( 'Delete', 'smart-programme-finder' ); ?></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php
            return;
        }

        /* ---------- Overview: All forms + chart ---------- */
        $all_entries  = get_option( 'spf_entries', array() );
        $total        = count( $all_entries );
        $thirty_ago   = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
        $seven_ago    = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

        /* Build daily counts for last 30 days */
        $chart_data = array();
        for ( $i = 29; $i >= 0; $i-- ) {
            $day              = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $chart_data[ $day ] = 0;
        }
        foreach ( $all_entries as $e ) {
            $day = substr( $e['created_at'] ?? '', 0, 10 );
            if ( isset( $chart_data[ $day ] ) ) {
                $chart_data[ $day ]++;
            }
        }
        ?>
        <div class="wrap spf-admin-wrap spf-entries-wrap">
            <h1><?php esc_html_e( 'Entries', 'smart-programme-finder' ); ?></h1>
            <?php $this->render_admin_notices(); ?>

            <!-- Chart card -->
            <div class="spf-card spf-entries-chart-card">
                <div class="spf-entries-chart-header">
                    <div>
                        <h2><?php esc_html_e( 'Total Entries', 'smart-programme-finder' ); ?></h2>
                        <span class="spf-entries-total"><?php echo esc_html( $total ); ?></span>
                    </div>
                    <div class="spf-entries-chart-meta">
                        <span class="spf-entries-range-label"><?php esc_html_e( 'Last 30 Days', 'smart-programme-finder' ); ?></span>
                    </div>
                </div>
                <div class="spf-entries-chart">
                    <canvas id="spf-entries-chart" height="220"></canvas>
                </div>
            </div>

            <!-- Forms summary table -->
            <div class="spf-card">
                <h2><?php esc_html_e( 'All Forms', 'smart-programme-finder' ); ?></h2>
                <?php if ( empty( $forms ) ) : ?>
                    <p><?php esc_html_e( 'No forms created yet.', 'smart-programme-finder' ); ?></p>
                <?php else : ?>
                <table class="widefat striped spf-entries-summary">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Form Name', 'smart-programme-finder' ); ?></th>
                            <th><?php esc_html_e( 'Created', 'smart-programme-finder' ); ?></th>
                            <th><?php esc_html_e( 'Last Entry', 'smart-programme-finder' ); ?></th>
                            <th><?php esc_html_e( 'All Time', 'smart-programme-finder' ); ?></th>
                            <th><?php esc_html_e( 'Last 30 Days', 'smart-programme-finder' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $forms as $form ) :
                            $fid       = (int) $form['id'];
                            $all_count = count( $this->get_entries_for_form( $fid ) );
                            $last30    = $this->get_entries_since( $fid, $thirty_ago );
                            $last_date = $this->get_last_entry_date( $fid );
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-entries&view=form&form_id=' . $fid ) ); ?>">
                                    <strong><?php echo esc_html( $form['name'] ); ?></strong>
                                </a>
                            </td>
                            <td><?php echo esc_html( isset( $form['created'] ) ? wp_date( 'M j, Y', strtotime( $form['created'] ) ) : '-' ); ?></td>
                            <td><?php echo $last_date ? esc_html( wp_date( 'M j, Y g:i a', strtotime( $last_date ) ) ) : '-'; ?></td>
                            <td><strong><?php echo esc_html( $all_count ); ?></strong></td>
                            <td><?php echo esc_html( $last30 ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <script>
        (function(){
            var canvas = document.getElementById('spf-entries-chart');
            if ( ! canvas || ! canvas.getContext ) return;
            var ctx    = canvas.getContext('2d');
            var data   = <?php echo wp_json_encode( array_values( $chart_data ) ); ?>;
            var labels = <?php echo wp_json_encode( array_map( function( $d ){ return wp_date( 'M j', strtotime( $d ) ); }, array_keys( $chart_data ) ) ); ?>;
            var W = canvas.parentElement.offsetWidth;
            var H = 220;
            canvas.width  = W;
            canvas.height = H;
            var pad = { top:20, right:20, bottom:40, left:40 };
            var cW  = W - pad.left - pad.right;
            var cH  = H - pad.top - pad.bottom;
            var max = Math.max.apply( null, data ) || 1;
            max = Math.ceil( max * 1.2 ) || 1;

            /* Grid */
            ctx.strokeStyle = '#e4e6eb';
            ctx.lineWidth   = 1;
            var steps = 4;
            for ( var i = 0; i <= steps; i++ ) {
                var y = pad.top + ( cH / steps ) * i;
                ctx.beginPath(); ctx.moveTo( pad.left, y ); ctx.lineTo( W - pad.right, y ); ctx.stroke();
                ctx.fillStyle = '#82868b';
                ctx.font = '11px -apple-system, BlinkMacSystemFont, sans-serif';
                ctx.textAlign = 'right';
                ctx.fillText( Math.round( max - ( max / steps ) * i ), pad.left - 6, y + 4 );
            }

            /* X labels */
            ctx.fillStyle = '#82868b';
            ctx.textAlign = 'center';
            var labelStep = Math.ceil( labels.length / 10 );
            for ( var i = 0; i < labels.length; i += labelStep ) {
                var x = pad.left + ( cW / ( data.length - 1 ) ) * i;
                ctx.fillText( labels[i], x, H - pad.bottom + 18 );
            }

            /* Line */
            ctx.beginPath();
            ctx.strokeStyle = '#e27730';
            ctx.lineWidth   = 2.5;
            ctx.lineJoin    = 'round';
            for ( var i = 0; i < data.length; i++ ) {
                var x = pad.left + ( cW / ( data.length - 1 ) ) * i;
                var y = pad.top + cH - ( data[i] / max ) * cH;
                if ( i === 0 ) ctx.moveTo( x, y ); else ctx.lineTo( x, y );
            }
            ctx.stroke();

            /* Dots */
            ctx.fillStyle = '#e27730';
            for ( var i = 0; i < data.length; i++ ) {
                if ( data[i] === 0 ) continue;
                var x = pad.left + ( cW / ( data.length - 1 ) ) * i;
                var y = pad.top + cH - ( data[i] / max ) * cH;
                ctx.beginPath(); ctx.arc( x, y, 3.5, 0, Math.PI * 2 ); ctx.fill();
            }
        })();
        </script>
        <?php
    }

    /* 
     * PAGE : Form Editor (WPForms-like builder)
     *  */

    public function render_form_edit(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
        $form    = $this->get_form( $form_id );

        if ( ! $form ) {
            wp_safe_redirect( admin_url( 'admin.php?page=spf-forms' ) );
            exit;
        }

        // Migrate old rules to confirmations if needed.
        $this->maybe_migrate_rules( $form_id );

        $fields        = $this->get_fields_for_form( $form_id );
        $confirmations = $this->get_confirmations_for_form( $form_id );
        $s             = wp_parse_args( $form['settings'] ?? array(), self::DEFAULT_APPEARANCE );
        $g             = wp_parse_args( $form['general'] ?? array(), self::DEFAULT_GENERAL );

        // Active panel / tab state from query params.
        $active_panel = sanitize_text_field( $_GET['panel'] ?? 'fields' );
        $active_tab   = sanitize_text_field( $_GET['tab'] ?? 'add-fields' );
        $active_stab  = sanitize_text_field( $_GET['stab'] ?? 'general' );
        $edit_field_id = isset( $_GET['field_id'] ) ? absint( $_GET['field_id'] ) : 0;
        // phpcs:enable

        // Resolve the field being edited (if any).
        $edit_field = null;
        if ( $edit_field_id > 0 ) {
            foreach ( $fields as $f ) {
                if ( (int) $f['id'] === $edit_field_id ) {
                    $edit_field = $f;
                    break;
                }
            }
        }

        // Build fields JSON for JS.
        $fields_json = wp_json_encode( array_values( array_map( function ( $f ) {
            return array(
                'id'          => (int) $f['id'],
                'field_key'         => $f['field_key'],
                'label'             => $f['label'],
                'type'              => $f['type'],
                'size'              => $f['size'] ?? 'medium',
                'placeholder'       => $f['placeholder'] ?? '',
                'required'          => ! empty( $f['required'] ),
                'options'           => $f['options'] ?? array(),
                'description'       => $f['description'] ?? '',
                'css_class'         => $f['css_class'] ?? '',
                'hide_label'        => ! empty( $f['hide_label'] ),
                'conditional_logic' => ! empty( $f['conditional_logic'] ),
                'conditional_type'  => $f['conditional_type'] ?? 'show',
                'conditionals'      => $f['conditionals'] ?? array(),
            );
        }, $fields ) ) );

        // Hide WP admin sidebar & footer for full-page builder experience.
        ?>
        <style>
            #wpcontent { margin-left: 0 !important; padding-left: 0 !important; }
            #wpbody-content { padding-bottom: 0 !important; }
            #wpfooter { display: none !important; }
            #adminmenumain, #adminmenuback, #adminmenuwrap { display: none !important; }
            html.wp-toolbar { padding-top: 32px !important; }
            .notice, .update-nag, .updated { display: none !important; }
        </style>

        <div id="spf-builder" class="spf-builder"
             data-panel="<?php echo esc_attr( $active_panel ); ?>"
             data-tab="<?php echo esc_attr( $active_tab ); ?>"
             data-stab="<?php echo esc_attr( $active_stab ); ?>"
             data-form-id="<?php echo esc_attr( $form_id ); ?>"
             data-fields="<?php echo esc_attr( $fields_json ); ?>">

            <!--  TOP BAR  -->
            <div class="spf-builder-topbar">
                <div class="spf-topbar-left">
                    <span class="dashicons dashicons-search spf-topbar-logo"></span>
                    <span class="spf-topbar-title">
                        <?php esc_html_e( 'Now editing', 'smart-programme-finder' ); ?>
                        <strong><?php echo esc_html( $form['name'] ); ?></strong>
                    </span>
                </div>
                <div class="spf-topbar-right">
                    <button type="button" class="spf-topbar-btn spf-topbar-btn--embed" onclick="prompt('<?php esc_attr_e( 'Copy this shortcode:', 'smart-programme-finder' ); ?>', '[spf_form id=&quot;<?php echo esc_attr( $form_id ); ?>&quot;]');">
                        <span class="dashicons dashicons-shortcode"></span>
                        <?php esc_html_e( 'Embed', 'smart-programme-finder' ); ?>
                    </button>
                    <button type="submit" form="spf-settings-form" class="spf-topbar-btn spf-topbar-btn--save">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e( 'Save', 'smart-programme-finder' ); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=spf-forms' ) ); ?>" class="spf-topbar-btn spf-topbar-btn--close" title="<?php esc_attr_e( 'Close', 'smart-programme-finder' ); ?>">&times;</a>
                </div>
            </div>

            <!--  BODY  -->
            <div class="spf-builder-body">

                <!-- --- ICON SIDEBAR ---------------- -->
                <div class="spf-builder-sidebar">
                    <a href="#" class="spf-sidebar-btn <?php echo 'fields' === $active_panel ? 'active' : ''; ?>" data-panel="fields">
                        <span class="dashicons dashicons-feedback"></span>
                        <span><?php esc_html_e( 'Fields', 'smart-programme-finder' ); ?></span>
                    </a>
                    <a href="#" class="spf-sidebar-btn <?php echo 'settings' === $active_panel ? 'active' : ''; ?>" data-panel="settings">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <span><?php esc_html_e( 'Settings', 'smart-programme-finder' ); ?></span>
                    </a>
                </div>

                <!-- --- FIELDS PANEL (left) --------- -->
                <div class="spf-builder-panel" id="spf-panel-fields" <?php echo 'fields' !== $active_panel ? 'style="display:none"' : ''; ?>>

                    <div class="spf-panel-header">
                        <a href="#" class="spf-panel-tab <?php echo 'add-fields' === $active_tab ? 'active' : ''; ?>" data-tab="add-fields">
                            <span class="dashicons dashicons-welcome-add-page"></span>
                            <?php esc_html_e( 'Add Fields', 'smart-programme-finder' ); ?>
                        </a>
                        <a href="#" class="spf-panel-tab <?php echo 'field-options' === $active_tab ? 'active' : ''; ?>" data-tab="field-options">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php esc_html_e( 'Field Options', 'smart-programme-finder' ); ?>
                        </a>
                    </div>

                    <div class="spf-panel-body">

                        <!-- Add Fields Tab (AJAX buttons) -->
                        <div id="spf-tab-add-fields" class="spf-panel-content" <?php echo 'add-fields' !== $active_tab ? 'style="display:none"' : ''; ?>>
                            <h3 class="spf-panel-section-title"><?php esc_html_e( 'Standard Fields', 'smart-programme-finder' ); ?></h3>
                            <div class="spf-add-fields-grid">
                                <?php foreach ( self::FIELD_TYPES as $type_key => $type_label ) : ?>
                                <button type="button" class="spf-add-field-btn" data-type="<?php echo esc_attr( $type_key ); ?>">
                                    <span class="dashicons <?php echo esc_attr( self::FIELD_ICONS[ $type_key ] ?? 'dashicons-edit' ); ?>"></span>
                                    <span><?php echo esc_html( $type_label ); ?></span>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Field Options Tab (AJAX save) -->
                        <div id="spf-tab-field-options" class="spf-panel-content" <?php echo 'field-options' !== $active_tab ? 'style="display:none"' : ''; ?>>
                            <div id="spf-field-options-wrap">
                                <?php if ( $edit_field ) : ?>
                                <?php $this->render_field_options_form( $edit_field, $form_id ); ?>
                                <?php else : ?>
                                <div class="spf-no-selection">
                                    <span class="dashicons dashicons-edit-large"></span>
                                    <p><?php esc_html_e( 'Click a field in the preview to edit its options.', 'smart-programme-finder' ); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div><!-- /.spf-panel-body -->
                </div><!-- /#spf-panel-fields -->

                <!-- --- SETTINGS PANEL (left - nav only) --- -->
                <div class="spf-builder-panel" id="spf-panel-settings" <?php echo 'settings' !== $active_panel ? 'style="display:none"' : ''; ?>>

                    <div class="spf-settings-subnav">
                        <a href="#" class="spf-subnav-link <?php echo 'general' === $active_stab ? 'active' : ''; ?>" data-stab="general">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php esc_html_e( 'General', 'smart-programme-finder' ); ?>
                        </a>
                        <a href="#" class="spf-subnav-link <?php echo 'confirmations' === $active_stab ? 'active' : ''; ?>" data-stab="confirmations">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e( 'Confirmations', 'smart-programme-finder' ); ?>
                        </a>
                        <a href="#" class="spf-subnav-link <?php echo 'appearance' === $active_stab ? 'active' : ''; ?>" data-stab="appearance">
                            <span class="dashicons dashicons-admin-appearance"></span>
                            <?php esc_html_e( 'Appearance', 'smart-programme-finder' ); ?>
                        </a>
                    </div>
                </div><!-- /#spf-panel-settings -->

                <!-- --- RIGHT PANEL (preview + settings content) --- -->
                <div class="spf-builder-preview">

                    <!-- Form Preview (visible when Fields panel is active) -->
                    <div class="spf-preview-inner" id="spf-preview-fields-content" <?php echo 'fields' !== $active_panel ? 'style="display:none"' : ''; ?>>
                        <h2 class="spf-preview-title"><?php echo esc_html( $form['name'] ); ?></h2>

                        <?php $this->render_admin_notices(); ?>

                        <?php if ( empty( $fields ) ) : ?>
                            <div class="spf-preview-empty" id="spf-preview-empty">
                                <span class="dashicons dashicons-welcome-add-page"></span>
                                <p><?php esc_html_e( 'Click a field type on the left to add your first field.', 'smart-programme-finder' ); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="spf-preview-fields spf-preview-grid--<?php echo esc_attr( $s['columns'] ); ?>" id="spf-preview-fields-grid" <?php echo empty( $fields ) ? 'style="display:none"' : ''; ?>>
                            <?php foreach ( $fields as $field ) :
                                $is_active = $edit_field && (int) $edit_field['id'] === (int) $field['id'];
                                $this->render_preview_field_html( $field, $form_id, $is_active );
                            endforeach; ?>
                        </div>

                        <div class="spf-preview-submit" id="spf-preview-submit" <?php echo empty( $fields ) ? 'style="display:none"' : ''; ?>>
                            <button type="button" class="spf-preview-btn"><?php echo esc_html( $s['button_text'] ); ?></button>
                        </div>
                    </div>

                    <!-- Settings Content (visible when Settings panel is active) -->
                    <div class="spf-settings-right-content" id="spf-settings-content" <?php echo 'settings' !== $active_panel ? 'style="display:none"' : ''; ?>>
                        <div class="spf-settings-right-inner">

                            <?php $this->render_admin_notices(); ?>

                            <!-- General + Appearance form -->
                            <form method="post" id="spf-settings-form">
                                <?php wp_nonce_field( 'spf_save_form_edit_action', 'spf_edit_nonce' ); ?>
                                <input type="hidden" name="spf_form_id" value="<?php echo esc_attr( $form_id ); ?>">
                                <input type="hidden" name="spf_save_form_edit" value="1">
                                <input type="hidden" name="spf_active_stab" id="spf-active-stab" value="<?php echo esc_attr( $active_stab ); ?>">

                                <!-- General -->
                                <div id="spf-stab-general" class="spf-stab-content" <?php echo 'general' !== $active_stab ? 'style="display:none"' : ''; ?>>
                                    <h2><?php esc_html_e( 'General', 'smart-programme-finder' ); ?></h2>

                                    <div class="spf-settings-field">
                                        <label for="spf_form_name"><?php esc_html_e( 'Form Name', 'smart-programme-finder' ); ?></label>
                                        <input type="text" id="spf_form_name" name="spf_form_name" value="<?php echo esc_attr( $form['name'] ); ?>" class="spf-input-full">
                                    </div>

                                    <div class="spf-settings-field">
                                        <label for="spf_general_description"><?php esc_html_e( 'Form Description', 'smart-programme-finder' ); ?></label>
                                        <textarea id="spf_general_description" name="spf_general_description" rows="3" class="spf-input-full"><?php echo esc_textarea( $g['description'] ); ?></textarea>
                                    </div>

                                    <div class="spf-settings-field">
                                        <label for="spf_button_text"><?php esc_html_e( 'Submit Button Text', 'smart-programme-finder' ); ?></label>
                                        <input type="text" id="spf_button_text" name="spf_button_text" value="<?php echo esc_attr( $s['button_text'] ); ?>" class="spf-input-full">
                                    </div>

                                    <div class="spf-settings-field">
                                        <label for="spf_general_button_processing_text"><?php esc_html_e( 'Submit Button Processing Text', 'smart-programme-finder' ); ?></label>
                                        <input type="text" id="spf_general_button_processing_text" name="spf_general_button_processing_text" value="<?php echo esc_attr( $g['button_processing_text'] ); ?>" class="spf-input-full">
                                    </div>

                                    <div class="spf-settings-field">
                                        <label for="spf_general_conf_btn_text"><?php esc_html_e( 'Confirmation Button Text', 'smart-programme-finder' ); ?></label>
                                        <input type="text" id="spf_general_conf_btn_text" name="spf_general_conf_btn_text" value="<?php echo esc_attr( $g['conf_btn_text'] ); ?>" class="spf-input-full">
                                        <p class="description"><?php esc_html_e( 'Text for the &ldquo;Try Again&rdquo; button shown in the confirmation popup / inline message.', 'smart-programme-finder' ); ?></p>
                                    </div>

                                    <h3 class="spf-settings-section-title"><?php esc_html_e( 'Advanced', 'smart-programme-finder' ); ?></h3>

                                    <div class="spf-settings-field">
                                        <label for="spf_general_form_css_class"><?php esc_html_e( 'Form CSS Class', 'smart-programme-finder' ); ?></label>
                                        <input type="text" id="spf_general_form_css_class" name="spf_general_form_css_class" value="<?php echo esc_attr( $g['form_css_class'] ); ?>" class="spf-input-full">
                                    </div>

                                    <div class="spf-settings-field">
                                        <label for="spf_general_button_css_class"><?php esc_html_e( 'Submit Button CSS Class', 'smart-programme-finder' ); ?></label>
                                        <input type="text" id="spf_general_button_css_class" name="spf_general_button_css_class" value="<?php echo esc_attr( $g['button_css_class'] ); ?>" class="spf-input-full">
                                    </div>
                                </div>

                                <!-- Appearance -->
                                <div id="spf-stab-appearance" class="spf-stab-content" <?php echo 'appearance' !== $active_stab ? 'style="display:none"' : ''; ?>>
                                    <h2><?php esc_html_e( 'Appearance', 'smart-programme-finder' ); ?></h2>

                                    <h3 class="spf-settings-section-title"><?php esc_html_e( 'Layout', 'smart-programme-finder' ); ?></h3>

                                    <div class="spf-settings-field">
                                        <label><?php esc_html_e( 'Form Max-Width (px)', 'smart-programme-finder' ); ?></label>
                                        <input type="number" name="spf_form_width" value="<?php echo esc_attr( $s['form_width'] ); ?>" min="200" max="1400" step="10" placeholder="<?php esc_attr_e( 'Full width', 'smart-programme-finder' ); ?>">
                                    </div>

                                    <div class="spf-settings-field">
                                        <label><?php esc_html_e( 'Number of Columns', 'smart-programme-finder' ); ?></label>
                                        <select name="spf_columns">
                                            <?php for ( $i = 1; $i <= 4; $i++ ) : ?>
                                            <option value="<?php echo esc_attr( $i ); ?>" <?php selected( $s['columns'], (string) $i ); ?>><?php echo esc_html( $i ); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>

                                    <div class="spf-settings-field">
                                        <label><?php esc_html_e( 'Field Spacing (px)', 'smart-programme-finder' ); ?></label>
                                        <input type="number" name="spf_field_spacing" value="<?php echo esc_attr( $s['field_spacing'] ); ?>" min="0" max="60" step="2">
                                    </div>

                                    <div class="spf-settings-field">
                                        <label><?php esc_html_e( 'Form Padding (px)', 'smart-programme-finder' ); ?></label>
                                        <input type="number" name="spf_form_padding" value="<?php echo esc_attr( $s['form_padding'] ); ?>" min="0" max="60" step="2">
                                    </div>

                                    <div class="spf-settings-field">
                                        <label><?php esc_html_e( 'Form Border Radius (px)', 'smart-programme-finder' ); ?></label>
                                        <input type="number" name="spf_form_border_radius" value="<?php echo esc_attr( $s['form_border_radius'] ); ?>" min="0" max="30">
                                    </div>

                                    <h3 class="spf-settings-section-title"><?php esc_html_e( 'Button', 'smart-programme-finder' ); ?></h3>

                                    <div class="spf-settings-field">
                                        <label><?php esc_html_e( 'Button Border Radius (px)', 'smart-programme-finder' ); ?></label>
                                        <input type="number" name="spf_btn_radius" value="<?php echo esc_attr( $s['btn_radius'] ); ?>" min="0" max="50">
                                    </div>

                                    <h3 class="spf-settings-section-title"><?php esc_html_e( 'Colors', 'smart-programme-finder' ); ?></h3>

                                    <?php
                                    $colors = array(
                                        'primary_color'  => __( 'Primary / Button Background', 'smart-programme-finder' ),
                                        'primary_hover'  => __( 'Primary Hover', 'smart-programme-finder' ),
                                        'btn_text_color' => __( 'Button Text Color', 'smart-programme-finder' ),
                                        'label_color'    => __( 'Label Color', 'smart-programme-finder' ),
                                        'input_bg'       => __( 'Input Background', 'smart-programme-finder' ),
                                        'input_text'     => __( 'Input Text Color', 'smart-programme-finder' ),
                                        'input_border'   => __( 'Input Border Color', 'smart-programme-finder' ),
                                        'form_bg'        => __( 'Form Background', 'smart-programme-finder' ),
                                    );
                                    foreach ( $colors as $ck => $cl ) : ?>
                                    <div class="spf-settings-field spf-settings-field--color">
                                        <label><?php echo esc_html( $cl ); ?></label>
                                        <input type="text" name="spf_<?php echo esc_attr( $ck ); ?>" value="<?php echo esc_attr( $s[ $ck ] ); ?>" class="spf-color-picker">
                                    </div>
                                    <?php endforeach; ?>

                                    <div class="spf-settings-field">
                                        <label><?php esc_html_e( 'Input Border Radius (px)', 'smart-programme-finder' ); ?></label>
                                        <input type="number" name="spf_input_radius" value="<?php echo esc_attr( $s['input_radius'] ); ?>" min="0" max="30">
                                    </div>
                                </div>
                            </form>

                            <!-- Confirmations -->
                            <div id="spf-stab-confirmations" class="spf-stab-content" <?php echo 'confirmations' !== $active_stab ? 'style="display:none"' : ''; ?>>
                                <div class="spf-stab-header">
                                    <h2><?php esc_html_e( 'Confirmations', 'smart-programme-finder' ); ?></h2>
                                    <button type="button" id="spf-add-conf-toggle" class="button button-primary"><?php esc_html_e( 'Add New Confirmation', 'smart-programme-finder' ); ?></button>
                                </div>

                                <!-- New confirmation form (hidden until toggled) -->
                                <div id="spf-add-conf-form" class="spf-conf-card spf-conf-card--new" style="display:none">
                                    <?php $this->render_confirmation_form( $form_id, null, $fields ); ?>
                                </div>

                                <!-- Existing confirmations -->
                                <?php if ( ! empty( $confirmations ) ) : ?>
                                    <?php foreach ( $confirmations as $conf ) : ?>
                                    <div class="spf-conf-card">
                                        <?php $this->render_confirmation_form( $form_id, $conf, $fields ); ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <p class="spf-empty-state" id="spf-no-confirmations"><?php esc_html_e( 'No confirmations yet. Add your first confirmation above.', 'smart-programme-finder' ); ?></p>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>

                </div><!-- /.spf-builder-preview -->
            </div><!-- /.spf-builder-body -->
        </div><!-- /#spf-builder -->
        <?php
    }

    /**
     * Render the field options editing form with General / Advanced / Smart Logic sub-tabs.
     */
    private function render_field_options_form( array $edit_field, int $form_id ): void {
        $type_label = self::FIELD_TYPES[ $edit_field['type'] ] ?? ucfirst( $edit_field['type'] );
        ?>
        <form method="post" class="spf-field-options-form" id="spf-field-options-form" data-field-id="<?php echo esc_attr( $edit_field['id'] ); ?>">
            <?php wp_nonce_field( 'spf_update_field_action', 'spf_update_field_nonce' ); ?>
            <input type="hidden" name="spf_field_id" value="<?php echo esc_attr( $edit_field['id'] ); ?>">
            <input type="hidden" name="spf_form_id" value="<?php echo esc_attr( $form_id ); ?>">

            <div class="spf-fo-header">
                <span class="spf-fo-header-type"><?php echo esc_html( $type_label ); ?></span>
                <span class="spf-fo-header-id">(ID #<?php echo esc_html( $edit_field['id'] ); ?>)</span>
            </div>

            <!-- Sub-tab navigation -->
            <div class="spf-fo-tabs">
                <button type="button" class="spf-fo-tab spf-fo-tab--active" data-fo-tab="general"><?php esc_html_e( 'General', 'smart-programme-finder' ); ?></button>
                <button type="button" class="spf-fo-tab" data-fo-tab="advanced"><?php esc_html_e( 'Advanced', 'smart-programme-finder' ); ?></button>
                <button type="button" class="spf-fo-tab" data-fo-tab="smart-logic"><?php esc_html_e( 'Smart Logic', 'smart-programme-finder' ); ?></button>
            </div>

            <!-- General pane -->
            <div class="spf-fo-pane spf-fo-pane--active" data-fo-pane="general">
                <div class="spf-option-row">
                    <label><?php esc_html_e( 'Label', 'smart-programme-finder' ); ?></label>
                    <input type="text" name="spf_field_label" value="<?php echo esc_attr( $edit_field['label'] ); ?>" required>
                </div>

                <div class="spf-option-row">
                    <label><?php esc_html_e( 'Type', 'smart-programme-finder' ); ?></label>
                    <select name="spf_field_type" id="spf_field_type">
                        <?php foreach ( self::FIELD_TYPES as $tk => $tl ) : ?>
                        <option value="<?php echo esc_attr( $tk ); ?>" <?php selected( $edit_field['type'], $tk ); ?>><?php echo esc_html( $tl ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="spf-option-row" id="spf-options-row" <?php echo ! in_array( $edit_field['type'], self::OPTION_TYPES, true ) ? 'style="display:none"' : ''; ?>>
                    <label><?php esc_html_e( 'Options (comma-separated)', 'smart-programme-finder' ); ?></label>
                    <textarea name="spf_field_options" rows="3"><?php echo esc_textarea( implode( ', ', $edit_field['options'] ?? array() ) ); ?></textarea>
                </div>

                <div class="spf-option-row">
                    <label><?php esc_html_e( 'Description', 'smart-programme-finder' ); ?></label>
                    <textarea name="spf_field_description" rows="2"><?php echo esc_textarea( $edit_field['description'] ?? '' ); ?></textarea>
                </div>

                <div class="spf-option-row spf-option-row--checkbox">
                    <label>
                        <input type="checkbox" name="spf_field_required" value="1" <?php checked( ! empty( $edit_field['required'] ) ); ?>>
                        <?php esc_html_e( 'Required', 'smart-programme-finder' ); ?>
                    </label>
                </div>
            </div>

            <!-- Advanced pane -->
            <div class="spf-fo-pane" data-fo-pane="advanced">
                <div class="spf-option-row">
                    <label><?php esc_html_e( 'Field Size', 'smart-programme-finder' ); ?></label>
                    <select name="spf_field_size">
                        <option value="small" <?php selected( $edit_field['size'] ?? 'medium', 'small' ); ?>><?php esc_html_e( 'Small', 'smart-programme-finder' ); ?></option>
                        <option value="medium" <?php selected( $edit_field['size'] ?? 'medium', 'medium' ); ?>><?php esc_html_e( 'Medium', 'smart-programme-finder' ); ?></option>
                        <option value="large" <?php selected( $edit_field['size'] ?? 'medium', 'large' ); ?>><?php esc_html_e( 'Large', 'smart-programme-finder' ); ?></option>
                    </select>
                </div>

                <div class="spf-option-row">
                    <label><?php esc_html_e( 'Placeholder Text', 'smart-programme-finder' ); ?></label>
                    <input type="text" name="spf_field_placeholder" value="<?php echo esc_attr( $edit_field['placeholder'] ?? '' ); ?>">
                </div>

                <div class="spf-option-row">
                    <label><?php esc_html_e( 'CSS Classes', 'smart-programme-finder' ); ?></label>
                    <input type="text" name="spf_field_css_class" value="<?php echo esc_attr( $edit_field['css_class'] ?? '' ); ?>">
                </div>

                <div class="spf-option-row spf-option-row--checkbox">
                    <label>
                        <input type="checkbox" name="spf_field_hide_label" value="1" <?php checked( ! empty( $edit_field['hide_label'] ) ); ?>>
                        <?php esc_html_e( 'Hide Label', 'smart-programme-finder' ); ?>
                    </label>
                </div>

                <div class="spf-option-row">
                    <p class="description"><strong><?php esc_html_e( 'Field Key:', 'smart-programme-finder' ); ?></strong> <code><?php echo esc_html( $edit_field['field_key'] ); ?></code></p>
                </div>
            </div>

            <!-- Smart Logic pane -->
            <div class="spf-fo-pane" data-fo-pane="smart-logic">
                <div class="spf-option-row spf-option-row--checkbox">
                    <label>
                        <input type="checkbox" name="spf_field_conditional" value="1" disabled>
                        <?php esc_html_e( 'Enable Conditional Logic', 'smart-programme-finder' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Conditional logic for individual fields is coming soon.', 'smart-programme-finder' ); ?></p>
                </div>
            </div>

            <div class="spf-option-actions">
                <button type="button" class="button button-primary spf-apply-field-btn"><?php esc_html_e( 'Apply', 'smart-programme-finder' ); ?></button>
            </div>
        </form>
        <?php
    }

    /* -- Confirmation form (reusable for new + edit) -- */

    private function render_confirmation_form( int $form_id, ?array $conf, array $fields ): void {
        $is_new     = null === $conf;
        $conf_id    = $is_new ? 0 : (int) $conf['id'];
        $conf_name  = $is_new ? '' : ( $conf['name'] ?? '' );
        $conf_msg   = $is_new ? '' : ( $conf['message'] ?? '' );
        $conf_type  = $is_new ? 'popup' : ( $conf['confirmation_type'] ?? 'popup' );
        $cond_on    = $is_new ? false : ! empty( $conf['conditional_logic'] );
        $logic_type = $is_new ? 'use' : ( $conf['logic_type'] ?? 'use' );
        $conditions = $is_new ? array() : ( $conf['conditions'] ?? array() );
        ?>
        <form method="post" class="spf-conf-form">
            <?php wp_nonce_field( 'spf_save_confirmation_action', 'spf_conf_nonce' ); ?>
            <input type="hidden" name="spf_form_id" value="<?php echo esc_attr( $form_id ); ?>">
            <input type="hidden" name="spf_conf_id" value="<?php echo esc_attr( $conf_id ); ?>">

            <div class="spf-conf-card-header">
                <input type="text" name="spf_conf_name" value="<?php echo esc_attr( $conf_name ); ?>" placeholder="<?php esc_attr_e( 'Confirmation Name', 'smart-programme-finder' ); ?>" class="spf-conf-name-input" required>
                <?php if ( ! $is_new ) : ?>
                <div class="spf-conf-card-actions">
                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array(
                        'page'                   => 'spf-form-edit',
                        'form_id'                => $form_id,
                        'spf_delete_confirmation' => $conf_id,
                    ), admin_url( 'admin.php' ) ), 'spf_delete_confirmation_' . $conf_id ) ); ?>" class="spf-conf-delete" onclick="return confirm('<?php esc_attr_e( 'Delete this confirmation?', 'smart-programme-finder' ); ?>');" title="<?php esc_attr_e( 'Delete', 'smart-programme-finder' ); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <div class="spf-conf-card-body">
                <div class="spf-conf-field">
                    <label><?php esc_html_e( 'Confirmation Type', 'smart-programme-finder' ); ?></label>
                    <select name="spf_conf_type">
                        <option value="popup" <?php selected( $conf_type, 'popup' ); ?>><?php esc_html_e( 'Popup (Modal)', 'smart-programme-finder' ); ?></option>
                        <option value="message" <?php selected( $conf_type, 'message' ); ?>><?php esc_html_e( 'Message (Below Form)', 'smart-programme-finder' ); ?></option>
                    </select>
                </div>

                <div class="spf-conf-field">
                    <label><?php esc_html_e( 'Confirmation Message', 'smart-programme-finder' ); ?></label>
                    <?php
                    $editor_id = 'spf_conf_message_' . $conf_id;
                    wp_editor( $conf_msg, $editor_id, array(
                        'textarea_name' => 'spf_conf_message',
                        'textarea_rows' => 5,
                        'media_buttons' => false,
                        'quicktags'     => true,
                        'tinymce'       => array(
                            'toolbar1' => 'bold,italic,underline,strikethrough,blockquote,bullist,numlist,alignleft,aligncenter,alignright,alignjustify,undo,redo,link,unlink',
                            'toolbar2' => '',
                        ),
                    ) );
                    ?>
                </div>

                <div class="spf-conf-logic-section">
                    <div class="spf-conf-logic-toggle-bar">
                        <span class="spf-conf-logic-section-label"><?php esc_html_e( 'Conditional Logic', 'smart-programme-finder' ); ?></span>
                        <label class="spf-toggle-label">
                            <span class="spf-toggle-text"><?php esc_html_e( 'Enable', 'smart-programme-finder' ); ?></span>
                            <input type="checkbox" name="spf_conf_conditional" value="1" class="spf-conf-cond-toggle" <?php checked( $cond_on ); ?>>
                            <span class="spf-toggle-switch"></span>
                        </label>
                    </div>

                    <div class="spf-conf-conditions-wrap" <?php echo ! $cond_on ? 'style="display:none"' : ''; ?>>
                    <div class="spf-conf-logic-header">
                        <select name="spf_conf_logic_type" class="spf-conf-logic-type">
                            <option value="use" <?php selected( $logic_type, 'use' ); ?>><?php esc_html_e( 'Use', 'smart-programme-finder' ); ?></option>
                            <option value="dont_use" <?php selected( $logic_type, 'dont_use' ); ?>><?php esc_html_e( "Don't use", 'smart-programme-finder' ); ?></option>
                        </select>
                        <span><?php esc_html_e( 'this confirmation if', 'smart-programme-finder' ); ?></span>
                    </div>

                    <div class="spf-conf-condition-rows" data-fields='<?php echo esc_attr( wp_json_encode( array_map( function ( $f ) {
                        return array( 'key' => $f['field_key'], 'label' => $f['label'], 'options' => $f['options'] ?? array() );
                    }, $fields ) ) ); ?>'>
                        <?php
                        if ( empty( $conditions ) ) {
                            $conditions = array( array( 'field_key' => '', 'operator' => 'is', 'value' => '' ) );
                        }
                        foreach ( $conditions as $ci => $cond ) :
                        ?>
                        <div class="spf-conf-condition-row">
                            <select name="spf_conf_conditions[<?php echo esc_attr( $ci ); ?>][field_key]" class="spf-cond-field">
                                <option value=""><?php esc_html_e( '- Select Field -', 'smart-programme-finder' ); ?></option>
                                <?php foreach ( $fields as $f ) : ?>
                                <option value="<?php echo esc_attr( $f['field_key'] ); ?>" <?php selected( $cond['field_key'] ?? '', $f['field_key'] ); ?>><?php echo esc_html( $f['label'] ); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <select name="spf_conf_conditions[<?php echo esc_attr( $ci ); ?>][operator]" class="spf-cond-op">
                                <option value="is" <?php selected( $cond['operator'] ?? 'is', 'is' ); ?>><?php esc_html_e( 'is', 'smart-programme-finder' ); ?></option>
                                <option value="is_not" <?php selected( $cond['operator'] ?? 'is', 'is_not' ); ?>><?php esc_html_e( 'is not', 'smart-programme-finder' ); ?></option>
                            </select>

                            <?php
                            // Find the selected field's choices for the value dropdown
                            $cond_fk = $cond['field_key'] ?? '';
                            $cond_opts = array();
                            if ( $cond_fk ) {
                                foreach ( $fields as $cf ) {
                                    if ( $cf['field_key'] === $cond_fk && ! empty( $cf['options'] ) ) {
                                        $cond_opts = is_array( $cf['options'] )
                                            ? $cf['options']
                                            : array_map( 'trim', explode( ',', $cf['options'] ) );
                                        break;
                                    }
                                }
                            }
                            ?>
                            <select name="spf_conf_conditions[<?php echo esc_attr( $ci ); ?>][value]" class="spf-cond-value">
                                <option value=""><?php esc_html_e( '— Select Choice —', 'smart-programme-finder' ); ?></option>
                                <?php foreach ( $cond_opts as $opt ) : ?>
                                <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $cond['value'] ?? '', $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <button type="button" class="spf-cond-add button button-small"><?php esc_html_e( 'And', 'smart-programme-finder' ); ?></button>
                            <button type="button" class="spf-cond-remove button button-small spf-btn-danger" title="<?php esc_attr_e( 'Remove', 'smart-programme-finder' ); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    </div>
                </div>

                <div class="spf-conf-footer">
                    <button type="submit" name="spf_save_confirmation" value="1" class="button button-primary"><?php esc_html_e( 'Save Confirmation', 'smart-programme-finder' ); ?></button>
                </div>
            </div>
        </form>
        <?php
    }

    /* -- Field mockup for preview ------------ */

    private function render_field_mockup( array $field ): void {
        $type = $field['type'] ?? 'text';
        $ph   = esc_attr( $field['placeholder'] ?? $field['label'] );

        switch ( $type ) {
            case 'select':
                echo '<div class="spf-mockup-select"><span>' . esc_html( $field['placeholder'] ?: $field['label'] ) . '</span><span class="dashicons dashicons-arrow-down-alt2"></span></div>';
                break;
            case 'textarea':
                echo '<div class="spf-mockup-textarea">' . esc_html( $ph ) . '</div>';
                break;
            case 'radio':
                if ( ! empty( $field['options'] ) ) {
                    echo '<div class="spf-mockup-choices">';
                    foreach ( array_slice( $field['options'], 0, 3 ) as $opt ) {
                        echo '<label class="spf-mockup-radio"><span class="spf-mockup-circle"></span>' . esc_html( $opt ) . '</label>';
                    }
                    echo '</div>';
                }
                break;
            case 'checkbox':
                if ( ! empty( $field['options'] ) ) {
                    echo '<div class="spf-mockup-choices">';
                    foreach ( array_slice( $field['options'], 0, 3 ) as $opt ) {
                        echo '<label class="spf-mockup-check"><span class="spf-mockup-square"></span>' . esc_html( $opt ) . '</label>';
                    }
                    echo '</div>';
                }
                break;
            default:
                echo '<div class="spf-mockup-input">' . esc_html( $ph ) . '</div>';
                break;
        }
    }

    /* 
     * PAGE : Global Settings
     *  */

    public function render_settings(): void {
        $settings = get_option( 'spf_settings', array() );
        $fallback = $settings['fallback_message'] ?? '';
        ?>
        <div class="wrap spf-admin-wrap">
            <h1><?php esc_html_e( 'Global Settings', 'smart-programme-finder' ); ?></h1>
            <?php $this->render_admin_notices(); ?>

            <div class="spf-card spf-card--form">
                <h2><?php esc_html_e( 'Default Fallback Message', 'smart-programme-finder' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Shown when no confirmation rule matches a visitor\'s answers.', 'smart-programme-finder' ); ?></p>
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

    /* 
     * Admin notices
     *  */

    private function render_admin_notices(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';

        $notices = array(
            'form_created'         => __( 'Form created successfully.', 'smart-programme-finder' ),
            'form_deleted'         => __( 'Form deleted.', 'smart-programme-finder' ),
            'field_added'          => __( 'Field added.', 'smart-programme-finder' ),
            'field_updated'        => __( 'Field updated.', 'smart-programme-finder' ),
            'field_deleted'        => __( 'Field deleted.', 'smart-programme-finder' ),
            'confirmation_saved'   => __( 'Confirmation saved.', 'smart-programme-finder' ),
            'confirmation_deleted' => __( 'Confirmation deleted.', 'smart-programme-finder' ),
            'settings_saved'       => __( 'Settings saved.', 'smart-programme-finder' ),
        );

        if ( isset( $notices[ $message ] ) ) {
            printf( '<div class="spf-builder-notice spf-builder-notice--success">%s</div>', esc_html( $notices[ $message ] ) );
        }

        settings_errors( 'spf_messages' );
    }

    /**
     * Strip formula-injection trigger characters from a CSV cell value.
     *
     * Spreadsheet applications (Excel, LibreOffice Calc) interpret cells that
     * begin with `=`, `+`, `-`, `@`, TAB, or CR as formulas. This prevents
     * stored user input from executing as a formula when an admin opens the
     * exported CSV (CWE-1236 / CSV Injection).
     *
     * @param string $value Raw cell value.
     * @return string Sanitized cell value.
     */
    private function sanitize_csv_cell( string $value ): string {
        return ltrim( $value, "=+-@\t\r\n" );
    }

    /* ----------------------------------------
     * AJAX field handlers (no page reload)
     * ---------------------------------------- */

    /**
     * AJAX: Save form settings + all builder fields in one request.
     * Replaces the classic form POST + redirect cycle so the UI never reloads.
     */
    public function ajax_save_form(): void {
        check_ajax_referer( 'spf_builder_ajax', 'nonce' );
        $this->require_admin_cap();

        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        if ( 0 === $form_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid form.', 'smart-programme-finder' ) ) );
        }

        /* ── Form meta (name, general, appearance) ── */
        $forms = get_option( 'spf_forms', array() );
        $found = false;

        foreach ( $forms as &$form ) {
            if ( (int) $form['id'] !== $form_id ) {
                continue;
            }
            $found = true;

            if ( isset( $_POST['spf_form_name'] ) ) {
                $form['name'] = sanitize_text_field( wp_unslash( $_POST['spf_form_name'] ) );
            }

            $general = array();
            foreach ( array_keys( self::DEFAULT_GENERAL ) as $gk ) {
                $post_key       = 'spf_general_' . $gk;
                $general[ $gk ] = isset( $_POST[ $post_key ] )
                    ? sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) )
                    : ( $form['general'][ $gk ] ?? self::DEFAULT_GENERAL[ $gk ] );
            }
            $form['general'] = $general;

            $settings = array();
            foreach ( array_keys( self::DEFAULT_APPEARANCE ) as $key ) {
                $post_key        = 'spf_' . $key;
                $settings[ $key ] = isset( $_POST[ $post_key ] )
                    ? sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) )
                    : ( $form['settings'][ $key ] ?? self::DEFAULT_APPEARANCE[ $key ] );
            }
            $form['settings'] = $settings;
            break;
        }
        unset( $form );

        if ( ! $found ) {
            wp_send_json_error( array( 'message' => __( 'Form not found.', 'smart-programme-finder' ) ) );
        }

        update_option( 'spf_forms', $forms );

        /* ── Fields ── */
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each key sanitized individually in the foreach loop below.
        $fields_payload = isset( $_POST['spf_fields'] ) && is_array( $_POST['spf_fields'] )
            ? wp_unslash( $_POST['spf_fields'] )
            : array();
        if ( ! empty( $fields_payload ) ) {
            $all_fields   = get_option( 'spf_fields', array() );
            $other_fields = array_values( array_filter( $all_fields, function ( $f ) use ( $form_id ) {
                return (int) ( $f['form_id'] ?? 0 ) !== $form_id;
            } ) );

            $new_fields = array();
            foreach ( $fields_payload as $fd ) {
                if ( ! is_array( $fd ) ) {
                    continue;
                }

                $type = sanitize_text_field( wp_unslash( $fd['type'] ?? 'text' ) );
                if ( ! array_key_exists( $type, self::FIELD_TYPES ) ) {
                    $type = 'text';
                }

                $size = sanitize_text_field( wp_unslash( $fd['size'] ?? 'medium' ) );
                if ( ! in_array( $size, array( 'small', 'medium', 'large' ), true ) ) {
                    $size = 'medium';
                }

                $field_id  = isset( $fd['id'] ) ? absint( $fd['id'] ) : 0;
                $label     = sanitize_text_field( wp_unslash( $fd['label'] ?? '' ) );
                $field_key = ! empty( $fd['field_key'] )
                    ? sanitize_title( wp_unslash( $fd['field_key'] ) )
                    : sanitize_title( $label ) . '_' . $field_id;

                $options_raw = sanitize_textarea_field( wp_unslash( $fd['options'] ?? '' ) );
                $options     = in_array( $type, self::OPTION_TYPES, true )
                    ? array_values( array_filter( array_map( 'trim', explode( ',', $options_raw ) ) ) )
                    : array();

                $conditionals     = array();
                $conditionals_raw = wp_unslash( $fd['conditionals'] ?? '[]' );
                $decoded          = json_decode( $conditionals_raw, true );
                if ( is_array( $decoded ) ) {
                    foreach ( $decoded as $cond ) {
                        if ( ! is_array( $cond ) ) {
                            continue;
                        }
                        $conditionals[] = array(
                            'field_key' => sanitize_text_field( $cond['field_key'] ?? '' ),
                            'operator'  => sanitize_text_field( $cond['operator'] ?? 'is' ),
                            'value'     => sanitize_text_field( $cond['value'] ?? '' ),
                        );
                    }
                }

                $new_fields[] = array(
                    'id'                => $field_id,
                    'form_id'           => $form_id,
                    'field_key'         => $field_key,
                    'label'             => $label,
                    'type'              => $type,
                    'size'              => $size,
                    'placeholder'       => sanitize_text_field( wp_unslash( $fd['placeholder'] ?? '' ) ),
                    'options'           => $options,
                    'required'          => ! empty( $fd['required'] ) && '0' !== (string) $fd['required'],
                    'description'       => sanitize_textarea_field( wp_unslash( $fd['description'] ?? '' ) ),
                    'css_class'         => sanitize_text_field( wp_unslash( $fd['css_class'] ?? '' ) ),
                    'hide_label'        => ! empty( $fd['hide_label'] ) && '0' !== (string) $fd['hide_label'],
                    'default_value'     => sanitize_text_field( wp_unslash( $fd['default_value'] ?? '' ) ),
                    'input_columns'     => sanitize_text_field( wp_unslash( $fd['input_columns'] ?? '' ) ),
                    'conditional_logic' => ! empty( $fd['conditional_logic'] ) && '0' !== (string) $fd['conditional_logic'],
                    'conditional_type'  => sanitize_text_field( wp_unslash( $fd['conditional_type'] ?? 'show' ) ),
                    'conditionals'      => $conditionals,
                );
            }

            update_option( 'spf_fields', array_merge( $other_fields, $new_fields ) );
        }

        wp_send_json_success( array( 'message' => __( 'Saved.', 'smart-programme-finder' ) ) );
    }

    public function ajax_add_field(): void {
        check_ajax_referer( 'spf_builder_ajax', 'nonce' );
        $this->require_admin_cap();

        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        $type    = isset( $_POST['field_type'] ) ? sanitize_text_field( wp_unslash( $_POST['field_type'] ) ) : 'text';

        if ( 0 === $form_id || ! array_key_exists( $type, self::FIELD_TYPES ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'smart-programme-finder' ) ) );
        }

        $fields   = get_option( 'spf_fields', array() );
        $field_id = count( $fields ) > 0 ? max( array_column( $fields, 'id' ) ) + 1 : 1;
        $label    = self::FIELD_TYPES[ $type ] . ' ' . $field_id;
        $field_key = sanitize_title( $label ) . '_' . $field_id;

        $default_options = array();
        if ( in_array( $type, self::OPTION_TYPES, true ) ) {
            $default_options = array( 'Option 1', 'Option 2', 'Option 3' );
        }

        $new_field = array(
            'id'               => $field_id,
            'form_id'          => $form_id,
            'field_key'        => $field_key,
            'label'            => $label,
            'type'             => $type,
            'size'             => 'medium',
            'placeholder'      => '',
            'options'          => $default_options,
            'required'         => true,
            'description'      => '',
            'css_class'        => '',
            'hide_label'       => false,
            'default_value'    => '',
            'input_columns'    => '',
            'conditional_logic' => false,
            'conditional_type'  => 'show',
            'conditionals'      => array(),
        );

        $fields[] = $new_field;
        update_option( 'spf_fields', $fields );

        ob_start();
        $this->render_preview_field_html( $new_field, $form_id, false );
        $preview_html = ob_get_clean();

        wp_send_json_success( array(
            'field'        => $new_field,
            'preview_html' => $preview_html,
        ) );
    }

    public function ajax_delete_field(): void {
        check_ajax_referer( 'spf_builder_ajax', 'nonce' );
        $this->require_admin_cap();

        $field_id = isset( $_POST['field_id'] ) ? absint( $_POST['field_id'] ) : 0;

        if ( 0 === $field_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid field.', 'smart-programme-finder' ) ) );
        }

        $fields = get_option( 'spf_fields', array() );
        $fields = array_values( array_filter( $fields, function ( $f ) use ( $field_id ) {
            return (int) $f['id'] !== $field_id;
        } ) );
        update_option( 'spf_fields', $fields );

        wp_send_json_success();
    }

    public function ajax_update_field(): void {
        check_ajax_referer( 'spf_builder_ajax', 'nonce' );
        $this->require_admin_cap();

        $field_id    = isset( $_POST['field_id'] ) ? absint( $_POST['field_id'] ) : 0;
        $form_id     = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        $label       = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
        $type        = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'text';
        $size        = isset( $_POST['size'] ) ? sanitize_text_field( wp_unslash( $_POST['size'] ) ) : 'medium';
        $placeholder = isset( $_POST['placeholder'] ) ? sanitize_text_field( wp_unslash( $_POST['placeholder'] ) ) : '';
        $required    = ! empty( $_POST['required'] );
        $options_raw = isset( $_POST['options'] ) ? sanitize_textarea_field( wp_unslash( $_POST['options'] ) ) : '';
        $description      = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
        $css_class        = isset( $_POST['css_class'] ) ? sanitize_text_field( wp_unslash( $_POST['css_class'] ) ) : '';
        $hide_label       = ! empty( $_POST['hide_label'] );
        $default_value    = isset( $_POST['default_value'] ) ? sanitize_text_field( wp_unslash( $_POST['default_value'] ) ) : '';
        $input_columns    = isset( $_POST['input_columns'] ) ? sanitize_text_field( wp_unslash( $_POST['input_columns'] ) ) : '';
        $cond_logic       = ! empty( $_POST['conditional_logic'] );
        $cond_type        = isset( $_POST['conditional_type'] ) ? sanitize_text_field( wp_unslash( $_POST['conditional_type'] ) ) : 'show';
        $conditionals_raw = isset( $_POST['conditionals'] ) ? wp_unslash( $_POST['conditionals'] ) : '[]';

        if ( 0 === $field_id || '' === $label ) {
            wp_send_json_error( array( 'message' => __( 'Label is required.', 'smart-programme-finder' ) ) );
        }
        if ( ! array_key_exists( $type, self::FIELD_TYPES ) ) {
            $type = 'text';
        }
        if ( ! in_array( $size, array( 'small', 'medium', 'large' ), true ) ) {
            $size = 'medium';
        }
        if ( ! in_array( $input_columns, array( '', '2', '3', 'inline' ), true ) ) {
            $input_columns = '';
        }
        if ( ! in_array( $cond_type, array( 'show', 'hide' ), true ) ) {
            $cond_type = 'show';
        }

        $parsed_options = array();
        if ( in_array( $type, self::OPTION_TYPES, true ) ) {
            $parsed_options = array_values( array_filter( array_map( 'trim', explode( ',', $options_raw ) ) ) );
        }

        // Parse conditionals from JSON string.
        $conditionals = array();
        if ( $cond_logic ) {
            $decoded = json_decode( $conditionals_raw, true );
            if ( is_array( $decoded ) ) {
                foreach ( $decoded as $cond ) {
                    $fk = isset( $cond['field_key'] ) ? sanitize_text_field( $cond['field_key'] ) : '';
                    $op = isset( $cond['operator'] ) ? sanitize_text_field( $cond['operator'] ) : 'is';
                    $vl = isset( $cond['value'] ) ? sanitize_text_field( $cond['value'] ) : '';
                    if ( '' !== $fk ) {
                        $conditionals[] = array(
                            'field_key' => $fk,
                            'operator'  => in_array( $op, array( 'is', 'is_not', 'contains', 'not_empty', 'empty' ), true ) ? $op : 'is',
                            'value'     => $vl,
                        );
                    }
                }
            }
        }

        $fields       = get_option( 'spf_fields', array() );
        $updated_field = null;
        foreach ( $fields as &$f ) {
            if ( (int) $f['id'] === $field_id ) {
                $f['label']             = $label;
                $f['type']              = $type;
                $f['size']              = $size;
                $f['placeholder']       = $placeholder;
                $f['required']          = $required;
                $f['options']           = $parsed_options;
                $f['description']       = $description;
                $f['css_class']         = $css_class;
                $f['hide_label']        = $hide_label;
                $f['default_value']     = $default_value;
                $f['input_columns']     = $input_columns;
                $f['conditional_logic'] = $cond_logic;
                $f['conditional_type']  = $cond_type;
                $f['conditionals']      = $conditionals;
                $updated_field          = $f;
                break;
            }
        }
        unset( $f );
        update_option( 'spf_fields', $fields );

        if ( ! $updated_field ) {
            wp_send_json_error( array( 'message' => __( 'Field not found.', 'smart-programme-finder' ) ) );
        }

        ob_start();
        $this->render_preview_field_html( $updated_field, $form_id, true );
        $preview_html = ob_get_clean();

        wp_send_json_success( array(
            'field'        => $updated_field,
            'preview_html' => $preview_html,
        ) );
    }

    /**
     * AJAX: Reorder fields for a form.
     */
    public function ajax_reorder_fields(): void {
        check_ajax_referer( 'spf_builder_ajax', 'nonce' );
        $this->require_admin_cap();

        $form_id   = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $order_raw = isset( $_POST['order'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['order'] ) ) : array();

        if ( 0 === $form_id || empty( $order_raw ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'smart-programme-finder' ) ) );
        }

        $all_fields = get_option( 'spf_fields', array() );

        // Separate this form's fields from other forms' fields.
        $form_fields  = array();
        $other_fields = array();
        foreach ( $all_fields as $f ) {
            if ( (int) ( $f['form_id'] ?? 0 ) === $form_id ) {
                $form_fields[ (int) $f['id'] ] = $f;
            } else {
                $other_fields[] = $f;
            }
        }

        // Rebuild in the new order.
        $sorted = array();
        foreach ( $order_raw as $fid ) {
            if ( isset( $form_fields[ $fid ] ) ) {
                $sorted[] = $form_fields[ $fid ];
            }
        }

        $all_fields = array_merge( $other_fields, $sorted );
        update_option( 'spf_fields', $all_fields );

        wp_send_json_success();
    }

    /**
     * AJAX: Duplicate a field.
     */
    public function ajax_duplicate_field(): void {
        check_ajax_referer( 'spf_builder_ajax', 'nonce' );
        $this->require_admin_cap();

        $field_id = isset( $_POST['field_id'] ) ? absint( $_POST['field_id'] ) : 0;
        $form_id  = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

        if ( 0 === $field_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid field.', 'smart-programme-finder' ) ) );
        }

        $fields = get_option( 'spf_fields', array() );

        // Find the source field.
        $source = null;
        foreach ( $fields as $f ) {
            if ( (int) $f['id'] === $field_id ) {
                $source = $f;
                break;
            }
        }

        if ( ! $source ) {
            wp_send_json_error( array( 'message' => __( 'Field not found.', 'smart-programme-finder' ) ) );
        }

        $new_id    = count( $fields ) > 0 ? max( array_column( $fields, 'id' ) ) + 1 : 1;
        $new_label = $source['label'] . ' ' . __( '(Copy)', 'smart-programme-finder' );
        $new_key   = sanitize_title( $new_label ) . '_' . $new_id;

        $new_field = $source;
        $new_field['id']        = $new_id;
        $new_field['field_key'] = $new_key;
        $new_field['label']     = $new_label;

        // Insert right after the source field.
        $inserted = array();
        foreach ( $fields as $f ) {
            $inserted[] = $f;
            if ( (int) $f['id'] === $field_id ) {
                $inserted[] = $new_field;
            }
        }

        update_option( 'spf_fields', $inserted );

        ob_start();
        $this->render_preview_field_html( $new_field, $form_id ?: (int) ( $source['form_id'] ?? 0 ), false );
        $preview_html = ob_get_clean();

        wp_send_json_success( array(
            'field'          => $new_field,
            'preview_html'   => $preview_html,
            'after_field_id' => $field_id,
        ) );
    }

    /**
     * Render a single field block for the preview area.
     */
    private function render_preview_field_html( array $field, int $form_id, bool $is_active ): void {
        $req_mark = ! empty( $field['required'] ) ? ' <span class="spf-preview-req">*</span>' : '';
        ?>
        <div class="spf-preview-field <?php echo $is_active ? 'spf-preview-field--active' : ''; ?>" data-field-id="<?php echo esc_attr( $field['id'] ); ?>">
            <span class="spf-preview-field-drag dashicons dashicons-move" title="<?php esc_attr_e( 'Drag to reorder', 'smart-programme-finder' ); ?>"></span>
            <a href="#" class="spf-preview-field-link" data-field-id="<?php echo esc_attr( $field['id'] ); ?>">
                <div class="spf-preview-field-header">
                    <span class="dashicons dashicons-visibility spf-preview-eye"></span>
                    <span class="spf-preview-label"><?php echo wp_kses_post( $field['label'] . $req_mark ); ?></span>
                </div>
                <div class="spf-preview-field-mockup">
                    <?php $this->render_field_mockup( $field ); ?>
                </div>
            </a>
            <div class="spf-preview-field-actions">
                <a href="#" class="spf-preview-field-duplicate" data-field-id="<?php echo esc_attr( $field['id'] ); ?>" title="<?php esc_attr_e( 'Duplicate', 'smart-programme-finder' ); ?>"><span class="dashicons dashicons-admin-page"></span></a>
                <a href="#" class="spf-preview-field-delete" data-field-id="<?php echo esc_attr( $field['id'] ); ?>" title="<?php esc_attr_e( 'Delete', 'smart-programme-finder' ); ?>">&times;</a>
            </div>
        </div>
        <?php
    }
}

