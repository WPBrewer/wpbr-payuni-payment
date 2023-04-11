<?php
/**
 * Payuni_Abstract_Payment_Gateway class file
 *
 * @package payuni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Payuni_Payment abstract class for handling all checkout related process.
 */
abstract class Payuni_Abstract_Payment_Gateway extends WC_Payment_Gateway {

	/**
	 * Web NO
	 *
	 * @var string
	 */
	protected $merchant_id;

	/**
	 * Trans Password
	 *
	 * @var string
	 */
	protected $hashkey;

	/**
	 * Pay Type
	 *
	 * @var string
	 */
	protected $hashiv;

	/**
	 * Test mode
	 *
	 * @var boolean
	 */
	protected $testmode;

	/**
	 * API url
	 *
	 * @var string
	 */
	protected $api_url;

	/**
	 * Minimum amount of the order
	 *
	 * @var string
	 */
	protected $min_amount = 0;

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

		// $this->icon              = $this->get_icon();
		$this->has_fields        = false;
		$this->order_button_text = __( 'Proceed to PAYUNi', 'woo-payuni-payment' );
		$this->supports          = array(
			'products',
		);

		$this->merchant_id                = strtoupper( get_option( 'payuni_payment_merchant_id' ) );
		$this->hashkey                    = get_option( 'payuni_payment_hashkey' );
		$this->hashiv                     = get_option( 'payuni_payment_hashiv' );
		$this->incomplete_payment_message = $this->get_option( 'incomplete_payment_message' );

		self::$order_metas = array(
			'_payuni_trade_no'     => __( 'Trade No', 'woo-payuni-payment' ),
			'_payuni_trade_amt'    => __( 'Trade Amount', 'woo-payuni-payment' ),
			'_payuni_trade_status' => __( 'Trade Status', 'woo-payuni-payment' ),
			'_payuni_message'      => __( 'Message', 'woo-payuni-payment' ),
		);

		$this->testmode       = wc_string_to_bool( get_option( 'payuni_payment_testmode_enabled' ) );
		$this->api_url        = ( $this->testmode ) ? 'https://sandbox-api.payuni.com.tw/api/upp' : 'https://api.payuni.com.tw/api/upp';
		$this->notify_url     = add_query_arg( 'wc-api', 'payuni_payment', home_url( '/' ) );

		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'payuni_payment_detail_after_order_table' ), 10, 1 );
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'payuni_thankyou_order_unpaid_message' ), 10, 2 );
	}


	/**
	 * Display payment detail after order table
	 *
	 * @param WC_Order $order The order object.
	 * @return void
	 */
	public function payuni_payment_detail_after_order_table( $order ) {

		if ( $order->get_meta( '_payment_method' ) === $this->id ) {

			echo '<h2>' . esc_html__( 'PAYUNi Payment Detail', 'woo-payuni-payment' ) . '</h2>';

			if ( empty( $order->get_meta( '_payuni_trade_no' ) ) ) {
				echo '<div class="payuni_payment_notify_not_received">' . esc_html__( 'If the payment detail is not displayed. Please wait for a moment and reload the page.', 'woo-payuni-payment' ) . '</div>';
			}

			echo '<table class="shop_table payuni_payment_details"><tbody>';

			$order_metas = self::get_order_metas();

			foreach ( $order_metas as $key => $value ) {
				echo '<tr><td><strong>' . esc_html( $value ) . '</strong></td>';
				echo '<td>' . esc_html( $order->get_meta( $key ) ) . '</td></tr>';
			}

			echo '</tbody></table>';
		}
	}

	/**
	 * Payment method settings
	 *
	 * @return void
	 */
	public function admin_options() {
		echo '<h3>' . esc_html( $this->get_method_title() ) . '</h3>';
		echo '<p>' . sprintf(
			/* translators: 1: Payment method title 2: PAYUNi URL */
			esc_html__( '%1$s is a payment gateway provided by %2$s', 'woo-payuni-payment' ),
			esc_html( $this->get_method_title() ),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( 'https://www.tcbbank.com.tw/' ),
				esc_html__( 'PAYUNi', 'woo-payuni-payment' )
			)
		) . '</p>';
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	/**
	 * Process payment
	 *
	 * @param string $order_id The order id.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		global $woocommerce;
		$order = new WC_Order( $order_id );

		// Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Redirect to payuni payment page
	 *
	 * @param WC_Order $order The order object.
	 * @return void
	 */
	public function receipt_page( $order ) {
		WC()->cart->empty_cart();
		$request = new Payuni_Payment_Request();
		$request->set_gateway( $this );
		$request->build_request_form( $order );
	}

	/**
	 * Display message on order thankyou page.
	 *
	 * @param string   $text Message on thankyou page.
	 * @param WC_Order $order The Order object.
	 * @return string
	 */
	public function payuni_thankyou_order_unpaid_message( $text, $order ) {
		if ( $order ) {
			if ( $order->get_payment_method() !== $this->id ) {
				return $text;
			}

			$tran_status = get_post_meta( $order->get_id(), '_payuni_trade_status', true );
			if ( 'pending' === $order->get_status() || '1' !== $tran_status ) {
				if ( empty( $this->incomplete_payment_message ) ) {
					$text = '<span class="payuni-incomplete-payment-message">' . esc_html__( 'We have received your order, but the payment is incompleted.', 'woo-payuni-payment' ) . '</span>';
				} else {
					$text = '<span class="payuni-incomplete-payment-message">' . $this->incomplete_payment_message . '</span>';
				}
			}
		}

		return $text;
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$is_available = ( 'yes' === $this->enabled );

		if ( WC()->cart && 0 < $this->get_order_total() && 0 < $this->max_amount && $this->max_amount < $this->get_order_total() ) {
			$is_available = false;
		}

		if ( WC()->cart && 0 < $this->get_order_total() && $this->min_amount > $this->get_order_total() ) {
			$is_available = false;
		}

		return $is_available;
	}

	/**
	 * Payment gateway icon output
	 *
	 * @return string
	 */
	public function get_icon() {
		// $icon_html  = '';
		// $icon_html .= '<img src="' . PAYUNI_PLUGIN_URL . 'payuni-logo.jpg " alt="' . __( 'PAYUNi Payment Gateway', 'woo-payuni-payment' ) . '" />';
		// return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Get order meta data
	 *
	 * @return array
	 */
	public static function get_order_metas() {
		return array_merge( self::$order_metas, static::$order_metas );
	}

	/**
	 * Return payment gateway method title
	 *
	 * @return string
	 */
	public function get_method_title() {
		return $this->method_title;
	}

	/**
	 * Return PAYUNi web no
	 *
	 * @return string
	 */
	public function get_merchant_id() {
		return $this->merchant_id;
	}

	public function get_hashkey() {
		return $this->hashkey;
	}

	public function get_hashiv() {
		return $this->hashiv;
	}



	/**
	 * Return PAYUNi payment url
	 *
	 * @return string
	 */
	public function get_api_url() {
		return $this->api_url;
	}

	/**
	 * Build items as string
	 *
	 * @param WC_Order $order The order object.
	 * @return string
	 */
	public function get_items_infos( $order ) {
		$items  = $order->get_items();
		$item_s = '';
		foreach ( $items as $item ) {
			$item_s .= $item['name'] . 'X' . $item['quantity'];
			if ( end( $items )['name'] !== $item['name'] ) {
				$item_s .= ',';
			}
		}
		$resp = ( mb_strlen( $item_s ) > 200 ) ? mb_substr( $item_s, 0, 200 ) : $item_s;
		return $resp;
	}

}
