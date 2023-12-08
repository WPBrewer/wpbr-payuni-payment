<?php
/**
 * The PAYUNi Payment plugin
 *
 * @since             1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       PAYUNi Payment for WooCommerce
 * Description:       PAYUNi Payment for WooCommerce
 * Plugin URI:        https://wpbrewer.com/product/wpbr-payuni-payment
 * Version:           1.1.1
 * Author:            WPBrewer
 * Author URI:        https://wpbrewer.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wpbr-payuni-payment
 * Domain Path:       /languages
 * @package payuni
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'PAYUNI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PAYUNI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAYUNI_BASENAME', plugin_basename( __FILE__ ) );
define( 'PAYUNI_PAYMENT_VERSION', '1.1.1' );

/**
 * Display warning when WooCommerce is not installed and activated.
 *
 * @return void
 */
function payuni_payment_needs_woocommerce() {

	echo '<div id="message" class="error">';
	echo '  <p>' . esc_html__( 'PAYUNi Payment needs WooCommerce, please intall and activate WooCommerce first!', 'wpbr-payuni-payment' ) . '</p>';
	echo '</div>';

}

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Run PAYUNi Payment plugin.
 *
 * @return void
 */
function run_payuni_payment() {

	if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( is_plugin_active( 'wpbr-payuni-payment/wpbr-payuni-payment.php' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			add_action( 'admin_notices', 'payuni_payment_needs_woocommerce' );
			return;
		}
	}

	require_once PAYUNI_PLUGIN_DIR . 'includes/class-payuni-payment.php';
	Payuni_Payment::init();
}

add_action( 'plugins_loaded', 'run_payuni_payment' );
