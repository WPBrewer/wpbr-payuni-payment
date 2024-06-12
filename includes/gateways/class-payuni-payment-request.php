<?php
/**
 * Payuni_Payment_Request class file
 *
 * @package payuni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generates payment form and redirect to PAYUNi
 */
class Payuni_Payment_Request {

	/**
	 * The gateway instance
	 *
	 * @var WC_Payment_Gateway
	 */
	protected $gateway;

	/**
	 * Build transaction args.
	 *
	 * @param  WC_Order $order The order object.
	 * @return array
	 */
	public function get_transaction_args( $order ) {

		Payuni_Payment::log( 'merchant id:' . $this->gateway->get_merchant_id() );
		Payuni_Payment::log( 'hashkey:' . get_option( 'payuni_payment_hashkey' ) );
		Payuni_Payment::log( 'hashiv:' . get_option( 'payuni_payment_hashiv' ) );

		$prod_desc = array();
		$items     = $order->get_items();
		foreach ( $items as $item ) {
			$prod_desc[] = $item->get_name() . ' * ' . $item->get_quantity();
		}

		$encrypt_info = apply_filters(
			'payuni_transaction_args_' . $this->gateway->id,
			array(
				'MerID'      => $this->gateway->get_merchant_id(),
				'MerTradeNo' => Payuni_Payment::build_payuni_order_no( $order->get_id() ),
				'TradeAmt'   => (int) $order->get_total(),
				'ProdDesc'   => implode( ';', $prod_desc ),
				// 'BackURL'    => $order->get_checkout_payment_url( true ),
				'ReturnURL'  => $this->gateway->return_url, // 前景通知網址付款完成返回指定網址 (感謝頁面)
				'NotifyURL'  => $this->gateway->notify_url, // 幕後.
				'UsrMail'    => $order->get_billing_email(), // 付款頁帶入 email
				'UsrMailFix' => '1', // 不可修改 email
				'Timestamp'  => time(),
				'Lang'       => get_option( 'payuni_payment_language', 'zh-tw' ),
			),
			$order
		);

		Payuni_Payment::log( 'request encrypt info:' . wc_print_r( $encrypt_info, true ) );

		$encrypted_info = Payuni_Payment::encrypt( $encrypt_info );

		$args = array(
			'MerID'       => $this->gateway->get_merchant_id(),
			'Version'     => '1.0',
			'EncryptInfo' => $encrypted_info,
			'HashInfo'    => Payuni_Payment::hash_info( $encrypted_info ),
		);

		return $args;
	}

	/**
	 * Generate the form and redirect to PAYUNi
	 *
	 * @param  WC_Order $order The order object.
	 * @return void
	 */
	public function build_request_form( $order ) {

		$order = wc_get_order( $order );

		try {
			?>
			<div><?php esc_html_e( 'Redirecting...', 'wpbr-payuni-payment' ); ?></div>
			<form method="post" id="payuni-form" action="<?php echo esc_url( $this->gateway->get_api_url() ); ?>" accept="UTF-8" accept-charset="UTF-8">
			<?php
			$fields = $this->get_transaction_args( $order );

			Payuni_Payment::log( 'request transaction args:' . wc_print_r( $fields, true ) );

			foreach ( $fields as $key => $value ) {
				echo '<input type="hidden" name="' . esc_html( $key ) . '" value="' . esc_html( $value ) . '">';
			}
			?>
			</form>
			<script type="text/javascript">
				document.getElementById('payuni-form').submit();
			</script>
			<?php

		} catch ( Exception $e ) {
			self::log( $e->getMessage() . ' ' . $e->getTraceAsString() );
		}
	}

	public function refund( $order_id, $amount, $reason ) {
		$order = wc_get_order( $order_id );

		if ( false === $order ) {

			return new WP_Error(
				'process_refund_request',
				/* translators:  %s is the order id */
				sprintf( __( 'Unable to find order #%s', 'wpbr-payuni-payment' ), $order_id ),
				array(
					'order_id'      => $order_id,
					'refund_amount' => $amount,
				)
			);

		}

		$payment_method = $order->get_payment_method();
		if ( array_key_exists( $payment_method, Payuni_Payment::$available_installments ) ) {
			if ( $order->get_total() !== $amount ) {
				return new WP_Error(
					'process_refund_request',
					/* translators:  %s is the order id */
					sprintf( __( 'The refund amount for order #%s should be the same as the order total for installment payment.', 'wpbr-payuni-payment' ), $order_id ),
					array(
						'order_id' => $order_id,
					)
				);
			}
		}

		$test_mode      = wc_string_to_bool( get_option( 'payuni_payment_testmode_enabled' ) );
		$mer_id         = $test_mode ? get_option( 'payuni_payment_merchant_id_test' ) : get_option( 'payuni_payment_merchant_id' );
		$transaction_id = $order->get_transaction_id();

		if ( empty( $transaction_id ) ) {
			return new WP_Error(
				'process_refund_request',
				/* translators:  %s is the order id */
				sprintf( __( 'Unable to find transaction id for order #%s', 'wpbr-payuni-payment' ), $order_id ),
				array(
					'order_id'      => $order_id,
					'refund_amount' => $amount,
				)
			);
		}

		$query_result = self::query( $order_id );
		if ( $query_result === false ) {
			return new WP_Error(
				'process_refund_request',
				/* translators:  %s is the order id */
				sprintf( __( 'Unable to Query Order status before refund', 'wpbr-payuni-payment' ), $order_id ),
				array(
					'order_id'      => $order_id,
					'refund_amount' => $amount,
				)
			);
		}

		$trade_status = $query_result['TradeStatus'];
		//9=未付款
		//1=已付款 (授權成功，請款申請中)
		//2=付款失敗
		//3=付款取消 (取消授權成功)
		//4=交易逾期
		
		//已付款
		if ( '1' === $trade_status ) {
			
			//請款狀態
			$close_status = $query_result['CloseStatus'];
			
			// 1=請款申請中，要用取消授權，9=未申請(for手動請款)
			if ( self::is_cancellable( $close_status ) ) {
				$encrypt_info = array(
					'MerID'     => $mer_id,
					'TradeNo'   => $transaction_id,
					'Timestamp' => time(),
				);
				$url = ( wc_string_to_bool( get_option( 'payuni_payment_testmode_enabled' ) ) ) ? 'https://sandbox-api.payuni.com.tw/api/trade/cancel' : 'https://api.payuni.com.tw/api/trade/cancel';
			} elseif ( self::is_closeable( $close_status ) ) {
				// 2=請款成功, // 7=請款處理中，要用退款
				$encrypt_info = array(
					'MerID'       => $mer_id,
					'TradeNo'     => $transaction_id,
					'Timestamp'   => time(),
					'CloseType'   => 2,
					'TradeAmt' => $amount
				);
				$url = ( wc_string_to_bool( get_option( 'payuni_payment_testmode_enabled' ) ) ) ? 'https://sandbox-api.payuni.com.tw/api/trade/close' : 'https://api.payuni.com.tw/api/trade/close';
			} else {
				
				return new WP_Error(
					'process_refund_request',
					/* translators:  %s is the TradeStatus of the order */
					sprintf( __( 'Unable to Refund this Order. TradeStatus: %1$s, CloseStatus: %2$s', 'wpbr-payuni-payment' ), $trade_status, $close_status ),
					array(
						'order_id'      => $order_id,
						'refund_amount' => $amount,
					)
				);
			}
			
			Payuni_Payment::log( 'refund url:' . $url );
		} else {
			return new WP_Error(
				'process_refund_request',
				/* translators:  %s is the TradeStatus of the order */
				sprintf( __( 'Unable to Refund this Order. TradeStatus:%s', 'wpbr-payuni-payment' ), $trade_status ),
				array(
					'order_id'      => $order_id,
					'refund_amount' => $amount,
				)
			);
		}

		Payuni_Payment::log( 'encrypt_info:' . wc_print_r( $encrypt_info, true ) );

		$encrypted_info = Payuni_Payment::encrypt( $encrypt_info );

		// Set the form data as an array
		$form_data = array(
			'MerID'       => $mer_id,
			'Version'     => '1.0',
			'EncryptInfo' => $encrypted_info,
			'HashInfo'    => Payuni_Payment::hash_info( $encrypted_info ),
		);
		Payuni_Payment::log( 'form data:' . wc_print_r( $form_data, true ) );

		$request_args = array(
			'httpversion' => '1,1',
			'timeout'     => '30',
			'body'        => $form_data,
		);

		// for credit card refund.
		$payment_class = Payuni_Payment::$allowed_payments[ $payment_method ];
		if ( empty( $payment_class ) ) {
			throw new Exception( 'PAYUNi refund failed. Payment method not found.' );
		}

		$response = wp_remote_post( $url, $request_args );
		if ( is_wp_error( $response ) ) {
			Payuni_Payment::log( 'refund error:' . $response->get_error_message() );
			return false;
		}

		$response_body = wp_remote_retrieve_body( $response );
		Payuni_Payment::log( 'refund response body:' . wc_print_r( $response_body, true ) );

		$result    = json_decode( $response_body, true );
		$decrypted = Payuni_Payment::decrypt( $result['EncryptInfo'] );
		if ( 'SUCCESS' === $result['Status'] ) {
			$order->add_order_note( 'PAYUNi payment refund success. Status:' . $result['Status'] . ', Message:' . $decrypted['Message'] );
			return true;
		} else {
			$order->add_order_note( 'PAYUNi payment refund failed. Status:' . $result['Status'] . ', Message:' . $decrypted['Message'] );
			throw new Exception( 'PAYUNi refund failed. Status:' . esc_html( $result['Status'] ) );
		}
	}//end refund()

	/**
	 * Query payment status and result
	 */
	public static function query( $order_id ) {

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			Payuni_Payment::log( 'PAYUNi query faied. No such order_id:' . $order_id );
			return false;
		}

		$test_mode      = wc_string_to_bool( get_option( 'payuni_payment_testmode_enabled' ) );
		$mer_id         = $test_mode ? get_option( 'payuni_payment_merchant_id_test' ) : get_option( 'payuni_payment_merchant_id' );

		$payuni_order_no = $order->get_meta( '_payuni_order_no' );
		$encrypt_info    = array(
			'MerID'      => $mer_id,
			'MerTradeNo' => $payuni_order_no,
			'Timestamp'  => time(),
		);

		$encrypted_info = Payuni_Payment::encrypt( $encrypt_info );

		// Set the form data as an array
		$form_data = array(
			'MerID'       => $mer_id,
			'Version'     => '2.0',
			'EncryptInfo' => $encrypted_info,
			'HashInfo'    => Payuni_Payment::hash_info( $encrypted_info ),
		);

		$request_args = array(
			'httpversion' => '1.1',
			'timeout'     => '30',
			'body'        => $form_data,
		);

		$url = ( $test_mode ) ? 'https://sandbox-api.payuni.com.tw/api/trade/query' : 'https://api.payuni.com.tw/api/trade/query';
		Payuni_Payment::log( 'query url:' . $url );
		$response = wp_remote_post( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			Payuni_Payment::log( 'query error:' . $response->get_error_message() );
			return false;
		}

		$response_body = wp_remote_retrieve_body( $response );
		Payuni_Payment::log( 'query response body:' . wc_print_r( $response_body, true ) );

		$result    = json_decode( $response_body, true );
		$decrypted = Payuni_Payment::decrypt( $result['EncryptInfo'] );
		Payuni_Payment::log( 'query decrypted info:' . wc_print_r( $decrypted, true ) );

		$query_result                = array();
		$query_result['MerTradeNo']  = $decrypted['Result'][0]['MerTradeNo'];
		$query_result['TradeNo']     = $decrypted['Result'][0]['TradeNo'];
		$query_result['TradeStatus'] = $decrypted['Result'][0]['TradeStatus'];
		$query_result['PaymentDay']  = $decrypted['Result'][0]['PaymentDay'];
		$query_result['CreateDay']   = $decrypted['Result'][0]['CreateDay'];
		$query_result['PaymentType'] = $decrypted['Result'][0]['PaymentType'];

		// 信用卡.
		if ( '1' === $query_result['PaymentType'] ) {
			$query_result['CloseStatus'] = $decrypted['Result'][0]['CloseStatus'];
		}

		$woo_order_id = Payuni_Payment::parse_payuni_order_no_to_woo_order_id( $query_result['MerTradeNo'] );

		if ( 'SUCCESS' === $result['Status'] ) {

			$order = wc_get_order( $woo_order_id );
			/* translators:  %s is the decrypted result */
			$order->add_order_note( sprintf( __( 'PAYUNi query succeed. Query result: %s', 'wpbr-payuni-payment' ), wc_print_r( $decrypted, true ) ) );
			Payuni_Payment::log( 'PAYUNi query success. Status:' . $result['Status'] . ', Message:' . $decrypted['Message'] . ', Trade Status:' . $query_result['TradeStatus'] );
			return $query_result;
		} else {
			Payuni_Payment::log( 'PAYUNi query failed. Status:' . $result['Status'] . ', Message:' . $decrypted['Message'] );
			return false;
		}
	}

	private static function is_cancellable( $close_status ) {
		if ( '1' === $close_status || '3' === $close_status || '9' === $close_status ) {
			return true;
		} else {
			return false;
		}
	}

	private static function is_closeable( $close_status ) {
		if ( '2' === $close_status || '7' === $close_status ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Set payment gateway instance
	 *
	 * @param WC_Payment_Gateway $gateway The payment gateway instance.
	 */
	public function set_gateway( $gateway ) {
		$this->gateway = $gateway;
	}
}
