<?php
/**
 * OrderMetaBoxes class file
 *
 * @package payuni
 */

namespace WPBrewer\Payuni\Payment\Admin;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use WPBrewer\Payuni\Payment\PayuniPayment;
use WPBrewer\Payuni\Payment\Utils\OrderMeta;
use WPBrewer\Payuni\Payment\Utils\SingletonTrait;

/**
 * OrderMetaBoxes main class for handling all checkout related process.
 */
class OrderMetaBoxes {

	use SingletonTrait;

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
	 * @param string $post_type            The post type.
	 * @param object $post_or_order_object The post object.
	 * 
	 * @return void
	 */
	public function payuni_add_meta_boxes( $post_type, $post_or_order_object ) {

		$order = ( $post_or_order_object instanceof \WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( ! array_key_exists( $order->get_payment_method(), PayuniPayment::get_allowed_payments( $order ) ) ) {
			return;
		}

		$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? wc_get_page_screen_id( 'shop-order' )
		: 'shop_order';

		add_meta_box(
			'payuni-order-meta-boxes',
			__( 'PAYUNi Payment Detail', 'wpbr-payuni-payment' ),
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
	 * @param  object $post_or_order_object The post object.
	 * @return void
	 */
	public function payuni_order_admin_meta_box( $post_or_order_object ) {

		$order = ( $post_or_order_object instanceof \WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

		if ( ! $order ) {
			return;
		}

		$payment_method   = $order->get_payment_method();
		$allowed_payments = PayuniPayment::get_allowed_payments( $order );
		$gateway          = $allowed_payments[ $payment_method ];

		$payuni_order_no_key = PayuniPayment::get_order_meta_key( $order, OrderMeta::PAYUNI_ORDER_NO );
		echo '<div><strong>訂單編號:</strong> ' . esc_html( $order->get_meta( $payuni_order_no_key ) ) . '</div>';
		foreach ( $gateway::get_order_metas() as $key => $value ) {
			// for backward compatibility.
			$key = PayuniPayment::get_order_meta_key( $order, $key );
			echo '<div><strong>' . esc_html( $value ) . ':</strong> ' . esc_html( $order->get_meta( $key ) ) . '</div>';
		}

		echo '<div><button id="payuni-query-btn" class="button" data-id="' . esc_html( $order->get_id() ) . '">查詢</button></div>';
	}

}
