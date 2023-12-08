<?php
/**
 * Payuni_Payment class file
 *
 * @package payuni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Payuni_Payment main class for handling all checkout related process.
 */
class Payuni_Payment {

	/**
	 * Class instance
	 *
	 * @var Payuni_Payment
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

	public static $order_metas;

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

		self::$log_enabled = 'yes' === get_option( 'payuni_payment_debug_log_enabled', 'no' );

		load_plugin_textdomain( 'wpbr-payuni-payment', false, dirname( PAYUNI_BASENAME ) . '/languages/' );

		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/abstract-payuni-payment.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-request.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-response.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-credit.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-cvs.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-atm.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/trait-payuni-payment-installment.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-installment-3.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-installment-6.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-installment-9.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-installment-12.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-installment-18.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-installment-24.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-installment-30.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-applepay.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-googlepay.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-aftee.php';
		require_once PAYUNI_PLUGIN_DIR . 'includes/gateways/class-payuni-payment-linepay.php';

		require_once PAYUNI_PLUGIN_DIR . 'includes/admin/meta-boxes/class-payuni-payment-order-meta-boxes.php';

		Payuni_Payment_Order_Meta_Boxes::init();
		Payuni_Payment_Response::init();

		self::$allowed_payments = array(
			'payuni-credit'    => 'Payuni_Payment_Credit',
			'payuni-cvs'       => 'Payuni_Payment_CVS',
			'payuni-atm'       => 'Payuni_Payment_ATM',
			'payuni-aftee'     => 'Payuni_Payment_Aftee',
			'payuni-applepay'  => 'Payuni_Payment_ApplePay',
			'payuni-googlepay' => 'Payuni_Payment_GooglePay',
			'payuni-linepay'   => 'Payuni_Payment_LINEPay',
		);

		$number_of_payments = get_option( 'payuni_payment_installment_number_of_payments', array() );

		self::$available_installments = array(
			'payuni-installment-3'  => 'Payuni_Payment_Installment_3',
			'payuni-installment-6'  => 'Payuni_Payment_Installment_6',
			'payuni-installment-9'  => 'Payuni_Payment_Installment_9',
			'payuni-installment-12' => 'Payuni_Payment_Installment_12',
			'payuni-installment-18' => 'Payuni_Payment_Installment_18',
			'payuni-installment-24' => 'Payuni_Payment_Installment_24',
			'payuni-installment-30' => 'Payuni_Payment_Installment_30',
		);

		foreach ( self::$available_installments as $key => $installment ) {
			if ( in_array( $key, $number_of_payments ) ) {
				self::$allowed_payments[ $key ] = $installment;
			}
		}

		self::$order_metas = array(
			'_payuni_trade_no'     => __( 'Trade No', 'wpbr-payuni-payment' ),
			'_payuni_trade_amt'    => __( 'Trade Amount', 'wpbr-payuni-payment' ),
			'_payuni_trade_status' => __( 'Trade Status', 'wpbr-payuni-payment' ),
			'_payuni_message'      => __( 'Message', 'wpbr-payuni-payment' ),
		);



		add_filter( 'woocommerce_get_settings_pages', array( self::get_instance(), 'payuni_add_settings' ), 15 );

		add_filter( 'woocommerce_payment_gateways', array( self::get_instance(), 'add_payuni_payment_gateway' ) );

		add_filter( 'plugin_action_links_' . PAYUNI_BASENAME, array( self::get_instance(), 'payuni_add_action_links' ) );

		add_action( 'wp_enqueue_scripts', array( self::get_instance(), 'payuni_checkout_enqueue_scripts' ), 9 );
		add_action( 'admin_enqueue_scripts', array( self::get_instance(), 'payuni_admin_scripts' ), 9 );

		add_action( 'wp_ajax_payuni_query', array( self::get_instance(), 'payuni_ajax_query_payment' ) );

		if ( version_compare( WC_VERSION, '8.2.0', '>=' ) &&  version_compare( WC_VERSION, '8.3.0', '<' ) ) {
			add_filter( 'woocommerce_locate_template', array( self::get_instance(), 'wpbr_payuni_wc_template' ), 10, 3 );
		}

	}

	/**
	 * Add payment gateways
	 *
	 * @param array $methods PAYUNi payment gateways.
	 * @return array
	 */
	public function add_payuni_payment_gateway( $methods ) {
		$merged_methods = array_merge( $methods, self::$allowed_payments );
		return $merged_methods;
	}

	/**
	 * Plugin action links
	 *
	 * @param array $links The action links array.
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

		wp_enqueue_style( 'payuni-payment', PAYUNI_PLUGIN_URL . 'assets/css/payuni-payment-public.css', array(), '1.0.0', 'all' );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @return void
	 */
	public function payuni_admin_scripts() {

		wp_enqueue_script( 'payuni-admin', PAYUNI_PLUGIN_URL . 'assets/js/payuni-payment-admin.js', array(), '1.0', true );
		// wp_enqueue_style( 'payuni-admin', PAYUNI_PLUGIN_URL . 'assets/css/payuni-payment-admin.css', array(), '1.0' );

		wp_localize_script(
			'payuni-admin',
			'payuni_object',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
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

		$posted = wp_unslash( $_POST );

		if ( ! array_key_exists( 'security', $posted ) || ! wp_verify_nonce( $posted['security'], 'payuni-query' ) ) {
			$return = array(
				'success' => false,
				'message' => __( 'Unsecure AJAX call', 'wpbr-payuni-payment' ),
			);
			wp_send_json( $return );
		}

		$order_id = $posted['order_id'];
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			$return = array(
				'success' => false,
				'message' => __( 'No such order id', 'wpbr-payuni-payment' ),
			);
			wp_send_json( $return );
		}

		$reserved_transaction_id = $order->get_transaction_id();
		$request                 = new Payuni_Payment_Request();
		try {

			if ( $request->query( $order->get_id() ) ) {
				$return = array(
					'success' => true,
					'message' => __( 'PAYUNi Query Successfully', 'wpbr-payuni-payment' ),
				);
				wp_send_json( $return );
			}
		} catch ( Exception $e ) {

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
	 * @return WC_Settings_Tab_Payuni
	 */
	public function payuni_add_settings() {
		require_once PAYUNI_PLUGIN_DIR . 'includes/settings/class-payuni-payment-settings-tab.php';
		return new WC_Settings_Tab_Payuni();
	}

	/**
	 * 加密
	 */
	public static function encrypt( $encrypt_info ) {
		$tag       = '';
		$encrypted = openssl_encrypt( http_build_query( $encrypt_info ), 'aes-256-gcm', trim( get_option( 'payuni_payment_hashkey' ) ), 0, trim( get_option( 'payuni_payment_hashiv' ) ), $tag );
		return trim( bin2hex( $encrypted . ':::' . base64_encode( $tag ) ) );
	}

	/**
	 * 解密
	 */
	public static function decrypt( string $encrypt_str = '' ) {

		$hashkey = get_option( 'payuni_payment_hashkey' );
		$hashiv  = get_option( 'payuni_payment_hashiv' );

		list($encrypt_data, $tag) = explode( ':::', hex2bin( $encrypt_str ), 2 );
		$encrypt_info             = openssl_decrypt( $encrypt_data, 'aes-256-gcm', trim( $hashkey ), 0, trim( $hashiv ), base64_decode( $tag ) );
		parse_str( $encrypt_info, $encrypt_arr );
		return $encrypt_arr;
	}

	/**
	 * hash
	 */
	public static function hash_info( string $encrypt_str = '' ) {
		return strtoupper( hash( 'sha256', get_option( 'payuni_payment_hashkey' ) . $encrypt_str . get_option( 'payuni_payment_hashiv' ) ) );
	}

	//payuni訂單編號 = woo訂單id + 3位數字
    public static function build_payuni_order_no( $order_id ) {

		$order = wc_get_order( $order_id );

        $payuni_order_no = $order_id;

		$order_serial_no = $order->get_meta( '_payuni_order_serial_no' );

        if ( $order_serial_no && $order_serial_no < 999 ) {
            $order_serial_no += 1;
            $payuni_order_no = $payuni_order_no . str_pad( $order_serial_no, 3, '0', STR_PAD_LEFT); ;
        } else {
			$order_serial_no = 1;
            $payuni_order_no = $payuni_order_no . str_pad( $order_serial_no, 3, '0', STR_PAD_LEFT);
        }

		$order->update_meta_data( '_payuni_order_serial_no', $order_serial_no );
		$order->save();

        return $payuni_order_no;

    }

    //將payuni的訂單編號轉成woo的訂單編號
    public static function parse_payuni_order_no_to_woo_order_id( $payuni_order_no ) {
        $real_woo_order_id = substr($payuni_order_no, 0, -3);
        return $real_woo_order_id;
    }

	/**
	 * Filter the cart template path to use cart.php in this plugin instead of the one in WooCommerce.
	 *
	 * @param string $template      Default template file path.
	 * @param string $template_name Template file slug.
	 * @param string $template_path Template file name.
	 *
	 * @return string The new Template file path.
	 */
	public function wpbr_payuni_wc_template( $template, $template_name, $template_path ) {

		if ( 'order-received.php' === basename( $template ) ) {
			$template = trailingslashit( PAYUNI_PLUGIN_DIR ) . 'includes/templates/checkout/order-received.php';
		}

		return $template;

	}

	/**
	 * Get refund api url by payment method.
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

	/**
	 * Log method.
	 *
	 * @param string $message The message to be logged.
	 * @param string $level The log level. Optional. Default 'info'. Possible values: emergency|alert|critical|error|warning|notice|info|debug.
	 * @return void
	 */
	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->log( $level, $message, array( 'source' => 'wpbr-payuni-payment' ) );
		}
	}

	/**
	 * Returns the single instance of the Payuni_Payment object
	 *
	 * @return Payuni_Payment
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}
