<?php

class SLSWC {

	private static $instance;

	/**
	 * Class constructor. Do nothing.
	 */
	public function __construct() {

	}

	/**
	 * Class init function. Hook needed actions and filters.
	 *
	 * @return void
	 */
	public static function init() {

		self::get_instance();

		/**
		 * Load the license manager class once all plugins are loaded.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		// Extra Plugin & Theme Header.
		add_filter( 'extra_plugin_headers', array( self::get_instance(), 'slswc_extra_headers') );
		add_filter( 'extra_theme_headers', array( self::get_instance(), 'slswc_extra_headers') );

		//load client manager
		add_action( 'admin_init', array( self::get_instance(), 'slswc_client_manager' ), 12 );

		// add_action( 'after_setup_theme', array( self::get_instance(), 'slswc_client_manager') );
		add_action( 'admin_footer', array( self::get_instance(), 'slswc_client_admin_script'), 11 );

	}

	/**
	 * Add extra theme headers.
	 *
	 * @param   array $headers The extra theme/plugin headers.
	 * @return  array
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	function slswc_extra_headers( $headers ) {

		if ( ! in_array( 'SLSWC', $headers, true ) ) {
			$headers[] = 'SLSWC';
		}

		if ( ! in_array( 'Updated', $headers, true ) ) {
			$headers[] = 'Updated';
		}

		if ( ! in_array( 'Author', $headers, true ) ) {
			$headers[] = 'Author';
		}

		if ( ! in_array( 'Slug', $headers, true ) ) {
			$headers[] = 'Slug';
		}

		if ( ! in_array( 'Required WP', $headers, true ) ) {
			$headers[] = 'Required WP';
		}

		if ( ! in_array( 'Compatible To', $headers, true ) ) {
			$headers[] = 'Compatible To';
		}

		if ( ! in_array( 'Documentation URL', $headers, true ) ) {
			$headers[] = 'Documentation URL';
		}

		return $headers;
	}


	/**
	 * Print admin script for SLSWC Client.
	 *
	 * @return void
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function slswc_client_admin_script() {

		global $slswc_license_server_url, $slswc_slug, $slswc_text_domain, $slswc_products;

		$screen = get_current_screen();
		if ( 'plugins' !== $screen->id ) {
			return;
		}

		?>
		<script type="text/javascript">
			jQuery( function( $ ){
				$( document ).ready( function() {
					var products = '<?php echo wp_json_encode( $slswc_products ); ?>';
					var $products = $.parseJSON(products);
					if( $( document ).find( '#plugin-information' ) && window.frameElement ) {
						var src = window.frameElement.src;
						<?php
						foreach ( $slswc_products as $slug => $details ) :
							if ( ! is_array( $details ) || array_key_exists( 'slug', $details ) ) {
								continue;
							}
							?>
						if ( undefined != '<?php echo esc_attr( $slug ); ?>' && src.includes( '<?php echo esc_attr( $slug ); ?>' ) ) {
							<?php $url = esc_url_raw( $details['license_server_url'] ) . 'products/' . esc_attr( $slug ) . '/#reviews'; ?>
							$( '#plugin-information' ).find( '.fyi-description' ).html( '<?php echo sprintf( __( 'To read all the reviews or write your own visit the <a href="%s">product page</a>.', 'slswcclient' ), $url ); ?>');
							$( '#plugin-information' ).find( '.counter-label a' ).each( function() {
								$(this).attr( 'href', '<?php echo esc_attr( $url ); ?>' );
							} );
						}
						<?php endforeach; ?>
					}
				} );
			} );
		</script>
		<?php
	}

	/**
	 * The available license status types
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 * @access  public
	 */
	public static function license_status_types() {

		return apply_filters(
			'slswc_license_status_types',
			array(
				'valid'           => __( 'Valid', 'slswcclient' ),
				'deactivated'     => __( 'Deactivated', 'slswcclient' ),
				'max_activations' => __( 'Max Activations reached', 'slswcclient' ),
				'invalid'         => __( 'Invalid', 'slswcclient' ),
				'inactive'        => __( 'Inactive', 'slswcclient' ),
				'active'          => __( 'Active', 'slswcclient' ),
				'expiring'        => __( 'Expiring', 'slswcclient' ),
				'expired'         => __( 'Expired', 'slswcclient' ),
			)
		);
	}

	/**
		 * Class logger so that we can keep our debug and logging information cleaner
		 *
		 * @since 1.0.0
		 * @access public
		 * @param mixed $data - The data to go to the error log.
		 */
		public static function log( $data ) {
			$logging_enabled = defined( 'SLSWC_CLIENT_LOGGING' ) && SLSWC_CLIENT_LOGGING ? true : false;
			if ( ! apply_filters( 'slswc_client_logging', $logging_enabled ) ) {
				return;
			}
			//phpcs:disable
			if ( is_array( $data ) || is_object( $data ) ) {
				error_log( __CLASS__ . ' : ' . print_r( $data, true ) );
			} else {
				error_log( __CLASS__ . ' : ' . $data );
			}
			//phpcs:enable

		} // log

	/**
	 * Load the license client manager.
	 *
	 * @return  SLSWC_Client_Manager Instance of the client manager
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function slswc_client_manager() {
		global $slswc_license_server_url, $slswc_slug, $slswc_text_domain;
		return SLSWC_Client_Manager::get_instance( $slswc_license_server_url, $slswc_slug, $slswc_text_domain );
	}


	/**
	 * Returns the single instance of the SLSWC object
	 *
	 * @return SLSWC
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

