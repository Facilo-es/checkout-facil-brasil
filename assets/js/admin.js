/**
 * Checkout Fácil Brasil: setup steps and the live preview.
 * Vanilla JS, no dependencies.
 */
( function ( doc ) {
	'use strict';

	function $all( sel, root ) {
		return Array.prototype.slice.call( ( root || doc ).querySelectorAll( sel ) );
	}

	var setup = doc.querySelector( '.cfbr-setup' );
	var pv = doc.querySelector( '.cfbr-pv' );

	function go( step ) {
		if ( ! setup ) {
			return;
		}
		step = parseInt( step, 10 );
		setup.setAttribute( 'data-step', String( step ) );
		$all( '.cfbr-panel', setup ).forEach( function ( p ) {
			p.hidden = parseInt( p.getAttribute( 'data-panel' ), 10 ) !== step;
		} );
		$all( '.cfbr-steps li', setup ).forEach( function ( li ) {
			var n = parseInt( li.getAttribute( 'data-step' ), 10 );
			li.classList.toggle( 'done', n < step );
			li.classList.toggle( 'cur', n === step );
			if ( n === step ) {
				li.setAttribute( 'aria-current', 'step' );
			} else {
				li.removeAttribute( 'aria-current' );
			}
		} );
		var panel = setup.querySelector( '.cfbr-panel[data-panel="' + step + '"]' );
		var h = panel ? panel.querySelector( '.cfbr-h' ) : null;
		if ( h ) {
			h.setAttribute( 'tabindex', '-1' );
			h.focus();
		}
	}

	function persons( value ) {
		if ( pv ) {
			pv.setAttribute( 'data-persons', value );
			if ( 'pj' === value ) {
				pv.setAttribute( 'data-type', '2' );
			} else if ( 'pf' === value ) {
				pv.setAttribute( 'data-type', '1' );
			}
			syncPill();
		}
		$all( '.cfbr-card' ).forEach( function ( card ) {
			var r = card.querySelector( 'input' );
			card.classList.toggle( 'is-on', !! ( r && r.checked ) );
		} );
	}

	function syncPill() {
		if ( ! pv ) {
			return;
		}
		var type = pv.getAttribute( 'data-type' );
		$all( '.cfbr-pv-pill button', pv ).forEach( function ( b ) {
			var on = b.getAttribute( 'data-type' ) === type;
			b.classList.toggle( 'on', on );
			b.setAttribute( 'aria-checked', on ? 'true' : 'false' );
		} );
	}

	function row( r ) {
		var sw = r.querySelector( '.cfbr-switch' );
		var on = !! ( sw && sw.checked );
		r.classList.toggle( 'on', on );
		$all( '.cfbr-req input', r ).forEach( function ( i ) {
			i.disabled = ! on;
		} );
		var req = r.querySelector( '.cfbr-req input[value="required"]' );
		var mode = on ? ( req && req.checked ? 'required' : 'optional' ) : 'off';
		var field = r.getAttribute( 'data-field' );
		if ( pv && field ) {
			$all( '[data-opt="' + field + '"]', pv ).forEach( function ( f ) {
				f.setAttribute( 'data-mode', mode );
			} );
		}
	}

	doc.addEventListener( 'click', function ( e ) {
		var t = e.target;
		var btn = t.closest ? t.closest( '[data-go]' ) : null;
		if ( btn ) {
			e.preventDefault();
			go( btn.getAttribute( 'data-go' ) );
			return;
		}
		var pill = t.closest ? t.closest( '.cfbr-pv-pill button' ) : null;
		if ( pill && pv ) {
			pv.setAttribute( 'data-type', pill.getAttribute( 'data-type' ) );
			syncPill();
		}
	} );

	doc.addEventListener( 'change', function ( e ) {
		var t = e.target;
		if ( 'cfbr[person_type]' === t.name ) {
			persons( t.value );
			return;
		}
		var r = t.closest ? t.closest( '.cfbr-row' ) : null;
		if ( r ) {
			row( r );
		}
	} );

	doc.addEventListener( 'keydown', function ( e ) {
		var t = e.target;
		if ( ! t.closest || ! t.closest( '.cfbr-pv-pill' ) ) {
			return;
		}
		if ( [ 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown' ].indexOf( e.key ) < 0 ) {
			return;
		}
		e.preventDefault();
		var pills = $all( '.cfbr-pv-pill button', pv );
		var next = pills[ ( pills.indexOf( t ) + 1 ) % pills.length ];
		if ( next ) {
			next.focus();
			next.click();
		}
	} );

	var checked = doc.querySelector( 'input[name="cfbr[person_type]"]:checked' );
	if ( checked ) {
		persons( checked.value );
	}
	$all( '.cfbr-row' ).forEach( row );
}( document ) );
