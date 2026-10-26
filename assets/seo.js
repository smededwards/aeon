/* global aeonSeo */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		const choose = event.target.closest( '.aeon-seo__choose' );
		const remove = event.target.closest( '.aeon-seo__remove' );
		if ( ! choose && ! remove ) {
			return;
		}
		const box = event.target.closest( '.aeon-seo' );
		const input = box.querySelector( '#aeon-social-image' );
		const preview = box.querySelector( '#aeon-social-preview' );
		const reset = box.querySelector( '.aeon-seo__remove' );
		if ( remove ) {
			input.value = '0';
			preview.hidden = true;
			preview.removeAttribute( 'src' );
			reset.hidden = true;
			box.querySelector( '.aeon-seo__status' ).textContent =
				aeonSeo.removed;
			box.querySelector( '.aeon-seo__choose' ).focus();
		} else {
			const picker = wp.media( {
				title: aeonSeo.title,
				button: { text: aeonSeo.button },
				library: { type: 'image' },
				multiple: false,
			} );
			picker.on( 'select', function () {
				const image = picker
					.state()
					.get( 'selection' )
					.first()
					.toJSON();
				input.value = String( image.id );
				preview.src = image.sizes?.medium?.url || image.url;
				preview.hidden = false;
				reset.hidden = false;
				box.querySelector( '.aeon-seo__status' ).textContent =
					aeonSeo.selected;
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
			picker.open();
			return;
		}
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	} );
} )();
