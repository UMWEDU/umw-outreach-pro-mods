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
			if ( defined( 'UMW_OUTREACH_ENABLE_SIDEBAR' ) ) {
				add_action( 'genesis_sidebar', 'genesis_do_sidebar', 11 );
			}
		}
	}
}
