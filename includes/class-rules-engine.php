<?php
/**
 * Rule evaluation engine.
 *
 * Evaluates confirmations (new format) and legacy rules for a specific
 * form against submitted data. Returns the first matching result or the
 * configured fallback.
 *
 * Confirmation evaluation:
 *   – Confirmations are checked in stored order (first match wins).
 *   – conditional_logic = false → always match (use as catch-all).
 *   – conditional_logic = true, logic_type = 'use' → ALL conditions
 *     must pass to return this confirmation's message.
 *   – conditional_logic = true, logic_type = 'dont_use' → if ALL
 *     conditions pass, skip this confirmation; otherwise return it.
 *
 * @package SmartProgrammeFinder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPF_Rules_Engine {

    /**
     * Confirmations for this form (new format).
     *
     * @var array
     */
    private array $confirmations;

    /**
     * Legacy rules for this form (old format, sorted by priority).
     *
     * @var array
     */
    private array $rules;

    /**
     * Fallback message when nothing matches.
     *
     * @var string
     */
    private string $fallback;

    /**
     * Field definitions keyed by field_key.
     *
     * @var array<string, array>
     */
    private array $field_map;

    /**
     * @param int $form_id The form whose rules should be evaluated.
     */
    public function __construct( int $form_id = 0 ) {

        /* ── Confirmations (new format) ────── */
        $all_confs = get_option( 'spf_confirmations', array() );
        if ( $form_id > 0 ) {
            $all_confs = array_filter( $all_confs, function ( array $c ) use ( $form_id ): bool {
                return (int) ( $c['form_id'] ?? 0 ) === $form_id;
            } );
        }
        $this->confirmations = array_values( $all_confs );

        /* ── Legacy rules ────────────────────── */
        $all_rules = get_option( 'spf_rules', array() );
        if ( $form_id > 0 ) {
            $all_rules = array_filter( $all_rules, function ( array $rule ) use ( $form_id ): bool {
                return (int) ( $rule['form_id'] ?? 0 ) === $form_id;
            } );
        }
        $this->rules = $this->filter_and_sort( $all_rules );

        /* ── Fallback ────────────────────────── */
        $settings       = get_option( 'spf_settings', array() );
        $this->fallback = $settings['fallback_message']
            ?? __( 'We could not find an exact match. Please contact our admissions team for guidance.', 'smart-programme-finder' );

        /* ── Field map ───────────────────────── */
        $all_fields = get_option( 'spf_fields', array() );
        $this->field_map = array();
        foreach ( $all_fields as $field ) {
            if ( $form_id > 0 && (int) ( $field['form_id'] ?? 0 ) !== $form_id ) {
                continue;
            }
            $this->field_map[ $field['field_key'] ] = $field;
        }
    }

    /* ──────────────────────────────────────────
     * Public API
     * ──────────────────────────────────────── */

    /**
     * Match submitted form data against confirmations, then legacy rules.
     *
     * @param array<string, string|array> $form_data Sanitized key => value pairs.
     *
     * @return array{matched: bool, message: string}
     */
    public function match_rules( array $form_data ): array {

        // 1. Try confirmations (new format) first.
        foreach ( $this->confirmations as $conf ) {
            $result = $this->evaluate_confirmation( $conf, $form_data );
            if ( null !== $result ) {
                return array(
                    'matched'           => true,
                    'message'           => $result,
                    'confirmation_type' => $conf['confirmation_type'] ?? 'popup',
                );
            }
        }

        // 2. Fallback to legacy rules.
        foreach ( $this->rules as $rule ) {
            if ( $this->evaluate_legacy_condition( $rule, $form_data ) ) {
                return array(
                    'matched'           => true,
                    'message'           => $rule['result'],
                    'confirmation_type' => 'popup',
                );
            }
        }

        return array(
            'matched'           => false,
            'message'           => $this->fallback,
            'confirmation_type' => 'popup',
        );
    }

    /* ──────────────────────────────────────────
     * Confirmation evaluation
     * ──────────────────────────────────────── */

    /**
     * @return string|null The message if this confirmation matches, or null.
     */
    private function evaluate_confirmation( array $conf, array $form_data ): ?string {

        // No conditional logic → always return this message.
        if ( empty( $conf['conditional_logic'] ) ) {
            return $conf['message'] ?? '';
        }

        $logic_type = $conf['logic_type'] ?? 'use';
        $conditions = $conf['conditions'] ?? array();

        if ( empty( $conditions ) ) {
            // No conditions but logic enabled — treat as no-match (skip).
            return null;
        }

        $all_pass = true;
        foreach ( $conditions as $cond ) {
            if ( ! $this->evaluate_single_condition( $cond, $form_data ) ) {
                $all_pass = false;
                break;
            }
        }

        if ( 'use' === $logic_type ) {
            return $all_pass ? ( $conf['message'] ?? '' ) : null;
        }

        // 'dont_use' — if ALL conditions pass, SKIP this confirmation.
        if ( 'dont_use' === $logic_type ) {
            return $all_pass ? null : ( $conf['message'] ?? '' );
        }

        return null;
    }

    /**
     * Evaluate a single condition ({field_key, operator, value}).
     */
    private function evaluate_single_condition( array $cond, array $form_data ): bool {
        $field_key = $cond['field_key'] ?? '';
        $operator  = $cond['operator'] ?? 'is';
        $expected  = mb_strtolower( trim( $cond['value'] ?? '' ) );

        if ( ! isset( $form_data[ $field_key ] ) ) {
            return 'is_not' === $operator; // field absent → 'is' fails, 'is_not' succeeds
        }

        $submitted = $form_data[ $field_key ];

        // Normalise submitted value(s).
        if ( is_array( $submitted ) ) {
            $values = array_map( function ( $v ) {
                return mb_strtolower( trim( (string) $v ) );
            }, $submitted );
        } else {
            $values = array( mb_strtolower( trim( (string) $submitted ) ) );
        }

        $found = in_array( $expected, $values, true );

        return 'is' === $operator ? $found : ! $found;
    }

    /* ──────────────────────────────────────────
     * Legacy rule evaluation
     * ──────────────────────────────────────── */

    private function filter_and_sort( array $rules ): array {
        $active = array_filter( $rules, function ( array $rule ): bool {
            return ( $rule['status'] ?? 'active' ) === 'active';
        } );

        usort( $active, function ( array $a, array $b ): int {
            return ( $a['priority'] ?? PHP_INT_MAX ) <=> ( $b['priority'] ?? PHP_INT_MAX );
        } );

        return array_values( $active );
    }

    private function evaluate_legacy_condition( array $rule, array $form_data ): bool {
        $field_key = $rule['field_key'] ?? '';
        $expected  = $rule['value'] ?? '';

        if ( ! isset( $form_data[ $field_key ] ) ) {
            return false;
        }

        $submitted = $form_data[ $field_key ];

        $field_type = $this->field_map[ $field_key ]['type'] ?? 'text';
        if ( 'checkbox' === $field_type && is_array( $submitted ) ) {
            $normalised = array_map( function ( $v ) {
                return mb_strtolower( trim( $v ) );
            }, $submitted );
            return in_array( mb_strtolower( trim( $expected ) ), $normalised, true );
        }

        if ( is_array( $submitted ) ) {
            $submitted = implode( ', ', $submitted );
        }

        return mb_strtolower( trim( (string) $submitted ) ) === mb_strtolower( trim( $expected ) );
    }
}
