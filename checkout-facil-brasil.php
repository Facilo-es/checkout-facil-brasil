<?php
/**
 * Plugin Name:       Facilo Checkout Fácil Brasil
 * Plugin URI:        https://github.com/Facilo-es/checkout-facil-brasil
 * Description:       CPF, CNPJ (including the new alphanumeric CNPJ), Número, Bairro and other Brazilian fields for the WooCommerce block and classic checkouts.
 * Version:           1.0.1
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Facilo
 * Author URI:        https://facilo.es
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       facilo-checkout-facil-brasil
 * Domain Path:       /languages
 * WC requires at least: 8.9
 * WC tested up to:   11.2
 *
 * @package CheckoutFacilBrasil
 */

defined( 'ABSPATH' ) || exit;

// A copy from before the wordpress.org rename (folder checkout-facil-brasil) may still be installed: only one may load.
if ( defined( 'CFBR_VERSION' ) ) {
	return;
}

define( 'CFBR_VERSION', '1.0.1' );
define( 'CFBR_FILE', __FILE__ );
define( 'CFBR_PATH', plugin_dir_path( __FILE__ ) );
define( 'CFBR_URL', plugin_dir_url( __FILE__ ) );
if ( ! defined( 'CFBR_PRO_URL' ) ) {
	define( 'CFBR_PRO_URL', 'https://checkout.freemius.com/plugin/44983/plan/79309/' );
}

require_once CFBR_PATH . 'includes/class-cfbr-validator.php';
require_once CFBR_PATH . 'includes/class-cfbr-settings.php';
require_once CFBR_PATH . 'includes/class-cfbr-fields.php';
require_once CFBR_PATH . 'includes/class-cfbr-plugin.php';

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'init',
	function () {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Ships pt_BR and es_ES until translate.wordpress.org has them; WordPress's own language packs still load first.
		load_plugin_textdomain( 'facilo-checkout-facil-brasil', false, dirname( plugin_basename( CFBR_FILE ) ) . '/languages' );
	},
	-1
);

register_activation_hook( __FILE__, array( 'CFBR_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'CFBR_Plugin', 'instance' ), 20 );
