<?php
/**
 * Plugin bootstrap.
 *
 * @package CheckoutFacilBrasil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads the parts of the plugin.
 */
class CFBR_Plugin {

	/**
	 * Single instance.
	 *
	 * @var CFBR_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Start the plugin.
	 *
	 * @return CFBR_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Is the old Brazilian Market on WooCommerce plugin active?
	 *
	 * @return bool
	 */
	public static function legacy_active() {
		return class_exists( 'Extra_Checkout_Fields_For_Brazil', false );
	}

	/**
	 * Hook everything up.
	 */
	private function __construct() {
		if ( ! class_exists( 'WooCommerce', false ) ) {
			return;
		}
		$files = array( 'class-cfbr-import-legacy', 'class-cfbr-setup', 'class-cfbr-privacy' );
		if ( ! self::legacy_active() ) {
			$files = array_merge( $files, array( 'class-cfbr-fields-classic', 'class-cfbr-fields-blocks', 'class-cfbr-order-data', 'class-cfbr-admin-order', 'class-cfbr-emails' ) );
		}
		foreach ( $files as $file ) {
			require_once CFBR_PATH . 'includes/' . $file . '.php';
		}

		CFBR_Import_Legacy::init();
		CFBR_Setup::init();
		CFBR_Privacy::init();
		if ( self::legacy_active() ) {
			return;
		}
		CFBR_Fields_Classic::init();
		CFBR_Fields_Blocks::init();
		CFBR_Order_Data::init();
		CFBR_Admin_Order::init();
		CFBR_Emails::init();
		add_filter( 'woocommerce_get_country_locale', array( $this, 'country_locale' ), 20 );
	}

	/**
	 * Brazilian labels for WooCommerce's own Company field.
	 *
	 * @param array $locale Locale data.
	 * @return array
	 */
	public function country_locale( $locale ) {
		if ( ! isset( $locale['BR'] ) || ! is_array( $locale['BR'] ) ) {
			$locale['BR'] = array();
		}
		$locale['BR']['company'] = array_merge(
			isset( $locale['BR']['company'] ) ? (array) $locale['BR']['company'] : array(),
			array( 'label' => CFBR_Fields::company_label() )
		);
		return $locale;
	}

	/**
	 * Data for the checkout scripts.
	 *
	 * @param string $context classic|block|account.
	 * @return array
	 */
	public static function script_data( $context ) {
		$defs     = CFBR_Fields::definitions();
		$messages = array();
		foreach ( $defs as $id => $def ) {
			$messages[ $id ] = array(
				'required' => isset( $def['required'] ) ? $def['required'] : '',
				'invalid'  => isset( $def['invalid'] ) ? $def['invalid'] : '',
				'label'    => $def['label'],
			);
		}
		$messages['company'] = array(
			'required' => CFBR_Fields::company_required_message(),
			'invalid'  => '',
			'label'    => CFBR_Fields::company_label(),
		);
		$settings            = CFBR_Settings::get();
		$data                = array(
			'ns'            => CFBR_Fields::NS,
			'context'       => $context,
			'personType'    => $settings['person_type'],
			'onlyBrazil'    => (bool) $settings['only_brazil'],
			'masks'         => (bool) $settings['masks'],
			'noNumber'      => (bool) $settings['no_number'],
			'companyReq'    => (bool) $settings['company_required'],
			'neighReq'      => (bool) $settings['neighborhood_required'],
			'modes'         => array(
				'rg'        => CFBR_Settings::mode( 'rg' ),
				'ie'        => CFBR_Settings::mode( 'ie' ),
				'birthdate' => CFBR_Settings::mode( 'birthdate' ),
				'gender'    => CFBR_Settings::mode( 'gender' ),
				'cellphone' => CFBR_Settings::mode( 'cellphone' ),
			),
			'noNumberValue' => CFBR_Fields::no_number_value(),
			'exemptValue'   => CFBR_Fields::ie_exempt_value(),
			'i18n'          => array(
				'messages' => $messages,
				'noNumber' => $defs['nonumber']['label'],
				'exempt'   => $defs['ie_exempt']['label'],
			),
		);
		/**
		 * Filter the data the checkout scripts receive as window.cfbrData.
		 *
		 * @param array  $data    Script data.
		 * @param string $context classic|block|account.
		 */
		return apply_filters( 'cfbr_script_data', $data, $context );
	}

	/**
	 * Runs on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		require_once CFBR_PATH . 'includes/class-cfbr-import-legacy.php';
		CFBR_Import_Legacy::maybe_import();
		CFBR_Settings::show_company_field();
	}
}
