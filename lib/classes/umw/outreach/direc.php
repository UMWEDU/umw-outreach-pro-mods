<?php
/**
 * Special treatment for the Directory site
 */

namespace UMW\Outreach;

if ( ! class_exists( 'Direc' ) ) {
	class Direc extends Base {
		/**
		 * @var int $blog the ID of the Directory blog
		 */
		public $blog = 4;

		function __construct() {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Entered the ' . __CLASS__ . ' constructor' );
			}

			if ( defined( 'UMW_EMPLOYEE_DIRECTORY' ) && is_numeric( UMW_EMPLOYEE_DIRECTORY ) ) {
				$this->blog = UMW_EMPLOYEE_DIRECTORY;
			} else {
				return;
			}

			parent::__construct();

			if ( intval( $this->blog ) !== intval( $GLOBALS['blog_id'] ) ) {
				return;
			}

			/**
			 * Fix the employee/building/department archives until I find a better way to handle this
			 */
			add_action( 'template_redirect', array( $this, 'do_directory_archives' ) );
			add_filter( 'wpghs_whitelisted_post_types', function ( $types = array() ) {
				return array_merge( $types, array( 'employee', 'department', 'office', 'building' ) );
			} );

			add_filter( 'toolset_rest_run_exposure_filters', '__return_true' );
			add_filter( 'toolset_rest_expose_field_group', array( $this, 'expose_toolset_fields_to_api' ), 99, 5 );

			add_shortcode( 'expert-file-bio', array( $this, 'do_expertfile_shortcode' ) );
			add_shortcode( 'expert-file-list', array( $this, 'do_expertfile_shortcode' ) );
			add_action( 'init', array( $this, 'add_expert_rewrite_tag' ), 10, 0 );
			add_filter( 'the_posts', array( &$this, 'is_single_expert' ) );
		}

		/**
		 * Fix the directory post type archives
		 */
		function do_directory_archives() {
			if ( ! is_post_type_archive() && ! is_tax() ) {
				return;
			}

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
			foreach ( $args as $k => $v ) {
				if ( is_numeric( $v ) ) {
					$meat .= sprintf( ' %s=%d', $k, $v );
				} else {
					$meat .= sprintf( ' %s="%s"', $k, $v );
				}
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
				'post_type'      => 'building',
				'orderby'        => 'title',
				'order'          => 'asc',
				'posts_per_page' => - 1,
				'numberposts'    => - 1,
				'post_status'    => 'publish',
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
				'post_type'      => 'department',
				'orderby'        => 'title',
				'order'          => 'asc',
				'posts_per_page' => - 1,
				'numberposts'    => - 1,
				'post_status'    => 'publish',
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
			return __( 'Campuses and Buildings' );
		}

		/**
		 * Handle an ExpertFile embed
		 *
		 * @param $atts array the list of shortcode attributes
		 * @param $content string|null the content between shortcodes
		 * @param $name string the name of the shortcode
		 *
		 * @return string the iframe with the ExpertFile embed
		 * @since  2019.4
		 * @link https://expertfile.com/embeds/linking
		 *
		 * @access public
		 */
		public function do_expertfile_shortcode( $atts = array(), $content = '', $name = 'expert-file-bio' ) {
			if ( defined( 'WP_DEBUG' ) ) {
				error_log( '[ExpertFile Debug]: Shortcode name: ' . $name );
			}

			$defaults = array(
				'font_family'          => 'Open Sans, Helvetica Neue, Helvetica',
				'page_size'            => 10,
				'access'               => 'all',
				'content'              => 'title,headline,expertise',
				'hide_search_bar'      => 'yes',
				'hide_search_category' => 'no',
				'hide_search_sort'     => 'no',
				'url_color'            => '%23002b5a',
				'color'                => '%23333333',
				'open_tab'             => 'no',
				'avatar'               => 'circle',
				'powered_by'           => 'no',
				'channel'              => '2c335699-55d3-49d3-8bd0-df86c24af20c',
			);

			if ( 'expert-file-bio' == $name ) {
				$expert = get_query_var( 'expert' );

				if ( empty( $expert ) ) {
					return '<p>Unfortunately, we were not able to retrieve an expert by the name you provided. Please try again.</p>';
				}

				if ( 1 == get_query_var( 'contact' ) ) {
					$src = 'https://embed.expertfile.com/v1/inquiry/' . $expert . '/1';
				} else {
					$src                      = 'https://embed.expertfile.com/v1/expert/' . $expert . '/1';
					$defaults['url_override'] = get_option( 'home' ) . '/experts/expert/' . $expert . '/inquiry/';
				}
				$defaults['content']              = 'name';
				$defaults['hide_search_category'] = 'yes';
				$defaults['hide_search_sort']     = 'yes';
				$defaults['channel']              = '8c37b042-2e49-45e9-9c0e-0d68a0ae0a71';
				$defaults['expert']               = $expert;
				$iframeID                         = 'embed-frame-featured';
			} else {
				$defaults['url_override'] = get_option( 'home' ) . '/experts/expert/{{username}}';

				$src      = 'https://embed.expertfile.com/v1/organization/5322/1';
				$iframeID = 'embed-frame-directory';
			}

			$atts = shortcode_atts( $defaults, $atts, $name );

			$url = add_query_arg( $atts, $src );

			$script = <<<EOD
var SF = SF || {}; SF.featured = document.getElementById('{$iframeID}'), s = new SeamLess({ window : SF.featured .contentWindow, origin : '*' }); s.receiveHeight({ channel : "{$atts['channel']}" }, function(height){ SF.featured.style.height = height + 'px';});
EOD;

			$output = sprintf( '<iframe id="%1$s" class="embed_preview" frameborder="0" scrolling="no" style="border: none; width: 100%;" src="%2$s"></iframe>', $iframeID, $url );
			$output .= sprintf( '<script type="text/javascript" src="%s"></script>', '//d2mo5pjlwftw8w.cloudfront.net/embed/seamless.ly.min.v1.0.4.js' );
			$output .= sprintf( '<script type="text/javascript">%s</script>', $script );

			return $output;
		}

		/**
		 * Register a new URL rewrite tag for individual experts
		 *
		 * @access public
		 * @return void
		 * @since  2.0
		 */
		public function add_expert_rewrite_tag() {
			add_rewrite_tag( '%expert%', '([^&]+)' );
			add_rewrite_tag( '%inquiry%', '([^&]+)' );
			add_rewrite_rule( '^experts/expert/([^/]*)/inquiry/?', 'index.php?expert=$matches[1]&contact=1&inquiry=1', 'top' );
			add_rewrite_rule( '^experts/expert/([^/]*)/inquiry/([^/]*)/?', 'index.php?expert=$matches[1]&contact=1&inquiry=1', 'top' );
			add_rewrite_rule( '^experts/expert/([^/]*)/?', 'index.php?expert=$matches[1]', 'top' );
		}

		/**
		 * Test to see if this is supposed to display a single ExpertFile profile
		 *
		 * @param $posts array the existing list of queried posts
		 *
		 * @access public
		 * @return array|null
		 * @since  2.0
		 */
		public function is_single_expert( $posts ) {
			global $wp, $wp_query;

			if ( isset( $wp->query_vars['expert'] ) ) {

				remove_all_actions( 'genesis_entry_header' );

				$posts   = null;
				$posts[] = $this->create_expert_post();

				/**
				 * Trick wp_query into thinking this is a page (necessary for wp_title() at least)
				 * Not sure if it's cheating or not to modify global variables in a filter
				 * but it appears to work and the codex doesn't directly say not to.
				 */
				$wp_query->is_page = true;
				//Not sure if this one is necessary but might as well set it like a true page
				$wp_query->is_singular = true;
				$wp_query->is_home     = false;
				$wp_query->is_archive  = false;
				$wp_query->is_category = false;
				//Longer permalink structures may not match the fake post slug and cause a 404 error so we catch the error here
				unset( $wp_query->query["error"] );
				$wp_query->query_vars["error"] = "";
				$wp_query->is_404              = false;
			}

			if ( isset( $wp_query->query_vars['inquiry'] ) ) {
				add_filter( 'genesis_build_crumbs', array( $this, 'expertfile_inquiry_breadcrumb' ), 99, 2 );
				set_query_var( 'contact', 1 );
			}

			return $posts;
		}

		/**
		 * Generate a fake \WP_Post object for the individual expert profile
		 *
		 * @access public
		 * @return bool|\stdClass
		 * @since  2.0
		 */
		public function create_expert_post() {
			global $wp_query;

			/**
			 * What we are going to do here, is create a fake post.  A post
			 * that doesn't actually exist. We're gonna fill it up with
			 * whatever values you want.  The content of the post will be
			 * the output from your plugin.
			 */

			$tmp = get_page_by_path( '/experts/expert/' );
			if ( ! is_a( $tmp, '\WP_Post' ) ) {
				return false;
			}

			$title   = $this->get_expert_title();
			$content = $tmp->post_content;

			if ( false === $title || empty( $title ) ) {
				$title = __( 'Faculty Expert Profile' );
			}

			/**
			 * Create a fake post.
			 */
			$post = new \stdClass;

			/**
			 * The author ID for the post.  Usually 1 is the sys admin.  Your
			 * plugin can find out the real author ID without any trouble.
			 */
			$post->post_author = 1;

			/**
			 * The safe name for the post.  This is the post slug.
			 */
			$post->post_name = get_query_var( 'expert' );

			/**
			 * Not sure if this is even important.  But gonna fill it up anyway.
			 */
			$post->guid = get_bloginfo( 'wpurl' ) . '/expert/' . get_query_var( 'expert' );


			/**
			 * The title of the page.
			 */
			$post->post_title = $title;

			/**
			 * This is the content of the post.  This is where the output of
			 * your plugin should go.  Just store the output from all your
			 * plugin function calls, and put the output into this var.
			 */
			$post->post_content = $tmp->post_content;

			/**
			 * Fake post ID to prevent WP from trying to show comments for
			 * a post that doesn't really exist.
			 */
			$post->ID = - 1;

			/**
			 * Static means a page, not a post.
			 */
			$post->post_status = 'static';

			/**
			 * Turning off comments for the post.
			 */
			$post->comment_status = 'closed';

			/**
			 * Let people ping the post?  Probably doesn't matter since
			 * comments are turned off, so not sure if WP would even
			 * show the pings.
			 */
			$post->ping_status = 'closed';

			$post->comment_count = 0;

			/**
			 * You can pretty much fill these up with anything you want.  The
			 * current date is fine.  It's a fake post right?  Maybe the date
			 * the plugin was activated?
			 */
			$post->post_date     = current_time( 'mysql' );
			$post->post_date_gmt = current_time( 'mysql', 1 );

			$post->post_parent = $tmp->ID;

			return ( $post );
		}

		/**
		 * Retrieve the title of the individual expert profile
		 *
		 * @param $parent bool whether to force the title for the main expert profile page
		 *
		 * @access public
		 * @return bool|string
		 * @since  2.0
		 */
		public function get_expert_title( $parent = false ) {
			global $wp_query;

			$expert = get_query_var( 'expert' );
			if ( empty( $expert ) ) {
				return false;
			}

			$title = false;

			if ( isset( $wp_query->query_vars['inquiry'] ) && false === $parent ) {
				$urlpage = 'https://embed.expertfile.com/v1/inquiry/' . $expert . '/1';
			} else {
				$urlpage = 'https://embed.expertfile.com/v1/expert/' . $expert . '/1';
			}

			$dom = new \DOMDocument();
			if ( $dom->loadHTMLFile( $urlpage ) ) {
				$list = $dom->getElementsByTagName( "title" );
				if ( $list->length > 0 ) {
					$title = $list->item( 0 )->textContent;
				}
			}

			return $title;
		}

		/**
		 * Modify the breadcrumbs for an ExpertFile inquiry page
		 *
		 * @param $crumbs array the existing list of breadcrumbs
		 * @param $args array the existing list of arguments
		 *
		 * @access public
		 * @return array the updated list of breadcrumbs
		 * @since  2.0
		 */
		public function expertfile_inquiry_breadcrumb( $crumbs, $args ) {
			$current = array_pop( $crumbs );
			$list    = explode( $args['sep'], $current );
			$current = array_pop( $list );

			$url      = get_option( 'home' ) . '/experts/expert/' . get_query_var( 'expert' ) . '/';
			$current  = sprintf( '<a href="%1$s">%2$s</a>', $url, $this->get_expert_title( true ) );
			$crumbs[] = implode( $args['sep'], $list );
			$crumbs[] = $current;
			$crumbs[] = __( 'Contact This Expert' );

			return $crumbs;
		}

		/**
		 * Attempt to expose Types Custom Fields to the REST API
		 * @param $expose_field_group bool True by default.
		 * @param $domain string Domain of the field group: 'posts', 'users' or 'terms'.
		 * @param $group_slug string Slug of the custom field group.
		 * @param $element_type mixed Type of the element for which we're deciding. Depending on the domain, this can be:
		 * - post type slug
		 * - taxonomy slug
		 * - user role name or an array with user role names
		 * @param $element_id int ID of the element.
		 *
		 * @access public
		 * @return bool whether to expose the field group or not
		 * @since  0.1
		 */
		public function expose_toolset_fields_to_api( $expose_field_group=true, $domain='', $group_slug='', $element_type='', $element_id=0 ) {
			if ( 'posts' === $domain ) {
				if ( 'department' === $element_type ) {
					return true;
				}
				if ( 'employee' === $element_type ) {
					return true;
				}
				if ( 'building' === $element_type ) {
					return true;
				}
			}

			return false;
		}
	}
}
