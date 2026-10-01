/**
 * Woo Party Chef - Chef's Dinner Party vergelijker.
 *
 * Progressive enhancement: the server renders the complete default state,
 * this script only makes color, persons and column picks interactive. Live
 * prices come from the data-config attribute rendered by PHP.
 *
 * compute() mirrors WOOPC_Products::compute_state() in PHP. Keep both in sync.
 * State is written to [data-*], [aria-*] and [hidden] attributes only, never
 * to classes, so WP Rocket's Remove Unused CSS cannot strip the styling.
 */
( function () {
	'use strict';

	var SIZES = [ 4, 5, 6, 8 ];
	var COLS = [ '4', '5', '6', '8', 'ext' ];
	var WATT = 250;

	function formatEur( value ) {
		var fixed = Math.max( 0, value ).toFixed( 2 ).split( '.' );
		var whole = fixed[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, '.' );
		return '€ ' + whole + ',' + fixed[ 1 ];
	}

	function formatWatt( watt ) {
		return String( watt ).replace( /\B(?=(\d{3})+(?!\d))/g, '.' ) + ' W';
	}

	function pluralExt( count ) {
		return count === 1 ? '1 uitbreidingsset' : count + ' uitbreidingssets';
	}

	function compute( color, persons, cfg ) {
		var items = color.items;
		var n = Math.max( 1, Math.min( cfg.max, persons ) );
		var set, ext, i;

		if ( n <= 8 ) {
			set = 8;
			for ( i = 0; i < SIZES.length; i++ ) {
				if ( SIZES[ i ] >= n ) {
					set = SIZES[ i ];
					break;
				}
			}
			ext = 0;
		} else {
			set = 8;
			ext = n - 8;
		}

		var solo = n === 1;
		if ( solo ) {
			set = 0;
			ext = 1;
		}

		var spare = set + ext - n;
		var total = ( solo ? 0 : items[ set ].price ) + ext * items.ext.price;
		var totalWas = ( solo ? 0 : items[ set ].regular ) + ext * items.ext.regular;
		var stations = set + ext;
		var title, text;

		if ( solo ) {
			title = color.name + ' uitbreidingsset';
			text = 'Eén kookstation van 250 W met eigen keramisch bord en bakplaat. Later uit te breiden tot een complete set.';
		} else {
			title = ext ? set + ' personen + ' + pluralExt( ext ) : color.name + ' voor ' + set + ' personen';
			text = stations + ' kookstations van 250 W, samen ' + formatWatt( stations * WATT ) + '.';
			if ( spare > 0 ) {
				text += ' Je houdt ' + ( spare === 1 ? 'één plek' : spare + ' plekken' ) + ' over voor een extra gast.';
			}
			if ( ext ) {
				text += ' Elke uitbreidingsset voegt één eigen kookstation met bord en bakplaat toe.';
			}
		}

		var alt = '';
		if ( n === 7 ) {
			alt = 'Liever precies 7 plekken? Kies de set voor 6 personen met 1 uitbreidingsset' +
				( cfg.showPrices ? ' voor ' + formatEur( items[ 6 ].price + items.ext.price ) : '' ) + '.';
		} else if ( n >= 2 && n <= 3 ) {
			alt = 'De kleinste set is voor 4 personen. Zo heb je meteen ruimte als er iemand aanschuift.';
		}

		var dots = [];
		for ( i = 0; i < set; i++ ) {
			dots.push( i < n ? 'guest' : 'spare' );
		}
		for ( i = 0; i < ext; i++ ) {
			dots.push( 'ext' );
		}

		var cols = {};
		COLS.forEach( function ( key ) {
			var item = items[ key ];
			var diff = item.regular - item.price;
			cols[ key ] = {
				active: key === 'ext' ? ext > 0 : Number( key ) === set,
				sale: cfg.showSale && item.price < item.regular - 0.004,
				price: formatEur( item.price ),
				was: formatEur( item.regular ),
				badge: cfg.discountPct
					? '-' + Math.round( diff / item.regular * 100 ) + '%'
					: '-€' + Math.round( diff ),
				url: item.url
			};
		} );

		return {
			n: n,
			title: title,
			text: text,
			alt: alt,
			dots: dots,
			hasExt: ext > 0,
			hasSpare: spare > 0,
			extLink: ext > 0 && ! solo,
			extLabel: 'Voeg ' + pluralExt( ext ) + ' toe →',
			extUrl: items.ext.url,
			total: formatEur( total ),
			totalWas: formatEur( totalWas ),
			save: 'Je bespaart ' + formatEur( totalWas - total ),
			onSale: cfg.showSale && total < totalWas - 0.004,
			ctaUrl: solo ? items.ext.url : items[ set ].url,
			image: solo ? 'ext' : String( set ),
			cols: cols
		};
	}

	function init( root ) {
		var cfg;
		try {
			cfg = JSON.parse( root.getAttribute( 'data-config' ) || '' );
		} catch ( e ) {
			return;
		}
		if ( ! cfg || ! cfg.colors ) {
			return;
		}

		var state = {
			color: root.getAttribute( 'data-color' ),
			n: parseInt( root.getAttribute( 'data-persons' ), 10 ) || 4
		};

		function ref( name, scope ) {
			return ( scope || root ).querySelector( '[data-ref="' + name + '"]' );
		}

		function setText( el, value ) {
			if ( el && el.textContent !== value ) {
				el.textContent = value;
			}
		}

		function show( el, visible ) {
			if ( el ) {
				el.hidden = ! visible;
			}
		}

		function setHref( el, url ) {
			if ( el && el.getAttribute( 'href' ) !== url ) {
				el.setAttribute( 'href', url );
			}
		}

		function render() {
			var color = cfg.colors[ state.color ];
			var s = compute( color, state.n, cfg );
			state.n = s.n;

			root.setAttribute( 'data-color', state.color );
			root.setAttribute( 'data-persons', String( s.n ) );

			root.querySelectorAll( '[data-pick-color]' ).forEach( function ( btn ) {
				btn.setAttribute( 'aria-pressed', btn.getAttribute( 'data-pick-color' ) === state.color ? 'true' : 'false' );
			} );
			// Planner image and PFAS badge follow the recommended product.
			var shown = color.items[ s.image ];
			root.querySelectorAll( '[data-image-id]' ).forEach( function ( img ) {
				img.hidden = img.getAttribute( 'data-image-id' ) !== String( shown.image );
			} );
			show( ref( 'pfas' ), !! shown.pfas );
			setText( ref( 'color-label' ), '· ' + color.label );

			// Planner (optional).
			setText( ref( 'n' ), String( s.n ) );
			var minus = root.querySelector( '[data-step="-1"]' );
			var plus = root.querySelector( '[data-step="1"]' );
			if ( minus ) {
				minus.disabled = s.n <= 1;
			}
			if ( plus ) {
				plus.disabled = s.n >= cfg.max;
			}

			var dots = ref( 'dots' );
			if ( dots ) {
				dots.innerHTML = s.dots.map( function ( kind ) {
					return '<span class="woopc__dot" data-kind="' + kind + '"></span>';
				} ).join( '' );
			}
			show( ref( 'legend-ext' ), s.hasExt );
			show( ref( 'legend-spare' ), s.hasSpare );

			setText( ref( 'title' ), s.title );
			setText( ref( 'text' ), s.text );
			var alt = ref( 'alt' );
			setText( alt, s.alt );
			show( alt, s.alt !== '' );
			var extLink = ref( 'ext-link' );
			setText( extLink, s.extLabel );
			setHref( extLink, s.extUrl );
			show( extLink, s.extLink );

			var total = ref( 'total' );
			if ( total ) {
				setText( total, s.total );
				total.setAttribute( 'data-sale', s.onSale ? 'true' : 'false' );
				var totalWas = ref( 'total-was' );
				setText( totalWas, s.totalWas );
				show( totalWas, s.onSale );
				var save = ref( 'save' );
				setText( save, s.save );
				show( save, s.onSale );
			}
			setHref( ref( 'cta' ), s.ctaUrl );

			// Table columns and mobile cards share the same per-column data.
			root.querySelectorAll( '[data-col]' ).forEach( function ( el ) {
				var col = s.cols[ el.getAttribute( 'data-col' ) ];
				if ( ! col ) {
					return;
				}
				el.setAttribute( 'data-active', col.active ? 'true' : 'false' );

				var price = ref( 'price', el );
				if ( price ) {
					setText( price, col.price );
					price.setAttribute( 'data-sale', col.sale ? 'true' : 'false' );
				}
				var was = ref( 'was', el );
				setText( was, col.was );
				var badge = ref( 'badge', el );
				setText( badge, col.badge );

				var saleRow = ref( 'sale-row', el );
				if ( saleRow ) {
					show( saleRow, col.sale );
				} else {
					show( was, col.sale );
					show( badge, col.sale );
				}
				setHref( ref( 'url', el ), col.url );
			} );
		}

		root.addEventListener( 'click', function ( event ) {
			var target = event.target;
			if ( ! ( target instanceof Element ) ) {
				return;
			}

			var colorBtn = target.closest( '[data-pick-color]' );
			if ( colorBtn ) {
				var key = colorBtn.getAttribute( 'data-pick-color' );
				if ( cfg.colors[ key ] ) {
					state.color = key;
					render();
				}
				return;
			}

			var stepBtn = target.closest( '[data-step]' );
			if ( stepBtn ) {
				state.n += parseInt( stepBtn.getAttribute( 'data-step' ), 10 ) || 0;
				render();
				return;
			}

			// Links inside a column navigate normally.
			if ( target.closest( 'a' ) ) {
				return;
			}

			var col = target.closest( '[data-col]' );
			if ( col ) {
				var pick = col.getAttribute( 'data-col' );
				state.n = pick === 'ext' ? 1 : parseInt( pick, 10 );
				render();
			}
		} );

		if ( ! cfg.colors[ state.color ] ) {
			state.color = Object.keys( cfg.colors )[ 0 ];
		}
		render();
	}

	function boot() {
		document.querySelectorAll( '.woopc[data-config]' ).forEach( init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
