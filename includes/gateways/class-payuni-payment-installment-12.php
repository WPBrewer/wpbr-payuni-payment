<?php
/**
 * Payuni_Payment_Installment_12 class file
 *
 * @package payuni
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PAYUNi Credit Card Payment method (12 installments)
 */
class Payuni_Payment_Installment_12 extends Payuni_Abstract_Payment_Gateway {

	use PayuniInstallmentableTrait;

	/**
	 * The Constructor
	 */
	public function __construct() {

		parent::__construct();

		$this->id                 = 'payuni-installment-12';
		$this->method_title       = __( 'PAYUNi Installment Payment (12 Installments)', 'wpbr-payuni-payment' );
		$this->method_description = __( 'PAYUNi Installment Payment (12 Installments)', 'wpbr-payuni-payment' );

		// Load the settings.
		$this->init_from_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->min_amount  = $this->get_option( 'min_amount' );

		$this->init_installment( 12, $this->min_amount );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_filter( 'payuni_transaction_args_' . $this->id, array( $this, 'payuni_payment_installment_transaction_arrgs' ), 10, 2 );

	}

}//end class
