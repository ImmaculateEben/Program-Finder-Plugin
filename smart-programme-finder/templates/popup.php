<?php
/**
 * Result popup / modal template.
 *
 * Available variables:
 *   $form_id — integer form identifier.
 *
 * @package SmartProgrammeFinder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="spf-modal-overlay" id="spf-modal-<?php echo esc_attr( $form_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="spf-modal-title-<?php echo esc_attr( $form_id ); ?>" hidden>
    <div class="spf-modal">
        <button type="button" class="spf-modal-close" aria-label="<?php esc_attr_e( 'Close', 'smart-programme-finder' ); ?>">&times;</button>

        <div class="spf-modal-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>

        <h2 class="spf-modal-title" id="spf-modal-title-<?php echo esc_attr( $form_id ); ?>">
            <?php esc_html_e( 'Your Recommendation', 'smart-programme-finder' ); ?>
        </h2>

        <div class="spf-modal-body">
            <!-- Populated dynamically via JS -->
        </div>

        <div class="spf-modal-actions">
            <button type="button" class="spf-modal-btn spf-modal-btn--reset">
                <?php esc_html_e( 'Try Again', 'smart-programme-finder' ); ?>
            </button>
        </div>
    </div>
</div>
