<?php
/**
 * The Brazilian card on the order screen, editable with the billing and shipping addresses.
 *
 * @package CheckoutFacilBrasil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order screen (HPOS and posts).
 */
class CFBR_Admin_Order {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_admin_billing_fields', array( __CLASS__, 'billing_fields' ), 20 );
		add_filter( 'woocommerce_admin_shipping_fields', array( __CLASS__, 'shipping_fields' ), 20 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'card' ), 5 );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'tidy' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Is this the order edit screen?
	 *
	 * @return bool
	 */
	private static function on_order_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		$ids = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$ids[] = wc_get_page_screen_id( 'shop-order' );
		}
		return in_array( $screen->id, $ids, true );
	}

	/**
	 * Card styles on the order screen only.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( self::on_order_screen() ) {
			wp_enqueue_style( 'cfbr-order', CFBR_URL . 'assets/css/admin.css', array(), CFBR_VERSION );
		}
	}

	/**
	 * Editable billing fields. WooCommerce saves them to _billing_<key>.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	public static function billing_fields( $fields ) {
		$defs  = CFBR_Fields::definitions();
		$new   = array();
		$type  = CFBR_Settings::get( 'person_type' );
		$extra = array();
		if ( 'none' !== $type ) {
			$extra['persontype'] = array(
				'label'   => $defs['persontype']['label'],
				'show'    => false,
				'type'    => 'select',
				'options' => array( '' => '—' ) + $defs['persontype']['options'],
			);
			$extra['cpf']        = array(
				'label' => $defs['cpf']['label'],
				'show'  => false,
			);
			$extra['rg']         = array(
				'label' => $defs['rg']['label'],
				'show'  => false,
			);
			$extra['cnpj']       = array(
				'label' => $defs['cnpj']['label'],
				'show'  => false,
			);
			$extra['ie']         = array(
				'label' => $defs['ie']['label'],
				'show'  => false,
			);
		}
		$extra['birthdate']    = array(
			'label' => $defs['birthdate']['label'],
			'show'  => false,
		);
		$extra['gender']       = array(
			'label'   => $defs['gender']['label'],
			'show'    => false,
			'type'    => 'select',
			'options' => array( '' => '—' ) + $defs['gender']['options'],
		);
		$extra['cellphone']    = array(
			'label' => $defs['cellphone']['label'],
			'show'  => false,
		);
		$extra['number']       = array(
			'label' => $defs['number']['label'],
			'show'  => false,
		);
		$extra['neighborhood'] = array(
			'label' => $defs['neighborhood']['label'],
			'show'  => false,
		);
		foreach ( $fields as $key => $field ) {
			$new[ $key ] = $field;
			if ( 'last_name' === $key ) {
				foreach ( array( 'persontype', 'cpf', 'rg', 'cnpj', 'ie', 'birthdate', 'gender' ) as $id ) {
					if ( isset( $extra[ $id ] ) ) {
						$new[ $id ] = $extra[ $id ];
					}
				}
			}
			if ( 'address_1' === $key ) {
				$new['number'] = $extra['number'];
			}
			if ( 'address_2' === $key ) {
				$new['neighborhood'] = $extra['neighborhood'];
			}
			if ( 'phone' === $key ) {
				$new['cellphone'] = $extra['cellphone'];
			}
		}
		foreach ( $extra as $id => $field ) {
			if ( ! isset( $new[ $id ] ) ) {
				$new[ $id ] = $field;
			}
		}
		return $new;
	}

	/**
	 * Editable shipping fields.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	public static function shipping_fields( $fields ) {
		$defs = CFBR_Fields::definitions();
		$new  = array();
		foreach ( $fields as $key => $field ) {
			$new[ $key ] = $field;
			if ( 'address_1' === $key ) {
				$new['number'] = array(
					'label' => $defs['number']['label'],
					'show'  => false,
				);
			}
			if ( 'address_2' === $key ) {
				$new['neighborhood'] = array(
					'label' => $defs['neighborhood']['label'],
					'show'  => false,
				);
			}
		}
		return $new;
	}

	/**
	 * The card shown under the billing address.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function card( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$rows = CFBR_Order_Data::display_rows( $order );
		$v    = CFBR_Fields::order_values( $order );
		$name = trim( 'BR' === $order->get_billing_country() && '2' === $v['persontype'] && $order->get_billing_company() ? $order->get_billing_company() : $order->get_formatted_billing_full_name() );
		echo '<div class="cfbr-ficha address">';
		if ( $rows || '' !== $name ) {
			echo '<div class="cfbr-ficha-h">';
			echo '<strong>' . esc_html( $name ) . '</strong>';
			if ( isset( $rows['persontype'] ) ) {
				echo '<span class="cfbr-ficha-tag">' . esc_html( $rows['persontype'][1] ) . '</span>';
				unset( $rows['persontype'] );
			}
			echo '</div>';
		}
		echo '<dl class="cfbr-ficha-dl">';
		foreach ( $rows as $key => $row ) {
			printf( '<dt class="cfbr-%1$s">%2$s</dt><dd class="cfbr-%1$s">%3$s</dd>', esc_attr( $key ), esc_html( $row[0] ), esc_html( $row[1] ) );
			if ( 'cnpj' === $key ) {
				/**
				 * Print extra rows beside the CNPJ row (Pro adds the company status).
				 *
				 * @param WC_Order $order Order.
				 */
				do_action( 'cfbr_admin_order_card', $order );
			}
		}
		if ( ! isset( $rows['cnpj'] ) ) {
			do_action( 'cfbr_admin_order_card', $order );
		}
		echo '</dl></div>';
	}

	/**
	 * After WooCommerce saved the edited fields, store them masked and upper-case like the checkout does.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function tidy( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$changed = false;
		$format  = array(
			'_billing_cpf'       => array( 'CFBR_Validator', 'is_cpf', 'format_cpf' ),
			'_billing_cnpj'      => array( 'CFBR_Validator', 'is_cnpj', 'format_cnpj' ),
			'_billing_cellphone' => array( 'CFBR_Validator', 'is_phone', 'format_phone' ),
		);
		foreach ( $format as $key => $fn ) {
			$value = (string) $order->get_meta( $key );
			if ( '' === $value ) {
				$order->delete_meta_data( $key );
				$changed = true;
				continue;
			}
			if ( call_user_func( array( $fn[0], $fn[1] ), $value ) ) {
				$clean = call_user_func( array( $fn[0], $fn[2] ), $value );
				if ( $clean !== $value ) {
					$order->update_meta_data( $key, $clean );
					$changed = true;
				}
			}
		}
		foreach ( array( '_billing_number', '_shipping_number', '_billing_ie' ) as $key ) {
			$value = (string) $order->get_meta( $key );
			if ( '' !== $value && ( 0 === strcasecmp( $value, CFBR_Fields::no_number_value() ) || 0 === strcasecmp( $value, CFBR_Fields::ie_exempt_value() ) ) && strtoupper( $value ) !== $value ) {
				$order->update_meta_data( $key, strtoupper( $value ) );
				$changed = true;
			}
		}
		$birth = (string) $order->get_meta( '_billing_birthdate' );
		if ( '' !== $birth ) {
			$clean = CFBR_Validator::format_birthdate( $birth );
			if ( $clean !== $birth ) {
				$order->update_meta_data( '_billing_birthdate', $clean );
				$changed = true;
			}
		}
		if ( $changed ) {
			$order->save();
		}
	}
}
