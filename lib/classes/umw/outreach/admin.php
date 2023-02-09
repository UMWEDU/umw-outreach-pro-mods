<?php

namespace UMW\Outreach;

use UMW\Outreach\Admin\Columns;
use UMW\Outreach\Admin\Metaboxes;
use UMW\Outreach\Base;

if ( ! class_exists( 'Admin' ) ) {
	class Admin {
		/**
		 * @var static Admin $instance
		 * @access private
		 */
		private static Admin $instance;
		/**
		 * @var bool $is_news
		 * @access private
		 */
		private bool $is_news = false;
		/**
		 * @var bool $is_events
		 * @access private
		 */
		private bool $is_events = false;

		/**
		 * Construct our Admin object
		 *
		 * @access private
		 * @since  0.1
		 */
		private function __construct() {
			add_action( 'wp_loaded', array( $this, 'init' ) );
		}

		/**
		 * Returns the instance of this class.
		 *
		 * @access  public
		 * @return  Admin
		 * @since   0.1
		 */
		public static function instance(): Admin {
			if ( ! isset( self::$instance ) ) {
				$className      = __CLASS__;
				self::$instance = new $className;
			}

			return self::$instance;
		}

		/**
		 * Instantiate necessary actions
		 *
		 * @access public
		 * @return void
		 * @since  2023.01
		 */
		public function init() {
			$this->is_events = ( defined( 'UMW_LOCALIST_VERSION' ) );
			$this->is_news   = is_a( $GLOBALS['umw_outreach_mods_obj'], 'UMW\Outreach\News' );

			if ( ! is_admin() || ( ! $this->is_events && ! $this->is_news ) ) {
				return;
			}

			Columns::instance();

			if ( $this->is_events ) {
				Metaboxes::instance();
			}
		}
	}
}