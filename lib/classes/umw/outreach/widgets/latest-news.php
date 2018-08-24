<?php
/**
 * Implements the latest news widget for use on home pages
 */

namespace UMW\Outreach\Widgets;

class Latest_News extends \WP_Widget {
	static $widget_id=0;
	private $transient_names = array();

	/**
	 * Latest_News constructor.
	 *
	 * @param string $id_base the base used for HTML IDs
	 * @param string $name the full-text name of the widget
	 * @param array $widget_options
	 * @param array $control_options
	 *
	 * @access public
	 * @since  0.1
	 */
	function __construct( $id_base='', $name='', array $widget_options = array(), array $control_options = array() ) {
		$id_base = 'umw-latest-news';
		$name = __( 'UMW Latest News', 'umw-outreach-mods' );
		$widget_options = array(
			'description' => __( 'Outputs a list of most recent stories for use on a front page', 'umw-outreach-mods' ),
		);

		$this->transient_names = array(
			'base' => 'umw-latest-news-widget-%s-api-base',
			'api' => 'umw-latest-news-widget-%s-wp-api',
			'posts' => 'umw-latest-news-widget-%s-posts-array',
		);

		parent::__construct( $id_base, $name, $widget_options, $control_options );
	}

	/**
	 * Clear out the existing transients
	 *
	 * @param \WP_Widget the existing instance of this widget
	 *
	 * @access public
	 * @since  0.1
	 * @return void
	 */
	public function delete_transients( $instance ) {
		foreach ( $this->transient_names as $t ) {
			delete_transient( sprintf( $t, $instance->id ) );
		}

		return;
	}

	/**
	 * Generate the form that allows control of the widget
	 * @param array $instance the existing settings for this instance of the widget
	 *
	 * @access public
	 * @since  0.1
	 * @return string
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( $instance, array(
			'title' => '',
			'source' => '',
			'columns' => 4,
			'thumbsize' => array(
				'width' => 285,
				'height' => 160
			),
			'count' => 4,
			'categories' => '',
			'tags' => '',
		) );

		$title = esc_attr( $instance['title'] );
		$source = esc_url( $instance['source'] );
		$columns = intval( $instance['columns'] );
		$count = intval( $instance['count'] );
		$thumbwidth = intval( $instance['thumbsize']['width'] );
		$thumbheight = intval( $instance['thumbsize']['height'] );
		$categories = isset( $instance['categories'] ) ? explode( ',', $instance['categories'] ) : array();
		$tags = isset( $instance['tags'] ) ? explode( ',', $instance['tags'] ) : array();

		$textfield = '<p><label for="%1$s">%2$s</label><input type="%5$s" name="%3$s" id="%1$s" value="%4$s" class="widefat"/></p>';
		$intfield = '<p><label for="%1$s">%2$s</label><input type="%5$s" name="%3$s" id="%1$s" value="%4$d" class="widefat"/></p>';

		printf( $textfield, $this->get_field_id( 'title' ), __( 'Title', 'umw-outreach-mods' ), $this->get_field_name( 'title' ), $title, 'text' );
		printf( $textfield, $this->get_field_id( 'source' ), __( 'Website', 'umw-outreach-mods' ), $this->get_field_name( 'source' ), $source, 'url' );
		printf( $intfield, $this->get_field_id( 'columns' ), __( 'Number of Columns', 'umw-outreach-mods' ), $this->get_field_name( 'columns' ), $columns, 'number' );
		printf( $intfield, $this->get_field_id( 'count' ), __( 'Total number of items to show', 'umw-outreach-mods' ), $this->get_field_name( 'count' ), $count, 'number' );
		printf( $intfield, $this->get_field_id( 'thumbsize[width]' ), __( 'Desired width of image', 'umw-outreach-mods' ), $this->get_field_name( 'thumbsize[width]' ), $thumbwidth, 'number' );
		printf( $intfield, $this->get_field_id( 'thumbsize[height]' ), __( 'Desired height of image', 'umw-outreach-mods' ), $this->get_field_name( 'thumbsize[height]' ), $thumbheight, 'number' );
		printf( $textfield, $this->get_field_id( 'categories' ), __( 'List of category IDs to include (separated by commas)', 'umw-outreach-mods' ), $this->get_field_name( 'categories' ), implode( ',', $categories ), 'text' );
		printf( $textfield, $this->get_field_id( 'tags' ), __( 'List of tag IDs to include (separated by commas)', 'umw-outreach-mods' ), $this->get_field_name( 'tags' ), implode( ',', $tags ), 'text' );
	}

	/**
	 * Save the settings for an individual widget
	 * @param array $new_instance the new settings for the widget
	 * @param array $old_instance the existing settings for the widget
	 *
	 * @access public
	 * @since  0.1
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = wp_parse_args( $instance, array(
			'source' => '',
			'columns' => null,
			'thumbsize' => array(
				'width' => null,
				'height' => null
			),
			'count' => null,
			'categories' => null,
			'tags' => null,
		) );

		if ( $new_instance['source'] != $old_instance['source'] ) {
			$this->delete_transients( $new_instance );
		}

		$instance['title'] = esc_attr( $new_instance['title'] );
		$instance['source'] = esc_url( $new_instance['source'] );
		$instance['columns'] = intval( $new_instance['columns'] );
		$instance['count'] = intval( $new_instance['count'] );
		$instance['thumbsize'] = array(
			'width' => intval( $new_instance['thumbsize']['width'] ),
			'height' => intval( $new_instance['thumbsize']['height'] ),
		);
		$instance['categories'] = empty( $new_instance['categories'] ) ? null : $new_instance['categories'];
		$instance['tags'] = empty( $new_instance['tags'] ) ? null : $new_instance['tags'];

		return $instance;
	}

	/**
	 * Output the widget itself
	 *
	 * @param array $args the general arguments for the widget
	 * @param array $instance the specific settings for this instance of the widget
	 *
	 * @access public
	 * @since  0.1
	 * @return void
	 */
	function widget( $args, $instance ) {
		/* Bail out if the source URL is not set */
		if ( ! array_key_exists( 'source', $instance ) || empty( $instance['source'] ) ) {
			return;
		}

		$instance = wp_parse_args( $instance, array(
			'source' => '',
			'columns' => 4,
			'thumbsize' => array(
				'width' => 285,
				'height' => 160
			),
			'count' => 4,
		) );
		$instance['widget_id'] = $args['id'];
		self::$widget_id = $instance['widget_id'];

		$args = wp_parse_args( $args, array(
			'before_widget' => '',
			'after_widget' => '',
			'before_title' => '',
			'after_title' => '',
		) );

		$title = apply_filters( 'widget_title', $instance['title'] );

		echo $args['before_widget'];

		if ( ! empty( $title ) ) {
			printf( '%s%s%s', $args['before_title'], $title, $args['after_title'] );
		}
		$this->content( $instance );

		echo $args['after_widget'];

		return;
	}

	/**
	 * Output the content of the widget
	 *
	 * @param array $instance the specific settings for this instance of the widget
	 *
	 * @access public
	 * @since  0.1
	 * @return void
	 */
	public function content( array $instance=array() ) {
		$api_base = $this->get_api_base( $instance );
		$wp_api = $this->get_wp_api( $api_base );
		$api_url = sprintf( '%s/posts', $wp_api );
		if ( isset( $instance['categories'] ) && ! empty( $instance['categories'] ) ) {
			$api_url = add_query_arg( 'categories', $instance['categories'], $api_url );
		}
		if ( isset( $instance['tags'] ) && ! empty( $instance['tags'] ) ) {
			$api_url = add_query_arg( 'tags', $instance['tags'], $api_url );
		}
		$api_url = add_query_arg( array(
			'per_page' => $instance['count'],
			'context' => 'view',
			'_embed' => 1
		), $api_url );
		error_log( '[Latest News Debug]: API URL: ' . $api_url );
		$posts = $this->get_posts( $api_url );
		error_log( '[Latest News Debug]: ' . print_r( $posts, true ) );
		foreach ( $posts as $index=>$post ) {
			$opts = array(
				'classes' => $this->get_css_classes( $instance['columns'], $index ),
				'feature' => $this->get_featured_image( $post, $instance['thumbsize'] ),
				'link' => $post->link,
				'title' => $post->title->rendered,
				'date' => date( 'F j, Y g:i a', strtotime( $post->modified ) ),
			);
			vprintf( $this->get_template(), $opts );
		}
	}

	/**
	 * Retrieve the location of the base API
	 *
	 * @param array $instance
	 *
	 * @access public
	 * @since  0.1
	 * @return string the URL to the base API
	 */
	public function get_api_base( $instance ) {
		$transient_name = sprintf( $this->transient_names['base'], $instance['widget_id'] );
		$api_base = get_transient( $transient_name );
		if ( false === $api_base ) {
			$request = wp_remote_get( $instance['source'] );
			$link_info = $this->parse_header_link( wp_remote_retrieve_header( $request, 'link' ) );
			if ( is_wp_error( $link_info ) ) {
				return '';
			}

			$api_base = $link_info['link'];
			set_transient( $transient_name, $api_base, HOUR_IN_SECONDS );
		}

		return $api_base;
	}

	/**
	 * Find the URL to the main WP API
	 *
	 * @param string $base the base URL for the API
	 *
	 * @access public
	 * @since  0.1
	 * @return string the URL to the main WP API
	 */
	public function get_wp_api( $base ) {
		$transient_name = sprintf( $this->transient_names['api'], self::$widget_id );
		$wp_api = get_transient( $transient_name );
		if ( false === $wp_api ) {
			$request = wp_remote_get( $base );
			$response = @json_decode( wp_remote_retrieve_body( $request ) );
			if ( is_object( $response ) && property_exists( $response, 'namespaces' ) && is_array( $response->namespaces ) ) {
				foreach ( $response->namespaces as $name ) {
					if ( substr( $name, 0, 2 ) == 'wp' ) {
						$wp_api = sprintf( '%s/%s', untrailingslashit( $base ), $name );
						set_transient( $transient_name, $wp_api, HOUR_IN_SECONDS );
						return $wp_api;
					}
				}
			}
		}

		return $wp_api;
	}

	/**
	 * Retrieve the array of posts to be displayed in this widget
	 *
	 * @param string $url the URL to query
	 *
	 * @access public
	 * @since  0.1
	 * @return array a collection of posts retrieved from the API
	 */
	public function get_posts( $url ) {
		$transient_name = sprintf( $this->transient_names['posts'], self::$widget_id );
		$posts = get_transient( $transient_name );
		if ( false !== $posts ) {
			return $posts;
		}

		$request = wp_remote_get( $url );
		$response = @json_decode( wp_remote_retrieve_body( $request ) );
		if ( ! empty( $response ) ) {
			set_transient( $transient_name, $response, HOUR_IN_SECONDS );
		}

		return $response;
	}

	/**
	 * Figure out the CSS class name(s) for this entry
	 *
	 * @param int the number of columns to display
	 * @param int the current item index in the collection
	 *
	 * @access public
	 * @since  0.1
	 * @return string the CSS class(es)
	 */
	public function get_css_classes( $columns, $index ) {
		$i = $index++;
		$classes = array( 'pcs-entry' );
		switch ( $columns ) {
			case 6 :
				$classes[] = 'one-sixth';
				break;
			case 4 :
				$classes[] = 'one-fourth';
				break;
			case 3 :
				$classes[] = 'one-third';
				break;
			case 2 :
				$classes[] = 'one-half';
				break;
			default :
				$classes[] = '';
				break;
		}

		if ( $i%$columns === 0 ) {
			$classes[] = 'first';
		}

		return implode( ' ', $classes );
	}

	/**
	 * Find the featured image for a post
	 *
	 * @param \stdClass $post the post being evaluated
	 * @param array $size the desired size of the image
	 *
	 * @access public
	 * @since  0.1
	 * @return string the featured image HTML
	 */
	public function get_featured_image( $post, $size ) {
		if ( ! property_exists( $post, '_embedded' ) ) {
			return '';
		}
		if ( ! is_object( $post->_embedded ) || ! property_exists( $post->_embedded, 'wp:featuredmedia' ) || ! is_array( $post->_embedded->{'wp:featuredmedia'} ) ) {
			return '';
		}
		$feature = array_shift( $post->_embedded->{'wp:featuredmedia'} );

		$url_widths = array();
		foreach ( $feature->media_details->sizes as $key=>$details ) {
			if ( $details->width == $size['width'] && $details->height == $size['height'] ) {
				$url = $details->source_url;
			} else if ( $details->width >= $size['width'] && $details->height >= $size['height'] ) {
				$url_widths[$key] = $details->width;
			}
		}

		if ( ! isset( $url ) ) {
			if ( count( $url_widths ) > 0 ) {
				asort( $url_widths );
				$urls = array_keys( $url_widths );
				$url  = $feature->media_details->sizes->{ array_shift( $urls ) }->source_url;
			} else {
				$url = $feature->media_details->sizes->full->source_url;
			}
		}

		return sprintf( '<img src="%s" alt="%s"/>', $url, $feature->alt_text );
	}

	/**
	 * Retrieve the format template for individual articles
	 *
	 * @access public
	 * @since  0.1
	 * @return string the format template
	 */
	public function get_template() {
		return '<article class="%1$s">
  <header>
    <figure class="pcs-featured-image">
      %2$s
    </figure>
    <h1 class="pcs-post-title">
      <a href="%3$s">%4$s</a>
    </h1>
  </header>
  <footer>
    <p class="pcs-publish-date">
      %5$s
    </p>
  </footer>
</article>';
	}

	/**
	 * Parse a 'link' HTTP header to get the base URL for a request
	 *
	 * @param string $header the link header to be parsed
	 *
	 * @access public
	 * @since  0.1
	 * @return \WP_Error|array an array with the link and the rel info
	 */
	public function parse_header_link( $header ) {
		preg_match( '/<(.*?)>; (.*?)="(.*?)"/m', $header, $matches );
		if ( empty( $matches ) ) {
			return new \WP_Error( 'no-response', __( 'The header link could not be parsed', 'umw-outreach-mods' ), $header );
		}

		return array(
			'link' => $matches[1],
			$matches[2] => $matches[3],
		);
	}
}