<?php
/**
 * Class to manage products relying on the Software License Server for WooCommerce.
 *
 * @since   1.0.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'SLSWC_Client_Manager' ) ) :
// phpcs:ignore
class SLSWC_Client_Manager {
	/**
	 * Instance of this class.
	 *
	 * @since 1.0.0
	 * @var object
	 */
	private static $instance = null;

	/**
	 * Version - current plugin version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public static $version;

	/**
	 * License URL - The base URL for your woocommerce install.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $license_server_url;

	/**
	 * The plugin slug to check for updates with the server.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public static $slug;

	/**
	 * Plugin text domain.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public static $text_domain;

	/**
	 * List of locally installed plugins
	 *
	 * @var     array $plugins The list of plugins.
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public static $plugins;

	/**
	 * List of locally installed themes.
	 *
	 * @var     array $themes The list of themes.
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public static $themes;

	/**
	 * List of products
	 *
	 * @var     array
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static $products;

	/**
	 * Don't allow cloning
	 *
	 * @since 1.0.0
	 */
	private function __clone() {}

	/**
	 * Don't allow unserializing instances of this class
	 *
	 * @since 1.0.0
	 */
	private function __wakeup() {}

	/**
	 * Return instance of this class
	 *
	 * @param   string $license_server_url The url to the license server.
	 * @param   string $slug The software slug.
	 * @param   string $text_domain The software text domain.
	 * @return  object
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static function get_instance( $license_server_url, $slug, $text_domain ) {
		self::$license_server_url = $license_server_url;
		self::$slug               = $slug;
		self::$text_domain        = $text_domain;

		if ( null === self::$instance ) {
			self::$instance = new self( self::$license_server_url, self::$slug, 'slswcclient' );
		}

		return self::$instance;
	} // get_instance

	/**
	 * Initialize the class actions
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 * @param string $license_server_url - The base url to your woocommerce shop.
	 * @param string $slug - The software slug.
	 * @param string $text_domain - The plugin's text domain.
	 */
	private function __construct( $license_server_url, $slug, $text_domain ) {
		self::$license_server_url = $license_server_url;
		self::$slug               = $slug;
		self::$text_domain        = $text_domain;

		self::$plugins = self::get_local_plugins();
		self::$themes  = self::get_local_themes();

		// add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'wp_ajax_slswc_install_product', array( $this, 'product_background_installer' ) );

		if ( self::is_products_page() ) {
			add_action( 'admin_footer', array( $this, 'admin_footer_script' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		}
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return  void
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public static function admin_enqueue_scripts() {
		if ( self::is_products_page() ) {
			wp_enqueue_script( 'thickbox' );
			wp_enqueue_style( 'thickbox' );
		}
	}

	/**
	 * Check if the current page is a product list page.
	 *
	 * @return  boolean
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public static function is_products_page() {

		$tabs = array( 'plugins', 'themes' );
		$page = 'slswc_license_manager';
		// phpcs:disable
		$is_page = isset( $_GET['page'] ) && $page === $_GET['page'] ? true : false;
		$is_tab  = isset( $_GET['tab'] ) && in_array( wp_unslash( $_GET['tab'] ), $tabs, true ) ? true : false;
		// phpcs:enable
		if ( is_admin() && $is_page && $is_tab ) {
			return true;
		}

		return false;
	}

	/**
	 * Add script to admin footer.
	 *
	 * @return  void
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public static function admin_footer_script() {
		?>
	<script type="text/javascript">
		jQuery( function( $ ){
			$('.slswc-install-now, .slswc-update-now').on( 'click', function(e){
				e.preventDefault();
				let $el = $(this);
				let package = $(this).data('package');
				let name = $(this).data('name');
				let slug = $(this).data('slug');
				let type = $(this).data('type');
				let label = $(this).html();
				let nonce = $(this).data('nonce');
				let action_label = "<?php esc_attr_e( 'Processing', 'slswcclient' ); ?>";
				$(this).html('<img src="<?php echo esc_url( admin_url( 'images/loading.gif' ) ); ?>" /> ' + action_label );
				$.ajax({
					url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
					data: {
						action:  'slswc_install_product',
						package: package,
						name:    name,
						slug:    slug,
						type:    type,
						nonce:   nonce
					},
					dataType: 'json',
					type: 'POST',
					success: function( response ) {
						if ( response.success ) {
							$('#slswc-product-install-message p').html( response.data.message );
							$('#slswc-product-install-message').addClass('updated').show();
						} else {
							$('#slswc-product-install-message p').html( response.data.message );
							$('#slswc-product-install-message').addClass('notice-warning').show();
						}
						$el.html( '<?php echo esc_attr( __( 'Done', 'slswcclient' ) ); ?>' );
						$el.attr('disabled', 'disabled');
					},
					error: function( error ) {
						$('#slswc-product-install-message p').html( error.data.message );
						$('#slswc-product-install-message').addClass('notice-error').show();
					}
				});
			});
		} );
	</script>
		<?php
	}
	/**
	 * ------------------------------------------------------------------
	 * Output Functions
	 * ------------------------------------------------------------------
	 */

	/**
	 * Add the admin menu to the dashboard
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 * @access  public
	 */
	public function add_admin_menu() {
		$page = add_options_page(
			__( 'License Manager', 'slswcclient' ),
			__( 'License Manager', 'slswcclient' ),
			'manage_options',
			'slswc_license_manager',
			array( $this, 'show_installed_products' )
		);
	}

	/**
	 * List all products installed on this server.
	 *
	 * @return  void
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static function show_installed_products() {
		$license_admin_url = admin_url( 'admin.php?page=slswc_license_manager' );
		// phpcs:ignore
		$tab = self::get_tab();

		?>
		<style>
			.slswc-product-thumbnail:before {font-size: 128px;}
			.slswc-plugin-card-bottom {display: flex;}
			.slswc-plugin-card-bottom div {width: 45%;}
			.slswc-plugin-card-bottom div.column-updated {float:left;text-align:left;}
			.slswc-plugin-card-bottom div.column-compatibility {float:right;text-align:right;}
		</style>
		<div class="wrap plugin-install-tab">
			<div id="slswc-product-install-message" class="notice inline hidden"><p></p></div>
			<h1><?php esc_attr_e( 'Licensed Plugins and Themes.', 'slswcclient' ); ?></h1>
			<?php

			if ( isset( $_POST['save_api_keys_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['save_api_keys_nonce'] ) ), 'save_api_keys' ) ) {

				$username        = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
				$consumer_key    = isset( $_POST['consumer_key'] ) ? sanitize_text_field( wp_unslash( $_POST['consumer_key'] ) ) : '';
				$consumer_secret = isset( $_POST['consumer_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['consumer_secret'] ) ) : '';

				$save_username        = update_option( 'slswc_api_username', $username );
				$save_consumer_key    = update_option( 'slswc_consumer_key', $consumer_key );
				$save_consumer_secret = update_option( 'slswc_consumer_secret', $consumer_secret );

				if ( $save_username && $save_consumer_key && $save_consumer_secret ) {
					?>
					<div class="updated"><p><?php esc_attr_e( 'API Settings saved', 'slswcclient' ); ?></p></div>
					<?php
				}
			}

			if ( ! empty( $_POST['connect_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['connect_nonce'] ) ), 'connect' ) ) {
				$connected = self::connect();
				if ( $connected ) {
					?>
					<div class="updated"><p>
					<?php
					esc_attr_e( 'API Connected successfully.', 'slswcclient' );
					?>
					</p></div>
					<?php
				} else {
					?>
					<div class="error notice is-dismissible"><p>
					<?php
					esc_attr_e( 'API connection failed. Please check your keys and try again.', 'slswcclient' );
					?>
					</p></div>
					<?php
				}
			}

			if ( ! empty( $_POST['reset_api_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['reset_api_settings_nonce'] ) ), 'reset_api_settings' ) ) {
				$deleted_username        = delete_option( 'slswc_api_username' );
				$deleted_consumer_key    = delete_option( 'slswc_consumer_key' );
				$deleted_consumer_secret = delete_option( 'slswc_consumer_secret' );

				if ( $deleted_username && $deleted_consumer_key && $deleted_consumer_secret ) {
					?>
					<p class="updated">
					<?php
					esc_attr_e( 'API Keys successfully.', 'slswcclient' );
					?>
					</p>
					<?php
				} else {
					?>
					<p class="updated">
					<?php
					esc_attr_e( 'API Keys not reset.', 'slswcclient' );
					?>
					</p>
					<?php
				}
			}

			if ( ! empty( $_POST['disconnect_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['disconnect_nonce'] ) ), 'disconnect' ) ) {
				update_option( 'slswc_api_connected', 'no' );
			}
			?>
			<div class="wp-filter">
				<ul class="filter-links">
					<li>
						<a href="<?php echo esc_attr( $license_admin_url ); ?>&tab=licenses"
							class="<?php echo esc_attr( ( 'licenses' === $tab || empty( $tab ) ) ? 'current' : '' ); ?>">
							<?php esc_attr_e( 'Licenses', 'slswcclient' ); ?>
						</a>
					</li>
					<li>
						<a href="<?php echo esc_attr( $license_admin_url ); ?>&tab=plugins"
							class="<?php echo ( 'plugins' === $tab ) ? 'current' : ''; ?>">
							<?php esc_attr_e( 'Plugins', 'slswcclient' ); ?>
						</a>
					</li>
					<li>
						<a href="<?php echo esc_attr( $license_admin_url ); ?>&tab=themes"
							class="<?php echo ( 'themes' === $tab ) ? 'current' : ''; ?>">
							<?php esc_attr_e( 'Themes', 'slswcclient' ); ?>
						</a>
					</li>
					<li>
						<a href="<?php echo esc_attr( $license_admin_url ); ?>&tab=api"
							class="<?php echo ( 'api' === $tab ) ? 'current' : ''; ?>">
							<?php esc_attr_e( 'API', 'slswcclient' ); ?>
						</a>
					</li>
				</ul>
			</div>
			<br class="clear" />

			<div class="tablenav-top"></div>
			<?php if ( 'licenses' === $tab || empty( $tab ) ) : ?>
			<div id="licenses">
				<?php self::licenses_form(); ?>
			</div>

			<?php elseif ( 'plugins' === $tab ) : ?>
			<div id="plugins" class="wp-list-table widefat plugin-install">
				<?php self::list_products( self::$plugins ); ?>
			</div>

			<?php elseif ( 'themes' === $tab ) : ?>
			<div id="themes" class="wp-list-table widefat plugin-install">
				<?php self::list_products( self::$themes ); ?>
			</div>

			<?php else : ?>
			<div id="api">
				<?php self::api_form(); ?>
			</div>
				<?php
			endif;
			?>
			<?php
	}

	/**
	 * Output licenses form
	 *
	 * @return  void
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static function licenses_form() {
		?>
		<style>
		.licenses-table{margin-top: 9px;}
		.licenses-table th, .licenses-table td {padding: 8px 10px;}
		.licenses-table .actions {vertical-align: middle;width: 20px;}
		.licenses-table .license-field input[type="text"], .licenses-table .license-field select{
			width: 100% !important;
		}
		</style>
		<?php

		if ( ! empty( $_POST['licenses'] ) && ! empty( $_POST['save_licenses_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['save_licenses_nonce'] ) ), 'save_licenses' ) ) {
			// phpcs:ignore
			$post_licenses = isset( $_POST['licenses'] ) ? wp_unslash( $_POST['licenses'] ) : array();

			if ( ! empty( $post_licenses ) ) {
				foreach ( $post_licenses as $slug => $license_details ) {
					$license_details = recursive_parse_args(
						$license_details,
						array(
							'license_status'  => 'inactive',
							'license_key'     => '',
							'license_expires' => '',
							'current_version' => self::$version,
							'environment'     => 'live',
						)
					);

					update_option( $slug . '_license_manager', $license_details );
					do_action( "slswc_save_license_{$slug}", $license_details );
				}
			}
		}
		?>
		<form name="licenses-form" action="" method="post">
			<?php wp_nonce_field( 'save_licenses', 'save_licenses_nonce' ); ?>
			<table class="form-table licenses-table widefat striped" >
				<thead>
					<tr>
						<th><?php esc_attr_e( 'Product Name', 'slswcclient' ); ?></th>
						<th><?php esc_attr_e( 'License Key', 'slswcclient' ); ?></th>
						<th><?php esc_attr_e( 'License Status', 'slswcclient' ); ?></th>
						<th><?php esc_attr_e( 'License Expires', 'slswcclient' ); ?></th>
						<th><?php esc_attr_e( 'Deactivate', 'slswcclient' ); ?></th>
						<th><?php esc_attr_e( 'Environment', 'slswcclient' ); ?></th>
						<?php do_action( 'slswc_after_licenses_column_headings' ); ?>
						<!--<th><?php esc_attr_e( 'Action', 'slswcclient' ); ?></th>-->
					</tr>
				</thead>
				<tbody>
					<?php
					if ( ! empty( self::$plugins ) ) :
						self::licenses_rows( self::$plugins );
						do_action( 'slswc_after_plugins_licenses_list' );
						endif;

					if ( ! empty( self::$themes ) ) :
						self::licenses_rows( self::$themes );
						do_action( 'slswc_after_themes_licenses_list' );
						endif;
					?>
					<?php do_action( 'slswc_after_products_licenses_list', self::$plugins, self::$themes ); ?>
				</tbody>
			</table>
			<p>
				<?php submit_button( __( 'Save Licenses', 'slswcclient' ), 'primary', 'save_licenses' ); ?>
			</p>
		</form>
		<?php
	}

	/**
	 * Licenses rows output
	 *
	 * @param   array $products The list of software products.
	 * @return  void
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static function licenses_rows( $products ) {

		foreach ( $products as $product ) :
			$slug         = esc_attr( $product['slug'] );
			$option_name  = $slug . '_license_manager';
			$license_info = get_option( $option_name );
			$product_name = ! empty( $product['name'] ) ? $product['name'] : $product['title'];

			$has_license_info    = empty( $license_info ) ? false : true;
			$license_key         = $has_license_info ? trim( $license_info['license_key'] ) : '';
			$current_version     = $has_license_info ? trim( $license_info['current_version'] ) : '';
			$license_status      = $has_license_info ? trim( $license_info['license_status'] ) : '';
			$license_expires     = $has_license_info ? trim( $license_info['license_expires'] ) : '';
			$license_environment = $has_license_info ? trim( $license_info['environment'] ) : 'live';
			?>
			<tr>
				<td><?php echo esc_attr( $product_name ); ?></td>
				<td class="license-field">
					<input type="text"
							name="licenses[<?php echo esc_attr( $slug ); ?>][license_key]"
							id="<?php echo esc_attr( $slug ); ?>_license_key"
							value="<?php echo esc_attr( $license_key ); ?>"
					/>
					<input type="hidden"
							name="licenses[<?php echo esc_attr( $slug ); ?>][current_version]"
							id="<?php echo esc_attr( $slug ); ?>_current_version"
							value="<?php echo esc_attr( $current_version ); ?>"
					/>
				</td>
				<td class="license-field">
					<?php self::license_status_field( $license_status ); ?>
					<input type="hidden"
							name="licenses[<?php echo esc_attr( $slug ); ?>][license_status]"
							id="<?php echo esc_attr( $slug ); ?>_license_status"
							value="<?php echo esc_attr( $license_status ); ?>"
					/>
				</td>
				<td class="license-field">
					<?php echo esc_attr( $license_expires ); ?>
					<input type="hidden"
							name="licenses[<?php echo esc_attr( $slug ); ?>][license_expires]"
							id="<?php echo esc_attr( $slug ); ?>_license_expires"
							value="<?php echo esc_attr( $license_expires ); ?>"
					/>
				</td>
				<td class="license-field">
					<input type="checkbox"
							name="licenses[<?php echo esc_attr( $slug ); ?>][deactivate_license]"
							value="deactivate_license"
							id="<?php echo esc_attr( $slug ); ?>_deactivate_license"
							<?php array_key_exists( 'deactivate_license', $license_info ) ? checked( $license_info['deactivate_license'], 'deactivate_license' ) : ''; ?>
					/>
				</td>
				<td class="license-field">
					<input type="radio"
							name="licenses[<?php echo esc_attr( $slug ); ?>][environment]"
							id="<?php echo esc_attr( $slug ); ?>_environment_live"
							value="live"
							<?php checked( $license_environment, 'live' ); ?>
					/> <?php echo esc_attr( __( 'Live', 'slswcclient' ) ); ?>

					<input type="radio"
							name="licenses[<?php echo esc_attr( $slug ); ?>][environment]"
							id="<?php echo esc_attr( $slug ); ?>_environment_staging"
							value="staging"
							<?php checked( $license_environment, 'staging' ); ?>
					/> <?php echo esc_attr( __( 'Staging', 'slswcclient' ) ); ?>
				</td>
				<?php do_action( 'slswc_after_license_column', $product ); ?>
				<!--<td>
					<a href="#"><span class="dashicons dashicons-yes"></span> Check</a>
				</td>-->
			</tr>
			<?php do_action( 'slswc_after_license_row', $product ); ?>
			<?php
		endforeach;
	}

	/**
	 * Output a list of products.
	 *
	 * @param   string $products The list of products.
	 * @return  void
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static function list_products( $products ) {
		$products = is_array( $products ) ? $products : (array) $products;

		$type = self::get_tab();

		if ( in_array( $type, array( 'plugins', 'themes' ) ) ) {
			$slugs    = array();
			$licenses = array();
			foreach ( $products as $slug => $details ) {
				$slugs[]           = $slug;
				$licenses[ $slug ] = $details;
			}
			$args            = array( 'post_name__in' => $slugs );
			$remote_products = (array) self::get_remote_products( $type, $args );
		} else {
			$remote_products = array();
		}

		SLSWC::log( 'Local products.' );
		SLSWC::log( $products );
		SLSWC::log( 'Remote Products' );
		SLSWC::log( $remote_products );

		?>
		<?php if ( ! empty( $products ) && count( $products ) > 0 ) : ?>
			<h2 class="screen-reader-text"><?php echo esc_attr( __( 'Plugins List', 'slswcclient' ) ); ?></h2>
			<div id="the-list">
				<?php foreach ( $products as $product ) : ?>
					<?php

					$product = is_array( $product ) ? $product : (array) $product;

					if ( array_key_exists( $product['slug'], $remote_products ) ) {
						$product = recursive_parse_args( (array) $remote_products[ $product['slug'] ], $product );
					}

					$installed = file_exists( $product['file'] ) || is_dir( $product['file'] ) ? true : false;

					$name_version = esc_attr( $product['name'] ) . ' ' . esc_attr( $product['version'] );
					$action_class = $installed ? 'update' : 'install';
					$action_label = $installed ? __( 'Update Now', 'slswcclient' ) : __( 'Install Now', 'slswcclient' );

					do_action( 'slswc_before_products_list', $products );

					$thumb_class = 'theme' === $product['type'] ? 'appearance' : 'plugins';
					?>
				<div class="plugin-card plugin-card-<?php echo esc_attr( $product['slug'] ); ?>">
					<div class="plugin-card-top">
						<div class="name column-name">
							<h3>
								<a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $product['slug'] . '&section=changelog&TB_iframe=true&width=600&height=800' ) ); ?>"
									class="thickbox open-plugin-details-modal">
									<?php echo esc_attr( $product['name'] ); ?>
									<?php if ( $product['thumbnail'] == '' ) : ?>
										<i class="dashicons dashicons-admin-<?php echo esc_attr( $thumb_class ); ?> plugin-icon slswc-product-thumbnail"></i>
									<?php else : ?>
										<img src="<?php echo esc_attr( $product['thumbnail'] ); ?>" class="plugin-icon" alt="<?php echo esc_attr( $name_version ); ?>">
									<?php endif; ?>
								</a>
							</h3>
						</div>
						<div class="action-links">
							<ul class="plugin-action-buttons">
								<li>
									<?php if ( empty( $product['download_url'] ) ) : ?>
										<?php esc_attr_e( 'Manual Download Only.', 'slswcclient' ); ?>
									<?php else : ?>
									<a class="slswc-<?php echo esc_attr( $action_class ); ?>-now <?php echo esc_attr( $action_class ); ?>-now button aria-button-if-js"
										data-package="<?php echo esc_url_raw( $product['download_url'] ); ?>"
										data-slug="<?php echo esc_attr( $product['slug'] ); ?>"
										href="#"
										<?php // translators: %s - The license name and version. ?>
										aria-label="<?php echo esc_attr( sprintf( __( 'Update %s now', 'slswcclient' ), esc_attr( $name_version ) ) ); ?>"
										data-name="<?php echo esc_attr( $name_version ); ?>"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'slswc_client_install_' . $product['slug'] ) ); ?>"
										role="button"
										data-type="<?php echo esc_attr( $product['type'] ); ?>">
										<?php echo esc_attr( $action_label ); ?>
									</a>
									<?php endif; ?>
								</li>
								<li>
									<a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $product['slug'] . '&section=changelog&TB_iframe=true&width=772&height=840' ) ); ?>"
										class="thickbox open-plugin-details-modal"
										<?php // translators: %s - Product name. ?>
										aria-label="<?php echo esc_attr( sprintf( __( 'More information about %s', 'slswcclient' ), esc_attr( $name_version ) ) ); ?>"
										data-title="<?php echo esc_attr( $name_version ); ?>">
										<?php echo esc_attr( __( 'More Details', 'slswcclient' ) ); ?>
									</a>
								</li>
							</ul>
						</div>
						<div class="desc column-description">
							<p><?php echo esc_attr( substr( $product['description'], 0, 110 ) ); ?></p>
							<p class="authors"> <cite>By <a href="<?php echo esc_attr( $product['author_uri'] ); ?>"><?php echo esc_attr( $product['author'] ); ?></a></cite></p>
						</div>
					</div>
					<div class="plugin-card-bottom slswc-plugin-card-bottom">
						<div class="column-updated">
							<strong>Last Updated: </strong>
							<?php echo esc_attr( human_time_diff( strtotime( $product['updated'] ) ) ); ?> ago.
						</div>
						<div class="column-compatibility">
							<?php self::show_compatible( $product['compatible_to'] ); ?>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
				<?php do_action( 'slswc_after_list_products', $products ); ?>
			</div>
		<?php else : ?>
			<div class="no-products">
				<p><?php esc_attr_e( 'It seems you currently do not have any products in this category yet.', 'slswcclient' ); ?></p>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Output API Settings form
	 *
	 * @return void
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static function api_form() {
		$keys = self::get_api_keys();
		?>
		<h2><?php esc_attr_e( 'API Settings', 'slswcclient' ); ?></h2>
		<?php if ( empty( $keys ) && ! self::is_connected() ) : ?>
			<?php
			$username        = isset( $keys['username'] ) ? $keys['username'] : '';
			$consumer_key    = isset( $keys['consumer_key'] ) ? $keys['consumer_key'] : '';
			$consumer_secret = isset( $keys['consumer_secret'] ) ? $keys['consumer_secret'] : '';
			?>
		<p class="about-text">
			<?php esc_attr_e( 'Enter your marketplace API details and click save. On the next step click Connect to get your subscriptions listed here.', 'slswcclient' ); ?>
		</p>
		<form name="api-keys" method="post" action="">
			<?php wp_nonce_field( 'save_api_keys', 'save_api_keys_nonce' ); ?>
			<input type="hidden" name="save_api_keys_check" value="1" />
			<table class="form-table">
				<tbody>
					<tr>
						<th><?php esc_attr_e( 'Username', 'slswcclient' ); ?></th>
						<td>
							<input type="text"
									name="username"
									value="<?php echo esc_attr( $username ); ?>"
							/>
						</td>
					</tr>
					<tr>
						<th><?php esc_attr_e( 'Consumer Key', 'slswcclient' ); ?></th>
						<td>
							<input type="password"
									name="consumer_key"
									value="<?php echo esc_attr( $consumer_key ); ?>"
							/>
						</td>
					</tr>
					<tr>
						<th><?php esc_attr_e( 'Consumer Secret', '' ); ?></th>
						<td>
							<input type="password"
									name="consumer_secret"
									value="<?php echo esc_attr( $consumer_secret ); ?>"
							/>
						</td>
					</tr>
					<tfoot>
						<tr>
							<th></th>
							<td>
								<input type="submit"
										id="save-api-keys"
										class="button button-primary"
										value="Save API Keys"
								/>
							</td>
						</tr>
					</tfoot>
				</tbody>
			</table>
		</form>
		<?php elseif ( ! empty( $keys ) && ! self::is_connected() ) : ?>
			<form name="connect" method="post" action="">
				<?php wp_nonce_field( 'connect', 'connect_nonce' ); ?>
				<p><?php esc_attr_e( 'Click on the button to connect your account now.', 'slswcclient' ); ?></p>
				<input type="submit"
						id="connect"
						class="button button-primary"
						value="<?php esc_attr_e( 'Connect Account Now', 'slswcclient' ); ?>"
				/>
			</form>

			<form name="reset_api_settings" method="post" action="">
				<?php wp_nonce_field( 'reset_api_settings', 'reset_api_settings_nonce' ); ?>
				<p></p>
				<input type="submit"
						id="reset_api_settings"
						class="button"
						value="<?php esc_attr_e( 'Reset API Keys', 'slswcclient' ); ?>"
				/>
			</form>

		<?php else : ?>
			<p><?php esc_attr_e( 'Your account is connected.', 'slswcclient' ); ?></p>
			<p><?php esc_attr_e( 'You should be able to see a list of your purchased products and get convenient automatic updates.', 'slswcclient' ); ?></p>
			<form name="disconnect" method="post" action="">
				<?php wp_nonce_field( 'disconnect', 'disconnect_nonce' ); ?>
				<input type="submit"
						id="disconnect"
						class="button button-primary"
						value="<?php esc_attr_e( 'Disconnect', 'slswcclient' ); ?>"
				/>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Output the product ratings
	 *
	 * @return void
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array $args The options for the rating.
	 */
	public static function output_ratings( $args ) {
		wp_star_rating( $args );
		?>
		<span class="num-ratings" aria-hidden="true">(<?php echo esc_attr( $args['number'] ); ?>)</span>
		<?php
	}

	/**
	 * Show compatibility message
	 *
	 * @param   string $version - The version to compare with installed WordPress version.
	 * @return  void
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static function show_compatible( $version ) {
		global $wp_version;
		$compatible = version_compare( $version, $wp_version ) >= 0 ? true : false;

		if ( $compatible ) {
			$compatibility_label = __( 'Compatible', 'slswcclient' );
			$compatibility_class = 'compatible';
		} else {
			$compatibility_label = __( 'Not compatible', 'slswcclient' );
			$compatibility_class = 'incompatible';
		}
		?>
		<span class="compatibility-<?php echo esc_attr( $compatibility_class ); ?>">
			<strong><?php echo esc_attr( $compatibility_label ); ?></strong>
			<?php
			esc_attr_e( ' with your version of WordPress', 'slswcclient' );
			?>
		</span>
		<?php
	}

	/**
	 * License acivated field.
	 *
	 * @since 1.0.0
	 * @since 1.0.1
	 * @access public
	 *
	 * @param string $status The license status.
	 */
	public static function license_status_field( $status ) {

		$license_labels = SLSWC::license_status_types();

		echo empty( $status ) ? '' : esc_attr( $license_labels[ $status ] );
	}

	/**
	 * Connect to the api server using API keys
	 *
	 * @return  array
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static function connect() {
		$keys       = self::get_api_keys();
		$connection = self::server_request( 'connect', $keys );

		SLSWC::log( 'Connecting...' );

		if ( $connection && $connection->connected && 'ok' === $connection->status ) {
			update_option( 'slswc_api_connected', apply_filters( 'slswc_api_connected', 'yes' ) );
			update_option( 'slswc_api_auth_user', apply_filters( 'slswc_api_auth_user', $connection->auth_user ) );

			return true;
		}

		return false;
	}

	/**
	 * Get more details about the product from the license server.
	 *
	 * @param   string $slug The software slug.
	 * @param   string $type The type of software. Expects plugin/theme, default 'plugin'.
	 * @return  array
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static function get_remote_product( $slug = '', $type = 'plugin' ) {

		$request_info = array(
			'slug' => empty( $slug ) ? self::$slug : $slug,
			'type' => $type,
		);

		$license_data = get_option( $slug . '_license_manager', null );

		if ( self::is_connected() ) {
			$request_info = array_merge( $request_info, self::get_api_keys() );
		} elseif ( null !== $license_data && ! empty( $license_data['license_key'] ) ) {
			$request_info['license_key'] = trim( $license_data['license_key'] );
		}

		$response = self::server_request( 'product', $request_info );

		if ( is_object( $response ) && 'ok' === $response->status ) {
			return $response->product;
		}

		SLSWC::log( 'Get remote product' );
		SLSWC::log( $response->product );

		return array();
	}

	/**
	 * Get a user's purchased products.
	 *
	 * @param   string $type The type of products. Expects plugins|themes, default 'plugins'.
	 * @param   array  $args The arguments to form the query to search for the products.
	 * @return  array
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static function get_remote_products( $type = 'plugins', $args = array() ) {
		$licensed_products = array();
		$request_info      = array();
		$slugs             = array();

		$request_info['type'] = $type;

		$licenses_data = self::get_license_data_for_all( $type );

		foreach ( $licenses_data as $slug => $_license_data ) {
			if ( ! self::ignore_status( $_license_data['license_status'] ) ) {
				$slugs[]                    = $slug;
				$licensed_products[ $slug ] = $_license_data;
			}
		}

		if ( ! empty( $licensed_products ) ) {
			$request_info['licensed_products'] = $licensed_products;
		}

		$request_info['query_args'] = wp_parse_args(
			$args,
			array(
				'post_name__in' => $slugs,
			)
		);

		if ( self::is_connected() ) {
			$request_info['api_keys'] = self::get_api_keys();
		}

		$response = self::server_request( 'products', $request_info );

		SLSWC::log( 'Getting remote products' );
		SLSWC::log( $response );

		if ( is_object( $response ) && 'ok' === $response->status ) {
			return $response->products;
		}

		return array();
	}

	/**
	 * Get license data for all locally installed
	 *
	 * @param   string $type The type of products to return license details for. Expects `plugins` or `themes`, default empty.
	 * @return  array
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public static function get_license_data_for_all( $type = '' ) {
		$all_products  = array();
		$licenses_data = array();

		if ( self::valid_type( $type ) ) {
			$function              = "get_local_{$type}";
			$all_products[ $type ] = self::$function();
		} else {
			$all_products['themes']  = self::get_local_themes();
			$all_products['plugins'] = self::get_local_plugins();
		}

		foreach ( $all_products as $type => $_products ) {

			foreach ( $_products as $slug => $_product ) {
				$_license_data          = get_option( $slug . '_license_manager' );
				$licenses_data[ $slug ] = $_license_data;
			}
		}

		$maybe_type_key = '' !== $type ? $type : '';
		return apply_filters( 'slswc_client_licence_data_for_all' . $maybe_type_key, $licenses_data );
	}

	/**
	 * Check if valid product type.
	 *
	 * @param   string $type The plural product type plugins|themes.
	 * @return  bool
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public static function valid_type( $type ) {
		return in_array( $type, array( 'themes', 'plugins' ) );
	}

	/**
	 * Check if status should be ignored
	 *
	 * @param   string $status The status tp check.
	 * @return  bool
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public static function ignore_status( $status ) {
		$ignored_statuses = array( 'expired', 'max_activations', 'failed' );
		return in_array( $status, $ignored_statuses );
	}

	/**
	 * Get the current tab.
	 *
	 * @return  string
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public static function get_tab() {
		return isset( $_GET['tab'] ) && ! empty( $_GET['tab'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) : '';
	}

	/**
	 * Get local themes.
	 *
	 * Get locally installed themes that have SLSWC file headers.
	 *
	 * @return  array $installed_themes List of plugins.
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public static function get_local_themes() {

		if ( ! function_exists( 'wp_get_themes' ) ) {
			return array();
		}

		$themes = wp_cache_get( 'slswc_themes', 'slswc' );

		if ( empty( $themes ) ) {
			$wp_themes = wp_get_themes();
			$themes    = array();

			foreach ( $wp_themes as $theme_file => $theme_details ) {
				if ( $theme_details->get( 'SLSWC' ) && 'theme' === $theme_details->get( 'SLSWC' ) ) {

					$theme_data = array(
						'file'              => WP_CONTENT_DIR . "/themes/{$theme_file}",
						'name'              => $theme_details->get( 'Name' ),
						'theme_url'         => $theme_details->get( 'ThemeURI' ),
						'description'       => $theme_details->get( 'Description' ),
						'author'            => $theme_details->get( 'Author' ),
						'author_uri'        => $theme_details->get( 'AuthorURI' ),
						'version'           => $theme_details->get( 'Version' ),
						'template'          => $theme_details->get( 'Template' ),
						'status'            => $theme_details->get( 'Status' ),
						'tags'              => $theme_details->get( 'Tags' ),
						'text_domain'       => $theme_details->get( 'TextDomain' ),
						'domain_path'       => $theme_details->get( 'DomainPath' ),
						// SLSWC Headers.
						'slswc'             => ! empty( $theme_details->get( 'SLSWC' ) ) ? $theme_details->get( 'SLSWC' ) : '',
						'slug'              => ! empty( $theme_details->get( 'Slug' ) ) ? $theme_details->get( 'Slug' ) : $theme_details->get( 'TextDomain' ),
						'required_wp'       => ! empty( $theme_details->get( 'RequiredWP' ) ) ? $theme_details->get( 'RequiredWP' ) : '',
						'compatible_to'     => ! empty( $theme_details->get( 'CompatibleTo' ) ) ? $theme_details->get( 'CompatibleTo' ) : '',
						'documentation_url' => ! empty( $theme_details->get( 'DocumentationURL' ) ) ? $theme_details->get( 'DocumentationURL' ) : '',
						'type'              => 'theme',
					);

					$themes[ $theme_details->get( 'Slug' ) ] = wp_parse_args( $theme_data, self::default_remote_product( 'theme' ) );
				}
			}
		}

		wp_cache_add( 'slswc_themes', $themes, 'slswc', apply_filters( 'slswc_themes_cache_expiry', HOUR_IN_SECONDS * 2 ) );

		return $themes;
	}

	/**
	 * Get local plugins.
	 *
	 * Get locally installed plugins that have SLSWC file headers.
	 *
	 * @return  array $installed_plugins List of plugins.
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public static function get_local_plugins() {

		if ( ! function_exists( 'get_plugins' ) ) {
			return array();
		}

		$plugins = wp_cache_get( 'slswc_plugins', 'slswc' );

		if ( empty( $plugins ) ) {
			$plugins    = array();
			$wp_plugins = get_plugins();

			foreach ( $wp_plugins as $plugin_file => $plugin_details ) {
				if ( isset( $plugin_details['SLSWC'] ) && 'plugin' === $plugin_details['SLSWC'] ) {

					$plugin_data = array(
						'file'              => WP_CONTENT_DIR . "/plugins/{$plugin_file}",
						'name'              => $plugin_details['Name'],
						'title'             => $plugin_details['Title'],
						'description'       => $plugin_details['Description'],
						'author'            => $plugin_details['Author'],
						'author_uri'        => $plugin_details['AuthorURI'],
						'version'           => $plugin_details['Version'],
						'plugin_url'        => $plugin_details['PluginURI'],
						'text_domain'       => $plugin_details['TextDomain'],
						'domain_path'       => $plugin_details['DomainPath'],
						'network'           => $plugin_details['Network'],
						// SLSWC Headers.
						'slswc'             => ! empty( $plugin_details['SLSWC'] ) ? $plugin_details['SLSWC'] : '',
						'slug'              => ! empty( $plugin_details['Slug'] ) ? $plugin_details['Slug'] : $plugin_details['TextDomain'],
						'required_wp'       => ! empty( $plugin_details['RequiredWP'] ) ? $plugin_details['RequiredWP'] : '',
						'compatible_to'     => ! empty( $plugin_details['CompatibleTo'] ) ? $plugin_details['CompatibleTo'] : '',
						'documentation_url' => ! empty( $plugin_details['DocumentationURL'] ) ? $plugin_details['DocumentationURL'] : '',
						'type'              => 'plugin',
					);

					$plugins[ $plugin_details['Slug'] ] = wp_parse_args( $plugin_data, self::default_remote_product( 'theme' ) );
				}
			}

			wp_cache_add( 'slswc_plugins', $plugins, 'slswc', apply_filters( 'slswc_plugins_cache_expiry', HOUR_IN_SECONDS * 2 ) );
		}

		return $plugins;
	}

	/**
	 * Get default remote product data
	 *
	 * @param   string $type The software type. Expects plugin, theme or other. Default plugin.
	 * @return  array $default_data The default product data.
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public static function default_remote_product( $type = 'plugin' ) {

		$default_data = array(
			'thumbnail'      => '',
			'updated'        => gmdate( 'Y-m-d' ),
			'reviews_count'  => 0,
			'average_rating' => 0,
			'activations'    => 0,
			'type'           => $type,
			'download_url'   => '',
		);

		return $default_data;
	}

	/**
	 * Get the API Keys stored in database
	 *
	 * @return  array
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static function get_api_keys() {
		return array_filter(
			array(
				'username'        => get_option( 'slswc_api_username' ),
				'consumer_key'    => get_option( 'slswc_consumer_key' ),
				'consumer_secret' => get_option( 'slswc_consumer_secret' ),
			)
		);
	}

	/**
	 * Save a list of products to the database.
	 *
	 * @param array $products List of products to save.
	 * @return void
	 */
	public static function save_products( $products = array() ) {
		if ( empty( $products ) ) {
			$products = self::$products;
		}
		SLSWC::log( 'Saving products...' );
		SLSWC::log( $products );
		update_option( 'slswc_products', $products );
	}

	/**
	 * Check if the account is connected to the api
	 *
	 * @return  boolean
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static function is_connected() {
		$is_connected = get_option( 'slswc_api_connected', 'no' );
		return 'yes' === $is_connected ? true : false;
	}

	/**
	 * Recursively merge two arrays.
	 *
	 * @param array $args User defined args.
	 * @param array $defaults Default args.
	 * @return array $new_args The two array merged into one.
	 */
	public static function recursive_parse_args( $args, $defaults ) {
		$args     = (array) $args;
		$new_args = (array) $defaults;
		foreach ( $args as $key => $value ) {
			if ( is_array( $value ) && isset( $new_args[ $key ] ) ) {
				$new_args[ $key ] = recursive_parse_args( $value, $new_args[ $key ] );
			} else {
				$new_args[ $key ] = $value;
			}
		}
		return $new_args;
	}

	/**
	 * ---------------------------------------------------------------------------------
	 * Server Request Functions
	 * ---------------------------------------------------------------------------------
	 */

	/**
	 * Send a request to the server.
	 *
	 * @param   string $action activate|deactivate|check_update.
	 * @param   array  $request_info The data to be sent to the server.
	 * @param   string $domain The domain to send the data to.
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static function server_request( $action = 'check_update', $request_info = array(), $domain = '' ) {

		$domain = empty( $domain ) ? self::$license_server_url : $domain;

		// Allow filtering the request info for plugins.
		$request_info = apply_filters( 'slswc_request_info_' . self::$slug, $request_info );

		// Build the server url api end point fix url build to support the WordPress API.
		$server_request_url = esc_url_raw( $domain . 'wp-json/slswc/v1/' . $action . '?' . http_build_query( $request_info ) );
		SLSWC::log( 'server_request_url:' . $server_request_url );

		// Options to parse the wp_safe_remote_get() call.
		$request_options = array( 'timeout' => 30 );

		// Allow filtering the request options.
		$request_options = apply_filters( 'slswc_request_options_' . self::$slug, $request_options );

		// Query the license server.
		$endpoint_get_actions = apply_filters( 'slswc_client_get_actions', array( 'product', 'products' ) );
		if ( in_array( $action, $endpoint_get_actions, true ) ) {
			$response = wp_safe_remote_get( $server_request_url, $request_options );
		} else {
			$response = wp_safe_remote_post( $server_request_url, $request_options );
		}


		// Validate that the response is valid not what the response is.
		$result = self::validate_response( $response );

		// Check if there is an error and display it if there is one, otherwise process the response.
		if ( ! is_wp_error( $result ) ) {

			$response_body = json_decode( wp_remote_retrieve_body( $response ) );

			// Check the status of the response.
			$continue = self::check_response_status( $response_body );

			if ( $continue ) {
				return $response_body;
			}
		} else {
			add_settings_error(
				self::$slug . '_license_manager',
				esc_attr( 'settings_updated' ),
				$result->get_error_message(),
				'error'
			);

			// Return null to halt the execution.
			return null;
		}
	}

	/**
	 * Validate the license server response to ensure its valid response not what the response is.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 * @access  public
	 * @param WP_Error|Array $response The response or WP_Error.
	 */
	public static function validate_response( $response ) {

		if ( ! empty( $response ) ) {

			// Can't talk to the server at all, output the error.
			if ( is_wp_error( $response ) ) {
				return new WP_Error(
					$response->get_error_code(),
					sprintf(
						// translators: 1. Error message.
						__( 'HTTP Error: %s', 'slswcclient' ),
						$response->get_error_message()
					)
				);
			}

			// There was a problem with the initial request.
			if ( ! isset( $response['response']['code'] ) ) {
				return new WP_Error( 'slswc_no_response_code', __( 'wp_safe_remote_get() returned an unexpected result.', 'slswcclient' ) );
			}

			// There is a validation error on the server side, output the problem.
			if ( 400 === $response['response']['code'] ) {

				$body = json_decode( $response['body'] );

				foreach ( $body->data->params as $param => $message ) {
					return new WP_Error(
						'slswc_validation_failed',
						sprintf(
							// translators: %s: Error/response message.
							__( 'There was a problem with your license: %s', 'slswcclient' ),
							$message
						)
					);
				}
			}

			// The server is broken.
			if ( 500 === $response['response']['code'] ) {
				return new WP_Error(
					'slswc_internal_server_error',
					sprintf(
						// translators: %s: the http response code from the server.
						__( 'There was a problem with the license server: HTTP response code is : %s', 'slswcclient' ),
						$response['response']['code']
					)
				);
			}

			if ( 200 !== $response['response']['code'] ) {
				return new WP_Error(
					'slswc_unexpected_response_code',
					sprintf(
						__( 'HTTP response code is : % s, expecting ( 200 )', 'slswcclient' ),
						$response['response']['code']
					)
				);
			}

			if ( empty( $response['body'] ) ) {
				return new WP_Error(
					'slswc_no_response',
					__( 'The server returned no response.', 'slswcclient' )
				);
			}

			return true;
		}
	}

	/**
	 * Validate the license server response to ensure its valid response not what the response is.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 * @access  public
	 * @param   object $response_body The data returned.
	 */
	public static function check_response_status( $response_body ) {
		SLSWC::log( 'Check response' );
		SLSWC::log( $response_body );

		if ( is_object( $response_body ) && ! empty( $response_body ) ) {

			$license_status_types = SLSWC::license_status_types();
			$status               = $response_body->status;

			return ( array_key_exists( $status, $license_status_types ) || 'ok' === $status ) ? true : false;
		}

		return false;
	}

	/**
	 * Install a product.
	 *
	 * @param string $slug    Product slug.
	 * @param string $package The product download url.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public static function product_background_installer( $slug = '', $package = '' ) {
		global $wp_filesystem;

		$slug = isset( $_REQUEST['slug'] ) ? wp_unslash( sanitize_text_field( wp_unslash( $_REQUEST['slug'] ) ) ) : '';
		if ( ! array_key_exists( 'nonce', $_REQUEST )
			|| ! empty( $_REQUEST ) && array_key_exists( 'nonce', $_REQUEST )
			&& isset( $_REQUEST ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'slswc_client_install_' . $slug ) ) {
			wp_send_json_error(
				array(
					'message' => esc_attr__( 'Failed to install product. Security token invalid.', 'slswcclient' ),
				)
			);
		}

		$download_link = isset( $_POST['package'] ) ? sanitize_text_field( wp_unslash( $_POST['package'] ) ) : '';
		$name          = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$product_type  = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		if ( ! empty( $download_link ) ) {
			// Suppress feedback.
			ob_start();

			try {

				$temp_file = download_url( $download_link, 60 );

				if ( ! is_wp_error( $temp_file ) ) {
					require_once ABSPATH . '/wp-admin/includes/file.php';
					WP_Filesystem();

					if ( 'plugin' === $product_type ) {
						$destination_dir = WP_CONTENT_DIR . '/plugins';
					} elseif ( 'theme' === $product_type ) {
						$destination_dir = WP_CONTENT_DIR . '/themes';
					} else {
						$destination_dir = WP_CONTENT_DIR;
					}

					$temp_dir = WP_CONTENT_DIR . '/slswcclient_temp_downloads';
					if ( ! $wp_filesystem->is_dir( $temp_dir ) ) {
						$wp_filesystem->mkdir( $temp_dir, FS_CHMOD_DIR );
					}

					$file_name   = $slug . '.zip';
					$destination = $temp_dir . $file_name;

					if ( $wp_filesystem->exists( $temp_file ) ) {
						$wp_filesystem->move( $temp_file, $destination, true );
					}

					if ( $wp_filesystem->exists( $destination ) ) {
						$unzipfile = unzip_file( $destination, $destination_dir );

						if ( $unzipfile ) {
							$deleted = $wp_filesystem->delete( $destination );
							wp_send_json_success(
								array(
									'message' => sprintf(
										// translators: %s - the name of the plugin/theme.
										__( 'Successfully installed new version of %s', 'slswcclient' ),
										$name
									),
								)
							);
						} else {
							wp_send_json_error(
								array(
									'slug'    => $slug,
									'message' => __( 'Installation failed. There was an error extracting the downloaded file.', 'slswcclient' ),
								)
							);
						}
					}
				}
			} catch ( Exception $e ) {
				wp_send_json_error(
					array(
						'slug'    => $slug . '_install_error',
						'message' => sprintf(
							// translators: 1: theme slug, 2: error message, 3: URL to install theme manually.
							__( '%1$s could not be installed (%2$s). <a href="%3$s">Please install it manually by clicking here.</a>', 'slswcclient' ),
							$slug,
							$e->getMessage(),
							esc_url( admin_url( 'update.php?action=install-' . $product_type . '&' . $product_type . '=' . $slug . '&_wpnonce=' . wp_create_nonce( 'install-' . $product_type . '_' . $slug ) ) )
						),
					)
				);
			}

			wp_send_json_error( array( 'message' => __( 'No action taken.', 'slswcclient' ) ) );

			// Discard feedback.
			ob_end_clean();
		}

		wp_send_json(
			array(
				'message' => __( 'Failed to install product. Download link not provided or is invalid.', 'slswcclient' ),
			)
		);
	}


}

endif;