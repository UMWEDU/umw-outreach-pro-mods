<?php

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		die( 'You do not have permission to access this file directly.' );
	}
}

namespace UMW\Outreach {
	class ACF_Setup {
		/**
		 * @var ACF_Setup $instance holds the single instance of this class
		 * @access private
		 */
		private static ACF_Setup $instance;
		/**
		 * @var string $plugin_path the root path to this plugin
		 * @access public
		 */
		public static string $plugin_path = '';
		/**
		 * @var string $plugin_url the root URL to this plugin
		 * @access public
		 */
		public static string $plugin_url = '';

		/**
		 * Creates the ACF_Setup object
		 *
		 * @access private
		 * @since  0.1
		 */
		private function __construct() {
			/*Base::log( 'Instantiating the ACF_Setup class' );*/
			add_action( 'plugins_loaded', array( $this, 'setup_acf' ), 9 );
			add_filter( 'acf/settings/save_json', array( $this, 'acf_save_json_path' ) );
			add_filter( 'acf/settings/load_json', array( $this, 'acf_load_json_path' ) );
		}

		/**
		 * Returns the instance of this class.
		 *
		 * @access  public
		 * @return  ACF_Setup
		 * @since   0.1
		 */
		public static function instance(): ACF_Setup {
			if ( ! isset( self::$instance ) ) {
				$className      = __CLASS__;
				self::$instance = new $className;
			}

			return self::$instance;
		}

		/**
		 * Set the root path to this plugin
		 *
		 * @access public
		 * @return void
		 * @since  1.0
		 */
		public static function set_plugin_path() {
			self::$plugin_path = plugin_dir_path( dirname( __FILE__, 4 ) );
		}

		/**
		 * Set the root URL to this plugin
		 *
		 * @access public
		 * @return void
		 * @since  1.0
		 */
		public static function set_plugin_url() {
			self::$plugin_url = plugin_dir_url( dirname( __FILE__, 4 ) );
		}

		/**
		 * Returns an absolute path based on the relative path passed
		 *
		 * @param string $path the path relative to the root of this plugin
		 *
		 * @access public
		 * @return string the absolute path
		 * @since  1.0
		 */
		public static function plugin_dir_path( string $path = '' ): string {
			if ( empty( self::$plugin_path ) ) {
				self::set_plugin_path();
			}

			$rt = self::$plugin_path;

			if ( '/' === substr( $path, - 1 ) ) {
				$rt = untrailingslashit( $rt );
			}

			return $rt . $path;
		}

		/**
		 * Returns an absolute URL based on the relative path passed
		 *
		 * @param string $url the URL relative to the root of this plugin
		 *
		 * @access public
		 * @return string the absolute URL
		 * @since  1.0
		 */
		public static function plugin_dir_url( string $url = '' ): string {
			if ( empty( self::$plugin_url ) ) {
				self::set_plugin_url();
			}

			$rt = self::$plugin_url;

			if ( '/' === substr( $url, - 1 ) ) {
				$rt = untrailingslashit( $rt );
			}

			return $rt . $url;
		}

		/**
		 * Setup Advanced Custom Fields
		 *
		 * @access public
		 * @return void
		 * @since  0.1
		 */
		public function setup_acf() {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
			}

			if ( is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) || is_plugin_active_for_network( 'advanced-custom-fields-pro/acf.php' ) ) {
				add_filter( 'acf/settings/show_admin', '__return_true' );

				return;
			}

			if ( class_exists( '\ACF' ) ) {
				Base::log( 'The ACF class already exists, so we will avoid including the information again' );

				return;
			}

			add_filter( 'acf/settings/path', array( $this, 'acf_path' ) );
			add_filter( 'acf/settings/dir', array( $this, 'acf_url' ) );
			add_filter( 'acf/settings/show_admin', '__return_false' );
			Base::log( 'Preparing to include ACF core from ' . self::plugin_dir_path( '/lib/acf/acf/acf.php' ) );
			require_once( self::plugin_dir_path( '/lib/acf/acf/acf.php' ) );
		}

		/**
		 * Alter the ACF path
		 *
		 * @param string $path the current path
		 *
		 * @access public
		 * @return string the altered path
		 * @since  1.0
		 */
		public function acf_path( string $path = '' ): string {
			return self::plugin_dir_path( '/lib/acf/acf/' );
		}

		/**
		 * Alter the ACF URL
		 *
		 * @param string $url the current URL
		 *
		 * @access public
		 * @return string the updated URL
		 * @since  1.0
		 */
		public function acf_url( string $url = '' ): string {
			return self::plugin_dir_url( '/lib/acf/acf/' );
		}

		/**
		 * Save local JSON files for custom field definitions
		 *
		 * @param string $path the existing save-point
		 *
		 * @access public
		 * @return string the updated save point
		 * @since  1.0
		 */
		public function acf_save_json_path( string $path ) : string {
			Base::log( 'The JSON load point should be: ' . self::plugin_dir_path( '/lib/acf/acf-json/' ) );
			return self::plugin_dir_path( '/lib/acf/acf-json/' );
		}

		/**
		 * Load local JSON files for custom field definitions
		 *
		 * @param array $paths the existing array of JSON load points
		 *
		 * @access public
		 * @return array the updated array of paths
		 * @since  1.0
		 */
		public function acf_load_json_path( array $paths ): array {
			Base::log( 'The JSON load point should be: ' . self::plugin_dir_path( '/lib/acf/acf-json/' ) );
			unset( $paths[0] );
			$paths[] = self::plugin_dir_path( '/lib/acf/acf-json/' );

			return $paths;
		}
	}
}
