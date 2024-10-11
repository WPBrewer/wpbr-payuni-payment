<?php
/**
 * The PAYUNi UPP(UNiPaypage) Payment plugin
 *
 * @since             1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       Pay with PAYUNi
 * Description:       Provides PAYUNi UPP(UNiPaypage) Payment for WooCommerce.
 * Plugin URI:        https://wpbrewer.com/product/wpbr-payuni-payment
 * Version:           1.6.2
 * Author:            WPBrewer
 * Author URI:        https://wpbrewer.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires Plugins:  woocommerce
 * Text Domain:       wpbr-payuni-payment
 * Domain Path:       /languages
 * @package payuni
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'WPBR_PAYUNI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPBR_PAYUNI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPBR_PAYUNI_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPBR_PAYUNI_PAYMENT_VERSION', '1.6.2' );

require_once WPBR_PAYUNI_PLUGIN_DIR . 'vendor/autoload.php';

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

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Run PAYUNi Payment plugin.
 *
 * @return void
 */
function run_payuni_payment() {

	/**
	 * Check if WooCommerce is installed and activated.
	 *
	 * @since 1.0.0
	 */
	if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( is_plugin_active( 'wpbr-payuni-payment/wpbr-payuni-payment.php' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			add_action( 'admin_notices', __NAMESPACE__ . '\\payuni_payment_needs_woocommerce' );
			return;
		}
	}

	WPBrewer\Payuni\Payment\PayuniPayment::init();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\run_payuni_payment' );
