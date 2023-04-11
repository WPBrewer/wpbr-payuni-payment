<?php
/**
 * Payuni_Payment_Aftee class file
 *
 * @package payuni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Payuni_Payment_Aftee class for AFTEE payment
 */
class Payuni_Payment_Aftee extends Payuni_Abstract_Payment_Gateway {

	/**
	 *  Order payment meta
	 *
	 * @var array
	 */
	public static $order_metas;

	/**
	 * Constructor
	 */
	public function __construct() {

		parent::__construct();

		$this->id                 = 'payuni-aftee';
		$this->method_title       = __( 'PAYUNi AFTEE Payment', 'woo-payuni-payment' );
		$this->method_description = __( 'PAYUNi AFTEE Payment', 'woo-payuni-payment' );
		$this->supports           = array(
			'products',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->title                      = $this->get_option( 'title' );
		$this->description                = $this->get_option( 'description' );
		$this->min_amount                 = $this->get_option( 'min_amount' );
		$this->incomplete_payment_message = $this->get_option( 'incomplete_payment_message' );

		// self::$refund_api_url = ( $this->testmode ) ? 'https://sandbox-api.payuni.com.tw/api/trade/common/refund/aftee' : 'https://api.payuni.com.tw/api/trade/common/refund/aftee';

		static::$order_metas =
			array(
				'_payuni_aftee_payno'   => _x( 'Pay No', 'AFTEE', 'woo-payuni-payment' ),
				'_payuni_aftee_paytime' => __( 'Pay Time', 'woo-payuni-payment' ),
			);

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_filter( 'payuni_transaction_args_' . $this->id, array( $this, 'payuni_payment_aftee_transaction_arrgs' ), 10, 2 );

	}

	/**
	 * Setup form fields for payment
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = include PAYUNI_PLUGIN_DIR . 'includes/settings/settings-payuni-payment-aftee.php';
	}

	/**
	 * Set transaction args for AFTEE payment
	 *
	 * @param array    $args transaction args.
	 * @param WC_Order $order order object.
	 *
	 * @return array
	 */
	public function payuni_payment_aftee_transaction_arrgs( $args, $order ) {
		return array_merge(
			$args,
			array(
				'Aftee' => '1',
			)
		);
	}


}
