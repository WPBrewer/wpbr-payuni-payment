<?php
// namespace WPBrewer\Core;
/**
 * The Software License Server for WooCommerce Client Library
 *
 * This class defines all code necessary to check for a valid license and available updates stored on your Software License Server for WooCommerce.
 *
 * Documentation can be found here : https://licenseserver.io/documentation
 *
 * To integrate this into your software product include the following code in your MAIN plugin file, do not attempt.
 * to add this code in any other file but your main plugin file.
 *
 *      // Required Parameters.
 *
 *      @param string  required $license_server_url - The url to the license server.
 *      @param string  required $plugin_file - The path to the main plugin file.
 *
 *      // Optional Parameters.
 *      @param string  optional $product_type - The type of product. plugin/theme
 *
 *  require_once plugin_dir_path( __FILE__ ) . 'path/to/class-slswc-client.php';
 *
 *  function slswc_instance(){
 *      return SLSWC_Client::get_instance( 'http://yourshopurl.here.com', $plugin_file, $product_type );
 *  } // slswc_instance()
 *
 *  slswc_instance();
 *
 * @version     1.0.0
 * @since       1.0.0
 * @package     SLSWC_Client
 * @author      Jamie Madden <support@licenseserver.io>
 * @link        https://licenseserver.io/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'SLSWC_Client' ) ) :

	/**
	 * Class responsible for a single product.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	//phpcs:ignore
	class SLSWC_Client {
		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		private static $instances = array();

		/**
		 * Version - current plugin version
		 *
		 * @var string $version
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public $version;

		/**
		 * License URL - The base URL for your woocommerce install
		 *
		 * @var string $license_server_url
		 * @version 1.0.0
		 * @since 1.0.0
		 */
		public $license_server_url;

		/**
		 * Slug - the plugin slug to check for updates with the server
		 *
		 * @var string $slug
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public $slug;

		/**
		 * Plugin text domain
		 *
		 * @var string $text_domain
		 * @version 1.0.0
		 * @since 1.0.0
		 */
		public $text_domain;

		/**
		 * Path to the plugin file or directory, relative to the plugins directory
		 *
		 * @var string $base_file
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public $base_file;

		/**
		 * Path to the plugin file or directory, relative to the plugins directory
		 *
		 * @var string $name
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public $name;

		/**
		 * Update interval - what period in hours to check for updates defaults to 12;
		 *
		 * @var string $update_interval
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public $update_interval;

		/**
		 * Option name - wp option name for license and update information stored as $slug_wc_software_license.
		 *
		 * @var string $option_name
		 * @version 1.0.0
		 * @since 1.0.0
		 */
		public $option_name;

		/**
		 * The license server host.
		 *
		 * @var string $version
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		private $license_server_host;

		/**
		 * The plugin license key.
		 *
		 * @var string $version
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		private $license_key;

		/**
		 * The domain the plugin is running on.
		 *
		 * @var string $version
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		private $domain;

		/**
		 * The plugin license key.
		 *
		 * @var string $version
		 * @version 1.0.0
		 * @since   1.0.0
		 * @access private
		 */
		private $admin_notice;

		/**
		 * The current environment on which the client is install.
		 *
		 * @var     string
		 * @since   1.0.0
		 * @version 1.0.0
		 */
		private $environment;

		/**
		 * Holds instance of SLSWC_Client_Manager class
		 *
		 * @var     SLSWC_Client_Manager
		 * @since   1.0.0
		 * @version 1.0.0
		 */
		public $client_manager;

		/**
		 * Whether to show the builtin settings page
		 *
		 * @var     bool
		 * @since   1.0.0
		 * @version 1.0.0
		 */
		public $show_settings_page;

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
		 * Return an instance of this class.
		 *
		 * @since   1.0.0
		 * @version 1.0.0
		 * @param   string $license_server_url - The base url to your woocommerce shop.
		 * @param   string $base_file - path to the plugin file or directory, relative to the plugins directory.
		 * @param   string $software_type - the type of software this is. plugin|theme, default: plugin.
		 * @param   mixed  ...$args - array of additional arguments to override default ones.
		 * @return  object A single instance of this class.
		 */
		public static function get_instance( $license_server_url, $base_file, $software_type = 'plugin', ...$args ) {

			$args = recursive_parse_args( $args, recursive_parse_args( self::get_default_args(), self::get_file_information( $base_file, $software_type ) ) );
			$text_domain = $args['text_domain'];
			if ( ! array_key_exists( $text_domain, self::$instances ) ) {
				self::$instances[ $text_domain ] = new self( $license_server_url, $base_file, $software_type, $args );
			}

			return self::$instances;

		} // get_instance

		/**
		 * Initialize the class actions.
		 *
		 * @since   1.0.0
		 * @version 1.0.0
		 * @param   string  $license_server_url - The base url to your woocommerce shop.
		 * @param   string  $base_file - path to the plugin file or directory, relative to the plugins directory.
		 * @param   string  $software_type - the type of software this is. plugin|theme, default: plugin.
		 * @param   integer $args - array of additional arguments to override default ones.
		 */
		private function __construct( $license_server_url, $base_file, $software_type, $args ) {
			if ( empty( $args ) ) {
				$args = $this->get_file_information( $base_file, $software_type );
			}

			$this->base_file          = $base_file;
			$this->name               = empty( $args['name'] ) ? $args['title'] : $args['name'];
			$this->license_server_url = trailingslashit( $license_server_url );
			$this->version            = $args['version'];
			$this->text_domain        = $args['text_domain'];
			$this->show_settings_page = $args['show_settings_page'];

			if ( 'plugin' === $software_type ) {
				$this->plugin_file = plugin_basename( $base_file );
				$this->slug        = empty( $args['slug'] ) ? basename( $this->plugin_file, '.php' ) : $args['slug'];
			} else {
				$this->theme_file = $base_file;
				$this->slug       = empty( $args['slug'] ) ? basename( $this->theme_file, '.css' ) : $args['slug'];
			}

			$this->update_interval = $args['update_interval'];
			$this->debug           = apply_filters( 'slswc_client_logging', defined( 'WP_DEBUG' ) && WP_DEBUG ? true : $args['debug'] );

			$this->option_name         = $this->slug . '_license_manager';
			$this->domain              = untrailingslashit( str_ireplace( array( 'http://', 'https://' ), '', home_url() ) );
			$this->license_details     = get_option( $this->option_name );
			$this->software_type       = $software_type;
			$this->environment         = $args['environment'];
			$this->license_manager_url = esc_url( admin_url( 'options-general.php?page=slswc_license_manager&tab=licenses' ) );
			$this->license_client_url  = esc_url( admin_url( 'options-general.php?page=' . $this->slug . '_license_manager' ) );

			// Get the license server host.
			// phpcs:ignore
			$this->license_server_host = @wp_parse_url( $this->license_server_url, PHP_URL_HOST );

			// Don't run the license activation code if running on local host.
			$whitelist = apply_filters( 'wcv_localhost_whitelist', array( '127.0.0.1', '::1' ) );

			// if ( isset( $_SERVER['SERVER_ADDR'] ) && in_array( sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ), $whitelist, true ) && ! $args['debug'] ) {

			// 	add_action( 'admin_notices', array( $this, 'license_localhost' ) );

			// } else {

				// Initilize wp-admin interfaces.
				add_action( 'admin_init', array( $this, 'check_install' ) );
				add_action( 'admin_init', array( $this, 'add_license_settings' ) );
				add_action( 'admin_menu', array( $this, 'add_license_menu' ) );

				// Internal methods.
				add_filter( 'http_request_host_is_external', array( $this, 'fix_update_host' ), 10, 2 );

				// Validate license on save.
				// add_action( 'slswc_save_license_' . $this->slug, array( $this, 'validate_license' ), 99 );

				/**
				 * Only allow updates if they have a valid license key.
				 * Or API keys are set to check for updates.
				 */
				if ( 'active' === $this->license_details['license_status'] || 'expiring' === $this->license_details['license_status'] || SLSWC_Client_Manager::is_connected() ) {
					if ( 'plugin' === $this->software_type ) {
						add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'update_check' ) );
						add_filter( 'plugins_api', array( $this, 'add_plugin_info' ), 10, 3 );
						add_filter( 'plugin_row_meta', array( $this, 'check_for_update_link' ), 10, 2 );
					} else {
						add_action( 'pre_set_site_transient_update_themes', array( $this, 'theme_update_check' ), 21, 1 );
					}

					add_action( 'admin_init', array( $this, 'process_manual_update_check' ) );
					add_action( 'all_admin_notices', array( $this, 'output_manual_update_check_result' ) );
				}
			// }

			global $slswc_license_server_url, $slswc_slug, $slswc_text_domain, $slswc_products;
			$slswc_license_server_url = trailingslashit( $license_server_url );
			$slswc_slug               = $args['slug'];
			$slswc_text_domain        = $args['text_domain'];

			$slswc_products = get_transient( 'slswc_products' );

			$slswc_products[ $slswc_slug ] = array(
				'slug'               => $slswc_slug,
				'text_domain'        => $slswc_text_domain,
				'license_server_url' => $slswc_license_server_url,
			);

			$slswc_products = array_filter( $slswc_products );

			set_transient( 'slswc_products', $slswc_products, HOUR_IN_SECONDS );

			SLSWC::log( "License Server Url: $license_server_url" );
			SLSWC::log( "Base file: $base_file" );
			SLSWC::log( "Software type: $software_type" );
			SLSWC::log( $args );
		}

		/**
		 * Get the default args
		 *
		 * @return  array $args
		 * @since   1.0.0
		 * @version 1.0.0
		 */
		public static function get_default_args() {
			return array(
				'update_interval'    => 12,
				'debug'              => false,
				'environment'        => 'live',
				'show_settings_page' => true,
			);
		}

		/**
		 * Check the installation and configure any defaults that are required
		 *
		 * @since   1.0.0
		 * @version 1.0.0
		 * @todo move this to a plugin activation hook
		 */
		public function check_install() {

			// Set defaults.
			if ( empty( $this->license_details ) ) {
				$default_license_options = array(
					'license_status'  => 'inactive',
					'license_key'     => '',
					'license_expires' => '',
					'current_version' => $this->version,
					'environment'     => $this->environment,
				);

				update_option( $this->option_name, $default_license_options );
			}

			if ( '' === $this->license_details || 'inactive' === $this->license_details['license_status'] || 'deactivated' === $this->license_details['license_status'] ) {
				add_action( 'admin_notices', array( $this, 'license_inactive' ) );
			}

			if ( 'expired' === $this->license_details['license_status'] && 'active' === $this->license_details['license_status'] ) {
				add_action( 'admin_notices', array( $this, 'license_inactive' ) );
			}

		}

		/**
		 * Display a license inactive notice
		 */
		public function license_inactive() {

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			echo '<div class="error notice is-dismissible"><p>';
			// phpcs:disable
			// translators: 1 - Product name. 2 - Link opening html. 3 - link closing html.
			echo sprintf( __( 'The %1$s license key has not been activated, so you will not be able to get automatic updates or support! %2$sClick here%3$s to activate your support and updates license key.', 'slswcclient' ), esc_attr( $this->name ), '<a href="' . esc_url_raw( $this->license_client_url ) . '">', '</a>' );
			echo '</p></div>';
			// phpcs:enable

		}

		/**
		 * Display the localhost detection notice
		 */
		public function license_localhost() {

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			echo '<div class="error notice is-dismissible"><p>';
			// translators: 1 - Product name.
			echo esc_attr( sprintf( __( '%s has detected you are running on your localhost. The license activation system has been disabled. ', 'slswcclient' ), esc_attr( $this->name ) ) ) . '</p></div>';

		}

		/**
		 * Check for updates with the license server.
		 *
		 * @since  1.0.0
		 * @param  object $transient object from the update api.
		 * @return object $transient object possibly modified.
		 */
		public function update_check( $transient ) {

			if ( empty( $transient->checked ) ) {
				return $transient;
			}

			$server_response = $this->server_request( 'check_update' );

			if ( $this->check_license( $server_response ) ) {
				if ( isset( $server_response ) && is_object( $server_response->software_details ) ) {

					$plugin_update_info = $server_response->software_details;

					if ( isset( $plugin_update_info->new_version ) ) {
						if ( version_compare( $plugin_update_info->new_version, $this->version, '>' ) ) {
							// Required to cast as array due to how object is returned from api.
							$plugin_update_info->sections              = (array) $plugin_update_info->sections;
							$plugin_update_info->banners               = (array) $plugin_update_info->banners;
							$transient->response[ $this->plugin_file ] = $plugin_update_info;
						}
					}
				}
			}

			return $transient;

		}

		/**
		 * Check if there are updates for themes.
		 *
		 * @param   mixed $transient transient object from update api.
		 * @return  mixed $transient transient object from update api.
		 * @since   1.0.0
		 * @version 1.0.0
		 */
		public function theme_update_check( $transient ) {

			if ( empty( $transient->checked ) ) {
				return $transient;
			}

			$server_response = $this->server_request( 'check_update' );

			if ( $this->check_license( $server_response ) ) {

				if ( isset( $server_response ) && is_object( $server_response->software_details ) ) {

					$theme_update_info = $server_response->software_details;

					if ( isset( $theme_update_info->new_version ) ) {
						if ( version_compare( $theme_update_info->new_version, $this->version, '>' ) ) {
							// Required to cast as array due to how object is returned from api.
							$theme_update_info->sections = (array) $theme_update_info->sections;
							$theme_update_info->banners  = (array) $theme_update_info->banners;
							$theme_update_info->url      = $theme_update_info->homepage;
							// Theme name.
							$transient->response[ $this->slug ] = (array) $theme_update_info;
						}
					}
				}
			}

			return $transient;
		}

		/**
		 * Add the plugin information to the WordPress Update API.
		 *
		 * @since  1.0.0
		 * @param  bool|object $result The result object. Default false.
		 * @param  string      $action The type of information being requested from the Plugin Install API.
		 * @param  object      $args Plugin API arguments.
		 * @return object
		 */
		public function add_plugin_info( $result, $action = null, $args = null ) {

			// Is this about our plugin?
			if ( isset( $args->slug ) ) {

				if ( $args->slug !== $this->slug ) {
					return $result;
				}
			} else {
				return $result;
			}

			$server_response    = $this->server_request();
			$plugin_update_info = $server_response->software_details;

			// Required to cast as array due to how object is returned from api.
			$plugin_update_info->sections = (array) $plugin_update_info->sections;
			$plugin_update_info->banners  = (array) $plugin_update_info->banners;
			$plugin_update_info->ratings  = (array) $plugin_update_info->ratings;
			if ( isset( $plugin_update_info ) && is_object( $plugin_update_info ) && false !== $plugin_update_info ) {
				return $plugin_update_info;
			}

			return $result;

		}

		/**
		 * Send a request to the server
		 *
		 * @param string $action Action to be taken. Possible balues: activate|deactivate|check_update. Default: check_update.
		 * @param array  $request_info The data to be sent as part of the request.
		 */
		public function server_request( $action = 'check_update', $request_info = array() ) {

			SLSWC::log( 'slswc client manager is connected:' . SLSWC_Client_Manager::is_connected() );
			if ( empty( $request_info ) && ! SLSWC_Client_Manager::is_connected() ) {
				$request_info['slug']        = $this->slug;
				$request_info['license_key'] = trim( $this->license_details['license_key'] );
				$request_info['domain']      = $this->domain;
				$request_info['version']     = $this->version;
				$request_info['environment'] = $this->environment;
			} elseif ( SLSWC_Client_Manager::is_connected() ) {
				$request_info['slug']        = $this->slug;
				$request_info['domain']      = $this->domain;
				$request_info['version']     = $this->version;
				$request_info['environment'] = $this->environment;

				$request_info = array_merge( $request_info, SLSWC_Client_Manager::get_api_keys() );
			}

			return SLSWC_Client_Manager::server_request( $action, $request_info );

		} // server_request


		/**
		 * Validate the license is active and if not, set the status and return false
		 *
		 * @since 1.0.0
		 * @access public
		 * @param object $response_body Response body.
		 */
		public function check_license( $response_body ) {

			$status = $response_body->status;

			if ( 'active' === $status || 'expiring' === $status ) {
				return true;
			}

			$this->set_license_status( $status );
			$this->set_license_expires( $response_body->expires );
			$this->save();

			return false;

		} // check_license


		/**
		 * Add a check for update link on the plugins page. You can change the link with the supplied filter.
		 * returning an empty string will disable this link
		 *
		 * @since 1.0.0
		 * @access public
		 * @param array  $links The array having default links for the plugin.
		 * @param string $file The name of the plugin file.
		 */
		public function check_for_update_link( $links, $file ) {
			// Only modify the plugin meta for our plugin.
			if ( $file === $this->plugin_file && current_user_can( 'update_plugins' ) ) {

				$update_link_url = wp_nonce_url(
					add_query_arg(
						array(
							'slswc_check_for_update' => 1,
							'slswc_slug'             => $this->slug,
						),
						self_admin_url( 'plugins.php' )
					),
					'slswc_check_for_update'
				);

				$update_link_text = apply_filters( 'slswc_update_link_text_' . $this->slug, __( 'Check for updates', 'slswcclient' ) );

				if ( ! empty( $update_link_text ) ) {
					$links[] = sprintf( '<a href="%s">%s</a>', esc_attr( $update_link_url ), $update_link_text );
				}
			}

			return $links;

		} // check_for_update_link

		/**
		 * Process the manual check for update if check for update is clicked on the plugins page.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function process_manual_update_check() {
			// phpcs:ignore
			if ( isset( $_GET['slswc_check_for_update'] ) && isset( $_GET['slswc_slug'] ) && $_GET['slswc_slug'] === $this->slug && current_user_can( 'update_plugins' ) && check_admin_referer( 'slswc_check_for_update' ) ) {

				// Check for updates.
				$server_response = $this->server_request();

				if ( $this->check_license( $server_response ) ) {

					if ( isset( $server_response ) && is_object( $server_response->software_details ) ) {

						$plugin_update_info = $server_response->software_details;

						if ( isset( $plugin_update_info ) && is_object( $plugin_update_info ) ) {

							if ( version_compare( (string) $plugin_update_info->new_version, (string) $this->version, '>' ) ) {

								$update_available = true;

							} else {

								$update_available = false;
							}
						} else {

							$update_available = false;
						}

						$status = ( null === $update_available ) ? 'no' : 'yes';

						wp_safe_redirect(
							add_query_arg(
								array(
									'slswc_update_check_result' => $status,
									'slswc_slug' => $this->slug,
								),
								self_admin_url( 'plugins.php' )
							)
						);
					}
				}
			}

		} // process_manual_update_check


		/**
		 * Out the results of the manual check
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function output_manual_update_check_result() {

			// phpcs:ignore
			if ( isset( $_GET['slswc_update_check_result'] ) && isset( $_GET['slswc_slug'] ) && ( $_GET['slswc_slug'] === $this->slug ) ) {

				// phpcs:ignore
				$check_result = wp_unslash( $_GET['slswc_update_check_result'] );

				switch ( $check_result ) {
					case 'no':
						$admin_notice = __( 'This plugin is up to date. ', 'slswcclient' );
						break;
					case 'yes':
						// translators: 1 - Plugin/Theme name.
						$admin_notice = sprintf( __( 'An update is available for %s.', 'slswcclient' ), $this->name );
						break;
					default:
						$admin_notice = __( 'Unknown update status.', 'slswcclient' );
						break;
				}

				printf( '<div class="updated notice is-dismissible"><p>%s</p></div>', esc_attr( apply_filters( 'slswc_manual_check_message_result_' . $this->slug, $admin_notice, $check_result ) ) );
			}

		} // output_manual_update_check_result

		/**
		 * This is for internal purposes to ensure that during development the HTTP requests go through.
		 * This is due to security features in the WordPress HTTP API.
		 *
		 * Source for this solution: Plugin Update Checker Library 3387.1 by Janis Elsts.
		 *
		 * @since 1.0.0
		 * @access public
		 * @param bool   $allow Whether to allow or not.
		 * @param string $host  The host name.
		 * @return bool
		 */
		public function fix_update_host( $allow, $host ) {

			if ( strtolower( $host ) === strtolower( $this->license_server_url ) ) {
				return true;
			}
			return $allow;

		} //fix_update_host

		/**
		 * Add the admin menu to the dashboard
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function add_license_menu() {
			if ( $this->show_settings_page ) {
				$page = add_options_page(
					// translators: 1 - Plugin/Theme name.
					sprintf( __( '%s License', 'slswcclient' ), $this->name ),
					// translators: 1 - Plugin/Theme name.
					sprintf( __( '%s License', 'slswcclient' ), $this->name ),
					'manage_options',
					$this->slug . '_license_manager',
					array( $this, 'load_license_page' )
				);
			}
		} // add_license_menu

		/**
		 * Load settings for the admin screens so users can input their license key
		 *
		 * Utilizes the WordPress Settings API to implment this
		 *
		 * @since 1.0.0
		 * @access public
		 * TODO: Remove settings functions related to old settings page
		 */
		public function add_license_settings() {

			register_setting( $this->option_name, $this->option_name, array( $this, 'validate_license' ) );

						// License key section.
			add_settings_section(
				$this->slug . '_license_activation',
				__( 'License Activation', 'slswcclient' ),
				array( $this, 'license_activation_section_callback' ),
				$this->option_name
			);

			// License key.
			add_settings_field(
				'license_key',
				__( 'License key', 'slswcclient' ),
				array( $this, 'license_key_field' ),
				$this->option_name,
				$this->slug . '_license_activation'
			);

			// License status.
			add_settings_field(
				'license_status',
				__( 'License Status', 'slswcclient' ),
				array( $this, 'license_status_field' ),
				$this->option_name,
				$this->slug . '_license_activation'
			);

			// License expires.
			add_settings_field(
				'license_expires',
				__( 'License Expires', 'slswcclient' ),
				array( $this, 'license_expires_field' ),
				$this->option_name,
				$this->slug . '_license_activation'
			);

			// License environment.
			add_settings_field(
				'license_environment',
				__( 'This is a Staging Site', 'slswcclient' ),
				array( $this, 'licence_environment_field' ),
				$this->option_name,
				$this->slug . '_environment'
			);

			// Deactivate license checkbox.
			add_settings_field(
				'deactivate_license',
				__( 'Deactivate license', 'slswcclient' ),
				array( $this, 'license_deactivate_field' ),
				$this->option_name,
				$this->slug . '_license_activation'
			);


		} // add_license_page

		/**
		 * License page output call back function.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function load_license_page() {
			?>
		<div class='wrap'>
			<?php // translators: 1 - Plugin/Theme name. ?>
		<h2><?php printf( esc_attr( __( "%s License Manager", 'slswcclient' )), esc_attr( $this->name ) ); ?></h2>
		<form action='options.php' method='post'>
			<div class="main">

				<?php
					settings_fields( $this->option_name );
					do_settings_sections( $this->option_name );
					submit_button( __( 'Save Changes', 'slswcclient' ) );
				?>
				</div>
			</form>
		</div>

			<?php
		} // license_page

		/**
		 * License activation settings section callback
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function license_activation_section_callback() {

			echo '<p>' . esc_attr( __( 'Please enter your license key to activate automatic updates and verify your support.', 'slswcclient' ) ) . '</p>';

		} // license_activation_section_callback

		/**
		 * License key field callback
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function license_key_field() {
			$value = ( isset( $this->license_details['license_key'] ) ) ? $this->license_details['license_key'] : '';
			echo '<input type="text" id="license_key" name="' . esc_attr( $this->option_name ) . '[license_key]" value="' . esc_attr( trim( $value ) ) . '" />';

		} // license_key_field

		/**
		 * License acivated field
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function license_status_field() {

			$license_labels = SLSWC::license_status_types();

			echo esc_attr( $license_labels[ $this->license_details['license_status'] ] );

		} // license_status_field

		/**
		 * License acivated field
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function license_expires_field() {
			echo esc_attr( $this->license_details['license_expires'] );
		}

		/**
		 * License deactivate checkbox
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function license_deactivate_field() {

			echo '<input type="checkbox" id="deactivate_license" name="' . esc_attr( $this->option_name ) . '[deactivate_license]" />';

		} // license_deactivate_field

		/**
		 * The current server environment
		 *
		 * @return  void
		 * @since   1.0.0
		 * @version 1.0.0
		 */
		public function licence_environment_field() {
			echo '<input type="checkbox" id="environment" name="' . esc_attr( $this->option_name ) . '[environment]" />';
		}

		/**
		 * Validate the license key information sent from the form.
		 *
		 * @since 1.0.0
		 * @access public
		 * @param array $input the input passed from the request.
		 */
		public function validate_license( $input ) {

			$options = $this->license_details;
			$type    = null;
			$message = null;
			$expires = '';

			SLSWC::log( 'Validate license: ' . print_r( $input, true ) );
			$options += $input;

			foreach ( $options as $key => $value ) {

				if ( 'license_key' === $key ) {

					if ( 'active' === $this->get_license_status() ) {
						continue;
					}

					if ( ! array_key_exists( 'deactivate_license', $input ) || 'deactivated' !== $this->get_license_status() ) {

						$this->license_details['license_key'] = $input[ $key ];
						$response                             = $this->server_request( 'activate' );

						SLSWC::log( 'Activating. current status is: ' . $this->get_license_status() );
						SLSWC::log( $response );

						// phpcs:ignore
						if ( null !== $response ) {

							if ( SLSWC_Client_Manager::check_response_status( $response ) ) {

								$options[ $key ]            = $input[ $key ];
								$options['license_status']  = $response->status;
								$options['license_expires'] = $response->expires;

								if ( 'valid' === $response->status || 'active' === $response->status ) {
									$type    = 'updated';
									SLSWC::log('license activated!!!!!');
									$message = __( 'License activated.', 'slswcclient' );
								} else {
									$type     = 'error';
									$messages = SLSWC::license_status_types();
									$message  = $messages[ $response->status ];
								}
							} else {

								$type    = 'error';
								$message = __( 'Invalid License', 'slswcclient' );
							}

							SLSWC::log( $message );

							add_settings_error(
								$this->option_name,
								esc_attr( 'settings_updated' ),
								$message,
								$type
							);

							$options[ $key ] = $input[ $key ];
						}
					}

					$options[ $key ] = $input[ $key ];

				} elseif ( array_key_exists( $key, $input ) && 'deactivate_license' === $key ) {

					$response = $this->server_request( 'deactivate' );

					SLSWC::log( $response );

					if ( null !== $response ) {

						if ( SLSWC_Client_Manager::check_response_status( $response ) ) {
							$options[ $key ]            = $input[ $key ];
							$options['license_status']  = $response->status;
							$options['license_expires'] = $response->expires;
							$type                       = 'updated';
							$message                    = __( 'License Deactivated', 'slswcclient' );

						} else {

							$type    = 'updated';
							$message = __( 'Unable to deactivate license. Please deactivate on the store.', 'slswcclient' );

						}

						SLSWC::log( $message );

						add_settings_error(
							$this->option_name,
							esc_attr( 'settings_updated' ),
							$message,
							$type
						);
					}
				} elseif ( 'license_status' === $key ) {

					if ( empty( $options['license_status'] ) ) {
						$options['license_status'] = 'inactive';
					} else {
						$options['license_status'] = $options['license_status'];
					}
				} elseif ( 'license_expires' === $key ) {

					if ( empty( $options['license_expires'] ) ) {
						$options['license_expires'] = '';
					} else {
						$options['license_expires'] = gmdate( 'Y-m-d', strtotime( $options['license_expires'] ) );
					}
				} elseif ( 'environment' === $key ) {
					$options['environment'] = $input['environment'];
				}
			}

			SLSWC::log( $options );

			return $options;

		} // validate_license


		/**
		 * --------------------------------------------------------------------------
		 * Getters
		 * --------------------------------------------------------------------------
		 *
		 * Methods for getting object properties.
		 */

		/**
		 * Get the license status.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function get_license_status() {

			return $this->license_details['license_status'];

		} // get_license_status

		/**
		 * Get the license key
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function get_license_key() {

			return $this->license_details['license_key'];

		} // get_license_key


		/**
		 * Get the license expiry
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function get_license_expires() {

			return $this->license_details['license_expires'];

		} // get_license_expires

		/**
		 * Get theme or plugin information from file.
		 *
		 * @param   string $base_file - Plugin file or theme slug.
		 * @param   string $type - Product type. plugin|theme.
		 * @return  array
		 * @since   1.0.0
		 * @version 1.0.0
		 */
		public static function get_file_information( $base_file, $type = 'plugin' ) {
			$data = array();
			if ( 'plugin' === $type ) {
				if ( ! function_exists( 'get_plugin_data' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$plugin = get_plugin_data( $base_file, false );

				$data = array(
					'name'              => $plugin['Name'],
					'title'             => $plugin['Title'],
					'description'       => $plugin['Description'],
					'author'            => $plugin['Author'],
					'author_uri'        => $plugin['AuthorURI'],
					'version'           => $plugin['Version'],
					'plugin_url'        => $plugin['PluginURI'],
					'text_domain'       => $plugin['TextDomain'],
					'domain_path'       => $plugin['DomainPath'],
					'network'           => $plugin['Network'],

					// SLSWC Headers.
					'slswc'             => ! empty( $plugin['SLSWC'] ) ? $plugin['SLSWC'] : '',
					'slug'              => ! empty( $plugin['Slug'] ) ? $plugin['Slug'] : $plugin['TextDomain'],
					'required_wp'       => ! empty( $plugin['RequiredWP'] ) ? $plugin['RequiredWP'] : '',
					'compatible_to'     => ! empty( $plugin['CompatibleTo'] ) ? $plugin['CompatibleTo'] : '',
					'documentation_url' => ! empty( $plugin['DocumentationURL'] ) ? $plugin['DocumentationURL'] : '',
					'type'              => $type,
				);
			} elseif ( 'theme' === $type ) {
				if ( ! function_exists( 'wp_get_theme' ) ) {
					require_once ABSPATH . 'wp-includes/theme.php';
				}
				$theme = wp_get_theme( basename( $base_file ) );

				$data = array(
					'name'              => $theme->get( 'Name' ),
					'theme_url'         => $theme->get( 'ThemeURI' ),
					'description'       => $theme->get( 'Description' ),
					'author'            => $theme->get( 'Author' ),
					'author_uri'        => $theme->get( 'AuthorURI' ),
					'version'           => $theme->get( 'Version' ),
					'template'          => $theme->get( 'Template' ),
					'status'            => $theme->get( 'Status' ),
					'tags'              => $theme->get( 'Tags' ),
					'text_domain'       => $theme->get( 'TextDomain' ),
					'domain_path'       => $theme->get( 'DomainPath' ),
					// SLSWC Headers.
					'slswc'             => ! empty( $theme->get( 'SLSWC' ) ) ? $theme->get( 'SLSWC' ) : '',
					'slug'              => ! empty( $theme->get( 'Slug' ) ) ? $theme->get( 'Slug' ) : $theme->get( 'TextDomain' ),
					'required_wp'       => ! empty( $theme->get( 'RequiredWP' ) ) ? $theme->get( 'RequiredWP' ) : '',
					'compatible_to'     => ! empty( $theme->get( 'CompatibleTo' ) ) ? $theme->get( 'CompatibleTo' ) : '',
					'documentation_url' => ! empty( $theme->get( 'DocumentationURL' ) ) ? $theme->get( 'DocumentationURL' ) : '',
					'type'              => $type,
				);
			}

			return $data;

		}

		/**
		 * --------------------------------------------------------------------------
		 * Setters
		 * --------------------------------------------------------------------------
		 *
		 * Methods to set the object properties for this instance. This does not
		 * interact with the database.
		 */

		/**
		 * Set the license status
		 *
		 * @since 1.0.0
		 * @access public
		 * @param string $license_status license status.
		 */
		public function set_license_status( $license_status ) {

			$this->license_details['license_status'] = $license_status;

		} // set_license_status

		/**
		 * Set the license key
		 *
		 * @since 1.0.0
		 * @access public
		 * @param string $license_key License key.
		 */
		public function set_license_key( $license_key ) {

			$this->license_details['license_key'] = $license_key;

		} // set_license_key

		/**
		 * Set the license expires.
		 *
		 * @since 1.0.0
		 * @access public
		 * @param string $license_expires License expiry date.
		 */
		public function set_license_expires( $license_expires ) {

			$this->license_details['license_expires'] = $license_expires;

		} // set_license_expires

		/**
		 * Save the license details.
		 *
		 * @return void
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function save() {

			update_option( $this->option_name, $this->license_details );

		} // save

	} // SLSWC_Client

endif;



/**
 * Helper functions.
 */

if ( ! function_exists( 'recursive_parse_args' ) ) {
	/**
	 * Recursively merge two arrays.
	 *
	 * @param  array $args User defined args.
	 * @param  array $defaults Default args.
	 * @return array $new_args The two array merged into one.
	 */
	function recursive_parse_args( $args, $defaults ) {
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
}


