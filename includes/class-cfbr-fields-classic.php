<?php
/**
 * Fields on the classic checkout ([woocommerce_checkout]) and the My Account address form.
 *
 * @package CheckoutFacilBrasil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Classic checkout.
 */
class CFBR_Fields_Classic {

	/**
	 * Our classic field keys (without billing_/shipping_).
	 *
	 * @var string[]
	 */
	const KEYS = array( 'persontype', 'cpf', 'rg', 'cnpj', 'ie', 'ie_exempt', 'birthdate', 'gender', 'cellphone', 'number', 'nonumber', 'neighborhood' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'billing_fields' ), 20, 2 );
		add_filter( 'woocommerce_shipping_fields', array( __CLASS__, 'shipping_fields' ), 20, 2 );
		add_filter( 'woocommerce_get_country_locale', array( __CLASS__, 'locale' ), 30 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_order' ), 20, 2 );
		add_action( 'woocommerce_checkout_update_customer', array( __CLASS__, 'save_customer' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Are we building the checkout (not the My Account address form)?
	 *
	 * @return bool
	 */
	private static function on_checkout() {
		return function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' );
	}

	/**
	 * A field definition for form_field().
	 *
	 * @param string $id       Our field id.
	 * @param array  $extra    Extra args.
	 * @return array
	 */
	private static function field( $id, array $extra = array() ) {
		$defs = CFBR_Fields::definitions();
		$def  = $defs[ $id ];
		$args = array(
			'label'       => $def['label'],
			'placeholder' => $def['placeholder'],
			'priority'    => $def['priority'],
			'required'    => false,
			'class'       => array( 'form-row-wide', 'cfbr-field', 'cfbr-field-' . $id ),
			'clear'       => true,
		);
		if ( '' !== $def['help'] ) {
			$args['description'] = $def['help'];
		}
		return array_merge( $args, $extra );
	}

	/**
	 * Add the Brazilian fields to the billing address.
	 *
	 * @param array  $fields  Billing fields.
	 * @param string $country Country.
	 * @return array
	 */
	public static function billing_fields( $fields, $country = '' ) {
		$checkout = self::on_checkout();
		if ( ! $checkout ) {
			return $fields; // My Account uses the block fields, so they are not shown twice.
		}
		$type = CFBR_Settings::get( 'person_type' );
		$defs = CFBR_Fields::definitions();

		if ( 'none' !== $type ) {
			if ( 'both' === $type ) {
				$fields['billing_persontype'] = self::field(
					'persontype',
					array(
						'type'     => 'radio',
						'options'  => $defs['persontype']['options'],
						'default'  => '1',
						'required' => $checkout,
						'class'    => array( 'form-row-wide', 'cfbr-field', 'cfbr-doc', 'cfbr-persontype' ),
					)
				);
			}
			if ( CFBR_Settings::wants_cpf() ) {
				$fields['billing_cpf'] = self::field(
					'cpf',
					array(
						'required'          => $checkout,
						'class'             => array( 'form-row-wide', 'cfbr-field', 'cfbr-doc', 'cfbr-pf' ),
						'custom_attributes' => array(
							'inputmode'      => 'numeric',
							'maxlength'      => '14',
							'data-cfbr-mask' => 'cpf',
							'autocomplete'   => 'off',
						),
					)
				);
				if ( 'off' !== CFBR_Settings::mode( 'rg' ) ) {
					$fields['billing_rg'] = self::field(
						'rg',
						array(
							'required' => $checkout && 'required' === CFBR_Settings::mode( 'rg' ),
							'class'    => array( 'form-row-wide', 'cfbr-field', 'cfbr-doc', 'cfbr-pf' ),
						)
					);
				}
			}
			if ( CFBR_Settings::wants_cnpj() ) {
				$fields['billing_cnpj'] = self::field(
					'cnpj',
					array(
						'required'          => $checkout,
						'class'             => array( 'form-row-wide', 'cfbr-field', 'cfbr-doc', 'cfbr-pj' ),
						'custom_attributes' => array(
							'maxlength'      => '18',
							'autocapitalize' => 'characters',
							'data-cfbr-mask' => 'cnpj',
							'autocomplete'   => 'off',
						),
					)
				);
				if ( 'off' !== CFBR_Settings::mode( 'ie' ) ) {
					$fields['billing_ie']        = self::field(
						'ie',
						array(
							'required'          => $checkout && 'required' === CFBR_Settings::mode( 'ie' ),
							'class'             => array( 'form-row-first', 'cfbr-field', 'cfbr-doc', 'cfbr-pj' ),
							'clear'             => false,
							'custom_attributes' => array( 'data-cfbr-mask' => 'upper' ),
						)
					);
					$fields['billing_ie_exempt'] = self::field(
						'ie_exempt',
						array(
							'type'  => 'checkbox',
							'class' => array( 'form-row-last', 'cfbr-field', 'cfbr-doc', 'cfbr-pj', 'cfbr-check' ),
						)
					);
				}
			}
			if ( isset( $fields['billing_company'] ) ) {
				$fields['billing_company']['label'] = CFBR_Fields::company_label();
				if ( 'both' === $type || 'pj' === $type ) {
					$fields['billing_company']['class'][] = 'cfbr-company';
					if ( $checkout && CFBR_Settings::get( 'company_required' ) ) {
						$fields['billing_company']['required'] = 'pj' === $type ? true : $fields['billing_company']['required'];
						$fields['billing_company']['class'][]  = 'cfbr-company-req';
					}
				}
			}
		}

		$birth = CFBR_Settings::mode( 'birthdate' );
		if ( 'off' !== $birth ) {
			$fields['billing_birthdate'] = self::field(
				'birthdate',
				array(
					'required'          => $checkout && 'required' === $birth,
					'class'             => array( 'form-row-first', 'cfbr-field' ),
					'clear'             => false,
					'custom_attributes' => array(
						'inputmode'      => 'numeric',
						'maxlength'      => '10',
						'data-cfbr-mask' => 'date',
						'autocomplete'   => 'bday',
					),
				)
			);
		}
		$gender = CFBR_Settings::mode( 'gender' );
		if ( 'off' !== $gender ) {
			$fields['billing_gender'] = self::field(
				'gender',
				array(
					'type'     => 'select',
					'options'  => array( '' => '' ) + $defs['gender']['options'],
					'required' => $checkout && 'required' === $gender,
					'class'    => array( 'off' !== $birth ? 'form-row-last' : 'form-row-wide', 'cfbr-field' ),
				)
			);
		}
		$cell = CFBR_Settings::mode( 'cellphone' );
		if ( 'off' !== $cell ) {
			$fields['billing_cellphone'] = self::field(
				'cellphone',
				array(
					'type'              => 'tel',
					'required'          => $checkout && 'required' === $cell,
					'custom_attributes' => array(
						'data-cfbr-mask' => 'phone',
						'autocomplete'   => 'tel',
					),
				)
			);
		}
		if ( 'replace_phone' === CFBR_Settings::get( 'cellphone' ) && isset( $fields['billing_phone'] ) ) {
			$fields['billing_phone']['label']                               = $defs['cellphone']['label'];
			$fields['billing_phone']['placeholder']                         = $defs['cellphone']['placeholder'];
			$fields['billing_phone']['custom_attributes']['data-cfbr-mask'] = 'phone';
		}

		return self::address_fields( $fields, 'billing', $checkout );
	}

	/**
	 * Add Número and Bairro to the shipping address.
	 *
	 * @param array  $fields  Shipping fields.
	 * @param string $country Country.
	 * @return array
	 */
	public static function shipping_fields( $fields, $country = '' ) {
		if ( ! self::on_checkout() ) {
			return $fields;
		}
		return self::address_fields( $fields, 'shipping', true );
	}

	/**
	 * Número, Sem número and Bairro.
	 *
	 * @param array  $fields   Fields.
	 * @param string $scope    billing|shipping.
	 * @param bool   $checkout On the checkout page.
	 * @return array
	 */
	private static function address_fields( $fields, $scope, $checkout ) {
		$no_number                    = (bool) CFBR_Settings::get( 'no_number' );
		$fields[ $scope . '_number' ] = self::field(
			'number',
			array(
				'required' => $checkout,
				'class'    => array( $no_number ? 'form-row-first' : 'form-row-wide', 'cfbr-field', 'cfbr-br', 'cfbr-number' ),
				'clear'    => ! $no_number,
			)
		);
		if ( $no_number ) {
			$fields[ $scope . '_nonumber' ] = self::field(
				'nonumber',
				array(
					'type'  => 'checkbox',
					'class' => array( 'form-row-last', 'cfbr-field', 'cfbr-br', 'cfbr-check', 'cfbr-nonumber' ),
				)
			);
		}
		$fields[ $scope . '_neighborhood' ] = self::field(
			'neighborhood',
			array(
				'required' => $checkout && (bool) CFBR_Settings::get( 'neighborhood_required' ),
				'class'    => array( 'form-row-wide', 'cfbr-field', 'cfbr-br' ),
			)
		);
		if ( isset( $fields[ $scope . '_postcode' ] ) && CFBR_Settings::get( 'masks' ) ) {
			$fields[ $scope . '_postcode' ]['custom_attributes']['data-cfbr-mask'] = 'cep';
		}
		return $fields;
	}

	/**
	 * Hide Número and Bairro outside Brazil in WooCommerce's own address script.
	 *
	 * @param array $locale Country locale.
	 * @return array
	 */
	public static function locale( $locale ) {
		if ( isset( $locale['BR'] ) ) {
			$locale['BR']['postcode']['priority'] = 45;
		}
		return $locale;
	}

	/**
	 * Raw values from a classic form post.
	 *
	 * @param array $post Posted data (already nonce-checked by WooCommerce).
	 * @return array
	 */
	public static function raw_from_post( array $post ) {
		$raw = array();
		foreach ( array( 'persontype', 'cpf', 'cnpj', 'company', 'rg', 'ie', 'ie_exempt', 'birthdate', 'gender', 'cellphone', 'country', 'state', 'postcode', 'number', 'nonumber', 'neighborhood' ) as $key ) {
			if ( isset( $post[ 'billing_' . $key ] ) ) {
				$target         = in_array( $key, array( 'country', 'state', 'postcode', 'number', 'nonumber', 'neighborhood' ), true ) ? 'billing_' . $key : $key;
				$raw[ $target ] = $post[ 'billing_' . $key ];
			}
		}
		foreach ( array( 'country', 'number', 'nonumber', 'neighborhood' ) as $key ) {
			if ( isset( $post[ 'shipping_' . $key ] ) ) {
				$raw[ 'shipping_' . $key ] = $post[ 'shipping_' . $key ];
			}
		}
		$raw['ship_to_different'] = ! empty( $post['ship_to_different_address'] ) && ( ! function_exists( 'wc_ship_to_billing_address_only' ) || ! wc_ship_to_billing_address_only() );
		if ( ! $raw['ship_to_different'] ) {
			foreach ( array( 'country', 'number', 'nonumber', 'neighborhood' ) as $key ) {
				$raw[ 'shipping_' . $key ] = isset( $raw[ 'billing_' . $key ] ) ? $raw[ 'billing_' . $key ] : '';
			}
		}
		if ( 'replace_phone' === CFBR_Settings::get( 'cellphone' ) && isset( $post['billing_phone'] ) ) {
			$raw['phone'] = $post['billing_phone'];
		}
		return $raw;
	}

	/**
	 * Posted data, sanitized as it is read.
	 *
	 * @return array
	 */
	private static function posted() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checks the checkout nonce before this runs.
		return map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
	}

	/**
	 * Replace WooCommerce's generic required errors for our fields with our own checks.
	 *
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 * @return void
	 */
	public static function validate( $data, $errors ) {
		foreach ( array( 'billing', 'shipping' ) as $scope ) {
			foreach ( array_merge( self::KEYS, array( 'company' ) ) as $key ) {
				$errors->remove( $scope . '_' . $key . '_required' );
			}
		}
		$values = CFBR_Fields::normalise( self::raw_from_post( self::posted() ) );
		$ours   = CFBR_Fields::validate( $values, 'classic', true );
		foreach ( $ours->get_error_codes() as $code ) {
			$id = 0 === strpos( $code, 'billing_' ) || 0 === strpos( $code, 'shipping_' ) ? $code : 'billing_' . $code;
			foreach ( $ours->get_error_messages( $code ) as $message ) {
				$errors->add( 'cfbr_' . $code, $message, array( 'id' => $id ) );
			}
		}
	}

	/**
	 * Store the normalised values on the order under the legacy keys.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $data  Posted data.
	 * @return void
	 */
	public static function save_order( $order, $data ) {
		$values = CFBR_Fields::normalise( self::raw_from_post( self::posted() ) );
		CFBR_Order_Data::save_legacy( $order, $values );
	}

	/**
	 * Keep the customer's saved values clean (masked, S/N) for the next order.
	 *
	 * @param WC_Customer $customer Customer.
	 * @param array       $data     Posted data.
	 * @return void
	 */
	public static function save_customer( $customer, $data ) {
		$values = CFBR_Fields::normalise( self::raw_from_post( self::posted() ) );
		CFBR_Order_Data::save_customer_legacy( $customer, $values );
	}

	/**
	 * Scripts and styles for the classic checkout and My Account.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! function_exists( 'is_checkout' ) || ! ( is_checkout() || is_account_page() ) || CFBR_Fields_Blocks::is_block_checkout() ) {
			return;
		}
		wp_enqueue_style( 'cfbr-checkout', CFBR_URL . 'assets/css/checkout.css', array(), CFBR_VERSION );
		wp_register_script( 'cfbr-masks', CFBR_URL . 'assets/js/masks.js', array(), CFBR_VERSION, true );
		wp_enqueue_script( 'cfbr-classic', CFBR_URL . 'assets/js/classic.js', array( 'cfbr-masks' ), CFBR_VERSION, true );
		wp_add_inline_script( 'cfbr-classic', 'window.cfbrData = ' . wp_json_encode( CFBR_Plugin::script_data( is_checkout() ? 'classic' : 'account' ) ) . ';', 'before' );
	}
}
