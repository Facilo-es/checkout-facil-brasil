<?php
/**
 * Block checkout: fields through WooCommerce's Additional Checkout Fields API.
 *
 * @package CheckoutFacilBrasil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block checkout fields, validation and the mirror into the legacy keys.
 */
class CFBR_Fields_Blocks {

	const CONTACT = array( 'persontype', 'cpf', 'rg', 'cnpj', 'ie', 'ie_exempt', 'birthdate', 'gender', 'cellphone' );
	const ADDRESS = array( 'number', 'nonumber', 'neighborhood' );

	/**
	 * The Store API checkout request being validated.
	 *
	 * @var WP_REST_Request|null
	 */
	private static $request = null;

	/**
	 * Values mirrored onto the last block order, for the customer copy.
	 *
	 * @var array|null
	 */
	private static $last_values = null;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_init', array( __CLASS__, 'register' ) );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'capture' ), 10, 3 );
		add_filter( 'woocommerce_sanitize_additional_field', array( __CLASS__, 'sanitize' ), 10, 2 );
		add_action( 'woocommerce_validate_additional_field', array( __CLASS__, 'validate_field' ), 10, 3 );
		add_action( 'woocommerce_blocks_validate_location_contact_fields', array( __CLASS__, 'validate_contact' ), 10, 3 );
		add_action( 'woocommerce_blocks_validate_location_address_fields', array( __CLASS__, 'validate_address' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'mirror_order' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'mirror_customer' ), 20 );
		add_action( 'woocommerce_save_account_details', array( __CLASS__, 'account_saved' ), 20 );
		add_action( 'woocommerce_customer_save_address', array( __CLASS__, 'address_saved' ), 20, 2 );
		add_filter( 'woocommerce_admin_billing_fields', array( __CLASS__, 'strip_admin_fields' ), 99 );
		add_filter( 'woocommerce_admin_shipping_fields', array( __CLASS__, 'strip_admin_fields' ), 99 );
		add_filter( 'woocommerce_filter_fields_for_order_confirmation', array( __CLASS__, 'hide_in_confirmation' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		foreach ( array_merge( self::CONTACT, self::ADDRESS ) as $id ) {
			add_filter( 'woocommerce_get_default_value_for_' . self::key( $id ), array( __CLASS__, 'default_value' ), 10, 3 );
		}
	}

	/**
	 * Full field id.
	 *
	 * @param string $id Our id.
	 * @return string
	 */
	public static function key( $id ) {
		return CFBR_Fields::NS . '/' . $id;
	}

	/**
	 * Does this WooCommerce support conditional hidden/required rules (9.9+)?
	 *
	 * @return bool
	 */
	public static function rules_supported() {
		$ok = defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '9.9', '>=' );
		/**
		 * Filter whether conditional field rules are used on the block checkout.
		 *
		 * @param bool $ok True on WooCommerce 9.9 and later.
		 */
		return (bool) apply_filters( 'cfbr_block_rules', $ok );
	}

	/**
	 * Is the current page a checkout that uses the Checkout block?
	 *
	 * @return bool
	 */
	public static function is_block_checkout() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return false;
		}
		$page = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'checkout' ) : 0;
		if ( $page > 0 && is_page( $page ) ) {
			return has_block( 'woocommerce/checkout', $page );
		}
		return has_block( 'woocommerce/checkout' );
	}

	/* ---------------------------------------------------------------------
	 * Rules (JSON Schema against WooCommerce's document object).
	 * ------------------------------------------------------------------- */

	/**
	 * Wrap a customer-level schema.
	 *
	 * @param array $customer Properties under customer.
	 * @return array
	 */
	private static function on_customer( array $customer ) {
		return array(
			'type'       => 'object',
			'required'   => array( 'customer' ),
			'properties' => array(
				'customer' => array(
					'type'       => 'object',
					'properties' => $customer,
				),
			),
		);
	}

	/**
	 * A contact field equals a value.
	 *
	 * @param string $id    Our id.
	 * @param mixed  $value Value.
	 * @return array
	 */
	private static function contact_is( $id, $value ) {
		return self::on_customer(
			array(
				'additional_fields' => array(
					'type'       => 'object',
					'required'   => array( self::key( $id ) ),
					'properties' => array( self::key( $id ) => array( 'const' => $value ) ),
				),
			)
		);
	}

	/**
	 * The billing country is set and is not Brazil.
	 *
	 * @return array
	 */
	private static function billing_foreign() {
		return self::on_customer(
			array(
				'billing_address' => array(
					'type'       => 'object',
					'required'   => array( 'country' ),
					'properties' => array( 'country' => array( 'not' => array( 'enum' => array( 'BR', '' ) ) ) ),
				),
			)
		);
	}

	/**
	 * The address being edited is in Brazil.
	 *
	 * @return array
	 */
	private static function address_in_brazil() {
		return self::on_customer(
			array(
				'address' => array(
					'type'       => 'object',
					'required'   => array( 'country' ),
					'properties' => array( 'country' => array( 'const' => 'BR' ) ),
				),
			)
		);
	}

	/**
	 * The No number box is ticked on the address being edited.
	 *
	 * @return array
	 */
	private static function nonumber_ticked() {
		return self::on_customer(
			array(
				'address' => array(
					'type'       => 'object',
					'required'   => array( self::key( 'nonumber' ) ),
					'properties' => array( self::key( 'nonumber' ) => array( 'enum' => array( true, '1', 1 ) ) ),
				),
			)
		);
	}

	/**
	 * Any of several conditions.
	 *
	 * @param array $rules Schemas.
	 * @return array|false False when no rule applies.
	 */
	private static function any( array $rules ) {
		$rules = array_values( array_filter( $rules ) );
		if ( ! $rules ) {
			return false;
		}
		return 1 === count( $rules ) ? $rules[0] : array( 'anyOf' => $rules );
	}

	/**
	 * Negation of a hidden rule: shown. False hidden means always shown.
	 *
	 * @param array|false $hidden Hidden rule.
	 * @return array|true
	 */
	private static function shown( $hidden ) {
		return false === $hidden ? true : array( 'not' => $hidden );
	}

	/**
	 * Hidden rule for a document field of one person type.
	 *
	 * @param string $kind pf|pj|any.
	 * @return array|false
	 */
	private static function doc_hidden( $kind ) {
		$both    = 'both' === CFBR_Settings::get( 'person_type' );
		$foreign = CFBR_Settings::get( 'only_brazil' ) ? self::billing_foreign() : false;
		$type    = false;
		if ( $both && 'pf' === $kind ) {
			$type = self::contact_is( 'persontype', '2' );
		} elseif ( $both && 'pj' === $kind ) {
			$type = array( 'not' => self::contact_is( 'persontype', '2' ) );
		}
		return self::any( array( $type, $foreign ) );
	}

	/* ---------------------------------------------------------------------
	 * Registration.
	 * ------------------------------------------------------------------- */

	/**
	 * Field options for registration, keyed by our id.
	 *
	 * @return array
	 */
	public static function field_options() {
		$defs    = CFBR_Fields::definitions();
		$rules   = self::rules_supported();
		$type    = CFBR_Settings::get( 'person_type' );
		$options = array();

		$base = function ( $id, array $extra ) use ( $defs ) {
			$def = $defs[ $id ];
			return array_merge(
				array(
					'id'                         => self::key( $id ),
					'label'                      => $def['label'],
					'optionalLabel'              => $def['label'],
					'location'                   => 'address' === $def['location'] ? 'address' : 'contact',
					'type'                       => 'text',
					'required'                   => false,
					'show_in_order_confirmation' => false,
					'attributes'                 => array( 'autocomplete' => 'off' ),
				),
				$extra
			);
		};
		$req  = function ( $hidden, $required ) use ( $rules ) {
			if ( ! $rules ) {
				return array( 'required' => false );
			}
			$out = array();
			if ( false !== $hidden ) {
				$out['hidden'] = $hidden;
			}
			if ( $required ) {
				$out['required'] = self::shown( $hidden );
			}
			return $out;
		};

		if ( 'none' !== $type ) {
			if ( 'both' === $type ) {
				$hidden = CFBR_Settings::get( 'only_brazil' ) ? self::billing_foreign() : false;
				$choice = array();
				foreach ( $defs['persontype']['options'] as $value => $label ) {
					$choice[] = array(
						'value' => (string) $value,
						'label' => $label,
					);
				}
				$options['persontype'] = $base(
					'persontype',
					array_merge(
						array(
							'type'    => 'select',
							'options' => $choice,
						),
						$rules ? $req( $hidden, true ) : array( 'required' => true )
					)
				);
			}
			if ( CFBR_Settings::wants_cpf() ) {
				$hidden         = self::doc_hidden( 'pf' );
				$options['cpf'] = $base(
					'cpf',
					array_merge(
						array(
							'attributes' => array(
								'autocomplete' => 'off',
								'maxLength'    => 14,
								'data-cfbr'    => 'cpf',
							),
						),
						$req( $hidden, true )
					)
				);
				$rg             = CFBR_Settings::mode( 'rg' );
				if ( 'off' !== $rg ) {
					$options['rg'] = $base( 'rg', $req( $hidden, 'required' === $rg ) );
				}
			}
			if ( CFBR_Settings::wants_cnpj() ) {
				$hidden          = self::doc_hidden( 'pj' );
				$options['cnpj'] = $base(
					'cnpj',
					array_merge(
						array(
							'attributes' => array(
								'autocomplete'   => 'off',
								'autocapitalize' => 'characters',
								'maxLength'      => 18,
								'data-cfbr'      => 'cnpj',
							),
						),
						$req( $hidden, true )
					)
				);
				$ie              = CFBR_Settings::mode( 'ie' );
				if ( 'off' !== $ie ) {
					$ie_opts = $req( $hidden, false );
					if ( $rules && 'required' === $ie ) {
						$ie_opts['required'] = array(
							'allOf' => array_values(
								array_filter(
									array(
										false === $hidden ? false : array( 'not' => $hidden ),
										array( 'not' => self::contact_is( 'ie_exempt', true ) ),
									)
								)
							),
						);
					}
					$options['ie']        = $base( 'ie', $ie_opts );
					$options['ie_exempt'] = $base(
						'ie_exempt',
						array_merge(
							array(
								'type'       => 'checkbox',
								'attributes' => array(),
							),
							$req( $hidden, false )
						)
					);
				}
			}
		}

		foreach ( array( 'birthdate', 'gender', 'cellphone' ) as $id ) {
			$mode = CFBR_Settings::mode( $id );
			if ( 'off' === $mode ) {
				continue;
			}
			$extra = array( 'required' => 'required' === $mode );
			if ( 'gender' === $id ) {
				$choice = array();
				foreach ( $defs['gender']['options'] as $value => $label ) {
					$choice[] = array(
						'value' => (string) $value,
						'label' => $label,
					);
				}
				$extra['type']    = 'select';
				$extra['options'] = $choice;
			} elseif ( 'birthdate' === $id ) {
				$extra['attributes'] = array(
					'autocomplete' => 'bday',
					'maxLength'    => 10,
					'data-cfbr'    => 'date',
				);
			} else {
				$extra['attributes'] = array(
					'autocomplete' => 'tel',
					'maxLength'    => 15,
					'data-cfbr'    => 'phone',
				);
			}
			$options[ $id ] = $base( $id, $extra );
		}

		$in_br          = $rules ? self::address_in_brazil() : null;
		$hidden_address = $rules ? array( 'not' => $in_br ) : false;
		$number         = array( 'attributes' => array( 'maxLength' => 10 ) );
		if ( $rules ) {
			$number['hidden']   = $hidden_address;
			$number['required'] = CFBR_Settings::get( 'no_number' )
				? array( 'allOf' => array( $in_br, array( 'not' => self::nonumber_ticked() ) ) )
				: $in_br;
		}
		$options['number'] = $base( 'number', $number );
		if ( CFBR_Settings::get( 'no_number' ) ) {
			$nonumber = array(
				'type'       => 'checkbox',
				'attributes' => array(),
			);
			if ( $rules ) {
				$nonumber['hidden'] = $hidden_address;
			}
			$options['nonumber'] = $base( 'nonumber', $nonumber );
		}
		$neigh = array();
		if ( $rules ) {
			$neigh['hidden'] = $hidden_address;
			if ( CFBR_Settings::get( 'neighborhood_required' ) ) {
				$neigh['required'] = $in_br;
			}
		}
		$options['neighborhood'] = $base( 'neighborhood', $neigh );

		/**
		 * Filter the options passed to woocommerce_register_additional_checkout_field().
		 *
		 * @param array $options Options keyed by our field id.
		 * @param bool  $rules   Whether conditional rules are in use.
		 */
		return apply_filters( 'cfbr_block_field_options', $options, $rules );
	}

	/**
	 * Register the fields.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}
		foreach ( self::field_options() as $options ) {
			woocommerce_register_additional_checkout_field( $options );
		}
	}

	/**
	 * Is this one of our fields? Returns our short id or ''.
	 *
	 * @param string $key Full key.
	 * @return string
	 */
	private static function ours( $key ) {
		$prefix = CFBR_Fields::NS . '/';
		return 0 === strpos( (string) $key, $prefix ) ? substr( $key, strlen( $prefix ) ) : '';
	}

	/* ---------------------------------------------------------------------
	 * Sanitize and validate.
	 * ------------------------------------------------------------------- */

	/**
	 * Store masked, upper-case values.
	 *
	 * @param mixed  $value Value.
	 * @param string $key   Full field key.
	 * @return mixed
	 */
	public static function sanitize( $value, $key ) {
		$id = self::ours( $key );
		if ( '' === $id || ! is_scalar( $value ) ) {
			return $value;
		}
		$value = trim( (string) $value );
		switch ( $id ) {
			case 'cpf':
				return CFBR_Validator::is_cpf( $value ) ? CFBR_Validator::format_cpf( $value ) : $value;
			case 'cnpj':
				$value = strtoupper( $value );
				return CFBR_Validator::is_cnpj( $value ) ? CFBR_Validator::format_cnpj( $value ) : $value;
			case 'cellphone':
				return CFBR_Validator::is_phone( $value ) ? CFBR_Validator::format_phone( $value ) : $value;
			case 'birthdate':
				return CFBR_Validator::format_birthdate( $value );
			case 'ie':
			case 'rg':
				return strtoupper( $value );
			case 'number':
				return 0 === strcasecmp( $value, CFBR_Fields::no_number_value() ) ? CFBR_Fields::no_number_value() : $value;
		}
		return $value;
	}

	/**
	 * Check one field's value (only called for non-empty values of shown fields).
	 *
	 * @param WP_Error $errors Errors.
	 * @param string   $key    Full key.
	 * @param mixed    $value  Value.
	 * @return void
	 */
	public static function validate_field( $errors, $key, $value ) {
		$id = self::ours( $key );
		if ( '' === $id || ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
			return;
		}
		$defs  = CFBR_Fields::definitions();
		$value = (string) $value;
		$bad   = false;
		switch ( $id ) {
			case 'cpf':
				$bad = ! CFBR_Validator::is_cpf( $value );
				break;
			case 'cnpj':
				$bad = ! CFBR_Validator::is_cnpj( $value );
				break;
			case 'birthdate':
				$bad = ! CFBR_Validator::is_birthdate( CFBR_Validator::format_birthdate( $value ) );
				break;
			case 'cellphone':
				$bad = ! CFBR_Validator::is_phone( $value );
				break;
			case 'gender':
				$bad = ! isset( $defs['gender']['options'][ $value ] );
				break;
		}
		if ( $bad ) {
			$message = ! empty( $defs[ $id ]['invalid'] ) ? $defs[ $id ]['invalid'] : $defs[ $id ]['required'];
			$errors->add( 'cfbr_' . $id, $message );
		}
	}

	/**
	 * Keep the Store API checkout request so location checks can see the whole form.
	 *
	 * @param mixed           $result  Result.
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function capture( $result, $server, $request ) {
		if ( $request instanceof WP_REST_Request && preg_match( '#^/wc/store(/v\d+)?/checkout$#', $request->get_route() ) ) {
			self::$request = $request;
		}
		return $result;
	}

	/**
	 * Values from a block checkout request, in the shape normalise() expects.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function raw_from_request( $request ) {
		$contact  = (array) $request->get_param( 'additional_fields' );
		$billing  = (array) $request->get_param( 'billing_address' );
		$shipping = (array) $request->get_param( 'shipping_address' );
		$raw      = array();
		foreach ( self::CONTACT as $id ) {
			if ( isset( $contact[ self::key( $id ) ] ) ) {
				$raw[ $id ] = $contact[ self::key( $id ) ];
			}
		}
		$raw['company'] = isset( $billing['company'] ) ? $billing['company'] : '';
		foreach ( array( 'country', 'state', 'postcode' ) as $part ) {
			$raw[ 'billing_' . $part ] = isset( $billing[ $part ] ) ? $billing[ $part ] : '';
		}
		$needs_shipping           = function_exists( 'WC' ) && WC()->cart && WC()->cart->needs_shipping();
		$raw['ship_to_different'] = $needs_shipping && ! empty( $shipping['country'] );
		$addresses                = array(
			'billing'  => $billing,
			'shipping' => $raw['ship_to_different'] ? $shipping : $billing,
		);
		foreach ( $addresses as $scope => $address ) {
			$raw[ $scope . '_country' ] = isset( $address['country'] ) ? $address['country'] : '';
			foreach ( self::ADDRESS as $id ) {
				$raw[ $scope . '_' . $id ] = isset( $address[ self::key( $id ) ] ) ? $address[ self::key( $id ) ] : '';
			}
		}
		if ( 'replace_phone' === CFBR_Settings::get( 'cellphone' ) && isset( $billing['phone'] ) ) {
			$raw['phone'] = $billing['phone'];
		}
		return $raw;
	}

	/**
	 * Is a normalised value empty for the field behind an error code?
	 *
	 * @param array  $data Normalised data.
	 * @param string $code Error code.
	 * @return bool
	 */
	private static function empty_for( array $data, $code ) {
		return isset( $data[ $code ] ) && ( '' === $data[ $code ] || false === $data[ $code ] );
	}

	/**
	 * Cross-field checks for the contact fields: Razão social for companies, IE for the state,
	 * and, without conditional rules, which document is required.
	 *
	 * @param WP_Error $errors Errors.
	 * @param array    $fields Contact values keyed by full key.
	 * @param string   $group  Group.
	 * @return void
	 */
	public static function validate_contact( $errors, $fields, $group = 'other' ) {
		$checkout = self::$request && 'POST' === self::$request->get_method();
		if ( self::$request && ! $checkout ) {
			return; // Partial updates while the shopper types.
		}
		if ( $checkout ) {
			$raw = self::raw_from_request( self::$request );
		} else {
			$raw      = array();
			$customer = function_exists( 'WC' ) && WC()->customer ? WC()->customer : null;
			foreach ( self::CONTACT as $id ) {
				if ( isset( $fields[ self::key( $id ) ] ) ) {
					$raw[ $id ] = $fields[ self::key( $id ) ];
				}
			}
			if ( $customer ) {
				$raw['billing_country'] = $customer->get_billing_country();
				$raw['billing_state']   = $customer->get_billing_state();
			}
		}
		$data        = CFBR_Fields::normalise( $raw );
		$result      = CFBR_Fields::validate( $data, $checkout ? 'block' : 'account', false );
		$rules       = self::rules_supported();
		$field_level = array( 'cpf', 'cnpj', 'birthdate', 'gender', 'cellphone' );
		foreach ( $result->get_error_codes() as $code ) {
			$empty = self::empty_for( $data, $code );
			if ( 'company' === $code && ( ! $checkout || ! $rules ) ) {
				continue; // Not this form; before 9.9 the Company field may be hidden by the block, so the script asks only when it shows.
			}
			if ( in_array( $code, $field_level, true ) && ! $empty ) {
				continue; // Already reported by validate_field().
			}
			if ( $empty && in_array( $code, array( 'birthdate', 'gender', 'cellphone' ), true ) ) {
				continue; // Plain required fields: WooCommerce reports them.
			}
			if ( $empty && $rules && in_array( $code, array( 'cpf', 'cnpj', 'rg', 'ie' ), true ) ) {
				continue; // Conditional rules make WooCommerce report them.
			}
			foreach ( $result->get_error_messages( $code ) as $message ) {
				$errors->add( 'cfbr_' . $code, $message );
			}
		}
	}

	/**
	 * Número and Bairro without conditional rules (WooCommerce 8.9 to 9.8).
	 *
	 * @param WP_Error $errors Errors.
	 * @param array    $fields Address values (the whole address at checkout).
	 * @param string   $group  billing|shipping.
	 * @return void
	 */
	public static function validate_address( $errors, $fields, $group = 'billing' ) {
		if ( self::rules_supported() || ( self::$request && 'POST' !== self::$request->get_method() ) ) {
			return;
		}
		if ( ! self::$request && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return; // Cart updates while the customer types: check only when the order is placed.
		}
		$fields  = (array) $fields;
		$country = isset( $fields['country'] ) ? $fields['country'] : '';
		if ( '' === $country ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- My Account form, nonce checked by WooCommerce.
			$country = isset( $_POST[ $group . '_country' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $group . '_country' ] ) ) : '';
		}
		if ( 'BR' !== strtoupper( $country ) ) {
			return;
		}
		$defs     = CFBR_Fields::definitions();
		$number   = isset( $fields[ self::key( 'number' ) ] ) ? trim( (string) $fields[ self::key( 'number' ) ] ) : '';
		$nonumber = ! empty( $fields[ self::key( 'nonumber' ) ] ) && 'false' !== $fields[ self::key( 'nonumber' ) ];
		$neigh    = isset( $fields[ self::key( 'neighborhood' ) ] ) ? trim( (string) $fields[ self::key( 'neighborhood' ) ] ) : '';
		if ( '' === $number && ! $nonumber ) {
			$errors->add( 'cfbr_' . $group . '_number', $defs['number']['required'] );
		}
		if ( '' === $neigh && CFBR_Settings::get( 'neighborhood_required' ) ) {
			$errors->add( 'cfbr_' . $group . '_neighborhood', $defs['neighborhood']['required'] );
		}
	}

	/* ---------------------------------------------------------------------
	 * Mirror into the legacy keys.
	 * ------------------------------------------------------------------- */

	/**
	 * Write the legacy keys on a block order.
	 *
	 * @param WC_Order        $order   Order.
	 * @param WP_REST_Request $request Request.
	 * @return void
	 */
	public static function mirror_order( $order, $request ) {
		try {
			$values = CFBR_Fields::normalise( self::raw_from_request( $request ) );
			CFBR_Order_Data::save_legacy( $order, $values );
			$order->save();
			self::$last_values = $values;
		} catch ( Exception $e ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->warning( 'Checkout Fácil Brasil could not copy the fields: ' . $e->getMessage(), array( 'source' => 'facilo-checkout-facil-brasil' ) );
			}
		}
	}

	/**
	 * Copy the values to the customer once the account exists.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function mirror_customer( $order ) {
		if ( null === self::$last_values || ! $order instanceof WC_Order || ! $order->get_customer_id() ) {
			return;
		}
		$customer = new WC_Customer( $order->get_customer_id() );
		CFBR_Order_Data::save_customer_legacy( $customer, self::$last_values );
		$customer->save();
	}

	/**
	 * After WooCommerce saves the account details form, copy our contact fields to the legacy user meta.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public static function account_saved( $user_id ) {
		if ( wc_notice_count( 'error' ) > 0 ) {
			return;
		}
		$customer = new WC_Customer( $user_id );
		$raw      = array();
		foreach ( self::CONTACT as $id ) {
			$raw[ $id ] = $customer->get_meta( '_wc_other/' . self::key( $id ) );
		}
		$raw['billing_country'] = $customer->get_billing_country();
		$raw['company']         = $customer->get_billing_company();
		self::copy_contact_to_customer( $customer, CFBR_Fields::normalise( $raw ) );
		$customer->save();
	}

	/**
	 * Contact legacy keys on a customer.
	 *
	 * @param WC_Customer $customer Customer.
	 * @param array       $data     Normalised data.
	 * @return void
	 */
	private static function copy_contact_to_customer( $customer, array $data ) {
		$data['billing_country']  = '' === $data['billing_country'] ? 'BR' : $data['billing_country'];
		$data['shipping_country'] = '';
		$legacy                   = CFBR_Fields::legacy_values( $data );
		foreach ( array( 'persontype', 'cpf', 'rg', 'cnpj', 'ie', 'birthdate', 'gender', 'cellphone' ) as $id ) {
			if ( isset( $legacy[ 'billing_' . $id ] ) ) {
				$customer->update_meta_data( 'billing_' . $id, $legacy[ 'billing_' . $id ] );
			}
		}
	}

	/**
	 * After WooCommerce saves an address in My Account, copy Número and Bairro to the legacy user meta.
	 *
	 * @param int    $user_id      User id.
	 * @param string $address_type billing|shipping.
	 * @return void
	 */
	public static function address_saved( $user_id, $address_type ) {
		if ( wc_notice_count( 'error' ) > 0 || ! in_array( $address_type, array( 'billing', 'shipping' ), true ) ) {
			return;
		}
		$customer = new WC_Customer( $user_id );
		$prefix   = '_wc_' . $address_type . '/';
		$number   = (string) $customer->get_meta( $prefix . self::key( 'number' ) );
		$nonumber = $customer->get_meta( $prefix . self::key( 'nonumber' ) );
		if ( ! empty( $nonumber ) && '0' !== (string) $nonumber ) {
			$number = CFBR_Fields::no_number_value();
		}
		$customer->update_meta_data( $address_type . '_number', $number );
		$customer->update_meta_data( $address_type . '_neighborhood', (string) $customer->get_meta( $prefix . self::key( 'neighborhood' ) ) );
		$customer->delete_meta_data( $address_type . '_nonumber' );
		$customer->save();
	}

	/**
	 * Show values saved by the classic checkout or the old plugin when the block field has none.
	 *
	 * @param mixed   $value     Null.
	 * @param string  $group     billing|shipping|other.
	 * @param WC_Data $wc_object Customer or order.
	 * @return mixed
	 */
	public static function default_value( $value, $group, $wc_object ) {
		if ( null !== $value || ! $wc_object instanceof WC_Data ) {
			return $value;
		}
		$id     = self::ours( str_replace( 'woocommerce_get_default_value_for_', '', current_filter() ) );
		$scope  = 'shipping' === $group ? 'shipping' : 'billing';
		$prefix = $wc_object instanceof WC_Order ? '_' : '';
		if ( in_array( $id, array( 'ie_exempt', 'nonumber' ), true ) ) {
			$source = 'ie_exempt' === $id ? 'billing_ie' : $scope . '_number';
			$stored = (string) $wc_object->get_meta( $prefix . $source );
			$flag   = 'ie_exempt' === $id ? CFBR_Fields::ie_exempt_value() : CFBR_Fields::no_number_value();
			return '' !== $stored && 0 === strcasecmp( $stored, $flag ) ? true : null;
		}
		$key    = in_array( $id, self::ADDRESS, true ) ? $scope . '_' . $id : 'billing_' . $id;
		$stored = (string) $wc_object->get_meta( $prefix . $key );
		if ( '' === $stored ) {
			return null;
		}
		if ( ( 'ie' === $id && 0 === strcasecmp( $stored, CFBR_Fields::ie_exempt_value() ) ) || ( 'number' === $id && 0 === strcasecmp( $stored, CFBR_Fields::no_number_value() ) ) ) {
			return null;
		}
		return $stored;
	}

	/**
	 * Our block fields are edited in the Brazilian card, not twice in WooCommerce's address forms.
	 *
	 * @param array $fields Admin fields.
	 * @return array
	 */
	public static function strip_admin_fields( $fields ) {
		foreach ( array_keys( (array) $fields ) as $key ) {
			if ( '' !== self::ours( $key ) || false !== strpos( (string) $key, CFBR_Fields::NS . '/' ) ) {
				unset( $fields[ $key ] );
			}
		}
		return $fields;
	}

	/**
	 * Our own block shows the values on the thank-you page and in emails.
	 *
	 * @param bool  $show  Show.
	 * @param array $field Field.
	 * @return bool
	 */
	public static function hide_in_confirmation( $show, $field ) {
		return isset( $field['id'] ) && '' !== self::ours( $field['id'] ) ? false : $show;
	}

	/**
	 * Scripts for the Checkout block.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::is_block_checkout() ) {
			return;
		}
		$deps = array( 'cfbr-masks', 'wp-data' );
		if ( wp_script_is( 'wc-blocks-data-store', 'registered' ) ) {
			$deps[] = 'wc-blocks-data-store';
		}
		wp_enqueue_style( 'cfbr-checkout', CFBR_URL . 'assets/css/checkout.css', array(), CFBR_VERSION );
		wp_register_script( 'cfbr-masks', CFBR_URL . 'assets/js/masks.js', array(), CFBR_VERSION, true );
		wp_enqueue_script( 'cfbr-blocks', CFBR_URL . 'assets/js/blocks.js', $deps, CFBR_VERSION, true );
		$data          = CFBR_Plugin::script_data( 'block' );
		$data['rules'] = self::rules_supported();
		$data['help']  = array(
			'cnpj'     => CFBR_Fields::definitions()['cnpj']['help'],
			'fallback' => __( 'Fill in the CPF or the CNPJ, depending on the person type', 'facilo-checkout-facil-brasil' ),
		);
		wp_add_inline_script( 'cfbr-blocks', 'window.cfbrData = ' . wp_json_encode( $data ) . ';', 'before' );
	}
}
