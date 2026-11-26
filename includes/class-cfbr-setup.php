<?php
/**
 * WooCommerce › Checkout Fácil Brasil: the 3-step setup and the settings tabs.
 *
 * @package CheckoutFacilBrasil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens.
 */
class CFBR_Setup {

	const SLUG       = 'checkout-facil-brasil';
	const SAVE       = 'cfbr_save_settings';
	const SETUP      = 'cfbr_save_setup';
	const SETUP_DONE = 'cfbr_setup_done';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_' . self::SAVE, array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_' . self::SETUP, array( __CLASS__, 'save_setup' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CFBR_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Our page URL.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Submenu under WooCommerce.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Checkout Fácil Brasil', 'facilo-checkout-facil-brasil' ),
			__( 'Checkout Fácil Brasil', 'facilo-checkout-facil-brasil' ),
			'manage_woocommerce',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * A Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'facilo-checkout-facil-brasil' ) . '</a>' );
		return $links;
	}

	/**
	 * Is the current admin screen ours?
	 *
	 * @return bool
	 */
	private static function on_our_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the page slug only.
		return is_admin() && isset( $_GET['page'] ) && self::SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Admin assets, only on our page.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::on_our_page() ) {
			return;
		}
		wp_enqueue_style( 'cfbr-admin', CFBR_URL . 'assets/css/admin.css', array(), CFBR_VERSION );
		wp_enqueue_script( 'cfbr-admin', CFBR_URL . 'assets/js/admin.js', array(), CFBR_VERSION, true );
	}

	/**
	 * Tabs: slug => array( label, render ).
	 *
	 * @return array
	 */
	public static function tabs() {
		$tabs = array(
			'fields'   => array(
				'label'  => __( 'Fields', 'facilo-checkout-facil-brasil' ),
				'render' => array( __CLASS__, 'tab_fields' ),
			),
			'behavior' => array(
				'label'  => __( 'Behaviour', 'facilo-checkout-facil-brasil' ),
				'render' => array( __CLASS__, 'tab_behavior' ),
			),
			'pro'      => array(
				'label'  => __( 'Pro', 'facilo-checkout-facil-brasil' ),
				'render' => array( __CLASS__, 'tab_pro' ),
			),
		);
		/**
		 * Filter the settings tabs. Each tab: array( 'label' => string, 'render' => callable ).
		 *
		 * @param array $tabs Tabs keyed by slug.
		 */
		return (array) apply_filters( 'cfbr_admin_tabs', $tabs );
	}

	/**
	 * The page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'facilo-checkout-facil-brasil' ), '', array( 'response' => 403 ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 0;
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:enable
		if ( $step || ( '' === $tab && ! get_option( self::SETUP_DONE ) ) ) {
			self::render_setup( max( 1, min( 3, $step ) ) );
			return;
		}
		$tabs = self::tabs();
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'fields';
		}
		echo '<div class="wrap cfbr-wrap">';
		echo '<h1 class="cfbr-title">' . self::mark() . esc_html__( 'Checkout Fácil Brasil', 'facilo-checkout-facil-brasil' ) . '</h1>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display flag only.
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'facilo-checkout-facil-brasil' ) . '</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display flag only.
		if ( isset( $_GET['cfbr-legacy-off'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The old plugin is off. Your Brazilian fields now come from Checkout Fácil Brasil.', 'facilo-checkout-facil-brasil' ) . '</p></div>';
		}
		echo '<nav class="nav-tab-wrapper cfbr-tabs">';
		foreach ( $tabs as $slug => $info ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s">%3$s</a>',
				esc_url( self::url( array( 'tab' => $slug ) ) ),
				$slug === $tab ? ' nav-tab-active' : '',
				esc_html( isset( $info['label'] ) ? $info['label'] : $slug )
			);
		}
		printf( '<a href="%s" class="cfbr-rerun">%s</a>', esc_url( self::url( array( 'step' => 1 ) ) ), esc_html__( 'Run the setup again', 'facilo-checkout-facil-brasil' ) );
		echo '</nav><div class="cfbr-tab-body">';
		if ( isset( $tabs[ $tab ]['render'] ) && is_callable( $tabs[ $tab ]['render'] ) ) {
			call_user_func( $tabs[ $tab ]['render'] );
		}
		echo '</div></div>';
	}

	/**
	 * The plugin mark.
	 *
	 * @return string
	 */
	private static function mark() {
		return '<svg class="cfbr-mark" viewBox="0 0 32 32" aria-hidden="true" focusable="false"><rect width="32" height="32" rx="9" fill="#3858e9"/><path d="M9 16.5l4.5 4.5L23 11.5" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	/**
	 * Labels for the off/optional/required choices.
	 *
	 * @return array
	 */
	private static function mode_labels() {
		return array(
			'off'           => __( 'Off', 'facilo-checkout-facil-brasil' ),
			'optional'      => __( 'Optional', 'facilo-checkout-facil-brasil' ),
			'required'      => __( 'Required', 'facilo-checkout-facil-brasil' ),
			'replace_phone' => __( 'Required, instead of Phone', 'facilo-checkout-facil-brasil' ),
		);
	}

	/**
	 * Person type labels.
	 *
	 * @return array
	 */
	private static function person_labels() {
		return array(
			'both' => __( 'Individuals and businesses', 'facilo-checkout-facil-brasil' ),
			'pf'   => __( 'Individuals only (CPF)', 'facilo-checkout-facil-brasil' ),
			'pj'   => __( 'Businesses only (CNPJ)', 'facilo-checkout-facil-brasil' ),
			'none' => __( 'Do not ask for a document', 'facilo-checkout-facil-brasil' ),
		);
	}

	/**
	 * Open a settings form.
	 *
	 * @param string $tab Tab slug.
	 * @return void
	 */
	private static function form_open( $tab ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cfbr-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::SAVE ) . '" />';
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />';
		wp_nonce_field( self::SAVE );
	}

	/**
	 * A toggle row.
	 *
	 * @param string $key   Setting.
	 * @param string $label Label.
	 * @param string $help  Help.
	 * @return void
	 */
	private static function toggle( $key, $label, $help = '' ) {
		$id = 'cfbr-' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="hidden" name="cfbr[' . esc_attr( $key ) . ']" value="0" />';
		echo '<input type="checkbox" class="cfbr-switch" id="' . esc_attr( $id ) . '" name="cfbr[' . esc_attr( $key ) . ']" value="1"' . checked( (bool) CFBR_Settings::get( $key ), true, false ) . ' />';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * A select row.
	 *
	 * @param string $key     Setting.
	 * @param string $label   Label.
	 * @param array  $options Value => label.
	 * @param string $help    Help.
	 * @return void
	 */
	private static function select( $key, $label, array $options, $help = '' ) {
		$id = 'cfbr-' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<select id="' . esc_attr( $id ) . '" name="cfbr[' . esc_attr( $key ) . ']">';
		foreach ( $options as $value => $text ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( CFBR_Settings::get( $key ), $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select>';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Fields tab.
	 *
	 * @return void
	 */
	public static function tab_fields() {
		$modes = self::mode_labels();
		$three = array_intersect_key( $modes, array_flip( array( 'off', 'optional', 'required' ) ) );
		$defs  = CFBR_Fields::definitions();
		self::form_open( 'fields' );
		echo '<h2>' . esc_html__( 'Which fields the checkout asks for', 'facilo-checkout-facil-brasil' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';
		self::select( 'person_type', __( 'Who buys in your shop?', 'facilo-checkout-facil-brasil' ), self::person_labels() );
		self::toggle( 'company_required', __( 'Company legal name', 'facilo-checkout-facil-brasil' ), __( 'Required for businesses. It uses WooCommerce’s own Company field.', 'facilo-checkout-facil-brasil' ) );
		self::select( 'cellphone', $defs['cellphone']['label'], $modes, __( 'Mask (00) 00000-0000. Also accepts landlines.', 'facilo-checkout-facil-brasil' ) );
		self::select( 'rg', $defs['rg']['label'], $three, __( 'Shown for individuals.', 'facilo-checkout-facil-brasil' ) );
		self::select( 'ie', $defs['ie']['label'], $three, __( 'Shown for businesses, with an Exempt option.', 'facilo-checkout-facil-brasil' ) );
		self::select( 'birthdate', $defs['birthdate']['label'], $three );
		self::select( 'gender', $defs['gender']['label'], $three, __( 'Always includes “Prefer not to say”.', 'facilo-checkout-facil-brasil' ) );
		self::toggle( 'neighborhood_required', __( 'Neighbourhood is required', 'facilo-checkout-facil-brasil' ) );
		echo '</table>';
		submit_button( __( 'Save changes', 'facilo-checkout-facil-brasil' ) );
		echo '</form>';
	}

	/**
	 * Behaviour tab.
	 *
	 * @return void
	 */
	public static function tab_behavior() {
		self::form_open( 'behavior' );
		echo '<h2>' . esc_html__( 'How the fields behave', 'facilo-checkout-facil-brasil' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';
		self::toggle( 'masks', __( 'Format while typing', 'facilo-checkout-facil-brasil' ), __( 'Adds the dots, slashes and dashes to CPF, CNPJ, CEP and phone numbers as the customer types.', 'facilo-checkout-facil-brasil' ) );
		self::toggle( 'only_brazil', __( 'Only for addresses in Brazil', 'facilo-checkout-facil-brasil' ), __( 'Customers abroad do not see the CPF and CNPJ fields.', 'facilo-checkout-facil-brasil' ) );
		self::toggle( 'no_number', __( 'Show “No number”', 'facilo-checkout-facil-brasil' ), __( 'A box next to Number that stores S/N.', 'facilo-checkout-facil-brasil' ) );
		echo '</table>';
		submit_button( __( 'Save changes', 'facilo-checkout-facil-brasil' ) );
		echo '</form>';
	}

	/**
	 * Pro tab: Pro's own screen when it is active, otherwise what Pro adds.
	 *
	 * @return void
	 */
	public static function tab_pro() {
		/**
		 * Filter the Pro tab. When it returns HTML, that HTML replaces the sales tab.
		 *
		 * @param string $html Empty by default.
		 */
		$html = (string) apply_filters( 'cfbr_pro_tab_html', '' );
		if ( '' !== $html ) {
			echo wp_kses( $html, self::allowed_html() );
			return;
		}
		$points = array(
			__( 'Address filled from the CEP: the customer types 8 digits and the rest appears on its own.', 'facilo-checkout-facil-brasil' ),
			__( 'Company details from the CNPJ, with a warning when the company is not active at the Receita.', 'facilo-checkout-facil-brasil' ),
			__( 'State registration checked for each state.', 'facilo-checkout-facil-brasil' ),
			__( 'Sign up and log in with CPF or CNPJ.', 'facilo-checkout-facil-brasil' ),
			__( 'Order search by CPF/CNPJ and documents on PDF invoices.', 'facilo-checkout-facil-brasil' ),
			__( 'CSV report for your accountant.', 'facilo-checkout-facil-brasil' ),
		);
		?>
		<div class="cfbr-pro">
			<div class="cfbr-pro-copy">
				<h2><?php esc_html_e( 'Less typing at checkout, fewer abandoned carts', 'facilo-checkout-facil-brasil' ); ?></h2>
				<ul class="cfbr-pro-list">
					<?php foreach ( $points as $point ) : ?>
						<li><?php echo esc_html( $point ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="cfbr-pro-price"><?php esc_html_e( 'US$ 19 per year for one shop · US$ 59 per year for unlimited shops', 'facilo-checkout-facil-brasil' ); ?></p>
				<p><a class="button button-primary button-hero cfbr-pro-button" href="<?php echo esc_url( CFBR_PRO_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Discover Pro', 'facilo-checkout-facil-brasil' ); ?></a></p>
				<p class="cfbr-pro-note"><?php esc_html_e( 'Secure payment through Freemius, in US dollars. Refund on request within 14 days.', 'facilo-checkout-facil-brasil' ); ?></p>
			</div>
			<div class="cfbr-demo" aria-hidden="true">
				<div class="cfbr-demo-field cfbr-demo-cep"><span class="cfbr-demo-label"><?php esc_html_e( 'CEP', 'facilo-checkout-facil-brasil' ); ?></span><span class="cfbr-demo-type">01001-000</span></div>
				<div class="cfbr-demo-field cfbr-demo-fill"><span class="cfbr-demo-label"><?php esc_html_e( 'Address', 'facilo-checkout-facil-brasil' ); ?></span><span>Praça da Sé</span></div>
				<div class="cfbr-demo-row">
					<div class="cfbr-demo-field cfbr-demo-fill"><span class="cfbr-demo-label"><?php esc_html_e( 'Neighbourhood', 'facilo-checkout-facil-brasil' ); ?></span><span>Sé</span></div>
					<div class="cfbr-demo-field cfbr-demo-fill"><span class="cfbr-demo-label"><?php esc_html_e( 'City', 'facilo-checkout-facil-brasil' ); ?></span><span>São Paulo · SP</span></div>
				</div>
				<div class="cfbr-demo-field cfbr-demo-focus"><span class="cfbr-demo-label"><?php esc_html_e( 'Number', 'facilo-checkout-facil-brasil' ); ?></span><span class="cfbr-demo-caret"></span></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Tags Pro's tab may use.
	 *
	 * @return array
	 */
	private static function allowed_html() {
		$allowed = wp_kses_allowed_html( 'post' );
		$common  = array(
			'name'         => true,
			'value'        => true,
			'type'         => true,
			'id'           => true,
			'class'        => true,
			'checked'      => true,
			'selected'     => true,
			'disabled'     => true,
			'placeholder'  => true,
			'for'          => true,
			'method'       => true,
			'action'       => true,
			'role'         => true,
			'aria-label'   => true,
			'data-*'       => true,
			'autocomplete' => true,
		);
		foreach ( array( 'form', 'input', 'select', 'option', 'button', 'label', 'textarea', 'fieldset', 'legend' ) as $tag ) {
			$allowed[ $tag ] = isset( $allowed[ $tag ] ) ? array_merge( $allowed[ $tag ], $common ) : $common;
		}
		return $allowed;
	}

	/**
	 * Save a settings tab.
	 *
	 * @return void
	 */
	public static function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'facilo-checkout-facil-brasil' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::SAVE );
		$input = isset( $_POST['cfbr'] ) && is_array( $_POST['cfbr'] ) ? map_deep( wp_unslash( $_POST['cfbr'] ), 'sanitize_text_field' ) : array();
		$tab   = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'fields';
		$keys  = 'behavior' === $tab
			? array( 'masks', 'only_brazil', 'no_number' )
			: array( 'person_type', 'company_required', 'cellphone', 'rg', 'ie', 'birthdate', 'gender', 'neighborhood_required' );
		CFBR_Settings::save( array_intersect_key( $input, array_flip( $keys ) ) );
		update_option( self::SETUP_DONE, 1, false );
		wp_safe_redirect(
			self::url(
				array(
					'tab'     => $tab,
					'updated' => 1,
				)
			)
		);
		exit;
	}

	/**
	 * Save the setup steps 1 and 2.
	 *
	 * @return void
	 */
	public static function save_setup() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'facilo-checkout-facil-brasil' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::SETUP );
		$input  = isset( $_POST['cfbr'] ) && is_array( $_POST['cfbr'] ) ? map_deep( wp_unslash( $_POST['cfbr'] ), 'sanitize_text_field' ) : array();
		$values = array();
		if ( isset( $input['person_type'] ) ) {
			$values['person_type'] = $input['person_type'];
		}
		foreach ( array( 'cellphone', 'rg', 'ie', 'birthdate', 'gender' ) as $field ) {
			$on               = ! empty( $input[ $field . '_on' ] );
			$mode             = isset( $input[ $field ] ) && 'required' === $input[ $field ] ? 'required' : 'optional';
			$values[ $field ] = $on ? $mode : 'off';
		}
		CFBR_Settings::save( $values );
		update_option( self::SETUP_DONE, 1, false );
		wp_safe_redirect( self::url( array( 'step' => 3 ) ) );
		exit;
	}

	/**
	 * The 3-step setup with the live preview.
	 *
	 * @param int $step 1, 2 or 3.
	 * @return void
	 */
	private static function render_setup( $step ) {
		$s      = CFBR_Settings::get();
		$defs   = CFBR_Fields::definitions();
		$cards  = array(
			'both' => array( __( 'Individuals and businesses', 'facilo-checkout-facil-brasil' ), __( 'CPF or CNPJ, the customer chooses', 'facilo-checkout-facil-brasil' ), '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c.8-3.4 3.2-5 6-5s5.2 1.6 6 5"/><path d="M15 20V8l6-1.5V20"/>' ),
			'pf'   => array( __( 'Individuals only (CPF)', 'facilo-checkout-facil-brasil' ), __( 'Shops that sell to consumers', 'facilo-checkout-facil-brasil' ), '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>' ),
			'pj'   => array( __( 'Businesses only (CNPJ)', 'facilo-checkout-facil-brasil' ), __( 'Wholesale and B2B sales', 'facilo-checkout-facil-brasil' ), '<path d="M4 21V5l8-2v18M12 9h8v12M7 8h2M7 12h2M7 16h2M15 13h2M15 17h2"/>' ),
			'none' => array( __( 'Do not ask for a document', 'facilo-checkout-facil-brasil' ), __( 'Only number and neighbourhood in the address', 'facilo-checkout-facil-brasil' ), '<path d="M5 12h14"/>' ),
		);
		$extras = array(
			'cellphone' => array( $defs['cellphone']['label'], __( 'Mask (00) 00000-0000. Also accepts landlines.', 'facilo-checkout-facil-brasil' ) ),
			'rg'        => array( $defs['rg']['label'], '' ),
			'ie'        => array( $defs['ie']['label'], __( 'Only shown for businesses, with the Exempt option', 'facilo-checkout-facil-brasil' ) ),
			'birthdate' => array( $defs['birthdate']['label'], '' ),
			'gender'    => array( $defs['gender']['label'], __( 'Always with “Prefer not to say”', 'facilo-checkout-facil-brasil' ) ),
		);
		$names  = array( __( 'Who buys', 'facilo-checkout-facil-brasil' ), __( 'Extra details', 'facilo-checkout-facil-brasil' ), __( 'Done', 'facilo-checkout-facil-brasil' ) );
		$svg    = array(
			'svg'    => array(
				'viewbox'         => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'aria-hidden'     => true,
				'focusable'       => true,
				'class'           => true,
			),
			'path'   => array( 'd' => true ),
			'circle' => array(
				'cx' => true,
				'cy' => true,
				'r'  => true,
			),
		);
		$exit   = self::url( array( 'tab' => 'fields' ) );
		?>
		<div class="wrap cfbr-setup" data-step="<?php echo esc_attr( $step ); ?>">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Checkout Fácil Brasil setup', 'facilo-checkout-facil-brasil' ); ?></h1>
			<header class="cfbr-top">
				<div class="cfbr-brand"><?php echo self::mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?><span><?php esc_html_e( 'Checkout Fácil Brasil', 'facilo-checkout-facil-brasil' ); ?></span></div>
				<ol class="cfbr-steps">
					<?php foreach ( $names as $i => $name ) : ?>
						<li class="<?php echo esc_attr( $i + 1 < $step ? 'done' : ( $i + 1 === $step ? 'cur' : '' ) ); ?>" data-step="<?php echo esc_attr( $i + 1 ); ?>"><?php echo esc_html( $name ); ?></li>
					<?php endforeach; ?>
				</ol>
				<a class="cfbr-exit" href="<?php echo esc_url( $exit ); ?>"><?php esc_html_e( 'Exit', 'facilo-checkout-facil-brasil' ); ?></a>
			</header>
			<div class="cfbr-setup-wrap">
				<main class="cfbr-q">
					<?php if ( 3 === $step ) : ?>
						<p class="cfbr-kick"><?php echo esc_html( sprintf( /* translators: 1: step number, 2: total steps. */ __( 'Step %1$d of %2$d', 'facilo-checkout-facil-brasil' ), 3, 3 ) ); ?></p>
						<div class="cfbr-done"><svg viewBox="0 0 64 64" aria-hidden="true" focusable="false"><circle cx="32" cy="32" r="30" fill="#e8edff"/><path d="M19 33l9 9 17-19" fill="none" stroke="#3858e9" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
						<h2 class="cfbr-h"><?php esc_html_e( 'All set. See how your checkout looks.', 'facilo-checkout-facil-brasil' ); ?></h2>
						<div class="cfbr-btns">
							<a class="cfbr-btn pri" href="<?php echo esc_url( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the checkout', 'facilo-checkout-facil-brasil' ); ?></a>
							<a class="cfbr-btn" href="<?php echo esc_url( $exit ); ?>"><?php esc_html_e( 'Go to the settings', 'facilo-checkout-facil-brasil' ); ?></a>
						</div>
						<p class="cfbr-sub"><?php esc_html_e( 'Works on the block checkout and on the classic one.', 'facilo-checkout-facil-brasil' ); ?></p>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cfbr-setup-form">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::SETUP ); ?>" />
							<?php wp_nonce_field( self::SETUP ); ?>
							<section class="cfbr-panel" data-panel="1" <?php echo 1 === $step ? '' : 'hidden'; ?>>
								<p class="cfbr-kick"><?php echo esc_html( sprintf( /* translators: 1: step number, 2: total steps. */ __( 'Step %1$d of %2$d', 'facilo-checkout-facil-brasil' ), 1, 3 ) ); ?></p>
								<fieldset>
									<legend class="cfbr-h"><?php esc_html_e( 'Who buys in your shop?', 'facilo-checkout-facil-brasil' ); ?></legend>
									<div class="cfbr-who">
										<?php foreach ( $cards as $value => $card ) : ?>
											<label class="cfbr-card">
												<input type="radio" name="cfbr[person_type]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $s['person_type'], $value ); ?> />
												<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true" focusable="false"><?php echo wp_kses( $card[2], $svg ); ?></svg>
												<b><?php echo esc_html( $card[0] ); ?></b>
												<small><?php echo esc_html( $card[1] ); ?></small>
											</label>
										<?php endforeach; ?>
									</div>
								</fieldset>
								<nav class="cfbr-nav"><span></span><button type="button" class="cfbr-btn pri" data-go="2"><?php esc_html_e( 'Continue', 'facilo-checkout-facil-brasil' ); ?></button></nav>
							</section>
							<section class="cfbr-panel" data-panel="2" <?php echo 2 === $step ? '' : 'hidden'; ?>>
								<p class="cfbr-kick"><?php echo esc_html( sprintf( /* translators: 1: step number, 2: total steps. */ __( 'Step %1$d of %2$d', 'facilo-checkout-facil-brasil' ), 2, 3 ) ); ?></p>
								<h2 class="cfbr-h"><?php esc_html_e( 'Which extra details do you want to ask for?', 'facilo-checkout-facil-brasil' ); ?></h2>
								<div class="cfbr-list">
									<?php
									foreach ( $extras as $field => $extra ) :
										$mode = CFBR_Settings::mode( $field );
										if ( 'cellphone' === $field && 'replace_phone' === $s['cellphone'] ) {
											$mode = 'required';
										}
										$on = 'off' !== $mode;
										?>
										<div class="cfbr-row<?php echo $on ? ' on' : ''; ?>" data-field="<?php echo esc_attr( $field ); ?>">
											<input type="checkbox" class="cfbr-switch" id="cfbr-setup-<?php echo esc_attr( $field ); ?>" name="cfbr[<?php echo esc_attr( $field ); ?>_on]" value="1" <?php checked( $on ); ?> />
											<label class="cfbr-row-t" for="cfbr-setup-<?php echo esc_attr( $field ); ?>"><b><?php echo esc_html( $extra[0] ); ?></b>
												<?php if ( '' !== $extra[1] ) : ?>
													<small><?php echo esc_html( $extra[1] ); ?></small>
												<?php endif; ?>
											</label>
											<span class="cfbr-req" role="radiogroup" aria-label="<?php echo esc_attr( $extra[0] ); ?>">
												<label><input type="radio" name="cfbr[<?php echo esc_attr( $field ); ?>]" value="optional" <?php checked( 'required' !== $mode ); ?> /><span><?php esc_html_e( 'Optional', 'facilo-checkout-facil-brasil' ); ?></span></label>
												<label><input type="radio" name="cfbr[<?php echo esc_attr( $field ); ?>]" value="required" <?php checked( 'required' === $mode ); ?> /><span><?php esc_html_e( 'Required', 'facilo-checkout-facil-brasil' ); ?></span></label>
											</span>
										</div>
									<?php endforeach; ?>
								</div>
								<p class="cfbr-fixed"><b><?php esc_html_e( 'Always included:', 'facilo-checkout-facil-brasil' ); ?></b> <?php esc_html_e( 'CPF or CNPJ, Number with “No number”, and Neighbourhood.', 'facilo-checkout-facil-brasil' ); ?></p>
								<nav class="cfbr-nav"><button type="button" class="cfbr-btn ghost" data-go="1"><?php esc_html_e( 'Back', 'facilo-checkout-facil-brasil' ); ?></button><button type="submit" class="cfbr-btn pri"><?php esc_html_e( 'Continue', 'facilo-checkout-facil-brasil' ); ?></button></nav>
							</section>
						</form>
					<?php endif; ?>
				</main>
				<aside class="cfbr-prev" aria-label="<?php esc_attr_e( 'Preview of your checkout', 'facilo-checkout-facil-brasil' ); ?>">
					<div class="cfbr-prev-h"><span class="cfbr-dot"></span><?php esc_html_e( 'Preview of your checkout', 'facilo-checkout-facil-brasil' ); ?> <em><?php esc_html_e( 'live', 'facilo-checkout-facil-brasil' ); ?></em></div>
					<?php self::preview( $s, $defs ); ?>
					<p class="cfbr-prev-f"><?php esc_html_e( 'Works on the block checkout and on the classic one.', 'facilo-checkout-facil-brasil' ); ?></p>
				</aside>
			</div>
		</div>
		<?php
	}

	/**
	 * The live preview: a small checkout drawn with the shop's settings. admin.js keeps it in step.
	 *
	 * @param array $s    Settings.
	 * @param array $defs Field definitions.
	 * @return void
	 */
	private static function preview( array $s, array $defs ) {
		$opt = function ( $field ) use ( $s ) {
			if ( 'cellphone' === $field && 'replace_phone' === $s['cellphone'] ) {
				return 'required';
			}
			return CFBR_Settings::mode( $field );
		};
		$box = function ( $label, $value, $attrs = '' ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs is built from escaped parts below.
			echo '<div class="cfbr-pv-f"' . $attrs . '><span class="cfbr-pv-l">' . esc_html( $label ) . '</span><span class="cfbr-pv-v">' . esc_html( $value ) . '</span></div>';
		};
		?>
		<div class="cfbr-pv" data-persons="<?php echo esc_attr( $s['person_type'] ); ?>" data-type="1">
			<p class="cfbr-pv-sec"><?php esc_html_e( 'Contact information', 'facilo-checkout-facil-brasil' ); ?></p>
			<?php $box( __( 'Email address', 'facilo-checkout-facil-brasil' ), 'cliente@exemplo.com.br' ); ?>
			<div class="cfbr-pv-pill" data-show="both" role="radiogroup" aria-label="<?php echo esc_attr( $defs['persontype']['label'] ); ?>">
				<button type="button" class="on" data-type="1" role="radio" aria-checked="true"><?php echo esc_html( $defs['persontype']['options']['1'] ); ?></button>
				<button type="button" data-type="2" role="radio" aria-checked="false"><?php echo esc_html( $defs['persontype']['options']['2'] ); ?></button>
			</div>
			<?php
			$box( $defs['cpf']['label'], '529.982.247-25', ' data-show="pf"' );
			$box( $defs['rg']['label'], '12.345.678-9', ' data-show="pf" data-opt="rg" data-mode="' . esc_attr( $opt( 'rg' ) ) . '"' );
			$box( $defs['cnpj']['label'], '12.ABC.345/01DE-35', ' data-show="pj"' );
			$box( $defs['ie']['label'], '110.042.490.114', ' data-show="pj" data-opt="ie" data-mode="' . esc_attr( $opt( 'ie' ) ) . '"' );
			$box( $defs['birthdate']['label'], '15/03/1990', ' data-opt="birthdate" data-mode="' . esc_attr( $opt( 'birthdate' ) ) . '"' );
			$box( $defs['gender']['label'], $defs['gender']['options'][ array_keys( $defs['gender']['options'] )[2] ], ' data-opt="gender" data-mode="' . esc_attr( $opt( 'gender' ) ) . '"' );
			$box( $defs['cellphone']['label'], '(11) 98765-4321', ' data-opt="cellphone" data-mode="' . esc_attr( $opt( 'cellphone' ) ) . '"' );
			?>
			<p class="cfbr-pv-sec"><?php esc_html_e( 'Billing address', 'facilo-checkout-facil-brasil' ); ?></p>
			<?php
			$box( CFBR_Fields::company_label(), 'Exemplo Comércio Ltda', ' data-show="pj"' );
			$box( __( 'CEP', 'facilo-checkout-facil-brasil' ), '01001-000' );
			$box( __( 'Address', 'facilo-checkout-facil-brasil' ), 'Praça da Sé' );
			?>
			<div class="cfbr-pv-row">
				<?php $box( $defs['number']['label'], '120' ); ?>
				<label class="cfbr-pv-check"><span class="cfbr-pv-box"></span><?php echo esc_html( $defs['nonumber']['label'] ); ?></label>
			</div>
			<?php $box( $defs['neighborhood']['label'], 'Sé' ); ?>
		</div>
		<?php
	}
}
