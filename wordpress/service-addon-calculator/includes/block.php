<?php
/**
 * Registers the Services Calculator block.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the block from its block.json metadata.
 *
 * Runs at priority 20 so the asset handles registered in
 * sac_register_assets() (priority 10) already exist.
 */
function sac_register_block() {
	register_block_type( SAC_PATH . 'blocks/services-calculator' );
}
add_action( 'init', 'sac_register_block', 20 );