<?php
/**
 * Registers front-end and editor assets.
 *
 * Assets are only registered here. They are enqueued on demand
 * by the block (via block.json handles) or by the shortcode,
 * so pages without the calculator load nothing from this plugin.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return a cache-busting version for an asset file.
 *
 * Uses the file's last modified time so browsers fetch the new file
 * after every change. Falls back to the plugin version.
 *
 * @param string $relative_path Path relative to the plugin root.
 * @return string
 */
function sac_asset_version( $relative_path ) {
	$file = SAC_PATH . $relative_path;

	return file_exists( $file ) ? (string) filemtime( $file ) : SAC_VERSION;
}

/**
 * Register scripts and styles.
 */
function sac_register_assets() {
	// Front-end calculator: vanilla JS, no dependencies on purpose.
	wp_register_script(
		'sac-calculator',
		SAC_URL . 'assets/js/calculator.js',
		array(),
		sac_asset_version( 'assets/js/calculator.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	// Block editor script: depends on core block editor packages.
	wp_register_script(
		'sac-block-editor',
		SAC_URL . 'assets/js/block-editor.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render' ),
		sac_asset_version( 'assets/js/block-editor.js' ),
		array( 'in_footer' => false )
	);

	// Shared styles for the front end and the editor preview.
	wp_register_style(
		'sac-calculator',
		SAC_URL . 'assets/css/calculator.css',
		array(),
		sac_asset_version( 'assets/css/calculator.css' )
	);
}
add_action( 'init', 'sac_register_assets' );