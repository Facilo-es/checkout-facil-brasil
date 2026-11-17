<?php
/**
 * Removes this plugin's own options. Order and customer data stay, because they belong to the shop.
 *
 * @package CheckoutFacilBrasil
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'cfbr_settings', 'cfbr_imported', 'cfbr_setup_done' ) as $cfbr_option ) {
	delete_option( $cfbr_option );
}
