<?php
/**
 * Order and customer data: legacy keys, formatted address, REST API and the customer-facing order view.
 *
 * @package CheckoutFacilBrasil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order data.
 */
class CFBR_Order_Data {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_localisation_address_formats', array( __CLASS__, 'address_formats' ), 20 );
		add_filter( 'woocommerce_formatted_address_replacements', array( __CLASS__, 'replacements' ), 20, 2 );
		add_filter( 'woocommerce_order_formatted_billing_address', array( __CLASS__, 'order_billing_address' ), 20, 2 );
		add_filter( 'woocommerce_order_formatted_shipping_address', array( __CLASS__, 'order_shipping_address' ), 20, 2 );
		add_filter( 'woocommerce_my_account_my_address_formatted_address', array( __CLASS__, 'account_address' ), 20, 3 );
		add_action( 'woocommerce_order_details_after_customer_details', array( __CLASS__, 'customer_details' ), 5 );

		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( __CLASS__, 'rest_order' ), 100, 2 );
		add_filter( 'woocommerce_rest_prepare_customer', array( __CLASS__, 'rest_customer' ), 100, 2 );
		add_filter( 'woocommerce_rest_shop_order_schema', array( __CLASS__, 'rest_schema' ), 100 );
		add_filter( 'woocommerce_rest_customer_schema', array( __CLASS__, 'rest_schema' ), 100 );
	}

	/**
	 * Write the normalised values to the order under the legacy keys, and remove keys that do not apply.
	 *
	 * @param WC_Order $order  Order.
	 * @param array    $values Normalised values.
	 * @return void
	 */
	public static function save_legacy( $order, array $values ) {
		$legacy = CFBR_Fields::legacy_values( $values );
		$keys   = array();
		foreach ( CFBR_Fields::LEGACY_BILLING as $key ) {
			$keys[] = 'billing_' . $key;
		}
		$keys[] = 'shipping_number';
		$keys[] = 'shipping_neighborhood';
		foreach ( $keys as $key ) {
			if ( isset( $legacy[ $key ] ) ) {
				$order->update_meta_data( '_' . $key, $legacy[ $key ] );
			} else {
				$order->delete_meta_data( '_' . $key );
			}
		}
		foreach ( array( '_billing_nonumber', '_shipping_nonumber', '_billing_ie_exempt' ) as $key ) {
			$order->delete_meta_data( $key );
		}
		/**
		 * Fires after the Brazilian fields are written to an order (not saved yet on the classic checkout).
		 *
		 * @param WC_Order $order  Order.
		 * @param array    $values Normalised values.
		 */
		do_action( 'cfbr_order_saved', $order, $values );
	}

	/**
	 * Keep the customer's legacy keys up to date.
	 *
	 * @param WC_Customer $customer Customer.
	 * @param array       $values   Normalised values.
	 * @return void
	 */
	public static function save_customer_legacy( $customer, array $values ) {
		if ( ! $customer instanceof WC_Customer || ! $customer->get_id() ) {
			return;
		}
		foreach ( CFBR_Fields::legacy_values( $values ) as $key => $value ) {
			$customer->update_meta_data( $key, $value );
		}
		foreach ( array( 'billing_nonumber', 'shipping_nonumber', 'billing_ie_exempt' ) as $key ) {
			$customer->delete_meta_data( $key );
		}
		// The block checkout and My Account read these keys.
		$ns      = CFBR_Fields::NS . '/';
		$contact = array(
			'persontype' => $values['persontype'],
			'cpf'        => $values['cpf'],
			'rg'         => $values['rg'],
			'cnpj'       => $values['cnpj'],
			'ie'         => $values['ie_exempt'] ? '' : $values['ie'],
			'ie_exempt'  => $values['ie_exempt'] ? '1' : '',
			'birthdate'  => $values['birthdate'],
			'gender'     => $values['gender'],
			'cellphone'  => $values['cellphone'],
		);
		foreach ( $contact as $id => $value ) {
			if ( '' !== (string) $value ) {
				$customer->update_meta_data( '_wc_other/' . $ns . $id, $value );
			}
		}
		foreach ( array( 'billing', 'shipping' ) as $scope ) {
			if ( 'BR' !== $values[ $scope . '_country' ] ) {
				continue;
			}
			$customer->update_meta_data( '_wc_' . $scope . '/' . $ns . 'number', $values[ $scope . '_nonumber' ] ? '' : $values[ $scope . '_number' ] );
			$customer->update_meta_data( '_wc_' . $scope . '/' . $ns . 'nonumber', $values[ $scope . '_nonumber' ] ? '1' : '' );
			$customer->update_meta_data( '_wc_' . $scope . '/' . $ns . 'neighborhood', $values[ $scope . '_neighborhood' ] );
		}
	}

	/**
	 * Rows to show for an order: label => value.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function display_rows( $order ) {
		$defs = CFBR_Fields::definitions();
		$v    = CFBR_Fields::order_values( $order );
		$rows = array();
		if ( '' !== $v['persontype'] && '' !== CFBR_Fields::persontype_label( $v['persontype'] ) ) {
			$rows['persontype'] = array( $defs['persontype']['label'], CFBR_Fields::persontype_label( $v['persontype'] ) );
		}
		foreach ( array( 'cpf', 'rg', 'cnpj', 'ie', 'birthdate', 'gender', 'cellphone' ) as $key ) {
			if ( '' !== $v[ $key ] ) {
				$rows[ $key ] = array( $defs[ $key ]['label'], $v[ $key ] );
			}
		}
		return $rows;
	}

	/**
	 * Brazilian address format: "Rua X, 123 – Bairro".
	 *
	 * @param array $formats Formats by country.
	 * @return array
	 */
	public static function address_formats( $formats ) {
		$formats['BR'] = "{company}\n{name}\n{address_1}, {number}{cfbr_neighborhood}\n{address_2}\n{city} – {state_code}\n{postcode}\n{country}";
		return $formats;
	}

	/**
	 * Fill {number} and {neighborhood}.
	 *
	 * @param array $replace Replacements.
	 * @param array $args    Address parts.
	 * @return array
	 */
	public static function replacements( $replace, $args ) {
		$number       = isset( $args['number'] ) ? (string) $args['number'] : '';
		$neighborhood = isset( $args['neighborhood'] ) ? (string) $args['neighborhood'] : '';

		$replace['{number}']            = $number;
		$replace['{neighborhood}']      = $neighborhood;
		$replace['{cfbr_neighborhood}'] = '' !== $neighborhood ? ' – ' . $neighborhood : '';
		if ( '' === $number && '' !== $neighborhood ) {
			$replace['{cfbr_neighborhood}'] = '– ' . $neighborhood;
		}
		return $replace;
	}

	/**
	 * Add number and neighbourhood to the order billing address.
	 *
	 * @param array    $address Address parts.
	 * @param WC_Order $order   Order.
	 * @return array
	 */
	public static function order_billing_address( $address, $order ) {
		if ( is_array( $address ) && $order instanceof WC_Order ) {
			$address['number']       = (string) $order->get_meta( '_billing_number' );
			$address['neighborhood'] = (string) $order->get_meta( '_billing_neighborhood' );
		}
		return $address;
	}

	/**
	 * Add number and neighbourhood to the order shipping address.
	 *
	 * @param array    $address Address parts.
	 * @param WC_Order $order   Order.
	 * @return array
	 */
	public static function order_shipping_address( $address, $order ) {
		if ( is_array( $address ) && $order instanceof WC_Order ) {
			$address['number']       = (string) $order->get_meta( '_shipping_number' );
			$address['neighborhood'] = (string) $order->get_meta( '_shipping_neighborhood' );
		}
		return $address;
	}

	/**
	 * Add number and neighbourhood to My Account addresses.
	 *
	 * @param array  $address     Address parts.
	 * @param int    $customer_id Customer.
	 * @param string $type        billing|shipping.
	 * @return array
	 */
	public static function account_address( $address, $customer_id, $type ) {
		if ( is_array( $address ) ) {
			$address['number']       = (string) get_user_meta( $customer_id, $type . '_number', true );
			$address['neighborhood'] = (string) get_user_meta( $customer_id, $type . '_neighborhood', true );
		}
		return $address;
	}

	/**
	 * Documents on the thank-you page and in My Account › Orders.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function customer_details( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$rows = self::display_rows( $order );
		if ( ! $rows ) {
			return;
		}
		echo '<section class="cfbr-customer-details"><dl class="cfbr-details">';
		foreach ( $rows as $key => $row ) {
			printf( '<dt class="cfbr-%1$s">%2$s</dt><dd class="cfbr-%1$s">%3$s</dd>', esc_attr( $key ), esc_html( $row[0] ), esc_html( $row[1] ) );
		}
		echo '</dl></section>';
	}

	/**
	 * Strip . - / like the old plugin's REST output.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function plain( $value ) {
		return str_replace( array( '.', '-', '/' ), '', (string) $value );
	}

	/**
	 * F or J, as the old plugin returns it.
	 *
	 * @param string $type Stored person type.
	 * @return string
	 */
	private static function rest_persontype( $type ) {
		$setting = CFBR_Settings::get( 'person_type' );
		if ( 'pf' === $setting ) {
			return 'F';
		}
		if ( 'pj' === $setting ) {
			return 'J';
		}
		if ( 'none' === $setting ) {
			return '';
		}
		return 2 === (int) $type ? 'J' : 'F';
	}

	/**
	 * Birthdate as Y-m-dT00:00:00.
	 *
	 * @param string $date dd/mm/yyyy.
	 * @return string
	 */
	private static function rest_birthdate( $date ) {
		$parts = explode( '/', (string) $date );
		if ( 3 === count( $parts ) && '' !== $parts[1] ) {
			return sprintf( '%s-%s-%sT00:00:00', $parts[2], $parts[1], $parts[0] );
		}
		return '';
	}

	/**
	 * Fields for the REST API.
	 *
	 * @param callable $get    Reads a key without the leading underscore.
	 * @return array Two arrays: billing, shipping.
	 */
	private static function rest_fields( $get ) {
		$billing  = array(
			'number'       => $get( 'billing_number' ),
			'neighborhood' => $get( 'billing_neighborhood' ),
			'persontype'   => self::rest_persontype( $get( 'billing_persontype' ) ),
			'cpf'          => self::plain( $get( 'billing_cpf' ) ),
			'rg'           => self::plain( $get( 'billing_rg' ) ),
			'cnpj'         => self::plain( $get( 'billing_cnpj' ) ),
			'ie'           => self::plain( $get( 'billing_ie' ) ),
			'birthdate'    => self::rest_birthdate( $get( 'billing_birthdate' ) ),
			'gender'       => substr( $get( 'billing_gender' ), 0, 1 ),
			'cellphone'    => $get( 'billing_cellphone' ),
		);
		$shipping = array(
			'number'       => $get( 'shipping_number' ),
			'neighborhood' => $get( 'shipping_neighborhood' ),
		);
		return array( $billing, $shipping );
	}

	/**
	 * Orders in the REST API.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param WC_Order         $order    Order.
	 * @return WP_REST_Response
	 */
	public static function rest_order( $response, $order ) {
		if ( ! $order instanceof WC_Order || ! isset( $response->data['billing'] ) ) {
			return $response;
		}
		list( $billing, $shipping ) = self::rest_fields(
			function ( $key ) use ( $order ) {
				return (string) $order->get_meta( '_' . $key );
			}
		);
		$response->data['billing']  = array_merge( (array) $response->data['billing'], $billing );
		$response->data['shipping'] = array_merge( (array) $response->data['shipping'], $shipping );
		return $response;
	}

	/**
	 * Customers in the REST API.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param WP_User          $user     User.
	 * @return WP_REST_Response
	 */
	public static function rest_customer( $response, $user ) {
		if ( ! isset( $response->data['billing'] ) || empty( $user->ID ) ) {
			return $response;
		}
		$id                         = (int) $user->ID;
		list( $billing, $shipping ) = self::rest_fields(
			function ( $key ) use ( $id ) {
				return (string) get_user_meta( $id, $key, true );
			}
		);
		$response->data['billing']  = array_merge( (array) $response->data['billing'], $billing );
		$response->data['shipping'] = array_merge( (array) $response->data['shipping'], $shipping );
		return $response;
	}

	/**
	 * Describe the extra fields in the REST schema.
	 *
	 * @param array $properties Schema properties.
	 * @return array
	 */
	public static function rest_schema( $properties ) {
		$billing = array(
			'number'       => __( 'Number.', 'facilo-checkout-facil-brasil' ),
			'neighborhood' => __( 'Neighbourhood.', 'facilo-checkout-facil-brasil' ),
			'persontype'   => __( 'Person type: F (individual) or J (business).', 'facilo-checkout-facil-brasil' ),
			'cpf'          => __( 'CPF, digits only.', 'facilo-checkout-facil-brasil' ),
			'rg'           => __( 'RG.', 'facilo-checkout-facil-brasil' ),
			'cnpj'         => __( 'CNPJ, letters and digits only.', 'facilo-checkout-facil-brasil' ),
			'ie'           => __( 'State registration.', 'facilo-checkout-facil-brasil' ),
			'birthdate'    => __( 'Date of birth.', 'facilo-checkout-facil-brasil' ),
			'gender'       => __( 'Gender, first letter.', 'facilo-checkout-facil-brasil' ),
			'cellphone'    => __( 'Cell phone.', 'facilo-checkout-facil-brasil' ),
		);
		foreach ( $billing as $key => $description ) {
			$properties['billing']['properties'][ $key ] = array(
				'description' => $description,
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			);
			if ( in_array( $key, array( 'number', 'neighborhood' ), true ) ) {
				$properties['shipping']['properties'][ $key ] = $properties['billing']['properties'][ $key ];
			}
		}
		return $properties;
	}
}
