<?php
/**
 * Payuni_Payment_Response class file
 *
 * @package payuni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Receive response from PAYUNi.
 */
class Payuni_Payment_Response {

	/**
	 * Class instance
	 *
	 * @var Payuni_Payment_Response
	 */
	private static $instance;

	/**
	 * Constructor
	 */
	public function __construct() {
		// do nothing.
	}

	/**
	 * Get the single instance or new one if not exists.
	 *
	 * @return Payuni_Payment_Response
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize and add hooks
	 *
	 * @return void
	 */
	public static function init() {
		self::get_instance();
		add_action( 'woocommerce_api_payuni_payment', array( self::get_instance(), 'payuni_receive_response' ) );
	}

	/**
	 * Receive response from PAYUNi
	 *
	 * @return void
	 */
	public static function payuni_receive_response() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		global $woocommerce;

		Payuni_Payment::log( 'payuni_receive_response. raw post data ' . wc_print_r( $_POST, true ) );

		$posted = wc_clean( wp_unslash( $_POST ) );

		$status       = $posted['Status'];
		$merid        = $posted['MerID'];
		$encrypt_info = $posted['EncryptInfo'];
		$hash_info    = $posted['HashInfo'];

		$decrypted_info = Payuni_Payment::decrypt( $encrypt_info );
		Payuni_Payment::log( 'PAYUNi response decrypted:' . wc_print_r( $decrypted_info, true ) );

		$status       = $decrypted_info['Status']; // SUCESS = 成功，OK = 審核通過.
		$trade_status = $decrypted_info['TradeStatus']; // 訂單狀態 0=取號成功or信用審查成功,  1 = 已付款, 2 = 付款失敗, 3 = 付款取消.
		$order_id     = $decrypted_info['MerTradeNo'];
		$message      = $decrypted_info['Message'];
		$pay_type     = $decrypted_info['PaymentType'];

		$woo_order_id = Payuni_Payment::parse_payuni_order_no_to_woo_order_id( $order_id );

		$order = wc_get_order( $woo_order_id );
		if ( ! $order ) {
			Payuni_Payment::log( 'Cant find order by id:' . $woo_order_id );
			return;
		}

		$order->update_meta_data( '_payuni_order_no', $order_id );
		$order->update_meta_data( '_payuni_trade_no', $decrypted_info['TradeNo'] );
		$order->update_meta_data( '_payuni_trade_status', $trade_status );
		$order->update_meta_data( '_payuni_trade_amt', $decrypted_info['TradeAmt'] );
		$order->update_meta_data( '_payuni_message', $message );

		self::update_order_meta( $order, $decrypted_info, '_payuni_credit_rescode', 'ResCode' );
		self::update_order_meta( $order, $decrypted_info, '_payuni_credit_rescode_msg', 'ResCodeMsg' );

		if ( '1' === $pay_type ) {


			self::update_order_meta( $order, $decrypted_info, '_payuni_credit_card4no', 'Card4No' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_credit_authday', 'AuthDay' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_credit_authtime', 'AuthTime' );

			// 分期.
			if ( '2' === $decrypted_info['AuthType'] ) {

				self::update_order_meta( $order, $decrypted_info, '_payuni_credit_cardinst', 'CardInst' );
				self::update_order_meta( $order, $decrypted_info, '_payuni_credit_firstamt', 'FirstAmt' );
				self::update_order_meta( $order, $decrypted_info, '_payuni_credit_eachamt', 'EachAmt' );

			}
			$order->save();

		} elseif ( '2' === $pay_type ) {

			self::update_order_meta( $order, $decrypted_info, '_payuni_atm_payno', 'PayNo' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_atm_banktype', 'BankType' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_atm_paytime', 'PayTime' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_atm_account5no', 'Account5No' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_atm_payset', 'PaySet' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_atm_expiredate', 'ExpireDate' );
			$order->save();

		} elseif ( '3' === $pay_type ) {

			self::update_order_meta( $order, $decrypted_info, '_payuni_cvs_payno', 'PayNo' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_cvs_store', 'Store' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_cvs_expiredate', 'ExpireDate' );
			$order->save();

		} elseif ( '7' === $pay_type ) {

			self::update_order_meta( $order, $decrypted_info, '_payuni_aftee_payno', 'PayNo' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_aftee_paytime', 'PayTime' );
			$order->save();

		}

		if ( '1' === $trade_status ) {
			$order->payment_complete( $decrypted_info['TradeNo'] );
			$order->add_order_note( 'PAYUNi payment completed. Trade Status:' . $trade_status . ', Message:' . $message );
		} else {
			$order->update_status( 'pending' );
			$order->update_meta_data( '_payuni_error_message', $message );
			$order->add_order_note( 'PAYUNi payment incompleted. Pay Type:' . $pay_type . ', Trade Status:' . $trade_status . ', Message:' . $message );
			$order->save();
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

	}



	/**
	 * Save received post data from PAYUNi
	 *
	 * @param WC_Order $order The order object.
	 * @param array    $data The post data received from PAYUNi.
	 * @param string   $meta_key The meta key to save.
	 * @param string   $data_key The data key to get.
	 */
	private static function update_order_meta( $order, $data, $meta_key, $data_key ) {
		if ( ! empty( $data[ $data_key ] ) ) {

			$value = ( 'Store' === $data_key && 'SEVEN' === $data[ $data_key ] ) ? '7-11' : $data[ $data_key ];
			$order->update_meta_data( $meta_key, $value );
		}
	}

}
