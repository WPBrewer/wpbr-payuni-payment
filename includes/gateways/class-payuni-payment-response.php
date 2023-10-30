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
		add_action( 'woocommerce_api_payuni_payment', array( self::get_instance(), 'payuni_receive_response' ), 10 );
		add_action( 'woocommerce_api_payuni_return', array( self::get_instance(), 'payuni_receive_response_frontend' ), 20 );
	}

	/**
	 * Receive backend notification from PAYUNi NotifyURL
	 *
	 * @return void
	 */
	public static function payuni_receive_response() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing

		if ( empty( $_POST ) ) {
			return;
		}

		$posted = wc_clean( wp_unslash( $_POST ) );
		Payuni_Payment::log( 'payuni_receive_response from ' . current_action() . '. raw post data ' . wc_print_r( $posted, true ) );

		$mer_id = get_option( 'payuni_payment_merchant_id' );
		if ( ! array_key_exists( 'MerID', $posted ) || $mer_id !== $posted['MerID'] ) {
			Payuni_Payment::log( 'PAYUNi received response MerID not found or not match. ' );
			return;
		}

		$merid        = $posted['MerID'];
		$status       = array_key_exists( 'Status', $posted )? $posted['Status'] : '';
		$encrypt_info = array_key_exists( 'EncryptInfo', $posted )? $posted['EncryptInfo']: '';
		$hash_info    = array_key_exists( 'HashInfo', $posted )? $posted['HashInfo'] : '';


		$decrypted_info = Payuni_Payment::decrypt( $encrypt_info );
		Payuni_Payment::log( 'PAYUNi NotifyURL response decrypted:' . wc_print_r( $decrypted_info, true ) );

		$status          = $decrypted_info['Status']; // SUCESS = 成功，OK = 審核通過.
		$trade_status    = $decrypted_info['TradeStatus']; // 訂單狀態 0=取號成功 or 信用審查成功,  1 = 已付款, 2 = 付款失敗, 3 = 付款取消.
		$payuni_order_no = $decrypted_info['MerTradeNo'];
		$message         = $decrypted_info['Message'];
		$pay_type        = $decrypted_info['PaymentType'];

		$woo_order_id = Payuni_Payment::parse_payuni_order_no_to_woo_order_id( $payuni_order_no );

		$order = wc_get_order( $woo_order_id );
		if ( ! $order ) {
			Payuni_Payment::log( 'Cant find order by id:' . $woo_order_id );
			return;
		}

		self::save_payuni_order_data( $order, $decrypted_info );

		if ( '1' === $trade_status ) {
			if ( ! $order->is_paid() ) {
				$order->payment_complete( $decrypted_info['TradeNo'] );
				$order->add_order_note( 'PAYUNi payment completed (NotifyURL). Trade Status:' . $trade_status . ', Message:' . $message );
			}
		} else {
			$order->add_order_note( 'PAYUNi payment incompleted (NotifyURL). Pay Type:' . $pay_type . ', Trade Status:' . $trade_status . ', Message:' . $message );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

	}

	public static function payuni_receive_response_frontend() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing

		if ( empty( $_POST ) ) {
			return;
		}

		$posted = wc_clean( wp_unslash( $_POST ) );
		Payuni_Payment::log( 'payuni_receive_response from ' . current_action() . '. raw post data ' . wc_print_r( $posted, true ) );

		$mer_id = get_option( 'payuni_payment_merchant_id' );
		if ( ! array_key_exists( 'MerID', $posted ) || $mer_id !== $posted['MerID'] ) {
			Payuni_Payment::log( 'PAYUNi received response MerID not found or not match. ' );
			return;
		}

		$merid        = $posted['MerID'];
		$status       = array_key_exists( 'Status', $posted )? $posted['Status'] : '';
		$encrypt_info = array_key_exists( 'EncryptInfo', $posted )? $posted['EncryptInfo']: '';
		$hash_info    = array_key_exists( 'HashInfo', $posted )? $posted['HashInfo'] : '';


		$decrypted_info = Payuni_Payment::decrypt( $encrypt_info );
		Payuni_Payment::log( 'PAYUNi ReturnURL response decrypted:' . wc_print_r( $decrypted_info, true ) );

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

		self::save_payuni_order_data( $order, $decrypted_info );


		if ( '1' === $trade_status ) {
			if ( ! $order->is_paid() ) {
				$order->payment_complete( $decrypted_info['TradeNo'] );
				$order->add_order_note( 'PAYUNi payment completed (ReturnURL). Trade Status:' . $trade_status . ', Message:' . $message );
			}
		} else {
			$order->add_order_note( 'PAYUNi payment incompleted (ReturnURL). Pay Type:' . $pay_type . ', Trade Status:' . $trade_status . ', Message:' . $message );
		}

		wp_redirect( $order->get_checkout_order_received_url() );//訂單感謝頁面
		exit;

		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	private static function save_payuni_order_data( $order, $decrypted_info ) {

		$pay_type = $decrypted_info['PaymentType'];


		$order->update_meta_data( '_payuni_order_no', $decrypted_info['MerTradeNo'] );
		$order->update_meta_data( '_payuni_trade_no', $decrypted_info['TradeNo'] );
		$order->update_meta_data( '_payuni_trade_status', $decrypted_info['TradeStatus'] );
		$order->update_meta_data( '_payuni_trade_amt', $decrypted_info['TradeAmt'] );
		$order->update_meta_data( '_payuni_message', $decrypted_info['Message'] );

		self::update_order_meta( $order, $decrypted_info, '_payuni_credit_rescode', 'ResCode' );
		self::update_order_meta( $order, $decrypted_info, '_payuni_credit_rescode_msg', 'ResCodeMsg' );

		if ( '1' === $pay_type ) {
			// 信用卡.
			self::update_order_meta( $order, $decrypted_info, '_payuni_credit_authtype', 'AuthType' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_credit_card4no', 'Card4No' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_credit_authday', 'AuthDay' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_credit_authtime', 'AuthTime' );

			if ( '2' === $decrypted_info['AuthType'] ) {
				// 分期.
				self::update_order_meta( $order, $decrypted_info, '_payuni_credit_cardinst', 'CardInst' );
				self::update_order_meta( $order, $decrypted_info, '_payuni_credit_firstamt', 'FirstAmt' );
				self::update_order_meta( $order, $decrypted_info, '_payuni_credit_eachamt', 'EachAmt' );

			}

		} elseif ( '2' === $pay_type ) {
			// ATM虛擬帳號.
			self::update_order_meta( $order, $decrypted_info, '_payuni_atm_payno', 'PayNo' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_atm_banktype', 'BankType' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_atm_paytime', 'PayTime' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_atm_account5no', 'Account5No' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_atm_payset', 'PaySet' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_atm_expiredate', 'ExpireDate' );


		} elseif ( '3' === $pay_type ) {
			// 超商代碼.
			self::update_order_meta( $order, $decrypted_info, '_payuni_cvs_payno', 'PayNo' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_cvs_store', 'Store' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_cvs_expiredate', 'ExpireDate' );

		} elseif ( '7' === $pay_type ) {
			// AFTEE.
			self::update_order_meta( $order, $decrypted_info, '_payuni_aftee_payno', 'PayNo' );
			self::update_order_meta( $order, $decrypted_info, '_payuni_aftee_paytime', 'PayTime' );

		} elseif ( '9' === $pay_type ) {
			//LINE Pay
			self::update_order_meta( $order, $decrypted_info, '_payuni_linepay_payno', 'PayNo' );
		}

		$order->save();

	}



	/**
	 * A wrapper function to save received post data from PAYUNi
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
