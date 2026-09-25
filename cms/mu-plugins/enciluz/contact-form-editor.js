( function ( wp ) {
	var el = wp.element.createElement;
	wp.blocks.registerBlockType( 'enciluz/contact-form', {
		apiVersion: 3,
		edit: function () {
			return el( 'div', wp.blockEditor.useBlockProps(),
				el( wp.serverSideRender, { block: 'enciluz/contact-form' } )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp );
