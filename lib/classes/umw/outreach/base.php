<?php
/**
 * Sets up the base class for UMW Outreach modifications
 * @package UMW Outreach Customizations
 * @version 3.0.2
 */

namespace UMW\Outreach;

use Genesis_Customizer;

if ( ! class_exists( 'Base' ) ) {
	/**
	 * Define the class used on internal sites
	 */
	class Base {
		/**
		 * @var string $version holds the version number that's appended to script/style files
		 */
		var $version = '2022.01.31.02';
		/**
		 * @var null|string $header_feed holds the URL of the custom header feed
		 */
		var $header_feed = null;
		/**
		 * @var null|string $footer_feed holds the URL of the custom footer feed
		 */
		var $footer_feed = null;
		/**
		 * @var null|string $settings_field holds the handle of the settings field
		 */
		var $settings_field = null;
		/**
		 * @var string $setting_name holds the handle of the setting as found in the database
		 */
		var $setting_name = 'umw_outreach_settings';
		/**
		 * @var bool $is_root determines whether this page is loaded within the root site or not
		 */
		var $is_root = false;
		/**
		 * @var null|string $root_url holds the URL of the root site for the entire system
		 */
		var $root_url = null;
		/**
		 * @var null|string $plugins_url the URL to the root folder of this plugin
		 */
		var $plugins_url = null;
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
		 * Build our UMW_Outreach_Mods_Sub object
		 * This object is used on all sites throughout the system except
		 *        for the root site of the entire system.
		 *
		 * @access  public
		 * @since   0.1
		 */
		function __construct() {
			$theme = get_stylesheet();

			add_filter( 'plugins_url', array( $this, 'protocol_relative_plugins_url' ), 99 );
			add_filter( 'plugins_url', array( $this, 'fix_local_plugins_url' ), 98 );

			if ( is_link( __FILE__ ) ) {
				$base = readlink( __FILE__ );
				self::log( 'Plugin file was a symlink; now it looks like: ' . $base );
			} else {
				$base = __FILE__;
			}
			$base = dirname( $base, 4 );
			$tmp  = untrailingslashit( plugins_url( '', $base ) );

			$this->plugins_url = $tmp;

			/**
			 * Somewhat hacky way to use just the small pieces we need if we're still
			 *        on the old UMW site
			 */
			if ( ( defined( 'WP_DEFAULT_THEME' ) && 'umw' == WP_DEFAULT_THEME ) || 'umw' === $theme ) {
				if ( 'outreach-pro' != $theme ) {
					add_action( 'after_setup_theme', array( $this, 'do_legacy_theme_setup' ), 11 );

					return;
				}
			}

			/**
			 * Back to normal
			 */
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
			add_action( 'wp_print_footer_scripts', array( $this, 'fix_tools_mobile_menu' ) );
			add_action( 'after_setup_theme', array( $this, 'genesis_tweaks' ), 11 );
			/*add_action( 'genesis_before', array( $this, 'do_analytics_code' ), 1 );*/

			add_action( 'global-umw-header', array( $this, 'do_full_header' ) );
			add_action( 'global-umw-footer', array( $this, 'do_full_footer' ) );
			add_action( 'global-umw-header', array( $this, 'do_value_prop' ) );

			add_action( 'wp_head', array( $this, 'siteimprove_edit_links' ), 99 );

			if ( defined( 'UMW_IS_ROOT' ) && ! is_numeric( UMW_IS_ROOT ) ) {
				$feedsite = UMW_IS_ROOT;
			} else if ( defined( 'DOMAIN_CURRENT_SITE' ) ) {
				$feedsite = sprintf( 'http://%s/', DOMAIN_CURRENT_SITE );
			} else {
				$feedsite = network_site_url( '/', 'http' );
			}

			$this->header_feed = esc_url( sprintf( '%sfeed/umw-global-header/', $feedsite ) );
			$this->footer_feed = esc_url( sprintf( '%sfeed/umw-global-footer/', $feedsite ) );

			$this->add_shortcodes();

			add_action( 'widgets_init', function () {
				register_widget( '\UMW\Outreach\Widgets\Latest_News' );
			} );

			/**
			 * Build a list of post types that, when updated, need to invalidate the atoz transients
			 */
			$this->types_that_clear_atoz_transients = apply_filters( 'umw-types-that-clear-atoz-transients', array(
				'employee',
				'building',
				'department',
				'areas',
			) );
			if ( ! empty( $this->types_that_clear_atoz_transients ) ) {
				foreach ( $this->types_that_clear_atoz_transients as $t ) {
					add_action( "save_post_{$t}", array( $this, 'clear_atoz_transients' ) );
				}
			}

			$this->settings_field     = 'umw-site-settings';
			$this->sanitized_settings = false;
			/*if ( defined( 'GENESIS_SETTINGS_FIELD' ) )
				$this->settings_field = GENESIS_SETTINGS_FIELD;
			else
				$this->settings_field = 'genesis-settings';*/

			add_filter( 'oembed_dataparse', array( $this, 'remove_oembed_link_wrapper' ), 10, 3 );

			add_filter( 'oembed_dataparse', array( $this, 'add_title_attr_to_oembed' ), 10, 3 );
			add_filter( 'oembed_iframe_title_attribute', array( $this, 'oembed_iframe_title_attribute' ), 11, 4 );

			$this->transient_timeout = HOUR_IN_SECONDS;

			add_action( 'template_redirect', array( $this, 'do_custom_feeds' ) );

			add_filter( 'jetpack_shortcodes_to_include', array(
				$this,
				'remove_youtube_and_vimeo_from_jetpack_shortcodes'
			) );
			add_filter( 'jetpack_photon_reject_https', '__return_false' );

			add_filter( 'body_class', array( $this, 'add_site_to_body_class' ) );

			$this->login_link_ajax_hooks();

			/*add_action( 'plugins_loaded', array( $this, 'jetpack_fluid_video_embeds' ) );*/

			/*add_action( 'after_setup_theme', array( $this, 'add_theme_support' ) );*/

			global $content_width;
			$content_width = 1100;

			$this->umw_is_root();

			add_action( 'wp', array( $this, 'is_page_template' ) );

			/*add_filter( 'wpghs_post_meta', array( $this, '_ghs_extra_post_meta' ), 10, 2 );*/

			/**
			 * Set up the Custom Sidebars for the Graduate Admissions section of the site
			 */
			add_action( 'cs_before_replace_sidebars', array( $this, 'do_graduate_custom_sidebars' ) );

			/**
			 * Stop Yoast SEO Premium from creating automatic redirects when a slug changes
			 */
			add_filter( 'wpseo_premium_post_redirect_slug_change', '__return_true' );
			add_filter( 'wpseo_premium_term_redirect_slug_change', '__return_true' );

			/**
			 * Fix Responsive Image SrcSet Attributes for SSL
			 */
			add_filter( 'wp_calculate_image_srcset', array( $this, 'ssl_srcset' ) );

			/**
			 * Attempt to fix Pretty Link Pro handling of SSL
			 */
			add_filter( 'prli_target_url', array( $this, 'prli_target_url' ) );
		}

		/**
		 * Attempt to fix local handling of plugins_url, especially with symlinks
		 *
		 * @param string $url The complete URL to the plugins directory including scheme and path.
		 * @param string $path Path relative to the URL to the plugins directory. Blank string
		 *                       if no path is specified.
		 * @param string $plugin The plugin file path to be relative to. Blank string if no plugin
		 *                       is specified.
		 *
		 * @access public
		 * @return string the updated URL
		 * @since  2021.02
		 */
		public function fix_local_plugins_url( string $url, string $path = '', string $plugin = '' ): string {
			if ( stristr( $url, 'local-content-folders' ) ) {
				self::log( 'Found a URL that needs to be fixed: ' . $url );

				return preg_replace( '/(.+)\/wp-content\/.+\/local-content-folders\/[^\/]+\/(.*?)/', '$1/wp-content/$2', $url );
			}
			if ( substr_count( $url, 'wp-content' ) > 1 ) {
				return preg_replace( '/(.+)\/wp-content\/.+\/wp-content\/(.*?)/', '$1/wp-content/$2', $url );
			}

			return $url;
		}

		/**
		 * Attempt to fix Pretty Link Pro's handling of SSL
		 *
		 * @param array $link an array with Pretty Link Pro URL information
		 *
		 * @access  public
		 * @return  array the updated array
		 * @since   0.1
		 */
		function prli_target_url( $link ) {
			if ( ! array_key_exists( 'url', $link ) ) {
				return $link;
			}

			if ( ! stristr( $link['url'], 'https:' ) ) {
				return $link;
			}

			$link['url'] = preg_replace( '~https://(.*?)umw\.edu~', 'http://$1umw.edu', $link['url'] );

			return $link;
		}

		/**
		 * Determine whether this page uses a custom page template
		 *        Make necessary tweaks if it is
		 *
		 * @access  public
		 * @return  void
		 * @since   0.1
		 */
		function is_page_template() {
			if ( is_page_template( 'page_landing.php' ) ) {
				$this->undo_genesis_tweaks();
			}
		}

		/**
		 * Adjust any URLs generated by the plugins_url() function to be protocol-relative
		 * We should only apply this if the site is being loaded over SSL, just in case
		 *
		 * @param string $url the URL being modified
		 *
		 * @access  public
		 * @return  string the updated URL
		 * @since   0.1
		 */
		function protocol_relative_plugins_url( $url ) {
			return str_replace( array( 'http://', 'https://' ), array( '//', '//' ), $url );
		}

		/**
		 * Return a full URL for a plugin file based on the root location of this plugin
		 *
		 * @param string the path to append to the root URL
		 *
		 * @access public
		 * @return string the full URL
		 * @since  1.0
		 */
		public function plugins_url( $path = '' ) {
			if ( empty( $path ) ) {
				return $this->plugins_url;
			}

			if ( '/' == substr( $path, 0, 1 ) ) {
				$path = substr( $path, 1 );
			}

			return trailingslashit( $this->plugins_url ) . $path;
		}

		/**
		 * Adjust the srcset attribute of the responsive images to use protocol-relative URLs
		 *
		 * @param array $sources an array of sources being used for an img srcset
		 *
		 * @access  public
		 * @return  array the updated array of source URLs
		 * @since   0.1
		 */
		function ssl_srcset( $sources = array() ) {
			foreach ( $sources as $id => $props ) {
				$url                   = $props['url'];
				$sources[ $id ]['url'] = $this->protocol_relative_plugins_url( $url );
			}

			return $sources;
		}

		/**
		 * Load only the pieces we need for the old UMW theme/site
		 *
		 * @access  public
		 * @return  bool
		 * @since   0.1
		 */
		function do_legacy_theme_setup() {
			if ( ! function_exists( 'umw_is_umw_homepage' ) ) {
				return false;
			}

			if ( defined( 'UMW_FULL_HEADER' ) && true === UMW_FULL_HEADER ) {
				remove_all_actions( 'genesis_header' );
				add_action( 'genesis_header', array( $this, 'do_full_header' ) );
				if ( function_exists( 'umw_is_full_header' ) && umw_is_full_header() ) {
					remove_action( 'umw_umw_subnav', 'umw_do_umw_subnav' );
					remove_action( 'genesis_header', 'umw_do_full_header' );
					remove_action( 'genesis_after_header', 'umw_do_umw_nav' );
				} else {
					remove_action( 'genesis_header', 'umw_do_global_header' );
				}

				global $umw_online_tools_obj;
				remove_action( 'genesis_before', array( $umw_online_tools_obj, 'do_toolbar' ), 2 );
				remove_action( 'genesis_before', array( $umw_online_tools_obj, 'do_header_bar' ), 5 );
				remove_action( 'wp_enqueue_scripts', array( $umw_online_tools_obj, 'enqueue_styles' ) );
				remove_action( 'umw-main-header-bar', array( $umw_online_tools_obj, 'do_audience_menu' ), 11 );

				remove_action( 'umw-main-header-bar', array( $umw_online_tools_obj, 'do_wordmark' ), 5 );
			}

			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_legacy_styles' ) );

			remove_all_actions( 'genesis_footer' );
			add_action( 'global-umw-footer', array( $this, 'do_full_footer' ) );

			if ( defined( 'UMW_IS_ROOT' ) && ! is_numeric( UMW_IS_ROOT ) ) {
				$feedsite = UMW_IS_ROOT;
			} else if ( defined( 'DOMAIN_CURRENT_SITE' ) ) {
				$feedsite = sprintf( 'http://%s/', DOMAIN_CURRENT_SITE );
			} else {
				$feedsite = network_site_url( '/', 'http' );
			}

			$this->footer_feed = esc_url( sprintf( '%sfeed/umw-global-footer/', $feedsite ) );

			$this->transient_timeout = HOUR_IN_SECONDS;

			global $content_width;
			$content_width = 1100;

			$this->umw_is_root();

			/* Get rid of the standard footer & replace it with our global footer */
			remove_all_actions( 'genesis_footer' );
			add_action( 'genesis_footer', array( $this, 'get_footer' ) );

			add_shortcode( 'current-date', array( $this, 'do_current_date_shortcode' ) );
			add_shortcode( 'current-url', array( $this, 'do_current_url_shortcode' ) );

			$this->login_link_ajax_hooks();

			/* Remove the default favicon & replace it with ours */
			if ( ! has_action( 'genesis_meta', 'genesis_load_favicon' ) ) {
				add_action( 'genesis_meta', 'genesis_load_favicon' );
			}
			add_filter( 'genesis_pre_load_favicon', array( $this, 'favicon_url' ) );

			return true;
		}

		/**
		 * Set up a separate style sheet just for the pieces we're using on the old
		 *        UMW site
		 *
		 * @access  public
		 * @return  void
		 * @since   0.1
		 */
		function enqueue_legacy_styles() {
			wp_enqueue_style( 'umw-global-footer-legacy', plugins_url( '/lib/styles/umw-legacy-styles.css', dirname( __FILE__, 4 ) ), array(), $this->version, 'all' );
		}

		/**
		 * Register the AJAX calls that will add the login link
		 *
		 * @access  public
		 * @return  void
		 * @since   0.1
		 */
		function login_link_ajax_hooks() {
			add_action( 'wp_ajax_umw_login_link', array( $this, 'umw_login_link' ) );
			add_action( 'wp_ajax_nopriv_umw_login_link', array( $this, 'umw_login_link' ) );
			add_action( 'wp_print_footer_scripts', array( $this, 'login_link_ajax_scripts' ) );
		}

		/**
		 * Output a login link if the user is on-campus
		 *
		 * @access  public
		 * @return  void
		 * @since   0.1
		 */
		function umw_login_link() {
			$link = $this->get_umw_login_link();

			if ( function_exists( 'wp_json_encode' ) ) {
				echo wp_json_encode( array( 'link' => $link ) );
			} else {
				echo json_encode( array( 'link' => $link ) );
			}
			wp_die();
		}

		/**
		 * Set up a login/admin link
		 *
		 * @access public
		 * @return string
		 * @since  0.1
		 */
		public function get_umw_login_link() {
			if ( is_user_logged_in() ) {
				$link = sprintf( '| <a rel="noindex, nofollow" href="%1$s" title="Go to the administration area for %2$s">%3$s</a>', admin_url(), esc_attr( get_bloginfo( 'name' ) ), __( 'Website Admin' ) );
			} else {
				$link = sprintf( '| <a rel="noindex, nofollow" href="%1$s" title="Login to the administration area for %2$s">%3$s</a>', wp_login_url(), esc_attr( get_bloginfo( 'name' ) ), __( 'Login' ) );
			}

			return $link;
		}

		/**
		 * Attempt to retrieve and possibly return a visitor's real IP address
		 *
		 * @param bool $echo whether or not to output the IP address
		 *
		 * @access  public
		 * @return  string the user's IP address
		 * @since   0.1
		 */
		function get_real_ip( $echo = false ) {
			/*if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) && ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} else*/
			if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}

			if ( $echo ) {
				echo $ip;
			}

			return $ip;
		}

		/**
		 * Output the scripts that handle retrieving and outputting the login link
		 *
		 * @access  public
		 * @return  void
		 * @since   0.1
		 */
		function login_link_ajax_scripts() {
			?>
            <script>
                jQuery(function () {
                    if (document.querySelectorAll('.login-link').length <= 0) {
                        return;
                    }
                    jQuery('.login-link').html('<?php echo $this->get_umw_login_link() ?>');
                });
            </script>
			<?php
		}

		/**
		 * Add the IE compatibility tag to the very top of the document
		 *
		 * @access  public
		 * @return  void
		 * @since   0.1
		 */
		function do_doctype() {
			genesis_do_doctype();
			$this->ie_compatibility_tag();
		}

		/**
		 * Output a compatibility tag to force IE to run the site in edge mode
		 *
		 * @access  public
		 * @return  void
		 * @since   0.1
		 */
		function ie_compatibility_tag() {
			echo '<meta http-equiv="X-UA-Compatible" content="IE=10; IE=9; IE=8; IE=7; IE=EDGE" />';
		}

		/**
		 * Apply a unique class to the body for each individual site, just in case
		 *        we need to add special CSS for a specific area
		 *
		 * @param array $classes the array of CSS classes being added to the body element
		 *
		 * @access  public
		 * @return  array the udpated array of CSS classes
		 * @since   0.1
		 */
		function add_site_to_body_class( $classes = array() ) {
			$url = get_bloginfo( 'url' );
			$url = str_replace( array( 'http://', 'https://' ), array( '', '' ), $url );
			$url = sanitize_title( $url );

			$classes[] = sanitize_title( $url );

			if ( ! is_multisite() ) {
				return $classes;
			}

			if ( ! defined( 'SUBDOMAIN_INSTALL' ) || false == SUBDOMAIN_INSTALL ) {
				global $wpdb;
				$path = $wpdb->get_var( $wpdb->prepare( "SELECT path FROM {$wpdb->blogs} WHERE blog_id=%d", $GLOBALS['blog_id'] ) );
				if ( '/' == $path ) {
					$path = 'root';
				} else {
					$path = sanitize_title( $path );
				}

				$classes[] = 'site-' . $path;
			}

			return $classes;
		}

		/**
		 * Output the Google Analytics script for the website
		 *
		 * @access  public
		 * @return  void
		 * @since   0.1
		 */
		function do_analytics_code() {
			?>
            <!-- New Universal Analytics Code Snippet -->
            <script>
                (function (i, s, o, g, r, a, m) {
                    i['GoogleAnalyticsObject'] = r;
                    i[r] = i[r] || function () {
                        (i[r].q = i[r].q || []).push(arguments)
                    }, i[r].l = 1 * new Date();
                    a = s.createElement(o),
                        m = s.getElementsByTagName(o)[0];
                    a.async = 1;
                    a.src = g;
                    m.parentNode.insertBefore(a, m)
                })(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');

                ga('create', 'UA-65596701-1', 'auto');
                ga('send', 'pageview');

            </script>
            <!-- / New Universal Analytics Code Snippet -->
			<?php
		}

		/**
		 * Check to see if this is the root site of the system
		 * @return  void
		 * @uses    UMW_Outreach_Mods_Sub::$root_url
		 *
		 * @access  public
		 * @since   0.1
		 * @uses    UMW_Outreach_Mods_Sub::$is_root
		 */
		function umw_is_root() {
			if ( defined( 'UMW_IS_ROOT' ) ) {
				if ( is_numeric( UMW_IS_ROOT ) && $GLOBALS['blog_id'] == UMW_IS_ROOT ) {
					$this->is_root  = true;
					$this->root_url = get_bloginfo( 'url' );
				} else if ( is_numeric( UMW_IS_ROOT ) ) {
					$this->root_url = get_blog_option( UMW_IS_ROOT, 'home_url', null );
				} else {
					$this->root_url = esc_url( UMW_IS_ROOT );
				}
			}
		}

		/**
		 * Regsiter any shortcodes we need to use
		 * @return  void
		 * @since   0.1
		 * @uses    add_shortcode()
		 *
		 * @access  public
		 */
		function add_shortcodes() {
			add_shortcode( 'atoz', array( $this, 'do_atoz_shortcode' ) );
			add_shortcode( 'wpv-last-modified', array( $this, 'wpv_last_modified' ) );
			add_shortcode( 'current-date', array( $this, 'do_current_date_shortcode' ) );
			add_shortcode( 'current-url', array( $this, 'do_current_url_shortcode' ) );
			add_shortcode( 'wpv-tel-link', array( $this, 'do_tel_link_shortcode' ) );
			add_shortcode( 'umw-login-link', array( $this, 'get_umw_login_link' ) );
		}

		/**
		 * Make sure that JetPack video embeds are responsive if FluidVideoEmbed is active
		 * @return  void
		 * @uses    add_filter() to filter the JetPack embeds
		 * @uses    UMW_Outreach_Mods_Sub::fve_jetpack_filter_video_embed()
		 *
		 * @access  public
		 * @since   0.1
		 * @uses    FluidVideoEmbed
		 */
		function jetpack_fluid_video_embeds() {
			global $fve;
			if ( ! isset( $fve ) && class_exists( '\FluidVideoEmbed' ) ) {
				\FluidVideoEmbed::instance();
			} else if ( ! class_exists( '\FluidVideoEmbed' ) ) {
				return;
			}

			add_filter( 'wp_video_shortcode', array( &$this, 'fve_jetpack_filter_video_embed' ), 16, 2 );
			add_filter( 'video_embed_html', array( &$this, 'fve_jetpack_filter_video_embed' ), 16 );
		}

		/**
		 * Return a responsive version of the JetPack video embed
		 *
		 * @param string $html the HTML being processed
		 * @param array $atts the array of arguments/attributes for the video
		 *
		 * @access  public
		 * @return  string the responsive HTML for the video embed
		 * @since   0.1
		 * @uses    FluidVideoEmbed
		 *
		 */
		function fve_jetpack_filter_video_embed( $html, $atts = array() ) {
			if ( ! stristr( $html, 'youtube' ) && ! stristr( $html, 'vimeo' ) ) {
				return $html;
			}

			if ( is_array( $atts ) && array_key_exists( 'src', $atts ) ) {
				global $fve;

				return $fve->filter_video_embed( '', $atts['src'], null );
			}

			preg_match( '`http(s*?):\/\/(www\.*?)youtube.com\/embed\/([a-zA-Z0-9]{1,})`', $html, $matches );

			global $fve;

			return $fve->filter_video_embed( $html, sprintf( 'https://youtube.com/watch?v=%s', $matches[3] ), null );
		}

		/**
		 * Make sure that JetPack doesn't override FluidVideoEmbed for YouTube and Vimeo
		 *
		 * @param array $shortcodes the array of existing registered shortcodes
		 *
		 * @access public
		 * @return array the updated array of registered shortcodes
		 * @since  0.1
		 */
		function remove_youtube_and_vimeo_from_jetpack_shortcodes( $shortcodes = array() ) {
			$good_shortcodes = array();
			foreach ( $shortcodes as $s ) {
				if ( stristr( $s, 'youtube' ) || stristr( $s, 'vimeo' ) ) {
					continue;
				}

				$good_shortcodes[] = $s;
			}

			return $good_shortcodes;
		}

		/**
		 * Attempt to use JetPack's native responsiveness for video embeds
		 *
		 * @access public
		 * @return void
		 * @since  0.1
		 */
		function add_theme_support() {
			/* Try using the JetPack responsive videos module instead of Fluid Video Embeds */
			add_theme_support( 'jetpack-responsive-videos' );
		}

		/**
		 * Override a shortcode by sending back the content
		 *        with no changes
		 *
		 * @param array $atts ignored
		 * @param string $content the content to return
		 */
		function __blank( $atts = array(), $content = '' ) {
			return $content;
		}

		/**
		 * Output a custom Atom feed
		 */
		function do_custom_feeds() {
			if ( ! is_singular( 'atom-feed' ) ) {
				return;
			}

			remove_shortcode( 'wpv-layout-start' );
			remove_shortcode( 'wpv-layout-end' );
			add_shortcode( 'wpv-layout-start', array( $this, '__blank' ) );
			add_shortcode( 'wpv-layout-end', array( $this, '__blank' ) );

			global $post;
			while ( have_posts() ) : the_post();
				$content = $post->post_content;
			endwhile;

			header( 'Content-Type: ' . feed_content_type( 'atom' ) . '; charset=' . get_option( 'blog_charset' ), true );
			$more = 1;

			echo '<?xml version="1.0" encoding="' . get_option( 'blog_charset' ) . '"?' . '>';
			do_action( 'rss_tag_pre', 'atom' );
			?>
            <feed
                    xmlns="http://www.w3.org/2005/Atom"
                    xmlns:thr="http://purl.org/syndication/thread/1.0"
                    xmlns:umwns="http://www.umw.edu/"
                    xml:lang="<?php bloginfo_rss( 'language' ); ?>"
                    xml:base="<?php bloginfo_rss( 'url' ) ?>/wp-atom.php"
				<?php
				/**
				 * Fires at end of the Atom feed root to add namespaces.
				 *
				 * @since 2.0.0
				 */
				do_action( 'atom_ns' );
				?>
            >
				<?php
				ob_start();
				self_link();
				$self_link     = ob_get_clean();
				$transient_key = 'custom-feed-' . base64_encode( $self_link );

				printf( "\n\t" . '<title type="text">%s</title>', get_bloginfo_rss( 'name' ) . get_wp_title_rss() );
				printf( "\n\t" . '<updated>%s</updated>', mysql2date( 'Y-m-d\TH:i:s\Z', get_lastpostmodified( 'GMT' ), false ) );
				printf( "\n\t" . '<link rel="alternate" type="%1$s" href="%2$s" />', get_bloginfo_rss( 'html_type' ), get_bloginfo_rss( 'url' ) );
				printf( "\n\t" . '<id>%s</id>', get_bloginfo( 'atom_url' ) );
				printf( "\n\t" . '<link rel="self" type="application/atom+xml" href="%s"/>', $self_link );
				echo "\n";
				do_action( 'atom_head' );

				$feed = get_transient( $transient_key );
				if ( false === $feed ) {
					$feed = str_replace( array( '<!&#091;CDATA&#091;', '&#093;&#093;>' ), array(
						'<![CDATA[',
						']]>'
					), do_shortcode( $content ) );
					set_transient( $transient_key, $feed, HOUR_IN_SECONDS );
				}
				echo $feed;
				?>
            </feed>
			<?php
			exit();
		}

		function custom_genesis_loop( $content ) {
			/*do_action( 'genesis_before_while' );*/
			do_action( 'genesis_before_entry' );
			printf( '<article %s>', genesis_attr( 'entry' ) );
			do_action( 'genesis_entry_header' );
			do_action( 'genesis_before_entry_content' );
			printf( '<div %s>', genesis_attr( 'entry-content' ) );

			echo $content;

			echo '</div>';
			do_action( 'genesis_after_entry_content' );
			do_action( 'genesis_entry_footer' );
			echo '</article>';
			do_action( 'genesis_after_entry' );
			/*do_action( 'genesis_after_endwhile' );*/
		}

		/**
		 * Return boolean false
		 */
		function _return_false() {
			return false;
		}

		/**
		 * If desired, output the value proposition area below the global header
		 */
		function do_value_prop() {
			$current = $this->get_option( $this->setting_name );
			if ( empty( $current ) || ! is_array( $current ) ) {
				return;
			}

			if ( false == $this->is_root && ( ! array_key_exists( 'site-title', $current ) || empty( $current['site-title'] ) ) ) {
				$current['site-title'] = get_bloginfo( 'name' );
			}

			if ( empty( $current['site-title'] ) && empty( $current['statement'] ) && empty( $current['content'] ) ) {
				return;
			}

			$current['statement'] = html_entity_decode( $current['statement'] );
			$current['content']   = html_entity_decode( $current['content'] );

			$format = '
<div class="site-info">
	<div class="wrap">';
			if ( ( ! empty( $current['site-title'] ) || ! empty( $current['statement'] ) ) && ! empty( $current['content'] ) ) {
				$format .= '
		<div class="five-sixths first">';
				if ( ! empty( $current['site-title'] ) ) {
					$format .= '
			<h2 class="site-info-title"><a href="%1$s" title="%2$s">%2$s</a></h2>';
				}
				if ( ! empty( $current['statement'] ) ) {
					$format .= '
			%3$s';
				}
				$format .= '
		</div>
		<div class="one-sixth">
			%4$s
		</div>';
			} else if ( ! empty( $current['content'] ) ) {
				$format .= '
		<div>
			%4$s
		</div>';
			} else {
				$format .= '
		<div>';
				if ( ! empty( $current['site-title'] ) ) {
					$format .= '
			<h2 class="site-info-title"><a href="%1$s" title="%2$s">%2$s</a></h2>';
				}
				if ( ! empty( $current['statement'] ) ) {
					$format .= '
			%3$s';
				}
				$format .= '
		</div>';
			}
			$format .= '
	</div>
</div>';

			printf( $format, esc_url( get_bloginfo( 'url' ) ), esc_attr( $current['site-title'] ), wpautop( $current['statement'] ), wpautop( $current['content'] ) );
		}

		/**
		 * Attempt to remove the link wrapper around oEmbedded images
		 */
		function remove_oembed_link_wrapper( $return, $data, $url ) {
			if ( 'photo' != $data->type ) {
				return $return;
			}

			if ( ! in_array( $data->provider_name, apply_filters( 'oembed-image-providers-no-link', array(
				'SmugMug',
				'Flickr'
			) ) ) ) {
				return $return;
			}

			if ( empty( $data->url ) || empty( $data->width ) || empty( $data->height ) ) {
				return $return;
			}
			if ( ! is_string( $data->url ) || ! is_numeric( $data->width ) || ! is_numeric( $data->height ) ) {
				return $return;
			}

			$title = ! empty( $data->title ) && is_string( $data->title ) ? $data->title : '';

			return '<img src="' . esc_url( $data->url ) . '" alt="' . esc_attr( $title ) . '" width="' . esc_attr( $data->width ) . '" height="' . esc_attr( $data->height ) . '" />';
		}

		/**
		 * Attempt to ensure that video & rich oEmbeds use a title attribute
		 *
		 * @param $html string The returned oEmbed HTML.
		 * @param $data \stdClass A data object result from an oEmbed provider.
		 * @param $url string URL of the content to be embedded.
		 *
		 * @access public
		 * @return string the updated HTML
		 * @since  2021.03.22
		 */
		public function add_title_attr_to_oembed( string $html, \stdClass $data, string $url ): string {
			return wp_filter_oembed_iframe_title_attribute( $html, $data, $url );
		}

		/**
		 * Attempt to add a title attribute to oEmbeds that don't have one
		 *
		 * @param $title string The title attribute.
		 * @param $result string The oEmbed HTML result.
		 * @param $data \stdClass A data object result from an oEmbed provider.
		 * @param $url string The URL of the content to be embedded.
		 *
		 * @access public
		 * @return string the updated title attribute
		 * @since  2021.03.22
		 */
		public function oembed_iframe_title_attribute( string $title, string $result, \stdClass $data, string $url ): string {
			if ( ! empty( $title ) ) {
				return $title;
			}

			if ( stristr( $url, 'youtube' ) || stristr( $url, 'youtu.be' ) ) {
				return __( 'Embedded YouTube video', 'umw-outreach-mods' );
			}

			if ( stristr( $url, 'vimeo' ) ) {
				return __( 'Embedded Vimeo video', 'umw-outreach-mods' );
			}

			if ( 'video' == $data->type ) {
				return __( 'Embedded video', 'umw-outreach-mods' );
			}

			return $title;
		}

		/**
		 * Set up any CSS style sheets that need to be used on the site
		 */
		function enqueue_styles() {
			if ( is_admin() ) {
				return;
			}

			/* Outreach enqueues a style sheet called google-fonts, that loads type faces we don't use */
			wp_dequeue_style( 'google-fonts' );
			/* Register our modified copy of the Outreach Pro base style sheet */
			wp_register_style( 'outreach-pro', $this->plugins_url( '/lib/styles/outreach-pro.css' ), array(), $this->version, 'all' );
			/* Enqueue our additional styles */
			if ( ! wp_style_is( 'genericons', 'registered' ) ) {
				wp_register_style( 'genericons', $this->plugins_url( '/lib/styles/genericons/genericons.css' ), array(), $GLOBALS['wp_version'], 'all' );
			}
			wp_enqueue_style( 'umw-outreach-mods', $this->plugins_url( '/lib/styles/umw-outreach-mods.css' ), array(
				'outreach-pro',
				'genericons',
				'dashicons'
			), $this->version, 'all' );
		}

		/**
		 * Output a little JS to fix the fact that the tools/search menu
		 *        no longer works on mobile
		 */
		function fix_tools_mobile_menu() {
			print( '<!-- Fix Tools Mega Menu on Mobile --><script>jQuery( function() { jQuery( \'.umw-is-root .tools-search-toggle\' ).on( \'click\', function() { jQuery( \'.mega-menu-item-umw-online-tools\' ).toggleClass( \'mega-toggle-on\' ); return false; } ); } )</script><!-- /Fix Tools Mega Menu on Mobile -->' );
		}

		/**
		 * Tweak anything that's done by Genesis or Outreach that's
		 *        not necessary for our implementation
		 */
		function genesis_tweaks() {
			if ( ! function_exists( 'genesis' ) ) {
				return false;
			}

			remove_action( 'genesis_before_header', 'genesis_skip_links', 5 );
			add_action( 'genesis_before', 'genesis_skip_links', 1 );

			remove_action( 'genesis_doctype', 'genesis_do_doctype' );
			add_action( 'genesis_doctype', array( $this, 'do_doctype' ) );

			/* Remove the default Genesis style sheet */
			remove_action( 'genesis_meta', 'genesis_load_stylesheet' );

			/* Remove the default favicon & replace it with ours */
			if ( ! has_action( 'genesis_meta', 'genesis_load_favicon' ) ) {
				add_action( 'genesis_meta', 'genesis_load_favicon' );
			}
			add_filter( 'genesis_pre_load_favicon', array( $this, 'favicon_url' ) );

			if ( is_admin() ) {
				add_action( 'admin_head', 'genesis_load_favicon' );
			}

			/* Get rid of the standard header & replace it with our global header */
			remove_all_actions( 'genesis_header' );
			add_action( 'genesis_header', array( $this, 'get_header' ) );

			add_theme_support( 'category-thumbnails' );
			add_theme_support( 'genesis-customizer-umw-settings' );

			/* Get rid of the standard footer & replace it with our global footer */
			remove_all_actions( 'genesis_footer' );
			add_action( 'genesis_footer', array( $this, 'get_footer' ) );

			/* Get everything out of the primary sidebar & replace it with just navigation */
			remove_all_actions( 'genesis_sidebar' );
			add_action( 'genesis_sidebar', array( $this, 'section_navigation' ) );

			/* Move the breadcrumbs to appear above the content-sidebar wrap */
			remove_action( 'genesis_before_loop', 'genesis_do_breadcrumbs' );
			add_action( 'genesis_before_content', 'genesis_do_breadcrumbs' );

			add_action( 'admin_menu', array( $this, 'add_submenu_page' ) );

			add_action( 'genesis_theme_settings_metaboxes', array( $this, 'metaboxes' ) );
			add_action( 'admin_init', array( $this, 'sanitizer_filters' ) );

			add_action( 'genesis_customizer', array(
				$this,
				'umw_customizer_theme_settings_config'
			) );

			add_action( 'genesis_before_content', array( $this, 'home_title' ), 9 );
			add_action( 'genesis_loop', array( $this, 'home_featured_image' ), 9 );

			$this->add_image_sizes();

			/**
			 * If Genesis Accessible isn't active and this is a version of Genesis older than 2.2,
			 *        add an HTML ID to the main content section
			 */
			if ( ! function_exists( 'genwpacc_activation_check' ) && ! function_exists( 'genesis_a11y' ) ) {
				add_filter( 'genesis_attr_content', array( $this, 'add_content_id' ), 99, 2 );
			}

			/**
			 * Remove the header-right sidebar, since we are replacing it with a nav menu
			 */
			unregister_sidebar( 'header-right' );

			return true;
		}

		/**
		 * Undo all of the custom actions we added, since this
		 *        page appears to be using a custom page template
		 *        that doesn't need all of these changes
		 */
		function undo_genesis_tweaks() {
			/* Get rid of the standard header & replace it with our global header */
			remove_action( 'genesis_header', array( $this, 'get_header' ) );

			/* Get rid of the standard footer & replace it with our global footer */
			remove_action( 'genesis_footer', array( $this, 'get_footer' ) );

			/* Get everything out of the primary sidebar & replace it with just navigation */
			remove_action( 'genesis_sidebar', array( $this, 'section_navigation' ) );

			/* Move the breadcrumbs to appear above the content-sidebar wrap */
			remove_action( 'genesis_before_content', 'genesis_do_breadcrumbs' );

			remove_action( 'genesis_before_content', array( $this, 'home_title' ), 9 );
			remove_action( 'genesis_loop', array( $this, 'home_featured_image' ), 9 );
		}

		/**
		 * Register any image sizes we need for this theme
		 */
		function add_image_sizes() {
			/**
			 * Add image size to be used in home page news widget
			 */
			add_image_size( 'news-feature', 250, 155, true );
			/**
			 * Add image size to be used as Post Featured Image
			 */
			add_image_size( 'post-feature', 680, 453, true );
			/**
			 * Add image size to be used in Post Feed Widget
			 */
			add_image_size( 'post-feed-feature', 285, 160, true );
			/**
			 * Add image size to be used as Featured Story in Slider &
			 *        Page Feature Image
			 */
			add_image_size( 'page-feature', 1140, 460, true );
			/**
			 * Add image size to be used as page feature image
			 */
			add_image_size( 'page-feature-uncropped', 1140, 1140, false );
			/**
			 * Add image size to be used as Feature Story in Sidebar
			 */
			add_image_size( 'sidebar-feature', 310, 155, true );
			/**
			 * Add image size to be used for 2018 UMW home page slider
			 */
			add_image_size( 'root-home-slideshow', 1140, 570, true );

			/**
			 * Add some of these new sizes to the size selector
			 */
			add_filter( 'image_size_names_choose', array( $this, 'image_size_names_choose' ) );
		}

		/**
		 * Add some custom image sizes to the Media Insert selector
		 */
		function image_size_names_choose( $sizes = array() ) {
			$sizes['page-feature']           = __( 'Page Feature (Cropped)' );
			$sizes['page-feature-uncropped'] = __( 'Page Feature (Original Shape)' );

			return $sizes;
		}

		/**
		 * Return the URL to our custom favicon
		 */
		function favicon_url( $url ) {
			return $this->plugins_url( '/lib/images/favicon.ico?v=' . $this->version );
		}

		/**
		 * Add an HTML ID to the <main> element to allow skip-links to work
		 */
		function add_content_id( $attr, $context ) {
			if ( 'content' != $context ) {
				return $attr;
			}

			$attr['id'] = 'genesis-content';

			return $attr;
		}

		/**
		 * Output the site name as the title of the front page
		 */
		function home_title() {
			if ( ! is_front_page() && ! is_home() ) {
				return;
			}
			if ( is_front_page() ) {
				$title = get_bloginfo( 'name' );
			} else if ( is_home() ) {
				$title = get_bloginfo( 'name' ) . ' Archives';
			}
			if ( empty( $title ) ) {
				return;
			}

			if ( defined( 'UMW_IS_ROOT' ) ) {
				if ( ! is_numeric( UMW_IS_ROOT ) || UMW_IS_ROOT != $GLOBALS['blog_id'] ) {
					$title = __( 'University of Mary Washington ' ) . $title;
				}
			}

			printf( '<h1 class="front-page-title hidden" style="display: block">%s</h1>', $title );
		}

		/**
		 * Output the featured image, if necessary, at the top of the home page
		 */
		function home_featured_image() {
			if ( ! is_front_page() ) {
				return;
			}

			$current = $this->get_option( $this->setting_name );
			if ( ! is_array( $current ) || ! array_key_exists( 'image-url', $current ) ) {
				return;
			}

			/*$img = array();
			foreach ( $current as $key => $value ) {
				if ( stristr( $key, 'image-' ) ) {
					$img[ str_replace( 'image-', '', $key ) ] = $value;
				}
			}*/

			$embed = $this->get_embedded_image( esc_url( $current['image-url'] ), array( 'title' => $current['image-title'] ) );
			if ( false === $embed ) {
				return;
			}

			$format = '<figure class="home-featured-image">';
			if ( esc_url( $current['image-link'] ) ) {
				$format .= '<a href="%5$s" title="%2$s">%3$s</a>';
			} else {
				$format .= '%3$s';
			}
			if ( ! empty( $current['image-title'] ) || ! empty( $current['image-subtitle'] ) ) {
				$format .= '<figcaption>';
				if ( ! empty( $current['image-title'] ) ) {
					if ( ! empty( $current['image-link'] ) ) {
						$format .= '<h2 class="home-feature-title"><a href="%5$s">%2$s</a></h2>';
					} else {
						$format .= '<h2 class="home-feature-title">%2$s</h2>';
					}
				}
				if ( ! empty( $current['image-subtitle'] ) ) {
					$format .= '<div class="home-feature-subtitle">%4$s</div>';
				}
				$format .= '</figcaption>';
			}
			$format .= '</figure>';

			printf( $format, esc_url( $current['image-url'] ), strip_tags( html_entity_decode( $current['image-title'] ), array() ), $embed, wpautop( $current['image-subtitle'] ), $current['image-link'] );
		}

		/**
		 * Attempt to retrieve the appropriate code for an embedded image
		 */
		function get_embedded_image( $url, $img = array( 'title' => null ) ) {
			add_filter( 'embed_defaults', array( $this, 'remove_default_oembed_width' ) );
			$embed = $srcs = array( 'small' => '', 'mid' => '', 'full' => '' );
			$args  = array( 'width' => 400 );

			require_once( ABSPATH . WPINC . '/class-oembed.php' );
			$oembed   = _wp_oembed_get_object();
			$provider = $oembed->get_provider( $url, $args );
			if ( false === $provider ) {
				return $this->get_embedded_image_direct( $url, $img );
			}

			$data = $oembed->fetch( $provider, $url, $args );
			if ( false === $data ) {
				return $this->get_embedded_image_direct( $url, $img );
			}

			/**
			 * Add support for oEmbeddable videos, just in case
			 */
			if ( 'photo' != $data->type ) {
				$args['width']  = 1140;
				$args['height'] = 800;

				return apply_filters( 'the_content', '[embed width="' . $args['width'] . '" height="' . $args['height'] . '"]' . $url . '[/embed]' );
			}
			$srcs['small'] = array( 'url' => $data->url, 'width' => $data->width, 'height' => $data->height );

			$embed['small'] = sprintf( '<img src="%1$s" width="%2$d" height="%3$d" alt="%4$s"/>', esc_url( $data->url ), esc_attr( $data->height ), esc_attr( $data->width ), esc_attr( $img['title'] ) );

			$args['width'] = 800;
			$data          = $oembed->fetch( $provider, $url, $args );
			if ( false !== $data ) {
				$embed['mid'] = sprintf( '<source media="(min-width:860px and max-width: 1023px)" srcset="%1$s"/>', $data->url );
				$srcs['mid']  = array( 'url' => $data->url, 'width' => $data->width, 'height' => $data->height );
			}

			if ( stristr( $url, 'flickr' ) ) {
				$args = array();
			} else {
				$args['width'] = 1140;
			}
			$data = $oembed->fetch( $provider, $url, $args );
			if ( false !== $data ) {
				$embed['full'] = sprintf( '<source media="(min-width: 1024px)" srcset="%1$s"/>', $data->url );
				$srcs['full']  = array( 'url' => $data->url, 'width' => $data->width, 'height' => $data->height );
			}

			remove_filter( 'embed_defaults', array( $this, 'remove_default_oembed_width' ) );

			if ( ! empty( $srcs['full'] ) && ! empty( $srcs['mid'] ) && ! empty( $srcs['small'] ) ) {
				return sprintf( '<img srcset="%1$s %2$s, %3$s %4$s" sizes="%5$s, %6$s" src="%7$s" alt="%8$s" width="%9$d" height="%10$d"/>',
					/* 1 */
					$srcs['small']['url'],
					/* 2 */
					$srcs['small']['width'] . 'w',
					/* 3 */
					$srcs['mid']['url'],
					/* 4 */
					$srcs['mid']['width'] . 'w',
					/* 5 */
					'(max-width: 500px)',
					/* 6 */
					'(max-width: 1024px)',
					/* 7 */
					$srcs['full']['url'],
					/* 8 */
					esc_attr( $img['title'] ),
					/* 9 */
					$srcs['full']['width'],
					/* 10 */
					$srcs['full']['height']
				);
			} else if ( ! empty( $srcs['full'] ) ) {
				return sprintf( '<img src="%1$s" alt="%2$s" width="%3$d" height="%4$d"/>', esc_url( $srcs['full']['url'] ), esc_attr( $img['title'] ), esc_attr( $srcs['full']['width'] ), esc_attr( $srcs['full']['height'] ) );
			} else {
				return false;
			}
		}

		/**
		 * Handle direct links to images that need to be embedded
		 */
		function get_embedded_image_direct( $url, $img = array( 'title' => '' ) ) {
			remove_filter( 'embed_defaults', array( $this, 'remove_default_oembed_width' ) );

			if ( ! in_array( substr( $url, - 3 ), array( 'jpg', 'peg', 'png', 'gif' ) ) ) {
				return false;
			}

			if ( ! esc_url( $url ) ) {
				return false;
			}

			$tmp = $this->attachment_url_to_postid( $url );
			if ( ! empty( $tmp ) && is_numeric( $tmp ) ) {
				$imgdata = wp_get_attachment_image_src( $tmp, 'page-feature-uncropped', false );
				if ( is_array( $imgdata ) ) {
					$imgdata[] = trim( strip_tags( get_post_meta( $tmp, '_wp_attachment_image_alt', true ) ) );

					return vsprintf( '<img src="%1$s" alt="%5$s" style="width: %2$dpx; max-width: 100%%; height: auto; max-height: %3$dpx;"/>', $imgdata );
				}
			}

			return sprintf( '<img src="%1$s" alt="%2$s"/>', esc_url( $url ), $img['title'] );
		}

		/**
		 * Tries to convert an attachment URL into a post ID.
		 * This version of this function was copied almost directly from the
		 *    code within WordPress Core; it's just a backup in case the
		 *    function doesn't exist within Core for some reason
		 *
		 * @param string $url The URL to resolve.
		 *
		 * @return int The found post ID, or 0 on failure.
		 * @since 4.0.0
		 *
		 * @global wpdb $wpdb WordPress database abstraction object.
		 *
		 */
		function attachment_url_to_postid( $url ) {
			/**
			 * Default to the one included with WP; if that doesn't
			 *    exist for some reason, we'll use the one copped from WP 4.3+
			 */
			if ( function_exists( 'attachment_url_to_postid' ) ) {
				return attachment_url_to_postid( $url );
			}

			/**
			 * Proceed with the one we copied from Core if the function
			 *    didn't exist in Core for some reason
			 */
			global $wpdb;

			$dir  = wp_upload_dir();
			$path = $url;

			$site_url   = parse_url( $dir['url'] );
			$image_path = parse_url( $path );

			//force the protocols to match if needed
			if ( isset( $image_path['scheme'] ) && ( $image_path['scheme'] !== $site_url['scheme'] ) ) {
				$path = str_replace( $image_path['scheme'], $site_url['scheme'], $path );
			}

			if ( 0 === strpos( $path, $dir['baseurl'] . '/' ) ) {
				$path = substr( $path, strlen( $dir['baseurl'] . '/' ) );
			}

			$sql     = $wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
				$path
			);
			$post_id = $wpdb->get_var( $sql );

			/**
			 * Filter an attachment id found by URL.
			 *
			 * @param int|null $post_id The post_id (if any) found by the function.
			 * @param string $url The URL being looked up.
			 *
			 * @since 4.2.0
			 *
			 */
			return (int) apply_filters( 'attachment_url_to_postid', $post_id, $url );
		}


		/**
		 * Let's get rid of the default maxwidth property on oEmbeds so Flickr might behave better
		 */
		function remove_default_oembed_width( $defaults = array() ) {
			return array();
		}

		/**
		 * Output a list of pages in a site as a navigation menu
		 */
		function section_navigation() {
			$args = array( 'title_li' => null );
			if ( class_exists( '\Exclude_Pages_From_Menu_Public' ) ) {
				$args = $this->get_excluded_page_ids( $args );
			}

			echo '<nav class="widget widget_section_nav"><ul>';
			wp_list_pages( $args );
			echo '</ul></nav>';
		}

		function get_excluded_page_ids( $args = array() ) {
			global $wpdb;
			$exclude_pages     = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s AND meta_value=%s", '_epfm_meta_box_field', 'epfm_meta_box_value' ) );
			$exclude_pages_ids = '';
			foreach ( $exclude_pages as $exclude_page ) {

				if ( $exclude_pages_ids != '' ) {
					$exclude_pages_ids .= ', ';
				}

				$exclude_pages_ids .= $exclude_page;
			}

			if ( ! empty( $args['exclude'] ) ) {
				$args['exclude'] .= ',';
			} else {
				$args['exclude'] = '';
			}

			$args['exclude'] .= $exclude_pages_ids;

			return $args;
		}

		/**
		 * Retrieve the global UMW header
		 */
		function get_header() {
			do_action( 'global-umw-header' );
		}

		/**
		 * Retrieve the global UMW footer
		 */
		function get_footer() {
			do_action( 'global-umw-footer' );
		}

		/**
		 * Output the global UMW header
		 */
		function do_full_header() {
			if ( class_exists( '\Mega_Menu_Style_Manager' ) ) {
				wp_dequeue_script( 'megamenu' );
			}
			if ( defined( 'MEGAMENU_PRO_VERSION' ) ) {
				wp_dequeue_script( 'megamenu-pro' );
			}

			if ( isset( $_GET['delete_transients'] ) ) {
				delete_site_transient( 'global-umw-header' );
				delete_site_option( 'global-umw-header' );
			}
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
				$header = get_site_option( 'global-umw-header', false );
				if ( false !== $header ) {
					echo $header;
				}
				/*print( "<p>For some reason, the header code was not a string. It looked like:</p>\n<pre><code>" );
				var_dump( $header );
				print( "</code></pre>\n" );*/
			}
		}

		/**
		 * Output the global UMW footer
		 */
		function do_full_footer() {
			if ( isset( $_GET['delete_transients'] ) ) {
				delete_site_transient( 'global-umw-footer' );
				delete_site_option( 'global-umw-footer' );
			}
			$footer = get_site_transient( 'global-umw-footer' );

			if ( false === $footer ) { /* There was no valid transient */
				$footer = $this->get_footer_from_feed();
				if ( false === $footer || is_wp_error( $footer ) ) { /* We did not successfully retrieve the feed */
					$footer = get_site_option( 'global-umw-footer', false );
				}
			}

			if ( empty( $footer ) ) {
				return '';
			}

			$script_html = array();

			$dom = new \DOMDocument();
			libxml_use_internal_errors( true );
			$dom->loadHTML( $footer );
			$footer_els = $dom->getElementsByTagName( 'footer' );
			foreach ( $footer_els as $footer_el ) {
				$footer = $dom->saveHTML( $footer_el );
			}
			$footer = '<!-- Parsed UMW Global Footer -->' . $footer . '<!-- /Parsed UMW Global Footer -->';

			$script_els = $dom->getElementsByTagName( 'script' );
			foreach ( $script_els as $script_el ) {
				$script_html[] = $dom->saveHTML( $script_el );
			}
			libxml_clear_errors();

			$this->footer_scripts = implode( '', $script_html );
			add_action( 'wp_print_footer_scripts', array( $this, 'do_syndicated_footer_scripts' ), 11 );

			if ( is_string( $footer ) ) {
				if ( ! $this->shortcode_exists( 'current-date' ) || ! $this->shortcode_exists( 'current-url' ) ) {
					$this->add_shortcodes();
				}

				error_log( '[Footer Debug]: Global footer looks like the following.' );
				error_log( print_r( $footer, true ) );

				preg_match_all( '/%5Bcurrent-url(.*)%5D/', $footer, $matches );
				foreach ( $matches[0] as $key => $match ) {
					$footer = str_replace( $match, urldecode( $match ), $footer );
				}

				$footer = do_shortcode( $footer );
				echo $footer;
			} else {
				$footer = get_site_option( 'global-umw-footer', false );
				if ( false !== $footer ) {
					if ( ! $this->shortcode_exists( 'current-date' ) || ! $this->shortcode_exists( 'current-url' ) ) {
						$this->add_shortcodes();
					}

					$footer = do_shortcode( $footer );
					echo $footer;
				}
				/*print( "<p>For some reason, the footer code was not a string. It looked like:</p>\n<pre><code>" );
				var_dump( $footer );
				print( "</code></pre>\n" );*/
			}

			return;
		}

		/**
		 * Print out the necessary script tags for the global footer
		 */
		public function do_syndicated_footer_scripts() {
			if ( ! isset( $this->footer_scripts ) || empty( $this->footer_scripts ) ) {
				return;
			}

			echo '<!-- Moved UMW Global Footer Scripts -->';
			echo $this->footer_scripts;
			echo '<!-- /Moved UMW Global Footer Scripts -->';
		}

		/**
		 * Check to see if a shortcode exists
		 */
		function shortcode_exists( $shortcode = '' ) {
			global $shortcode_tags;
			if ( empty( $shortcode ) ) {
				return false;
			}

			return array_key_exists( $shortcode, $shortcode_tags );
		}

		/**
		 * Retrieve the global UMW header from the feed on the root site
		 */
		function get_header_from_feed() {
			printf( "\n<!-- Attempting to retrieve '%s' -->\n", esc_url( $this->header_feed ) );
			$header = wp_remote_get( esc_url( add_query_arg( 'time', time(), $this->header_feed ) ) );
			if ( is_wp_error( $header ) ) {
				/*print( '<pre><code>' );
				var_dump( $header );
				print( '</code></pre>' );*/
				return $header;
			}

			/*print( '<pre><code>' );
			var_dump( $header );
			print( '</code></pre>' );
			wp_die( 'Done' );*/

			if ( 200 === absint( wp_remote_retrieve_response_code( $header ) ) ) {
				$header = wp_remote_retrieve_body( $header );
				if ( ! $this->shortcode_exists( 'current-date' ) || ! $this->shortcode_exists( 'current-url' ) ) {
					$this->add_shortcodes();
				}

				$header = do_shortcode( $header );
				/*print( '<pre><code>' );
				var_dump( $header );
				print( '</code></pre>' );
				wp_die( 'Done' );*/
				set_site_transient( 'global-umw-header', $header, $this->transient_timeout );
				update_site_option( 'global-umw-header', $header );

				return $header;
			}

			return '';
		}

		/**
		 * Retrieve the global UMW footer from the feed on the root site
		 */
		function get_footer_from_feed() {
			printf( "\n<!-- Attempting to retrieve '%s' -->\n", esc_url( $this->footer_feed ) );
			$footer = wp_remote_get( add_query_arg( 'time', time(), $this->footer_feed ) );
			if ( is_wp_error( $footer ) ) {
				/*print( '<pre><code>' );
				var_dump( $footer );
				print( '</code></pre>' );
				wp_die( 'Done' );*/
				return $footer;
			}

			if ( 200 === absint( wp_remote_retrieve_response_code( $footer ) ) ) {
				$footer = wp_remote_retrieve_body( $footer );
				set_site_transient( 'global-umw-footer', $footer, $this->transient_timeout );
				update_site_option( 'global-umw-footer', $footer );

				return $footer;
			}

			return '';
		}

		/**
		 * Output the appropriate meta data to allow the Site Improve Edit button to work
		 */
		function siteimprove_edit_links() {
			if ( ! is_singular() ) {
				return;
			}

			global $post;
			$edit_uri  = admin_url( 'post.php' );
			$uri_parts = explode( '/', $edit_uri );
			array_shift( $uri_parts );
			$network = $web = '';
			while ( empty( $network ) && count( $uri_parts ) > 0 ) {
				$network = array_shift( $uri_parts );
			}
			while ( empty( $web ) && count( $uri_parts ) > 0 ) {
				$web = array_shift( $uri_parts );
			}
			if ( 'wp-admin' == $web ) {
				$web = '';
			}
			$format = '
			<!-- Site Improve URI Information -->
			<meta name="PageID" content="%d" />
			<meta name="baseURL" content="%s" />
			<meta name="site" content="%s" />
			<!-- / Site Improve URI Information -->
		';
			printf( $format, $post->ID, $network, $web );
		}


		/**
		 * Generate the content of the atoz shortcode
		 *
		 * @param array $args the list of arguments fed to the shortcode
		 *
		 * Arguments for the shortcode include:
		 *    * post_type - the type of post to be included in the list
		 *    * field - the field by which to sort the results
		 *    * view - if Views is in use on the site, a View ID can be fed to the shortcode to
		 *        format each item in the list according to that View
		 *    * child_of - if a post ID is provided, only descendents of that post will be displayed
		 *    * numberposts - how many items to show in the list
		 *    * reverse - whether to show results in a-to-z order or z-to-a order (a-to-z by default)
		 */
		function do_atoz_shortcode( $args = array() ) {
			$defaults = apply_filters( 'atoz-shortcode-defaults', array(
				'post_type'   => 'post',
				'field'       => 'title',
				'view'        => null,
				'child_of'    => 0,
				'numberposts' => - 1,
				'reverse'     => false,
				'tax_name'    => null,
				'tax_term'    => null,
				'return_link' => true,
				'alpha_links' => true,
			) );

			$atts = array();
			foreach ( $args as $k => $v ) {
				if ( ! is_numeric( $k ) ) {
					$atts[ $k ] = $v;
					continue;
				}

				if ( stristr( $v, '=' ) ) {
					$tmp = explode( '=', $v );
					if ( count( $tmp ) <= 1 ) {
						continue;
					}

					$key = array_shift( $tmp );
					$val = implode( '=', $tmp );

					$atts[ $key ] = trim( $val, ' "\'' );
				}
			}
			$args = $atts;
			$atts = null;

			$nonmeta = array(
				'ID',
				'author',
				'title',
				'name',
				'type',
				'date',
				'modified',
				'parent',
				'comment_count',
				'menu_order',
				'post__in'
			);

			$atts                = wp_parse_args( $args, $defaults );
			$args                = shortcode_atts( $defaults, $args );
			$args['return_link'] = in_array( $args['return_link'], array( true, 'true', 1, '1' ), true );
			$args['alpha_links'] = in_array( $args['alpha_links'], array( true, 'true', 1, '1' ), true );

			$transient_key = sprintf( 'atoz-%s', base64_encode( implode( '|', $args ) ) );

			$r = get_site_transient( $transient_key );
			if ( false !== $r ) {
				return $r;
			}

			$query = array(
				'post_type'      => $args['post_type'],
				'order'          => $args['reverse'] ? 'desc' : 'asc',
				'numberposts'    => $args['numberposts'],
				'posts_per_page' => $args['numberposts'],
				'post_status'    => 'publish',
			);
			if ( ! empty( $args['child_of'] ) ) {
				$query['child_of'] = $args['child_of'];
			}
			if ( ! in_array( $args['field'], $nonmeta ) ) {
				$meta              = true;
				$query['orderby']  = 'meta_value';
				$query['meta_key'] = $args['field'];
			} else {
				$meta             = false;
				$query['orderby'] = $args['field'];
			}
			if ( ! empty( $args['tax_name'] ) && ! empty( $args['tax_term'] ) ) {
				$query['tax_query'] = array(
					array(
						'taxonomy' => $args['tax_name'],
						'field'    => is_numeric( $args['tax_term'] ) ? 'term_id' : 'slug',
						'terms'    => explode( ' ', $args['tax_term'] )
					),
				);
			}

			/**
			 * Attempt to separate out custom fields or taxonomy terms
			 */
			$taxes = array_diff_key( $atts, $args );
			if ( is_array( $taxes ) && count( $taxes ) ) {
				foreach ( $taxes as $k => $v ) {
					$tmp = get_taxonomy( $k );
					if ( is_object( $tmp ) && ! is_wp_error( $tmp ) ) {
						if ( ! array_key_exists( 'tax_query', $query ) ) {
							$query['tax_query'] = array();
						}
						$query['tax_query'][] = array(
							'taxonomy' => $k,
							'field'    => is_numeric( $v ) ? 'term_id' : 'slug',
							'terms'    => explode( ' ', $v )
						);
					} else {
						if ( ! array_key_exists( 'meta_query', $query ) ) {
							$query['meta_query'] = array();
						}
						$query['meta_query'][] = array(
							'key'     => $k,
							'value'   => array( $v ),
							'compare' => 'IN'
						);
					}
				}
			}

			$posts    = new \WP_Query( $query );
			$a        = null;
			$list     = array();
			$postlist = array();
			$wrapper  = '<div>%1$s</div>';

			/**
			 * If we're using a View for the list item template, let's make sure
			 *        that each item doesn't get wrapped in the Views wrapper div
			 */
			if ( ! empty( $args['view'] ) && function_exists( 'render_view' ) ) {
				remove_shortcode( 'wpv-layout-start' );
				remove_shortcode( 'wpv-layout-end' );
				add_shortcode( 'wpv-layout-start', array( $this, '__blank' ) );
				add_shortcode( 'wpv-layout-end', array( $this, '__blank' ) );
			}

			global $post;
			if ( $posts->have_posts() ) : while ( $posts->have_posts() ) : $posts->the_post();
				setup_postdata( $post );
				if ( $meta ) {
					$o = (string) get_post_meta( get_the_ID(), $args['field'], true );
				} else {
					if ( property_exists( $post, $args['field'] ) ) {
						$o = (string) $post->{$args['field']};
					} else if ( property_exists( $post, sprintf( 'post_%s', $args['field'] ) ) ) {
						$o = (string) $post->{sprintf( 'post_%s', $args['field'] )};
					}
				}
				if ( strtolower( $o[0] ) != $a ) {
					$a      = strtolower( $o[0] );
					$list[] = $a;
				}
				if ( ! empty( $args['view'] ) && function_exists( 'render_view' ) ) {
					$tmp = render_view_template( $args['view'], $post );
					if ( substr( $tmp, 0, 3 ) == '<li' ) {
						$wrapper = '<ul>%1$s</ul>';
					}
					$postlist[ $a ][] = $tmp;
				} else {
					$postlist[ $a ][] = apply_filters( 'atoz-generic-output', sprintf( '<a href="%1$s" title="%2$s">%3$s</a>', get_permalink(), apply_filters( 'the_title_attribute', get_the_title() ), get_the_title() ), $post );
				}
			endwhile; endif;
			wp_reset_postdata();
			wp_reset_query();

			/**
			 * Let's put the wrapper div back, so that any Views rendered
			 *        outside of our list will work as expected
			 */
			if ( ! empty( $args['view'] ) && function_exists( 'render_view' ) ) {
				add_shortcode( 'wpv-layout-start', 'wpv_layout_start_shortcode' );
				add_shortcode( 'wpv-layout-end', 'wpv_layout_end_shortcode' );
			}

			if ( empty( $list ) || empty( $postlist ) ) {
				return 'The post list was empty';
			}

			$list = array_map( array( $this, 'do_alpha_link' ), $list );
			if ( empty( $args['view'] ) ) {
				foreach ( $postlist as $a => $p ) {
					$postlist[ $a ] = array_map( array( $this, 'do_generic_alpha_wrapper' ), $p );
				}
			}

			foreach ( $postlist as $a => $p ) {
				if ( $args['return_link'] ) {
					$rtlink = sprintf( '<p><a href="#%1$s" title="%2$s"><span class="%3$s"></span> %4$s</a></p>', 'letter-links-' . $transient_key, __( 'Return to the top of the list' ), 'genericon genericon-top', __( 'Return to top' ) );
				} else {
					$rtlink = '';
				}
				$postlist[ $a ] = sprintf( '<section class="atoz-alpha-letter-section"><h2 class="atoz-alpha-header-letter" id="atoz-%1$s">%2$s</h2>%3$s%4$s</section>', strtolower( $a ), strtoupper( $a ), sprintf( $wrapper, implode( '', $p ) ), $rtlink );
			}

			if ( $args['alpha_links'] ) {
				$alpha_links = '<nav class="atoz-alpha-links" id="' . 'letter-links-' . $transient_key . '"><ul><li>%1$s</li></ul></nav>';
			} else {
				$alpha_links = '';
			}

			$output = apply_filters( 'atoz-final-output',
				sprintf( $alpha_links . '<div class="atoz-alpha-content">%2$s</div>',
					implode( '</li><li>', $list ),
					implode( '', $postlist )
				), $list, $postlist
			);

			set_site_transient( $transient_key, $output, DAY_IN_SECONDS );

			return $output;
		}

		/**
		 * Set up a letter anchor link to be displayed at the top of the page
		 *
		 * @param string $letter the letter of the alphabet that's being linked
		 *
		 * @return string the linked letter
		 */
		function do_alpha_link( $letter ) {
			$format = apply_filters( 'atoz-alpha-link-format', '<a href="#atoz-%1$s">%2$s</a>' );
			$args   = apply_filters( 'atoz-alpha-link-args', array( strtolower( $letter ), strtoupper( $letter ) ) );

			return vsprintf( $format, $args );
		}

		/**
		 * If no View is used for the atoz results, we'll use this format instead
		 *
		 * @param string $value the result that's being wrapped
		 *
		 * @return string the formatted result
		 */
		function do_generic_alpha_wrapper( $value ) {
			$format = apply_filters( 'atoz-generic-alpha-wrapper-format', '<p class="atoz-item">%1$s</p>' );
			$args   = apply_filters( 'atoz-generic-alpha-wrapper-args', array( $value ) );

			return vsprintf( $format, $args );
		}

		/**
		 * Delete any transients that were set by the atoz shortcode
		 * This is generally invoked automatically when any post that could be included
		 *        in the atoz list is updated or inserted
		 */
		function clear_atoz_transients( $post_id = 0 ) {
			if ( wp_is_post_revision( $post_id ) ) {
				return;
			}
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			global $wpdb;
			$transients = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", '_site_transient_atoz-%' ) );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[A to Z Debug] Effected Transient List:' );
				error_log( print_r( $transients, true ) );
			}

			foreach ( $transients as $t ) {
				$key = str_ireplace( '_site_transient_atoz', 'atoz', $t );
				delete_site_transient( $key );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[A to Z Debug] Deleted site transient ' . $key );
				}
			}
		}

		/**
		 * Add our default settings to the Genesis settings array
		 */
		function settings_defaults( $settings = array() ) {
			$settings[ $this->setting_name ] = apply_filters( 'umw-outreach-settings-defaults', array(
				'site-title'     => null,
				'statement'      => null,
				'content'        => null,
				'image-url'      => null,
				'image-title'    => null,
				'image-subtitle' => null,
				'image-link'     => null,
			) );

			return $settings;
		}

		/**
		 * Tell Genesis to use our new filter to sanitize our settings
		 */
		function sanitizer_filters() {
			register_setting( defined( 'GENESIS_SETTINGS_FIELD' ) ? GENESIS_SETTINGS_FIELD : 'genesis-settings', $this->settings_field, array(
				$this,
				'sanitize_settings'
			) );
		}

		/**
		 * Retrieve a specific theme option
		 */
		function get_option( $key = null, $blog = false, $default = false ) {
			if ( $key === $this->setting_name ) {
				$key = null;
			}

			$opt = $allopts = $converted = false;

			$old_settings_field = defined( 'GENESIS_SETTINGS_FIELD' ) ? GENESIS_SETTINGS_FIELD : 'genesis-settings';

			if ( empty( $blog ) ) {
				$blog = $GLOBALS['blog_id'];
			}

			if ( is_multisite() ) {
				$test = get_blog_option( $blog, $this->settings_field, array() );

				if ( ! is_array( $test ) || ! array_key_exists( $this->setting_name, $test ) ) {
					/* The old version of the options doesn't exist, so, we either already converted them, or they
							never existed, so we don't need to convert them */
					update_blog_option( $blog, 'umw-outreach-mods-moved-options', $this->version );
				}
			} else {
				$test = get_option( $this->settings_field, array() );

				if ( ! is_array( $test ) || array_key_exists( $this->setting_name, $test ) ) {
					update_option( 'umw-outreach-mods-moved-options', $this->version );
				}
			}

			if ( empty( $blog ) || ( isset( $GLOBALS['blog_id'] ) && intval( $blog ) === $GLOBALS['blog_id'] ) ) {
				$converted = get_option( 'umw-outreach-mods-moved-options', false );
				if ( false !== $converted ) {
					$allopts = get_option( $this->settings_field, array() );
					if ( ! empty( $allopts ) && array_key_exists( $this->setting_name, $allopts ) ) {
						$new = $allopts;
						$old = $allopts[ $this->setting_name ];
						unset( $new[ $this->setting_name ] );
						if ( ! array_key_exists( 'image', $old ) || ! is_array( $old['image'] ) ) {
							$old['image'] = array();
						}

						foreach ( $old['image'] as $k => $v ) {
							$new[ 'image-' . $k ] = $v;
						}

						unset( $old['image'] );

						foreach ( $old as $k => $v ) {
							$new[ $k ] = $v;
						}

						update_option( $this->settings_field, $new );
						update_option( 'umw-outreach-mods-moved-options', $this->version );

						$tmp = get_option( $old_settings_field, array() );
						if ( array_key_exists( 'umw_outreach_settings', $tmp ) ) {
							unset( $tmp['umw_outreach_settings'] );
							update_option( $old_settings_field, $tmp );
						}

						$allopts = $new;
					}
				}
			} else {
				$converted = get_blog_option( $blog, 'umw-outreach-mods-moved-options', false );
				if ( false !== $converted ) {
					$allopts = get_blog_option( $blog, $this->settings_field, array() );
					if ( ! empty( $allopts ) && array_key_exists( $this->setting_name, $allopts ) ) {
						$new = $allopts;
						$old = $allopts[ $this->setting_name ];
						unset( $allopts[ $this->setting_name ] );
						if ( ! array_key_exists( 'image', $old ) || ! is_array( $old['image'] ) ) {
							$old['image'] = array();
						}

						foreach ( $old['image'] as $k => $v ) {
							$new[ 'image-' . $k ] = $v;
						}

						unset( $old['image'] );

						foreach ( $old as $k => $v ) {
							$new[ $k ] = $v;
						}

						update_blog_option( $blog, $this->settings_field, $new );
						update_blog_option( $blog, 'umw-outreach-mods-moved-options', $this->version );

						$tmp = get_blog_option( $blog, $old_settings_field, array() );
						if ( array_key_exists( 'umw_outreach_settings', $tmp ) ) {
							unset( $tmp['umw_outreach_settings'] );
							update_blog_option( $blog, $old_settings_field, $tmp );
						}

						$allopts = $new;
					}
				}
			}

			if ( empty( $key ) ) {
				return $allopts;
			}

			if ( is_array( $allopts ) && array_key_exists( $key, $allopts ) ) {
				$opt = $allopts[ $key ];
			} else {
				$opt = $default;
			}

			if ( empty( $opt ) ) {
				$tmp = $this->settings_defaults( array() );

				return $tmp[ $this->setting_name ];
			}

			return $opt;
		}

		function convert_genesis_options( $blog = false ) {
			$allopts = ( empty( $blog ) ) ? get_option( $this->settings_field, array() ) : get_blog_option( $blog, $this->settings_field, array() );

			if ( array_key_exists( 'umw_outreach_mods', $allopts ) ) {
				$oldopts = $allopts['umw_outreach_mods'];
				unset( $allopts['umw_outreach_mods'] );
			} else {
				$oldopts = array();
			}

			$opt = array_merge( $allopts, $oldopts );

			error_log( '[UMW Settings Debug]: Retrieved Genesis Settings' );
			error_log( print_r( $opt, true ) );
			if ( is_array( $opt ) && ! empty( $opt ) ) {
				$opt = stripslashes_deep( $opt );
				foreach ( $opt as $k => $v ) {
					switch ( $k ) {
						case 'statement' :
						case 'content' :
							$opt[ $k ] = html_entity_decode( $v );
							break;
						case 'image' :
							$v['subtitle'] = empty( $v['subtitle'] ) ? '' : html_entity_decode( $v['subtitle'] );
							foreach ( $v as $key => $value ) {
								$opt[ 'image-' . $key ] = $value;
							}
							break;
					}
				}
			}

			/*error_log( '[UMW Settings Debug]: Formatted New Settings' );
			error_log( print_r( $opt, true ) );*/

			if ( empty( $blog ) || ( isset( $GLOBALS['blog_id'] ) && intval( $blog ) === $GLOBALS['blog_id'] ) ) {
				add_option( $this->settings_field, $opt );
				add_option( 'umw-outreach-mods-moved-options', $this->version );
				$tmp = get_option( $this->settings_field, array() );
			} else {
				add_blog_option( $blog, $this->settings_field, $opt );
				add_blog_option( $blog, 'umw-outreach-mods-moved-options', $this->version );
				$tmp = get_blog_option( $blog, $this->settings_field, array() );
			}

			/*error_log( '[UMW Settings Debug]: Retrieved New Settings' );
			error_log( print_r( $tmp, true ) );*/

			return $tmp;
		}

		/**
		 * Retrieve a Genesis option
		 * Only used if we still haven't converted the settings to our
		 *        new settings field in an attempt to avoid mutilation during
		 *        Genesis updates
		 * @see UMW_Outreach_Mods_Sub::get_option()
		 */
		function get_genesis_option( $key, $blog = false, $default = false ) {
			$old_settings_field   = $this->settings_field;
			$this->settings_field = defined( 'GENESIS_SETTINGS_FIELD' ) ? GENESIS_SETTINGS_FIELD : 'genesis-settings';

			if ( empty( $blog ) || intval( $blog ) === $GLOBALS['blog_id'] ) {
				$opt = genesis_get_option( $key );
			} else {
				$opt = get_blog_option( $blog, $this->settings_field );
				if ( ! is_array( $opt ) || ! array_key_exists( $key, $opt ) ) {
					$opt = $default;
				} else {
					$opt = $opt[ $key ];
				}
			}

			if ( empty( $opt ) ) {
				$tmp = $this->settings_defaults( array() );

				return $tmp[ $this->setting_name ];
			}

			$this->settings_field = $old_settings_field;

			return $opt;
		}

		/**
		 * Add a new submenu page to link directly to the Customizer panel
		 */
		public function add_submenu_page() {
			add_submenu_page(
				'genesis',
				__( 'UMW Settings', 'genesis' ),
				__( 'UMW Settings', 'genesis' ),
				'edit_theme_options',
				'umw-site-settings',
				array( $this, 'do_submenu_page' )
			);
		}

		/**
		 * Handle the redirect from the submenu page to the Customizer panel
		 */
		public function do_submenu_page() {
			$redirect_to = admin_url( 'customize.php?autofocus[panel]=genesis-umw' );

			if ( ! genesis_is_menu_page( 'umw-site-settings' ) ) {
				echo '<p>This page has moved. Please <a href="' . $redirect_to . '">visit the new location in the Customizer.</a></p>';

				return;
			}

			wp_safe_redirect( esc_url_raw( $redirect_to ) );
			exit;
		}

		/**
		 * Add any metaboxes that need to appear on the Genesis settings page
		 */
		function metaboxes( $pagehook ) {
			add_meta_box( 'genesis-theme-settings-umw-outreach-settings', __( 'UMW Settings', 'genesis' ), array(
				$this,
				'settings_box'
			), $pagehook, 'main' );
		}

		/**
		 * Retrieve a formatted HTML ID for a settings field
		 */
		function get_field_id( $name ) {
			$id = '';
			switch ( $name ) {
				case 'statement' :
					$id = 'umwstatement';
					break;
				case 'content' :
					$id = 'umwcontent';
					break;
				case 'image-subtitle' :
					$id = 'umwimagesubtitle';
					break;
				default :
					$id = sprintf( '%s[%s][%s]', $this->settings_field, $this->setting_name, $name );
					break;
			}

			return $id;
		}

		/**
		 * Add UMW theme settings to Genesis Customizer panel
		 *
		 * @param Genesis_Customizer $genesis_customizer the existing configuration
		 *
		 * @access public
		 * @return void
		 * @since  0.1
		 */
		public function umw_customizer_theme_settings_config( Genesis_Customizer $genesis_customizer ) {
			$umw_config = array(
				'genesis-umw' => array(
					'active_callback' => '__return_true',
					'title'           => __( 'UMW Settings', 'genesis' ),
					'description'     => __( 'Settings specific to UMW\'s implementation of the Outreach Pro theme.', 'genesis' ),
					'settings_field'  => 'umw-site-settings',
					'control_prefix'  => 'genesis-umw',
					'theme_supports'  => 'genesis-customizer-umw-settings',
					'sections'        => array(
						'umw_settings'       => array(
							'active_callback' => '__return_true',
							'title'           => __( 'Value Proposition Settings', 'genesis' ),
							'panel'           => 'genesis-umw',
							'controls'        => array(
								'site-title' => array(
									'label'       => __( 'Site title', 'genesis' ),
									'section'     => 'umw_settings',
									'type'        => 'text',
									'input_attrs' => array(
										'placeholder' => __( 'Site title', 'genesis' ),
									),
									'settings'    => array(
										'default' => '',
									),
								),
								'statement'  => array(
									'label'    => __( 'Statement', 'genesis' ),
									'section'  => 'umw_settings',
									'type'     => 'textarea',
									'settings' => array(
										'default' => '',
									),
								),
								'content'    => array(
									'label'    => __( 'Secondary Content', 'genesis' ),
									'section'  => 'umw_settings',
									'type'     => 'textarea',
									'settings' => array(
										'default' => '',
									)
								),
							),
						),
						'umw_featured_image' => array(
							'active_callback' => '__return_true',
							'title'           => __( 'Featured Image', 'genesis' ),
							'panel'           => 'genesis-umw',
							'controls'        => array(
								'image-url'      => array(
									'label'       => __( 'Image URL', 'genesis' ),
									'section'     => 'umw_featured_image',
									'type'        => 'url',
									'input_attrs' => array(
										'placeholder' => __( 'Image URL', 'genesis' ),
									),
									'settings'    => array(
										'default' => '',
									),
								),
								'image-title'    => array(
									'label'       => __( 'Title/Caption', 'genesis' ),
									'section'     => 'umw_featured_image',
									'type'        => 'text',
									'input_attrs' => array(
										'placeholder' => __( 'Title of featured area', 'genesis' ),
									),
									'settings'    => array(
										'default' => '',
									),
								),
								'image-subtitle' => array(
									'label'    => __( 'Subtext', 'genesis' ),
									'section'  => 'umw_featured_image',
									'type'     => 'textarea',
									'settings' => array(
										'default' => '',
									),
								),
								'image-link'     => array(
									'label'       => __( 'Link Address', 'genesis' ),
									'section'     => 'umw_featured_image',
									'type'        => 'url',
									'input_attrs' => array(
										'placeholder' => __( 'Link Address', 'genesis' ),
									),
									'settings'    => array(
										'default' => '',
									),
								),
							),
						),
					),
				),
			);

			$umw_config = apply_filters( 'umw-outreach-genesis-customizer-config', $umw_config );

			return $genesis_customizer->register( $umw_config );
		}

		/**
		 * Echo a formatted HTML ID for a settings field
		 */
		function field_id( $name ) {
			echo $this->get_field_id( $name );
		}

		/**
		 * Retrieve a formatted HTML name for a settings field
		 */
		function get_field_name( $name ) {
			return sprintf( '%s[%s][%s]', $this->settings_field, $this->setting_name, $name );
		}

		/**
		 * Echo a formatted HTML name for a settings field
		 */
		function field_name( $name ) {
			echo $this->get_field_name( $name );
		}

		/**
		 * Output the settings metabox
		 */
		function settings_box() {
			$current = $this->get_option( $this->setting_name );
			do_action( 'pre-umw-outreach-settings' );
			?>
            <p><label for="<?php $this->field_id( 'site-title' ) ?>"><?php _e( 'Site Title' ) ?></label>
                <input class="widefat" type="text" name="<?php $this->field_name( 'site-title' ) ?>"
                       id="<?php $this->field_id( 'site-title' ) ?>"
                       value="<?php echo esc_html( $current['site-title'] ) ?>"/></p>
            <div><label for="<?php $this->field_id( 'statement' ) ?>"><?php _e( 'Statement' ) ?></label><br/>
				<?php wp_editor( $current['statement'], $this->get_field_id( 'statement' ), array(
					'media_buttons' => false,
					'textarea_name' => $this->get_field_name( 'statement' ),
					'textarea_rows' => 6,
					'teeny'         => true
				) ) ?></div>
            <div><label for="<?php $this->field_id( 'content' ) ?>"><?php _e( 'Secondary Content' ) ?></label><br/>
				<?php wp_editor( $current['content'], $this->get_field_id( 'content' ), array(
					'media_buttons' => false,
					'textarea_name' => $this->get_field_name( 'content' ),
					'textarea_rows' => 6,
					'teeny'         => true
				) ) ?></div>
			<?php do_action( 'pre-umw-outreach-image-settings' ) ?>
            <fieldset style="padding: 1em; border: 1px solid #e2e2e2;">
                <legend style="font-weight: 700"><?php _e( 'Featured Image' ) ?></legend>
                <p><label for="<?php $this->field_id( 'image-url' ) ?>"><?php _e( 'Image URL' ) ?></label>
                    <input class="widefat" type="url" id="<?php $this->field_id( 'image-url' ) ?>"
                           name="<?php $this->field_name( 'image-url' ) ?>"
                           value="<?php echo esc_url( $current['image']['url'] ) ?>"/><br/>
                    <span style="font-style: italic; font-size: .9em"><strong>Note:</strong> You can use the URL for <a
                                href="http://codex.wordpress.org/Embeds#Okay.2C_So_What_Sites_Can_I_Embed_From.3F"
                                target="_blank">any oEmbeddable image provider,</a> or use the direct URL of any image</span>
                </p>
                <p><label for="<?php $this->field_id( 'image-title' ) ?>"><?php _e( 'Title/Caption' ) ?></label>
                    <input class="widefat" type="text" id="<?php $this->field_id( 'image-title' ) ?>"
                           name="<?php $this->field_name( 'image-title' ) ?>"
                           value="<?php echo esc_html( $current['image']['title'] ) ?>"/></p>
                <div><label for="<?php $this->field_id( 'image-subtitle' ) ?>"><?php _e( 'Subtext' ) ?></label><br/>
					<?php wp_editor( $current['image']['subtitle'], $this->get_field_id( 'image-subtitle' ), array(
						'media_buttons' => false,
						'textarea_name' => $this->get_field_name( 'image-subtitle' ),
						'textarea_rows' => 6,
						'teeny'         => true
					) ) ?></div>
                <p><label for="<?php $this->field_id( 'image-link' ) ?>"><?php _e( 'Link Address' ) ?></label>
                    <input class="widefat" type="url" name="<?php $this->field_name( 'image-link' ) ?>"
                           id="<?php $this->field_id( 'image-link' ) ?>"
                           value="<?php echo esc_url( $current['image']['link'] ) ?>"/></p>
            </fieldset>
			<?php
			do_action( 'post-umw-outreach-settings' );
		}

		/**
		 * Sanitize all of our custom settings
		 */
		function sanitize_settings( $val = array() ) {
			if ( true === $this->sanitized_settings ) {
				return $val;
			}
			if ( empty( $val ) ) {
				return null;
			}
			$rt                   = array();
			$allowedtags          = wp_kses_allowed_html( 'user_description' );
			$allowedtags['img']   = array(
				'class' => true,
				'id'    => true,
				'title' => true,
				'src'   => true,
				'alt'   => true,
			);
			$rt['site-title']     = empty( $val['site-title'] ) ? null : sanitize_text_field( $val['site-title'] );
			$rt['statement']      = empty( $val['statement'] ) ? null : wp_kses_post( $val['statement'] );
			$rt['content']        = empty( $val['content'] ) ? null : wp_kses_post( $val['content'] );
			$rt['image-url']      = esc_url( $val['image-url'] ) ? esc_url_raw( $val['image-url'] ) : null;
			$rt['image-title']    = empty( $val['image-title'] ) ? null : sanitize_text_field( $val['image-title'] );
			$rt['image-subtitle'] = empty( $val['image-subtitle'] ) ? null : wp_kses( $val['image-subtitle'], $allowedtags );
			$rt['image-link']     = esc_url( $val['image-link'] ) ? esc_url_raw( $val['image-link'] ) : null;

			$this->sanitized_settings = true;

			return apply_filters( 'umw-site-settings-sanitized', $rt, $val );
		}

		/**
		 * Set up a shortcode for Views that outputs the last modified date
		 *
		 * @param array $atts the array of shortcode attributes
		 *
		 * @return string the modification date
		 * @uses get_option( 'date_format' ) to retrieve the default date format
		 * @uses shortcode_atts() to sanitize the list of attributes
		 * @uses apply_filters( 'get_the_modified_date' ) to format the date of a non-current post
		 *
		 * @uses get_post_modified_time() to retrieve the modification date of a non-current post
		 * @uses get_the_modified_date() to retrieve the modification date of the current post
		 */
		function wpv_last_modified( $atts = array() ) {
			$atts     = shortcode_atts( array(
				'format' => get_option( 'date_format', 'F j, Y h:i:s' ),
				'id'     => 0
			), $atts, 'wpv-last-modified' );
			$tempDate = 0;

			if ( ! empty( $atts['id'] ) ) {
				$date = '';
				if ( $tempDate < get_post_modified_time( 'U', false, $atts['id'] ) ) {
					$date = apply_filters( 'get_the_modified_date', get_post_modified_time( $atts['format'], false, $atts['id'] ), $atts['format'] );
				}

				return $date;
			}

			if ( $tempDate < get_the_modified_date( 'U' ) ) {
				return get_the_modified_date( $atts['format'] );
			}

			return '';
		}

		/**
		 * Set up a shortcode to format a telephone number & wrap it in a tel link
		 *
		 * @param array $atts the array of attributes sent to the shortcode
		 *        * format - the format in which the phone number should be output on the screen
		 *        * area - the 3-digit default area code
		 *        * exchange - the 3-digit default exchange
		 *        * country - the 1-digit country code
		 *        * title - the name of the person/office/place to which the phone number belongs
		 * @param string $content the telephone number that should be formatted
		 *
		 * @return string the formatted string with a link around it
		 * @uses shortcode_atts() to sanitize the list of shortcode attributes
		 *
		 */
		function do_tel_link_shortcode( $atts = array(), $content = '' ) {
			$original = $content;
			$content  = do_shortcode( $content );
			if ( empty( $content ) ) {
				return '';
			}

			$atts = shortcode_atts( array(
				'format'   => '###-###-####',
				'area'     => '540',
				'exchange' => '654',
				'country'  => '1',
				'title'    => '',
				'link'     => 1
			), $atts );
			if ( in_array( $atts['link'], array( 'false', false, 0, '0' ), true ) ) {
				$atts['link'] = false;
			} else {
				$atts['link'] = true;
			}
			$content  = preg_replace( '/[^0-9]/', '', $content );
			$area     = substr( preg_replace( '/[^0-9]/', '', $atts['area'] ), 0, 3 );
			$exchange = substr( preg_replace( '/[^0-9]/', '', $atts['exchange'] ), 0, 3 );
			$country  = substr( preg_replace( '/[^0-9]/', '', $atts['country'] ), 0, 1 );
			// Let's make sure the phone number ends up having 11 digits
			switch ( strlen( $content ) ) {
				/* Original number was just an extension */
				case 4 :
					$content = $atts['country'] . $atts['area'] . $atts['exchange'] . $content;
					break;
				/* Original number was just exchange + extension */
				case 7 :
					$content = $atts['country'] . $atts['area'] . $content;
					break;
				/* Original number included area code, exchange and extension */
				case 10 :
					$content = $atts['country'] . $content;
					break;
				/* Original number was complete, including country code */
				case 11 :
					break;
				/* If the original number didn't have 4, 7, 10 or 11 digits in the first place, it
						probably wasn't valid to begin with, so just return it all by itself */
				default :
					return $original;
			}
			/* If we somehow ended up with a number that doesn't have 11 digits, just bail out */
			if ( strlen( $content ) !== 11 ) {
				return $original;
			}

			/* Set up the printf format based on the format argument; replacing number signs with digit placeholders */
			$format = str_replace( '#', '%d', $atts['format'] );
			if ( $atts['link'] ) {
				$link = '<a href="tel:+%1$s" title="%2$s">%3$s</a>';
			} else {
				$link = '%3$s';
			}
			/* Store the 11-digit all-numeric string in a var to use as the link address */
			$linknum = $content;
			/* Split the 11-digit all-numeric string into individual characters */
			$linktext = str_split( $linknum );
			/* Make sure the number that will be formatted has the right number of digits */
			$output_digits = mb_substr_count( $atts['format'], '#' );
			$linktext      = array_slice( $linktext, ( 0 - absint( $output_digits ) ) );
			/* Output the phone number in the desired format */
			$format = vsprintf( $format, $linktext );
			$title  = do_shortcode( $atts['title'] );
			$title  = empty( $atts['title'] ) ? '' : esc_attr( 'Call ' . $title );

			return sprintf( $link, $linknum, $title, $format );
		}

		/**
		 * Set up a shortcode to output the current date (useful for copyrights)
		 *
		 * @param array $atts the array of attributes sent to the shortcode
		 *
		 * @return string the formatted date
		 * @uses date() to format the date
		 *
		 * @uses shortcode_atts() to sanitize the list of shortcode attributes
		 * @uses get_option( 'date_format' ) to retrieve the default date format
		 */
		function do_current_date_shortcode( $atts = array() ) {
			$atts     = shortcode_atts( array(
				'format' => get_option( 'date_format', 'F j, Y h:i:s' ),
				'before' => '',
				'after'  => '',
				'ignore' => ''
			), $atts, 'current-date' );
			$tempDate = 0;
			if ( $tempDate < date( 'U' ) ) {
				$date = date( $atts['format'] );
				if ( $date == $atts['ignore'] ) {
					return '';
				}

				return $atts['before'] . date( $atts['format'] ) . $atts['after'];
			}

			return '';
		}

		/**
		 * Set up a shortcode to output the URL of the current page
		 *
		 * @param array $atts the array of attributes sent to the shortcode
		 *
		 * @return string the URL
		 * @uses $_SERVER to retrieve the current URL
		 * @uses urlencode() to urlencode the URL if the sanitize attribute is set to true
		 *
		 * @uses shortcode_atts() to sanitize the list of shortcode attributes
		 * @uses esc_url() to escape/sanitize the URL that gets returned
		 */
		function do_current_url_shortcode( $atts = array() ) {
			$atts    = shortcode_atts( array(
				'sanitize' => false,
				'before'   => '',
				'after'    => ''
			), $atts, 'current-url' );
			$tempURL = esc_url( $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] );
			if ( in_array( $atts['sanitize'], array( 1, '1', 'true', true ), true ) ) {
				$tempURL = urlencode( $tempURL );
			}
			if ( ! empty( $tempURL ) ) {
				return $atts['before'] . $tempURL . $atts['after'];
			}

			return '';
		}

		/**
		 * Make sure any desired additional post meta gets synced through
		 *        the GitHub Sync plugin
		 *
		 * @param array $meta the array of meta data being synced
		 * @param \WordPress_GitHub_Sync_Post $post the GHS post object being synced
		 *
		 * @return array the updated array of meta data
		 */
		function _ghs_extra_post_meta( $meta = array(), $post = null ) {
			$keys = apply_filters( 'umw-outreach-mods-ghs-meta-keys', array(
				'page' => array(
					'_genesis_layout',
					'_wp_page_template',
				),
				'post' => array(
					'_genesis_layout',
					'_wp_page_template',
				)
			) );
			$pt   = get_post_type( $post->id );
			if ( ! array_key_exists( $pt, $keys ) ) {
				return $meta;
			}

			foreach ( $keys[ $pt ] as $k ) {
				$tmp = get_post_meta( $post->id, $k, true );
				if ( ! empty( $tmp ) ) {
					$meta[ $k ] = $tmp;
				}
			}

			return $meta;
		}

		/**
		 * Apply custom sidebars specifically to Graduate Admissions pages based on
		 *        the custom sidebars for the top-level Graduate Admissions page
		 * This is probably not the best place for this function, but it will do
		 *        until I can come up with a better place for it.
		 */
		function do_graduate_custom_sidebars() {
			/**
			 * This isn't the install that includes the Admissions site
			 */
			if ( defined( 'UMW_IS_ROOT' ) && ! is_numeric( UMW_IS_ROOT ) ) {
				return;
			}

			/**
			 * This isn't the Admissions site
			 */
			if ( 6 != $GLOBALS['blog_id'] ) {
				return;
			}

			/**
			 * This is the admin area, so we shouldn't mess with things
			 */
			if ( is_admin() ) {
				return;
			}

			/**
			 * Grab the CustomSidebarsReplacer object
			 */
			$temp = \CustomSidebarsReplacer::instance();
			if ( ! is_object( $temp ) ) {
				return;
			}

			/**
			 * This can't be a page in the Graduate Admissions section because
			 *        it's not a page
			 */
			if ( ! is_singular( 'page' ) ) {
				return;
			}

			/**
			 * Check to see if this page has the Graduate Admissions page as one
			 *        of its ancestors
			 */
			$ancs = get_ancestors( get_the_ID(), 'page' );
			if ( ! in_array( 6, $ancs ) ) {
				return;
			}

			/**
			 * Trick Custom Sidebars into thinking we're on the top Graduate Admissions
			 *        page, instead of one of the descendant pages
			 */
			global $post;
			$post = get_post( 6 );
			$temp->store_original_post_id();

			/**
			 * Make sure we set the post information back to the original state, so the
			 *        rest of the page renders correctly
			 */
			wp_reset_postdata();
		}

		/**
		 * Custom logging function that can be short-circuited
		 *
		 * @access public
		 * @return void
		 * @since  0.1
		 */
		public static function log( $message ) {
			if ( ( ! defined( 'WP_DEBUG' ) || false === WP_DEBUG ) && ! current_user_can( 'delete_users' ) ) {
				return;
			}

			error_log( '[News Site Debug]: ' . $message );
		}

		/**
		 * Set the root path to this plugin
		 *
		 * @access public
		 * @return void
		 * @since  1.0
		 */
		public static function set_plugin_path() {
			self::$plugin_path = plugin_dir_path( dirname( __FILE__, 4 ) );
		}

		/**
		 * Set the root URL to this plugin
		 *
		 * @access public
		 * @return void
		 * @since  1.0
		 */
		public static function set_plugin_url() {
			self::$plugin_url = plugin_dir_url( dirname( __FILE__, 4 ) );
		}

		/**
		 * Returns an absolute path based on the relative path passed
		 *
		 * @param string $path the path relative to the root of this plugin
		 *
		 * @access public
		 * @return string the absolute path
		 * @since  1.0
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
		 * @return string the absolute URL
		 * @since  1.0
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

	}
}
