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

			add_shortcode( 'expert-file-bio', array( $this, 'do_expertfile_shortcode' ) );
			add_shortcode( 'expert-file-list', array( $this, 'do_expertfile_shortcode' ) );
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
		 * @param $atts array the list of shortcode attributes
		 * @param $content string|null the content between shortcodes
		 * @param $name string the name of the shortcode
		 *
		 * @link https://expertfile.com/embeds/linking
		 *
		 * @access public
		 * @since  2019.4
		 * @return string the iframe with the ExpertFile embed
		 */
		public function do_expertfile_shortcode( $atts=array(), $content='', $name='expert-file-bio' ) {
			if ( defined( 'WP_DEBUG' ) ) {
				error_log( '[ExpertFile Debug]: Shortcode name: ' . $name );
			}

			$defaults = array(
				'font_family' => 'Open Sans, Helvetica Neue, Helvetica',
				'page_size' => 10,
				'access' => 'all',
				'content' => 'title,headline,expertise',
				'hide_search_bar' => 'yes',
				'hide_search_category' => 'no',
				'hide_search_sort' => 'no',
				'url_color' => '%23002b5a',
				'color' => '%23333333',
				'open_tab' => 'no',
				'avatar' => 'circle',
				'powered_by' => 'no',
				'channel' => '2c335699-55d3-49d3-8bd0-df86c24af20c',
			);

			if ( 'expert-file-bio' == $name ) {
				if ( ! isset( $_GET['expert'] ) || empty( $_GET['expert'] ) ) {
					return '<p>Unfortunately, we were not able to retrieve an expert by the name you provided. Please try again.</p>';
				}

				$src = 'https://embed.expertfile.com/v1/expert/' . $_GET['expert'] . '/1';
				$defaults['content'] = 'name';
				$defaults['hide_search_category'] = 'yes';
				$defaults['hide_search_sort'] = 'yes';
				$defaults['channel'] = '8c37b042-2e49-45e9-9c0e-0d68a0ae0a71';
				$defaults['expert'] = $_GET['expert'];
				$iframeID = 'embed-frame-featured';
			} else {
				$defaults['url_override'] = get_option( 'home' ) . '/expert/?expert={{username}}';

				$src = 'https://embed.expertfile.com/v1/organization/5322/1';
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
	}
}
