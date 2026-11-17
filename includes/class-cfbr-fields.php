<?php
/**
 * Field list, normalising and validation shared by both checkouts.
 *
 * @package CheckoutFacilBrasil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Brazilian checkout fields.
 */
class CFBR_Fields {

	const NS = 'checkout-facil-brasil';

	/**
	 * Legacy order meta keys (without the leading underscore) shared with the old plugin.
	 *
	 * @var string[]
	 */
	const LEGACY_BILLING = array( 'persontype', 'cpf', 'rg', 'cnpj', 'ie', 'birthdate', 'gender', 'cellphone', 'number', 'neighborhood' );

	/**
	 * Field definitions. Labels, placeholders and help pass through the cfbr_field_definitions filter.
	 *
	 * @return array
	 */
	public static function definitions() {
		$fields = array(
			'persontype'   => array(
				'label'    => __( 'Person type', 'checkout-facil-brasil' ),
				'location' => 'contact',
				'priority' => 22,
				'options'  => array(
					'1' => __( 'Individual', 'checkout-facil-brasil' ),
					'2' => __( 'Business', 'checkout-facil-brasil' ),
				),
			),
			'cpf'          => array(
				'label'       => __( 'CPF', 'checkout-facil-brasil' ),
				'placeholder' => '000.000.000-00',
				'location'    => 'contact',
				'priority'    => 23,
				'required'    => __( 'Enter the CPF.', 'checkout-facil-brasil' ),
				'invalid'     => __( 'The CPF you entered is not valid. Check the numbers.', 'checkout-facil-brasil' ),
			),
			'rg'           => array(
				'label'    => __( 'RG', 'checkout-facil-brasil' ),
				'location' => 'contact',
				'priority' => 24,
				'required' => __( 'Enter the RG.', 'checkout-facil-brasil' ),
			),
			'cnpj'         => array(
				'label'       => __( 'CNPJ', 'checkout-facil-brasil' ),
				'placeholder' => '00.000.000/0000-00',
				'help'        => __( 'Accepts the new CNPJ with letters.', 'checkout-facil-brasil' ),
				'location'    => 'contact',
				'priority'    => 26,
				'required'    => __( 'Enter the CNPJ.', 'checkout-facil-brasil' ),
				'invalid'     => __( 'The CNPJ you entered is not valid. Check the letters and numbers.', 'checkout-facil-brasil' ),
			),
			'ie'           => array(
				'label'    => __( 'State registration', 'checkout-facil-brasil' ),
				'location' => 'contact',
				'priority' => 27,
				'required' => __( 'Enter the state registration.', 'checkout-facil-brasil' ),
				'invalid'  => __( 'The state registration is not valid for the chosen state.', 'checkout-facil-brasil' ),
			),
			'ie_exempt'    => array(
				'label'    => __( 'Exempt', 'checkout-facil-brasil' ),
				'location' => 'contact',
				'priority' => 28,
			),
			'birthdate'    => array(
				'label'       => __( 'Date of birth', 'checkout-facil-brasil' ),
				'placeholder' => __( 'dd/mm/yyyy', 'checkout-facil-brasil' ),
				'location'    => 'contact',
				'priority'    => 31,
				'required'    => __( 'Enter the date of birth.', 'checkout-facil-brasil' ),
				'invalid'     => __( 'Enter the date of birth as dd/mm/yyyy.', 'checkout-facil-brasil' ),
			),
			'gender'       => array(
				'label'    => __( 'Gender', 'checkout-facil-brasil' ),
				'location' => 'contact',
				'priority' => 32,
				'required' => __( 'Choose an option for gender.', 'checkout-facil-brasil' ),
				'options'  => self::gender_options(),
			),
			'cellphone'    => array(
				'label'       => __( 'Cell phone', 'checkout-facil-brasil' ),
				'placeholder' => '(00) 00000-0000',
				'location'    => 'contact',
				'priority'    => 105,
				'required'    => __( 'Enter the cell phone.', 'checkout-facil-brasil' ),
				'invalid'     => __( 'Enter the cell phone with area code, such as (11) 91234-5678.', 'checkout-facil-brasil' ),
			),
			'number'       => array(
				'label'    => __( 'Number', 'checkout-facil-brasil' ),
				'location' => 'address',
				'priority' => 55,
				'required' => __( 'Enter the number.', 'checkout-facil-brasil' ),
			),
			'nonumber'     => array(
				'label'    => __( 'No number', 'checkout-facil-brasil' ),
				'location' => 'address',
				'priority' => 56,
			),
			'neighborhood' => array(
				'label'    => __( 'Neighbourhood', 'checkout-facil-brasil' ),
				'location' => 'address',
				'priority' => 65,
				'required' => __( 'Enter the neighbourhood.', 'checkout-facil-brasil' ),
			),
		);
		foreach ( $fields as $id => $field ) {
			$fields[ $id ] = array_merge(
				array(
					'id'          => $id,
					'placeholder' => '',
					'help'        => '',
				),
				$field
			);
		}
		/**
		 * Filter the Brazilian field definitions (labels, placeholders, help).
		 *
		 * @param array $fields Field definitions keyed by id.
		 */
		return apply_filters( 'cfbr_field_definitions', $fields );
	}

	/**
	 * Gender choices. Stored as the translated label, the same as the old plugin.
	 *
	 * @return array
	 */
	public static function gender_options() {
		$labels = array(
			__( 'Female', 'checkout-facil-brasil' ),
			__( 'Male', 'checkout-facil-brasil' ),
			__( 'Prefer not to say', 'checkout-facil-brasil' ),
		);
		return array_combine( $labels, $labels );
	}

	/**
	 * Messages for the company field.
	 *
	 * @return string
	 */
	public static function company_required_message() {
		return __( 'Enter the company legal name.', 'checkout-facil-brasil' );
	}

	/**
	 * Label for the company field in Brazil.
	 *
	 * @return string
	 */
	public static function company_label() {
		return __( 'Company legal name', 'checkout-facil-brasil' );
	}

	/**
	 * Text stored for an address with no number.
	 *
	 * @return string
	 */
	public static function no_number_value() {
		return 'S/N';
	}

	/**
	 * Text stored for an exempt state registration.
	 *
	 * @return string
	 */
	public static function ie_exempt_value() {
		return 'ISENTO';
	}

	/**
	 * Is WooCommerce's Company field shown at checkout?
	 *
	 * @return bool
	 */
	public static function company_enabled() {
		return 'hidden' !== get_option( 'woocommerce_checkout_company_field', 'optional' );
	}

	/**
	 * Is the person type field shown (setting "both")?
	 *
	 * @return bool
	 */
	public static function has_persontype() {
		return 'both' === CFBR_Settings::get( 'person_type' );
	}

	/**
	 * Clean and format raw values. Keys: persontype, cpf, cnpj, company, rg, ie, ie_exempt,
	 * birthdate, gender, cellphone, billing_country, billing_state, billing_postcode,
	 * billing_number, billing_nonumber, billing_neighborhood, ship_to_different,
	 * shipping_country, shipping_number, shipping_nonumber, shipping_neighborhood.
	 *
	 * @param array $raw Raw values.
	 * @return array
	 */
	public static function normalise( array $raw ) {
		$text = function ( $key ) use ( $raw ) {
			return isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? trim( sanitize_text_field( wp_unslash( (string) $raw[ $key ] ) ) ) : '';
		};
		$flag = function ( $key ) use ( $raw ) {
			return ! empty( $raw[ $key ] ) && 'false' !== $raw[ $key ] && '0' !== (string) $raw[ $key ];
		};

		$type = CFBR_Settings::get( 'person_type' );
		if ( 'pf' === $type ) {
			$persontype = '1';
		} elseif ( 'pj' === $type ) {
			$persontype = '2';
		} elseif ( 'none' === $type ) {
			$persontype = '';
		} else {
			$persontype = '2' === $text( 'persontype' ) ? '2' : '1';
		}

		$data = array(
			'persontype'        => $persontype,
			'cpf'               => $text( 'cpf' ),
			'cnpj'              => $text( 'cnpj' ),
			'company'           => $text( 'company' ),
			'rg'                => $text( 'rg' ),
			'ie'                => $text( 'ie' ),
			'ie_exempt'         => $flag( 'ie_exempt' ) || 0 === strcasecmp( $text( 'ie' ), self::ie_exempt_value() ),
			'birthdate'         => CFBR_Validator::format_birthdate( $text( 'birthdate' ) ),
			'gender'            => $text( 'gender' ),
			'cellphone'         => $text( 'cellphone' ),
			'billing_country'   => strtoupper( $text( 'billing_country' ) ),
			'billing_state'     => strtoupper( $text( 'billing_state' ) ),
			'billing_postcode'  => $text( 'billing_postcode' ),
			'ship_to_different' => $flag( 'ship_to_different' ),
			'shipping_country'  => strtoupper( $text( 'shipping_country' ) ),
		);

		if ( '' !== $data['cpf'] && CFBR_Validator::is_cpf( $data['cpf'] ) ) {
			$data['cpf'] = CFBR_Validator::format_cpf( $data['cpf'] );
		}
		if ( '' !== $data['cnpj'] && CFBR_Validator::is_cnpj( $data['cnpj'] ) ) {
			$data['cnpj'] = CFBR_Validator::format_cnpj( $data['cnpj'] );
		}
		if ( '' !== $data['cellphone'] && CFBR_Validator::is_phone( $data['cellphone'] ) ) {
			$data['cellphone'] = CFBR_Validator::format_phone( $data['cellphone'] );
		}
		if ( $data['ie_exempt'] ) {
			$data['ie'] = self::ie_exempt_value();
		}
		foreach ( array( 'billing', 'shipping' ) as $scope ) {
			$nonumber                         = $flag( $scope . '_nonumber' ) || self::no_number_value() === strtoupper( $text( $scope . '_number' ) );
			$data[ $scope . '_nonumber' ]     = $nonumber;
			$data[ $scope . '_number' ]       = $nonumber ? self::no_number_value() : $text( $scope . '_number' );
			$data[ $scope . '_neighborhood' ] = $text( $scope . '_neighborhood' );
		}
		return $data;
	}

	/**
	 * Are the document fields (person type, CPF, CNPJ, RG, IE) active for this billing country?
	 *
	 * @param string $country Billing country.
	 * @return bool
	 */
	public static function documents_apply( $country ) {
		if ( 'none' === CFBR_Settings::get( 'person_type' ) ) {
			return false;
		}
		return ! CFBR_Settings::get( 'only_brazil' ) || '' === $country || 'BR' === $country;
	}

	/**
	 * Validate normalised data. Adds errors keyed by field id.
	 *
	 * @param array  $data    Output of normalise().
	 * @param string $context classic|block|account|register.
	 * @param bool   $address Also check Número and Bairro.
	 * @return WP_Error
	 */
	public static function validate( array $data, $context = 'classic', $address = true ) {
		$errors = new WP_Error();
		$defs   = self::definitions();
		$docs   = self::documents_apply( $data['billing_country'] );
		$is_pf  = '1' === $data['persontype'];
		$is_pj  = '2' === $data['persontype'];

		if ( $docs && $is_pf ) {
			if ( '' === $data['cpf'] ) {
				$errors->add( 'cpf', $defs['cpf']['required'] );
			} elseif ( ! CFBR_Validator::is_cpf( $data['cpf'] ) ) {
				$errors->add( 'cpf', $defs['cpf']['invalid'] );
			}
			if ( 'required' === CFBR_Settings::mode( 'rg' ) && '' === $data['rg'] ) {
				$errors->add( 'rg', $defs['rg']['required'] );
			}
		}
		if ( $docs && $is_pj ) {
			if ( '' === $data['cnpj'] ) {
				$errors->add( 'cnpj', $defs['cnpj']['required'] );
			} elseif ( ! CFBR_Validator::is_cnpj( $data['cnpj'] ) ) {
				$errors->add( 'cnpj', $defs['cnpj']['invalid'] );
			}
			if ( CFBR_Settings::get( 'company_required' ) && self::company_enabled() && '' === $data['company'] ) {
				$errors->add( 'company', self::company_required_message() );
			}
			$ie_mode = CFBR_Settings::mode( 'ie' );
			if ( 'off' !== $ie_mode && ! $data['ie_exempt'] ) {
				if ( '' === $data['ie'] ) {
					if ( 'required' === $ie_mode ) {
						$errors->add( 'ie', $defs['ie']['required'] );
					}
				} elseif ( ! apply_filters( 'cfbr_ie_is_valid', true, $data['ie'], $data['billing_state'] ) ) {
					$errors->add( 'ie', $defs['ie']['invalid'] );
				}
			}
		}

		$birth_mode = CFBR_Settings::mode( 'birthdate' );
		if ( 'off' !== $birth_mode ) {
			if ( '' === $data['birthdate'] ) {
				if ( 'required' === $birth_mode ) {
					$errors->add( 'birthdate', $defs['birthdate']['required'] );
				}
			} elseif ( ! CFBR_Validator::is_birthdate( $data['birthdate'] ) ) {
				$errors->add( 'birthdate', $defs['birthdate']['invalid'] );
			}
		}

		$gender_mode = CFBR_Settings::mode( 'gender' );
		if ( 'off' !== $gender_mode ) {
			if ( '' === $data['gender'] ) {
				if ( 'required' === $gender_mode ) {
					$errors->add( 'gender', $defs['gender']['required'] );
				}
			} elseif ( ! isset( $defs['gender']['options'][ $data['gender'] ] ) ) {
				$errors->add( 'gender', $defs['gender']['required'] );
			}
		}

		$cell_mode = CFBR_Settings::mode( 'cellphone' );
		if ( 'off' !== $cell_mode ) {
			if ( '' === $data['cellphone'] ) {
				if ( 'required' === $cell_mode ) {
					$errors->add( 'cellphone', $defs['cellphone']['required'] );
				}
			} elseif ( ( '' === $data['billing_country'] || 'BR' === $data['billing_country'] ) && ! CFBR_Validator::is_phone( $data['cellphone'] ) ) {
				$errors->add( 'cellphone', $defs['cellphone']['invalid'] );
			}
		}

		if ( $address ) {
			$scopes = array( 'billing' );
			if ( $data['ship_to_different'] ) {
				$scopes[] = 'shipping';
			}
			foreach ( $scopes as $scope ) {
				if ( 'BR' !== $data[ $scope . '_country' ] ) {
					continue;
				}
				if ( '' === $data[ $scope . '_number' ] ) {
					$errors->add( $scope . '_number', $defs['number']['required'] );
				}
				if ( CFBR_Settings::get( 'neighborhood_required' ) && '' === $data[ $scope . '_neighborhood' ] ) {
					$errors->add( $scope . '_neighborhood', $defs['neighborhood']['required'] );
				}
			}
		}

		/**
		 * Let add-ons add their own checks.
		 *
		 * @param WP_Error $errors  Errors so far.
		 * @param array    $data    Normalised values.
		 * @param string   $context classic|block|account|register.
		 */
		do_action( 'cfbr_validate', $errors, $data, $context );
		return $errors;
	}

	/**
	 * Legacy meta values for an order from normalised data. Only fields that apply are filled.
	 *
	 * @param array $data Normalised data.
	 * @return array Keys without the leading underscore (billing_cpf ...).
	 */
	public static function legacy_values( array $data ) {
		$docs   = self::documents_apply( $data['billing_country'] );
		$is_pf  = $docs && '1' === $data['persontype'];
		$is_pj  = $docs && '2' === $data['persontype'];
		$values = array();
		if ( $docs ) {
			$values['billing_persontype'] = $data['persontype'];
		}
		if ( $is_pf ) {
			$values['billing_cpf'] = $data['cpf'];
			if ( 'off' !== CFBR_Settings::mode( 'rg' ) ) {
				$values['billing_rg'] = $data['rg'];
			}
		}
		if ( $is_pj ) {
			$values['billing_cnpj'] = $data['cnpj'];
			if ( 'off' !== CFBR_Settings::mode( 'ie' ) ) {
				$values['billing_ie'] = $data['ie'];
			}
		}
		if ( 'off' !== CFBR_Settings::mode( 'birthdate' ) ) {
			$values['billing_birthdate'] = $data['birthdate'];
		}
		if ( 'off' !== CFBR_Settings::mode( 'gender' ) ) {
			$values['billing_gender'] = $data['gender'];
		}
		if ( 'off' !== CFBR_Settings::mode( 'cellphone' ) ) {
			$values['billing_cellphone'] = $data['cellphone'];
		}
		foreach ( array( 'billing', 'shipping' ) as $scope ) {
			if ( 'BR' === $data[ $scope . '_country' ] ) {
				$values[ $scope . '_number' ]       = $data[ $scope . '_number' ];
				$values[ $scope . '_neighborhood' ] = $data[ $scope . '_neighborhood' ];
			}
		}
		return array_filter( $values, 'strlen' );
	}

	/**
	 * Values for display from an order, read from the legacy keys.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function order_values( $order ) {
		$values = array();
		foreach ( self::LEGACY_BILLING as $key ) {
			$values[ $key ] = (string) $order->get_meta( '_billing_' . $key );
		}
		$values['shipping_number']       = (string) $order->get_meta( '_shipping_number' );
		$values['shipping_neighborhood'] = (string) $order->get_meta( '_shipping_neighborhood' );
		return $values;
	}

	/**
	 * Label for a stored person type.
	 *
	 * @param string $type 1 or 2.
	 * @return string
	 */
	public static function persontype_label( $type ) {
		$defs = self::definitions();
		return isset( $defs['persontype']['options'][ (string) $type ] ) ? $defs['persontype']['options'][ (string) $type ] : '';
	}
}
