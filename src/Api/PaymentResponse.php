<?php
/**
 * PaymentResponse class file
 *
 * @package payuni
 */

namespace WPBrewer\Payuni\Payment\Api;

use WPBrewer\Payuni\Payment\PayuniPayment;
use WPBrewer\Payuni\Payment\Utils\OrderMeta;
use WPBrewer\Payuni\Payment\Utils\PayType;
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
		add_action( 'woocommerce_api_payuni_payment', array( self::get_instance(), 'payuni_receive_notify' ), 10 );
		add_action( 'woocommerce_api_payuni_return', array( self::get_instance(), 'payuni_receive_response_frontend' ), 20 );
		add_action( 'woocommerce_thankyou', array( self::get_instance(), 'maybe_query_on_thankyou' ), 5 );
		add_filter( 'woocommerce_thankyou_order_received_text', array( self::get_instance(), 'filter_thankyou_confirming_text' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( self::get_instance(), 'maybe_enqueue_order_status_poll' ) );
		add_action( 'wp_ajax_payuni_order_status', array( self::get_instance(), 'ajax_order_status' ) );
		add_action( 'wp_ajax_nopriv_payuni_order_status', array( self::get_instance(), 'ajax_order_status' ) );
	}

	/**
	 * Receive backend notification from PAYUNi NotifyURL.
	 * This is the only path that may change WooCommerce order status.
	 *
	 * @return void
	 */
	public static function payuni_receive_notify() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing

		$parsed = self::parse_posted_callback( 'Notify' );
		if ( is_wp_error( $parsed ) ) {
			PayuniPayment::log( 'PAYUNi Notify rejected: ' . $parsed->get_error_message() );
			return;
		}

		$order          = $parsed['order'];
		$decrypted_info = $parsed['decrypted'];
		$woo_order_id   = $order->get_id();

		PayuniPayment::log( 'PAYUNi NotifyURL response decrypted:' . wc_print_r( $decrypted_info, true ) );

		// 電子發票的通知.
		if ( array_key_exists( 'InvoiceNo', $decrypted_info ) ) {
			self::save_einvoice_data( $order, $decrypted_info );
			$order->add_order_note( 'PAYUNi E-Invoice Notify. InvoiceStatus:' . $decrypted_info['InvoiceStatus'] . ', InvoiceNo:' . $decrypted_info['InvoiceNo'] );
			return;
		}

		// Gateway error / decline (e.g. UPP02102) — no PaymentType/TradeStatus; do not treat as binding mismatch.
		if ( ! self::is_upp_success( $decrypted_info ) ) {
			self::handle_gateway_error_callback( $order, 'Notify', $decrypted_info );
			return;
		}

		if ( ! self::is_trade_amount_valid( $order, $decrypted_info['TradeAmt'] ?? null ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: PAYUNi TradeAmt 2: order total */
					__( 'PAYUNi Notify rejected: TradeAmt (%1$s) does not match order total (%2$s).', 'wpbr-payuni-payment' ),
					isset( $decrypted_info['TradeAmt'] ) ? $decrypted_info['TradeAmt'] : '',
					$order->get_total()
				)
			);
			PayuniPayment::log( sprintf( 'PAYUNi Notify: TradeAmt mismatch for order %s.', $woo_order_id ) );
			return;
		}

		if ( ! self::is_payment_method_bound( $order, $decrypted_info ) ) {
			self::reject_payment_method_mismatch( $order, 'Notify', $decrypted_info );
			return;
		}

		self::add_callback_order_note( $order, 'Notify', $decrypted_info );

		if ( $order->is_paid() ) {
			PayuniPayment::log( sprintf( 'PAYUNi Notify: Order %s already paid. Just add note and log.', $woo_order_id ) );
			return;
		}

		// Ensure MerTradeNo is stored so trade/query can locate the transaction.
		if ( ! empty( $decrypted_info['MerTradeNo'] ) ) {
			$order->update_meta_data( OrderMeta::PAYUNI_ORDER_NO, $decrypted_info['MerTradeNo'] );
			$order->save();
		}

		$query_result = PaymentRequest::query( $woo_order_id, false, $decrypted_info['MerTradeNo'] ?? '' );
		if ( false === $query_result ) {
			$order->add_order_note( __( 'PAYUNi Notify received but trade/query failed. Order status was not changed. Please check manually or wait for thankyou query.', 'wpbr-payuni-payment' ) );
			PayuniPayment::log( sprintf( 'PAYUNi Notify: query failed for order %s. Status not changed.', $woo_order_id ), 'warning' );
			return;
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: query TradeStatus */
				__( 'PAYUNi Notify query confirmed. TradeStatus: %s', 'wpbr-payuni-payment' ),
				$query_result['TradeStatus']
			)
		);

		if ( ! self::is_trade_amount_valid( $order, $query_result['TradeAmt'] ?? null ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: query TradeAmt 2: order total */
					__( 'PAYUNi Notify query rejected: TradeAmt (%1$s) does not match order total (%2$s).', 'wpbr-payuni-payment' ),
					isset( $query_result['TradeAmt'] ) ? $query_result['TradeAmt'] : '',
					$order->get_total()
				)
			);
			PayuniPayment::log( sprintf( 'PAYUNi Notify: query TradeAmt mismatch for order %s.', $woo_order_id ) );
			return;
		}

		if ( ! self::is_payment_method_bound( $order, $query_result ) ) {
			self::reject_payment_method_mismatch( $order, 'Notify query', $query_result );
			return;
		}

		// Prefer query fields for status / amount / trade no; keep notify details for payment-specific meta.
		$info_for_update                 = $decrypted_info;
		$info_for_update['TradeStatus']  = $query_result['TradeStatus'];
		$info_for_update['TradeNo']      = $query_result['TradeNo'];
		$info_for_update['MerTradeNo']   = $query_result['MerTradeNo'];
		$info_for_update['TradeAmt']     = $query_result['TradeAmt'] ?? ( $decrypted_info['TradeAmt'] ?? '' );
		$info_for_update['PaymentType']  = $query_result['PaymentType'] ?? ( $decrypted_info['PaymentType'] ?? '' );

		self::update_order_meta_and_order_status( $order, $info_for_update );

		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Receive return from PAYUNi ReturnURL.
	 * Validates and may store meta / notes, but never changes order status.
	 *
	 * @return void
	 */
	public static function payuni_receive_response_frontend() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing

		$parsed = self::parse_posted_callback( 'Return' );
		if ( is_wp_error( $parsed ) ) {
			PayuniPayment::log( 'PAYUNi Return rejected: ' . $parsed->get_error_message() );
			return;
		}

		$order          = $parsed['order'];
		$decrypted_info = $parsed['decrypted'];
		$woo_order_id   = $order->get_id();

		PayuniPayment::log( 'PAYUNi ReturnURL response decrypted:' . wc_print_r( $decrypted_info, true ) );

		// Gateway error / decline (e.g. UPP02102 CVS amount not allowed) — note + save MerTradeNo, never mark paid.
		if ( ! self::is_upp_success( $decrypted_info ) ) {
			self::handle_gateway_error_callback( $order, 'Return', $decrypted_info );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		if ( ! self::is_trade_amount_valid( $order, $decrypted_info['TradeAmt'] ?? null ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: PAYUNi TradeAmt 2: order total */
					__( 'PAYUNi Return rejected: TradeAmt (%1$s) does not match order total (%2$s).', 'wpbr-payuni-payment' ),
					isset( $decrypted_info['TradeAmt'] ) ? $decrypted_info['TradeAmt'] : '',
					$order->get_total()
				)
			);
			PayuniPayment::log( sprintf( 'PAYUNi Return: TradeAmt mismatch for order %s.', $woo_order_id ) );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		if ( ! self::is_payment_method_bound( $order, $decrypted_info ) ) {
			self::reject_payment_method_mismatch( $order, 'Return', $decrypted_info );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		self::add_callback_order_note( $order, 'Return', $decrypted_info );

		if ( $order->is_paid() ) {
			PayuniPayment::log( sprintf( 'PAYUNi Return: Order %s already paid. Just add note and log.', $woo_order_id ) );
		} else {
			// Store payment details (e.g. ATM virtual account) without changing WC order status.
			// Never persist TradeStatus=PAID from Return — that would block Notify from completing payment.
			self::save_payuni_order_data( $order, $decrypted_info, false );
			do_action( 'wpbr_payuni_on_order_status_updated', $order );
		}

		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;

		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Query PAYUNi on thankyou when Notify is late or earlier query failed.
	 *
	 * @param int $order_id The order id.
	 *
	 * @return void
	 */
	public function maybe_query_on_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		self::try_complete_from_query( $order, true );
	}

	/**
	 * Replace unpaid thankyou text with confirming + countdown for instant payments.
	 *
	 * @param string         $text  Default received text.
	 * @param \WC_Order|null $order Order object.
	 *
	 * @return string
	 */
	public function filter_thankyou_confirming_text( $text, $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return $text;
		}

		if ( ! PayType::is_instant_payment_gateway( $order->get_payment_method() ) ) {
			return $text;
		}

		if ( ! $order->needs_payment() || ! in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
			return $text;
		}

		// Do not show confirming countdown when PAYUNi already returned a hard error.
		$status_key = PayuniPayment::get_order_meta_key( $order, OrderMeta::STATUS );
		$upp_status = (string) $order->get_meta( $status_key );
		if ( '' !== $upp_status && 'SUCCESS' !== $upp_status ) {
			return $text;
		}

		$seconds = 60;

		return sprintf(
			'<span class="payuni-incomplete-payment-message payuni-confirming-payment" id="payuni-confirming-payment">%s</span>',
			sprintf(
				/* translators: %s: countdown seconds element */
				esc_html__( 'Confirming payment result… %s seconds remaining.', 'wpbr-payuni-payment' ),
				'<span class="payuni-countdown" data-payuni-countdown>' . (int) $seconds . '</span>'
			)
		);
	}

	/**
	 * Enqueue thankyou poll script while an instant-pay order still needs payment.
	 *
	 * Interval 3s, max 1 minute (20 attempts).
	 *
	 * @return void
	 */
	public function maybe_enqueue_order_status_poll() {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return;
		}

		$order_id = absint( get_query_var( 'order-received' ) );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order || ! PayType::is_instant_payment_gateway( $order->get_payment_method() ) ) {
			return;
		}

		if ( ! $order->needs_payment() ) {
			return;
		}

		$status_key = PayuniPayment::get_order_meta_key( $order, OrderMeta::STATUS );
		$upp_status = (string) $order->get_meta( $status_key );
		if ( '' !== $upp_status && 'SUCCESS' !== $upp_status ) {
			return;
		}

		wp_enqueue_script(
			'payuni-order-status-poll',
			WPBR_PAYUNI_PLUGIN_URL . 'assets/js/order-status-poll.js',
			array(),
			WPBR_PAYUNI_PAYMENT_VERSION,
			true
		);

		wp_localize_script(
			'payuni-order-status-poll',
			'payuniOrderStatusPoll',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'orderId'      => $order->get_id(),
				'orderKey'     => $order->get_order_key(),
				'interval'     => 3000,
				'maxAttempts'  => 20,
				'totalSeconds' => 60,
				'timeoutText'  => __( 'If you have already paid, please refresh this page later. If not, please complete the payment as soon as possible.', 'wpbr-payuni-payment' ),
			)
		);
	}

	/**
	 * AJAX handler for thankyou order status polling.
	 *
	 * @return void
	 */
	public function ajax_order_status() {
		$order_id  = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_key = isset( $_GET['order_key'] ) ? wc_clean( wp_unslash( $_GET['order_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$order = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order || ! hash_equals( (string) $order->get_order_key(), (string) $order_key ) ) {
			wp_send_json_error( null, 403 );
		}

		if ( ! isset( PayuniPayment::$allowed_payments[ $order->get_payment_method() ] ) ) {
			wp_send_json_error( null, 400 );
		}

		if ( $order->needs_payment() ) {
			// Silent progress notes to avoid spamming order notes every 3 seconds.
			self::try_complete_from_query( $order, false );
			$order = wc_get_order( $order_id );
		}

		wp_send_json_success(
			array(
				'needs_payment' => $order ? $order->needs_payment() : true,
			)
		);
	}

	/**
	 * Query PAYUNi and complete the order when TradeStatus is paid.
	 *
	 * @param \WC_Order $order            The order.
	 * @param bool      $add_progress_note Whether to note non-paid query results (thankyou first load).
	 *
	 * @return bool True when the order is paid after this call.
	 */
	public static function try_complete_from_query( $order, $add_progress_note = false ) {
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		$payment_method = $order->get_payment_method();
		if ( ! isset( PayuniPayment::$allowed_payments[ $payment_method ] ) ) {
			return false;
		}

		if ( $order->is_paid() ) {
			return true;
		}

		if ( ! in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
			return false;
		}

		$query_result = PaymentRequest::query( $order->get_id(), false );
		if ( false === $query_result ) {
			PayuniPayment::log( sprintf( 'PAYUNi thankyou/poll query failed for order %s.', $order->get_id() ) );
			return false;
		}

		$trade_status = isset( $query_result['TradeStatus'] ) ? (string) $query_result['TradeStatus'] : '';

		if ( $add_progress_note || TradeStatus::PAID === $trade_status ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: query TradeStatus */
					__( 'PAYUNi thankyou query. TradeStatus: %s', 'wpbr-payuni-payment' ),
					$trade_status
				)
			);
		}

		if ( ! self::is_trade_amount_valid( $order, $query_result['TradeAmt'] ?? null ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: query TradeAmt 2: order total */
					__( 'PAYUNi thankyou query rejected: TradeAmt (%1$s) does not match order total (%2$s).', 'wpbr-payuni-payment' ),
					isset( $query_result['TradeAmt'] ) ? $query_result['TradeAmt'] : '',
					$order->get_total()
				)
			);
			return false;
		}

		if ( ! self::is_payment_method_bound( $order, $query_result ) ) {
			self::reject_payment_method_mismatch( $order, 'thankyou query', $query_result );
			return false;
		}

		$info_for_update = array(
			'Status'      => 'SUCCESS',
			'Message'     => '',
			'MerTradeNo'  => $query_result['MerTradeNo'],
			'TradeNo'     => $query_result['TradeNo'],
			'TradeStatus' => $query_result['TradeStatus'],
			'TradeAmt'    => $query_result['TradeAmt'] ?? '',
			'PaymentType' => $query_result['PaymentType'] ?? '',
		);

		if ( ! empty( $query_result['OffPayNo'] ) ) {
			$info_for_update['PayNo'] = $query_result['OffPayNo'];
		}
		if ( ! empty( $query_result['OffChannel'] ) ) {
			if ( '3' === (string) ( $query_result['PaymentType'] ?? '' ) ) {
				$info_for_update['Store'] = $query_result['OffChannel'];
			} else {
				$info_for_update['BankType'] = $query_result['OffChannel'];
			}
		}
		if ( ! empty( $query_result['OffExpireTime'] ) ) {
			$info_for_update['ExpireDate'] = $query_result['OffExpireTime'];
		}
		if ( ! empty( $query_result['PaymentDay'] ) && '0000-00-00 00:00:00' !== $query_result['PaymentDay'] ) {
			$info_for_update['PayTime'] = $query_result['PaymentDay'];
		}

		self::update_order_meta_and_order_status( $order, $info_for_update );

		$order = wc_get_order( $order->get_id() );
		return $order && $order->is_paid();
	}

	/**
	 * Parse and validate MerID, HashInfo, EncryptInfo from POST.
	 *
	 * @param string $context Notify|Return for log messages.
	 *
	 * @return array|\WP_Error {
	 *     @type \WC_Order $order
	 *     @type array     $decrypted
	 * }
	 */
	private static function parse_posted_callback( $context ) {
		if ( empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return new \WP_Error( 'payuni_empty_post', sprintf( 'PAYUNi %s: empty POST.', $context ) );
		}

		$test_mode     = wc_string_to_bool( get_option( 'payuni_payment_testmode_enabled' ) );
		$mer_id        = $test_mode ? get_option( 'payuni_payment_merchant_id_test' ) : get_option( 'payuni_payment_merchant_id' );
		$posted_mer_id = isset( $_POST['MerID'] ) ? wc_clean( wp_unslash( $_POST['MerID'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( (string) $mer_id !== (string) $posted_mer_id ) {
			return new \WP_Error( 'payuni_mer_id', sprintf( 'PAYUNi %s: MerID not found or not match.', $context ) );
		}

		$encrypt_info = isset( $_POST['EncryptInfo'] ) ? wc_clean( wp_unslash( $_POST['EncryptInfo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$hash_info    = isset( $_POST['HashInfo'] ) ? wc_clean( wp_unslash( $_POST['HashInfo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! PayuniPayment::verify_hash_info( $encrypt_info, $hash_info ) ) {
			// Best-effort: if EncryptInfo was built with the real key, resolve the order and leave an admin note.
			self::maybe_note_hash_rejection( $context, $encrypt_info, $hash_info );
			return new \WP_Error( 'payuni_hash', sprintf( 'PAYUNi %s: HashInfo mismatch or missing.', $context ) );
		}

		$decrypted_info = PayuniPayment::decrypt( $encrypt_info );
		if ( empty( $decrypted_info ) || empty( $decrypted_info['MerTradeNo'] ) ) {
			return new \WP_Error( 'payuni_decrypt', sprintf( 'PAYUNi %s: decrypt failed or MerTradeNo missing.', $context ) );
		}

		if ( ! empty( $decrypted_info['MerID'] ) && (string) $mer_id !== (string) $decrypted_info['MerID'] ) {
			return new \WP_Error( 'payuni_mer_id_payload', sprintf( 'PAYUNi %s: decrypted MerID mismatch.', $context ) );
		}

		$woo_order_id = PayuniPayment::parse_payuni_order_no_to_woo_order_id( $decrypted_info['MerTradeNo'] );
		$order        = wc_get_order( $woo_order_id );
		if ( ! $order ) {
			return new \WP_Error( 'payuni_order', sprintf( 'PAYUNi %s: cannot find order by id %s.', $context, $woo_order_id ) );
		}

		return array(
			'order'     => $order,
			'decrypted' => $decrypted_info,
		);
	}

	/**
	 * Leave an order note when HashInfo verification fails but EncryptInfo is still decryptable.
	 *
	 * Completely random payloads cannot be mapped to an order; those are log-only.
	 *
	 * @param string $context      Notify|Return.
	 * @param string $encrypt_info Posted EncryptInfo.
	 * @param string $hash_info    Posted HashInfo.
	 *
	 * @return void
	 */
	private static function maybe_note_hash_rejection( $context, $encrypt_info, $hash_info ) {
		$decrypted_info = PayuniPayment::decrypt( $encrypt_info );
		if ( empty( $decrypted_info['MerTradeNo'] ) ) {
			return;
		}

		$woo_order_id = PayuniPayment::parse_payuni_order_no_to_woo_order_id( $decrypted_info['MerTradeNo'] );
		$order        = wc_get_order( $woo_order_id );
		if ( ! $order ) {
			return;
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: Notify or Return 2: MerTradeNo 3: claimed TradeStatus 4: HashInfo prefix */
				__( 'PAYUNi %1$s rejected: invalid HashInfo (possible forgery). MerTradeNo: %2$s, claimed TradeStatus: %3$s, HashInfo: %4$s…', 'wpbr-payuni-payment' ),
				$context,
				$decrypted_info['MerTradeNo'],
				isset( $decrypted_info['TradeStatus'] ) ? $decrypted_info['TradeStatus'] : '',
				substr( (string) $hash_info, 0, 12 )
			)
		);
	}

	/**
	 * Compare PAYUNi TradeAmt with WooCommerce order total.
	 *
	 * @param \WC_Order  $order     The order.
	 * @param mixed|null $trade_amt Trade amount from PAYUNi.
	 *
	 * @return bool
	 */
	private static function is_trade_amount_valid( $order, $trade_amt ) {
		if ( null === $trade_amt || '' === $trade_amt ) {
			return false;
		}
		return (int) round( (float) $order->get_total() ) === (int) round( (float) $trade_amt );
	}

	/**
	 * Whether PAYUNi Status indicates a successful trade payload.
	 *
	 * @param array $data Decrypted payload.
	 *
	 * @return bool
	 */
	private static function is_upp_success( $data ) {
		return isset( $data['Status'] ) && 'SUCCESS' === (string) $data['Status'];
	}

	/**
	 * Handle non-SUCCESS Return/Notify: record Status/Message and MerTradeNo, never complete payment.
	 *
	 * @param \WC_Order $order          The order.
	 * @param string    $context        Notify|Return.
	 * @param array     $decrypted_info Decrypted PAYUNi payload.
	 *
	 * @return void
	 */
	private static function handle_gateway_error_callback( $order, $context, $decrypted_info ) {
		self::add_callback_order_note( $order, $context, $decrypted_info );

		if ( ! empty( $decrypted_info['MerTradeNo'] ) ) {
			$order->update_meta_data( OrderMeta::PAYUNI_ORDER_NO, $decrypted_info['MerTradeNo'] );
		}
		$order->update_meta_data( OrderMeta::STATUS, $decrypted_info['Status'] ?? '' );
		$order->update_meta_data( OrderMeta::MESSAGE, $decrypted_info['Message'] ?? '' );
		if ( isset( $decrypted_info['TradeAmt'] ) && '' !== $decrypted_info['TradeAmt'] ) {
			$order->update_meta_data( OrderMeta::TRADE_AMOUNT, $decrypted_info['TradeAmt'] );
		}
		$order->update_meta_data( OrderMeta::PLUGIN_VERSION, WPBR_PAYUNI_PAYMENT_VERSION );
		$order->save();

		PayuniPayment::log(
			sprintf(
				'PAYUNi %s: non-SUCCESS Status=%s Message=%s order=%s',
				$context,
				$decrypted_info['Status'] ?? '',
				$decrypted_info['Message'] ?? '',
				$order->get_id()
			)
		);
	}

	/**
	 * Whether PaymentType (/ AuthType) matches the order payment gateway.
	 *
	 * @param \WC_Order $order The order.
	 * @param array     $data  Decrypted notify/return or query result.
	 *
	 * @return bool
	 */
	private static function is_payment_method_bound( $order, $data ) {
		$pay_type  = $data['PaymentType'] ?? null;
		$auth_type = $data['AuthType'] ?? null;
		return PayType::matches_order_payment_method( $order, $pay_type, $auth_type );
	}

	/**
	 * Reject callback when PaymentType does not match order gateway.
	 *
	 * @param \WC_Order $order   The order.
	 * @param string    $context Notify|Return|thankyou query|Notify query.
	 * @param array     $data    Payload with PaymentType.
	 *
	 * @return void
	 */
	private static function reject_payment_method_mismatch( $order, $context, $data ) {
		$pay_type = $data['PaymentType'] ?? '';
		$order->add_order_note(
			sprintf(
				/* translators: 1: context 2: PAYUNi PaymentType 3: WooCommerce payment method */
				__( 'PAYUNi %1$s rejected: PaymentType (%2$s) does not match order payment method (%3$s).', 'wpbr-payuni-payment' ),
				$context,
				$pay_type,
				$order->get_payment_method()
			)
		);
		PayuniPayment::log(
			sprintf(
				'PAYUNi %s: PaymentType %s mismatch for order %s (gateway %s).',
				$context,
				$pay_type,
				$order->get_id(),
				$order->get_payment_method()
			)
		);
	}

	/**
	 * Add a standardized callback order note including TradeStatus.
	 *
	 * @param \WC_Order $order          The order.
	 * @param string    $context        Notify|Return.
	 * @param array     $decrypted_info Decrypted PAYUNi payload.
	 *
	 * @return void
	 */
	private static function add_callback_order_note( $order, $context, $decrypted_info ) {
		$status       = $decrypted_info['Status'] ?? '';
		$message      = $decrypted_info['Message'] ?? '';
		$mer_trade_no = $decrypted_info['MerTradeNo'] ?? '';
		$trade_no     = $decrypted_info['TradeNo'] ?? '';
		$pay_type     = $decrypted_info['PaymentType'] ?? '';
		$trade_status = $decrypted_info['TradeStatus'] ?? '';

		if ( 'Notify' === $context ) {
			$text_log = __( 'PAYUNi Notify', 'wpbr-payuni-payment' );
		} else {
			$text_log = __( 'PAYUNi Return', 'wpbr-payuni-payment' );
		}

		$text_code         = __( 'Status code:', 'wpbr-payuni-payment' );
		$text_message      = __( 'Transaction message:', 'wpbr-payuni-payment' );
		$text_mertradeno   = __( 'MerTradeNo:', 'wpbr-payuni-payment' );
		$text_number       = __( 'UNi number:', 'wpbr-payuni-payment' );
		$text_paytype      = __( 'Payment type:', 'wpbr-payuni-payment' );
		$text_trade_status = __( 'TradeStatus:', 'wpbr-payuni-payment' );

		$order->add_order_note(
			"<strong>{$text_log}</strong><br>{$text_code} {$status}<br>{$text_message} {$message}<br>{$text_mertradeno} {$mer_trade_no}<br>{$text_number} {$trade_no}<br>{$text_trade_status} {$trade_status}<br>{$text_paytype} " . PayType::get_name( $pay_type )
		);
	}

	/**
	 * Save PAYUNi order data to WooCommerce order meta
	 *
	 * @param \WC_Order $order                 The order object.
	 * @param array     $decrypted_info        The decrypted info from PAYUNi.
	 * @param bool      $persist_paid_status   Whether TradeStatus=PAID may be written to meta.
	 */
	private static function save_payuni_order_data( $order, $decrypted_info, $persist_paid_status = true ) {

		$pay_type     = $decrypted_info['PaymentType'] ?? '';
		$trade_status = isset( $decrypted_info['TradeStatus'] ) ? (string) $decrypted_info['TradeStatus'] : '';

		$order->update_meta_data( OrderMeta::STATUS, $decrypted_info['Status'] ?? '' );
		$order->update_meta_data( OrderMeta::MESSAGE, $decrypted_info['Message'] ?? '' );
		$order->update_meta_data( OrderMeta::PAYUNI_ORDER_NO, $decrypted_info['MerTradeNo'] ?? '' );
		$order->update_meta_data( OrderMeta::UNI_NO, $decrypted_info['TradeNo'] ?? '' );
		$order->update_meta_data( OrderMeta::TRADE_AMOUNT, $decrypted_info['TradeAmt'] ?? '' );
		$order->update_meta_data( OrderMeta::PAY_TYPE, $pay_type );

		// Return must not persist PAID; Notify/thankyou query are the source of truth for paid status.
		if ( $persist_paid_status || TradeStatus::PAID !== $trade_status ) {
			$order->update_meta_data( OrderMeta::TRADE_STATUS, $trade_status );
		}

		self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_REST_CODE, 'ResCode' );
		self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_REST_CODE_MSG, 'ResCodeMsg' );

		if ( '1' === $pay_type ) {
			// 信用卡.
			self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_AUTH_TYPE, 'AuthType' );
			self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_CARD_4NO, 'Card4No' );
			self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_AUTH_DAY, 'AuthDay' );
			self::update_order_meta( $order, $decrypted_info, OrderMeta::CREDIT_AUTH_TIME, 'AuthTime' );

			if ( isset( $decrypted_info['AuthType'] ) && '2' === $decrypted_info['AuthType'] ) {
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
			self::update_order_meta( $order, $decrypted_info, OrderMeta::CVS_PAY_TIME, 'PayTime' );

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
	 * Update order meta and WooCommerce order status from trusted PAYUNi data.
	 *
	 * @param \WC_Order $order          The order object.
	 * @param array     $decrypted_info Decrypted / query-merged info.
	 *
	 * @return void
	 */
	private static function update_order_meta_and_order_status( $order, $decrypted_info ) {

		$trade_status = isset( $decrypted_info['TradeStatus'] ) ? (string) $decrypted_info['TradeStatus'] : '';

		self::save_payuni_order_data( $order, $decrypted_info );

		if ( TradeStatus::PAID === $trade_status ) {
			$order->payment_complete( $decrypted_info['TradeNo'] ?? '' );
		} elseif ( TradeStatus::EXPIRED === $trade_status ) {
			// 交易逾期失效 (AFTEE, ATM, CVS).
			$order->update_status( 'failed' );
		} elseif ( TradeStatus::CANCEL === $trade_status || TradeStatus::FAIL === $trade_status ) {
			// 付款取消或付款失敗.
			$order->update_status( 'failed' );
		}

		do_action( 'wpbr_payuni_on_order_status_updated', $order );
	}

	/**
	 * Save e-invoice notify data.
	 *
	 * @param \WC_Order $order          The order.
	 * @param array     $decrypted_info Decrypted payload.
	 *
	 * @return void
	 */
	private static function save_einvoice_data( $order, $decrypted_info ) {
		$order->update_meta_data( OrderMeta::EINVOICE_NO, $decrypted_info['InvoiceNo'] );
		$order->update_meta_data( OrderMeta::EINVOICE_AMT, $decrypted_info['TradeAmt'] );
		$order->update_meta_data( OrderMeta::EINVOICE_TIME, $decrypted_info['InvoiceTime'] );
		$order->update_meta_data( OrderMeta::EINVOICE_TYPE, $decrypted_info['InvoiceNotifyType'] );
		$order->update_meta_data( OrderMeta::EINVOICE_INFO, $decrypted_info['InvoiceInfo'] );
		$order->update_meta_data( OrderMeta::EINVOICE_STATUS, $decrypted_info['InvoiceStatus'] );
		$order->save();
	}

	/**
	 * A wrapper function to save received post data from PAYUNi
	 *
	 * @param \WC_Order $order    The order object.
	 * @param array     $data     The post data received from PAYUNi.
	 * @param string    $meta_key The meta key to save.
	 * @param string    $data_key The data key to get.
	 */
	private static function update_order_meta( $order, $data, $meta_key, $data_key ) {
		if ( ! empty( $data[ $data_key ] ) ) {

			$value = ( 'Store' === $data_key && 'SEVEN' === $data[ $data_key ] ) ? '7-11' : $data[ $data_key ];
			$order->update_meta_data( $meta_key, $value );
		}
	}
}
