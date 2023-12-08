<?php
/**
 * Payuni_Payment_LINEPay class file
 *
 * @package payuni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Payuni_Payment_LINEPay class for LINE Pay payment
 */
class Payuni_Payment_LINEPay extends Payuni_Abstract_Payment_Gateway {

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

		$this->id                 = 'payuni-linepay';
		$this->method_title       = __( 'PAYUNi LINE Pay', 'wpbr-payuni-payment' );
		$this->method_description = __( 'PAYUNi LINE Pay', 'wpbr-payuni-payment' );
		$this->supports           = array(
			'products',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->title                      = $this->get_option( 'title' );
		$this->description                = $this->get_option( 'description' );
		$this->incomplete_payment_message = $this->get_option( 'incomplete_payment_message' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_filter( 'payuni_transaction_args_' . $this->id, array( $this, 'payuni_payment_linepay_transaction_arrgs' ), 10, 2 );

	}


	/**
	 * Setup form fields for payment
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = include PAYUNI_PLUGIN_DIR . 'includes/settings/settings-payuni-payment-linepay.php';
	}

	/**
	 * Add payment parameter for Apple Pay payment.
	 *
	 * @param array    $args The payment parameters.
	 * @param WC_ORDER $order The order object.
	 *
	 * @return array
	 */
	public function payuni_payment_linepay_transaction_arrgs( $args, $order ) {
		return array_merge(
			$args,
			array(
				'LinePay' => '1',
			)
		);
	}

	public static function get_payment_order_metas() {
		$order_metas =
			array(
				'_payuni_linepay_payno'   => _x( 'Pay No', 'LINE Pay', 'wpbr-payuni-payment' ),
			);

		return $order_metas;
	}

	/**
	 * Process the refund and return the result.
	 *
	 * This method is called only when the administrator processes it.
	 * When the administrator requests a refund, the process_refund() method is called through WC_AJAX::refund_line_items().
	 *
	 * @see woocommerce::action - woocommerce_delete_shop_order_transients
	 *
	 * @param int    $order_id The order id.
	 * @param float  $amount The ammount to be refund.
	 * @param string $reason The reason why the refund is requested.
	 *
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$request = new Payuni_Payment_Request( $this );
		return $request->refund( $order_id, $amount, $reason );
	}

}
