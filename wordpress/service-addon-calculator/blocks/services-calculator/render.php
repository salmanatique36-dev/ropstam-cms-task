<?php
/**
 * Server-side render for the Services Calculator block.
 *
 * Variables available from core: $attributes, $content, $block.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core. ?>>
	<p><?php esc_html_e( 'Services Calculator block is registered.', 'service-addon-calculator' ); ?></p>
</div>