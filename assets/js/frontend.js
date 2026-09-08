/**
 * Frontend behavior for the NanoBar floating panel: open/close, submenus,
 * persisted open state, automatic bottom tab-bar mode on phones, and the
 * Query Monitor button proxy.
 *
 * @package NanoBar
 */

( function () {
	'use strict';

	function init() {
		var wrap = document.getElementById( 'nanobar' );
		if ( ! wrap ) {
			return;
		}

		var toggle = wrap.querySelector( '.nanobar__toggle' );
		var groups = wrap.querySelectorAll( '.nanobar__item-group' );
		var STORAGE_KEY = 'nanobar_open';

		function setMenuState( open ) {
			wrap.classList.toggle( 'is-open', open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			try {
				localStorage.setItem( STORAGE_KEY, open ? '1' : '0' );
			} catch ( err ) {
				// Persistence not available: not a blocking error.
			}
		}

		function closeSubmenus( except ) {
			groups.forEach( function ( group ) {
				if ( group === except ) {
					return;
				}
				group.classList.remove( 'is-open' );
				var button = group.querySelector( ':scope > .nanobar__submenu-toggle' );
				var trigger = group.querySelector( ':scope > .nanobar__item--submenu-trigger' );
				var submenu = group.querySelector( ':scope > .nanobar__submenu' );
				if ( button ) {
					button.setAttribute( 'aria-expanded', 'false' );
				}
				if ( trigger ) {
					trigger.setAttribute( 'aria-expanded', 'false' );
				}
				if ( submenu ) {
					submenu.setAttribute( 'aria-hidden', 'true' );
				}
			} );
		}

		function toggleSubmenu( group ) {
			var open = ! group.classList.contains( 'is-open' );
			closeSubmenus( group );
			group.classList.toggle( 'is-open', open );
			var button = group.querySelector( ':scope > .nanobar__submenu-toggle' );
			var trigger = group.querySelector( ':scope > .nanobar__item--submenu-trigger' );
			var submenu = group.querySelector( ':scope > .nanobar__submenu' );
			if ( button ) {
				button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			}
			if ( trigger ) {
				trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			}
			if ( submenu ) {
				submenu.setAttribute( 'aria-hidden', open ? 'false' : 'true' );
			}
		}

		groups.forEach( function ( group ) {
			var button = group.querySelector( ':scope > .nanobar__submenu-toggle' );
			var trigger = group.querySelector( ':scope > .nanobar__item--submenu-trigger' );
			if ( button ) {
				button.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					event.stopPropagation();
					toggleSubmenu( group );
				} );
			}
			if ( trigger ) {
				trigger.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					event.stopPropagation();
					toggleSubmenu( group );
				} );
			}
		} );

		toggle.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
			if ( wrap.classList.contains( 'is-open' ) ) {
				setMenuState( false );
				closeSubmenus();
			} else {
				setMenuState( true );
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! wrap.contains( event.target ) ) {
				setMenuState( false );
				closeSubmenus();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' !== event.key ) {
				return;
			}
			if ( wrap.classList.contains( 'is-open' ) ) {
				setMenuState( false );
				closeSubmenus();
				toggle.focus();
			}
		} );

		try {
			if ( '1' === localStorage.getItem( STORAGE_KEY ) ) {
				setMenuState( true );
			}
		} catch ( err ) {
			// Persistence not available: starts closed.
		}

		// Phones get an always-visible bottom tab bar instead of the toggle +
		// dropdown menu — see the "--tabbar-auto" modifier in the SCSS. The
		// breakpoint matches the plugin's own existing phone-tweaks media
		// query (assets/scss/components/_panel.scss).
		var mobileQuery = window.matchMedia( '(max-width: 600px)' );
		function updateAutoTabbar( mediaQuery ) {
			wrap.classList.toggle( 'nanobar--tabbar-auto', mediaQuery.matches );
		}
		updateAutoTabbar( mobileQuery );
		if ( mobileQuery.addEventListener ) {
			mobileQuery.addEventListener( 'change', updateAutoTabbar );
		} else if ( mobileQuery.addListener ) {
			mobileQuery.addListener( updateAutoTabbar );
		}

		// Below 480px the tab-bar drops its text labels too (icons only) —
		// see the "--tabbar-compact" modifier in the SCSS.
		var compactQuery = window.matchMedia( '(max-width: 480px)' );
		function updateTabbarCompact( mediaQuery ) {
			wrap.classList.toggle( 'nanobar--tabbar-compact', mediaQuery.matches );
		}
		updateTabbarCompact( compactQuery );
		if ( compactQuery.addEventListener ) {
			compactQuery.addEventListener( 'change', updateTabbarCompact );
		} else if ( compactQuery.addListener ) {
			compactQuery.addListener( updateTabbarCompact );
		}

		var qmBtn = document.getElementById( 'nanobar-qm-toggle' );
		if ( qmBtn ) {
			// The Query Monitor button works by proxying a click to the real toggle
			// inside the (visually hidden) native admin bar.
			var findQmNode = function () {
				return document.querySelector( '#wp-admin-bar-query-monitor > a' ) ||
					document.querySelector( '#wp-admin-bar-query-monitor a' );
			};

			// init() itself only runs once parsing is done (see bottom of file), so
			// the native admin bar (wp_admin_bar_render, wp_footer priority 1000,
			// after this panel's 999) is already in the DOM by now: if the toggle
			// is nowhere to be found, hide our button instead of leaving a dead
			// control in the menu.
			if ( ! findQmNode() ) {
				qmBtn.hidden = true;
			}

			qmBtn.addEventListener( 'click', function () {
				var qmNode = findQmNode();
				if ( qmNode ) {
					qmNode.click();
					return;
				}
				qmBtn.hidden = true;
				if ( window.console && window.console.warn ) {
					console.warn( 'NanoBar: Query Monitor node not found in the admin bar (#wp-admin-bar-query-monitor). Is the admin bar being removed by the theme or another plugin?' );
				}
			} );
		}
	}

	// The panel markup is printed on wp_footer at priority 999 — this script
	// (enqueued in the footer too, but printed by core's default priority-20
	// wp_print_footer_scripts) can run before that markup exists in the DOM,
	// so #nanobar wouldn't be found yet. Defer init() until parsing is done.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
