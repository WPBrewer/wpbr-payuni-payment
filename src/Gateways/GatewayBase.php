<?php
/**
 * GatewayBase class file
 *
 * @package payuni
 */

namespace WPBrewer\Payuni\Payment\Gateways;

use WPBrewer\Payuni\Payment\Api\PaymentRequest;
use WPBrewer\Payuni\Payment\PayuniPayment;
use WPBrewer\Payuni\Payment\Utils\OrderMeta;
use WPBrewer\Payuni\Payment\Utils\TradeStatus;

defined( 'ABSPATH' ) || exit;

/**
 * GatewayBase abstract class for handling all checkout related process.
 */
abstract class GatewayBase extends \WC_Payment_Gateway {

	/**
	 * Merchant ID
	 *
	 * @var string
	 */
	protected $merchant_id;

	/**
	 * Hash Key
	 *
	 * @var string
	 */
	protected $hashkey;

	/**
	 * Hash IV
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
	 * Frontend return url
	 *
	 * @var string
	 */
	public $return_url;

	/**
	 * Notify url
	 *
	 * @var string
	 */
	public $notify_url;

	/**
	 * The message displayed when the payment is incompleted
	 *
	 * @var string
	 */
	public $incomplete_payment_message;

	/**
	 * Minimum amount of the order
	 *
	 * @var string
	 */
	protected $min_amount = 0;

	/**
	 * Constructor
	 */
	public function __construct() {

		$this->has_fields        = false;
		$this->order_button_text = __( 'Proceed to PAYUNi', 'wpbr-payuni-payment' );
		$this->supports          = array(
			'products',
		);

		$this->testmode                   = wc_string_to_bool( get_option( 'payuni_payment_testmode_enabled' ) );
		$this->merchant_id                = ( $this->testmode ) ? strtoupper( get_option( 'payuni_payment_merchant_id_test' ) ) : strtoupper( get_option( 'payuni_payment_merchant_id' ) );
		$this->hashkey                    = ( $this->testmode ) ? get_option( 'payuni_payment_hashkey_test' ) : get_option( 'payuni_payment_hashkey' );
		$this->hashiv                     = ( $this->testmode ) ? get_option( 'payuni_payment_hashiv_test' ) : get_option( 'payuni_payment_hashiv' );
		$this->incomplete_payment_message = $this->get_option( 'incomplete_payment_message' );

		$this->api_url    = ( $this->testmode ) ? 'https://sandbox-api.payuni.com.tw/api/upp' : 'https://api.payuni.com.tw/api/upp';
		$this->notify_url = add_query_arg( 'wc-api', 'payuni_payment', home_url( '/' ) );
		$this->return_url = add_query_arg( 'wc-api', 'payuni_return', home_url( '/' ) );

		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'payuni_payment_detail_after_order_table' ), 10, 1 );
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'payuni_thankyou_order_unpaid_message' ), 10, 2 );
	}


	/**
	 * Display payment detail after order table
	 *
	 * @param  \WC_Order $order The order object.
	 * @return void
	 */
	public function payuni_payment_detail_after_order_table( $order ) {

		if ( $order->get_payment_method() === $this->id ) {

			echo '<h2>' . esc_html__( 'PAYUNi Payment Detail', 'wpbr-payuni-payment' ) . '</h2>';

			$trade_no_key = PayuniPayment::get_order_meta_key( $order, OrderMeta::UNI_NO );
			if ( empty( $order->get_meta( $trade_no_key ) ) ) {
				echo '<div class="payuni_payment_notify_not_received">' . esc_html__( 'If the payment detail is not displayed. Please wait for a moment and reload the page.', 'wpbr-payuni-payment' ) . '</div>';
			}

			echo '<table class="shop_table payuni_payment_details"><tbody>';

			$order_metas = self::get_order_metas();

			foreach ( $order_metas as $key => $value ) {
				echo '<tr><td><strong>' . esc_html( $value ) . '</strong></td>';
				$order_meta_key = PayuniPayment::get_order_meta_key( $order, $key );
				echo '<td>' . esc_html( $order->get_meta( $order_meta_key ) ) . '</td></tr>';
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
			esc_html__( '%1$s is a payment gateway provided by %2$s', 'wpbr-payuni-payment' ),
			esc_html( $this->get_method_title() ),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( 'https://www.payuni.com.tw/' ),
				esc_html__( 'PAYUNi', 'wpbr-payuni-payment' )
			)
		) . '</p>';
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	/**
	 * Process payment
	 *
	 * @param  string $order_id The order id.
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );
		// Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Redirect to payuni payment page
	 *
	 * @param  \WC_Order $order The order object.
	 * @return void
	 */
	public function receipt_page( $order ) {
		WC()->cart->empty_cart();
		$request = new PaymentRequest();
		$request->set_gateway( $this );
		$request->build_request_form( $order );
	}

	/**
	 * Display message on order thankyou page.
	 *
	 * @param  string    $text  Message on thankyou page.
	 * @param  \WC_Order $order The Order object.
	 * @return string
	 */
	public function payuni_thankyou_order_unpaid_message( $text, $order ) {
		if ( $order ) {
			if ( $order->get_payment_method() !== $this->id ) {
				return $text;
			}

			$trade_status_key = PayuniPayment::get_order_meta_key( $order, OrderMeta::TRADE_STATUS );
			$trade_status     = $order->get_meta( $trade_status_key );

			if ( 'pending' === $order->get_status() || TradeStatus::PAID !== $trade_status ) {
				if ( empty( $this->incomplete_payment_message ) ) {
					$text = '<span class="payuni-incomplete-payment-message">' . esc_html__( 'We have received your order, but the payment is incompleted.', 'wpbr-payuni-payment' ) . '</span>';
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
	 * Get order meta data
	 *
	 * @return array
	 */
	public static function get_order_metas() {
		return array_merge( PayuniPayment::$order_metas, static::get_payment_order_metas() );
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
	 * Return PAYUNi Merchant ID
	 *
	 * @return string
	 */
	public function get_merchant_id() {
		return $this->merchant_id;
	}

	/**
	 * Return PAYUNi Hash Key
	 *
	 * @return string
	 */
	public function get_hashkey() {
		return $this->hashkey;
	}

	/**
	 * Return PAYUNi Hash IV
	 *
	 * @return string
	 */
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
	 * @param  WC_Order $order The order object.
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
