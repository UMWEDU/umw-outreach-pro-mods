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
		/**
		 * @var int the number of social media posts to display in the sidebar
		 * @access private
		 */
		private $latest_social_posts_count = 1;
		/**
		 * @var int the current social media post being displayed
		 * @access private
		 */
		private static $latest_social_posts_counter = 0;

		function __construct() {
			parent::__construct();

			if ( intval( $this->blog ) !== intval( $GLOBALS['blog_id'] ) ) {
				return;
			}

			add_action( 'plugins_loaded', array( $this, 'setup_acf' ), 9 );

			add_action( 'init', array( $this, 'add_topic_to_rest' ), 25 );
			add_action( 'rest_api_init', array( $this, 'add_category_thumbnail_to_rest' ) );
			add_filter( 'rest_prepare_topic', array( $this, 'add_category_thumbnail_link'), 11, 3 );

			add_action( 'wp_print_styles', array( $this, 'custom_styles' ) );
			add_shortcode( 'latest-social-posts', array( $this, 'do_latest_social_posts' ) );
		}

		/**
		 * Make any changes to Genesis that need to be made specifically for the News site
		 *
		 * @access public
		 * @since  2018.05
		 * @return void
		 */
		public function genesis_tweaks() {
			parent::genesis_tweaks();

			remove_action( 'genesis_before_content', 'genesis_do_breadcrumbs' );
			add_action( 'genesis_before_content', array( $this, 'topic_navigation' ) );
		}

		/**
		 * Output any custom CSS for the News site
		 *
		 * @access public
		 * @since  2018.05
		 * @return void
		 */
		public function custom_styles() {
?>
			<style type="text/css">
				.home-top .widget {padding: 0;}
				.home-top .widget:first-child {padding-top: 0px;}
				#genesis-sidebar-secondary h2.widget-title {color: #fff;}
				div.home-bottom span.entry-comments-link {display: none;}
				h2.entry-title a {color: #b81237;}
				h2.entry-title a:hover {color: #4C6A8B; text-decoration: underline;}
			</style>
			<?php
		}

		/**
		 * Output the Topic Navigation menu at the top of the page
		 *
		 * @access public
		 * @since  2018.05
		 * @return void
		 */
		public function topic_navigation() {
			if ( ! function_exists( 'render_view' ) ) {
				genesis_do_breadcrumbs();
				return;
			}

			if ( !is_page() ) {
				echo render_view( array( 'id' => 87856 ) );
			}

			genesis_do_breadcrumbs();
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

			error_log( '[News Sitee Debug]: ' . $message );
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

		/**
		 * Retrieve and output the most recent Instagram item
		 *
		 * @access private
		 * @since  0.1
		 * @return string the HTML for the Instagram item
		 */
		private function do_instagram_post() {
			$instagram_name = '';
			if ( function_exists( 'get_field' ) )
				$instagram_name = get_field( 'umwnews_social_instagram_username' );

			if ( empty( $instagram_name ) ) {
				$instagram_name = 'uofmarywashington';
			}

			$instagram_name = $this->get_instagram_id( $instagram_name );
			do_shortcode( sprintf( '[fts_instagram instagram_id=%1$d super_gallery=no pics_count=%2$d image_size=250 icon_size=50 hide_date_likes_comments=yes profile_photo=no profile_stats=no profile_name=no profile_description=no]', $instagram_name, $this->latest_social_posts_count ) );

			$posts = get_transient( sprintf( 'fts_instagram_cache_%1$d_num%2$d', $instagram_name, $this->latest_social_posts_count ) );
			if ( false !== $posts ) {
				if ( array_key_exists( 'data', $posts ) ) {
					$posts = json_decode( $posts['data'] );
					if ( property_exists( $posts, 'data' ) ) {
						$posts = $posts->data;
					}
				}
			}

			$link = $posts[self::$latest_social_posts_counter]->link;
			$imgurl = $posts[self::$latest_social_posts_counter]->images->low_resolution->url;
			$caption = $posts[self::$latest_social_posts_counter]->caption->text;

			/*$link = 'https://www.instagram.com/p/BXqlt0ClJqj/?taken-by=marywash';
			$imgurl = 'https://scontent-iad3-1.cdninstagram.com/t51.2885-15/s640x640/sh0.08/e35/c135.0.810.810/20766551_111200026226263_4624646013523591168_n.jpg';
			$caption = 'This is an Instagram caption';*/

			return sprintf( '
	<a href="%1$s" class="recent-social instagram">
		<figure style="background-image: url(%2$s)">
			<figcaption>
				%3$s
			</figcaption>
		</figure>
	</a>', $link, $imgurl, $caption );
		}

		/**
		 * Attempt to convert an Instagram username into an Instagram ID
		 * @param $name string the username being converted
		 *
		 * @access private
		 * @since  0.1
		 * @return bool|int the user ID or false on failure
		 */
		private function get_instagram_id( $name ) {
			$id = get_option( 'umwnews_instagram_id_'. $name, false );
			if ( false !== $id ) {
				return $id;
			}

			$url = 'https://api.instagram.com/v1/users/search';
			$args = array(
				'q' => $name,
				'client_id' => '9844495a8c4c4c51a7c519d0e7e8f293',
				'access_token' => '258559306.da06fb6.c222db6f1a794dccb7a674fec3f0941f',
				'callback' => '?',
			);
			$url = add_query_arg( $args, $url );

			$response = wp_remote_get( $url );
			if ( is_wp_error( $response ) ) {
				self::log( 'Error retrieving Instagram ID: ' . $response->get_error_message() );
				return false;
			}

			$body = @json_decode( wp_remote_retrieve_body( $response ) );
			if ( is_object( $body ) && property_exists( $body, 'data' ) ) {
				$accounts = $body->data;
				foreach ( $accounts as $acct ) {
					if ( strtolower( $name ) == strtolower( $acct->username ) ) {
						update_option( 'umwnews_instagram_id_'. $name, $acct->id );
						return $acct->id;
					}
				}
			}

			return false;
		}

		/**
		 * Retrieve and output the most recent Facebook post
		 *
		 * @access private
		 * @since  0.1
		 * @return string the HTML for the Facebook item
		 */
		private function do_facebook_post() {
			$facebook_name = '';
			if ( function_exists( 'get_field' ) )
				$facebook_name = get_field( 'umwnews_social_facebook_username' );

			if ( empty( $facebook_name ) ) {
				$facebook_name = 'UniversityofMaryWashington';
			}

			do_shortcode( sprintf( '[fts_facebook type=page id=%1$s posts=%2$d posts_displayed=page_only]', $facebook_name, $this->latest_social_posts_count ) );

			/* For some reason, the current version of FTS seems to be adding 1 to the number of posts in the cache for Facebook */
			$posts = get_transient( sprintf( 'fts_fb_page_%1$s_num%2$d', $facebook_name, $this->latest_social_posts_count ) );
			if ( false === $posts ) {
				$posts = get_transient( sprintf( 'fts_fb_page_%1$s_num%2$d', $facebook_name, ( $this->latest_social_posts_count + 1 ) ) );
			}
			if ( false !== $posts ) {
				if ( array_key_exists( 'feed_data', $posts ) ) {
					$posts = json_decode( $posts['feed_data'] );
					if ( property_exists( $posts, 'data' ) ) {
						$posts = $posts->data;
					}
				}
			}


			$link = $posts[self::$latest_social_posts_counter]->link;
			$caption = $posts[self::$latest_social_posts_counter]->message;

			if ( strlen( $caption ) > 150 ) {
				$arr = explode( ' ', $caption );
				while ( strlen( $caption ) > 150 ) {
					array_pop( $arr );
					$caption = implode( ' ', $arr );
				}

				$caption .= '&hellip;';
			}

			$date = $posts[self::$latest_social_posts_counter]->created_time;
			$date = date( 'F j g:ia', strtotime( $date ) );
			/*$caption = '<span class="dashicons dashicons-facebook"></span><div class="_5pbx userContent" data-ft="{&quot;tn&quot;:&quot;K&quot;}" id="js_jd"><p>It\'s <a class="_58cn" href="/hashtag/nationalrelaxationday?source=feed_text&amp;story_id=10155626650646660" data-ft="{&quot;tn&quot;:&quot;*N&quot;,&quot;type&quot;:104}"><span class="_5afx"><span aria-label="hashtag" class="_58cl _5afz">#</span><span class="_58cm">NationalRelaxationDay</span></span></a>. Which of these is your favorite Mary Wash way to relax?</p><p> A) Hanging out by the fountain<br> B) Bench-sitting on Campus Walk<br> C) Kicking back on Ball Circle</p></div>';*/

			return sprintf( '
	<a href="%3$s" class="recent-social facebook">
		<figure>
			<figcaption>
				%1$s
				<footer class="datetime">%2$s</footer>
			</figcaption>
		</figure>
	</a>', $caption, $date, $link );
		}

		/**
		 * Retrieve and output the most recent tweet
		 *
		 * @access private
		 * @since  0.1
		 * @return string the HTML for the tweet
		 */
		private function do_recent_tweet() {
			$twitter_name = '';
			if ( function_exists( 'get_field' ) )
				$twitter_name = get_field( 'umwnews_social_twitter_username' );

			if ( empty( $twitter_name ) ) {
				$twitter_name = 'UMaryWash';
			}

			do_shortcode( sprintf( '[fts_twitter twitter_name=%1$s tweets_count=%2$d cover_photo=no stats_bar=no show_retweets=yes show_replies=no]', $twitter_name, $this->latest_social_posts_count ) );

			$posts = get_transient( sprintf( 'fts_twitter_data_cache_%1$s_num%2$d', $twitter_name, $this->latest_social_posts_count ) );
			if ( false !== $posts ) {
				if ( is_object( $posts ) && property_exists( $posts, 'data' ) ) {
					$posts = $posts->data;
					if ( is_array( $posts ) ) {
						$posts = array_slice( $posts, 0, 6 );
					}
				}
			}


			$date = $posts[self::$latest_social_posts_counter]->created_at;
			$date = date( 'F j g:ia', strtotime( $date ) );
			$caption = $posts[self::$latest_social_posts_counter]->full_text;
			$link = sprintf( 'https://twitter.com/%2$s/status/%1$d', $posts[self::$latest_social_posts_counter]->id, $posts[self::$latest_social_posts_counter]->user->screen_name );

			/*$caption = '<span class="dashicons dashicons-twitter"></span><p>It\'s <a href="/hashtag/NationalRelaxationDay?src=hash" data-query-source="hashtag_click" class="twitter-hashtag pretty-link js-nav" dir="ltr"><s>#</s><b>NationalRelaxationDay</b></a>. Which of these is your favorite Mary Wash way to relax?<a href="https://t.co/YQ9wHAYGKl" class="twitter-timeline-link u-hidden" data-pre-embedded="true" dir="ltr">pic.twitter.com/YQ9wHAYGKl</a></p>';*/
			return sprintf( '
	<a href="%2$s" class="recent-social twitter">
		<figure>
			<figcaption>
				%1$s
				<footer class="datetime">%3$s</footer>
			</figcaption>
		</figure>
	</a>', $caption, $link, $date );
		}

		/**
		 * Process the shortcode for the latest social posts
		 * @param array $atts the list of attributes to send to the shortcode
		 *
		 * @access public
		 * @since  2018.05
		 * @return string
		 */
		public function do_latest_social_posts( $atts=array() ) {
			$count = 1;
			if ( is_array( $atts ) && ! empty( $atts ) )
				$count = $atts[0];

			$this->latest_social_posts_count = $count;
			self::$latest_social_posts_counter = 0;

			$ob = '<div class="recent-social-posts">';
			for ( self::$latest_social_posts_counter = 0; self::$latest_social_posts_counter < $this->latest_social_posts_count; self::$latest_social_posts_counter++ ) {
				$ob .= $this->do_instagram_post();
				$ob .= $this->do_facebook_post();
				$ob .= $this->do_recent_tweet();
			}
			$ob .= '</div>';

			return $ob;
		}
	}
}