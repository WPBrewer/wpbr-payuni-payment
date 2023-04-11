<?php
/**
 * Payuni_Payment_Order_Meta_Boxes class file
 *
 * @package payuni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Payuni_Payment main class for handling all checkout related process.
 */
class Payuni_Payment_Order_Meta_Boxes {

	/**
	 * Class instance
	 *
	 * @var Payuni_Payment_Order_Meta_Boxes
	 */
	private static $instance;

	/**
	 * Constructor
	 */
	public function __construct() {
		// do nothing.
	}

	/**
	 * Initialize class andd add hooks
	 *
	 * @return void
	 */
	public static function init() {
		self::get_instance();

		add_action( 'add_meta_boxes', array( self::get_instance(), 'payuni_add_meta_boxes' ) );
	}

	/**
	 * Add meta box
	 *
	 * @param object $post The post object.
	 * @return void
	 */
	public function payuni_add_meta_boxes( $post ) {

		global $post;

		if ( array_key_exists( get_post_meta( $post->ID, '_payment_method', true ), Payuni_Payment::$allowed_payments ) ) {
			add_meta_box(
				'payuni-order-meta-boxes',
				__( 'PAYUNi Payment Detail', 'wc-payuni-payment' ),
				array(
					self::get_instance(),
					'payuni_order_admin_meta_box',
				),
				'shop_order',
				'side',
				'high'
			);
		}

	}

	/**
	 * Meta box ouput
	 *
	 * @param object $post The post object.
	 * @return void
	 */
	public function payuni_order_admin_meta_box( $post ) {

		$order = wc_get_order( $post->ID );
		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();
		$gateway        = Payuni_Payment::$allowed_payments[ $payment_method ];

		foreach ( $gateway::get_order_metas() as $key => $value ) {
			echo '<div><strong>' . esc_html( $value ) . ':</strong> ' . esc_html( $order->get_meta( $key ) ) . '</div>';
		}


		echo '<div><button id="payuni-query-btn" class="button" data-id="' . esc_html( $order->get_id() ) . '">查詢</button></div>';

	}

	/**
	 * Returns the single instance of the Payuni_Payment_Order_Meta_Boxes object
	 *
	 * @return Payuni_Payment_Order_Meta_Boxes
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
