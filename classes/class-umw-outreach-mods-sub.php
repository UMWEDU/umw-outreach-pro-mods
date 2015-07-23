<?php
/**
 * Sets up the base class for UMW Outreach modifications
 * @package UMW Outreach Customizations
 * @version 0.1.37
 */
if ( ! class_exists( 'UMW_Outreach_Mods_Sub' ) ) {
	/**
	 * Define the class used on internal sites
	 */
	class UMW_Outreach_Mods_Sub {
		var $version = '0.1.37';
		var $header_feed = null;
		var $footer_feed = null;
		var $settings_field = null;
		var $setting_name = 'umw_outreach_settings';
		var $is_root = false;
		var $root_url = null;
		
		/**
		 * Build our UMW_Outreach_Mods_Sub object
		 * This object is used on all sites throughout the system except 
		 * 		for the root site of the entire system.
		 */
		function __construct() {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
			add_action( 'after_setup_theme', array( $this, 'genesis_tweaks' ), 11 );
			
			add_action( 'global-umw-header', array( $this, 'do_full_header' ) );
			add_action( 'global-umw-footer', array( $this, 'do_full_footer' ) );
			add_action( 'global-umw-header', array( $this, 'do_value_prop' ) );
			
			$this->header_feed = esc_url( sprintf( 'http://%s/feed/umw-global-header/', DOMAIN_CURRENT_SITE ) );
			$this->footer_feed = esc_url( sprintf( 'http://%s/feed/umw-global-footer/', DOMAIN_CURRENT_SITE ) );
			
			add_shortcode( 'atoz', array( $this, 'do_atoz_shortcode' ) );
			add_shortcode( 'wpv-last-modified', array( $this, 'wpv_last_modified' ) );
			add_shortcode( 'current-date', array( $this, 'do_current_date_shortcode' ) );
			add_shortcode( 'current-url', array( $this, 'do_current_url_shortcode' ) );
			
			/**
			 * Build a list of post types that, when updated, need to invalidate the atoz transients
			 */
			$this->types_that_clear_atoz_transients = apply_filters( 'umw-types-that-clear-atoz-transients', array(
				'employee', 
				'building', 
				'department', 
			) );
			if ( ! empty( $this->types_that_clear_atoz_transients ) ) {
				foreach ( $this->types_that_clear_atoz_transients as $t ) {
					add_action( "save_post_{$t}", array( $this, 'clear_atoz_transients' ) );
				}
			}
			
			if ( defined( 'GENESIS_SETTINGS_FIELD' ) )
				$this->settings_field = GENESIS_SETTINGS_FIELD;
			else
				$this->settings_field = 'genesis-settings';
			
			add_filter( 'oembed_dataparse', array( $this, 'remove_oembed_link_wrapper' ), 10, 3 );
			
			$this->transient_timeout = 10;
			
			/**
			 * Fix the employee/building/department archives until I find a better way to handle this
			 */
			add_action( 'template_redirect', array( $this, 'do_directory_archives' ) );
			
			add_action( 'template_redirect', array( $this, 'do_custom_feeds' ) );
			
			add_filter( 'jetpack_shortcodes_to_include', array( $this, 'remove_youtube_and_vimeo_from_jetpack_shortcodes' ) );
			
			/*add_action( 'plugins_loaded', array( $this, 'jetpack_fluid_video_embeds' ) );*/
			
			/*add_action( 'after_setup_theme', array( $this, 'add_theme_support' ) );*/
			
			global $content_width;
			$content_width = 1100;
			
			if ( defined( 'UMW_IS_ROOT' ) ) {
				if ( is_numeric( UMW_IS_ROOT ) && $GLOBALS['blog_id'] == UMW_IS_ROOT ) {
					$this->is_root = true;
					$this->root_url = get_bloginfo( 'url' );
				} else if ( is_numeric( UMW_IS_ROOT ) ) {
					$this->root_url = get_blog_option( UMW_IS_ROOT, 'home_url', null );
				} else {
					$this->root_url = esc_url( UMW_IS_ROOT );
				}
			}
		}
		
		function jetpack_fluid_video_embeds() {
			global $fve;
			if ( ! isset( $fve ) && class_exists( 'FluidVideoEmbed' ) )
				FluidVideoEmbed::instance();
			else if ( ! class_exists( 'FluidVideoEmbed' ) )
				return;
			
			add_filter( 'wp_video_shortcode', array( &$this, 'fve_jetpack_filter_video_embed' ), 16, 2 );
			add_filter( 'video_embed_html', array( &$this, 'fve_jetpack_filter_video_embed' ), 16 );
		}
		
		function fve_jetpack_filter_video_embed( $html, $atts=array() ) {
			if ( ! stristr( $html, 'youtube' ) && ! stristr( $html, 'vimeo' ) )
				return $html;
			
			if ( is_array( $atts ) && array_key_exists( 'src', $atts ) ) {
				global $fve;
				return $fve->filter_video_embed( '', $atts['src'] );
			}
				
			preg_match( '`http(s*?):\/\/(www\.*?)youtube.com\/embed\/([a-zA-Z0-9]{1,})`', $html, $matches );
			
			global $fve;
			return $fve->filter_video_embed( $html, sprintf( 'https://youtube.com/watch?v=%s', $matches[3] ), null );
		}
		
		function remove_youtube_and_vimeo_from_jetpack_shortcodes( $shortcodes=array() ) {
			$good_shortcodes = array();
			foreach ( $shortcodes as $s ) {
				if ( stristr( $s, 'youtube' ) || stristr( $s, 'vimeo' ) )
					continue;
					
				$good_shortcodes[] = $s;
			}
			
			return $good_shortcodes;
		}
		
		function add_theme_support() {
			/* Try using the JetPack responsive videos module instead of Fluid Video Embeds */
			add_theme_support( 'jetpack-responsive-videos' );
		}
		
		/**
		 * Output a custom Atom feed
		 */
		function do_custom_feeds() {
			if ( ! is_singular( 'atom-feed' ) )
				return;
			
			get_transient( 'custom-feed-testing' );
			set_transient( 'custom-feed-testing', 'CAG', HOUR_IN_SECONDS );
			
			global $post;
			while( have_posts() ) : the_post();
				$content = $post->post_content;
			endwhile;
			
			header('Content-Type: ' . feed_content_type('atom') . '; charset=' . get_option('blog_charset'), true);
			$more = 1;
			
			echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>';
			do_action( 'rss_tag_pre', 'atom' );
?>
<feed
	xmlns="http://www.w3.org/2005/Atom"
	xmlns:thr="http://purl.org/syndication/thread/1.0"
	xml:lang="<?php bloginfo_rss( 'language' ); ?>"
	xml:base="<?php bloginfo_rss('url') ?>/wp-atom.php"
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
			$self_link = ob_get_clean();
			$transient_key = 'custom-feed-' . base64_encode( $self_link );
			
			printf( "\n\t" . '<title type="text">%s</title>', get_bloginfo_rss( 'name' ) . get_wp_title_rss() );
			printf( "\n\t" . '<updated>%s</updated>', mysql2date( 'Y-m-d\TH:i:s\Z', get_lastpostmodified('GMT'), false ) );
			printf( "\n\t" . '<link rel="alternate" type="%1$s" href="%2$s" />', get_bloginfo_rss('html_type'), get_bloginfo_rss('url') );
			printf( "\n\t" . '<id>%s</id>', get_bloginfo('atom_url') );
			printf( "\n\t" . '<link rel="self" type="application/atom+xml" href="%s"/>', $self_link );
			printf( "\n\t" . '<transient-key>%s</transient-key>', $transient_key );
			echo "\n";
			do_action( 'atom_head' );
			
			delete_transient( $transient_key );
			$feed = get_transient( $transient_key );
			if ( false === $feed ) {
				$feed = do_shortcode( $content );
				set_transient( $transient_key, $feed, HOUR_IN_SECONDS );
			}
			echo $feed;
?>
</feed>
<?php
			exit();
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
		 * Return boolean false
		 */
		function _return_false() {
			return false;
		}
		
		/**
		 * Return the title for the employee archive page
		 */
		function do_employee_archive_title( $title ) {
			if ( is_tax( 'employee-type' ) ) {
				$ob = get_queried_object();
				if ( is_object( $ob ) && ! is_wp_error( $ob ) ) {
					return __( $ob->name . ' A to Z' );
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
		
		/**
		 * If desired, output the value proposition area below the global header
		 */
		function do_value_prop() {
			$current = $this->get_option( $this->setting_name );
			if ( empty( $current ) || ! is_array( $current ) )
				return;
			
			if ( false == $this->is_root )
				$current['site-title'] = get_bloginfo( 'name' );
			
			if ( empty( $current['site-title'] ) && empty( $current['statement'] ) && empty( $current['content'] ) )
				return;
			
			$current['statement'] = html_entity_decode( $current['statement'] );
			$current['content'] = html_entity_decode( $current['content'] );
			
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
			if ( 'photo' != $data->type )
				return $return;
			
			if ( ! in_array( $data->provider_name, apply_filters( 'oembed-image-providers-no-link', array( 'SmugMug', 'Flickr' ) ) ) )
				return $return;
			
			if ( empty( $data->url ) || empty( $data->width ) || empty( $data->height ) )
				return $return;
			if ( ! is_string( $data->url ) || ! is_numeric( $data->width ) || ! is_numeric( $data->height ) )
				return $return;
			
			$title = ! empty( $data->title ) && is_string( $data->title ) ? $data->title : '';
			return '<img src="' . esc_url( $data->url ) . '" alt="' . esc_attr($title) . '" width="' . esc_attr($data->width) . '" height="' . esc_attr($data->height) . '" />';
		}
		
		/**
		 * Set up any CSS style sheets that need to be used on the site
		 */
		function enqueue_styles() {
			/* Outreach enqueues a style sheet called google-fonts, that loads type faces we don't use */
			wp_dequeue_style( 'google-fonts' );
			/* Register our modified copy of the Outreach Pro base style sheet */
			wp_register_style( 'outreach-pro', plugins_url( '/styles/outreach-pro.css', dirname( __FILE__ ) ), array(), $this->version, 'all' );
			/* Enqueue our additional styles */
			if ( ! wp_style_is( 'genericons', 'registered' ) ) 
				wp_register_style( 'genericons', plugins_url( '/styles/genericons/genericons.css', dirname( __FILE__ ) ), array(), $GLOBALS['wp_version'], 'all' );
			wp_enqueue_style( 'umw-outreach-mods', plugins_url( '/styles/umw-outreach-mods.css', dirname( __FILE__ ) ), array( 'outreach-pro', 'genericons', 'dashicons' ), $this->version, 'all' );
		}
		
		/**
		 * Tweak anything that's done by Genesis or Outreach that's 
		 * 		not necessary for our implementation
		 */
		function genesis_tweaks() {
			if ( ! function_exists( 'genesis' ) )
				return false;
			
			/* Remove the default Genesis style sheet */
			remove_action( 'genesis_meta', 'genesis_load_stylesheet' );
			
			/* Remove the default favicon & replace it with ours */
			if ( ! has_action( 'genesis_meta', 'genesis_load_favicon' ) )
				add_action( 'genesis_meta', 'genesis_load_favicon' );
			add_filter( 'genesis_pre_load_favicon', array( $this, 'favicon_url' ) );
			
			if ( is_admin() )
				add_action( 'admin_head', 'genesis_load_favicon' );
			
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
			
			add_action( 'genesis_theme_settings_metaboxes', array( $this, 'metaboxes' ) );
			add_action( 'admin_init', array( $this, 'sanitizer_filters' ) );
			add_filter( 'genesis_available_sanitizer_filters', array( $this, 'add_sanitizer_filter' ) );
			add_filter( 'genesis_theme_settings_defaults', array( $this, 'settings_defaults' ) );
			
			add_action( 'genesis_loop', array( $this, 'home_featured_image' ), 9 );
			
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
			 *		Page Feature Image
			 */
			add_image_size( 'page-feature', 1140, 460, true );
			/**
			 * Add image size to be used as Feature Story in Sidebar
			 */
			add_image_size( 'sidebar-feature', 310, 155, true );
			
			/**
			 * If Genesis Accessible isn't active and this is a version of Genesis older than 2.2, 
			 * 		add an HTML ID to the main content section
			 */
			if ( ! function_exists( 'genwpacc_activation_check' ) && ! function_exists( 'genesis_a11y' ) )
				add_filter( 'genesis_attr_content', array( $this, 'add_content_id' ), 99, 2 );
		}
		
		/**
		 * Return the URL to our custom favicon
		 */
		function favicon_url( $url ) {
			return plugins_url( '/images/favicon.ico', dirname( __FILE__ ) );
		}
		
		/**
		 * Add an HTML ID to the <main> element to allow skip-links to work
		 */
		function add_content_id( $attr, $context ) {
			if ( 'content' != $context ) 
				return $attr;
				
			$attr['id'] = 'genesis-content';
			
			return $attr;
		}
		
		/**
		 * Output the featured image, if necessary, at the top of the home page
		 */
		function home_featured_image() {
			if ( ! is_front_page() )
				return;
				
			$current = $this->get_option( $this->setting_name );
			if ( ! is_array( $current ) || ! array_key_exists( 'image', $current ) )
				return;
			
			$img = $current['image'];
			if ( ! is_array( $img ) || ! array_key_exists( 'url', $img ) || ! esc_url( $img['url'] ) )
				return;
			
			$embed = $this->get_embedded_image( esc_url( $img['url'] ), $img );
			if ( false === $embed )
				return;
				
			$format = '<figure class="home-featured-image">';
			if ( esc_url( $img['link'] ) ) {
				$format .= '<a href="%5$s" title="%2$s">%3$s</a>';
			} else {
				$format .= '%3$s';
			}
			if ( ! empty( $img['title'] ) || ! empty( $img['subtitle'] ) ) {
				$format .= '<figcaption>';
				if ( ! empty( $img['title'] ) ) {
					if ( ! empty( $img['link'] ) ) {
						$format .= '<h2 class="home-feature-title"><a href="%5$s">%2$s</a></h2>';
					} else {
						$format .= '<h2 class="home-feature-title">%2$s</h2>';
					}
				}
				if ( ! empty( $img['subtitle'] ) ) {
					$format .= '<div class="home-feature-subtitle">%4$s</div>';
				}
				$format .= '</figcaption>';
			}
			$format .= '</figure>';
			
			printf( $format, esc_url( $img['url'] ), strip_tags( html_entity_decode( $img['title'] ), array() ), $embed, wpautop( $img['subtitle'] ), $img['link'] );
		}
		
		/**
		 * Attempt to retrieve the appropriate code for an embedded image
		 */
		function get_embedded_image( $url, $img=array('title'=>null) ) {
			add_filter( 'embed_defaults', array( $this, 'remove_default_oembed_width' ) );
			$embed = $srcs = array( 'small' => '', 'mid' => '', 'full' => '' );
			$args = array( 'width' => 400 );
			
			require_once( ABSPATH . WPINC . '/class-oembed.php' );
			$oembed = _wp_oembed_get_object();
			$provider = $oembed->get_provider( $url, $args );
			if ( false === $provider )
				return $this->get_embedded_image_direct( $url, $img );
			
			$data = $oembed->fetch( $provider, $url, $args );
			if ( false === $data )
				return $this->get_embedded_image_direct( $url, $img );
			
			/**
			 * Add support for oEmbeddable videos, just in case
			 */
			if ( 'photo' != $data->type ) {
				$args['width'] = 1140;
				$args['height'] = 800;
				return apply_filters( 'the_content', '[embed width="1140" height="800"]' . $url . '[/embed]' );
			}
			$srcs['small'] = array( 'url' => $data->url, 'width' => $data->width, 'height' => $data->height );
			
			$embed['small'] = sprintf( '<img src="%1$s" width="%2$d" height="%3$d" alt="%4$s"/>', esc_url( $data->url ), esc_attr( $data->height ), esc_attr( $data->width ), esc_attr( $img['title'] ) );
			
			$args['width'] = 800;
			$data = $oembed->fetch( $provider, $url, $args );
			if ( false !== $data ) {
				$embed['mid'] = sprintf( '<source media="(min-width:860px and max-width: 1023px)" srcset="%1$s"/>', $data->url );
				$srcs['mid'] = array( 'url' => $data->url, 'width' => $data->width, 'height' => $data->height );
			}
			
			if ( stristr( $url, 'flickr' ) ) {
				$args = array();
			} else {
				$args['width'] = 1140;
			}
			$data = $oembed->fetch( $provider, $url, $args );
			if ( false !== $data ) {
				$embed['full'] = sprintf( '<source media="(min-width: 1024px)" srcset="%1$s"/>', $data->url );
				$srcs['full'] = array( 'url' => $data->url, 'width' => $data->width, 'height' => $data->height );
			}
			
			remove_filter( 'embed_defaults', array( $this, 'remove_default_oembed_width' ) );
			
			if ( ! empty( $srcs['full'] ) && ! empty( $srcs['mid'] ) && ! empty( $srcs['small'] ) ) {
				return sprintf( '<img srcset="%1$s %2$s, %3$s %4$s" sizes="%5$s, %6$s" src="%7$s" alt="%8$s" width="%9$d" height="%10$d"/>', 
					/* 1 */$srcs['small']['url'], 
					/* 2 */$srcs['small']['width'] . 'w', 
					/* 3 */$srcs['mid']['url'], 
					/* 4 */$srcs['mid']['width'] . 'w', 
					/* 5 */'(max-width: 500px)', 
					/* 6 */'(max-width: 1024px)', 
					/* 7 */$srcs['full']['url'], 
					/* 8 */esc_attr( $img['title'] ), 
					/* 9 */$srcs['full']['width'], 
					/* 10 */$srcs['full']['height']
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
		function get_embedded_image_direct( $url, $img=array('title'=>'') ) {
			remove_filter( 'embed_defaults', array( $this, 'remove_default_oembed_width' ) );
			
			if ( ! in_array( substr( $url, -3 ), array( 'jpg', 'peg', 'png', 'gif' ) ) )
				return false;
			
			if ( ! esc_url( $url ) )
				return false;
			
			return sprintf( '<img src="%1$s" alt="%2$s"/>', esc_url( $url ), $img['title'] );
		}
		
		/**
		 * Let's get rid of the default maxwidth property on oEmbeds so Flickr might behave better
		 */
		function remove_default_oembed_width( $defaults=array() ) {
			return array();
		}
		
		/**
		 * Output a list of pages in a site as a navigation menu
		 */
		function section_navigation() {
			echo '<nav class="widget widget_section_nav"><ul>';
			wp_list_pages( array( 'title_li' => null ) );
			echo '</ul></nav>';
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
		
		/**
		 * Output the global UMW footer
		 */
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
		
		/**
		 * Retrieve the global UMW header from the feed on the root site
		 */
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
		
		/**
		 * Retrieve the global UMW footer from the feed on the root site
		 */
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
		
		/**
		 * Generate the content of the atoz shortcode
		 * @param array $args the list of arguments fed to the shortcode
		 *
		 * Arguments for the shortcode include:
		 * 	* post_type - the type of post to be included in the list
		 * 	* field - the field by which to sort the results
		 * 	* view - if Views is in use on the site, a View ID can be fed to the shortcode to 
		 * 		format each item in the list according to that View
		 * 	* child_of - if a post ID is provided, only descendents of that post will be displayed
		 * 	* numberposts - how many items to show in the list
		 * 	* reverse - whether to show results in a-to-z order or z-to-a order (a-to-z by default)
		 */
		function do_atoz_shortcode( $args=array() ) {
			$defaults = apply_filters( 'atoz-shortcode-defaults', array(
				'post_type' => 'post', 
				'field' => 'title', 
				'view' => null, 
				'child_of' => 0, 
				'numberposts' => -1, 
				'reverse' => false, 
				'tax_name' => null, 
				'tax_term' => null, 
			) );
			
			$atts = array();
			foreach ( $args as $k=>$v ) {
				if ( ! is_numeric( $k ) ) {
					$atts[$k] = $v;
					continue;
				}
				
				if ( stristr( $v, '=' ) ) {
					$tmp = explode( '=', $v );
					if ( count( $tmp ) <= 1 )
						continue;
					
					$key = array_shift( $tmp );
					$val = implode( '=', $tmp );
					
					$atts[$key] = trim( $val, ' "\'' );
				}
			}
			$args = $atts;
			$atts = null;
			
			$nonmeta = array( 'ID', 'author', 'title', 'name', 'type', 'date', 'modified', 'parent', 'comment_count', 'menu_order', 'post__in' );
			
			$atts = wp_parse_args( $args, $defaults );
			$args = shortcode_atts( $defaults, $args );
			
			$transient_key = sprintf( 'atoz-%s', base64_encode( implode( '|', $args ) ) );
			
			/*$r = get_site_transient( $transient_key );
			if ( false !== $r )
				return $r;*/
			
			$query = array(
				'post_type' => $args['post_type'], 
				'order' => $args['reverse'] ? 'desc' : 'asc', 
				'numberposts' => $args['numberposts'], 
				'posts_per_page' => $args['numberposts'], 
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
			if ( ! empty( $args['tax_name'] ) && ! empty( $args['tax_term'] ) ) {
				$query['tax_query'] = array(
					array( 
						'taxonomy' => $args['tax_name'], 
						'field' => is_numeric( $args['tax_term'] ) ? 'term_id' : 'slug', 
						'terms' => explode( ' ', $args['tax_term'] )
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
						continue;
					} else {
						if ( ! array_key_exists( 'meta_query', $query ) ) {
							$query['meta_query'] = array();
						}
						$query['meta_query'][] = array(
							'key'      => $k, 
							'value'    => array( $v ), 
							'compare'  => 'IN'
						);
					}
				}
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
				$rtlink = sprintf( '<p><a href="#%1$s" title="%2$s"><span class="%3$s"></span> %4$s</a></p>', 'letter-links-' . $transient_key, __( 'Return to the top of the list' ), 'genericon genericon-top', __( 'Return to top' ) );
				$postlist[$a] = sprintf( '<section class="atoz-alpha-letter-section"><h2 class="atoz-alpha-header-letter" id="atoz-%1$s">%2$s</h2>%3$s%4$s</section>', strtolower( $a ), strtoupper( $a ), '<div>' . implode( '', $p ) . '</div>', $rtlink );
			}
			
			$output = apply_filters( 'atoz-final-output', 
				sprintf( '<nav class="atoz-alpha-links" id="' . 'letter-links-' . $transient_key . '"><ul><li>%1$s</li></ul></nav><div class="atoz-alpha-content">%2$s</div>', 
					implode( '</li><li>', $list ), 
					implode( '', $postlist ) 
				), $list, $postlist 
			);
			
			set_site_transient( $transient_key, $output, DAY_IN_SECONDS );
			return $output;
		}
		
		/**
		 * Set up a letter anchor link to be displayed at the top of the page
		 * @param string $letter the letter of the alphabet that's being linked
		 * @return string the linked letter
		 */
		function do_alpha_link( $letter ) {
			$format = apply_filters( 'atoz-alpha-link-format', '<a href="#atoz-%1$s">%2$s</a>' );
			$args = apply_filters( 'atoz-alpha-link-args', array( strtolower( $letter ), strtoupper( $letter ) ) );
			return vsprintf( $format, $args );
		}
		
		/**
		 * If no View is used for the atoz results, we'll use this format instead
		 * @param string $value the result that's being wrapped
		 * @return string the formatted result
		 */
		function do_generic_alpha_wrapper( $value ) {
			$format = apply_filters( 'atoz-generic-alpha-wrapper-format', '<p class="atoz-item">%1$s</p>' );
			$args = apply_filters( 'atoz-generic-alpha-wrapper-args', array( $value ) );
			return vsprintf( $format, $args );
		}
		
		/**
		 * Delete any transients that were set by the atoz shortcode
		 * This is generally invoked automatically when any post that could be included 
		 * 		in the atoz list is updated or inserted
		 */
		function clear_atoz_transients() {
			if ( wp_is_post_revision( $post_id ) )
				return;
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
				return;
			
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
		 * Add a new filter for our settings to the available filters list in Genesis
		 */
		function add_sanitizer_filter( $filters=array() ) {
			$filters['umw_outreach_settings_filter'] = array( $this, 'sanitize_settings' );
			return $filters;
		}
		
		/**
		 * Add our default settings to the Genesis settings array
		 */
		function settings_defaults( $defaults=array() ) {
			$settings[$this->setting_name] = apply_filters( 'umw-outreach-settings-defaults', array(
				'site-title' => null, 
				'statement'  => null, 
				'content'    => null, 
				'image'      => array(
					'url'       => null, 
					'title'     => null, 
					'subtitle'  => null, 
					'link'      => null
				)
			) );
			return $settings;
		}
		
		/**
		 * Tell Genesis to use our new filter to sanitize our settings
		 */
		function sanitizer_filters() {
			genesis_add_option_filter( 
				'umw_outreach_settings_filter', 
				$this->settings_field, 
				array( 
					$this->setting_name, 
				)
			);
		}
		
		/**
		 * Retrive a specific Genesis option
		 */
		function get_option( $key, $blog=false, $default=false ) {
			if ( empty( $blog ) || intval( $blog ) === $GLOBALS['blog_id'] ) {
				$opt = genesis_get_option( $key );
			} else {
				$opt = get_blog_option( $blog, GENESIS_SETTINGS_FIELD );
				if ( ! is_array( $opt ) || ! array_key_exists( $key, $opt ) )
					$opt = $default;
				else
					$opt = $opt[$key];
			}
			
			if ( empty( $opt ) ) {
				$tmp = $this->settings_defaults(array());
				return $tmp[$this->setting_name];
			}
			
			return $opt;
		}
		
		/**
		 * Add any metaboxes that need to appear on the Genesis settings page
		 */
		function metaboxes( $pagehook ) {
			add_meta_box( 'genesis-theme-settings-umw-outreach-settings', __( 'UMW Settings', 'genesis' ), array( $this, 'settings_box' ), $pagehook, 'main' );
		}
		
		/**
		 * Retrieve a formatted HTML ID for a settings field
		 */
		function get_field_id( $name ) {
			return sprintf( '%s[%s][%s]', GENESIS_SETTINGS_FIELD, $this->setting_name, $name );
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
			return sprintf( '%s[%s][%s]', GENESIS_SETTINGS_FIELD, $this->setting_name, $name );
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
	<input class="widefat" type="text" name="<?php $this->field_name( 'site-title' ) ?>" id="<?php $this->field_id( 'site-title' ) ?>" value="<?php echo $current['site-title'] ?>"/></p>
<div><label for="<?php $this->field_id( 'statement' ) ?>"><?php _e( 'Statement' ) ?></label><br/> 
	<?php wp_editor( $current['statement'], $this->get_field_id( 'statement' ), array( 'media_buttons' => false, 'textarea_name' => $this->get_field_name( 'statement' ), 'textarea_rows' => 6, 'teeny' => true ) ) ?></div>
<div><label for="<?php $this->field_id( 'content' ) ?>"><?php _e( 'Secondary Content' ) ?></label><br/> 
	<?php wp_editor( $current['content'], $this->get_field_id( 'content' ), array( 'media_buttons' => false, 'textarea_name' => $this->get_field_name( 'content' ), 'textarea_rows' => 6, 'teeny' => true ) ) ?></div>
<?php do_action( 'pre-umw-outreach-image-settings' ) ?>
<fieldset style="padding: 1em; border: 1px solid #e2e2e2;">
	<legend style="font-weight: 700"><?php _e( 'Featured Image' ) ?></legend>
	<p><label for="<?php $this->field_id( 'image-url' ) ?>"><?php _e( 'Image URL' ) ?></label> 
		<input class="widefat" type="url" id="<?php $this->field_id( 'image-url' ) ?>" name="<?php $this->field_name( 'image-url' ) ?>" value="<?php echo esc_url( $current['image']['url'] ) ?>"/><br/> 
		<span style="font-style: italic; font-size: .9em"><strong>Note:</strong> You can use the URL for <a href="http://codex.wordpress.org/Embeds#Okay.2C_So_What_Sites_Can_I_Embed_From.3F" target="_blank">any oEmbeddable image provider,</a> or use the direct URL of any image</span></p>
	<p><label for="<?php $this->field_id( 'image-title' ) ?>"><?php _e( 'Title/Caption' ) ?></label> 
		<input class="widefat" type="text" id="<?php $this->field_id( 'image-title' ) ?>" name="<?php $this->field_name( 'image-title' ) ?>" value="<?php echo $current['image']['title'] ?>"/></p>
	<div><label for="<?php $this->field_id( 'image-subtitle' ) ?>"><?php _e( 'Subtext' ) ?></label><br/> 
		<?php wp_editor( $current['image']['subtitle'], $this->get_field_id( 'image-subtitle' ), array( 'media_buttons' => false, 'textarea_name' => $this->get_field_name( 'image-subtitle' ), 'textarea_rows' => 6, 'teeny' => true ) ) ?></div>
	<p><label for="<?php $this->field_id( 'image-link' ) ?>"><?php _e( 'Link Address' ) ?></label> 
		<input class="widefat" type="url" name="<?php $this->field_name( 'image-link' ) ?>" id="<?php $this->field_id( 'image-link' ) ?>" value="<?php echo esc_url( $current['image']['link'] ) ?>"/></p>
</fieldset>
<?php
			do_action( 'post-umw-outreach-settings' );
		}
		
		/**
		 * Sanitize all of our custom settings
		 */
		function sanitize_settings( $val=array() ) {
			if ( empty( $val ) ) 
				return null;
				
			$rt = array();
			
			$rt['site-title'] = empty( $val['site-title'] ) ? null : esc_attr( $val['site-title'] );
			$rt['statement'] = empty( $val['statement'] ) ? null : esc_textarea( $val['statement'] );
			$rt['content'] = empty( $val['content'] ) ? null : esc_textarea( $val['content'] );
			$rt['image'] = array();
			$rt['image']['url'] = esc_url( $val['image-url'] ) ? esc_url( $val['image-url'] ) : null;
			$rt['image']['title'] = empty( $val['image-title'] ) ? null : esc_attr( $val['image-title'] );
			$rt['image']['subtitle'] = empty( $val['image-subtitle'] ) ? null : esc_textarea( $val['image-subtitle'] );
			$rt['image']['link'] = esc_url( $val['image-link'] ) ? esc_url( $val['image-link'] ) : null;
			
			return $rt;
		}
		
		/**
		 * Set up a shortcode for Views that outputs the last modified date
		 */
		function wpv_last_modified( $atts=array() ) {
			$atts = shortcode_atts( array( 'format' => get_option( 'date_format', 'F j, Y h:i:s' ) ), $atts, 'wpv-last-modified' );
			$tempDate = 0;
			if ( $tempDate < get_the_modified_date( 'U' ) ) {
				return get_the_modified_date( $atts['format'] );
			}
			return '';
		}
		
		/**
		 * Set up a shortcode to output the current date (useful for copyrights)
		 */
		function do_current_date_shortcode( $atts=array() ) {
			$atts = shortcode_atts( array( 'format' => get_option( 'date_format', 'F j, Y h:i:s' ), 'before' => '', 'after' => '' ), $atts, 'current-date' );
			$tempDate = 0;
			if ( $tempDate < date( 'U' ) ) {
				return $atts['before'] . date( $atts['format'] ) . $atts['after'];
			}
			return '';
		}
		
		/**
		 * Set up a shortcode to output the URL of the current page
		 */
		function do_current_url_shortcode( $atts=array() ) {
			$atts = shortcode_atts( array( 'sanitize' => false, 'before' => '', 'after' => '' ), $atts, 'current-url' );
			$tempURL = esc_url( $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] );
			if ( in_array( $atts['sanitize'], array( 1, '1', 'true', true ), true ) ) {
				$tempURL = urlencode( $tempURL );
			}
			if ( ! empty( $tempURL ) )
				return $atts['before'] . $tempURL . $atts['after'];
			
			return '';
		}
	}
}