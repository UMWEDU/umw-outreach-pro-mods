<?php
/**
 * Special treatment for older sites that need the old-style sidebar & widget areas
 */

namespace UMW\Outreach;

if ( ! class_exists( 'Extra_Layouts' ) ) {
	class Extra_Layouts extends Base {
		function __construct() {
			parent::__construct();

			if ( ! defined( 'UMW_IS_ROOT' ) || is_numeric( UMW_IS_ROOT ) ) {
				return;
			}

			add_action( 'after_setup_theme', array( $this, 'reenable_sidebars' ), 12 );
		}

		/**
		 * Re-enable the primary sidebar after it's been disabled by the base class
		 *
		 * @access public
		 * @return void
		 * @since  2021.04
		 */
		public function reenable_sidebars() {
			if ( ! defined( 'UMW_OUTREACH_ENABLE_SIDEBAR' ) ) {
				return;
			}

			$this->register_sidebars();
			add_action( 'genesis_sidebar', 'genesis_do_sidebar', 11 );
			add_action( 'genesis_after_loop', array( $this, 'do_below_content_sidebars' ), 1 );
		}

		/**
		 * Register the old-style widget areas that are missing in this theme
		 *
		 * @access private
		 * @return void
		 * @since  2021.04
		 */
		private function register_sidebars() {
			genesis_register_sidebar( array(
				'id'   => 'below-content-1',
				'name' => __( 'Below Content 1', 'umw-outreach' )
			) );
			genesis_register_sidebar( array(
				'id'   => 'below-content-2',
				'name' => __( 'Below Content 2', 'umw-outreach' )
			) );
			genesis_register_sidebar( array(
				'id'   => 'below-content-3',
				'name' => __( 'Below Content 3', 'umw-outreach' )
			) );
		}

		/**
		 * Output the old-style widget areas below the content
		 *
		 * @access public
		 * @return void
		 * @since  2021.04
		 */
		public function do_below_content_sidebars() {
			if ( ! is_active_sidebar( 'below-content-1' ) && ! is_active_sidebar( 'below-content-2' ) && ! is_active_sidebar( 'below-content-3' ) ) {
				return;
			}

			$wrapper_class = '';

			if ( is_active_sidebar( 'below-content-1' ) && ! is_active_sidebar( 'below-content-2' ) && ! is_active_sidebar( 'below-content-3' ) ) {
				$wrapper_class = 'one-column';
			}

			if ( is_active_sidebar( 'below-content-1' ) && is_active_sidebar( 'below-content-2' ) && ! is_active_sidebar( 'below-content-3' ) ) {
				$wrapper_class = 'two-columns';
			}

			if ( is_active_sidebar( 'below-content-1' ) && is_active_sidebar( 'below-content-2' ) && is_active_sidebar( 'below-content-3' ) ) {
				$wrapper_class = 'three-columns';
			}

			echo '<div class="below-content-widgets">';
			echo '<div class="below-content-widget-wrapper ' . $wrapper_class . '">';
			for ( $i=1; $i<=3; $i++ ) {
				if ( is_active_sidebar( 'below-content-' . $i ) ) {
					echo '<div class="below-content-' . $i . '">';
					dynamic_sidebar( 'below-content-' . $i );
					echo '</div>';
				}
			}
			echo '</div>';
			echo '</div>';
		}
	}
}
