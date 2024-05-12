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
	 * Setting constructor.
	 */
	public function __construct() {

		$this->id    = 'payuni';
		$this->label = __( 'PAYUNi', 'wpbr-payuni-payment' );

		add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );

		add_action( 'admin_init', array( $this, 'payuni_redirect_default_tab' ) );

		add_filter( 'woocommerce_get_sections_' . $this->id, array( $this, 'payuni_payment_sections' ), 10, 1 );

		parent::__construct();
	}

	/**
	 * Get settings array.
	 * @param mixed $sections The sections.
	 * @return mixed The sections.
	 */
	public function payuni_payment_sections( $sections ) {

		unset( $sections[''] );
		if ( is_array( $sections ) && ! array_key_exists( 'payment', $sections ) ) {
			$sections['payment'] = __( 'Payment Settings', 'wpbr-payuni-payment' );
		}
		return $sections;
	}

	/**
	 * The settings for payment section.
	 * @return array The settings. 
	 */
	public function get_settings_for_payment_section() {
		$settings = apply_filters(
			'payuni_payment_settings',
			array(
				array(
					'title' => __( 'Payment Settings', 'wpbr-payuni-payment' ),
					'type'  => 'title',
					'id'    => 'payment_general_setting',
				),
				array(
					'title'   => __( 'Debug Log', 'wpbr-payuni-payment' ),
					'type'    => 'checkbox',
					'default' => 'no',
					/* translators:  %s is the order id */
					'desc'    => sprintf( __( 'Log PAYUNi payment message. You Can find logs with source name <strong>wpbr-payuni-payment</strong> at WooCommerce -> Status -> Logs. %s', 'wpbr-payuni-payment' ), $this->get_log_link() ),
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

		return $settings;
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

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
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

	/**
	 * Get debug logs url.
	 */
	protected function get_log_link() {
		return '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ) . '">' . __( 'View logs', 'wpbr-payuni-pro' ) . '</a>';
	}
}
