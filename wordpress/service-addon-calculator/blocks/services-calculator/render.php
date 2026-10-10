<?php
/**
 * Server-side render for the Services Calculator block.
 *
 * Variables available from core: $attributes, $content, $block.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

echo sac_render_services_calculator( get_block_wrapper_attributes( array( 'class' => 'sac-calculator' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every dynamic value is escaped inside the function.