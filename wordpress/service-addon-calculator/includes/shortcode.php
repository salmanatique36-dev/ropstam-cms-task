<?php
/**
 * [sac_services_calculator] shortcode.
 *
 * Fallback for the Classic Editor, page builders and widgets.
 * Renders exactly the same markup as the block.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the shortcode.
 *
 * Assets are enqueued here as a safety net (widgets, page builders),
 * since this callback only runs when the shortcode is actually used.
 *
 * @return string
 */
function sac_services_calculator_shortcode() {
	wp_enqueue_style( 'sac-calculator' );
	wp_enqueue_script( 'sac-calculator' );

	// Static, trusted attribute string: no user input involved.
	return sac_render_services_calculator( 'class="sac-calculator"' );
}
add_shortcode( 'sac_services_calculator', 'sac_services_calculator_shortcode' );

/**
 * Enqueue the stylesheet in <head> when the current post contains the shortcode.
 *
 * Styles enqueued from inside a shortcode are printed in the footer,
 * which causes a flash of unstyled content. Detecting the shortcode
 * early loads the CSS in the head instead.
 */
function sac_enqueue_for_shortcode() {
	$post = get_post();

	if ( is_singular() && $post && has_shortcode( $post->post_content, 'sac_services_calculator' ) ) {
		wp_enqueue_style( 'sac-calculator' );
	}
}
add_action( 'wp_enqueue_scripts', 'sac_enqueue_for_shortcode' );