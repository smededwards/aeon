// Internal dependencies.
import '../scss/frontend/admin-bar-scroll.scss';

const adminBar = document.getElementById( 'wpadminbar' );

if ( adminBar ) {
	const root = document.documentElement;
	const threshold = 24;
	let direction = 'up';
	let distance = 0;
	let previousScrollY = window.scrollY;

	root.classList.add( 'aeon-admin-bar' );

	/**
	 * Show the toolbar and reset the downward scroll threshold.
	 *
	 * @return {void}
	 */
	const showAdminBar = () => {
		root.classList.remove( 'aeon-admin-bar--hidden' );
		distance = 0;
	};

	/**
	 * Hide the toolbar after a short downward scroll.
	 *
	 * @return {void}
	 */
	const hideAdminBar = () => {
		root.classList.add( 'aeon-admin-bar--hidden' );
	};

	/**
	 * Track scroll direction and toggle the toolbar at the relevant threshold.
	 *
	 * @return {void}
	 */
	const handleScroll = () => {
		const currentScrollY = Math.max( 0, window.scrollY );
		const delta = currentScrollY - previousScrollY;

		if ( currentScrollY === 0 || delta < 0 ) {
			direction = 'up';
			showAdminBar();
		} else if ( delta > 0 ) {
			if ( 'down' !== direction ) {
				direction = 'down';
				distance = 0;
			}
			distance += delta;
			if ( distance >= threshold ) {
				hideAdminBar();
			}
		}

		previousScrollY = currentScrollY;
	};

	window.addEventListener( 'scroll', handleScroll, { passive: true } );
	adminBar.addEventListener( 'focusin', showAdminBar );
}
