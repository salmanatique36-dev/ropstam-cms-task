<?php
/**
 * Registers Service meta fields and their sanitization.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sanitize a price value: numeric, non-negative, rounded to 2 decimals.
 *
 * @param mixed $value Raw value.
 * @return float
 */
function sac_sanitize_price( $value ) {
	$value = is_numeric( $value ) ? (float) $value : 0.0;

	return round( max( 0.0, $value ), 2 );
}

/**
 * Only users who can edit the service may change its meta through the REST API.
 *
 * @param bool   $allowed  Whether the user can edit the meta.
 * @param string $meta_key Meta key.
 * @param int    $post_id  Post ID.
 * @return bool
 */
function sac_can_edit_service_meta( $allowed, $meta_key, $post_id ) {
	return current_user_can( 'edit_post', $post_id );
}

/**
 * Register base_price and delivery_time_days for the sac_service post type.
 */
function sac_register_meta() {
	register_post_meta(
		'sac_service',
		'base_price',
		array(
			'type'              => 'number',
			'description'       => __( 'Base price of the service in USD.', 'service-addon-calculator' ),
			'single'            => true,
			'default'           => 0,
			'sanitize_callback' => 'sac_sanitize_price',
			'auth_callback'     => 'sac_can_edit_service_meta',
			'show_in_rest'      => true,
		)
	);

	register_post_meta(
		'sac_service',
		'delivery_time_days',
		array(
			'type'              => 'integer',
			'description'       => __( 'Estimated delivery time in days.', 'service-addon-calculator' ),
			'single'            => true,
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'auth_callback'     => 'sac_can_edit_service_meta',
			'show_in_rest'      => true,
		)
	);
}
add_action( 'init', 'sac_register_meta' );