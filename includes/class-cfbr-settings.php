<?php
/**
 * Settings storage.
 *
 * @package CheckoutFacilBrasil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the cfbr_settings option.
 */
class CFBR_Settings {

	const OPTION = 'cfbr_settings';

	/**
	 * Cached settings for this request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'person_type'           => 'both',
			'company_required'      => 1,
			'rg'                    => 'off',
			'ie'                    => 'off',
			'birthdate'             => 'off',
			'gender'                => 'off',
			'cellphone'             => 'off',
			'neighborhood_required' => 1,
			'no_number'             => 1,
			'masks'                 => 1,
			'only_brazil'           => 1,
		);
	}

	/**
	 * Allowed values for the choice settings.
	 *
	 * @return array
	 */
	public static function choices() {
		return array(
			'person_type' => array( 'both', 'pf', 'pj', 'none' ),
			'rg'          => array( 'off', 'optional', 'required' ),
			'ie'          => array( 'off', 'optional', 'required' ),
			'birthdate'   => array( 'off', 'optional', 'required' ),
			'gender'      => array( 'off', 'optional', 'required' ),
			'cellphone'   => array( 'off', 'optional', 'required', 'replace_phone' ),
		);
	}

	/**
	 * Get all settings, or one.
	 *
	 * @param string|null $key Setting key.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = self::sanitize( is_array( $saved ) ? $saved : array() );
		}
		if ( null === $key ) {
			return self::$cache;
		}
		return isset( self::$cache[ $key ] ) ? self::$cache[ $key ] : null;
	}

	/**
	 * Clean a settings array, keeping defaults for anything missing or unknown.
	 *
	 * @param array $input Raw values.
	 * @return array
	 */
	public static function sanitize( array $input ) {
		$clean   = self::defaults();
		$choices = self::choices();
		foreach ( $clean as $key => $default ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			if ( isset( $choices[ $key ] ) ) {
				$value         = sanitize_key( (string) $input[ $key ] );
				$clean[ $key ] = in_array( $value, $choices[ $key ], true ) ? $value : $default;
			} else {
				$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			}
		}
		return $clean;
	}

	/**
	 * Save settings.
	 *
	 * @param array $values Values to merge over the current settings.
	 * @return void
	 */
	public static function save( array $values ) {
		$merged = self::sanitize( array_merge( self::get(), $values ) );
		update_option( self::OPTION, $merged );
		self::$cache = $merged;
		self::show_company_field();
	}

	/**
	 * Razão social uses WooCommerce's own Company field, so it must not be hidden when companies buy.
	 *
	 * @return void
	 */
	public static function show_company_field() {
		$s = self::get();
		if ( $s['company_required'] && in_array( $s['person_type'], array( 'both', 'pj' ), true ) && 'hidden' === get_option( 'woocommerce_checkout_company_field', 'optional' ) ) {
			update_option( 'woocommerce_checkout_company_field', 'optional' );
		}
	}

	/**
	 * Forget the cached copy (after a direct option change).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Is a field off, optional or required? Works for rg, ie, birthdate, gender, cellphone.
	 *
	 * @param string $field Field id.
	 * @return string off|optional|required
	 */
	public static function mode( $field ) {
		$value = self::get( $field );
		if ( 'cellphone' === $field && 'replace_phone' === $value ) {
			return 'off';
		}
		return in_array( $value, array( 'optional', 'required' ), true ) ? $value : 'off';
	}

	/**
	 * Does the store ask for CPF?
	 *
	 * @return bool
	 */
	public static function wants_cpf() {
		return in_array( self::get( 'person_type' ), array( 'both', 'pf' ), true );
	}

	/**
	 * Does the store ask for CNPJ?
	 *
	 * @return bool
	 */
	public static function wants_cnpj() {
		return in_array( self::get( 'person_type' ), array( 'both', 'pj' ), true );
	}
}
