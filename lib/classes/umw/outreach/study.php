<?php
/**
 * Special treatment for the Areas of Study site
 */

namespace UMW\Outreach;

if ( ! class_exists( 'Study' ) ) {
	class Study extends Base {
		/**
		 * @var int $blog the ID of the Study blog
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
			add_action( 'genesis_before_loop', array( $this, 'do_program_feature' ), 11 );

			add_shortcode( 'wpv-oembed', array( $this, 'do_wpv_oembed' ) );

			add_filter( 'wpghs_whitelisted_post_types', array( $this, 'wpg2hs_post_types' ) );
			add_filter( 'wpghs_post_meta', array( $this, 'wp2ghs_post_meta' ), 10, 2 );
			add_filter( 'wpghs_content_export', array( $this, 'wp2ghs_template_content' ), 10, 2 );
			add_filter( 'wpghs_content_import', array( $this, 'wp2ghs_untemplate_content' ) );
		}

		function do_wpv_oembed( $atts = array(), $content = '' ) {
			$content = do_shortcode( $content );
			if ( ! esc_url( $content ) ) {
				return '';
			}

			$atts = shortcode_atts( array( 'width' => 1140, 'height' => 0 ), $atts, 'wpv-oembed' );
			$args = array();
			if ( isset( $atts['width'] ) && is_numeric( $atts['width'] ) && ! empty( $atts['width'] ) ) {
				$args['width'] = $atts['width'];
			}
			if ( isset( $atts['height'] ) && is_numeric( $atts['height'] ) && ! empty( $atts['height'] ) ) {
				$args['height'] = $atts['height'];
			}
			global $fve;
			if ( ! isset( $fve ) && class_exists( '\FluidVideoEmbed' ) ) {
				\FluidVideoEmbed::instance();

				return $fve->filter_video_embed( '', $content, null );
			} else if ( ! class_exists( '\FluidVideoEmbed' ) ) {
				return wp_oembed_get( $content, $atts );
			} else {
				return $fve->filter_video_embed( '', $content, null );
			}
		}

		/**
		 * Insert the featured video or image above the content area
		 *        on an individual program
		 */
		function do_program_feature() {
			if ( ! is_singular( 'areas' ) ) {
				return;
			}

			$video = esc_url( get_post_meta( get_the_ID(), 'wpcf-video', true ) );
			if ( empty( $video ) && ! has_post_thumbnail() ) {
				return;
			}

			if ( empty( $video ) ) {
				$feature = get_the_post_thumbnail( get_the_ID(), 'page-feature' );
			} else {
				global $fve;
				if ( ! isset( $fve ) && class_exists( '\FluidVideoEmbed' ) ) {
					\FluidVideoEmbed::instance();
				}
				if ( ! isset( $fve ) ) {
					$feature = wp_oembed_get( $video, array( 'width' => 1140 ) );
				} else {
					$feature = $fve->filter_video_embed( '', $video, null );
				}
			}

			echo '<figure class="program-feature"><div class="wrap">' . $feature . '</div></figure>';
		}

		/**
		 * Fix the Areas of Study post type archives
		 */
		function do_study_archives() {
			if ( ! is_post_type_archive() && ! is_tax() ) {
				return;
			}

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
				'post_type'   => 'areas',
				'view'        => 106,
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
			foreach ( $args as $k => $v ) {
				if ( is_numeric( $v ) ) {
					$meat .= sprintf( ' %s=%d', $k, $v );
				} else {
					$meat .= sprintf( ' %s="%s"', $k, $v );
				}
			}
			$short   = sprintf( '[atoz%s]', $meat );
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

		/**
		 * Make sure the Areas of Study post type is synced with WP Github Sync
		 * @param $types array the existing list of post types synced
		 *
		 * @access public
		 * @return array the updated list of post types
		 * @since  2019.12.03
		 */
		public function wpg2hs_post_types( $types ) {
			return array_merge( $types, array( 'areas' ) );
		}

		/**
		 * Whitelist all of the Areas of Study custom field meta for Github Sync
		 * @param array $meta the existing list of meta data
		 * @param \WordPress_GitHub_Sync_Post $post the post object being synced
		 *
		 * @access public
		 * @return array the updated list of meta data
		 * @since  2019.12.03
		 */
		public function wp2ghs_post_meta( $meta, $post ) {
			error_log( '[WPGHS Study Sync]: ' . print_r( $post, true ) );

			$new_meta = array(
				'degree-awarded',
				'home-page-feature',
				'value-proposition',
				'areas-of-study',
				'career-opportunties',
				'internships',
				'honors',
				'minor-requirements',
				'major-requirements',
				'scholarships',
				'testimonial',
				'department',
				'courses',
				'example-schedule',
				'video',
			);

			//wp_cache_delete( $post->post->ID, 'post_meta' );

			foreach ( $new_meta as $item ) {
				$tmp = $this->get_new_post_meta( 'wpcf-' . $item, $post );
				if ( ! empty( $tmp ) ) {
					$meta[ 'wpcf-' . $item ] = $tmp;
				}
			}

			return $meta;
		}

		/**
		 * Attempt to retrieve/return updated post meta when a post is being saved/exported
		 * @param string $key the meta key to be retrieved
		 * @param \WordPress_GitHub_Sync_Post $post the post object
		 *
		 * @access public
		 * @return mixed the retrieved post meta
		 * @since  2019.12.03
		 */
		public function get_new_post_meta( $key, $post ) {
			if ( isset( $_POST ) ) {
				if ( array_key_exists( $key, $_POST ) ) {
					return $_POST[$key];
				} else {
					return false;
				}
			}

			return get_post_meta( $post->post->ID, $key, true );
		}

		/**
		 * Attempt to template an Area of Study when syncing to Github
		 * @param string $content the existing content
		 * @param \WordPress_GitHub_Sync_Post $post the post being queried
		 *
		 * @access public
		 * @return string the updated content
		 * @since  2019.12.03
		 */
		public function wp2ghs_template_content( $content, $post ) {
			$new_meta = array(
				'degree-awarded',
				'home-page-feature',
				'value-proposition',
				'areas-of-study',
				'career-opportunties',
				'internships',
				'honors',
				'minor-requirements',
				'major-requirements',
				'scholarships',
				'testimonial',
				'department',
				'courses',
				'example-schedule',
				'video',
			);

			$content_add = array();
			foreach ( $new_meta as $item ) {
				$tmp = $this->get_new_post_meta( 'wpcf-' . $item, $post );
				if ( ! empty( $tmp ) ) {
					$content_add[ $item ] = $tmp;
				}
			}

			$new_content = '';
			if ( array_key_exists( 'home-page-feature', $content_add ) ) {
				$new_content .= "\n<!-- home-page-feature -->\n";
				$new_content .= '<a href="' . $content_add['home-page-feature'] . '"><img src="' . $content_add['home-page-feature'] . '" style="width: 100%; height: auto;" alt=""/></a>';
				$new_content .= "\n<!-- End home-page-feature -->\n";
			}

			if ( array_key_exists( 'video', $content_add ) ) {
				$new_content .= "\n<!-- video -->\n";
				//$new_content .= $this->do_markdown_embed( $content_add['video'] );
				$new_content .= $content_add['video'];
				$new_content .= "\n<!-- End video -->\n";
			}

			if ( array_key_exists( 'value-proposition', $content_add ) ) {
				$new_content .= "\n<!-- value-proposition -->\n";
				$new_content .= $content_add['value-proposition'];
				$new_content .= "\n<!-- End value-proposition -->\n";
			}

			if ( array_key_exists( 'degree-awarded', $content_add ) ) {
				$new_content .= "\n<!-- degree-awarded -->\n";
				$new_content .= '<h2>Degree Awarded</h2>';
				$new_content .= $content_add['degree-awarded'];
				$new_content .= "\n<!-- End degree-awarded -->";
			}

			if ( array_key_exists( 'areas-of-study', $content_add ) ) {
				$new_content .= "\n<!-- areas-of-study -->\n";
				$new_content .= '<h2>Areas of Study</h2>';
				$new_content .= $content_add['areas-of-study'];
				$new_content .= "\n<!-- End areas-of-study -->\n";
			}

			if ( array_key_exists( 'career-opportunities', $content_add ) ) {
				$new_content .= "\n<!-- career-opportunities -->\n";
				$new_content .= '<h2>Career Opportunities</h2>';
				$new_content .= $content_add['career-opportunities'];
				$new_content .= "\n<!-- End career-opportunities -->\n";
			}

			if ( array_key_exists( 'internships', $content_add ) ) {
				$new_content .= "\n<!-- internships -->\n";
				$new_content .= '<h2>Internships</h2>';
				$new_content .= $content_add['internships'];
				$new_content .= "\n<!-- End internships -->\n";
			}

			if ( array_key_exists( 'testimonial', $content_add ) ) {
				$new_content .= "\n<!-- testimonial -->\n";
				$new_content .= sprintf( '<blockquote class="program-testimonial">
  %s
</blockquote>', $content_add['testimonial'] );
				$new_content .= "\n<!-- End testimonial -->\n";
			}

			if ( array_key_exists( 'honors', $content_add ) ) {
				$new_content .= "\n<!-- honors -->\n";
				$new_content .= '<h2>Honors</h2>';
				$new_content .= $content_add['honors'];
				$new_content .= "\n<!-- End honors -->\n";
			}

			if ( array_key_exists( 'major-requirements', $content_add ) || array_key_exists( 'minor-requirements', $content_add ) ) {
				$new_content .= "\n<!-- requirements -->\n";
				$new_content .= '<h2>Requirements</h2>';
				if ( array_key_exists( 'major-requirements', $content_add ) ) {
					$new_content .= "\n<!-- major-requirements -->\n";
					$new_content .= '<h3>Major Requirements</h3>';
					$new_content .= $content_add['major-requirements'];
					$new_content .= "\n<!-- End major-requirements -->\n";
				}
				if ( array_key_exists( 'minor-requirements', $content_add ) ) {
					$new_content .= "\n<!-- minor-requirements -->\n";
					$new_content .= '<h3>Minor Requirements</h3>';
					$new_content .= $content_add['minor-requirements'];
					$new_content .= "\n<!-- End minor-requirements -->\n";
				}
				$new_content .= "\n<!-- End requirements -->\n";
			}

			if ( array_key_exists( 'scholarships', $content_add ) ) {
				$new_content .= "\n<!-- scholarships -->\n";
				$new_content .= '<h2>Scholarships</h2>';
				$new_content .= $content_add['scholarships'];
				$new_content .= "\n<!-- End scholarships -->\n";
			}

			$labels = array(
				'courses' => 'Course Listing',
				'department' => 'Department Website',
				'example-schedule' => 'Example Course Schedule',
			);
			$new_content .= "\n<!-- resource-links -->\n";
			$new_content .= '<h2>Resource Links</h2>';
			foreach ( $labels as $k => $v ) {
				if ( array_key_exists( $k, $content_add ) ) {
					$new_content .= "\n<!-- {$k} -->\n";
					$new_content .= sprintf( '<a href="%s" class="button">%s</a>', $content_add[$k], $v );
					$new_content .= "\n<!-- End {$k} -->\n";
				}
			}
			$new_content .= "\n<!-- End resource-links -->\n";

			$content = "\n<!-- Types Custom Fields: -->\n";
			$content .= $new_content;
			$content .= "\n<!-- End Types Custom Fields -->";

			return $content;
		}

		/**
		 * Remove all Types Custom Field data from content before importing back from Github
		 * @param string $content the Github content
		 *
		 * @access public
		 * @return string the updated content
		 * @since  2019.12.03
		 */
		public function wp2ghs_untemplate_content( $content ) {
			// We can safely return an empty string, since Areas of Study do not include the content editor
			return '';

			$new_meta = array(
				'degree-awarded',
				'home-page-feature',
				'value-proposition',
				'areas-of-study',
				'career-opportunties',
				'internships',
				'honors',
				'minor-requirements',
				'major-requirements',
				'scholarships',
				'testimonial',
				'department',
				'courses',
				'example-schedule',
				'video',
			);

			$start_pos = strpos( $content, "\n<!-- Types Custom Fields: -->\n" );
			$end_pos = strpos( $content, "\n<!-- End Types Custom Fields -->" );

			$start = substr( $content, 0, $start_pos );
			$end = substr( $content, ( $end_pos + strlen( "\n<!-- End Types Custom Fields -->" ) ) );

			return $start . $end;
		}

		/**
		 * Attempt to embed a video or image in mark-down-compatible HTML
		 * @param string $url the URL to the item being embedded
		 *
		 * @access public
		 * @return string the updated HTML that can be converted to markdown
		 * @since  2019.12.03
		 */
		public function do_markdown_embed( $url ) {
			if ( ! esc_url( $url ) ) {
				return '';
			}

			$t = '<a href="%1$s"><img src="%2$s" alt="" style="width: 100%; height: auto;"/></a>';

			$oembed = _wp_oembed_get_object();
			$data = $oembed->get_data( $url );

			return sprintf( $t, $url, $data['thumbnail_url'] );
		}
	}
}
