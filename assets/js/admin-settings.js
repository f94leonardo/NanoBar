/**
 * Settings → NanoBar admin page: live position/size/color preview, the
 * "Restore defaults" button, and dismissible settings notices.
 *
 * Reads its defaults/config/i18n strings from the `nanobarSettings` object
 * localized by Settings\Page::enqueue_assets(), the same single source of
 * truth used by the sanitizer and the frontend renderer, so the live preview
 * can never drift from what gets saved.
 *
 * @package NanoBar
 */

( function () {
	'use strict';

	if ( ! window.jQuery || ! window.nanobarSettings ) {
		return;
	}

	jQuery( function ( $ ) {
		var defaults = window.nanobarSettings.defaults;
		var config = window.nanobarSettings.config;
		var i18n = window.nanobarSettings.i18n;

		var $posH = $( '#nanobar-pos-h' );
		var $posV = $( '#nanobar-pos-v' );
		var $sizeInput = $( '#nanobar-toggle-size-input' );
		var $colorField = $( '#nanobar-color-field' );
		var $viewport = $( '#nanobar-viewport' );
		var $preview = $( '#nanobar-preview-button' );
		var $label = $( '#nanobar-viewport-label' );

		// Client-side mirror of Support\Color::get_contrast_color() in PHP,
		// needed because the preview has to react to colors that have not
		// been saved yet. Every number and color it uses comes from `config`,
		// so the two stay in sync on their own.
		function contrastColor( hex ) {
			hex = ( hex || '' ).replace( '#', '' );
			if ( 3 === hex.length ) {
				hex = hex[ 0 ] + hex[ 0 ] + hex[ 1 ] + hex[ 1 ] + hex[ 2 ] + hex[ 2 ];
			}
			if ( ! /^[0-9a-fA-F]{6}$/.test( hex ) ) {
				return config.contrast.on_dark;
			}
			var weights = config.contrast.weights;
			var red = parseInt( hex.substr( 0, 2 ), 16 );
			var green = parseInt( hex.substr( 2, 2 ), 16 );
			var blue = parseInt( hex.substr( 4, 2 ), 16 );
			var yiq = ( red * weights.red + green * weights.green + blue * weights.blue ) / 1000;
			return yiq >= config.contrast.threshold ? config.contrast.on_light : config.contrast.on_dark;
		}

		function updatePreview( color ) {
			var size = parseInt( $sizeInput.val(), 10 );
			var posH = $posH.val();
			var posV = $posV.val();
			size = Math.max( config.size.min, Math.min( config.size.max, isNaN( size ) ? defaults.toggle_size : size ) );
			color = color || defaults.toggle_bg_color;

			$preview.css( {
				width: size + 'px',
				height: size + 'px',
				background: color,
				color: contrastColor( color )
			} );

			var alignH = 'left' === posH ? 'flex-start' : ( 'right' === posH ? 'flex-end' : 'center' );
			var alignV = 'bottom' === posV ? 'flex-end' : 'flex-start';
			var labelText = ( 'top' === posV ? i18n.top : i18n.bottom );
			labelText += ' · ' + ( 'left' === posH ? i18n.left : ( 'right' === posH ? i18n.right : i18n.center ) );

			$viewport.css( {
				justifyContent: alignH,
				alignItems: alignV
			} );
			$label.text( labelText );
		}

		function updateCenterOption() {
			var allowsCenter = config.positions.center_vertical === $posV.val();
			var $centerOption = $posH.find( 'option[value="center"]' );
			$centerOption.prop( 'disabled', ! allowsCenter );
			if ( ! allowsCenter && 'center' === $posH.val() ) {
				$posH.val( config.positions.center_fallback );
			}
		}

		$colorField.wpColorPicker( {
			change: function ( event, ui ) {
				updatePreview( ui.color.toString() );
			},
			clear: function () {
				updatePreview( $colorField.data( 'default-color' ) );
			}
		} );

		$sizeInput.on( 'input change', function () {
			updatePreview( $colorField.val() );
		} );
		$posH.on( 'change', function () {
			updateCenterOption();
			updatePreview( $colorField.val() );
		} );
		$posV.on( 'change', function () {
			updateCenterOption();
			updatePreview( $colorField.val() );
		} );

		$( '#nanobar-reset-defaults' ).on( 'click', function ( event ) {
			event.preventDefault();
			$( 'input[name="nanobar_options[enabled]"]' ).prop( 'checked', !! defaults.enabled );
			$( 'input[name="nanobar_options[roles][]"]' ).prop( 'checked', function () {
				return defaults.roles.indexOf( $( this ).val() ) !== -1;
			} );
			$posH.val( defaults.position_horizontal );
			$posV.val( defaults.position_vertical );
			$sizeInput.val( defaults.toggle_size );
			$colorField.wpColorPicker( 'color', defaults.toggle_bg_color );
			$( 'input[name="nanobar_options[icons_only]"]' ).prop( 'checked', !! defaults.icons_only );
			$( 'input[name="nanobar_options[force_admin_bar_for_qm]"]' ).prop( 'checked', !! defaults.force_admin_bar_for_qm );
			updateCenterOption();
			updatePreview( defaults.toggle_bg_color );
		} );

		updateCenterOption();
		updatePreview( $colorField.val() );

		var $notices = $( '.nanobar-notices-container .notice' );

		$notices.each( function () {
			var $notice = $( this );

			// Add type icon (success, error, warning).
			if ( ! $notice.find( '.notice-icon' ).length ) {
				$notice.prepend( '<span class="notice-icon"></span>' );
			}

			// Add close button.
			if ( ! $notice.find( '.notice-dismiss' ).length ) {
				$notice.append( '<button type="button" class="notice-dismiss" aria-label="Close notification">×</button>' );
			}

			// Auto-dismiss after 5 seconds (success only).
			if ( $notice.hasClass( 'notice-success' ) ) {
				var dismissTimeout = setTimeout( function () {
					$notice.addClass( 'is-dismissing' );
					setTimeout( function () {
						$notice.fadeOut( function () {
							$notice.remove();
						} );
					}, 400 );
				}, 5000 );

				// Clear the timeout if dismissed manually.
				$notice.on( 'click.dismiss', '.notice-dismiss', function ( e ) {
					e.preventDefault();
					clearTimeout( dismissTimeout );
					$notice.trigger( 'close.notice-dismiss' );
				} );
			} else {
				// Error and warning: manual close only.
				$notice.on( 'click.dismiss', '.notice-dismiss', function ( e ) {
					e.preventDefault();
					$notice.addClass( 'is-dismissing' );
					setTimeout( function () {
						$notice.fadeOut( function () {
							$notice.remove();
						} );
					}, 400 );
				} );
			}
		} );
	} );
}() );
