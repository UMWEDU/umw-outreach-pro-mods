<?php
/**
 * Special treatment for the Directory site
 */
if ( ! class_exists( 'UMW_Outreach_Mods_Directory' ) ) {
	class UMW_Outreach_Mods_Directory extends UMW_Outreach_Mods_Sub {
		/**
		 * @var int $blog the ID of the Directory blog
		 */
		public $blog = 4;
		
		function __construct() {
			parent::__construct();
			
			if ( intval( $this->blog ) !== intval( $GLOBALS['blog_id'] ) ) {
				return;
			}
			
			/**
			 * Fix the employee/building/department archives until I find a better way to handle this
			 */
			add_action( 'template_redirect', array( $this, 'do_directory_archives' ) );
		}
		
		/**
		 * Fix the directory post type archives
		 */
		function do_directory_archives() {
			if ( ! is_post_type_archive() && ! is_tax() )
				return;
			
			if ( is_post_type_archive( 'employee' ) || is_tax( 'employee-type' ) ) {
				remove_action( 'genesis_loop', 'genesis_do_loop' );
				add_action( 'genesis_loop', array( $this, 'do_employee_loop' ) );
			} else if ( is_post_type_archive( 'building' ) ) {
				remove_action( 'genesis_loop', 'genesis_do_loop' );
				add_action( 'genesis_loop', array( $this, 'do_building_loop' ) );
			} else if ( is_post_type_archive( 'department' ) ) {
				remove_action( 'genesis_loop', 'genesis_do_loop' );
				add_action( 'genesis_loop', array( $this, 'do_department_loop' ) );
			}
		}
		
		/**
		 * Output the employee A to Z list
		 */
		function do_employee_loop() {
			$args = array(
				'post_type' => 'employee', 
				'field'     => 'wpcf-last-name', 
				'view'      => 363
			);
			if ( is_tax( 'employee-type' ) ) {
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
			$content = do_shortcode( sprintf( '[atoz%s]', $meat ) );
			
			add_filter( 'genesis_post_title_text', array( $this, 'do_employee_archive_title' ) );
			add_filter( 'genesis_link_post_title', array( $this, '_return_false' ) );
			$this->custom_genesis_loop( $content );
			remove_filter( 'genesis_link_post_title', array( $this, '_return_false' ) );
			remove_filter( 'genesis_post_title_text', array( $this, 'do_employee_archive_title' ) );
		}
		
		/**
		 * Output the list of buildings
		 */
		function do_building_loop() {
			$args = array( 
				'post_type'   => 'building', 
				'orderby'     => 'title', 
				'order'       => 'asc', 
				'posts_per_page' => -1, 
				'numberposts' => -1, 
				'post_status' => 'publish', 
			);
			
			query_posts( $args );
			
			$content = render_view( array( 'id' => 79 ) );
			
			wp_reset_postdata();
			wp_reset_query();
			
			add_filter( 'genesis_post_title_text', array( $this, 'do_building_archive_title' ) );
			add_filter( 'genesis_link_post_title', array( $this, '_return_false' ) );
			$this->custom_genesis_loop( $content );
			remove_filter( 'genesis_link_post_title', array( $this, '_return_false' ) );
			remove_filter( 'genesis_post_title_text', array( $this, 'do_building_archive_title' ) );
			
			return;
		}
		
		/**
		 * Render the Departments archive page
		 */
		function do_department_loop() {
			$args = array( 
				'post_type'   => 'department', 
				'orderby'     => 'title', 
				'order'       => 'asc', 
				'posts_per_page' => -1, 
				'numberposts' => -1, 
				'post_status' => 'publish', 
			);
			
			query_posts( $args );
			
			$content = render_view( array( 'id' => 82 ) );
			
			wp_reset_postdata();
			wp_reset_query();
			
			add_filter( 'genesis_post_title_text', array( $this, 'do_department_archive_title' ) );
			add_filter( 'genesis_link_post_title', array( $this, '_return_false' ) );
			$this->custom_genesis_loop( $content );
			remove_filter( 'genesis_link_post_title', array( $this, '_return_false' ) );
			remove_filter( 'genesis_post_title_text', array( $this, 'do_department_archive_title' ) );
			
			return;
		}
		
		/**
		 * Return the title for the employee archive page
		 */
		function do_employee_archive_title( $title ) {
			if ( is_tax( 'employee-type' ) ) {
				$ob = get_queried_object();
				if ( is_object( $ob ) && ! is_wp_error( $ob ) ) {
					return __( sprintf( '%s A to Z', $ob->name ) );
				}
			}
			return __( 'Employees A to Z' );
		}
		
		/**
		 * Return the title for the department archive page
		 */
		function do_department_archive_title( $title ) {
			return __( 'Departments' );
		}
		
		/**
		 * Returnt he title for the building archive page
		 */
		function do_building_archive_title( $title ) {
			return __( 'Buildings' );
		}
		
	}
}