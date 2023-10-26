<?php
/**
 * Payuni_Payment_Order_Meta_Boxes class file
 *
 * @package payuni
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

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

		add_action( 'add_meta_boxes', array( self::get_instance(), 'payuni_add_meta_boxes' ), 10, 2 );
	}

	/**
	 * Add meta box
	 *
	 * @param object $post The post object.
	 * @return void
	 */
	public function payuni_add_meta_boxes( $post_type, $post_or_order_object ) {

		$order = ( $post_or_order_object instanceof \WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( ! array_key_exists( $order->get_payment_method(), Payuni_Payment::$allowed_payments ) ) {
			return;
		}

		$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? wc_get_page_screen_id( 'shop-order' )
		: 'shop_order';

		add_meta_box(
			'payuni-order-meta-boxes',
			__( 'PAYUNi Payment Detail', 'wc-payuni-payment' ),
			array(
				self::get_instance(),
				'payuni_order_admin_meta_box',
			),
			$screen,
			'side',
			'high'
		);


	}

	/**
	 * Meta box ouput
	 *
	 * @param object $post The post object.
	 * @return void
	 */
	public function payuni_order_admin_meta_box( $post_or_order_object ) {

		$order = ( $post_or_order_object instanceof \WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();
		$gateway        = Payuni_Payment::$allowed_payments[ $payment_method ];

		echo '<div><strong>訂單編號:</strong> ' . esc_html( $order->get_meta( '_payuni_order_no' ) ) . '</div>';
		foreach (  $gateway::get_order_metas() as $key => $value ) {
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
