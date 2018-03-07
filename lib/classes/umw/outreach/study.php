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
			if ( ! isset( $fve ) && class_exists( 'FluidVideoEmbed' ) ) {
				FluidVideoEmbed::instance();

				return $fve->filter_video_embed( '', $content, null );
			} else if ( ! class_exists( 'FluidVideoEmbed' ) ) {
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
				if ( ! isset( $fve ) && class_exists( 'FluidVideoEmbed' ) ) {
					FluidVideoEmbed::instance();
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
	}
}
