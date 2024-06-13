<?php
/**
 * CreditInstallment18 class file
 *
 * @package payuni
 */

namespace WPBrewer\Payuni\Payment\Gateways;

defined( 'ABSPATH' ) || exit;

/**
 * PAYUNi Credit Card Payment method (18 installments)
 */
class CreditInstallment18 extends GatewayBase {

	const GATEWAY_ID = 'payuni-upp-installment-18';

	use TraitCreditInstallment;

	/**
	 * The Constructor
	 */
	public function __construct() {

		parent::__construct();

		$this->id                 = self::GATEWAY_ID;
		$this->method_title       = __( 'PAYUNi Installment Payment (18 Installments)', 'wpbr-payuni-payment' );
		$this->method_description = __( 'PAYUNi Installment Payment (18 Installments)', 'wpbr-payuni-payment' );

		// Load the settings.
		$this->init_from_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->min_amount  = $this->get_option( 'min_amount' );

		$this->init_installment( 18, $this->min_amount );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_filter( 'payuni_transaction_args_' . $this->id, array( $this, 'payuni_payment_installment_transaction_arrgs' ), 10, 2 );
	}
}//end class