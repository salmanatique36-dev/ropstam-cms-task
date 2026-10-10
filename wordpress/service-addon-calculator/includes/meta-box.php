<?php
/**
 * Admin meta box for editing Service details.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the Service Details meta box to the sac_service edit screen.
 */
function sac_add_meta_box() {
	add_meta_box(
		'sac_service_details',
		__( 'Service Details', 'service-addon-calculator' ),
		'sac_render_meta_box',
		'sac_service',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_sac_service', 'sac_add_meta_box' );

/**
 * Render the meta box fields.
 *
 * @param WP_Post $post Current post object.
 */
function sac_render_meta_box( $post ) {
	$base_price    = get_post_meta( $post->ID, 'base_price', true );
	$delivery_days = get_post_meta( $post->ID, 'delivery_time_days', true );

	wp_nonce_field( 'sac_save_service_meta', 'sac_service_meta_nonce' );
	?>
	<p>
		<label for="sac_base_price"><?php esc_html_e( 'Base price (USD)', 'service-addon-calculator' ); ?></label>
		<input type="number" id="sac_base_price" name="sac_base_price" class="widefat" min="0" step="0.01" value="<?php echo esc_attr( $base_price ); ?>">
	</p>
	<p>
		<label for="sac_delivery_time_days"><?php esc_html_e( 'Delivery time (days)', 'service-addon-calculator' ); ?></label>
		<input type="number" id="sac_delivery_time_days" name="sac_delivery_time_days" class="widefat" min="1" step="1" value="<?php echo esc_attr( $delivery_days ); ?>">
	</p>
	<?php
}

/**
 * Save meta box values securely.
 *
 * @param int $post_id Post ID being saved.
 */
function sac_save_meta_box( $post_id ) {
	// 1. Verify the nonce (CSRF protection).
	if ( ! isset( $_POST['sac_service_meta_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sac_service_meta_nonce'] ) ), 'sac_save_service_meta' ) ) {
		return;
	}

	// 2. Skip autosaves, the user has not clicked Save yet.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// 3. Check the user is allowed to edit this post.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['sac_base_price'] ) ) {
		update_post_meta( $post_id, 'base_price', sac_sanitize_price( wp_unslash( $_POST['sac_base_price'] ) ) );
	}

	if ( isset( $_POST['sac_delivery_time_days'] ) ) {
		update_post_meta( $post_id, 'delivery_time_days', absint( wp_unslash( $_POST['sac_delivery_time_days'] ) ) );
	}
}
add_action( 'save_post_sac_service', 'sac_save_meta_box' );