<?php
/**
 * PAYUNi setting class.
 *
 * @package payuni
 */

defined( 'ABSPATH' ) || exit;
/**
 * Settings class.
 */
class WC_Settings_Tab_Payuni extends WC_Settings_Page {

	/**
	 * The sections.
	 *
	 * @var $sections
	 */
	private static $sections;

	/**
	 * Setting constructor.
	 */
	public function __construct() {

		$this->id    = 'payuni';
		$this->label = __( 'PAYUNi', 'wpbr-payuni-payment' );

		self::$sections = array(
			'payment' => __( 'Payment Settings', 'wpbr-payuni-payment' ),
		);

		add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
		// add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );

		add_action( 'admin_init', array( $this, 'payuni_redirect_default_tab' ) );

		parent::__construct();
	}

	/**
	 * Get setting sections
	 *
	 * @return array
	 */
	public function get_sections() {

		$sections = array();
		if ( is_plugin_active( 'wpbr-payuni-payment/wpbr-payuni-payment.php' ) ) {
			$sections['payment'] = __( 'Payment Settings', 'wpbr-payuni-payment' );
		} elseif ( is_plugin_active( 'wpbr-payuni-shipping/wpbr-payuni-shipping.php' ) ) {
			$sections['shipping'] = __( 'Shipping Settings', 'woo-payuni-shipping' );
		}

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Get all the settings for this plugin for @see woocommerce_admin_fields() function.
	 *
	 * @return array Array of settings for @see woocommerce_admin_fields() function.
	 */
	public function get_settings( $current_section = '' ) {

		if ( 'payment' === $current_section ) {
			$settings = apply_filters(
				'payuni_payment_settings',
				array(
					array(
						'title' => __( 'General Payment Settings', 'wpbr-payuni-payment' ),
						'type'  => 'title',
						'id'    => 'payment_general_setting',
					),
					array(
						'title'   => __( 'Debug Log', 'wpbr-payuni-payment' ),
						'type'    => 'checkbox',
						'default' => 'no',
						'desc'    => sprintf( __( 'Log PAYUNi payment message, inside <code>%s</code>', 'wpbr-payuni-payment' ), wc_get_log_file_path( 'wpbr-payuni-payment' ) ),
						'id'      => 'payuni_payment_debug_log_enabled',
					),
					array(
						'title'   => __( 'Number of Payments', 'wpbr-payuni-payment' ),
						'type'    => 'multiselect',
						'class'   => 'wc-enhanced-select',
						'css'     => 'width: 400px;',
						'options' => array(
							'payuni-installment-3'  => 3,
							'payuni-installment-6'  => 6,
							'payuni-installment-9'  => 9,
							'payuni-installment-12' => 12,
							'payuni-installment-18' => 18,
							'payuni-installment-24' => 24,
							'payuni-installment-30' => 30,
						),
						'desc'    => __( 'Please select the number of payment that customer can use, please make sure the installment you selected is eanbled in PAYUNi.', 'wpbr-payuni-payment' ),
						'id'      => 'payuni_payment_installment_number_of_payments',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'payment_general_setting',
					),
					array(
						'title' => __( 'API Settings', 'wpbr-payuni-payment' ),
						'type'  => 'title',
						'desc'  => __( 'Enter your PAYUNi API credentials', 'wpbr-payuni-payment' ),
						'id'    => 'payuni_payment_api_settings',
					),
					array(
						'title'   => __( 'Test Mode', 'wpbr-payuni-payment' ),
						'type'    => 'checkbox',
						'default' => 'yes',
						'desc'    => __( 'When enabled, you need to use the test-only data below.', 'wpbr-payuni-payment' ),
						'id'      => 'payuni_payment_testmode_enabled',
					),
					array(
						'title'    => __( 'MerchantID', 'wpbr-payuni-payment' ),
						'type'     => 'text',
						'desc'     => __( 'This is the MerchantID when you apply PAYUNi API', 'wpbr-payuni-payment' ),
						'desc_tip' => true,
						'id'       => 'payuni_payment_merchant_id',
					),
					array(
						'title'    => __( 'HashKey', 'wpbr-payuni-payment' ),
						'type'     => 'text',
						'desc'     => __( 'This is the HashKey when you apply PAYUNi API', 'wpbr-payuni-payment' ),
						'desc_tip' => true,
						'id'       => 'payuni_payment_hashkey',
					),
					array(
						'title'    => __( 'HashIV', 'wpbr-payuni-payment' ),
						'type'     => 'text',
						'desc'     => __( 'This is the HashIV when you apply PAYUNi API', 'wpbr-payuni-payment' ),
						'desc_tip' => true,
						'id'       => 'payuni_payment_hashiv',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'payuni_payment_api_settings',
					),
				)
			);
		}

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}

	/**
	 * Redirect to the default tab.
	 *
	 * @return void
	 */
	public function payuni_redirect_default_tab() {

		global $pagenow;

		if ( 'admin.php' !== $pagenow ) {
			return;
		}

		$page    = ( array_key_exists( 'page', $_GET ) ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tab     = ( array_key_exists( 'tab', $_GET ) ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		$section = ( array_key_exists( 'section', $_GET ) ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

		if ( 'wc-settings' === $page && 'payuni' === $tab ) {

			if ( empty( $section ) ) {
				wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=payuni&section=payment' ) );
				exit;
			}
		}

	}

	/**
	 * Output the setting tab
	 *
	 * @return void
	 */
	public function output() {
		global $current_section;

		if ( 'payment' !== $current_section ) {
			return;
		}

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Save the settings
	 *
	 * @return void
	 */
	public function save() {
		global $current_section;

		if ( 'payment' !== $current_section ) {
			return;
		}

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::save_fields( $settings );
	}
}
