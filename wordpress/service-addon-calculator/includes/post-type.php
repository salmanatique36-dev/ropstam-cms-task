<?php
/**
 * Registers the Services custom post type.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the sac_service post type.
 */
function sac_register_post_type() {
	$labels = array(
		'name'               => _x( 'Services', 'post type general name', 'service-addon-calculator' ),
		'singular_name'      => _x( 'Service', 'post type singular name', 'service-addon-calculator' ),
		'menu_name'          => __( 'Services', 'service-addon-calculator' ),
		'all_items'          => __( 'All Services', 'service-addon-calculator' ),
		'add_new_item'       => __( 'Add New Service', 'service-addon-calculator' ),
		'edit_item'          => __( 'Edit Service', 'service-addon-calculator' ),
		'new_item'           => __( 'New Service', 'service-addon-calculator' ),
		'view_item'          => __( 'View Service', 'service-addon-calculator' ),
		'search_items'       => __( 'Search Services', 'service-addon-calculator' ),
		'not_found'          => __( 'No services found.', 'service-addon-calculator' ),
		'not_found_in_trash' => __( 'No services found in Trash.', 'service-addon-calculator' ),
	);

	register_post_type(
		'sac_service',
		array(
			'labels'        => $labels,
			'public'        => true,
			'has_archive'   => true,
			'rewrite'       => array( 'slug' => 'services' ),
			'menu_icon'     => 'dashicons-hammer',
			'menu_position' => 20,
			'show_in_rest'  => true,
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
		)
	);
}
add_action( 'init', 'sac_register_post_type' );