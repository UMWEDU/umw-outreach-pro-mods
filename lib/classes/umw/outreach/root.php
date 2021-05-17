<?php
/**
 * Sets up the root site class for any UMW Outreach Modifications
 * @package UMW Outreach Customizations
 * @version 1.0.17
 */

namespace UMW\Outreach;

if ( ! class_exists( 'Root' ) ) {
	/**
	 * Define the class used to manage the global header & footer
	 */
	class Root extends Base {
		var $dbversion = '20150522/090000';

		/**
		 * Build our UMW_Outreach_Mods object
		 * This class is used only on the root site of the entire system
		 */
		function __construct() {
			parent::__construct();

			$dbv = get_option( 'umw-outreach-mods-version', false );
			if ( $dbv != $this->dbversion ) {
				add_action( 'init', array( $this, 'flush_rules' ) );
			}

			add_action( 'genesis_setup', array( $this, 'register_sidebars' ) );

			add_action( 'umw-header-logo', array( $this, 'get_logo' ) );
			add_action( 'init', array( $this, 'add_feed' ) );
			add_action( 'plugins_loaded', array( $this, 'use_plugins' ), 55 );
			add_filter( 'feed_content_type', array( $this, 'fake_query' ), 10, 2 );

			$this->shortcodes_to_unregister = apply_filters( 'umw-global-header-footer-shortcodes-to-unregister', array(
				'current-url',
				'current-date',
			) );

			add_filter( 'body_class', array( $this, 'add_root_body_class' ) );

			add_action( 'genesis_loop', array( $this, 'do_home_page_content' ), 9 );

			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_root_styles' ) );
		}

		public function enqueue_root_styles() {
			wp_enqueue_style( 'home-slider', parent::plugin_dir_url( 'lib/styles/umw-news-slideshow.css' ), array(), $this->version, 'all' );
		}

		function fake_query( $content_type, $type ) {
			if ( 'umw-global-header' == $type || 'umw-global-footer' == $type ) {
				add_action( 'template_redirect', array( $this, 'fix_fake_query' ) );

				return 'text/plain';
			}

			return $content_type;
		}

		function fix_fake_query() {
			global $wp_query;
			$wp_query->is_archive = true;
		}

		function do_home_page_content() {
			if ( ! is_front_page() || ! is_main_query() || ( ! is_active_sidebar( 'home-top' ) && ! is_active_sidebar( 'home-bottom' ) ) ) {
				return;
			}

			if ( have_posts() ) : while ( have_posts() ) : the_post();
				$tmp = get_the_content();
				if ( empty( $tmp ) ) {
					continue;
				}

				echo '<section class="home-page-main-content">';
				the_content();
				echo '</section>';
			endwhile; endif;
		}

		/**
		 * Add a special class to the root site in the system in case
		 *        we need to apply special CSS or JS for the root
		 */
		function add_root_body_class( $classes = array() ) {
			$classes[] = 'umw-is-root';

			return $classes;
		}

		/**
		 * This is purely for debugging purposes, and will be removed
		 */
		function test_cascade() {
			print( "\n<!-- This is the root class -->\n" );
		}

		/**
		 * Register the feeds that hold the contents of the global header and footer
		 */
		function add_feed() {
			add_feed( 'umw-global-header', array( $this, 'get_header_for_feed' ) );
			add_feed( 'umw-global-footer', array( $this, 'get_footer_for_feed' ) );
		}

		/**
		 * Flush any rewrite rules, if necessary, to get our feed working
		 */
		function flush_rules() {
			global $wp_rewrite;
			if ( is_object( $wp_rewrite ) ) {
				$wp_rewrite->flush_rules();
				update_option( 'umw-outreach-mods-version', $this->dbversion );
			}
		}

		/**
		 * Adjust anything that Genesis or Outreach does that's not necessary for us
		 */
		function genesis_tweaks() {
			parent::genesis_tweaks();
			add_action( 'umw-footer', array( $this, 'do_footer_primary' ) );
			add_action( 'umw-footer', array( $this, 'do_footer_top' ) );
			add_action( 'umw-footer', array( $this, 'do_footer_secondary' ) );
			/**
			 * Adjust the logo properties for staging/dev sites
			 */
			add_filter( 'umw-header-logo-info', array( $this, 'get_correct_logo_info' ) );
			/**
			 * Register the header-right navigation menu
			 */
			register_nav_menu( 'header-right', __( 'Header Menu', 'umw-outreach-mods' ) );
			add_action( 'genesis_header_right', array( $this, 'do_header_right' ) );
			add_action( 'umw-header-right', array( $this, 'do_header_right' ) );
		}

		/**
		 * If we're using a URL other than umw.edu as the root site,
		 *        we'll adjust some elements of the logo to reflect that
		 */
		function get_correct_logo_info( $info = array() ) {
			$host = $_SERVER['HTTP_HOST'];
			if ( stristr( $host, 'wpengine.com' ) ) {
				if ( stristr( $host, 'staging' ) ) {
					$info['link'] = esc_url( '//umwwebmaster.staging.wpengine.com/' );
				} else {
					$info['link'] = esc_url( '//umwwebmaster.wpengine.com/' );
				}
			} else {
				$tmp = explode( '.', $host );
				if ( count( $tmp ) > 2 ) {
					$tmp = array_slice( $tmp, - 2, 2 );
				}

				$info['link'] = esc_url( sprintf( '//www.%s', implode( '.', $tmp ) ) );
			}

			return $info;
		}

		/**
		 * Make sure any plugins that are necessary for the global
		 *        header/footer are set up and ready to use
		 */
		function use_plugins() {
			if ( is_admin() ) {
				return;
			}

			if ( isset( $GLOBALS['umw_online_tools_obj'] ) ) {
				global $umw_online_tools_obj;
				$umw_online_tools_obj->enqueue_styles();
				/*add_action( 'umw-above-header', array( $umw_online_tools_obj, 'do_toolbar', 1 ) );
				add_action( 'umw-above-header', array( $umw_online_tools_obj, 'do_header_bar', 5 ) );*/
				/*} else {
					print( "\n<!-- The umw_online_tools_obj object doesn't seem to exist -->\n" );*/
			}
		}

		/**
		 * Register any additional widget areas that are necessary for the global header/footer
		 */
		function register_sidebars() {
			genesis_register_sidebar( array(
				'id'   => 'global-footer-top',
				'name' => __( 'Global Footer Top' )
			) );
			genesis_register_sidebar( array(
				'id'   => 'global-footer-1',
				'name' => __( 'Global Footer 1' ),
			) );
			genesis_register_sidebar( array(
				'id'   => 'global-footer-2',
				'name' => __( 'Global Footer 2' ),
			) );
			genesis_register_sidebar( array(
				'id'   => 'global-footer-3',
				'name' => __( 'Global Footer 3' ),
			) );
			genesis_register_sidebar( array(
				'id'   => 'global-footer-4',
				'name' => __( 'Global Footer 4' ),
			) );
			genesis_register_sidebar( array(
				'id'   => 'global-footer-bottom-1',
				'name' => __( 'Global Footer Bottom 1' ),
			) );
			genesis_register_sidebar( array(
				'id'   => 'global-footer-bottom-2',
				'name' => __( 'Global Footer Bottom 2' ),
			) );
		}

		/**
		 * Handle the header-right navigation menu
		 */
		function do_header_right() {
			wp_nav_menu( array(
				'theme_location' => 'header-right',
				'container'      => 'nav',
				'fallback_cb'    => false,
			) );
		}

		/**
		 * Output the global UMW header
		 */
		function do_full_header() {
			do_action( 'umw-above-header' );
			print( '<header class="site-header umw-global-header"><div class="wrap">' );
			do_action( 'umw-header-logo' );
			if ( has_action( 'umw-header-right' ) ) {
				print( '<aside class="widget-area header-widget-area">' );
				do_action( 'umw-header-right' );
				print( '</aside>' );
			}
			print( '</div></header>' );
			do_action( 'umw-below-header' );
		}

		/**
		 * Output the global UMW footer
		 */
		function do_full_footer() {
			do_action( 'umw-above-footer' );
			print( '<footer class="site-footer umw-global-footer"><div class="wrap">' );
			do_action( 'umw-footer' );
			print( '</div></footer>' );
			do_action( 'umw-below-footer' );
		}

		/**
		 * Retrieve the SVG logo used in the header
		 */
		function get_logo() {
			$logo_info = apply_filters( 'umw-header-logo-info', array(
				'fallback'     => $this->plugins_url( '/lib/images/umw-primary-logo-white.png' ),
				'fallback_alt' => __( 'University of Mary Washington' ),
				'link'         => 'http://www.umw.edu/',
				'link_title'   => __( 'Return to the University of Mary Washington home page' ),
				'logo_id'      => 'umw-full-logo-img',
				'logo_class'   => 'umw-full-logo-img',
			) );
			?>
            <div class="umw-logo-block"><a href="<?php echo $logo_info['link'] ?>"
                                           title="<?php echo $logo_info['link_title'] ?>">
                    <svg id="<?php echo $logo_info['logo_id'] ?>" class="<?php echo $logo_info['logo_class'] ?>" version="1.1" xmlns="http://www.w3.org/2000/svg" x="0" y="0" viewBox="0 0 270.4 69.4"
                         xml:space="preserve"><title><?php _e( 'University of Mary Washington' ) ?></title><style>.st0{fill:#fff}</style>
                        <path class="st0"
                              d="M0 1.6h11.3v1.2c-2.9.1-3.6.4-3.7 3.2v12.6c0 1.5 0 3 .5 4.2 1 2.6 3.8 4 7 4 2.5 0 4.8-.9 6.1-2.3 1.8-1.8 1.8-4 1.8-6.9V7c-.1-3.3-.5-3.9-3.6-4.1V1.6h8.9v1.2c-2.8.4-3.6 1.1-3.7 4.3v10.7c-.1 3.9-.2 7-4 9.2-2 1.2-4 1.5-6.2 1.5-3.1 0-6.3-.6-8.5-2.8-2-2-2.1-3.9-2.2-6V6.3c0-1.4 0-2-.5-2.6-.6-.8-1.5-.8-3.2-.9V1.6z"/>
                        <path class="st0"
                              d="M26.4 12.6c2.7-.4 3.3-.6 5.2-1.4.4 1 .4 1.3.6 2.4 1.4-1.1 3.3-2.5 6.2-2.5 1.1 0 2.4.2 3.5 1.2 1.3 1.2 1.3 2.6 1.4 3.8v9.2c0 1.2.5 1.6 2.6 1.6V28h-8.5v-1.1c2-.2 2.6-.3 2.6-1.6v-8.4c0-.8-.1-1.8-.6-2.6-.4-.6-1.3-1.3-2.8-1.3-2.2 0-3.5 1.2-4.3 2v10.1c0 1.4.7 1.6 2.7 1.7v1.1h-8.5v-1.1c2.3 0 2.6-.6 2.6-1.8v-9.8c0-1.5-1-1.5-2.8-1.5l.1-1.1zM46.9 12.6c3-.3 3.6-.5 6.1-1.5v13.7c0 1.6.4 1.8 2.6 1.9v1.1h-8.5v-1.2c2.1-.1 2.6-.4 2.6-1.5v-9.5c0-1.8-.8-1.9-2.8-1.9v-1.1zM49.2 6.3c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2c-1.1-.1-2-1-2-2M54.7 11.5H62v1.1c-.7 0-1.6.1-1.6.9 0 .3.1.6.3.8l4.1 9.3 4.2-9.4c.2-.4.3-.6.3-.8 0-.7-.8-.8-1.8-.9v-1.1h5.7v1.1c-1.9.2-2 .5-2.6 1.6l-6.3 13.7h-1L57 14.2c-.7-1.3-1.1-1.6-2.4-1.6l.1-1.1zM83.4 17.7c0-1.4-.1-2.6-.6-3.5-.6-1.2-1.8-1.7-3.1-1.7-3.4 0-3.8 3.4-4.1 5.2h7.8zm3.3 6.7c-1.1 1.6-2.6 4-6.6 4-1.1 0-3.5-.1-5.6-2.1s-2.4-4.5-2.4-6.3c0-5.5 3.5-8.9 7.9-8.9 1.8 0 3.6.7 4.9 2 1.9 2 2 4.3 2.1 6H75.7c0 1.7 0 3.5.7 5 .8 1.7 2.3 2.8 4.4 2.8 2.6 0 3.9-1.5 5-3l.9.5zM87.9 12.6c2.7-.4 3.3-.6 5.3-1.5.4 1.2.4 1.6.6 3 1.1-1.2 2.5-2.8 4.7-2.8 2 0 2.3 1.5 2.3 2 0 .8-.5 1.6-1.4 1.6-.3 0-.6-.1-.8-.3-.2-.2-.3-.4-.4-.6-.2-.3-.6-.6-1.3-.6-1.3 0-2.3 1.2-2.9 2V25c0 1.4.4 1.6 3.1 1.8v1.1h-9v-1.1c2-.1 2.5-.4 2.6-1.5v-9.6c0-1.9-.7-1.9-2.8-2v-1.1zM103.3 22.2c.2 1 .6 2.6 2 3.7.7.6 1.8.9 2.8.9 1.7 0 2.8-1.1 2.8-2.5 0-1.2-.8-1.8-1.1-2-.4-.3-.7-.5-3.1-1.7-2.4-1.2-4.2-2.4-4.2-5 0-2.8 1.9-4.5 4.5-4.5 2 0 3.1 1 3.9 1.8.3-.5.4-.7.7-1.4h.9l.1 5.4h-.9c-.3-1.2-.6-2.2-1.6-3.2-.8-.8-1.7-1.2-2.7-1.2-1.3 0-2.4.8-2.4 2.2 0 1.3.9 2 2.6 2.8.6.3 1.2.6 1.8.8 1.9.9 4 2.1 4 5.1 0 2.8-1.8 4.9-4.8 4.9-2.2 0-3.6-1.1-4.6-1.8-.4.7-.5.9-.7 1.3h-.9v-5.7l.9.1zM115 12.6c3-.3 3.6-.5 6.1-1.5v13.7c0 1.6.4 1.8 2.6 1.9v1.1h-8.5v-1.2c2.1-.1 2.6-.4 2.6-1.5v-9.5c0-1.8-.9-1.9-2.8-1.9v-1.1zM117.4 6.3c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2c-1.1-.1-2-1-2-2M124.3 11.9c1-.3 2.6-.7 3.4-3.3.3-1 .4-2 .5-2.9h1.3v5.9h4.4v1.2h-4.4v10.4c0 .5 0 .9.1 1.4.3 1 .9 1.6 1.9 1.6 1.3 0 2.1-1 2.7-1.8l.8.6c-1 1.3-2.3 3-4.9 3-1 0-1.9-.3-2.6-.9-1.1-1-1.3-2.8-1.3-4l.1-10.4h-2.1l.1-.8z"/>
                        <path class="st0"
                              d="M133.6 11.5h7.6v1.2c-.7 0-2 .2-1.7.9.1.3.3.7.3.7l4.1 9.1 4.2-9.2s.2-.5.2-.6c.2-.7-1.2-.9-1.8-.9v-1.2h5.7v1.2c-1.9.2-2.2.5-2.9 2l-6.6 14.7c-.3.6-.6 1.2-.8 1.8-2.4 5.2-3.5 7.5-5.8 7.5-1.1 0-1.9-.6-1.9-1.5 0-.3.1-.6.3-.9h.5c1.8 0 3.2-1.4 4.2-3 .9-1.5 2-3.9 3-6L136.1 14c-.7-1.3-1.3-1.3-2.4-1.3l-.1-1.2zM163.5 14.7c-1.3 2.5-2.5 7.2-2.5 9.5 0 .8.1 1.8.6 2.3.1.1.5.6 1.1.6.9 0 1.7-.7 2.3-1.6 1.8-2.4 3.5-8.7 3.5-11 0-.4 0-.9-.2-1.4-.3-.7-1-1.2-1.6-1.2-.8 0-2.1.7-3.2 2.8m-.4-3c1.3-1 2.8-1.2 3.6-1.2 2.9 0 4.5 2.5 4.5 6 0 3.4-1.5 6.9-3 8.8-1.4 1.8-3.3 3.1-5.5 3.1-2.1 0-4.4-1.8-4.4-6.2 0-3.5 1.6-8.1 4.8-10.5"/>
                        <path class="st0"
                              d="M180.1 11.3h3.8l-.4 1.3h-3.7l-4.2 15.3c-1.1 3.6-2 6.7-3.6 8.6-1.7 2.1-3.5 2.2-4.2 2.2-2.2 0-3-1.7-3-2.7 0-.9.6-1.9 1.8-1.9.6 0 1.4.3 1.4 1.2 0 .3-.1.6-.2.8-.1.3-.3.5-.3.8 0 .1 0 .6.6.6 1.9 0 3-3.9 3.5-6l5-18.7h-4.1l.5-1.3h4c1-3.4 1.7-5.6 3-7.5 2.3-3.3 5-3.8 6.2-3.8 2 0 3.8 1.1 3.8 2.6 0 .9-.7 1.6-1.5 1.6-1.3 0-1.6-1.3-1.8-1.6-.2-.6-.3-1-1.1-1s-1.6.5-2.2 1c-1 1-1.3 2.3-1.9 4.2l-1.4 4.3zM25.4 32.4h7.8v1.3c-1.8.1-2.2.1-2.7.4-1.2.6-1.2 2-1.1 2.9l.2 16.7c0 1.1 0 2 .3 2.6.5 1 1.2 1 3.5 1.1v1.2H21.7v-1.2c1.9-.1 2.3-.1 2.9-.4 1-.6 1.1-1.7 1.1-2.9V40.9c0-2.4 0-3.1.2-5.7-.4 1.1-.6 1.8-1.2 3.2l-8.4 20.2H15L6.8 39.1c-.6-1.6-.7-1.8-1.3-3.5-.1 3.6-.1 7.3-.1 10.9 0 3.3 0 5 .1 7.2.1 2.3.4 3.5 3.9 3.8v1.2H0v-1.2c3.2-.4 3.6-1.2 3.8-3.8L4 37.4c0-3.3-.6-3.7-3.8-3.8v-1.2h7.9l6.3 15c1 2.7 1.3 3.4 2.3 6.1l8.7-21.1zM44.5 49.4c-.7.3-1.5.6-2.3.8-.8.3-1.7.4-2.4.9-1.4.9-1.5 2.1-1.5 2.7 0 1.9 1.3 2.7 2.7 2.7 1.6 0 2.8-.9 3.6-1.6l-.1-5.5zm0-3c0-.5 0-1-.2-1.5-.5-1.7-2.2-1.8-2.9-1.8-.4 0-2.1 0-2.6 1.8-.1.4-.1.9-.3 1.3-.3.8-1.2.8-1.5.8-.3 0-1.8 0-1.8-1.4 0-.9 1.4-3.7 6.3-3.7 4.1 0 6.1 1.9 6.1 4.2v9.4c0 .6.1 1.6 1.1 1.6 1.2 0 1.5-1.3 1.7-2h1.2c-.3 1.3-1 3.8-3.9 3.8-1.2 0-1.8-.4-2.2-.7-.8-.7-.9-1.4-1-2.1-.8.8-2.9 2.8-5.5 2.8-2.3 0-4.2-1.3-4.2-4.2 0-3.2 1.9-4.4 4.8-5.1 1-.3 2-.4 3-.7l1.8-.6.1-1.9zM51.9 43.4c2.7-.4 3.3-.6 5.3-1.5.4 1.2.4 1.6.6 3.1 1.1-1.2 2.5-2.8 4.7-2.8 2 0 2.3 1.5 2.3 2 0 .8-.5 1.6-1.4 1.6-.3 0-.6-.1-.8-.3-.2-.2-.3-.4-.4-.6-.2-.3-.6-.6-1.3-.6-1.3 0-2.3 1.2-2.9 2v9.6c0 1.4.4 1.6 3.1 1.8v1.1h-9v-1.1c2-.1 2.5-.4 2.6-1.5v-9.6c0-1.9-.7-1.9-2.8-2v-1.2zM65.7 42.3h7.5v1.1c-.7 0-1.7.1-1.7.9 0 .2.1.4.3.9l4.1 9.1 4.2-9.2c.2-.6.3-.7.3-.8 0-.8-1.2-.8-1.8-.8v-1.1h5.7v1.1c-1.9.2-2.1.6-2.9 2.1l-6.6 14.7c-.3.6-.6 1.2-.8 1.8-2.4 5.2-3.5 7.5-5.8 7.5-1.1 0-1.9-.6-1.9-1.5 0-.3.2-.6.3-.9h.5c1.8 0 3.2-1.4 4.2-3 .9-1.5 2-3.9 3.1-6l-6.2-13.3c-.7-1.3-1.4-1.4-2.4-1.4l-.1-1.2zM90 32.4h10.5v1.2c-.4 0-1.4.1-1.8.2-.3.1-1.2.3-1.2 1.3 0 .3.1.6.3 1.3l3.1 10.8c.7 2.6.9 3.2 1.5 5.9l6.4-20.7h1.2l5.2 15.2c.8 2.4 1 3 1.7 5.4.3-1.4.4-1.8.8-3.2l3.8-13c.1-.6.3-1.1.3-1.6 0-1.5-1.5-1.6-3.5-1.6v-1.2h9v1.2c-2.7.2-3.4.5-4.2 3L116.5 59h-1.4l-6.9-19.9-6.1 19.9h-1.5l-6.8-22.4c-.7-2-1.1-2.8-3.8-3.2v-1zM132.7 49.4c-.7.3-1.5.6-2.3.8-.9.3-1.7.4-2.5.9-1.4.9-1.5 2.1-1.5 2.7 0 1.9 1.3 2.7 2.7 2.7 1.6 0 2.8-.9 3.6-1.6v-5.5zm0-3c0-.5 0-1-.1-1.5-.5-1.7-2.2-1.8-2.9-1.8-.4 0-2.1 0-2.6 1.8-.1.4-.1.9-.3 1.3-.4.8-1.3.8-1.6.8-.3 0-1.8 0-1.8-1.4 0-.9 1.4-3.7 6.4-3.7 4.1 0 6.1 1.9 6.1 4.2v9.4c.1.5.1 1.5 1.1 1.5 1.2 0 1.5-1.3 1.7-2h1.1c-.3 1.3-1 3.8-3.9 3.8-1.2 0-1.8-.4-2.2-.7-.8-.7-.9-1.4-1-2.1-.9.8-2.9 2.8-5.5 2.8-2.3 0-4.2-1.3-4.2-4.2 0-3.2 1.9-4.4 4.8-5.1 1-.3 2-.4 3-.7l1.8-.6v-1.8h.1zM141.9 52.9c.2 1 .6 2.6 2 3.7.7.6 1.8.9 2.8.9 1.7 0 2.8-1.1 2.8-2.4 0-1.2-.8-1.8-1.1-2-.4-.3-.7-.5-3.1-1.7-2.4-1.2-4.2-2.4-4.2-5.1 0-2.8 1.9-4.5 4.5-4.5 2 0 3 1 3.9 1.8.3-.5.4-.7.7-1.4h.9l.1 5.4h-.9c-.3-1.2-.6-2.2-1.6-3.2-.8-.8-1.7-1.2-2.7-1.2-1.3 0-2.4.8-2.4 2.2 0 1.3.9 2 2.6 2.8.6.3 1.2.6 1.8.8 1.9.9 4 2.1 4 5.1 0 2.8-1.8 4.9-4.8 4.9-2.2 0-3.6-1.1-4.6-1.8-.4.7-.5.9-.7 1.3h-.9v-5.7l.9.1zM152.6 32.6c2.9-.2 3.6-.3 6.1-1.2v12.8c1.3-1 3.1-2.2 5.8-2.2 2.3 0 3.8 1 4.4 2 .7 1 .7 2.4.7 3.9V56c0 1.4.8 1.5 2.6 1.6v1.1h-8.5v-1.1c.9-.1 1.4-.2 1.6-.2 1-.3 1-.9 1-1.3v-7.7c0-1.3 0-2.2-.3-2.9-.4-.8-1.3-1.6-3-1.6-.7 0-1.5.2-2.3.4-1 .4-1.6.9-2 1.4v10.2c.1 1.5.6 1.6 2.6 1.7v1.1h-8.5v-1.1c1.9-.2 2.6-.2 2.6-1.9V35.4c0-1.7-.8-1.7-2.8-1.8v-1zM173.4 43.4c3-.3 3.6-.4 6.1-1.4v13.7c0 1.6.4 1.8 2.6 1.9v1.1h-8.5v-1.2c2.1-.1 2.6-.4 2.6-1.5v-9.6c0-1.8-.9-1.9-2.8-1.9v-1.1zM175.8 37.1c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2c-1.2 0-2-.9-2-2M183.1 43.4c2.7-.4 3.3-.6 5.2-1.4.4 1 .4 1.3.6 2.4 1.4-1.1 3.3-2.5 6.2-2.5 1.1 0 2.4.2 3.5 1.2 1.3 1.2 1.3 2.6 1.4 3.8V56c0 1.2.5 1.6 2.6 1.6v1.1h-8.5v-1.1c2-.2 2.6-.3 2.6-1.6v-8.4c0-.8-.1-1.8-.6-2.6-.4-.6-1.3-1.3-2.8-1.3-2.1 0-3.4 1.2-4.3 2v10.1c0 1.4.7 1.6 2.7 1.7v1.1h-8.5v-1.1c2.3 0 2.6-.6 2.6-1.8V46c0-1.5-1-1.5-2.8-1.5l.1-1.1zM207.6 48.1c0 1.1 0 4.6 3.2 4.6s3.2-4.3 3.2-5.2c0-.8 0-4.5-3.2-4.5-2.1-.1-3.2 1.6-3.2 5.1m11.8-6.2c1.4 0 1.9.9 1.9 1.6 0 .5-.2 1.4-1.4 1.4-.5 0-.8-.2-1-.5-.4-.5-.5-.6-1-.6-.6 0-1.1.3-1.5.7.4.8.9 1.8.9 3.4 0 3.2-2 4.9-4.4 5.4-1.2.3-2.5.3-3.7.5-2.4.4-2.7 1.2-2.7 1.5 0 .7.6.7 2.8.7 5.9.2 8.1.3 9.6 2.2.8 1.1 1 2.2 1 3.1 0 4.1-3.1 7.6-8.7 7.6-4.3 0-8.2-2.1-8.2-5.5 0-1.8 1.1-2.9 2.3-3.3 1.1-.5 3-.7 3-.6 0 0-.1.1-.3.2-.3.2-2.3 1.2-2.3 3.5 0 2.7 2.4 4.2 5.5 4.2 2.9 0 6-1.4 6-4.2 0-2.9-2.8-3.2-4.7-3.3-3.4-.3-4.2-.4-5.3-.7-1.6-.4-2.7-1.4-2.7-3.1 0-2 2-2.6 3.3-2.9-1.3-.6-3.3-1.6-3.3-5 0-3.8 2.8-6 6.3-6 2.9 0 4.3 1.3 5 1.9 1-1.1 2.2-2.2 3.6-2.2M221.8 42.6c1-.3 2.6-.7 3.4-3.3.3-1 .4-2 .5-2.9h1.3v5.9h4.4v1.2H227v10.4c0 .5 0 .9.1 1.4.3 1 .9 1.6 1.9 1.6 1.3 0 2.1-1 2.7-1.8l.8.7c-1 1.3-2.3 3-4.9 3-1 0-1.9-.3-2.6-.9-1.1-1-1.3-2.8-1.3-4l.1-10.4h-2.1l.1-.9zM236.6 50.7c0 1.3 0 7.2 5 7.2 4.1 0 4.9-4.1 4.9-7.4 0-4.5-1.2-6.2-2.8-7-.6-.3-1.4-.5-2.1-.5-4.2 0-5 4.2-5 7.7m13.5-.3c0 1-.1 4-2.4 6.4-1.9 1.9-4.1 2.3-6.1 2.3-5.7 0-8.5-3.8-8.5-8.6 0-4.1 2-6.7 4.3-7.8 1.4-.7 3.1-1 4.3-1 4.1.2 8.4 2.5 8.4 8.7M250.9 43.4c2.7-.4 3.3-.6 5.2-1.4.4 1 .4 1.3.6 2.4 1.5-1.1 3.3-2.5 6.2-2.5 1.1 0 2.4.2 3.5 1.2 1.3 1.2 1.3 2.6 1.4 3.8V56c0 1.2.5 1.6 2.6 1.6v1.1h-8.5v-1.1c2-.2 2.6-.3 2.6-1.6v-8.4c0-.8-.1-1.8-.6-2.6-.4-.6-1.3-1.3-2.8-1.3-2.1 0-3.5 1.2-4.3 2v10.1c0 1.4.7 1.6 2.7 1.7v1.1H251v-1.1c2.3 0 2.6-.6 2.6-1.8V46c0-1.5-1-1.5-2.8-1.5l.1-1.1z"/>
                        <image src="<?php echo $logo_info['fallback'] ?>" xlink:href=""
                               alt="<?php echo $logo_info['fallback_alt'] ?>"/></svg>
                </a></div>
			<?php
		}

		function do_footer_top() {
			if ( ! is_active_sidebar( 'global-footer-top' ) ) {
				return;
			}

			print( '<aside class="global-footer-top widget-area sidebar">' );
			dynamic_sidebar( 'global-footer-top' );
			print( '</aside>' );
		}

		function do_footer_primary() {
			$sidebars = array();
			$footers  = array( 'global-footer-1', 'global-footer-2', 'global-footer-3', 'global-footer-4' );
			foreach ( $footers as $f ) {
				if ( is_active_sidebar( $f ) ) {
					$sidebars[] = $f;
				}
			}

			$class = array( 'widget-area', 'sidebar' );

			switch ( count( $sidebars ) ) {
				case 0 :
					return false;
				case 1 :
					$class[] = '';
					break;
				case 2 :
					$class[] = 'one-half';
					break;
				case 3 :
					$class[] = 'one-third';
					break;
				default :
					$class[] = 'one-fourth';
					break;
			}

			print( '<div class="primary-global-footer"><div class="wrap">' );

			$first = true;
			foreach ( $sidebars as $s ) {
				$tmp = $class;
				if ( $first ) {
					$tmp[] = 'first';
				}
				$tmp[] = $s;

				$first = false;

				ob_start();
				dynamic_sidebar( $s );
				$sidebar = ob_get_clean();

				printf( '<aside class="%1$s">%2$s</aside>', implode( ' ', $tmp ), $sidebar );
			}

			print( '</div></div>' );
		}

		function do_footer_secondary() {
			if ( ! is_active_sidebar( 'global-footer-bottom-1' ) && ! is_active_sidebar( 'global-footer-bottom-2' ) ) {
				return false;
			}

			print( '<div class="secondary-global-footer">' );
			$class = array( 'widget-area sidebar' );
			if ( is_active_sidebar( 'global-footer-bottom-1' ) && is_active_sidebar( 'global-footer-bottom-2' ) ) {
				$class[] = 'one-half';
			}

			$first = true;
			foreach ( array( 'global-footer-bottom-1', 'global-footer-bottom-2' ) as $s ) {
				if ( ! is_active_sidebar( $s ) ) {
					continue;
				}

				$tmp = $class;
				if ( $first ) {
					$tmp[] = 'first';
					$first = false;
				}

				$tmp[] = $s;
				printf( '<aside class="%1$s">', implode( ' ', $tmp ) );
				dynamic_sidebar( $s );
				print( '</aside>' );
			}
			print( '</div>' );
		}

		function get_header_for_feed() {

			header( 'Content-Type: text/html' );
			header( 'Access-Control-Allow-Origin: *' );

			foreach ( $this->shortcodes_to_unregister as $s ) {
				remove_shortcode( $s );
			}
			print( "\n<!-- UMW Global Header: version {$this->version} -->\n" );
			print( "\n<!-- UMW Global Header Styles -->\n" );
			$this->gather_styles();
			print( "\n<!-- / UMW Global Header Styles -->\n" );

			remove_action( 'genesis_before', 'umw_analytics_gtm_noscript', 1 );
			do_action( 'genesis_before' );
			$this->do_full_header();
			print( "\n<!-- / UMW Global Header -->\n" );
			exit();
		}

		function gather_styles() {
			global $wp_styles;
			if ( class_exists( '\Mega_Menu_Style_Manager' ) ) {
				$tmp = new \Mega_Menu_Style_Manager;
				$css = str_replace( array( 'http://', 'https://' ), array( '//', '//' ), $tmp->get_css() );
				printf( '<style type="text/css" title="global-max-megamenu">%s</style>', $css );
			}
			$wp_styles->do_items( 'umw-online-tools' );
			do_action( 'umw-main-header-bar-styles' );
		}

		function gather_scripts() {
			if ( class_exists( '\UMW_Search_Engine' ) ) {
				\UMW_Search_Engine::do_search_choices_js();
			}
			if ( class_exists( '\Mega_Menu_Style_Manager' ) ) {
				$tmp = new \Mega_Menu_Style_Manager;
				$tmp->enqueue_scripts();

				global $wp_scripts;
				$wp_scripts->done[] = 'jquery';
				$wp_scripts->done[] = 'jquery-migrate';
				$wp_scripts->done[] = 'hoverIntent';
				if ( defined( 'MEGAMENU_PRO_VERSION' ) && class_exists( 'Mega_Menu_Pro' ) ) {
					\Mega_Menu_Pro::enqueue_public_scripts();
					$wp_scripts->do_items( 'megamenu-pro' );
				} else {
					$wp_scripts->do_items( 'megamenu' );
				}
			}
			if ( wp_script_is( 'umw-online-tools', 'enqueued' ) ) {
				global $wp_scripts;
				$wp_scripts->done[] = 'jquery';
				$wp_scripts->done[] = 'jquery-migrate';
				$wp_scripts->do_items( 'umw-online-tools' );
			}
		}

		function get_footer_for_feed() {

			header( 'Content-Type: text/html' );
			header( 'Access-Control-Allow-Origin: *' );

			foreach ( $this->shortcodes_to_unregister as $s ) {
				remove_shortcode( $s );
			}
			print( "\n<!-- UMW Global Footer: version {$this->version} -->\n" );
			$this->do_full_footer();
			print( "\n<!-- UMW Global Footer Scripts -->\n" );
			$this->gather_scripts();
			print( "\n<!-- / UMW Global Footer Scripts -->\n" );
			print( "\n<!-- / UMW Global Footer -->\n" );
			exit();
		}
	}
}