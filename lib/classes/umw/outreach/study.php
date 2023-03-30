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
			if ( is_admin() ) {
				add_action( 'admin_init', array( $this, 'do_upgrade' ) );
			}
		}

		/**
		 * Upgrade the information in the database
		 *
		 * @access public
		 * @return void
		 * @since  2023.03
		 */
		public function do_upgrade() {
			Base::log( 'Entered the do_upgrade method' );

			$upgraded = get_option( 'outreach-upgraded/study', 0 );

			if ( version_compare( $upgraded, $this->version, '>=' ) ) {
				Base::log( 'It appears we have already upgraded all posts on this site to version ' . $upgraded );
				return;
			}

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
				'posts_per_page' => -1,
				'meta_query' => array(
					array(
						'key' => 'wpcf-department',
						'compare' => 'EXISTS',
					),
				),
			) );

			Base::log( 'Retrieved ' . count( $posts ) . ' posts to evaluate' );

			foreach ( $posts as $post ) {

				$v = get_post_meta( $post->ID, 'outreach-upgraded', true );
				if ( version_compare( $v, $this->version, '>=' ) ) {
					/* We already performed the upgrade */
					Base::log( 'It appears that the information for post ' . $post->ID . ' has already been upgraded to ' . $this->version );
					continue;
				}

				foreach ( $meta as $key ) {
					switch ( $key ) {
						case 'department' :
						case 'courses' :
							$old = get_post_meta( $post->ID, 'wpcf-' . $key, true );
							if ( function_exists( 'update_field' ) ) {
								Base::log( 'Preparing to run update_field() on ' . $post->ID . ' for ' . $key );
								$new = update_field( $key, $old, $post->ID );
							} else {
								Base::log( 'Preparing to run update_post_meta() on ' . $post->ID . ' for ' . $key );
								$new = update_post_meta( $post->ID, $key, $old );
							}
							Base::log( 'Preparing to delete wpcf-' . $key . ' from the post meta for ' . $post->ID );
							delete_post_meta( $post->ID, 'wpcf-' . $key );
							break;
						default :
							Base::log( 'Preparing to delete wpcf-' . $key . ' from the post meta for ' . $post->ID );
							delete_post_meta( $post->ID, 'wpcf-' . $key );
							break;
					}
				}

				update_post_meta( $post->ID, 'outreach-upgraded', $this->version );
			}

			update_option( 'outreach-upgraded/study', $this->version );
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
