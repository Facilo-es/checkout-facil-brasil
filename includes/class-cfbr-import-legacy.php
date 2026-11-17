<?php
/**
 * Reads the settings of Brazilian Market on WooCommerce and offers to turn it off.
 *
 * @package CheckoutFacilBrasil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import from the old plugin.
 */
class CFBR_Import_Legacy {

	const LEGACY_FILE   = 'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php';
	const LEGACY_OPTION = 'wcbcf_settings';
	const IMPORTED      = 'cfbr_imported';
	const ACTION        = 'cfbr_deactivate_legacy';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_import' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'deactivate' ) );
	}

	/**
	 * Map the old settings to ours.
	 *
	 * @param array $old wcbcf_settings value.
	 * @return array
	 */
	public static function map( array $old ) {
		$on    = function ( $key ) use ( $old ) {
			return isset( $old[ $key ] ) && '' !== (string) $old[ $key ] && '0' !== (string) $old[ $key ];
		};
		$types = array(
			'0' => 'none',
			'1' => 'both',
			'2' => 'pf',
			'3' => 'pj',
		);
		$cells = array(
			'0'  => 'off',
			'1'  => 'optional',
			'2'  => 'required',
			'-1' => 'replace_phone',
		);
		$type  = isset( $old['person_type'] ) ? (string) $old['person_type'] : '1';
		$cell  = isset( $old['cell_phone'] ) ? (string) $old['cell_phone'] : '-1';
		return array(
			'person_type'           => isset( $types[ $type ] ) ? $types[ $type ] : 'both',
			'company_required'      => 1,
			'rg'                    => $on( 'rg' ) ? 'required' : 'off',
			'ie'                    => $on( 'ie' ) ? 'required' : 'off',
			'birthdate'             => $on( 'birthdate' ) ? 'required' : 'off',
			'gender'                => $on( 'gender' ) ? 'required' : 'off',
			'cellphone'             => isset( $cells[ $cell ] ) ? $cells[ $cell ] : 'off',
			'neighborhood_required' => $on( 'neighborhood_required' ) ? 1 : 0,
			'no_number'             => 1,
			'masks'                 => $on( 'maskedinput' ) ? 1 : 0,
			'only_brazil'           => $on( 'only_brazil' ) ? 1 : 0,
		);
	}

	/**
	 * Import once, when we have no settings of our own yet.
	 *
	 * @return bool True when settings were imported now.
	 */
	public static function maybe_import() {
		if ( false !== get_option( CFBR_Settings::OPTION, false ) ) {
			return false;
		}
		$old = get_option( self::LEGACY_OPTION, false );
		if ( ! is_array( $old ) ) {
			return false;
		}
		CFBR_Settings::flush();
		CFBR_Settings::save( self::map( $old ) );
		update_option( self::IMPORTED, time(), false );
		update_option( 'cfbr_setup_done', 1, false );
		return true;
	}

	/**
	 * Is the old plugin active?
	 *
	 * @return bool
	 */
	public static function legacy_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( self::LEGACY_FILE ) || CFBR_Plugin::legacy_active();
	}

	/**
	 * The one notice shown outside our own screen: while the old plugin is active.
	 *
	 * @return void
	 */
	public static function notice() {
		if ( ! current_user_can( 'activate_plugins' ) || ! self::legacy_active() ) {
			return;
		}
		$imported = (bool) get_option( self::IMPORTED );
		?>
		<div class="notice notice-warning cfbr-legacy-notice">
			<p><strong>
			<?php
			echo $imported
				? esc_html__( 'Your Brazilian Market on WooCommerce data has been imported', 'checkout-facil-brasil' )
				: esc_html__( 'Brazilian Market on WooCommerce is still active', 'checkout-facil-brasil' );
			?>
			</strong></p>
			<p><?php esc_html_e( 'Orders, customers and settings stay the same. To avoid duplicate fields at checkout, deactivate the old plugin.', 'checkout-facil-brasil' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php wp_nonce_field( self::ACTION ); ?>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Deactivate the old plugin', 'checkout-facil-brasil' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * One-click deactivation of the old plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You are not allowed to deactivate plugins.', 'checkout-facil-brasil' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		self::maybe_import();
		deactivate_plugins( self::LEGACY_FILE );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => CFBR_Setup::SLUG,
					'cfbr-legacy-off' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
