<?php
/**
 * Plugin Name:       Service Add-on Calculator
 * Description:       Registers a Services post type and a block that lists the 3 most recently updated services with a live add-on price calculator.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Muhammad Salman Atique
 * License:           GPL-2.0-or-later
 * Text Domain:       service-addon-calculator
 *
 * @package ServiceAddonCalculator
 */

// Block direct access to this file.
defined( 'ABSPATH' ) || exit;

define( 'SAC_VERSION', '1.0.0' );
define( 'SAC_FILE', __FILE__ );
define( 'SAC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SAC_URL', plugin_dir_url( __FILE__ ) );

require_once SAC_PATH . 'includes/post-type.php';
require_once SAC_PATH . 'includes/meta.php';
require_once SAC_PATH . 'includes/assets.php';
require_once SAC_PATH . 'includes/block.php';

if ( is_admin() ) {
	require_once SAC_PATH . 'includes/meta-box.php';
}
/**
 * Activation: register the CPT first, then flush rewrite rules
 * so /services/ URLs work immediately without a 404.
 */
function sac_activate() {
	sac_register_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'sac_activate' );

/**
 * Deactivation: remove the CPT and clean its rewrite rules.
 */
function sac_deactivate() {
	unregister_post_type( 'sac_service' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'sac_deactivate' );