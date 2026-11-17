/**
 * Checkout Fácil Brasil: Checkout block helpers.
 *
 * WooCommerce renders and saves the fields. This file adds the person type
 * switch, masks, check-digit feedback, "Sem número", our own messages, the
 * fallback help on WooCommerce before 9.9, the cfbr:cep and cfbr:cnpj events
 * and window.cfbr.setBlockField() for add-ons.
 */
( function ( win, doc ) {
	'use strict';

	var data = win.cfbrData || {};
	var core = win.cfbrCore;
	var wpData = win.wp && win.wp.data;
	if ( ! core || ! wpData ) {
		return;
	}
	var NS = data.ns || 'checkout-facil-brasil';
	var ADDRESS_IDS = [ 'number', 'nonumber', 'neighborhood' ];
	var MSG = ( data.i18n && data.i18n.messages ) || {};
	var lastCep = { billing: '', shipping: '' };
	var lastCnpj = '';
	var ours = {};

	function key( id ) {
		return NS + '/' + id;
	}

	function store( name ) {
		try {
			return wpData.select( name );
		} catch ( e ) {
			return null;
		}
	}

	function act( name ) {
		try {
			return wpData.dispatch( name );
		} catch ( e ) {
			return null;
		}
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

	function additional() {
		var s = store( 'wc/store/checkout' );
		return s && s.getAdditionalFields ? s.getAdditionalFields() || {} : {};
	}

	function address( scope ) {
		var s = store( 'wc/store/cart' );
		if ( ! s || ! s.getCustomerData ) {
			return {};
		}
		var c = s.getCustomerData() || {};
		return ( 'shipping' === scope ? c.shippingAddress : c.billingAddress ) || {};
	}

	function setAdditional( id, value ) {
		var d = act( 'wc/store/checkout' );
		if ( d && d.setAdditionalFields ) {
			var patch = Object.assign( {}, additional() );
			patch[ key( id ) ] = value;
			d.setAdditionalFields( patch );
		}
	}

	function setAddress( scope, id, value ) {
		var d = act( 'wc/store/cart' );
		if ( ! d ) {
			return;
		}
		var patch = {};
		patch[ -1 === ADDRESS_IDS.indexOf( id ) ? id : key( id ) ] = value;
		if ( 'shipping' === scope && d.setShippingAddress ) {
			d.setShippingAddress( patch );
		} else if ( d.setBillingAddress ) {
			d.setBillingAddress( patch );
		}
	}

	/**
	 * Sets one of our fields (or a core address field) in the block stores.
	 *
	 * @param {string} id    Short id, e.g. 'neighborhood', 'number' or 'cpf'.
	 * @param {*}      value Value.
	 * @param {string} scope billing|shipping (address fields only).
	 */
	function setBlockField( id, value, scope ) {
		id = String( id || '' ).replace( NS + '/', '' );
		if ( -1 !== ADDRESS_IDS.indexOf( id ) || /^(address_1|address_2|city|state|postcode|country|company|phone)$/.test( id ) ) {
			setAddress( scope || 'billing', id, value );
		} else {
			setAdditional( id, value );
		}
	}

	function inputFor( id, scope ) {
		var loc = -1 !== ADDRESS_IDS.indexOf( id ) ? ( scope || 'billing' ) : 'contact';
		return doc.getElementById( loc + '-' + NS + '-' + id ) || doc.getElementById( loc + '-' + NS + '/' + id );
	}

	function setNative( el, value ) {
		if ( el.value === value ) {
			return;
		}
		var setter = Object.getOwnPropertyDescriptor( win.HTMLInputElement.prototype, 'value' ).set;
		setter.call( el, value );
		el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}

	function personType() {
		if ( 'pf' === data.personType ) {
			return '1';
		}
		if ( 'pj' === data.personType ) {
			return '2';
		}
		return String( additional()[ key( 'persontype' ) ] || '1' );
	}

	function wrapper( el ) {
		return el ? el.closest( '.wc-block-components-text-input, .wc-block-components-select-input, .wc-blocks-components-select, .wc-block-components-checkbox' ) || el.parentNode : null;
	}

	/* ---------- Person type switch ---------- */

	function ensureDefaults() {
		if ( 'both' !== data.personType ) {
			return;
		}
		var current = additional()[ key( 'persontype' ) ];
		if ( '1' !== String( current ) && '2' !== String( current ) ) {
			setAdditional( 'persontype', '1' );
		}
	}

	function buildPills( select ) {
		var outer = select.closest( '.wc-block-components-select-input' ) || wrapper( select );
		if ( ! outer || outer.getAttribute( 'data-cfbr-pills' ) ) {
			return;
		}
		outer.setAttribute( 'data-cfbr-pills', '1' );
		outer.classList.add( 'cfbr-select-hidden' );
		var group = doc.createElement( 'div' );
		group.className = 'cfbr-pills';
		group.setAttribute( 'role', 'radiogroup' );
		group.setAttribute( 'aria-label', ( MSG.persontype && MSG.persontype.label ) || '' );
		Array.prototype.forEach.call( select.options, function ( opt ) {
			if ( ! opt.value ) {
				return;
			}
			var b = doc.createElement( 'button' );
			b.type = 'button';
			b.className = 'cfbr-pill';
			b.setAttribute( 'role', 'radio' );
			b.setAttribute( 'data-value', opt.value );
			b.textContent = opt.textContent;
			group.appendChild( b );
		} );
		group.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '.cfbr-pill' );
			if ( b ) {
				setAdditional( 'persontype', b.getAttribute( 'data-value' ) );
			}
		} );
		group.addEventListener( 'keydown', function ( e ) {
			var pills = Array.prototype.slice.call( group.querySelectorAll( '.cfbr-pill' ) );
			var i = pills.indexOf( doc.activeElement );
			var n = -1;
			if ( 'ArrowRight' === e.key || 'ArrowDown' === e.key ) {
				n = ( i + 1 ) % pills.length;
			} else if ( 'ArrowLeft' === e.key || 'ArrowUp' === e.key ) {
				n = ( i - 1 + pills.length ) % pills.length;
			}
			if ( n >= 0 ) {
				e.preventDefault();
				pills[ n ].focus();
				setAdditional( 'persontype', pills[ n ].getAttribute( 'data-value' ) );
			}
		} );
		outer.parentNode.insertBefore( group, outer );
		syncPills();
	}

	function syncPills() {
		var type = personType();
		Array.prototype.forEach.call( doc.querySelectorAll( '.cfbr-pill' ), function ( b ) {
			var on = b.getAttribute( 'data-value' ) === type;
			b.setAttribute( 'aria-checked', on ? 'true' : 'false' );
			b.tabIndex = on ? 0 : -1;
			b.classList.toggle( 'is-on', on );
		} );
	}

	/* ---------- Help on WooCommerce before 9.9 ---------- */

	function fallbackHelp() {
		if ( data.rules || 'both' !== data.personType || ! data.help ) {
			return;
		}
		var cnpj = inputFor( 'cnpj' );
		var w = wrapper( cnpj );
		if ( w && ! doc.getElementById( 'cfbr-fallback-help' ) ) {
			var p = doc.createElement( 'p' );
			p.id = 'cfbr-fallback-help';
			p.className = 'cfbr-help';
			p.textContent = data.help.fallback;
			w.parentNode.insertBefore( p, w.nextSibling );
		}
	}

	/* ---------- Masks and checks ---------- */

	function scopeOf( el ) {
		return 0 === ( el.id || '' ).indexOf( 'shipping-' ) ? 'shipping' : 'billing';
	}

	function countryOf( scope ) {
		return address( scope ).country || '';
	}

	function mark( el, ok ) {
		var w = wrapper( el );
		if ( w ) {
			w.classList.toggle( 'cfbr-ok', !! ok );
		}
	}

	function setError( id, message ) {
		var vkey = 'contact_' + key( id );
		var d = act( 'wc/store/validation' );
		if ( ! d ) {
			return;
		}
		if ( message ) {
			var e = {};
			e[ vkey ] = { message: message, hidden: false };
			ours[ vkey ] = message;
			d.setValidationErrors( e );
		} else if ( ours[ vkey ] ) {
			delete ours[ vkey ];
			var v = store( 'wc/store/validation' );
			var cur = v && v.getValidationError ? v.getValidationError( vkey ) : null;
			if ( cur && cur.message === ( MSG[ id ] && MSG[ id ].invalid ) ) {
				d.clearValidationError( vkey );
			}
		}
	}

	function checkDoc( el, final ) {
		var kind = el.getAttribute( 'data-cfbr' );
		var v = el.value || '';
		var clean = v.replace( /[^0-9A-Za-z]/g, '' );
		var ok;
		if ( 'cpf' === kind ) {
			ok = core.cpfOk( v );
			if ( 11 === clean.length || final ) {
				mark( el, ok );
				setError( 'cpf', clean && ! ok ? MSG.cpf && MSG.cpf.invalid : '' );
			}
		} else if ( 'cnpj' === kind ) {
			ok = core.cnpjOk( v );
			if ( 14 === clean.length || final ) {
				mark( el, ok );
				setError( 'cnpj', clean && ! ok ? MSG.cnpj && MSG.cnpj.invalid : '' );
			}
			if ( ok && clean.toUpperCase() !== lastCnpj ) {
				lastCnpj = clean.toUpperCase();
				emit( 'cfbr:cnpj', { cnpj: lastCnpj, checkout: 'block' } );
			}
		}
	}

	function onInput( e ) {
		var el = e.target;
		if ( ! el || 'INPUT' !== el.tagName ) {
			return;
		}
		var kind = el.getAttribute( 'data-cfbr' );
		var id = el.id || '';
		if ( /^(billing|shipping)-postcode$/.test( id ) ) {
			var scope = scopeOf( el );
			if ( 'BR' !== countryOf( scope ) ) {
				return;
			}
			if ( data.masks ) {
				setNative( el, core.mask( 'cep', el.value ) );
			}
			var digits = el.value.replace( /\D/g, '' );
			if ( 8 === digits.length && digits !== lastCep[ scope ] ) {
				lastCep[ scope ] = digits;
				emit( 'cfbr:cep', { cep: digits, scope: scope, checkout: 'block' } );
			}
			return;
		}
		if ( /^(billing|shipping)-phone$/.test( id ) && data.masks && 'BR' === countryOf( scopeOf( el ) ) ) {
			kind = 'phone';
		}
		if ( ! kind ) {
			return;
		}
		if ( data.masks || 'cnpj' === kind ) {
			setNative( el, core.mask( kind, el.value ) );
		}
		checkDoc( el, 'focusout' === e.type );
	}

	/* ---------- Sem número ---------- */

	function noNumber( scope ) {
		var box = inputFor( 'nonumber', scope );
		var num = inputFor( 'number', scope );
		if ( ! box || ! num ) {
			return;
		}
		var value = data.noNumberValue || 'S/N';
		var ticked = !! address( scope )[ key( 'nonumber' ) ];
		var w = wrapper( num );
		if ( ticked ) {
			if ( num.value !== value ) {
				setAddress( scope, 'number', value );
			}
			num.disabled = true;
		} else {
			if ( num.disabled && num.value === value ) {
				setAddress( scope, 'number', '' );
			}
			num.disabled = false;
		}
		if ( w ) {
			w.classList.toggle( 'cfbr-locked', ticked );
		}
	}

	/* ---------- Our messages instead of WooCommerce's generic ones ---------- */

	function valueFor( vkey ) {
		var m = vkey.match( /^(contact|billing|shipping)_(.+)$/ );
		if ( ! m ) {
			return '';
		}
		var src = 'contact' === m[ 1 ] ? additional() : address( m[ 1 ] );
		return src[ m[ 2 ] ];
	}

	var fixing = false;
	function fixMessages() {
		if ( fixing ) {
			return;
		}
		var v = store( 'wc/store/validation' );
		if ( ! v || ! v.getValidationError ) {
			return;
		}
		var patch = {};
		var any = false;
		[ 'contact', 'billing', 'shipping' ].forEach( function ( loc ) {
			Object.keys( MSG ).forEach( function ( id ) {
				var vkey = loc + '_' + key( id );
				var err = v.getValidationError( vkey );
				if ( ! err || ! err.message ) {
					return;
				}
				var val = valueFor( vkey );
				var want = ( '' === val || undefined === val || null === val || false === val ) ? MSG[ id ].required : MSG[ id ].invalid;
				if ( want && err.message !== want && err.message !== ours[ vkey ] ) {
					patch[ vkey ] = { message: want, hidden: !! err.hidden };
					any = true;
				}
			} );
		} );
		if ( any ) {
			fixing = true;
			act( 'wc/store/validation' ).setValidationErrors( patch );
			fixing = false;
		}
	}

	/* ---------- Wiring ---------- */

	function scan() {
		var select = doc.getElementById( 'contact-' + NS + '-persontype' ) || doc.getElementById( 'contact-' + NS + '/persontype' );
		if ( select && 'SELECT' === select.tagName && 'both' === data.personType ) {
			buildPills( select );
		}
		ensureDefaults();
		fallbackHelp();
		[ 'billing', 'shipping' ].forEach( noNumber );
	}

	/* Razão social: WooCommerce's Company field is optional on its own, so a business needs it from us. */
	var companyErr = false;
	function companyRule() {
		var d = act( 'wc/store/validation' );
		var v = store( 'wc/store/validation' );
		if ( ! d || ! v || ! v.getValidationError || ! MSG.company ) {
			return;
		}
		var billing = address( 'billing' );
		var shown = doc.getElementById( 'billing-company' ) || doc.getElementById( 'shipping-company' );
		var needed = !! shown && !! data.companyReq && '2' === personType() && ( ! data.onlyBrazil || 'BR' === billing.country ) && ! String( billing.company || '' ).trim();
		var cur = v.getValidationError( 'billing_company' );
		if ( needed ) {
			if ( ! cur ) {
				companyErr = true;
				d.setValidationErrors( { billing_company: { message: MSG.company.required, hidden: true } } );
			}
		} else if ( companyErr ) {
			companyErr = false;
			if ( cur && cur.message === MSG.company.required ) {
				d.clearValidationError( 'billing_company' );
			}
		}
	}

	/*
	 * WooCommerce before 9.9 has no conditional "required", and it does not show the
	 * server's refusal for additional fields. So the CPF or CNPJ the person type needs
	 * gets a client error here; the server still checks it on every order.
	 */
	var docErr = {};
	function docRule() {
		if ( data.rules || 'both' !== data.personType ) {
			return;
		}
		var d = act( 'wc/store/validation' );
		var v = store( 'wc/store/validation' );
		if ( ! d || ! v || ! v.getValidationError ) {
			return;
		}
		var type = personType();
		var br = ! data.onlyBrazil || 'BR' === address( 'billing' ).country;
		var extra = additional();
		[ [ 'cpf', '1' ], [ 'cnpj', '2' ] ].forEach( function ( pair ) {
			var id = pair[ 0 ];
			var vkey = 'contact_' + key( id );
			if ( ! MSG[ id ] || ! MSG[ id ].required || ! inputFor( id, 'contact' ) ) {
				return;
			}
			var needed = br && pair[ 1 ] === type && ! String( extra[ key( id ) ] || '' ).trim();
			var cur = v.getValidationError( vkey );
			if ( needed ) {
				if ( ! cur ) {
					docErr[ vkey ] = true;
					var e = {};
					e[ vkey ] = { message: MSG[ id ].required, hidden: true };
					d.setValidationErrors( e );
				}
			} else if ( docErr[ vkey ] ) {
				delete docErr[ vkey ];
				if ( cur && cur.message === MSG[ id ].required ) {
					d.clearValidationError( vkey );
				}
			}
		} );
	}

	var lastType = '';
	var lastFlags = '';
	function onStore() {
		var type = personType();
		if ( type !== lastType ) {
			lastType = type;
			syncPills();
		}
		var flags = String( !! address( 'billing' )[ key( 'nonumber' ) ] ) + String( !! address( 'shipping' )[ key( 'nonumber' ) ] );
		if ( flags !== lastFlags ) {
			lastFlags = flags;
			[ 'billing', 'shipping' ].forEach( noNumber );
		}
		fixMessages();
		companyRule();
		docRule();
	}

	win.cfbr = win.cfbr || {};
	win.cfbr.setBlockField = setBlockField;

	function init() {
		ensureDefaults();
		doc.addEventListener( 'input', onInput );
		doc.addEventListener( 'focusout', onInput );
		wpData.subscribe( onStore );
		var timer = null;
		var mo = new MutationObserver( function () {
			if ( timer ) {
				return;
			}
			timer = setTimeout( function () {
				timer = null;
				scan();
			}, 50 );
		} );
		mo.observe( doc.body, { childList: true, subtree: true } );
		scan();
	}

	if ( 'loading' === doc.readyState ) {
		doc.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}( window, document ) );
