/**
 * Checkout Fácil Brasil: classic checkout and My Account address forms.
 *
 * Shows the CPF or CNPJ fields for the chosen person type, masks inputs,
 * handles "Sem número" and "Isento", hides Brazil-only fields for other
 * countries and fires the cfbr:cep and cfbr:cnpj events. Vanilla JS; uses
 * jQuery only to listen to the events WooCommerce fires through it.
 */
( function ( win, doc ) {
	'use strict';

	var data = win.cfbrData || {};
	var core = win.cfbrCore;
	if ( ! core ) {
		return;
	}
	var lastCep = { billing: '', shipping: '' };
	var lastCnpj = '';

	function $all( sel, root ) {
		return Array.prototype.slice.call( ( root || doc ).querySelectorAll( sel ) );
	}

	function row( el ) {
		return el && el.closest ? el.closest( '.form-row' ) : null;
	}

	function emit( name, detail ) {
		var ev;
		try {
			ev = new CustomEvent( name, { detail: detail } );
		} catch ( e ) {
			ev = doc.createEvent( 'CustomEvent' );
			ev.initCustomEvent( name, false, false, detail );
		}
		doc.dispatchEvent( ev );
	}

	function personType() {
		if ( 'pf' === data.personType ) {
			return '1';
		}
		if ( 'pj' === data.personType ) {
			return '2';
		}
		var checked = doc.querySelector( 'input[name="billing_persontype"]:checked' );
		return checked ? checked.value : '1';
	}

	function setShown( el, show ) {
		if ( ! el ) {
			return;
		}
		el.hidden = ! show;
		el.style.display = show ? '' : 'none';
	}

	function countryOf( scope ) {
		var c = doc.getElementById( scope + '_country' );
		return c ? c.value : 'BR';
	}

	function applyPersonType() {
		var type = personType();
		$all( '.cfbr-pf' ).forEach( function ( el ) {
			setShown( el, '1' === type && ( ! data.onlyBrazil || 'BR' === countryOf( 'billing' ) ) );
		} );
		$all( '.cfbr-pj' ).forEach( function ( el ) {
			setShown( el, '2' === type && ( ! data.onlyBrazil || 'BR' === countryOf( 'billing' ) ) );
		} );
		$all( '.cfbr-company-req' ).forEach( function ( el ) {
			el.classList.toggle( 'validate-required', '2' === type );
			var label = el.querySelector( 'label' );
			if ( ! label ) {
				return;
			}
			var req = label.querySelector( '.required' );
			var opt = label.querySelector( '.optional' );
			if ( '2' === type ) {
				if ( opt ) {
					opt.style.display = 'none';
				}
				if ( ! req ) {
					req = doc.createElement( 'abbr' );
					req.className = 'required cfbr-added';
					req.textContent = '*';
					label.appendChild( doc.createTextNode( ' ' ) );
					label.appendChild( req );
				}
				req.style.display = '';
			} else {
				if ( opt ) {
					opt.style.display = '';
				}
				if ( req && req.classList.contains( 'cfbr-added' ) ) {
					req.style.display = 'none';
				}
			}
		} );
	}

	function applyCountry() {
		[ 'billing', 'shipping' ].forEach( function ( scope ) {
			var isBr = 'BR' === countryOf( scope );
			$all( '[id^="' + scope + '_"].cfbr-br, .cfbr-br' ).forEach( function ( el ) {
				var input = el.querySelector( 'input, select' );
				if ( input && 0 === input.id.indexOf( scope + '_' ) ) {
					setShown( el, isBr );
				}
			} );
		} );
		if ( data.onlyBrazil ) {
			var isBr = 'BR' === countryOf( 'billing' );
			$all( '.cfbr-persontype' ).forEach( function ( el ) {
				setShown( el, isBr );
			} );
			$all( '.cfbr-field:not(.cfbr-br):not(.cfbr-doc)' ).forEach( function ( el ) {
				var input = el.querySelector( 'input, select' );
				if ( input && 0 === input.id.indexOf( 'billing_' ) ) {
					setShown( el, isBr );
				}
			} );
		}
		applyPersonType();
	}

	function setValidity( el, ok ) {
		var r = row( el );
		if ( ! r ) {
			return;
		}
		r.classList.remove( 'cfbr-ok', 'woocommerce-validated', 'woocommerce-invalid', 'cfbr-bad' );
		if ( '' === el.value ) {
			return;
		}
		r.classList.add( ok ? 'cfbr-ok' : 'cfbr-bad' );
		r.classList.add( ok ? 'woocommerce-validated' : 'woocommerce-invalid' );
	}

	function checkDoc( el ) {
		var kind = el.getAttribute( 'data-cfbr-mask' );
		var v = el.value;
		if ( 'cpf' === kind ) {
			setValidity( el, core.cpfOk( v ) );
		} else if ( 'cnpj' === kind ) {
			var ok = core.cnpjOk( v );
			setValidity( el, ok );
			var clean = v.replace( /[^0-9A-Za-z]/g, '' ).toUpperCase();
			if ( ok && clean !== lastCnpj ) {
				lastCnpj = clean;
				emit( 'cfbr:cnpj', { cnpj: clean, checkout: 'classic' } );
			}
		} else if ( 'phone' === kind && 'BR' === countryOf( el.id.indexOf( 'shipping_' ) === 0 ? 'shipping' : 'billing' ) ) {
			if ( v ) {
				setValidity( el, core.phoneOk( v ) );
			}
		}
	}

	function checkCep( el ) {
		var scope = 0 === el.id.indexOf( 'shipping_' ) ? 'shipping' : 'billing';
		if ( 'BR' !== countryOf( scope ) ) {
			return;
		}
		var digits = el.value.replace( /\D/g, '' );
		if ( 8 === digits.length && digits !== lastCep[ scope ] ) {
			lastCep[ scope ] = digits;
			emit( 'cfbr:cep', { cep: digits, scope: scope, checkout: 'classic' } );
		}
	}

	function onInput( e ) {
		var el = e.target;
		if ( ! el || ! el.getAttribute ) {
			return;
		}
		var kind = el.getAttribute( 'data-cfbr-mask' );
		if ( ! kind ) {
			return;
		}
		var scope = 0 === el.id.indexOf( 'shipping_' ) ? 'shipping' : 'billing';
		var maskIt = data.masks && ( 'cep' !== kind && 'phone' !== kind || 'BR' === countryOf( scope ) );
		if ( 'cpf' === kind || 'cnpj' === kind || 'upper' === kind ) {
			maskIt = data.masks || 'upper' === kind;
		}
		if ( maskIt ) {
			var masked = core.mask( kind, el.value );
			if ( masked !== el.value ) {
				el.value = masked;
			}
		}
		if ( 'cep' === kind ) {
			checkCep( el );
		} else if ( 'blur' === e.type || 'change' === e.type ) {
			checkDoc( el );
		} else if ( 'cnpj' === kind || 'cpf' === kind ) {
			var len = el.value.replace( /[^0-9A-Za-z]/g, '' ).length;
			if ( ( 'cpf' === kind && 11 === len ) || ( 'cnpj' === kind && 14 === len ) ) {
				checkDoc( el );
			}
		}
	}

	function toggleBox( box, input, value ) {
		if ( ! box || ! input ) {
			return;
		}
		var r = row( input );
		if ( box.checked ) {
			if ( input.value && input.value !== value ) {
				input.setAttribute( 'data-cfbr-prev', input.value );
			}
			input.value = value;
			input.readOnly = true;
			input.setAttribute( 'aria-disabled', 'true' );
			if ( r ) {
				r.classList.add( 'cfbr-locked' );
				r.classList.remove( 'woocommerce-invalid', 'woocommerce-invalid-required-field' );
			}
		} else {
			if ( input.value === value ) {
				input.value = input.getAttribute( 'data-cfbr-prev' ) || '';
			}
			input.readOnly = false;
			input.removeAttribute( 'aria-disabled' );
			if ( r ) {
				r.classList.remove( 'cfbr-locked' );
			}
		}
	}

	function wireBoxes() {
		[ 'billing', 'shipping' ].forEach( function ( scope ) {
			var box = doc.getElementById( scope + '_nonumber' );
			var input = doc.getElementById( scope + '_number' );
			if ( box && input && ! box.getAttribute( 'data-cfbr-wired' ) ) {
				box.setAttribute( 'data-cfbr-wired', '1' );
				box.addEventListener( 'change', function () {
					toggleBox( box, input, data.noNumberValue || 'S/N' );
				} );
				if ( input.value === ( data.noNumberValue || 'S/N' ) ) {
					box.checked = true;
				}
				toggleBox( box, input, data.noNumberValue || 'S/N' );
			}
		} );
		var ex = doc.getElementById( 'billing_ie_exempt' );
		var ie = doc.getElementById( 'billing_ie' );
		if ( ex && ie && ! ex.getAttribute( 'data-cfbr-wired' ) ) {
			ex.setAttribute( 'data-cfbr-wired', '1' );
			ex.addEventListener( 'change', function () {
				toggleBox( ex, ie, data.exemptValue || 'ISENTO' );
			} );
			if ( ie.value && ie.value.toUpperCase() === ( data.exemptValue || 'ISENTO' ) ) {
				ex.checked = true;
			}
			toggleBox( ex, ie, data.exemptValue || 'ISENTO' );
		}
	}

	function maskExisting() {
		$all( '[data-cfbr-mask]' ).forEach( function ( el ) {
			if ( el.value ) {
				onInput( { target: el, type: 'init' } );
			}
		} );
	}

	function refresh() {
		wireBoxes();
		applyCountry();
	}

	function init() {
		doc.addEventListener( 'input', onInput );
		doc.addEventListener( 'blur', onInput, true );
		doc.addEventListener( 'change', function ( e ) {
			var t = e.target;
			if ( ! t ) {
				return;
			}
			if ( 'billing_persontype' === t.name ) {
				applyPersonType();
			} else if ( /_country$/.test( t.id || '' ) || 'ship-to-different-address-checkbox' === t.id ) {
				applyCountry();
			} else if ( t.getAttribute && t.getAttribute( 'data-cfbr-mask' ) ) {
				onInput( e );
			}
		} );
		if ( win.jQuery ) {
			win.jQuery( doc.body ).on( 'country_to_state_changed updated_checkout', refresh );
			win.jQuery( doc ).on( 'change', 'select[id$="_country"]', applyCountry );
		}
		maskExisting();
		refresh();
	}

	if ( 'loading' === doc.readyState ) {
		doc.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}( window, document ) );
