<?php
/**
 * AJAX handler for frontend form submissions.
 *
 * @package SmartProgrammeFinder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPF_Ajax {

    /**
     * Field types whose submitted values must be one of the stored options.
     *
     * @var string[]
     */
    private const OPTION_TYPES = array( 'select', 'radio', 'checkbox' );

    public function __construct() {
        add_action( 'wp_ajax_spf_submit_form', array( $this, 'handle_submission' ) );
        add_action( 'wp_ajax_nopriv_spf_submit_form', array( $this, 'handle_submission' ) );
    }

    /**
     * Process an AJAX form submission.
     */
    public function handle_submission(): void {
        // Extract form ID first so the nonce can be form-bound (prevents cross-form probing).
        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

        // Nonce check — action is bound to the specific form ID.
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'spf_submit_nonce_' . $form_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed. Please refresh the page and try again.', 'smart-programme-finder' ),
            ), 403 );
        }

        // Rate limiting: max 10 submissions per 60 seconds per IP.
        $ip          = $this->get_user_ip();
        $rate_key    = 'spf_rate_' . md5( $ip );
        $submissions = (int) get_transient( $rate_key );
        if ( $submissions >= 10 ) {
            wp_send_json_error( array(
                'message' => __( 'Too many submissions. Please wait a moment and try again.', 'smart-programme-finder' ),
            ), 429 );
        }
        set_transient( $rate_key, $submissions + 1, 60 );

        // Retrieve fields for this specific form
        $all_fields = get_option( 'spf_fields', array() );
        $fields     = array_values( array_filter( $all_fields, function ( $f ) use ( $form_id ) {
            return (int) ( $f['form_id'] ?? 0 ) === $form_id;
        } ) );

        if ( empty( $fields ) ) {
            wp_send_json_error( array(
                'message' => __( 'This form is not configured yet. Please contact the site administrator.', 'smart-programme-finder' ),
            ) );
        }

        // Build sanitized form data and validate
        $form_data      = array();
        $missing_fields = array();

        foreach ( $fields as $field ) {
            $key  = $field['field_key'];
            $type = $field['type'] ?? 'text';

            // Checkbox fields submit as arrays (field_key[])
            if ( 'checkbox' === $type ) {
                $raw = isset( $_POST[ $key ] ) ? (array) $_POST[ $key ] : array();
                $value = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $raw ) );

                // Validate each value against allowed options
                if ( ! empty( $field['options'] ) && ! empty( $value ) ) {
                    $allowed = array_map( 'mb_strtolower', array_map( 'trim', $field['options'] ) );
                    $value   = array_filter( $value, function ( $v ) use ( $allowed ) {
                        return in_array( mb_strtolower( trim( $v ) ), $allowed, true );
                    } );
                    $value = array_values( $value );
                }

                if ( ! empty( $field['required'] ) && empty( $value ) ) {
                    $missing_fields[] = $field['label'];
                }

                $form_data[ $key ] = $value;
                continue;
            }

            // All other types — single value
            $value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';

            // Validate against allowed options for select / radio
            if ( in_array( $type, array( 'select', 'radio' ), true ) && ! empty( $field['options'] ) && '' !== $value ) {
                $allowed = array_map( 'mb_strtolower', array_map( 'trim', $field['options'] ) );
                if ( ! in_array( mb_strtolower( trim( $value ) ), $allowed, true ) ) {
                    $value = '';
                }
            }

            // Email format validation
            if ( 'email' === $type && '' !== $value && ! is_email( $value ) ) {
                $value = '';
                $missing_fields[] = $field['label'] . ' (' . __( 'invalid email', 'smart-programme-finder' ) . ')';
                $form_data[ $key ] = $value;
                continue;
            }

            if ( ! empty( $field['required'] ) && '' === $value ) {
                $missing_fields[] = $field['label'];
            }

            $form_data[ $key ] = $value;
        }

        if ( ! empty( $missing_fields ) ) {
            wp_send_json_error( array(
                'message' => sprintf(
                    __( 'Please complete the following fields: %s', 'smart-programme-finder' ),
                    implode( ', ', $missing_fields )
                ),
            ) );
        }

        // Evaluate rules for this form
        $engine = new SPF_Rules_Engine( $form_id );
        $result = $engine->match_rules( $form_data );

        // Store every submission — no cap. Entries persist until an admin deletes them.
        $entries_store   = SPF_Entries_Store::instance();
        $stored_entry_id = $entries_store->add_entry( array(
            'form_id'    => $form_id,
            'fields'     => $form_data,
            'result'     => $result['message'],
            'matched'    => $result['matched'],
            'created_at' => current_time( 'mysql' ),
            'ip'         => $ip,
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
        ) );

        if ( $stored_entry_id <= 0 ) {
            wp_send_json_error( array(
                'message' => __( 'We could not save your submission right now. Please try again later.', 'smart-programme-finder' ),
            ), 500 );
        }

        wp_send_json_success( array(
            'message'           => wp_kses_post( $result['message'] ),
            'matched'           => $result['matched'],
            'fallback'          => ! $result['matched'],
            'confirmation_type' => $result['confirmation_type'] ?? 'popup',
        ) );
    }

    /**
     * Get user IP address safely.
     *
     * Uses REMOTE_ADDR by default (cannot be spoofed).
     * Only checks proxy headers when the spf_trust_proxy_headers filter returns true.
     */
    private function get_user_ip(): string {
        /**
         * Filter to enable trusting proxy headers (X-Forwarded-For).
         * Only enable if site runs behind a trusted reverse proxy (Cloudflare, load balancer).
         *
         * @param bool $trust Whether to trust proxy headers. Default false.
         */
        $trust_proxy = (bool) apply_filters( 'spf_trust_proxy_headers', false );

        if ( $trust_proxy && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            $ip  = trim( $ips[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }

        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
    }
}
