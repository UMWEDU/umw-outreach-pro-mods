<?php
/**
 * Special treatment for the News site
 */

namespace UMW\Outreach;

if ( ! class_exists( 'News' ) ) {
	class News extends Base {
		/**
		 * @var int $blog the ID of the News site
		 */
		public $blog = 7;
		/**
		 * @var string $plugin_path the root path to this plugin
		 * @access public
		 */
		public static $plugin_path = '';
		/**
		 * @var string $plugin_url the root URL to this plugin
		 * @access public
		 */
		public static $plugin_url = '';

		function __construct() {
			parent::__construct();

			if ( intval( $this->blog ) !== intval( $GLOBALS['blog_id'] ) ) {
				return;
			}

			add_action( 'plugins_loaded', array( $this, 'setup_acf' ), 9 );

			add_action( 'init', array( $this, 'add_topic_to_rest' ), 25 );
			add_action( 'rest_api_init', array( $this, 'add_category_thumbnail_to_rest' ) );
			add_filter( 'rest_prepare_topic', array( $this, 'add_category_thumbnail_link'), 11, 3 );
		}

		/**
		 * Custom logging function that can be short-circuited
		 *
		 * @access public
		 * @since  0.1
		 * @return void
		 */
		public static function log( $message ) {
			if ( ! defined( 'WP_DEBUG' ) || false === WP_DEBUG ) {
				return;
			}

			error_log( '[HSU Debug]: ' . $message );
		}

		/**
		 * Set the root path to this plugin
		 *
		 * @access public
		 * @since  1.0
		 * @return void
		 */
		public static function set_plugin_path() {
			self::$plugin_path = plugin_dir_path( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) );
		}

		/**
		 * Set the root URL to this plugin
		 *
		 * @access public
		 * @since  1.0
		 * @return void
		 */
		public static function set_plugin_url() {
			self::$plugin_url = plugin_dir_url( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) );
		}

		/**
		 * Returns an absolute path based on the relative path passed
		 *
		 * @param string $path the path relative to the root of this plugin
		 *
		 * @access public
		 * @since  1.0
		 * @return string the absolute path
		 */
		public static function plugin_dir_path( $path = '' ) {
			if ( empty( self::$plugin_path ) ) {
				self::set_plugin_path();
			}

			$rt = self::$plugin_path;

			if ( '/' === substr( $path, - 1 ) ) {
				$rt = untrailingslashit( $rt );
			}

			return $rt . $path;
		}

		/**
		 * Returns an absolute URL based on the relative path passed
		 *
		 * @param string $url the URL relative to the root of this plugin
		 *
		 * @access public
		 * @since  1.0
		 * @return string the absolute URL
		 */
		public static function plugin_dir_url( $url = '' ) {
			if ( empty( self::$plugin_url ) ) {
				self::set_plugin_url();
			}

			$rt = self::$plugin_url;

			if ( '/' === substr( $url, - 1 ) ) {
				$rt = untrailingslashit( $rt );
			}

			return $rt . $url;
		}

		/**
		 * Make the "Topic" taxonomy available in the REST API
		 *
		 * @access public
		 * @since  2018.05
		 * @return void
		 */
		public function add_topic_to_rest() {
			global $wp_taxonomies;
			if ( array_key_exists( 'topic', $wp_taxonomies ) ) {
				$wp_taxonomies['topic']->show_in_rest = true;
				$wp_taxonomies['topic']->rest_base = 'topics';
				$wp_taxonomies['topic']->rest_controller_class = 'WP_REST_Terms_Controller';
			}
		}

		/**
		 * Add the category thumbnail to taxonomy REST API requests
		 */
		public function add_category_thumbnail_to_rest() {
			register_rest_field(
				'topic',
				'featured_media',
				array(
					'get_callback' => array( $this, 'get_category_thumbnail_id' )
				)
			);
			register_rest_field(
				'topic',
				'thumbnail',
				array(
					'get_callback' => array( $this, 'get_category_thumbnail' )
				)
			);
			register_rest_field(
				'topic',
				'title',
				array(
					'get_callback' => array( $this, 'get_slideshow_category_title' )
				)
			);
			register_rest_field(
				'topic',
				'excerpt',
				array(
					'get_callback' => array( $this, 'get_slideshow_category_excerpt' )
				)
			);
		}

		/**
		 * Retrieve information about the category/taxonomy term thumbnail
		 * @param $cat array the array of taxonomy term information
		 *
		 * @access public
		 * @since  2018.05
		 * @return bool|int false on failure or the ID of the image on success
		 */
		public function get_category_thumbnail( $cat ) {
			$thumb = get_field( 'topic-featured_image', sprintf( 'term_%d', $cat['id'] ), false );
			if ( empty( $thumb ) ) {
				return false;
			}

			return absint( $thumb );
		}

		/**
		 * Retrieve information about the category/taxonomy term thumbnail
		 * @param $cat array the array of taxonomy term information
		 *
		 * @access public
		 * @since  2018.05
		 * @return bool|int false on failure or the ID of the image on success
		 */
		public function get_category_thumbnail_id( $cat ) {
			return $this->get_category_thumbnail( $cat );
		}

		/**
		 * Add appropriate items to the _links collection in the REST API for the thumbnail image
		 * @param $data \WP_REST_Response the REST response information being modified
		 * @param $term \WP_Term the term being queried
		 * @param $request \WP_REST_Request the REST request being processed
		 *
		 * @return \WP_REST_Response the modified response
		 */
		public function add_category_thumbnail_link( $data, $term, $request ) {
			if ( 'topic' != $term->taxonomy ) {
				return $data;
			}

			$featured_media = $this->get_category_thumbnail_id( array( 'id' => $term->term_id ) );

			$image_url = rest_url( 'wp/v2/media/' . $featured_media );

			$links['https://api.w.org/featuredmedia'] = array(
				'href'       => $image_url,
				'embeddable' => true,
			);

			$data->add_links(
				$links
			);

			return $data;
		}

		/**
		 * Retrieve the caption title for the slideshow image
		 * @param $cat array the information about the taxonomy term being processed
		 *
		 * @access public
		 * @since 2018.05
		 * @return bool|array the title with key "rendered"
		 */
		public function get_slideshow_category_title( $cat ) {
			$title = get_field( 'topic-slide_title', sprintf( 'term_%d', $cat['id'] ) );
			if ( empty( $title ) ) {
				return false;
			}

			$title = sprintf( '%s <span class="campaign-tag">@MaryWash</span>', $title );

			return array(
				'rendered' => $title,
			);
		}

		/**
		 * Retrieve the caption excerpt/text for the slideshow image
		 * @param $cat array the information about the taxonomy term being processed
		 *
		 * @access public
		 * @since  2018.05
		 * @return bool|array the excerpt with key "rendered" and "protected" false
		 */
		public function get_slideshow_category_excerpt( $cat ) {
			$excerpt = get_field( 'topic-slide_caption', sprintf( 'term_%d', $cat['id'] ) );
			if ( empty( $excerpt ) ) {
				return false;
			}

			self::log( $cat );

			$excerpt = sprintf( '<div class="caption-text">%1$s</div><footer><a href="%2$s" title="%3$s">%4$s</a></footer>', $excerpt, get_term_link( $cat['id'] ), __( 'View stories about ', 'umw-outreach-mods' ) . $cat['name'], __( 'Learn more', 'umw-outreach-mods' ) );

			return array(
				'rendered' => $excerpt,
				'protected' => false,
			);
		}

		/**
		 * Setup Advanced Custom Fields
		 *
		 * @access public
		 * @since  0.1
		 * @return void
		 */
		public function setup_acf() {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
			}

			if ( is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) || is_plugin_active_for_network( 'advanced-custom-fields-pro/acf.php' ) ) {
				include_once( $this->plugin_dir_path( 'lib/includes/custom-fields.php' ) );

				return;
			}

			if ( class_exists( '\ACF' ) ) {
				self::log( 'The ACF class already exists, so we will avoid including the information again' );

				return;
			}

			add_filter( 'acf/settings/path', array( $this, 'acf_path' ) );
			add_filter( 'acf/settings/dir', array( $this, 'acf_url' ) );
			add_filter( 'acf/settings/show_admin', '__return_false' );
			self::log( 'Preparing to include ACF core from ' . $this->plugin_dir_path( 'lib/classes/acf/acf.php' ) );
			self::log( 'Preparing to include custom field definitions from ' . $this->plugin_dir_path( 'lib/includes/custom-fields.php' ) );
			require_once( $this->plugin_dir_path( 'lib/classes/acf/acf.php' ) );
			require_once( $this->plugin_dir_path( 'lib/includes/topic-slideshow-custom-fields.php' ) );
		}

		/**
		 * Alter the ACF path
		 *
		 * @param string $path the current path
		 *
		 * @access public
		 * @since  1.0
		 * @return string the altered path
		 */
		public function acf_path( $path = '' ) {
			return $this->plugin_dir_path( '/lib/classes/acf/' );
		}

		/**
		 * Alter the ACF URL
		 *
		 * @param string $url the current URL
		 *
		 * @access public
		 * @since  1.0
		 * @return string the updated URL
		 */
		public function acf_url( $url = '' ) {
			return $this->plugin_dir_url( '/lib/classes/acf/' );
		}
	}
}