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

			if( defined( 'UMW_OUTREACH_ENABLE_SIDEBAR') ) {
				add_action( 'genesis_sidebar', 'genesis_do_sidebar', 11 );
			}
		}
	}
}
