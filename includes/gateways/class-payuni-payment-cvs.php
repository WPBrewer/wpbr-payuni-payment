<?php
/**
 * Payuni_Payment_CVS class file
 *
 * @package payuni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Payuni_Payment_CVS class for Credit Card payment
 */
class Payuni_Payment_CVS extends Payuni_Abstract_Payment_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {

		parent::__construct();

		$this->id                 = 'payuni-cvs';
		$this->method_title       = __( 'PAYUNi CVS Payment', 'wpbr-payuni-payment' );
		$this->method_description = __( 'PAYUNi CVS Payment', 'wpbr-payuni-payment' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title                      = $this->get_option( 'title' );
		$this->description                = $this->get_option( 'description' );
		$this->expire_days                = empty( $this->get_option( 'expire_days' ) ) ? '7' : $this->get_option( 'expire_days' );
		$this->incomplete_payment_message = $this->get_option( 'incomplete_payment_message' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_filter( 'payuni_transaction_args_' . $this->id, array( $this, 'payuni_payment_cvs_transaction_arrgs' ), 10, 2 );

	}

	/**
	 * Setup form fields for payment
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = include WPBR_PAYUNI_PLUGIN_DIR . 'includes/settings/settings-payuni-payment-cvs.php';
	}

	/**
	 * Set cvs payment transaction args
	 *
	 * @param array    $args The transaction args.
	 * @param WC_Order $order The order object.
	 *
	 * @return array
	 */
	public function payuni_payment_cvs_transaction_arrgs( $args, $order ) {

		// set default time zone.
		date_default_timezone_set( 'Asia/Taipei' );

		return array_merge(
			$args,
			array(
				'ExpireDate' => date( 'Y-m-d', strtotime( '+' . $this->expire_days . ' days' ) ),
				'CVS'        => '1',
			)
		);
	}

	public static function get_payment_order_metas() {
		$order_metas =
			array(
				'_payuni_cvs_payno'      => _x( 'Pay No', 'CVS', 'wpbr-payuni-payment' ),
				'_payuni_cvs_store'      => __( 'CVS Store', 'wpbr-payuni-payment' ),
				'_payuni_cvs_expiredate' => __( 'Expire Date', 'wpbr-payuni-payment' ),
			);

		return $order_metas;
	}

}
