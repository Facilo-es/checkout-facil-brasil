<?php
/**
 * Brazilian fields in the order emails.
 *
 * @package CheckoutFacilBrasil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Emails.
 */
class CFBR_Emails {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_email_customer_details_fields', array( __CLASS__, 'fields' ), 20, 3 );
	}

	/**
	 * Add the rows to the customer details block of every order email (HTML and plain text).
	 *
	 * @param array    $fields        Fields.
	 * @param bool     $sent_to_admin To the shop.
	 * @param WC_Order $order         Order.
	 * @return array
	 */
	public static function fields( $fields, $sent_to_admin, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $fields;
		}
		foreach ( CFBR_Order_Data::display_rows( $order ) as $key => $row ) {
			$fields[ 'cfbr_' . $key ] = array(
				'label' => $row[0],
				'value' => $row[1],
			);
		}
		return $fields;
	}
}
