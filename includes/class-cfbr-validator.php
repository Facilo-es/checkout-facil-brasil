<?php
/**
 * CPF, CNPJ (numeric and alphanumeric), phone and CEP checks and formats.
 *
 * @package CheckoutFacilBrasil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pure validation helpers. No WordPress calls, so they are easy to test.
 */
class CFBR_Validator {

	/**
	 * Keep digits only.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function digits( $value ) {
		return preg_replace( '/\D+/', '', (string) $value );
	}

	/**
	 * Keep A-Z and 0-9, upper-cased.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function alnum( $value ) {
		return preg_replace( '/[^A-Z0-9]+/', '', strtoupper( (string) $value ) );
	}

	/**
	 * Receita Federal check digit for a list of character values.
	 *
	 * @param int[] $values  Values.
	 * @param int[] $weights Weights.
	 * @return int
	 */
	private static function check_digit( array $values, array $weights ) {
		$sum = 0;
		foreach ( $weights as $i => $weight ) {
			$sum += $values[ $i ] * $weight;
		}
		$rest = $sum % 11;
		return $rest < 2 ? 0 : 11 - $rest;
	}

	/**
	 * Is this a valid CPF?
	 *
	 * @param mixed $value CPF with or without mask.
	 * @return bool
	 */
	public static function is_cpf( $value ) {
		$value = (string) $value;
		if ( preg_match( '/[^0-9.\-\s]/', $value ) ) {
			return false;
		}
		$cpf = self::digits( $value );
		if ( 11 !== strlen( $cpf ) || preg_match( '/^(\d)\1{10}$/', $cpf ) ) {
			return false;
		}
		$values = array_map( 'intval', str_split( $cpf ) );
		$d1     = self::check_digit( $values, range( 10, 2 ) );
		$d2     = self::check_digit( $values, range( 11, 2 ) );
		return $values[9] === $d1 && $values[10] === $d2;
	}

	/**
	 * Is this a valid CNPJ? Accepts the numeric and the alphanumeric format.
	 *
	 * @param mixed $value CNPJ with or without mask.
	 * @return bool
	 */
	public static function is_cnpj( $value ) {
		$value = strtoupper( (string) $value );
		if ( preg_match( '/[^0-9A-Z.\/\-\s]/', $value ) ) {
			return false;
		}
		$cnpj = self::alnum( $value );
		if ( ! preg_match( '/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj ) || preg_match( '/^(.)\1{13}$/', $cnpj ) ) {
			return false;
		}
		$values = array();
		foreach ( str_split( $cnpj ) as $char ) {
			$values[] = ord( $char ) - 48;
		}
		$w1 = array( 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 );
		$w2 = array_merge( array( 6 ), $w1 );
		return self::check_digit( $values, $w1 ) === $values[12] && self::check_digit( $values, $w2 ) === $values[13];
	}

	/**
	 * Format a CPF as 000.000.000-00 (only when it has 11 digits).
	 *
	 * @param mixed $value CPF.
	 * @return string
	 */
	public static function format_cpf( $value ) {
		$cpf = self::digits( $value );
		if ( 11 !== strlen( $cpf ) ) {
			return trim( (string) $value );
		}
		return substr( $cpf, 0, 3 ) . '.' . substr( $cpf, 3, 3 ) . '.' . substr( $cpf, 6, 3 ) . '-' . substr( $cpf, 9, 2 );
	}

	/**
	 * Format a CNPJ as AA.AAA.AAA/AAAA-00 (only when it has 14 characters).
	 *
	 * @param mixed $value CNPJ.
	 * @return string
	 */
	public static function format_cnpj( $value ) {
		$cnpj = self::alnum( $value );
		if ( 14 !== strlen( $cnpj ) ) {
			return trim( (string) $value );
		}
		return substr( $cnpj, 0, 2 ) . '.' . substr( $cnpj, 2, 3 ) . '.' . substr( $cnpj, 5, 3 ) . '/' . substr( $cnpj, 8, 4 ) . '-' . substr( $cnpj, 12, 2 );
	}

	/**
	 * Is this a Brazilian phone with area code (10 or 11 digits)?
	 *
	 * @param mixed $value Phone.
	 * @return bool
	 */
	public static function is_phone( $value ) {
		$phone = self::digits( $value );
		if ( 0 === strpos( $phone, '55' ) && strlen( $phone ) > 11 ) {
			$phone = substr( $phone, 2 );
		}
		$len = strlen( $phone );
		return ( 10 === $len || 11 === $len ) && '0' !== $phone[0];
	}

	/**
	 * Format a phone as (00) 00000-0000 or (00) 0000-0000.
	 *
	 * @param mixed $value Phone.
	 * @return string
	 */
	public static function format_phone( $value ) {
		$phone = self::digits( $value );
		$len   = strlen( $phone );
		if ( 11 === $len ) {
			return '(' . substr( $phone, 0, 2 ) . ') ' . substr( $phone, 2, 5 ) . '-' . substr( $phone, 7 );
		}
		if ( 10 === $len ) {
			return '(' . substr( $phone, 0, 2 ) . ') ' . substr( $phone, 2, 4 ) . '-' . substr( $phone, 6 );
		}
		return trim( (string) $value );
	}

	/**
	 * Format a CEP as 00000-000.
	 *
	 * @param mixed $value CEP.
	 * @return string
	 */
	public static function format_cep( $value ) {
		$cep = self::digits( $value );
		return 8 === strlen( $cep ) ? substr( $cep, 0, 5 ) . '-' . substr( $cep, 5 ) : trim( (string) $value );
	}

	/**
	 * Is this a real dd/mm/yyyy date in the past?
	 *
	 * @param mixed $value Date.
	 * @return bool
	 */
	public static function is_birthdate( $value ) {
		if ( ! preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', trim( (string) $value ), $m ) ) {
			return false;
		}
		if ( ! checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
			return false;
		}
		$year = (int) $m[3];
		return $year >= 1900 && sprintf( '%04d%02d%02d', $year, $m[2], $m[1] ) <= gmdate( 'Ymd' );
	}

	/**
	 * Accept dd/mm/yyyy or yyyy-mm-dd (date inputs) and return dd/mm/yyyy.
	 *
	 * @param mixed $value Date.
	 * @return string
	 */
	public static function format_birthdate( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return $m[3] . '/' . $m[2] . '/' . $m[1];
		}
		$digits = self::digits( $value );
		if ( 8 === strlen( $digits ) && ! preg_match( '#^\d{2}/\d{2}/\d{4}$#', $value ) ) {
			return substr( $digits, 0, 2 ) . '/' . substr( $digits, 2, 2 ) . '/' . substr( $digits, 4 );
		}
		return $value;
	}
}
