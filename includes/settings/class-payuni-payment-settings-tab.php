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
		$this->label = __( 'PAYUNi', 'woo-payuni-payment' );

		add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
		add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );

		parent::__construct();
	}

	/**
	 * Get setting sections
	 *
	 * @return array
	 */
	public function get_sections() {

		$sections = array(
			'' => __( 'Payment Settings', 'woo-payuni-payment' ),
		);

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Get all the settings for this plugin for @see woocommerce_admin_fields() function.
	 *
	 * @return array Array of settings for @see woocommerce_admin_fields() function.
	 */
	public function get_settings( $current_section = '' ) {

			$settings = apply_filters(
				'payuni_payment_settings',
				array(
					array(
						'title' => __( 'General Payment Settings', 'woo-payuni-payment' ),
						'type'  => 'title',
						'id'    => 'payment_general_setting',
					),
					array(
						'title'   => __( 'Debug Log', 'woo-payuni-payment' ),
						'type'    => 'checkbox',
						'default' => 'no',
						'desc'    => sprintf( __( 'Log PAYUNi payment message, inside <code>%s</code>', 'woo-payuni-payment' ), wc_get_log_file_path( 'woo-payuni-payment' ) ),
						'id'      => 'payuni_payment_debug_log_enabled',
					),
					array(
						'title'   => __( 'Number of Payments', 'woo-payuni-payment' ),
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
						'desc'    => __( 'Please select the number of payment that customer can use, please make sure the installment you selected is eanbled in PAYUNi.', 'woo-payuni-payment' ),
						'id'      => 'payuni_payment_installment_number_of_payments',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'payment_general_setting',
					),
					array(
						'title' => __( 'API Settings', 'woo-payuni-payment' ),
						'type'  => 'title',
						'desc'  => __( 'Enter your PAYUNi API credentials', 'woo-payuni-payment' ),
						'id'    => 'payuni_payment_api_settings',
					),
					array(
						'title'   => __( 'Test Mode', 'woo-payuni-payment' ),
						'type'    => 'checkbox',
						'default' => 'yes',
						'desc'    => __( 'When enabled, you need to use the test-only data below.', 'woo-payuni-payment' ),
						'id'      => 'payuni_payment_testmode_enabled',
					),
					array(
						'title'    => __( 'MerchantID', 'woo-payuni-payment' ),
						'type'     => 'text',
						'desc'     => __( 'This is the MerchantID when you apply PAYUNi API', 'woo-payuni-payment' ),
						'desc_tip' => true,
						'id'       => 'payuni_payment_merchant_id',
					),
					array(
						'title'    => __( 'HashKey', 'woo-payuni-payment' ),
						'type'     => 'text',
						'desc'     => __( 'This is the HashKey when you apply PAYUNi API', 'woo-payuni-payment' ),
						'desc_tip' => true,
						'id'       => 'payuni_payment_hashkey',
					),
					array(
						'title'    => __( 'HashIV', 'woo-payuni-payment' ),
						'type'     => 'text',
						'desc'     => __( 'This is the HashIV when you apply PAYUNi API', 'woo-payuni-payment' ),
						'desc_tip' => true,
						'id'       => 'payuni_payment_hashiv',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'payuni_payment_api_settings',
					),
				)
			);

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}

	/**
	 * Output the setting tab
	 *
	 * @return void
	 */
	public function output() {
		global $current_section;
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
		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::save_fields( $settings );
	}
}
