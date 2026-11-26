<?php
/**
 * Personal data export, erasure and the suggested privacy policy text.
 *
 * @package CheckoutFacilBrasil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Privacy tools.
 */
class CFBR_Privacy {

	/**
	 * Customer meta keys we write.
	 *
	 * @return string[]
	 */
	public static function customer_keys() {
		$keys = array();
		foreach ( CFBR_Fields::LEGACY_BILLING as $key ) {
			$keys[] = 'billing_' . $key;
		}
		$keys[] = 'shipping_number';
		$keys[] = 'shipping_neighborhood';
		$ns     = CFBR_Fields::NS . '/';
		foreach ( array( 'persontype', 'cpf', 'rg', 'cnpj', 'ie', 'ie_exempt', 'birthdate', 'gender', 'cellphone' ) as $id ) {
			$keys[] = '_wc_other/' . $ns . $id;
		}
		foreach ( array( 'billing', 'shipping' ) as $scope ) {
			foreach ( array( 'number', 'nonumber', 'neighborhood' ) as $id ) {
				$keys[] = '_wc_' . $scope . '/' . $ns . $id;
			}
		}
		return $keys;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'policy_text' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_filter( 'woocommerce_privacy_export_order_personal_data', array( __CLASS__, 'export_order' ), 10, 2 );
		add_action( 'woocommerce_privacy_remove_order_personal_data', array( __CLASS__, 'erase_order' ) );
	}

	/**
	 * Suggested text for the privacy policy.
	 *
	 * @return void
	 */
	public static function policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text for stores that use Checkout Fácil Brasil. Adjust it to the fields you ask for.', 'facilo-checkout-facil-brasil' ) . '</p>';
		$text .= '<p><strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'facilo-checkout-facil-brasil' ) . '</strong> ';
		$text .= esc_html__( 'At checkout we ask for your CPF or CNPJ and, depending on the store’s settings, your RG, state registration, date of birth, gender and mobile number, as well as the number and neighbourhood of your address. We use this data to issue the invoice for your purchase, to deliver your order and to contact you about it. We keep it with your order for as long as the tax law requires. This data is stored only in our store and is not sent to other services by this plugin.', 'facilo-checkout-facil-brasil' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Checkout Fácil Brasil', 'facilo-checkout-facil-brasil' ), wp_kses_post( $text ) );
	}

	/**
	 * Register the customer exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['checkout-facil-brasil'] = array(
			'exporter_friendly_name' => __( 'Checkout Fácil Brasil: customer data', 'facilo-checkout-facil-brasil' ),
			'callback'               => array( __CLASS__, 'export_customer' ),
		);
		return $exporters;
	}

	/**
	 * Register the customer eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['checkout-facil-brasil'] = array(
			'eraser_friendly_name' => __( 'Checkout Fácil Brasil: customer data', 'facilo-checkout-facil-brasil' ),
			'callback'             => array( __CLASS__, 'erase_customer' ),
		);
		return $erasers;
	}

	/**
	 * Label for a stored key.
	 *
	 * @param string $key Meta key.
	 * @return string
	 */
	private static function label( $key ) {
		$defs = CFBR_Fields::definitions();
		$id   = preg_replace( '#^(_wc_(other|billing|shipping)/' . preg_quote( CFBR_Fields::NS, '#' ) . '/|billing_|shipping_)#', '', $key );
		$name = isset( $defs[ $id ]['label'] ) ? $defs[ $id ]['label'] : $id;
		if ( 0 === strpos( $key, 'shipping_' ) || 0 === strpos( $key, '_wc_shipping/' ) ) {
			/* translators: %s: field name. */
			$name = sprintf( __( '%s (shipping)', 'facilo-checkout-facil-brasil' ), $name );
		}
		return $name;
	}

	/**
	 * Export a customer's stored values.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function export_customer( $email ) {
		$user = get_user_by( 'email', $email );
		$data = array();
		if ( $user ) {
			$seen = array();
			foreach ( self::customer_keys() as $key ) {
				$value = (string) get_user_meta( $user->ID, $key, true );
				$name  = self::label( $key );
				if ( '' === $value || isset( $seen[ $name ] ) ) {
					continue;
				}
				$seen[ $name ] = true;
				$data[]        = array(
					'name'  => $name,
					'value' => $value,
				);
			}
		}
		$items = array();
		if ( $data ) {
			$items[] = array(
				'group_id'    => 'checkout-facil-brasil',
				'group_label' => __( 'Brazilian checkout fields', 'facilo-checkout-facil-brasil' ),
				'item_id'     => 'user-' . $user->ID,
				'data'        => $data,
			);
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Erase a customer's stored values.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function erase_customer( $email ) {
		$user    = get_user_by( 'email', $email );
		$removed = false;
		if ( $user ) {
			foreach ( self::customer_keys() as $key ) {
				if ( '' !== (string) get_user_meta( $user->ID, $key, true ) ) {
					delete_user_meta( $user->ID, $key );
					$removed = true;
				}
			}
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Add our order rows to WooCommerce's order export.
	 *
	 * @param array    $data  Rows.
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function export_order( $data, $order ) {
		foreach ( CFBR_Order_Data::display_rows( $order ) as $row ) {
			$data[] = array(
				'name'  => $row[0],
				'value' => $row[1],
			);
		}
		return $data;
	}

	/**
	 * Remove our order meta when WooCommerce erases an order's personal data.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function erase_order( $order ) {
		$keys = array();
		foreach ( CFBR_Fields::LEGACY_BILLING as $key ) {
			$keys[] = '_billing_' . $key;
		}
		$keys = array_merge( $keys, array( '_shipping_number', '_shipping_neighborhood' ) );
		foreach ( self::customer_keys() as $key ) {
			if ( 0 === strpos( $key, '_wc_' ) ) {
				$keys[] = $key;
			}
		}
		foreach ( $keys as $key ) {
			$order->delete_meta_data( $key );
		}
		$order->save();
	}
}
