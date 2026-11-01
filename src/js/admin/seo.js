import '../../scss/admin/_seo.scss';

/**
 * Handle social image selection and removal in the post editor.
 *
 * @param {MouseEvent} event Document click event.
 * @return {void}
 */
function handleSeoImageAction( event ) {
	if ( ! ( event.target instanceof window.Element ) ) {
		return;
	}

	const choose = event.target.closest( '.aeon-seo__choose' );
	const remove = event.target.closest( '.aeon-seo__remove' );
	if ( ! choose && ! remove ) {
		return;
	}

	const box = event.target.closest( '.aeon-seo' );
	if ( ! box ) {
		return;
	}

	const input = box.querySelector( '#aeon-social-image' );
	const preview = box.querySelector( '#aeon-social-preview' );
	const reset = box.querySelector( '.aeon-seo__remove' );
	const status = box.querySelector( '.aeon-seo__status' );
	const messages = box.dataset;

	if ( remove ) {
		input.value = '0';
		preview.hidden = true;
		preview.removeAttribute( 'src' );
		reset.hidden = true;
		status.textContent = messages.imageRemoved;
		box.querySelector( '.aeon-seo__choose' ).focus();
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		return;
	}

	const picker = window.wp.media( {
		title: messages.imageTitle,
		button: { text: messages.imageButton },
		library: { type: 'image' },
		multiple: false,
	} );
	picker.on( 'select', () =>
		updateSocialImage( picker, input, preview, reset, status, messages )
	);
	picker.open();
}

/**
 * Update the selected attachment preview and announce the result.
 *
 * @param {Object}            picker   WordPress media frame.
 * @param {HTMLInputElement}  input    Social attachment input.
 * @param {HTMLImageElement}  preview  Social attachment preview.
 * @param {HTMLButtonElement} reset    Clear image button.
 * @param {HTMLElement}       status   Live status message.
 * @param {DOMStringMap}      messages Localised image picker messages.
 * @return {void}
 */
function updateSocialImage( picker, input, preview, reset, status, messages ) {
	const image = picker.state().get( 'selection' ).first().toJSON();
	input.value = String( image.id );
	preview.src = image.sizes?.medium?.url || image.url;
	preview.hidden = false;
	reset.hidden = false;
	status.textContent = messages.imageSelected;
	input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
}

document.addEventListener( 'click', handleSeoImageAction );
