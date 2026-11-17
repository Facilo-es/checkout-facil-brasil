/**
 * Checkout Fácil Brasil: masks and check digits shared by the classic and block checkouts.
 * No dependencies. Exposes window.cfbrCore.
 */
( function ( win ) {
	'use strict';

	function dv( vals, weights ) {
		var s = 0, i, r;
		for ( i = 0; i < weights.length; i++ ) {
			s += vals[ i ] * weights[ i ];
		}
		r = s % 11;
		return r < 2 ? 0 : 11 - r;
	}

	function cpfOk( v ) {
		var d = String( v || '' );
		if ( /[^\d.\-\s]/.test( d ) ) {
			return false;
		}
		d = d.replace( /\D/g, '' );
		if ( d.length !== 11 || /^(\d)\1{10}$/.test( d ) ) {
			return false;
		}
		var n = d.split( '' ).map( Number );
		return dv( n, [ 10, 9, 8, 7, 6, 5, 4, 3, 2 ] ) === n[ 9 ] && dv( n, [ 11, 10, 9, 8, 7, 6, 5, 4, 3, 2 ] ) === n[ 10 ];
	}

	function cnpjOk( v ) {
		var c = String( v || '' ).toUpperCase().replace( /[.\/\-\s]/g, '' );
		if ( ! /^[0-9A-Z]{12}[0-9]{2}$/.test( c ) || /^(.)\1{13}$/.test( c ) ) {
			return false;
		}
		var n = c.split( '' ).map( function ( ch ) {
			return ch.charCodeAt( 0 ) - 48;
		} );
		var w1 = [ 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ], w2 = [ 6 ].concat( w1 );
		return dv( n, w1 ) === n[ 12 ] && dv( n, w2 ) === n[ 13 ];
	}

	function phoneOk( v ) {
		var d = String( v || '' ).replace( /\D/g, '' );
		if ( d.length > 11 && d.indexOf( '55' ) === 0 ) {
			d = d.slice( 2 );
		}
		return ( d.length === 10 || d.length === 11 ) && d.charAt( 0 ) !== '0';
	}

	function fit( raw, pattern ) {
		var out = '', j = 0, i;
		for ( i = 0; i < pattern.length && j < raw.length; i++ ) {
			out += pattern.charAt( i ) === '#' ? raw.charAt( j++ ) : pattern.charAt( i );
		}
		return out;
	}

	function mask( kind, v ) {
		var d, c;
		v = String( v || '' );
		switch ( kind ) {
			case 'cpf':
				return fit( v.replace( /\D/g, '' ).slice( 0, 11 ), '###.###.###-##' );
			case 'cnpj':
				c = v.toUpperCase().replace( /[^0-9A-Z]/g, '' );
				c = c.slice( 0, 12 ) + c.slice( 12 ).replace( /\D/g, '' );
				return fit( c.slice( 0, 14 ), '##.###.###/####-##' );
			case 'cep':
				return fit( v.replace( /\D/g, '' ).slice( 0, 8 ), '#####-###' );
			case 'phone':
				d = v.replace( /\D/g, '' ).slice( 0, 11 );
				return fit( d, d.length > 10 ? '(##) #####-####' : '(##) ####-####' );
			case 'date':
				return fit( v.replace( /\D/g, '' ).slice( 0, 8 ), '##/##/####' );
			case 'upper':
				return v.toUpperCase();
		}
		return v;
	}

	win.cfbrCore = {
		cpfOk: cpfOk,
		cnpjOk: cnpjOk,
		phoneOk: phoneOk,
		mask: mask
	};
}( window ) );
