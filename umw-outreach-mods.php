<?php
/**
 * Plugin Name: UMW Outreach Customizations
 * Description: Implements various UMW-specific tweaks to the Outreach Pro Genesis child theme
 * Version: 0.1.20
 * Author: cgrymala
 * License: GPL2
 */
if ( ! class_exists( 'UMW_Outreach_Mods' ) ) {
	/**
	 * Define the class used on internal sites
	 */
	class UMW_Outreach_Mods_Sub {
		var $version = '0.1.19';
		var $header_feed = null;
		var $footer_feed = null;
		
		function __construct() {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
			add_action( 'after_setup_theme', array( $this, 'genesis_tweaks' ), 11 );
			
			add_action( 'global-umw-header', array( $this, 'do_full_header' ) );
			add_action( 'global-umw-footer', array( $this, 'do_full_footer' ) );
			
			$this->header_feed = esc_url( sprintf( 'http://%s/feed/umw-global-header/', DOMAIN_CURRENT_SITE ) );
			$this->footer_feed = esc_url( sprintf( 'http://%s/feed/umw-global-footer/', DOMAIN_CURRENT_SITE ) );
			
			add_shortcode( 'atoz', array( $this, 'do_atoz_shortcode' ) );
			
			$this->transient_timeout = 10;
		}
		
		function enqueue_styles() {
			wp_dequeue_style( 'google-fonts' );
			/* Register our modified copy of the Outreach Pro base style sheet */
			wp_register_style( 'outreach-pro', plugins_url( '/styles/outreach-pro.css', __FILE__ ), array(), $this->version, 'all' );
			/* Enqueue our additional styles */
			wp_enqueue_style( 'umw-outreach-mods', plugins_url( '/styles/umw-outreach-mods.css', __FILE__ ), array( 'outreach-pro', 'genericons', 'dashicons' ), $this->version, 'all' );
		}
		
		function genesis_tweaks() {
			if ( ! function_exists( 'genesis' ) )
				return false;
				
			/* Remove the default Genesis style sheet */
			remove_action( 'genesis_meta', 'genesis_load_stylesheet' );
			
			/* Get rid of the standard header & replace it with our global header */
			remove_all_actions( 'genesis_header' );
			add_action( 'genesis_header', array( $this, 'get_header' ) );
			
			add_theme_support( 'category-thumbnails' );
			
			/* Get rid of the standard footer & replace it with our global footer */
			remove_all_actions( 'genesis_footer' );
			add_action( 'genesis_footer', array( $this, 'get_footer' ) );
			
			/* Get everything out of the primary sidebar & replace it with just navigation */
			remove_all_actions( 'genesis_sidebar' );
			add_action( 'genesis_sidebar', array( $this, 'section_navigation' ) );
			
			/* Move the breadcrumbs to appear above the content-sidebar wrap */
			remove_action( 'genesis_before_loop', 'genesis_do_breadcrumbs' );
			add_action( 'genesis_before_content', 'genesis_do_breadcrumbs' );
		}
		
		function section_navigation() {
			echo '<nav class="widget widget_section_nav"><ul>';
			wp_list_pages( array( 'title_li' => null ) );
			echo '</ul></nav>';
		}
		
		function get_header() {
			do_action( 'global-umw-header' );
		}
		
		function get_footer() {
			do_action( 'global-umw-footer' );
		}
		
		function do_full_header() {
			delete_site_transient( 'global-umw-header' );
			$header = get_site_transient( 'global-umw-header' );
			
			if ( false === $header ) { /* There was no valid transient */
				$header = $this->get_header_from_feed();
				if ( false === $header || is_wp_error( $header ) ) { /* We did not successfully retrieve the feed */
					$header = get_site_option( 'global-umw-header', false );
				}
			}
			
			if ( is_string( $header ) ) {
				echo $header;
			} else {
				print( "<p>For some reason, the header code was not a string. It looked like:</p>\n<pre><code>" );
				var_dump( $header );
				print( "</code></pre>\n" );
			}
		}
		
		function do_full_footer() {
			delete_site_transient( 'global-umw-footer' );
			$footer = get_site_transient( 'global-umw-footer' );
			
			if ( false === $footer ) { /* There was no valid transient */
				$footer = $this->get_footer_from_feed();
				if ( false === $footer || is_wp_error( $footer ) ) { /* We did not successfully retrieve the feed */
					$footer = get_site_option( 'global-umw-footer', false );
				}
			}
			
			if ( is_string( $footer ) ) {
				echo $footer;
			} else {
				print( "<p>For some reason, the footer code was not a string. It looked like:</p>\n<pre><code>" );
				var_dump( $footer );
				print( "</code></pre>\n" );
			}
		}
		
		function get_header_from_feed() {
			printf( "\n<!-- Attempting to retrieve '%s' -->\n", esc_url( $this->header_feed ) );
			$header = wp_remote_get( esc_url( add_query_arg( 'time', time(), $this->header_feed ) ) );
			if ( is_wp_error( $header ) )  {
				print( '<pre><code>' );
				var_dump( $header );
				print( '</code></pre>' );
				return $header;
			}
				
			/*print( '<pre><code>' );
			var_dump( $header );
			print( '</code></pre>' );
			wp_die( 'Done' );*/
				
			if ( 200 === absint( wp_remote_retrieve_response_code( $header ) ) ) {
				$header = wp_remote_retrieve_body( $header );
				/*print( '<pre><code>' );
				var_dump( $header );
				print( '</code></pre>' );
				wp_die( 'Done' );*/
				set_site_transient( 'global-umw-header', $header, $this->transient_timeout );
				update_site_option( 'global-umw-header', $header );
				return $header;
			}
		}
		
		function get_footer_from_feed() {
			printf( "\n<!-- Attempting to retrieve '%s' -->\n", esc_url( $this->footer_feed ) );
			$footer = wp_remote_get( add_query_arg( 'time', time(), $this->footer_feed ) );
			if ( is_wp_error( $footer ) ) {
				print( '<pre><code>' );
				var_dump( $footer );
				print( '</code></pre>' );
				return $footer;
			}
				
			if ( 200 === absint( wp_remote_retrieve_response_code( $footer ) ) ) {
				$footer = wp_remote_retrieve_body( $footer );
				set_site_transient( 'global-umw-footer', $footer, $this->transient_timeout );
				update_site_option( 'global-umw-footer', $footer );
				return $footer;
			}
		}
		
		function do_atoz_shortcode( $args=array() ) {
			$defaults = apply_filters( 'atoz-shortcode-defaults', array(
				'post_type' => 'post', 
				'field' => 'title', 
				'view' => null, 
				'child_of' => 0, 
				'numberposts' => -1, 
				'reverse' => false, 
			) );
			
			$nonmeta = array( 'ID', 'author', 'title', 'name', 'type', 'date', 'modified', 'parent', 'comment_count', 'menu_order', 'post__in' );
			
			$args = shortcode_atts( $defaults, $args );
			$query = array(
				'post_type' => $args['post_type'], 
				'order' => $args['reverse'] ? 'desc' : 'asc', 
				'numberposts' => $args['numberposts'], 
				posts_per_page' => $args['numberposts'], 
				'post_status' => 'publish', 
			);
			if ( ! empty( $args['child_of'] ) ) {
				$query['child_of'] = $args['child_of'];
			}
			if ( ! in_array( $args['field'], $nonmeta ) ) {
				$meta = true;
				$query['orderby'] = 'meta_value';
				$query['meta_key'] = $args['field'];
			} else {
				$meta = false;
				$query['orderby'] = $args['field'];
			}
			
			$posts = new WP_Query( $query );
			$a = null;
			$list = array();
			$postlist = array();
			
			global $post;
			if ( $posts->have_posts() ) : while ( $posts->have_posts() ) : $posts->the_post();
				setup_postdata( $post );
				if ( $meta ) {
					$o = (string) get_post_meta( get_the_ID(), $args['field'], true );
				} else {
					$o = (string) $post->{$args['field']};
				}
				if ( strtolower( $o[0] ) != $a ) {
					$a = strtolower( $o[0] );
					$list[] = $a;
				}
				if ( ! empty( $args['view'] ) && function_exists( 'render_view' ) ) {
					$postlist[$a][] = render_view_template( $args['view'], $post );
				} else {
					$postlist[$a][] = apply_filters( 'atoz-generic-output', sprintf( '<a href="%1$s" title="%2$s">%3$s</a>', get_permalink(), apply_filters( 'the_title_attribute', get_the_title() ), get_the_title() ), $post );
				}
			endwhile; endif;
			wp_reset_postdata();
			wp_reset_query();
			
			if ( empty( $list ) || empty( $postlist ) ) {
				return 'The post list was empty';
			}
			
			$list = array_map( array( $this, 'do_alpha_link' ), $list );
			if ( empty( $args['view'] ) ) {
				foreach ( $postlist as $a=>$p ) {
					$postlist[$a] = array_map( array( $this, 'do_generic_alpha_wrapper' ), $p );
				}
			}
			
			foreach ( $postlist as $a=>$p ) {
				$postlist[$a] = sprintf( '<section class="atoz-alpha-letter-section"><h2 class="atoz-alpha-header-letter" id="atoz-%1$s">%2$s</h2>%3$s</section>', strtolower( $a ), strtoupper( $a ), '<div>' . implode( '', $p ) . '</div>' );
			}
			
			$output = apply_filters( 'atoz-final-output', 
				sprintf( '<nav class="atoz-alpha-links"><ul><li>%1$s</li></ul></nav><div class="atoz-alpha-content">%2$s</div>', 
					implode( '</li><li>', $list ), 
					implode( '', $postlist ) 
				), $list, $postlist 
			);
			
			return $output;
		}
		
		function do_alpha_link( $letter ) {
			$format = apply_filters( 'atoz-alpha-link-format', '<a href="#atoz-%1$s">%2$s</a>' );
			$args = apply_filters( 'atoz-alpha-link-args', array( strtolower( $letter ), strtoupper( $letter ) ) );
			return vsprintf( $format, $args );
		}
		
		function do_generic_alpha_wrapper( $value ) {
			$format = apply_filters( 'atoz-generic-alpha-wrapper-format', '<p class="atoz-item">%1$s</p>' );
			$args = apply_filters( 'atoz-generic-alpha-wrapper-args', array( $value ) );
			return vsprintf( $format, $args );
		}
	}
	
	/**
	 * Define the class used to manage the global header & footer
	 */
	class UMW_Outreach_Mods extends UMW_Outreach_Mods_Sub {
		var $dbversion = '20150522/090000';
		
		function __construct() {
			parent::__construct();
			
			$dbv = get_option( 'umw-outreach-mods-version', false );
			if ( $dbv != $this->dbversion ) {
				add_action( 'init', array( $this, 'flush_rules' ) );
			}
			
			add_action( 'umw-header-logo', array( $this, 'get_logo' ) );
			add_action( 'widgets_init', array( $this, 'register_sidebars' ) );
			add_action( 'init', array( $this, 'add_feed' ) );
			add_action( 'plugins_loaded', array( $this, 'use_plugins' ) );
		}
		
		function test_cascade() {
			print( "\n<!-- This is the root class -->\n" );
		}
		
		function add_feed() {
			add_feed( 'umw-global-header', array( $this, 'get_header_for_feed' ) );
			add_feed( 'umw-global-footer', array( $this, 'get_footer_for_feed' ) );
		}
		
		function flush_rules() {
			global $wp_rewrite;
			if ( is_object( $wp_rewrite ) ) {
				$wp_rewrite->flush_rules();
				update_option( 'umw-outreach-mods-version', $this->dbversion );
			}
		}
		
		function genesis_tweaks() {
			parent::genesis_tweaks();
			add_action( 'umw-footer', array( $this, 'do_footer_primary' ) );
			add_action( 'umw-footer', array( $this, 'do_footer_top' ) );
			add_action( 'umw-footer', array( $this, 'do_footer_secondary' ) );
		}
		
		function use_plugins() {
			if ( isset( $GLOBALS['umw_online_tools_obj'] ) ) {
				global $umw_online_tools_obj;
				$umw_online_tools_obj->enqueue_styles();
				/*add_action( 'umw-above-header', array( $umw_online_tools_obj, 'do_toolbar', 1 ) );
				add_action( 'umw-above-header', array( $umw_online_tools_obj, 'do_header_bar', 5 ) );*/
			} else {
				print( "\n<!-- The umw_online_tools_obj object doesn't seem to exist -->\n" );
			}
		}
		
		function register_sidebars() {
			genesis_register_sidebar( array(
				'id' => 'global-footer-top', 
				'name' => __( 'Global Footer Top' )
			) );
			genesis_register_sidebar( array( 
				'id' => 'global-footer-1', 
				'name' => __( 'Global Footer 1' ), 
			) );
			genesis_register_sidebar( array( 
				'id' => 'global-footer-2', 
				'name' => __( 'Global Footer 2' ), 
			) );
			genesis_register_sidebar( array( 
				'id' => 'global-footer-3', 
				'name' => __( 'Global Footer 3' ), 
			) );
			genesis_register_sidebar( array( 
				'id' => 'global-footer-4', 
				'name' => __( 'Global Footer 4' ), 
			) );
			genesis_register_sidebar( array( 
				'id' => 'global-footer-bottom-1', 
				'name' => __( 'Global Footer Bottom 1' ), 
			) );
			genesis_register_sidebar( array( 
				'id' => 'global-footer-bottom-2', 
				'name' => __( 'Global Footer Bottom 2' ), 
			) );
		}
		
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
		
		function do_full_footer() {
			do_action( 'umw-above-footer' );
			print( '<footer class="site-footer umw-global-footer"><div class="wrap">' );
			do_action( 'umw-footer' );
			print( '</div></footer>' );
			do_action( 'umw-below-footer' );
		}
		
		function get_logo() {
?>
<div class="umw-logo-block"><a href="http://www.umw.edu/" title="Return to the University of Mary Washington home page"><svg id="umw-full-logo-img" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 683.7 226.3" enable-background="new 0 0 683.7 226.3" xml:space="preserve"><style>.style0{fill:	#717073;}.style1{fill:	#003468;}</style><g><path d="M111.1 8.2L75.5 0v0l0 0l0 0v0c-0.3 0.1-33.1 12.5-59.7 34.3C-11 56.2 4.4 73.4 4.4 73.4 C2 66.2-1.5 54.7 22.6 37C46 19.9 75.5 9.6 75.5 9.6v0l35.6 8.2V8.2z" class="style0"/><path d="M111.1 32.9V25l-35.6-7.9C61 22.6 43.8 31 43.8 31C-2.4 53.5 4.8 72.8 4.8 72.8S2.1 62.1 27.7 47 c25.6-15.1 47.8-22 47.8-22L111.1 32.9z" class="style0"/><path d="M19.5 143.1L19.5 143.1l-1-74.4h0c0 0 0 0 0 0c0-0.9-2.1-1.6-4.8-1.6c-2.6 0-4.7 0.7-4.7 1.6h0v0 c0 0 0 0 0 0c0 0 0 0 0 0l-1.1 74.3h0c0 0 0 0.1 0 0.1c0 1.1 2.5 2 5.7 2C16.8 145.1 19.5 144.2 19.5 143.1 C19.5 143.1 19.5 143.1 19.5 143.1" class="style0"/><path d="M39.9 147.8L39.9 147.8l-1.2-92.4h0c0 0 0 0 0 0c0-1.1-2.6-2-5.9-2c-3.2 0-5.8 0.9-5.9 2h0v0c0 0 0 0 0 0 c0 0 0 0 0 0l-1.3 92.4h0c0 0 0 0.1 0 0.1c0 1.4 3.1 2.5 7.1 2.4C36.6 150.3 39.9 149.2 39.9 147.8 C39.9 147.8 39.9 147.8 39.9 147.8" class="style0"/><path d="M63.9 153.2L63.9 153.2l-1.5-111h0c0 0 0 0 0 0c0-1.3-3.2-2.4-7.1-2.4c-3.9 0-7 1.1-7.1 2.4h0v0 c0 0 0 0 0 0s0 0 0 0l-1.6 110.9h0c0 0 0 0.1 0 0.1c0 1.6 3.7 3 8.5 2.9C60 156.2 63.9 154.9 63.9 153.2 C63.9 153.2 63.9 153.2 63.9 153.2" class="style0"/><path d="M0.9 147.4c0 0 28.8 18.9 85 22.9l25.1-5.7v23.1l-25.1 6.4c0 0-62.5-8.9-85-38.2L0.9 147.4z" class="style0"/><path d="M104.9 54.6l15.9-0.1l0 1.7c-4.1 0.2-5.1 0.5-5.2 4.4l0.1 17.6c0 2.1 0 4.2 0.6 5.9 c1.4 3.6 5.3 5.6 9.8 5.6c3.5 0 6.7-1.3 8.6-3.2c2.5-2.6 2.5-5.7 2.5-9.6L137 61.9c-0.2-4.6-0.7-5.5-5.1-5.7l0-1.7l12.4 0l0 1.7 c-3.9 0.5-5 1.6-5.1 6.1l0.1 14.9c-0.1 5.5-0.2 9.8-5.5 12.9c-2.9 1.6-5.6 2-8.7 2.1c-4.4 0-8.8-0.9-11.9-3.9 c-2.9-2.8-3-5.5-3.1-8.3L110 61.1c-0.1-2-0.1-2.9-0.7-3.7c-0.8-1-2.1-1.1-4.4-1.2L104.9 54.6z" class="style1"/><path d="M164 62.2l9.9 0l0 1.4c-0.9 0-1.9 0.1-2.7 0.6c-1.2 0.7-1.4 1.9-1.5 4.1l0.1 23l-1.9 0l-15.2-21.1 c-1.3-1.8-1.6-2.2-3-4.5l0.1 19.3c0 1.1 0.1 2.4 0.5 3.3c0.9 1.5 2.7 1.5 3.7 1.5l0 1.4l-10.3 0l0-1.4c0.8 0 1.6-0.1 2.3-0.4 c1.9-0.8 2-2.7 2-4.4L148.1 66c-0.2-2.2-1.3-2.2-4.1-2.4l0-1.3l8.3 0L165 79.7c1.5 2.1 1.9 2.7 3.2 4.7L168.1 68 c-0.1-3.3-0.9-4.1-4.1-4.4L164 62.2z" class="style1"/><path d="M176.4 62.2l12.5 0l0 1.3c-3 0.1-4.1 0.2-4.1 3.1l0.1 19.5c0 1.4 0 2.2 0.7 2.8c0.7 0.7 1.5 0.7 3.6 0.7 l0 1.4l-13 0l0-1.4c3.7-0.1 4.3-0.2 4.3-3.9l-0.1-19.2c0-0.8 0-1.6-0.7-2.3c-0.6-0.6-1.4-0.6-3.4-0.8L176.4 62.2z" class="style1"/><path d="M190.7 62.1l11.9 0l0 1.3c-2.3 0-3.4 0.1-3.4 1.4c0 0.4 0.1 0.7 0.5 1.5l5.2 12.2c1.5 3.6 1.7 4.2 2.9 7.6 l7.6-19.2c0.4-1 0.6-1.4 0.6-1.9c0-0.3-0.1-1-0.8-1.3c-0.4-0.2-0.4-0.2-2.7-0.2l0-1.4l9.9 0l0 1.3c-3.6 0.3-3.9 1-4.9 3.6 l-9.9 24.5l-1.6 0l-10.9-25.2c-0.9-1.9-1.4-2.4-4.1-2.8L190.7 62.1z" class="style1"/><path d="M224.1 61.9l20.9-0.1l0.8 7.2l-1.3 0c-1.5-4.7-2.5-5.6-6.5-5.6l-5.6 0l0 11.4l2.7 0c1.1 0 2.2-0.1 3.1-1 c0.8-0.9 0.9-2.1 1-3l1.3 0l0 9.8l-1.3 0c0-1-0.1-2-0.5-2.8c-0.8-1.4-2.3-1.5-3.4-1.5l-2.7 0l0 9.4c0 1.4 0.1 2.5 1.7 3.1 c1.2 0.5 2.3 0.5 4 0.5c1.8 0 4.1-0.1 5.9-1.8c1.5-1.4 2.3-3.6 2.6-5.8l1.3 0l-0.5 9L224 90.9l0-1.4c3.4-0.1 4.1-0.3 4.3-3.2 l-0.1-19.8c0-0.8 0-1.8-0.6-2.5c-0.6-0.7-1.2-0.7-3.4-0.8L224.1 61.9z" class="style1"/><path d="M262.3 75.7c1.3 0 2.5-0.1 3.4-0.5c2.2-1 2.8-3.5 2.8-5.7c0-3.5-1.5-4.7-2.6-5.3c-1.6-0.9-3.2-0.9-7-0.8 l0 12.4L262.3 75.7z M250.6 61.9l10 0c2.4 0 5.5 0 7.8 0.8c2.9 1 4.8 3.4 4.8 6.5c0 1.6-0.5 3.3-2 4.9c-2 1.9-4.1 2.2-5.8 2.4 c3.5 0.8 3.7 1.1 6.4 4.8l4 5.6c1.6 2.2 1.9 2.4 4.2 2.4l0 1.4l-6.6 0c-0.9-0.8-1.4-1.3-2.3-2.7c-0.5-0.7-2.6-4.2-3-4.9 c-3.8-6.1-3.9-6.1-8.9-5.9l0 8.6c0 1.2 0 2.3 0.8 3c0.7 0.7 1.5 0.6 3.6 0.7l0 1.3l-12.9 0l0-1.3c3.2-0.2 4.3-0.3 4.2-3.7 l-0.1-19.1c0-1.3 0-2.1-0.7-2.7c-0.6-0.7-1.1-0.6-3.4-0.8L250.6 61.9z" class="style1"/><path d="M282.2 81.6c0.3 1.3 1.2 5.1 4.5 7c1.2 0.7 2.6 1 3.9 1c3.5 0 5.9-2.2 5.9-5.3c0-2.9-2-4.3-2.9-4.8 c-1-0.7-1.4-0.9-5.8-3.1c-3.3-1.7-6.4-3.3-6.4-7.9c0-5.3 4.4-7.5 8-7.5c1.1 0 2.1 0.2 3 0.4c1.8 0.5 2.5 1 3.5 1.7 c0.4-0.7 0.5-0.8 0.9-1.7l0.9 0l0.1 7.8l-1.3 0c-0.3-1.4-0.7-2.9-1.6-4.1c-1.2-1.6-3.2-2.8-5.5-2.8c-3.1 0-4.8 1.9-4.8 4.2 c0 1.1 0.4 2 0.9 2.6c0.9 1.2 2.4 2 7.6 4.7c3.3 1.7 6.4 3.3 6.5 8.5c0 1.1-0.1 2.6-0.8 4c-0.7 1.6-3 4.8-7.7 4.9 c-0.8 0-1.8-0.1-3-0.4c-2.5-0.6-3.9-1.7-5.1-2.6c-0.5 1.2-0.6 1.4-1 2.5l-1.3 0l0-9.2L282.2 81.6z" class="style1"/><path d="M302.5 61.7l12.5 0l0 1.3c-3 0.1-4.1 0.2-4.1 3.1l0.1 19.5c0 1.4 0 2.2 0.7 2.8c0.7 0.6 1.5 0.7 3.6 0.7 l0 1.4l-13 0l0-1.4c3.7-0.1 4.3-0.2 4.3-3.9l-0.1-19.2c0-0.8 0-1.6-0.7-2.3c-0.6-0.6-1.4-0.6-3.4-0.8L302.5 61.7z" class="style1"/><path d="M317.7 61.6l24.8-0.1l0.7 7.3l-1.2 0c-0.4-1.3-0.9-3.1-2.1-4.3c-1.4-1.4-3.1-1.4-4.7-1.4l-3 0l0.1 22.9 c0 1.3 0.3 2.4 1.8 2.8c0.3 0.1 0.6 0.1 2.5 0.2l0 1.3l-12.9 0l0-1.4c3.3-0.2 4.3-0.3 4.3-3.2l-0.1-22.7l-3.1 0 c-2.5 0.1-3.6 0.3-4.7 1.6c-1 1.2-1.5 2.5-1.9 4.1l-1.3 0L317.7 61.6z" class="style1"/><path d="M344.2 61.5l12.3 0l0 1.4c-1.9 0-3.2 0-3.2 1.1c0 0.3 0.2 0.7 0.5 1.2l6.2 10.7l6.3-10.5 c0.2-0.4 0.5-0.9 0.5-1.2c0-1.1-1.3-1.2-4-1.3l0-1.4l10.3 0l0 1.4c-2.7 0.2-3.6 0.5-5.1 2.9l-7.1 11.8l0 8.5c0 2.7 1.2 2.9 4.3 3 l0 1.3l-13 0l0-1.3c1.9-0.1 2.2-0.2 2.7-0.3c1.5-0.4 1.6-1.4 1.7-3l0-8.2l-7.7-12.2c-1.4-2-2.1-2.4-4.6-2.5L344.2 61.5z" class="style1"/><path d="M396 63.9c-3.4 2.6-4.3 7.1-4.3 11.4c0 1.8 0.1 3.5 0.5 5.3c0.3 1.4 0.9 4.5 3.3 6.6 c1.1 0.9 3.2 2.2 6.5 2.2c3.4 0 6.1-1.4 7.6-3.3c1.8-2.1 2.8-5.9 2.8-10.8c0-2.1-0.2-6-2-8.9c-1.8-3-4.9-4.5-8.4-4.5 C400.4 62 398.1 62.3 396 63.9 M388.3 68.6c1.7-3.4 6-7.9 13.8-7.9c1.3 0 2.7 0.2 4 0.4c5.8 1.3 11.3 6.3 11.3 14.6 c0 8.6-5.9 15.3-15.4 15.3c-8.7 0-15.2-5.9-15.3-14.9C386.7 73.5 387.2 70.9 388.3 68.6" class="style1"/><path d="M418.9 61.3l20.9-0.1l0.8 7l-1.3 0c-0.5-1.5-1.1-3.1-2.1-4.2c-1.3-1.3-2.7-1.3-4.5-1.4l-5.4 0l0 11.4l3.1 0 c0.9 0 1.8 0 2.5-0.8c0.9-0.9 1-2.2 1-3.2l1.3 0l0 9.8l-1.3 0c0-1 0-2.1-0.6-3c-0.9-1.2-2.2-1.3-3.1-1.3l-3 0l0 8.9 c0 2.3 0.1 3.1 0.9 3.7c0.6 0.4 1 0.4 3.4 0.6l0 1.3l-12.9 0l0-1.4c2.3 0 3.4-0.1 4-1.2c0.3-0.6 0.3-1.3 0.3-2.9l-0.1-19 c0-0.7 0-1.6-0.7-2.2c-0.7-0.6-1.6-0.7-3.4-0.8L418.9 61.3z" class="style1"/><path d="M147.4 99.6l13.2 0l0 2.1c-2.9 0.2-3.6 0.2-4.5 0.7c-2 1-1.9 3.3-1.9 4.9l0.4 28.2c0 1.8 0 3.4 0.5 4.5 c0.8 1.6 2 1.7 6 1.9l0 2l-19.7 0.1l0-2.1c3.1-0.1 4-0.2 4.8-0.7c1.7-0.9 1.9-2.9 1.9-5l-0.1-22.3c0-4 0-5.2 0.3-9.5 c-0.6 1.8-1 3-2 5.4l-14.1 34.2l-2.1 0l-13.9-33c-1.1-2.7-1.1-2.9-2.2-6c-0.1 6.2-0.1 12.3-0.1 18.4c0 5.6 0.1 8.5 0.2 12.2 c0.1 3.8 0.6 6 6.5 6.4l0 2.1l-15.8 0.1l0-2.1c5.3-0.8 6.1-2 6.4-6.4l0.3-27.6c0-5.6-1.1-6.3-6.4-6.3l0-2l13.3 0l10.7 25.3 c1.8 4.5 2.3 5.8 3.9 10.2L147.4 99.6z" class="style1"/><path d="M182.4 122.5c-1.2-3.2-1.4-3.8-2.6-7.2l-5.4 14l10.6 0L182.4 122.5z M180.5 108.5l1.6 0l11.6 28.4 c1.4 3.5 2.1 5 5.7 5.3l0 1.6l-14.6 0l0-1.6c2.5 0 4.5 0 4.5-1.8c0-0.4-0.1-0.8-0.7-2.2l-2.8-7.1l-12 0l-2.4 6.3 c-0.4 1.3-0.6 1.9-0.6 2.5c0 1.7 1.5 2.1 4 2.3l0 1.6l-11.5 0l0-1.6c3.4-0.4 4.3-1.3 5.8-4.8L180.5 108.5z" class="style1"/><path d="M215.4 125.5c1.6-0.1 3-0.2 4.1-0.7c2.7-1.2 3.4-4.2 3.4-6.9c0-4.2-1.8-5.7-3.1-6.4c-1.9-1.1-3.8-1.1-8.4-1 l0.1 15L215.4 125.5z M201.3 108.8l12 0c2.9 0 6.7 0 9.4 1c3.5 1.2 5.8 4.1 5.8 7.9c0 1.9-0.6 4-2.4 5.9c-2.4 2.3-4.9 2.7-7.1 2.9 c4.2 1 4.4 1.4 7.7 5.8l4.8 6.7c1.9 2.6 2.3 2.9 5.1 2.9l0 1.6l-8 0c-1.1-1-1.7-1.6-2.8-3.3c-0.5-0.8-3.1-5-3.7-6 c-4.6-7.3-4.8-7.3-10.8-7.2l0 10.4c0 1.5 0.1 2.8 0.9 3.6c0.8 0.8 1.8 0.8 4.3 0.9l0 1.6l-15.6 0.1l0-1.6c3.9-0.2 5.1-0.4 5.1-4.4 l-0.1-23c0-1.5-0.1-2.6-0.8-3.3c-0.7-0.8-1.4-0.8-4.1-1L201.3 108.8z" class="style1"/><path d="M232.4 108.7l14.9-0.1l0 1.7c-2.3 0-3.9 0-3.8 1.3c0 0.4 0.2 0.8 0.5 1.4l7.5 13l7.6-12.7 c0.3-0.5 0.6-1 0.6-1.5c0-1.4-1.5-1.4-4.8-1.6l0-1.7l12.4 0l0 1.7c-3.2 0.3-4.3 0.7-6.2 3.5l-8.6 14.2l0 10.3 c0 3.2 1.4 3.5 5.2 3.6l0 1.6l-15.7 0.1l0-1.6c2.3-0.2 2.7-0.2 3.2-0.4c1.8-0.5 2-1.7 2-3.6l0-9.9l-9.3-14.8 c-1.6-2.5-2.6-2.9-5.6-3L232.4 108.7z" class="style1"/><path d="M280.9 99.1l17.8-0.1l0 2c-0.7 0.1-2.4 0.1-3 0.3c-0.5 0.1-2 0.4-2 2.2c0 0.5 0.1 1 0.5 2.3l5.3 18.3 c1.3 4.4 1.5 5.5 2.5 10L312.6 99l2 0l8.9 25.6c1.3 4 1.6 5 2.9 9.2c0.5-2.3 0.6-3 1.4-5.4l6.4-22c0.2-1 0.5-1.9 0.5-2.6 c0-2.6-2.5-2.6-5.8-2.7l0-2.1l15.2-0.1l0 2.1c-4.5 0.4-5.8 0.9-7.1 5.1L326 144l-2.4 0l-11.8-33.6l-10.2 33.7l-2.6 0l-11.7-37.7 c-1.1-3.4-1.8-4.8-6.4-5.3L280.9 99.1z" class="style1"/><path d="M349.7 122c-1.2-3.2-1.4-3.8-2.6-7.2l-5.4 14l10.6 0L349.7 122z M347.8 107.9l1.6 0l11.6 28.4 c1.4 3.5 2.1 5 5.7 5.3l0 1.6l-14.6 0l0-1.6c2.5 0 4.5 0 4.5-1.8c0-0.4-0.1-0.8-0.6-2.2l-2.8-7.1l-12 0l-2.4 6.3 c-0.4 1.3-0.6 1.9-0.6 2.5c0 1.7 1.5 2.1 4 2.3l0 1.6l-11.5 0l0-1.6c3.4-0.4 4.3-1.3 5.8-4.8L347.8 107.9z" class="style1"/><path d="M370.7 132.2c0.4 1.6 1.4 6.2 5.5 8.5c1.5 0.8 3.2 1.2 4.7 1.2c4.2 0 7.1-2.7 7.1-6.4 c0-3.6-2.4-5.2-3.5-5.8c-1.2-0.8-1.7-1.1-7-3.8c-4-2.1-7.8-4-7.8-9.5c0-6.4 5.3-9 9.7-9c1.3 0 2.6 0.2 3.6 0.5 c2.1 0.6 3.1 1.2 4.2 2.1c0.5-0.8 0.6-1 1.1-2l1.1 0l0.2 9.4l-1.5 0c-0.4-1.7-0.9-3.5-1.9-5c-1.4-1.9-3.8-3.3-6.6-3.3 c-3.7 0-5.8 2.3-5.8 5.1c0 1.3 0.5 2.4 1.1 3.2c1.1 1.4 2.9 2.5 9.2 5.6c4 2.1 7.8 4 7.8 10.3c0 1.3-0.1 3.1-0.9 4.9 c-0.8 1.9-3.6 5.8-9.3 5.9c-1 0-2.2-0.1-3.6-0.5c-3-0.8-4.7-2.1-6.1-3.2c-0.6 1.5-0.7 1.7-1.2 3l-1.5 0l0-11.1L370.7 132.2z" class="style1"/><path d="M395.3 108.2l15.1-0.1l0 1.5c-2.3 0.1-2.5 0.2-3 0.3c-1.8 0.5-1.9 2.2-1.9 3.7l0 9.8l17.3-0.1l0-9.6 c-0.1-3.9-1.4-3.9-4.9-4.1l0-1.6l15.1 0l0 1.6c-2 0.2-2.2 0.2-2.6 0.3c-2 0.5-2.2 1.9-2.3 3.8l0.1 23.5c0 3.8 1.1 3.9 5.1 4l0 1.6 l-15.6 0.1l0-1.6c4.6-0.3 5.1-0.4 5.2-4.4l0-11.4l-17.3 0.1l0 11.4c0 1 0 2.4 0.7 3.2c0.8 0.9 1.8 1 4.4 1.1l0 1.6l-15.7 0.1l0-1.7 c2.5-0.3 2.6-0.3 3.1-0.4c1.7-0.4 2.1-1.1 2.2-3.2l-0.1-25.2c0-0.6-0.1-1.3-0.6-1.9c-0.8-0.9-2.2-1-4.3-1.1L395.3 108.2z" class="style1"/><path d="M436.4 108l15.1-0.1l0 1.6c-3.7 0.1-4.9 0.3-4.9 3.8l0.1 23.6c0 1.7 0.1 2.6 0.8 3.4 c0.8 0.8 1.8 0.8 4.4 0.8l0 1.7l-15.7 0.1l0-1.7c4.5-0.1 5.1-0.3 5.2-4.8l-0.1-23.2c0-1 0-2-0.9-2.8c-0.7-0.7-1.7-0.8-4.1-0.9 L436.4 108z" class="style1"/><path d="M479.1 107.9l12 0l0 1.7c-1.1 0.1-2.3 0.2-3.2 0.7c-1.4 0.9-1.7 2.3-1.8 5l0.1 27.7l-2.3 0l-18.4-25.5 c-1.5-2.1-1.9-2.7-3.6-5.4l0.1 23.3c0 1.4 0.1 2.9 0.7 3.9c1.1 1.8 3.3 1.8 4.4 1.8l0 1.6l-12.4 0l0-1.7c0.9-0.1 1.9-0.1 2.8-0.5 c2.3-1 2.4-3.3 2.4-5.3l-0.1-22.8c-0.2-2.6-1.6-2.7-4.9-2.9l0-1.6l10 0l15.3 21.1c1.8 2.5 2.3 3.3 3.9 5.7l-0.1-19.9 c-0.1-4-1.1-4.9-5-5.3L479.1 107.9z" class="style1"/><path d="M522.7 140c-1.2 0.3-2.5 0.5-3.6 0.9c-1.7 0.5-3.3 1.2-4.9 1.7c-1.9 0.6-3.9 0.9-5.9 0.9 c-10.2 0-17.1-7.7-17.1-17.4c0-10.3 7.3-19.1 17.8-19.2c5.4 0 9.1 2.4 10.7 3.5c0.6-1 0.7-1.4 1.2-2.6l1.4 0l0.2 12l-1.5 0 c-0.5-2.3-2.5-11.1-11.8-11.1c-7.1 0-11.8 5.7-11.8 16.5c0 3.8 0.7 7.1 1.8 9.5c1.7 3.7 5.1 6.7 10 6.7c3.4 0 6.4-1.6 7.6-2.8 c0.8-0.7 0.9-1.3 0.9-2.2l0-4.3c-0.1-1.3-0.2-2.6-2.2-2.9c-0.4 0-1.3-0.1-3.3-0.1l0-1.6l15.1-0.1l0 1.6c-1.4 0-2.2 0.1-2.8 0.3 c-1.6 0.5-1.6 1.8-1.7 2.6L522.7 140z" class="style1"/><path d="M527.5 107.7l30-0.1l0.9 8.8l-1.4 0c-0.5-1.6-1-3.7-2.5-5.2c-1.7-1.7-3.7-1.7-5.7-1.7l-3.6 0l0.1 27.7 c0.1 1.6 0.3 2.9 2.1 3.4c0.4 0.1 0.7 0.1 3 0.2l0 1.6l-15.6 0.1l0-1.6c4-0.2 5.2-0.4 5.2-3.9l-0.1-27.5l-3.8 0 c-3.1 0.1-4.3 0.3-5.6 1.9c-1.2 1.5-1.8 3.1-2.3 5l-1.5 0L527.5 107.7z" class="style1"/><path d="M571.4 110.6c-4.1 3.2-5.2 8.6-5.1 13.8c0 2.1 0.2 4.2 0.6 6.4c0.3 1.7 1.1 5.4 4 8 c1.3 1.1 3.8 2.7 7.8 2.7c4.1 0 7.3-1.7 9.2-4c2.2-2.6 3.4-7.2 3.4-13c0-2.6-0.2-7.2-2.4-10.8c-2.2-3.6-5.9-5.4-10.2-5.4 C576.7 108.3 574 108.6 571.4 110.6 M562.2 116.2c2.1-4.2 7.2-9.6 16.7-9.6c1.6 0 3.2 0.2 4.8 0.5c7 1.6 13.6 7.6 13.6 17.6 c0 10.4-7.1 18.4-18.6 18.5c-10.6 0-18.4-7.1-18.4-18C560.3 122.2 560.8 119 562.2 116.2" class="style1"/><path d="M621.3 107.4l12 0l0 1.7c-1.1 0.1-2.3 0.2-3.2 0.7c-1.4 0.9-1.7 2.3-1.8 5l0.1 27.7l-2.3 0L607.7 117 c-1.5-2.1-1.9-2.7-3.6-5.4l0.1 23.3c0 1.4 0.1 2.9 0.7 3.9c1.1 1.8 3.3 1.8 4.4 1.8l0 1.6l-12.4 0l0-1.7c0.9-0.1 1.9-0.1 2.8-0.5 c2.3-1 2.4-3.3 2.4-5.3L602 112c-0.2-2.6-1.6-2.7-4.9-2.9l0-1.6l10 0l15.3 21.1c1.8 2.5 2.3 3.2 3.9 5.7l-0.1-19.9 c-0.1-4-1.1-4.9-5-5.3L621.3 107.4z" class="style1"/><path d="M90.6 157.9L90.6 157.9L89 35.4h0c0 0 0 0 0 0c0-1.5-3.5-2.6-7.8-2.6c-4.3 0-7.7 1.2-7.8 2.6h0v0 c0 0 0 0 0 0c0 0 0 0 0 0l-1.8 122.4h0c0 0 0 0.1 0 0.1c0 1.8 4 3.3 9.4 3.2C86.3 161.2 90.6 159.8 90.6 157.9 C90.6 157.9 90.6 157.9 90.6 157.9" class="style0"/><path d="M668.9 217.1l1.4-8.2l2.4-2.4l4.7 10.6h3.6l-5.8-12.7l8.4-8.8h-4.1l-6.8 7.6c-0.5 0.6-1.2 1.4-1.8 2.2h-0.1 l3.4-19.8H671l-5.4 31.6H668.9z M656.9 217.1l1.6-9.5c0.8-5 3.4-8.9 6.3-8.9c0.4 0 0.7 0 0.9 0.1l0.6-3.6c-0.3 0-0.6-0.1-1-0.1 c-2.6 0-4.7 2.2-5.9 5.2h-0.1c0.2-1.6 0.4-3.2 0.5-4.7h-2.9c-0.2 2-0.5 4.8-1 7.6l-2.4 13.9H656.9z M644.1 198c3.5 0 4.5 3.5 4.5 6 c0 5-3 10.6-6.9 10.6c-2.8 0-4.6-2.5-4.6-6C637.2 203.6 640 198 644.1 198 M644.5 195.1c-6.3 0-10.7 6.5-10.7 13.6 c0 4.8 2.7 8.8 7.6 8.8c6.5 0 10.8-6.9 10.8-13.6C652.1 199.5 649.8 195.1 644.5 195.1 M606.9 195.5l1.8 21.5h3.1l5.6-11.8 c0.9-1.9 1.4-3.5 2.2-5.6h0.1c0 1.9 0.1 3.7 0.3 5.7l1.3 11.7h3.1l9.6-21.5h-3.4l-5.2 12.4c-0.8 2.1-1.3 3.7-1.8 5.5h-0.1 c0-1.4-0.1-3.2-0.3-5.6l-1.2-12.3h-2.9l-5.7 12.5c-1 2.1-1.7 4-2.1 5.4h-0.1c0-1.7 0.1-3.1-0.1-5.8l-0.7-12.1H606.9z M589.7 198 c3.5 0 4.5 3.5 4.5 6c0 5-3 10.6-6.9 10.6c-2.8 0-4.6-2.5-4.6-6C582.7 203.6 585.5 198 589.7 198 M590 195.1 c-6.3 0-10.7 6.5-10.7 13.6c0 4.8 2.7 8.8 7.6 8.8c6.5 0 10.8-6.9 10.8-13.6C597.6 199.5 595.3 195.1 590 195.1 M573.4 191.5 l-0.7 4h-2.8l-0.5 2.9h2.8l-1.9 10.9c-0.3 1.6-0.4 2.8-0.4 4c0 2.2 1.2 4.2 4.1 4.2c1 0 2-0.1 2.6-0.4l0.2-2.9 c-0.4 0.1-1 0.2-1.6 0.2c-1.3 0-1.8-0.8-1.8-2.1c0-1.1 0.2-2.2 0.4-3.4l1.8-10.4h4.6l0.5-2.9H576l0.9-5.2L573.4 191.5z M553.6 191.5l-0.7 4h-2.8l-0.5 2.9h2.8l-1.9 10.9c-0.3 1.6-0.4 2.8-0.4 4c0 2.2 1.2 4.2 4.1 4.2c1 0 2-0.1 2.6-0.4l0.2-2.9 c-0.4 0.1-1 0.2-1.6 0.2c-1.3 0-1.8-0.8-1.8-2.1c0-1.1 0.2-2.2 0.4-3.4l1.8-10.4h4.6l0.5-2.9h-4.6l0.9-5.2L553.6 191.5z M544 200.7 c0 3.4-4.1 4.2-9.3 4.1c0.8-3.4 3.2-6.8 6.4-6.8C542.8 197.9 544 198.9 544 200.7 M544.2 213.1c-1.2 0.7-2.9 1.5-5.2 1.5 c-2 0-3.6-0.9-4.3-2.9c-0.4-1.2-0.6-3.2-0.4-4.1c7.2 0.1 12.9-1.3 12.9-7c0-3.1-2-5.6-5.7-5.6c-6.2 0-10.6 7.3-10.6 13.8 c0 4.8 2.2 8.7 7.4 8.7c2.6 0 5-0.8 6.4-1.7L544.2 213.1z M524 205.9c-0.7 4.2-3.6 8.2-6.4 8.2c-3 0-3.8-2.8-3.7-5.2 c0-5.3 3.4-10.9 8.2-10.9c1.4 0 2.6 0.4 3.2 0.7L524 205.9z M509.5 224.8c1.2 0.9 3.4 1.5 5.8 1.5c2.2 0 4.8-0.5 6.8-2.4 c2-2 3.1-5.1 3.9-9.6l3-17.8c-1.5-0.8-4-1.5-6.2-1.5c-7.5 0-12.3 7.2-12.3 14.5c0 3.8 2 7.5 5.9 7.5c2.6 0 5-1.5 6.7-4.6h0.1 l-0.6 3.2c-1.1 5.8-3.7 7.6-7 7.6c-2 0-4-0.6-5.1-1.4L509.5 224.8z M487.2 216c0.9 0.8 2.8 1.4 4.8 1.5c4.1 0 7.4-2.5 7.4-7.1 c0-2.4-1.4-4.3-3.6-5.6c-1.8-1.1-2.7-2-2.7-3.6c0-1.9 1.4-3.2 3.4-3.2c1.4 0 2.7 0.5 3.4 1l0.9-2.8c-0.7-0.5-2.3-1.1-4-1.1 c-4.1 0-7 2.9-7 6.6c0 2.2 1.2 4.1 3.5 5.5c2 1.2 2.7 2.3 2.7 4c0 1.9-1.4 3.5-3.7 3.5c-1.6 0-3.2-0.7-4.2-1.3L487.2 216z M481.6 206.4c-0.8 4.8-3.9 8.1-6.5 8.1c-2.8 0-3.6-2.7-3.6-5.3c0-5.7 3.6-11.2 8.2-11.2c1.4 0 2.5 0.4 3.2 0.9L481.6 206.4z M485.2 185.5l-1.7 10.3c-0.8-0.4-2.2-0.8-3.4-0.8c-6.8 0-12 7-12 14.7c0 4.4 2 7.8 5.8 7.8c2.8 0 5.2-1.7 7-4.8h0.1l-0.5 4.3h3 c0.1-2.1 0.4-4.6 0.8-6.8l4.2-24.7H485.2z M450.4 217.1l1.8-10.4c0.9-5.3 4.2-8.5 6.6-8.5c2.3 0 3 1.6 3 3.7c0 0.9-0.1 1.9-0.2 2.8 l-2.1 12.4h3.3l2.2-12.6c0.2-1.2 0.4-2.5 0.4-3.5c0-4.5-2.6-6-4.9-6c-2.8 0-5.4 1.7-7.2 4.7h-0.1l0.5-4.2h-3 c-0.2 1.8-0.5 3.9-0.9 6.3l-2.6 15.2H450.4z M444.7 191.9c1.3 0 2.2-1 2.3-2.5c0-1.3-0.8-2.3-2-2.3c-1.2 0-2.1 1.1-2.2 2.5 C442.8 190.9 443.5 191.9 444.7 191.9 M441.8 217.1l3.7-21.5h-3.3l-3.7 21.5H441.8z M410.1 217.1l1.8-10.8c0.8-4.6 3.8-8.1 6.2-8.1 c2.4 0 2.9 1.9 2.9 3.7c0 0.8-0.1 1.6-0.2 2.6l-2.2 12.6h3.2l1.8-11c0.8-4.8 3.7-7.9 6.1-7.9c2.2 0 2.9 1.5 2.9 3.7 c0 0.9-0.1 2-0.3 2.8l-2.1 12.4h3.2l2.2-12.7c0.2-1.2 0.3-2.7 0.3-3.6c0-4-2.5-5.6-4.7-5.6c-2.9 0-5.4 1.7-7.1 4.8 c-0.2-2.7-1.6-4.8-4.5-4.8c-2.6 0-5 1.5-6.8 4.5h-0.1l0.5-4h-2.9c-0.2 1.8-0.5 3.9-0.9 6.3l-2.6 15.2H410.1z M392.1 191.5l-0.7 4 h-2.8l-0.5 2.9h2.8l-1.9 10.9c-0.3 1.6-0.4 2.8-0.4 4c0 2.2 1.2 4.2 4.1 4.2c1 0 2-0.1 2.6-0.4l0.2-2.9c-0.4 0.1-1 0.2-1.6 0.2 c-1.3 0-1.8-0.8-1.8-2.1c0-1.1 0.2-2.2 0.4-3.4l1.8-10.4h4.6l0.5-2.9h-4.6l0.9-5.2L392.1 191.5z M380.6 204.8c-1 5.7-4.2 9.7-7 9.7 c-2.6 0-3.3-2.4-3.3-4.8c0-6 4.1-11.8 9.2-11.8c1.1 0 1.8 0.1 2.3 0.3L380.6 204.8z M382.7 217.1c-0.1-2.4 0.2-6.2 0.8-10l2-11.2 c-1.4-0.4-3.4-0.8-5.2-0.8c-8.4 0-13.4 7.9-13.4 15.4c0 4.1 2.1 7.1 5.6 7.1c2.8 0 5.4-1.6 7.5-6h0.1c-0.2 2.2-0.4 4.3-0.4 5.6 H382.7z M362.4 200.7c0 3.4-4.1 4.2-9.3 4.1c0.8-3.4 3.2-6.8 6.4-6.8C361.2 197.9 362.4 198.9 362.4 200.7 M362.7 213.1 c-1.2 0.7-2.9 1.5-5.2 1.5c-2 0-3.6-0.9-4.3-2.9c-0.4-1.2-0.6-3.2-0.4-4.1c7.2 0.1 12.9-1.3 12.9-7c0-3.1-2-5.6-5.7-5.6 c-6.2 0-10.6 7.3-10.6 13.8c0 4.8 2.2 8.7 7.4 8.7c2.6 0 5-0.8 6.4-1.7L362.7 213.1z M340.6 217.1l1.6-9.5c0.8-5 3.4-8.9 6.3-8.9 c0.4 0 0.7 0 0.9 0.1l0.6-3.6c-0.3 0-0.6-0.1-1-0.1c-2.6 0-4.7 2.2-5.9 5.2H343c0.2-1.6 0.4-3.2 0.5-4.7h-2.9c-0.2 2-0.5 4.8-1 7.6 l-2.4 13.9H340.6z M331.5 205.9c-0.7 4.2-3.6 8.2-6.4 8.2c-3 0-3.8-2.8-3.7-5.2c0-5.3 3.4-10.9 8.2-10.9c1.4 0 2.6 0.4 3.2 0.7 L331.5 205.9z M316.9 224.8c1.2 0.9 3.4 1.5 5.8 1.5c2.2 0 4.8-0.5 6.8-2.4c2-2 3.1-5.1 3.9-9.6l3-17.8c-1.5-0.8-4-1.5-6.2-1.5 c-7.5 0-12.3 7.2-12.3 14.5c0 3.8 2 7.5 5.9 7.5c2.6 0 5-1.5 6.7-4.6h0.1l-0.6 3.2c-1.1 5.8-3.7 7.6-7 7.6c-2 0-4-0.6-5.1-1.4 L316.9 224.8z M305.5 200.7c0 3.4-4.1 4.2-9.3 4.1c0.8-3.4 3.2-6.8 6.4-6.8C304.3 197.9 305.5 198.9 305.5 200.7 M305.7 213.1 c-1.2 0.7-2.9 1.5-5.2 1.5c-2 0-3.6-0.9-4.3-2.9c-0.4-1.2-0.6-3.2-0.4-4.1c7.2 0.1 12.9-1.3 12.9-7c0-3.1-2-5.6-5.7-5.6 c-6.2 0-10.6 7.3-10.6 13.8c0 4.8 2.2 8.7 7.4 8.7c2.6 0 5-0.8 6.4-1.7L305.7 213.1z M283.6 217.1l1.6-9.5c0.8-5 3.4-8.9 6.3-8.9 c0.4 0 0.7 0 0.9 0.1l0.6-3.6c-0.3 0-0.6-0.1-1-0.1c-2.6 0-4.7 2.2-5.9 5.2H286c0.2-1.6 0.4-3.2 0.5-4.7h-2.9c-0.2 2-0.5 4.8-1 7.6 l-2.4 13.9H283.6z M275.9 200.7c0 3.4-4.1 4.2-9.3 4.1c0.8-3.4 3.2-6.8 6.4-6.8C274.7 197.9 275.9 198.9 275.9 200.7 M276.1 213.1 c-1.2 0.7-2.9 1.5-5.2 1.5c-2 0-3.6-0.9-4.3-2.9c-0.4-1.2-0.6-3.2-0.4-4.1c7.2 0.1 12.9-1.3 12.9-7c0-3.1-2-5.6-5.7-5.6 c-6.2 0-10.6 7.3-10.6 13.8c0 4.8 2.2 8.7 7.4 8.7c2.6 0 5-0.8 6.4-1.7L276.1 213.1z M245.1 217.1l1.8-10.8c0.8-4.8 4-8.1 6.6-8.1 c2.2 0 3 1.6 3 3.5c0 1.2-0.1 2.2-0.2 3l-2.2 12.4h3.3l2.2-12.6c0.2-1.1 0.3-2.5 0.3-3.6c0-4.3-2.6-5.8-4.8-5.8 c-2.9 0-5.3 1.6-6.9 4.3h-0.1l2.4-13.8h-3.3l-5.4 31.6H245.1z M215.4 195.5l1.8 21.5h3.1l5.6-11.8c0.9-1.9 1.4-3.5 2.2-5.6h0.1 c0 1.9 0.1 3.7 0.3 5.7l1.3 11.7h3.1l9.6-21.5H239l-5.2 12.4c-0.8 2.1-1.3 3.7-1.8 5.5h-0.1c0-1.4-0.1-3.2-0.3-5.6l-1.2-12.3h-2.9 l-5.7 12.5c-1 2.1-1.7 4-2.1 5.4h-0.1c0-1.7 0.1-3.1-0.1-5.8l-0.7-12.1H215.4z" class="style1"/></g><image src="<?php echo plugins_url( '/images/umw-primary-logo-white.png', __FILE__ ) ?>" xlink:href="" alt="University of Mary Washington"/></svg></a></div>
<?php
		}
		
		function do_footer_top() {
			if ( ! is_active_sidebar( 'global-footer-top' ) )
				return;
				
			print( '<aside class="global-footer-top widget-area sidebar">' );
			dynamic_sidebar( 'global-footer-top' );
			print( '</aside>' );
		}
		
		function do_footer_primary() {
			$sidebars = array();
			$footers = array( 'global-footer-1', 'global-footer-2', 'global-footer-3', 'global-footer-4' );
			foreach ( $footers as $f ) {
				if ( is_active_sidebar( $f ) ) {
					$sidebars[] = $f;
				}
			}
			
			$class = array( 'widget-area', 'sidebar' );
			
			switch( count( $sidebars ) ) {
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
			if ( ! is_active_sidebar( 'global-footer-bottom-1' ) && ! is_active_sidebar( 'global-footer-bottom-2' ) )
				return false;
			
			print( '<div class="secondary-global-footer">' );
			$class = array( 'widget-area sidebar' );
			if ( is_active_sidebar( 'global-footer-bottom-1' ) && is_active_sidebar( 'global-footer-bottom-2' ) ) {
				$class[] = 'one-half';
			}
			
			$first = true;
			foreach ( array( 'global-footer-bottom-1', 'global-footer-bottom-2' ) as $s ) {
				if ( ! is_active_sidebar( $s ) )
					continue;
				
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
			print( "\n<!-- UMW Global Header: version {$this->version} -->\n" );
			print( "\n<!-- UMW Global Header Styles -->\n" );
			$this->gather_styles();
			print( "\n<!-- / UMW Global Header Styles -->\n" );
			
			do_action( 'genesis_before' );
			$this->do_full_header();
			print( "\n<!-- / UMW Global Header -->\n" );
			exit();
		}
		
		function gather_styles() {
			global $wp_styles;
			if ( class_exists( 'Mega_Menu_Style_Manager' ) ) {
				$tmp = new Mega_Menu_Style_Manager;
				$css = $tmp->get_css();
				printf( '<style type="text/css" title="global-max-megamenu">%s</style>', $css );
			}
			$wp_styles->do_items( 'umw-online-tools' );
			do_action( 'umw-main-header-bar-styles' );
		}
		
		function gather_scripts() {
			if ( class_exists( 'UMW_Search_Engine' ) ) {
				UMW_Search_Engine::do_search_choices_js();
			}
			if ( class_exists( 'Mega_Menu_Style_Manager' ) ) {
				$tmp = new Mega_Menu_Style_Manager;
				$tmp->enqueue_scripts();
				
				global $wp_scripts;
				$wp_scripts->done[] = 'jquery';
				$wp_scripts->done[] = 'jquery-migrate';
				$wp_scripts->do_items( 'megamenu' );
			}
		}
		
		function get_footer_for_feed() {
			print( "\n<!-- UMW Global Footer: version {$this->version} -->\n" );
			$this->do_full_footer();
			print( "\n<!-- UMW Global Footer Scripts -->\n" );
			$this->gather_scripts();
			print( "\n<!-- / UMW Global Footer Scripts -->\n" );
			print( "\n<!-- / UMW Global Footer -->\n" );
			exit();
		}
	}
	
	define( 'WP_MANAGE_GLOBAL_HEADER_FOOTER', true );
	
	function inst_umw_outreach_mods_obj() {
		global $umw_outreach_mods_obj, $blog_id;
		if ( defined( 'WP_MANAGE_GLOBAL_HEADER_FOOTER' ) && WP_MANAGE_GLOBAL_HEADER_FOOTER && 1 === absint( $blog_id ) ) {
			$umw_outreach_mods_obj = new UMW_Outreach_Mods;
		} else {
			$umw_outreach_mods_obj = new UMW_Outreach_Mods_Sub;
		}
	}
	inst_umw_outreach_mods_obj();
}