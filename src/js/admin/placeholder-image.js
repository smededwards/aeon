import '../../scss/admin/_placeholder-image.scss';

/**
 * Set up the accessible default featured image selector.
 *
 * @return {void}
 */
( function () {
	'use strict';

	let picker;

	/**
	 * Open the Media Library and apply the selected image to the setting.
	 *
	 * @param {MouseEvent} event Button click event.
	 * @return {void}
	 */
	function choosePlaceholderImage( event ) {
		event.preventDefault();

		if ( picker ) {
			picker.open();
			return;
		}

		picker = window.wp.media( {
			button: {
				text: window.aeonPlaceholderImage.button,
			},
			library: {
				type: 'image',
			},
			title: window.aeonPlaceholderImage.title,
			multiple: false,
		} );

		picker.on( 'select', setPlaceholderImage );
		picker.open();
	}

	/**
	 * Update the setting, preview, and screen reader status with the selection.
	 *
	 * @return {void}
	 */
	function setPlaceholderImage() {
		const image = picker.state().get( 'selection' ).first().toJSON();
		const input = document.getElementById( 'aeon-placeholder-image-id' );
		const preview = document.getElementById(
			'aeon-placeholder-image-preview'
		);
		const clearButton = document.querySelector(
			'.aeon-placeholder-image__clear'
		);
		const status = document.querySelector(
			'.aeon-placeholder-image__status'
		);

		input.value = image.id;
		preview.src = image.sizes?.medium?.url || image.url;
		preview.hidden = false;
		clearButton.hidden = false;
		status.textContent = window.aeonPlaceholderImage.selected;
	}

	/**
	 * Clear the image setting and return keyboard focus to the picker button.
	 *
	 * @param {MouseEvent} event Button click event.
	 * @return {void}
	 */
	function clearPlaceholderImage( event ) {
		event.preventDefault();

		const input = document.getElementById( 'aeon-placeholder-image-id' );
		const preview = document.getElementById(
			'aeon-placeholder-image-preview'
		);
		const chooseButton = document.querySelector(
			'.aeon-placeholder-image__choose'
		);
		const clearButton = document.querySelector(
			'.aeon-placeholder-image__clear'
		);
		const status = document.querySelector(
			'.aeon-placeholder-image__status'
		);

		input.value = '0';
		preview.removeAttribute( 'src' );
		preview.hidden = true;
		clearButton.hidden = true;
		status.textContent = window.aeonPlaceholderImage.removed;
		chooseButton.focus();
	}

	/**
	 * Attach controls when the settings page has the Media Library available.
	 *
	 * @return {void}
	 */
	function initialisePlaceholderImagePicker() {
		const chooseButton = document.querySelector(
			'.aeon-placeholder-image__choose'
		);
		const clearButton = document.querySelector(
			'.aeon-placeholder-image__clear'
		);

		if ( ! chooseButton || ! clearButton || ! window.wp?.media ) {
			return;
		}

		chooseButton.addEventListener( 'click', choosePlaceholderImage );
		clearButton.addEventListener( 'click', clearPlaceholderImage );
	}

	initialisePlaceholderImagePicker();
} )();
