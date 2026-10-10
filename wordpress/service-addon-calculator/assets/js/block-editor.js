/**
 * Registers the Services Calculator block in the editor.
 *
 * Plain JS using WordPress globals, so no build step is needed.
 * The block is dynamic: markup comes from render.php and the editor
 * shows a live preview through ServerSideRender.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el } = wp.element;
	const { useBlockProps } = wp.blockEditor;
	const ServerSideRender = wp.serverSideRender;

	registerBlockType( 'sac/services-calculator', {
		edit: function Edit() {
			return el(
				'div',
				useBlockProps(),
				el( ServerSideRender, { block: 'sac/services-calculator' } )
			);
		},

		// Dynamic block: nothing is saved in post content.
		save: function () {
			return null;
		},
	} );
} )( window.wp );