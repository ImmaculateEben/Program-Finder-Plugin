<?php
/**
 * Shortcode [spf_form id="1"]
 *
 * Renders the frontend recommendation form.
 *
 * @package SmartProgrammeFinder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPF_Shortcode {

    public function __construct() {
        add_shortcode( 'spf_form', array( $this, 'render' ) );
    }

    /**
     * Shortcode callback.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render( $atts ): string {
        $atts = shortcode_atts( array(
            'id' => '1',
        ), $atts, 'spf_form' );

        $form_id = absint( $atts['id'] );

        // Enqueue frontend assets
        wp_enqueue_style( 'spf-frontend' );
        wp_enqueue_script( 'spf-frontend' );

        // Load per-form appearance settings.
        $form_settings = SPF_Admin::get_form_settings( $form_id );

        // Capture template output
        ob_start();
        $this->load_template( 'form', array(
            'form_id'       => $form_id,
            'form_settings' => $form_settings,
        ) );
        $this->load_template( 'popup', array( 'form_id' => $form_id ) );
        return ob_get_clean();
    }

    /**
     * Load a template file from the templates directory.
     *
     * Allows themes to override templates by placing them in
     * smart-programme-finder/ within the active theme directory.
     *
     * @param string $template_name Template name without extension.
     * @param array  $args          Variables available inside the template.
     */
    private function load_template( string $template_name, array $args = array() ): void {
        // Allow theme override
        $theme_file = locate_template( 'smart-programme-finder/' . $template_name . '.php' );

        if ( $theme_file ) {
            $template_path = $theme_file;
        } else {
            $template_path = SPF_PLUGIN_DIR . 'templates/' . $template_name . '.php';
        }

        if ( ! file_exists( $template_path ) ) {
            return;
        }

        // Extract args so they are available as local variables in the template.
        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled array from plugin internals.
        extract( $args );
        include $template_path;
    }
}
