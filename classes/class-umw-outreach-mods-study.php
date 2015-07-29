<?php
/**
 * Special treatment for the Areas of Study site
 */
if ( ! class_exists( 'UMW_Outreach_Mods_Study' ) ) {
	class UMW_Outreach_Mods_Study extends UMW_Outreach_Mods_Sub {
		/**
		 * @var int $blog the ID of the Directory blog
		 */
		public $blog = 5;
		
		function __construct() {
			parent::__construct();
			
			if ( intval( $this->blog ) !== intval( $GLOBALS['blog_id'] ) ) {
				return;
			}
			
			/**
			 * Fix the Areas of Study archives until I find a better way to handle this
			 */
			add_action( 'template_redirect', array( $this, 'do_study_archives' ) );
		}
		
		/**
		 * Fix the Areas of Study post type archives
		 */
		function do_study_archives() {
			if ( ! is_post_type_archive() && ! is_tax() )
				return;
			
			if ( is_post_type_archive( 'areas' ) || is_tax( 'key' ) || is_tax( 'department' ) ) {
				remove_action( 'genesis_loop', 'genesis_do_loop' );
				add_action( 'genesis_loop', array( $this, 'do_study_loop' ) );
			}
		}
		
		/**
		 * Output the Areas of Study A to Z list
		 */
		function do_study_loop() {
			$args = array(
				'post_type' => 'areas', 
				'view'      => 106, 
				'return_link' => 0, 
				'alpha_links' => 0, 
			);
			if ( is_tax( 'key' ) || is_tax( 'department' ) ) {
				$ob = get_queried_object();
				if ( is_object( $ob ) && ! is_wp_error( $ob ) ) {
					$args['tax_name'] = $ob->taxonomy;
					$args['tax_term'] = $ob->slug;
				}
			}
			$meat = '';
			foreach ( $args as $k=>$v ) {
				if ( is_numeric( $v ) )
					$meat .= sprintf( ' %s=%d', $k, $v );
				else
					$meat .= sprintf( ' %s="%s"', $k, $v );
			}
			$short = sprintf( '[atoz%s]', $meat );
			$content = do_shortcode( $short );
			
			add_filter( 'genesis_post_title_text', array( $this, 'do_study_archive_title' ) );
			add_filter( 'genesis_link_post_title', array( $this, '_return_false' ) );
			$this->custom_genesis_loop( $content );
			remove_filter( 'genesis_link_post_title', array( $this, '_return_false' ) );
			remove_filter( 'genesis_post_title_text', array( $this, 'do_study_archive_title' ) );
		}
		
		/**
		 * Return the title for the Areas of Study archive page
		 */
		function do_study_archive_title( $title ) {
			if ( is_tax( 'key' ) ) {
				$ob = get_queried_object();
				if ( is_object( $ob ) && ! is_wp_error( $ob ) ) {
					return __( sprintf( '%s A to Z', $ob->name ) );
				}
			} else if ( is_tax( 'department' ) ) {
				$ob = get_queried_object();
				if ( is_object( $ob ) && ! is_wp_error( $ob ) ) {
					return __( sprintf( 'Areas of Study in %s', $ob->name ) );
				}
			}
			
			return __( 'Areas of Study A to Z' );
		}
	}
}
