<?php
/**
 * Payuni_Payment_ATM class file
 *
 * @package payuni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Payuni_Payment_ATM class for Credit Card payment
 */
class Payuni_Payment_ATM extends Payuni_Abstract_Payment_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {

		parent::__construct();

		$this->id                 = 'payuni-atm';
		$this->method_title       = __( 'PAYUNi ATM Payment', 'wpbr-payuni-payment' );
		$this->method_description = __( 'PAYUNi ATM Payment', 'wpbr-payuni-payment' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->expire_days = empty( $this->get_option( 'expire_days' ) ) ? '7' : $this->get_option( 'expire_days' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_filter( 'payuni_transaction_args_' . $this->id, array( $this, 'payuni_payment_atm_transaction_arrgs' ), 10, 2 );
	}

	/**
	 * Setup form fields for payment
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = include WPBR_PAYUNI_PLUGIN_DIR . 'includes/settings/settings-payuni-payment-atm.php';
	}

	/**
	 * Set ATM payment  transaction args
	 *
	 * @param array    $args The transaction args.
	 * @param WC_Order $order The order object.
	 * @return array
	 */
	public function payuni_payment_atm_transaction_arrgs( $args, $order ) {

		// set default time zone.
		date_default_timezone_set( 'Asia/Taipei' );

		return array_merge(
			$args,
			array(
				'ExpireDate' => date( 'Y-m-d H:i:s', strtotime( '+' . $this->expire_days . ' days' ) ),
				'ATM'        => '1',
			)
		);
	}

	public static function get_payment_order_metas() {
		$order_metas =
			array(
				'_payuni_atm_payno'      => _x( 'Pay No', 'ATM', 'wpbr-payuni-payment' ),
				'_payuni_atm_banktype'   => __( 'Bank Code', 'wpbr-payuni-payment' ),
				'_payuni_atm_expiredate' => __( 'Expire Date', 'wpbr-payuni-payment' ),
				'_payuni_atm_paytime'    => __( 'Pay Time', 'wpbr-payuni-payment' ),
				'_payuni_atm_account5no' => __( 'Account 5 No', 'wpbr-payuni-payment' ),
			);

		return $order_metas;
	}

}
