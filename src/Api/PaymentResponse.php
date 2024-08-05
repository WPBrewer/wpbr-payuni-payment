<?php
/**
 * PaymentResponse class file
 *
 * @package payuni
 */

namespace WPBrewer\Payuni\Payment\Api;

use WPBrewer\Payuni\Payment\PayuniPayment;
use WPBrewer\Payuni\Payment\Utils\OrderMeta;
use WPBrewer\Payuni\Payment\Utils\SingletonTrait;
use WPBrewer\Payuni\Payment\Utils\TradeStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Receive response from PAYUNi.
 */
class PaymentResponse {

	use SingletonTrait;

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

		$test_mode     = wc_string_to_bool( get_option( 'payuni_payment_testmode_enabled' ) );
		$mer_id        = $test_mode ? get_option( 'payuni_payment_merchant_id_test' ) : get_option( 'payuni_payment_merchant_id' );
		$posted_mer_id = ( isset( $_POST['MerID'] ) ) ? wc_clean( wp_unslash( $_POST['MerID'] ) ) : '';

		if ( $mer_id !== $posted_mer_id ) {
			PayuniPayment::log( 'PAYUNi received response MerID not found or not match. ' );
			return;
		}

		$encrypt_info   = ( isset( $_POST['EncryptInfo'] ) ) ? wc_clean( wp_unslash( $_POST['EncryptInfo'] ) ) : '';
		$decrypted_info = PayuniPayment::decrypt( $encrypt_info );
		PayuniPayment::log( 'PAYUNi NotifyURL response decrypted:' . wc_print_r( $decrypted_info, true ) );

		$status          = $decrypted_info['Status']; // SUCESS = 成功，OK = 審核通過.
		$trade_status    = $decrypted_info['TradeStatus']; // 訂單狀態 0=取號成功 or 信用審查成功,  1 = 已付款, 2 = 付款失敗, 3 = 付款取消.
		$payuni_order_no = $decrypted_info['MerTradeNo'];
		$message         = $decrypted_info['Message'];
		$pay_type        = $decrypted_info['PaymentType'];

		$woo_order_id = PayuniPayment::parse_payuni_order_no_to_woo_order_id( $payuni_order_no );
		$order        = wc_get_order( $woo_order_id );
		if ( ! $order ) {
			PayuniPayment::log( 'Cant find order by id:' . $woo_order_id );
			return;
		}

		self::save_payuni_order_data( $order, $decrypted_info );

		if ( TradeStatus::PAID === $trade_status ) {
			if ( ! $order->is_paid() ) {
				$order->payment_complete( $decrypted_info['TradeNo'] );
				$order->add_order_note( 'PAYUNi payment completed (NotifyURL). Trade Status:' . $trade_status . ', Message:' . $message );
			}
		} elseif ( TradeStatus::EXPIRED === $trade_status ) {
			$order->update_status( 'failed' );
			$order->add_order_note( 'PAYUNi payment expired (NotifyURL). Pay Type:' . $pay_type . ', Trade Status:' . $trade_status . ', Message:' . $message );
		} elseif ( TradeStatus::CANCEL === $trade_status || TradeStatus::FAIL === $trade_status ) {
			$order->update_status( 'failed' );
			$order->add_order_note( 'PAYUNi payment cancelled or failed (NotifyURL). Pay Type:' . $pay_type . ', Trade Status:' . $trade_status . ', Message:' . $message );
		} else {
			$order->add_order_note( 'PAYUNi payment incompleted (NotifyURL). Pay Type:' . $pay_type . ', Trade Status:' . $trade_status . ', Message:' . $message );
		}

     // phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Receive return from PAYUNi ReturnURL
	 */
	public static function payuni_receive_response_frontend() {
     // phpcs:disable WordPress.Security.NonceVerification.Missing	
		if ( empty( $_POST ) ) {
			return;
		}

		$test_mode     = wc_string_to_bool( get_option( 'payuni_payment_testmode_enabled' ) );
		$mer_id        = $test_mode ? get_option( 'payuni_payment_merchant_id_test' ) : get_option( 'payuni_payment_merchant_id' );
		$posted_mer_id = ( isset( $_POST['MerID'] ) ) ? wc_clean( wp_unslash( $_POST['MerID'] ) ) : '';

		if ( $mer_id !== $posted_mer_id ) {
			PayuniPayment::log( 'PAYUNi received response MerID not found or not match. ' );
			return;
		}

		$encrypt_info   = ( isset( $_POST['EncryptInfo'] ) ) ? wc_clean( wp_unslash( $_POST['EncryptInfo'] ) ) : '';
		$decrypted_info = PayuniPayment::decrypt( $encrypt_info );
		PayuniPayment::log( 'PAYUNi ReturnURL response decrypted:' . wc_print_r( $decrypted_info, true ) );

		$status       = $decrypted_info['Status']; // SUCESS = 成功，OK = 審核通過.
		$trade_status = $decrypted_info['TradeStatus']; // 訂單狀態 0=取號成功or信用審查成功,  1 = 已付款, 2 = 付款失敗, 3 = 付款取消.
		$order_id     = $decrypted_info['MerTradeNo'];
		$message      = $decrypted_info['Message'];
		$pay_type     = $decrypted_info['PaymentType'];

		$woo_order_id = PayuniPayment::parse_payuni_order_no_to_woo_order_id( $order_id );
		$order        = wc_get_order( $woo_order_id );
		if ( ! $order ) {
			PayuniPayment::log( 'Cant find order by id:' . $woo_order_id );
			return;
		}

		self::save_payuni_order_data( $order, $decrypted_info );

		if ( TradeStatus::PAID === $trade_status ) {
			if ( ! $order->is_paid() ) {
				$order->payment_complete( $decrypted_info['TradeNo'] );
				$order->add_order_note( 'PAYUNi payment completed (ReturnURL). Trade Status:' . $trade_status . ', Message:' . $message );
			}
		} else {
			$order->add_order_note( 'PAYUNi payment incompleted (ReturnURL). Pay Type:' . $pay_type . ', Trade Status:' . $trade_status . ', Message:' . $message );
		}

		wp_redirect( $order->get_checkout_order_received_url() );// 訂單感謝頁面.
		exit;

     // phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Save PAYUNi order data to WooCommerce order meta
	 *
	 * @param WC_Order $order          The order object.
	 * @param array    $decrypted_info The decrypted info from PAYUNi.
	 */
	private static function save_payuni_order_data( $order, $decrypted_info ) {

		$pay_type = $decrypted_info['PaymentType'];

		$order->update_meta_data( OrderMeta::PAYUNI_ORDER_NO, $decrypted_info['MerTradeNo'] );
		$order->update_meta_data( OrderMeta::UNI_NO, $decrypted_info['TradeNo'] );
		$order->update_meta_data( OrderMeta::TRADE_STATUS, $decrypted_info['TradeStatus'] );
		$order->update_meta_data( OrderMeta::TRADE_AMOUNT, $decrypted_info['TradeAmt'] );
		$order->update_meta_data( OrderMeta::MESSAGE, $decrypted_info['Message'] );

		self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_REST_CODE, 'ResCode' );
		self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_REST_CODE_MSG, 'ResCodeMsg' );

		if ( '1' === $pay_type ) {
			// 信用卡.
			self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_AUTH_TYPE, 'AuthType' );
			self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_CARD_4NO, 'Card4No' );
			self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_AUTH_DAY, 'AuthDay' );
			self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_AUTH_TIME, 'AuthTime' );

			if ( '2' === $decrypted_info['AuthType'] ) {
				// 分期.
				self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_INSTALL, 'CardInst' );
				self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_FIRST_AMT, 'FirstAmt' );
				self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_EACH_AMT, 'EachAmt' );

			}
		} elseif ( '2' === $pay_type ) {
			// ATM虛擬帳號.
			self::update_order_meta( $order, $decrypted_info, OrderMeta::AMT_PAY_NO, 'PayNo' );
			self::update_order_meta( $order, $decrypted_info, OrderMeta::AMT_BANK_TYPE, 'BankType' );
			self::update_order_meta( $order, $decrypted_info, OrderMeta::AMT_PAY_TIME, 'PayTime' );
			self::update_order_meta( $order, $decrypted_info, OrderMeta::AMT_ACCOUNT_5NO, 'Account5No' );
			self::update_order_meta( $order, $decrypted_info, OrderMeta::AMT_PAY_SET, 'PaySet' );
			self::update_order_meta( $order, $decrypted_info, OrderMeta::AMT_EXPIRE_DATE, 'ExpireDate' );

		} elseif ( '3' === $pay_type ) {
			// 超商代碼.
			self::update_order_meta( $order, $decrypted_info, OrderMeta::CVS_PAY_NO, 'PayNo' );
			self::update_order_meta( $order, $decrypted_info, OrderMeta::CVS_STORE, 'Store' );
			self::update_order_meta( $order, $decrypted_info, OrderMeta::CVS_EXPIRE_DATE, 'ExpireDate' );

		} elseif ( '7' === $pay_type ) {
			// AFTEE.
			self::update_order_meta( $order, $decrypted_info, OrderMeta::AFTEE_PAY_NO, 'PayNo' );
			self::update_order_meta( $order, $decrypted_info, OrderMeta::AFTEE_PAY_TIME, 'PayTime' );

		} elseif ( '9' === $pay_type ) {
			// LINE Pay.
			self::update_order_meta( $order, $decrypted_info, OrderMeta::LINE_PAY_NO, 'PayNo' );
		}

		$order->update_meta_data( OrderMeta::PLUGIN_VERSION, WPBR_PAYUNI_PAYMENT_VERSION );

		$order->save();
	}

	/**
	 * A wrapper function to save received post data from PAYUNi
	 *
	 * @param WC_Order $order    The order object.
	 * @param array    $data     The post data received from PAYUNi.
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
