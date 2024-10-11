<?php

namespace WPBrewer\Payuni\Payment;

use WPBrewer\Payuni\Payment\Admin\OrderList;
use WPBrewer\Payuni\Payment\Admin\OrderMetaBoxes;
use WPBrewer\Payuni\Payment\Api\PaymentRequest;
use WPBrewer\Payuni\Payment\Api\PaymentResponse;
use WPBrewer\Payuni\Payment\Gateways\Aftee;
use WPBrewer\Payuni\Payment\Gateways\ApplePay;
use WPBrewer\Payuni\Payment\Gateways\Atm;
use WPBrewer\Payuni\Payment\Gateways\Credit;
use WPBrewer\Payuni\Payment\Gateways\CreditInstallment3;
use WPBrewer\Payuni\Payment\Gateways\CreditInstallment6;
use WPBrewer\Payuni\Payment\Gateways\CreditInstallment9;
use WPBrewer\Payuni\Payment\Gateways\CreditInstallment12;
use WPBrewer\Payuni\Payment\Gateways\CreditInstallment18;
use WPBrewer\Payuni\Payment\Gateways\CreditInstallment24;
use WPBrewer\Payuni\Payment\Gateways\CreditInstallment30;
use WPBrewer\Payuni\Payment\Gateways\Cvs;
use WPBrewer\Payuni\Payment\Gateways\GooglePay;
use WPBrewer\Payuni\Payment\Gateways\LinePay;
use WPBrewer\Payuni\Payment\Gateways\SamsungPay;
use WPBrewer\Payuni\Payment\Settings\SettingsTab;
use WPBrewer\Payuni\Payment\Utils\OrderMeta;

/**
 * PayuniPayment class file
 *
 * @package payuni
 */

defined( 'ABSPATH' ) || exit;

/**
 * PayuniPayment main class for handling all checkout related process.
 */
class PayuniPayment {


	/**
	 * Class instance
	 *
	 * @var PayuniPayment
	 */
	private static $instance;

	/**
	 * Whether or not logging is enabled.
	 *
	 * @var boolean
	 */
	public static $log_enabled = false;

	/**
	 * WC_Logger instance.
	 *
	 * @var WC_Logger Logger instance
	 * */
	public static $log = false;

	/**
	 * Suppoeted payment gateways
	 *
	 * @var array
	 * */
	public static $allowed_payments;

	/**
	 * Suppoeted installment payment gateways
	 *
	 * @var array
	 * */
	public static $available_installments;

	/**
	 * Whether or not e-invoice is enabled
	 *
	 * @var boolean
	 * */
	public static $einvoice_enabled;

	/**
	 * Order meta for all payment gateways
	 *
	 * @var array
	 * */
	public static $order_metas;

	/**
	 * Notify url
	 *
	 * @var string
	 * */
	public static $notify_url;

	/**
	 * Constructor
	 */
	public function __construct() {
		// do nothing.
	}

	/**
	 * Initialize class and add hooks
	 *
	 * @return void
	 */
	public static function init() {

		self::get_instance();

		self::$log_enabled      = 'yes' === get_option( 'payuni_payment_debug_log_enabled', 'no' );
		self::$einvoice_enabled = 'yes' === get_option( 'payuni_payment_einvoice_enabled', 'no' );

		load_plugin_textdomain( 'wpbr-payuni-payment', false, dirname( WPBR_PAYUNI_BASENAME ) . '/languages/' );

		OrderList::init();
		OrderMetaBoxes::init();
		PaymentResponse::init();

		self::$allowed_payments = array(
			Credit::GATEWAY_ID     => '\WPBrewer\Payuni\Payment\Gateways\Credit',
			Cvs::GATEWAY_ID        => '\WPBrewer\Payuni\Payment\Gateways\Cvs',
			Atm::GATEWAY_ID        => '\WPBrewer\Payuni\Payment\Gateways\Atm',
			Aftee::GATEWAY_ID      => '\WPBrewer\Payuni\Payment\Gateways\Aftee',
			ApplePay::GATEWAY_ID   => '\WPBrewer\Payuni\Payment\Gateways\ApplePay',
			GooglePay::GATEWAY_ID  => '\WPBrewer\Payuni\Payment\Gateways\GooglePay',
			SamsungPay::GATEWAY_ID => '\WPBrewer\Payuni\Payment\Gateways\SamsungPay',
			LinePay::GATEWAY_ID    => '\WPBrewer\Payuni\Payment\Gateways\LinePay',
		);

		$number_of_payments = get_option( 'payuni_payment_installment_number_of_payments', array() );

		self::$available_installments = array(
			CreditInstallment3::GATEWAY_ID  => '\WPBrewer\Payuni\Payment\Gateways\CreditInstallment3',
			CreditInstallment6::GATEWAY_ID  => '\WPBrewer\Payuni\Payment\Gateways\CreditInstallment6',
			CreditInstallment9::GATEWAY_ID  => '\WPBrewer\Payuni\Payment\Gateways\CreditInstallment9',
			CreditInstallment12::GATEWAY_ID => '\WPBrewer\Payuni\Payment\Gateways\CreditInstallment12',
			CreditInstallment18::GATEWAY_ID => '\WPBrewer\Payuni\Payment\Gateways\CreditInstallment18',
			CreditInstallment24::GATEWAY_ID => '\WPBrewer\Payuni\Payment\Gateways\CreditInstallment24',
			CreditInstallment30::GATEWAY_ID => '\WPBrewer\Payuni\Payment\Gateways\CreditInstallment30',
		);

		foreach ( self::$available_installments as $key => $installment ) {
			if ( in_array( $key, $number_of_payments, true ) ) {
				self::$allowed_payments[ $key ] = $installment;
			}
		}

		self::$order_metas = array(
			OrderMeta::UNI_NO       => __( 'Trade No', 'wpbr-payuni-payment' ),
			OrderMeta::TRADE_AMOUNT => __( 'Trade Amount', 'wpbr-payuni-payment' ),
			OrderMeta::TRADE_STATUS => __( 'Trade Status', 'wpbr-payuni-payment' ),
			OrderMeta::MESSAGE      => __( 'Message', 'wpbr-payuni-payment' ),
		);

		add_filter( 'woocommerce_get_settings_pages', array( self::get_instance(), 'payuni_add_settings' ) );

		add_filter( 'woocommerce_payment_gateways', array( self::get_instance(), 'add_payuni_payment_gateway' ) );

		add_filter( 'plugin_action_links_' . WPBR_PAYUNI_BASENAME, array( self::get_instance(), 'payuni_add_action_links' ) );

		add_action( 'wp_enqueue_scripts', array( self::get_instance(), 'payuni_checkout_enqueue_scripts' ), 9 );
		add_action( 'admin_enqueue_scripts', array( self::get_instance(), 'payuni_admin_scripts' ), 9 );

		add_action( 'wp_ajax_payuni_query', array( self::get_instance(), 'payuni_ajax_query_payment' ) );
	}

	/**
	 * Add payment gateways
	 *
	 * @param  array $methods PAYUNi payment gateways.
	 * @return array
	 */
	public function add_payuni_payment_gateway( $methods ) {
		$merged_methods = array_merge( $methods, self::$allowed_payments );
		return $merged_methods;
	}

	/**
	 * Plugin action links
	 *
	 * @param  array $links The action links array.
	 * @return array
	 */
	public function payuni_add_action_links( $links ) {
		$setting_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=payuni&section=payment' ) . '">' . __( 'General Settings', 'wpbr-payuni-payment' ) . '</a>',
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Payment Settings', 'wpbr-payuni-payment' ) . '</a>',
		);
		return array_merge( $links, $setting_links );
	}

	/**
	 * Enqueue JS/CSS on checkout page
	 *
	 * @return void
	 */
	public static function payuni_checkout_enqueue_scripts() {

		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_style( 'payuni-payment', WPBR_PAYUNI_PLUGIN_URL . 'assets/css/styles-public.css', array(), WPBR_PAYUNI_PAYMENT_VERSION, 'all' );

		wp_enqueue_script( 'payuni-public', WPBR_PAYUNI_PLUGIN_URL . 'assets/js/scripts.js', array(), WPBR_PAYUNI_PAYMENT_VERSION, true );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @return void
	 */
	public function payuni_admin_scripts() {

		//enqueue admin css
		wp_enqueue_style( 'payuni-admin', WPBR_PAYUNI_PLUGIN_URL . 'assets/css/styles-admin.css', array(), WPBR_PAYUNI_PAYMENT_VERSION, 'all' );

		wp_enqueue_script( 'payuni-admin', WPBR_PAYUNI_PLUGIN_URL . 'assets/js/scripts-admin.js', array(), WPBR_PAYUNI_PAYMENT_VERSION, true );
		wp_localize_script(
			'payuni-admin',
			'payuni_object',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'query_nonce' => wp_create_nonce( 'payuni-query' ),
			)
		);
	}

	/**
	 * Handle of confirm payment request via wp-admin.
	 *
	 * @return void
	 */
	public function payuni_ajax_query_payment() {

		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( wc_clean( wp_unslash( $_POST['security'] ) ), 'payuni-query' ) ) {
			$return = array(
				'success' => false,
				'message' => __( 'Unsecure AJAX call', 'wpbr-payuni-payment' ),
			);
			wp_send_json( $return );
		}

		$order_id = ( isset( $_POST['order_id'] ) ) ? wc_clean( wp_unslash( $_POST['order_id'] ) ) : '';
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			$return = array(
				'success' => false,
				'message' => __( 'No such order id', 'wpbr-payuni-payment' ),
			);
			wp_send_json( $return );
		}

		$reserved_transaction_id = $order->get_transaction_id();
		$request                 = new PaymentRequest();
		try {

			if ( $request->query( $order->get_id() ) !== false ) {
				$return = array(
					'success' => true,
					'message' => __( 'PAYUNi Query Successfully', 'wpbr-payuni-payment' ),
				);
				wp_send_json( $return );
			}
		} catch ( \Exception $e ) {

			$order->add_order_note( __( 'PAYUNi Query Failed!', 'wpbr-payuni-payment' ) . $e->getMessage() );
			$return = array(
				'success' => false,
				'message' => $e->getMessage(),
			);
			wp_send_json( $return );

		}
	}

	/**
	 * Add settings tab
	 *
	 * @param array $settings The settings array.
	 *
	 * @return array
	 */
	public function payuni_add_settings( $settings ) {
		$settings[] = new SettingsTab();
		return $settings;
	}

	/**
	 * Encrypt the data
	 *
	 * @param array $encrypt_info The info to be encrypted.
	 */
	public static function encrypt( $encrypt_info ) {

		$test_mode = wc_string_to_bool( get_option( 'payuni_payment_testmode_enabled' ) );
		$hashkey   = $test_mode ? get_option( 'payuni_payment_hashkey_test' ) : get_option( 'payuni_payment_hashkey' );
		$hashiv    = $test_mode ? get_option( 'payuni_payment_hashiv_test' ) : get_option( 'payuni_payment_hashiv' );

		$tag       = '';
		$encrypted = openssl_encrypt( http_build_query( $encrypt_info ), 'aes-256-gcm', trim( $hashkey ), 0, trim( $hashiv ), $tag );
		return trim( bin2hex( $encrypted . ':::' . base64_encode( $tag ) ) );
	}

	/**
	 * Decrypt the data
	 *
	 * @param string $encrypt_str The string to be decrypted.
	 */
	public static function decrypt( string $encrypt_str = '' ) {

		$test_mode = wc_string_to_bool( get_option( 'payuni_payment_testmode_enabled' ) );
		$hashkey   = $test_mode ? get_option( 'payuni_payment_hashkey_test' ) : get_option( 'payuni_payment_hashkey' );
		$hashiv    = $test_mode ? get_option( 'payuni_payment_hashiv_test' ) : get_option( 'payuni_payment_hashiv' );

		list($encrypt_data, $tag) = explode( ':::', hex2bin( $encrypt_str ), 2 );
		$encrypt_info             = openssl_decrypt( $encrypt_data, 'aes-256-gcm', trim( $hashkey ), 0, trim( $hashiv ), base64_decode( $tag ) );
		parse_str( $encrypt_info, $encrypt_arr );
		return $encrypt_arr;
	}

	/**
	 * Hash the data
	 *
	 * @param string $encrypt_str The string to be hashed.
	 */
	public static function hash_info( string $encrypt_str = '' ) {
		$test_mode = wc_string_to_bool( get_option( 'payuni_payment_testmode_enabled' ) );
		$hashkey   = $test_mode ? get_option( 'payuni_payment_hashkey_test' ) : get_option( 'payuni_payment_hashkey' );
		$hashiv    = $test_mode ? get_option( 'payuni_payment_hashiv_test' ) : get_option( 'payuni_payment_hashiv' );

		return strtoupper( hash( 'sha256', $hashkey . $encrypt_str . $hashiv ) );
	}

	/**
	 * Build payuni order no
	 *
	 * @param int $order_id The order id.
	 */
	public static function build_payuni_order_no( $order_id ) {

		$order = wc_get_order( $order_id );

		$payuni_order_no = $order_id;

		$order_serial_no = $order->get_meta( '_payuni_order_serial_no' );

		if ( $order_serial_no && $order_serial_no < 999 ) {
			$order_serial_no += 1;
			$payuni_order_no  = $payuni_order_no . '-' . $order_serial_no;
		} else {
			$order_serial_no = 1;
			$payuni_order_no = $payuni_order_no . '-' . $order_serial_no;
		}

		$order->update_meta_data( '_payuni_order_serial_no', $order_serial_no );
		$order->save();

		return $payuni_order_no;
	}

	/**
	 * Parse payuni order no to woo order id
	 *
	 * @param string $payuni_order_no The payuni order number.
	 *
	 * @return string
	 */
	public static function parse_payuni_order_no_to_woo_order_id( $payuni_order_no ) {

		if ( strpos( $payuni_order_no, '-' ) !== false ) {
			$real_woo_order_id = explode( '-', $payuni_order_no )[0];
		} else {
			$real_woo_order_id = substr( $payuni_order_no, 0, -3 );
		}

		return $real_woo_order_id;
	}

	/**
	 * Get refund api url by payment method.
	 *
	 * @param string $payment_method The payment method id.
	 */
	public static function get_refund_api_url( $payment_method ) {

		$refund_api_url = 'https://sandbox-api.payuni.com.tw/api';

		if ( 'payuni-credit' === $payment_method || 'payuni-applepay' === $payment_method ) {
			$refund_api_url = $refund_api_url . '/trade/cancel';
		} elseif ( 'payuni-aftee' === $payment_method ) {
			$refund_api_url = $refund_api_url . '/trade/common/refund/aftee';
		}
		return $refund_api_url;
	}

	public static function get_allowed_payments( $order = null ) {
		if ( ! $order ) {
			return self::$allowed_payments;
		}

		$plugin_version = $order->get_meta( OrderMeta::PLUGIN_VERSION );
		if ( \version_compare( $plugin_version, '1.5.0' ) >= 0 ) {
			return self::$allowed_payments;
		} else {
			// for backward-compatibility.
			$old_allowed_payments = array();
			foreach ( self::$allowed_payments as $key => $value ) {
				$old_payment_id                          = str_replace( 'upp-', '', $key );
				$old_allowed_payments[ $old_payment_id ] = $value;
			}
			return $old_allowed_payments;
		}
	}

	/**
	 * Get allowed installments
	 *
	 * @param \WC_Order $order The order object.
	 *
	 * @return array
	 */
	public static function get_allowed_install_payments( $order = null ) {
		if ( ! $order ) {
			return self::$available_installments;
		}

		$plugin_version = $order->get_meta( OrderMeta::PLUGIN_VERSION );
		if ( \version_compare( $plugin_version, '1.5.0' ) >= 0 ) {
			return self::$available_installments;
		} else {
			// for backward-compatibility.
			$old_available_payments = array();
			foreach ( self::$available_installments as $key => $value ) {
				$old_payment_id                            = str_replace( 'upp-', '', $key );
				$old_available_payments[ $old_payment_id ] = $value;
			}
			return $old_available_payments;
		}
	}

	/**
	 * Get order meta key
	 *
	 * @param \WC_Order $order The order object.
	 * @param string    $key   The order meta key.
	 *
	 * @return string
	 */
	public static function get_order_meta_key( $order, $key ) {
		$plugin_version = $order->get_meta( OrderMeta::PLUGIN_VERSION );
		if ( \version_compare( $plugin_version, '1.5.0' ) >= 0 ) {
			return $key;
		} else {
			// for backward-compatibility.
			return str_replace( '_wpbr_payuni_upp_', '_payuni_', $key );
		}
	}

	/**
	 * Log method.
	 *
	 * @param  string $message The message to be logged.
	 * @param  string $level   The log level. Optional. Default 'info'. Possible values: emergency|alert|critical|error|warning|notice|info|debug.
	 * @return void
	 */
	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = new \WC_Logger();
			}
			self::$log->log( $level, $message, array( 'source' => 'wpbr-payuni-payment' ) );
		}
	}

	/**
	 * Returns the single instance of the Payuni_Payment object
	 *
	 * @return PayuniPayment
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
