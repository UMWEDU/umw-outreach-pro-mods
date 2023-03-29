<?php
/**
 * Special treatment for the Areas of Study site
 */

namespace UMW\Outreach;

if ( ! class_exists( 'Study' ) ) {
	class Study extends Base {
		/**
		 * @var int $blog the ID of the Study blog
		 */
		public $blog = 5;

		function __construct() {
			if ( defined( 'UMW_STUDY_SITE' ) && is_numeric( UMW_STUDY_SITE ) ) {
				$this->blog = UMW_STUDY_SITE;
			}

			parent::__construct();

			if ( intval( $this->blog ) !== intval( $GLOBALS['blog_id'] ) ) {
				return;
			}

			if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
			}

			/**
			 * Short-circuit this functionality if Types is still active on the site
			 */
			if ( is_plugin_active( 'types/wpcf.php' ) || is_plugin_active_for_network( 'types/wpcf.php' ) ) {
				return;
			}

			add_action( 'init', array( $this, 'load_types' ) );
			ACF_Setup::instance();
			add_action( 'widgets_init', array( $this, 'register_widgets' ) );
			$this->do_upgrade();
		}

		/**
		 * Upgrade the information in the database
		 *
		 * @access private
		 * @return void
		 * @since  2023.03
		 */
		private function do_upgrade() {
			$meta = array(
				'degree-awarded',
				'home-page-feature',
				'value-proposition',
				'areas-of-study',
				'career-opportunties',
				'internships',
				'honors',
				'minor-requirements',
				'major-requirements',
				'scholarships',
				'testimonial',
				'department',
				'courses',
				'example-schedule',
				'video',
			);

			$posts = get_posts( array(
				'post_type' => 'areas',
				'posts_per_page' => -1
			) );

			foreach ( $posts as $post ) {

				$v = get_post_meta( $post->ID, 'outreach-upgraded', true );
				if ( version_compare( $v, $this->version, '>=' ) ) {
					/* We already performed the upgrade */
					return;
				}

				foreach ( $meta as $key ) {
					Base::log( 'Preparing to delete ' . $key . ' from the post meta for ' . $post->ID );
					switch ( $key ) {
						case 'department' :
						case 'courses' :
							$old = get_post_meta( $post->ID, 'wpcf-' . $key, true );
							$new = update_post_meta( $post->ID, $key, $old );
						default :
							delete_post_meta( $post->ID, 'wpcf-' . $key );
							break;
					}
				}

				add_post_meta( $post->ID, 'outreach-upgraded', $this->version );
			}
		}

		/**
		 * Instantiate the custom post types & taxonomies
		 *
		 * @access public
		 * @return void
		 * @since  1.0
		 */
		public function load_types() {
			Post_Types\Areas::instance();
			foreach ( array( 'Department', 'Key' ) as $type ) {
				call_user_func( array( '\UMW\Outreach\Taxonomies\\' . $type, 'instance' ) );
			}
		}

		/**
		 * Instantiate our Areas of Study widgets
		 *
		 * @access public
		 * @return void
		 * @since  1.0
		 */
		public function register_widgets() {
			foreach ( array( 'Navigation', 'Contact' ) as $item ) {
				register_widget( '\UMW\Outreach\Widgets\Study_' . $item );
			}
		}
	}
}
