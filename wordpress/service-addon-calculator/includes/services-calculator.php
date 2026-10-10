<?php
/**
 * Data helpers and markup for the Services Calculator.
 *
 * Shared by the block (render.php) and the shortcode,
 * so the markup lives in one place.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get the 3 most recently updated published services.
 *
 * @return WP_Post[]
 */
function sac_get_recent_services() {
	$query = new WP_Query(
		array(
			'post_type'              => 'sac_service',
			'post_status'            => 'publish',
			'posts_per_page'         => 3,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	return $query->posts;
}

/**
 * Get the optional add-ons offered for a service.
 *
 * The list can be changed with the `sac_service_addons` filter, globally
 * or per service. Items are sanitized after the filter runs, since
 * third-party code may return unexpected data.
 *
 * @param int $service_id Service post ID.
 * @return array[] Add-ons with id, label and price keys.
 */
function sac_get_service_addons( $service_id ) {
	$defaults = array(
		array(
			'id'    => 'priority',
			'label' => __( 'Priority Turnaround', 'service-addon-calculator' ),
			'price' => 50,
		),
		array(
			'id'    => 'source-files',
			'label' => __( 'Source Files', 'service-addon-calculator' ),
			'price' => 30,
		),
		array(
			'id'    => 'extra-revision',
			'label' => __( 'Extra Revision Round', 'service-addon-calculator' ),
			'price' => 25,
		),
	);

	$addons = apply_filters( 'sac_service_addons', $defaults, $service_id );

	if ( ! is_array( $addons ) ) {
		return array();
	}

	$clean = array();

	foreach ( $addons as $addon ) {
		if ( ! is_array( $addon ) || empty( $addon['id'] ) || empty( $addon['label'] ) || ! isset( $addon['price'] ) ) {
			continue;
		}

		$clean[] = array(
			'id'    => sanitize_key( $addon['id'] ),
			'label' => sanitize_text_field( $addon['label'] ),
			'price' => sac_sanitize_price( $addon['price'] ),
		);
	}

	return $clean;
}

/**
 * Format an amount as a USD price, e.g. $1,250.00.
 *
 * @param float $amount Amount.
 * @return string
 */
function sac_format_price( $amount ) {
	return '$' . number_format( (float) $amount, 2 );
}

/**
 * Build the full calculator markup.
 *
 * @param string $wrapper_attributes Pre-escaped attributes for the wrapper element.
 * @return string
 */
function sac_render_services_calculator( $wrapper_attributes = '' ) {
	$services   = sac_get_recent_services();
	$heading_id = wp_unique_id( 'sac-heading-' );

	ob_start();
	?>
	<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the caller. ?> aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" data-sac-currency="USD" data-sac-locale="en-US">
		<h2 id="<?php echo esc_attr( $heading_id ); ?>" class="sac-heading">
			<?php esc_html_e( 'Recently Updated Services', 'service-addon-calculator' ); ?>
		</h2>

		<?php if ( empty( $services ) ) : ?>
			<p class="sac-empty"><?php esc_html_e( 'No services are available yet. Please check back soon.', 'service-addon-calculator' ); ?></p>
		<?php else : ?>
			<div class="sac-services">
				<?php
				foreach ( $services as $service ) {
					sac_render_service_card( $service );
				}
				?>
			</div>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Output a single service card.
 *
 * @param WP_Post $service Service post.
 */

function sac_render_service_card( $service ) {
	$base_price    = sac_sanitize_price( get_post_meta( $service->ID, 'base_price', true ) );
	$delivery_days = absint( get_post_meta( $service->ID, 'delivery_time_days', true ) );
	$addons        = $base_price > 0 ? sac_get_service_addons( $service->ID ) : array();
	$excerpt       = wp_trim_words( get_the_excerpt( $service ), 20 );
	$modified_gmt  = (int) get_post_modified_time( 'U', true, $service );
	$card_id       = wp_unique_id( 'sac-service-' );
	$total_id      = $card_id . '-total';

	$input_ids = array();
	foreach ( $addons as $addon ) {
		$input_ids[] = $card_id . '-' . $addon['id'];
	}
	?>
	<article class="sac-card" data-sac-card data-sac-base="<?php echo esc_attr( $base_price ); ?>" aria-labelledby="<?php echo esc_attr( $card_id . '-title' ); ?>">
		<header class="sac-card__header">
			<h3 id="<?php echo esc_attr( $card_id . '-title' ); ?>" class="sac-card__title">
				<a href="<?php echo esc_url( get_permalink( $service ) ); ?>"><?php echo esc_html( get_the_title( $service ) ); ?></a>
			</h3>
			<time class="sac-card__updated" datetime="<?php echo esc_attr( gmdate( 'c', $modified_gmt ) ); ?>">
				<?php
				/* translators: %s: human-readable time difference, e.g. "2 days". */
				echo esc_html( sprintf( __( 'Updated %s ago', 'service-addon-calculator' ), human_time_diff( $modified_gmt ) ) );
				?>
			</time>
		</header>

		<?php if ( $excerpt ) : ?>
			<p class="sac-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
		<?php endif; ?>

		<dl class="sac-card__meta">
			<div class="sac-card__meta-item">
				<dt><?php esc_html_e( 'Base price', 'service-addon-calculator' ); ?></dt>
				<dd>
					<?php
					echo $base_price > 0
						? esc_html( sac_format_price( $base_price ) )
						: esc_html__( 'On request', 'service-addon-calculator' );
					?>
				</dd>
			</div>
			<div class="sac-card__meta-item">
				<dt><?php esc_html_e( 'Delivery', 'service-addon-calculator' ); ?></dt>
				<dd>
					<?php
					echo $delivery_days > 0
						/* translators: %d: number of days. */
						? esc_html( sprintf( _n( '%d day', '%d days', $delivery_days, 'service-addon-calculator' ), $delivery_days ) )
						: esc_html__( 'To be confirmed', 'service-addon-calculator' );
					?>
				</dd>
			</div>
		</dl>

		<?php if ( ! empty( $addons ) ) : ?>
			<fieldset class="sac-addons">
				<legend class="sac-addons__legend"><?php esc_html_e( 'Optional add-ons', 'service-addon-calculator' ); ?></legend>

				<?php foreach ( $addons as $index => $addon ) : ?>
					<label class="sac-addon" for="<?php echo esc_attr( $input_ids[ $index ] ); ?>">
						<span class="sac-addon__text">
							<span class="sac-addon__name"><?php echo esc_html( $addon['label'] ); ?></span>
							<span class="sac-addon__price">+<?php echo esc_html( sac_format_price( $addon['price'] ) ); ?></span>
						</span>
						<input
							type="checkbox"
							role="switch"
							id="<?php echo esc_attr( $input_ids[ $index ] ); ?>"
							class="sac-addon__input"
							value="<?php echo esc_attr( $addon['id'] ); ?>"
							data-sac-price="<?php echo esc_attr( $addon['price'] ); ?>"
						>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<p class="sac-card__total">
				<span id="<?php echo esc_attr( $total_id . '-label' ); ?>" class="sac-card__total-label"><?php esc_html_e( 'Estimated total', 'service-addon-calculator' ); ?></span>
				<output
					id="<?php echo esc_attr( $total_id ); ?>"
					class="sac-card__amount"
					for="<?php echo esc_attr( implode( ' ', $input_ids ) ); ?>"
					aria-labelledby="<?php echo esc_attr( $total_id . '-label' ); ?>"
					aria-live="polite"
					data-sac-total
				><?php echo esc_html( sac_format_price( $base_price ) ); ?></output>
			</p>
		<?php endif; ?>
	</article>
	<?php
}