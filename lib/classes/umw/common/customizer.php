<?php
/**
 * Implements the Customizer settings for this plugin
 */

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		die( 'You do not have permission to access this file directly.' );
	}
}

namespace UMW\Common {
	if ( ! class_exists( 'Customizer' ) ) {
		class Customizer {
			/**
			 * @var Customizer $instance holds the single instance of this class
			 * @access private
			 */
			private static $instance;
			/**
			 * @var string $panel the handle of the Antioch Customizer panel
			 * @access public
			 */
			public $panel;

			/**
			 * Creates the Customizer object
			 *
			 * @access private
			 * @since  0.1
			 */
			private function __construct() {
				add_action( 'customize_register', array( $this, 'register' ) );
			}

			/**
			 * Returns the instance of this class.
			 *
			 * @access  public
			 * @return  Customizer
			 * @since   0.1
			 */
			public static function instance() {
				if ( ! isset( self::$instance ) ) {
					$className      = __CLASS__;
					self::$instance = new $className;
				}

				return self::$instance;
			}

			/**
			 * Register the Customizer panel
			 *
			 * @param \WP_Customize_Manager $wp_customize the main Customizer object
			 *
			 * @access public
			 * @return void
			 * @since  0.1
			 */
			public function register( \WP_Customize_Manager $wp_customize ) {
				$this->panel = $this->add_panel( $wp_customize );
				do_action( 'umw/customizer/register', $wp_customize, $this->panel );
			}

			/**
			 * Register the custom panel, if it doesn't already exist
			 *
			 * @param \WP_Customize_Manager $wp_customize the main Customizer object
			 *
			 * @access private
			 * @return string the handle of the registered panel
			 * @since  0.1
			 */
			private function add_panel( \WP_Customize_Manager $wp_customize ) {
				$panel = $wp_customize->get_panel( 'umw' );
				if ( is_a( $panel, '\WP_Customize_Panel' ) ) {
					return 'umw';
				}

				$wp_customize->add_panel( 'umw', array(
					'title' => __( 'UMW Settings', 'custom-search' ),
				) );

				return 'umw';
			}
		}
	}
}