<?php
/**
 * Implements the latest news widget for use on home pages
 */

namespace UMW\Outreach\Widgets;

class Latest_News extends \WP_Widget {
	static $widget_id=0;
	static $version = '0.1';
	private $transient_names = array();
	public $control_js = '';
	public $control_js_arr = array();

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

		add_action( 'wp_ajax_get_umw_latest_news', array( $this, 'get_ajax_response' ) );
		add_action( 'wp_ajax_get_umw_latest_news_fields', array( $this, 'get_js_widget_instance' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_script' ) );

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
			'thumbsize' => '',
			'count' => 4,
			'categories' => '',
			'tags' => '',
		) );

		$title = esc_attr( $instance['title'] );
		$source = esc_url( $instance['source'] );
		$columns = intval( $instance['columns'] );
		$count = intval( $instance['count'] );
		$thumbsize = is_array( $instance['thumbsize'] ) ? '' : esc_attr( $instance['thumbsize'] );
		$categories = isset( $instance['categories'] ) ? explode( ',', $instance['categories'] ) : array();
		$tags = isset( $instance['tags'] ) ? explode( ',', $instance['tags'] ) : array();

		$textfield = '<p><label for="%1$s">%2$s</label><input type="%5$s" name="%3$s" id="%1$s" value="%4$s" class="widefat"/></p>';
		$intfield = '<p><label for="%1$s">%2$s</label><input type="%5$s" name="%3$s" id="%1$s" value="%4$d" class="widefat"/></p>';
		$selectfield = '<p><label for="%1$s">%2$s</label><select name="%3$s" id="%1$s" class="widefat" %5$s></select></p>';

		printf( $textfield, $this->get_field_id( 'title' ), __( 'Title', 'umw-outreach-mods' ), $this->get_field_name( 'title' ), $title, 'text' );
		printf( $textfield, $this->get_field_id( 'source' ), __( 'Website', 'umw-outreach-mods' ), $this->get_field_name( 'source' ), $source, 'url' );
		printf( $intfield, $this->get_field_id( 'columns' ), __( 'Number of Columns', 'umw-outreach-mods' ), $this->get_field_name( 'columns' ), $columns, 'number' );
		printf( $intfield, $this->get_field_id( 'count' ), __( 'Total number of items to show', 'umw-outreach-mods' ), $this->get_field_name( 'count' ), $count, 'number' );
		/*printf( $intfield, $this->get_field_id( 'thumbsize[width]' ), __( 'Desired width of image', 'umw-outreach-mods' ), $this->get_field_name( 'thumbsize[width]' ), $thumbwidth, 'number' );
		printf( $intfield, $this->get_field_id( 'thumbsize[height]' ), __( 'Desired height of image', 'umw-outreach-mods' ), $this->get_field_name( 'thumbsize[height]' ), $thumbheight, 'number' );
		printf( $textfield, $this->get_field_id( 'categories' ), __( 'List of category IDs to include (separated by commas)', 'umw-outreach-mods' ), $this->get_field_name( 'categories' ), implode( ',', $categories ), 'text' );
		printf( $textfield, $this->get_field_id( 'tags' ), __( 'List of tag IDs to include (separated by commas)', 'umw-outreach-mods' ), $this->get_field_name( 'tags' ), implode( ',', $tags ), 'text' );*/
		echo '<fieldset class="umw-latest-news-feed-details" name="' . $this->get_field_name( 'feed-details-fieldset' ) . '"><legend>';
		_e( 'Feed details', 'umw-outreach-mods' );
		echo '</legend>';
		printf( $selectfield, $this->get_field_id( 'categories_select' ), __( 'Sample Categories Selector', 'umw-outreach-mods' ),  $this->get_field_name( 'categories_select[]' ), '', 'multiple' );
		printf( $selectfield, $this->get_field_id( 'tags_select' ), __( 'Tags to display', 'umw-outreach-mods' ), $this->get_field_name( 'tags_select[]' ), '', 'multiple' );
		printf( $selectfield, $this->get_field_id( 'size_select' ), __( 'Thumbnail size', 'umw-outreach-mods' ), $this->get_field_name( 'size_select' ), $thumbsize, '' );
		echo '</fieldset>';
		$this->do_loader_graphic();

		wp_enqueue_script( 'umw-latest-news-widget' );
		$this->do_control_javascript( $instance, $this->get_field_id( '' ), $this->get_field_name( 'source' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'do_localize_admin_script' ), 1, 99 );
	}

	/**
	 * Register the admin script
	 *
	 * @access public
	 * @since  0.1
	 * @return void
	 */
	public function register_admin_script() {
		wp_register_script(
			'umw-latest-news-widget',
			plugins_url( 'scripts/umw/outreach/widgets/latest-news.js', dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ),
			array( 'jquery' ),
			self::$version,
			true
		);
	}

	/**
	 * Output the JavaScript that's necessary to keep the categories/tags lists up-to-date
	 * @param \WP_Widget the specific instance of the widget
	 * @param string $widget_id the ID of the specific widget
	 * @param string $field_name the name of the fields we're looking for
	 *
	 * @access public
	 * @since  0.1
	 * @return void
	 */
	public function do_control_javascript( $instance, $widget_id='', $field_name='' ) {
		error_log( '[Latest News Debug]: Option Name: ' . print_r( $this->option_name, true ) );
		error_log( '[Latest News Debug]: Widget Number: ' . print_r( $this->number, true ) );

		$field_id = $widget_id;
		$widget_id = $this->number;
		if ( 'i' == $widget_id ) {
			$widget_id = 0;
		}

		$instance['field_name'] = $field_name;
		$instance['widget_id'] = $widget_id;
		$instance['ajax_url'] = admin_url( 'admin-ajax.php' );
		$instance['ajax_action'] = 'get_umw_latest_news';
		$instance['nonce'] = wp_create_nonce( 'umw-latest-news-ajax' );
		if ( wp_script_is( 'select2', 'registered' ) ) {
			$instance['hasSelect2'] = true;
		} else {
			$instance['hasSelect2'] = false;
		}

		$this->control_js_arr[$field_id] = $instance;

		return;
	}

	/**
	 * JSON-ize the PHP objects we'll use with our AJAX requests
	 *
	 * @access public
	 * @since  0.1
	 * @return void
	 */
	public function do_localize_admin_script() {
		$this->control_js_arr['default'] = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'action' => 'get_umw_latest_news_fields',
			'nonce' => wp_create_nonce( 'umw-latest-news-ajax' ),
		);
		wp_localize_script( 'umw-latest-news-widget', 'umw_latest_news_widget', $this->control_js_arr );
	}

	/**
	 * Attempt to run an AJAX request to the API
	 *
	 * @access public
	 * @since  0.1
	 * @return void
	 */
	public function get_ajax_response() {
		$response = array();

		error_log( '[Latest News Debug]: Option Name: ' . print_r( $this->option_name, true ) );
		error_log( '[Latest News Debug]: Widget Number: ' . print_r( $this->number, true ) );

		$widget_options_all = get_option($this->option_name);
		error_log( '[Latest News Debug]: All Widget Options: ' . print_r( $widget_options_all, true ) );

		$options = $widget_options_all[ $this->number ];
		error_log( '[Latest News Debug]: Options: ' . print_r( $options, true ) );

		$url = esc_url( $_GET['source'] );
		$api_base = $this->get_api_base( array( 'source' => $url ), false );
		$wp_api = $this->get_wp_api( $api_base, false );

		$response['source'] = $url;
		$response['url'] = array();
		$response['instance'] = array_merge( $_GET['instance'], $options );
		foreach ( array( 'categories', 'tags' ) as $t ) {
			$api_url = sprintf( '%s/%s', $wp_api, $t );
			$api_url = add_query_arg( array( 'per_page' => 100, 'hide_empty' => true ), $api_url );
			$response['url'][] = $api_url;
			$tmp = wp_remote_get( $api_url );

			if ( ! is_wp_error( $tmp ) ) {
				$pages = wp_remote_retrieve_header( $tmp, 'x-wp-totalpages' );
				if ( $pages > 1 ) {
					$page      = 1;
					$tax_array = json_decode( wp_remote_retrieve_body( $tmp ) );
					while ( $page <= $pages ) {
						$page++;
						$r = wp_remote_get( add_query_arg( 'page', $page, $api_url ) );
						$tax_array = $tax_array + json_decode( wp_remote_retrieve_body( $r ) );
					}
					$response[$t] = $tax_array;
				} else {
					$response[ $t ] = json_decode( wp_remote_retrieve_body( $tmp ) );
				}
			}
		}

		$api_url = sprintf( '%s/%s', $wp_api, 'media' );
		$api_url = add_query_arg( array( 'media_type' => 'image' ), $api_url );
		$response['url'][] = $api_url;
		$r = wp_remote_get( $api_url );
		if ( ! is_wp_error( $r ) ) {
			$data = json_decode( wp_remote_retrieve_body( $r ) );
			$sample = array_pop( $data );
			$response['image_sizes'] = $sample->media_details->sizes;
		}

		error_log( '[Latest News Debug]: AJAX Response: ' . print_r( $response, true ) );

		header("Content-type: application/json" );
		echo json_encode( $response );
		die();
	}

	/**
	 * Get Widget Instance to return to JS request
	 *
	 * @access public
	 * @since  0.1
	 * @return void
	 */
	public function get_js_widget_instance() {
		$widget_id = $_GET['widget_source_field_id'];
		$widget_number = $_GET['widget_number'];
		$widget_options_all = get_option($this->option_name);
		$instance = $widget_options_all[$widget_number];

		$instance['field_name'] = $_GET['widget_source_field_id'];
		$instance['widget_id'] = $widget_number;
		$instance['ajax_url'] = admin_url( 'admin-ajax.php' );
		$instance['ajax_action'] = 'get_umw_latest_news';
		$instance['nonce'] = wp_create_nonce( 'umw-latest-news-ajax' );
		if ( wp_script_is( 'select2', 'registered' ) ) {
			$instance['hasSelect2'] = true;
		} else {
			$instance['hasSelect2'] = false;
		}

		header("Content-type: application/json" );
		echo json_encode( $instance );
		die();
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
		$instance = wp_parse_args( $new_instance, array(
			'source' => '',
			'columns' => null,
			'thumbsize' => '',
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
		$instance['thumbsize'] = esc_attr( $new_instance['size_select'] );
		$instance['categories'] = empty( $new_instance['categories_select'] ) ? null : implode( ',', $new_instance['categories_select'] );
		$instance['tags'] = empty( $new_instance['tags_select'] ) ? null : implode( ',', $new_instance['tags_select'] );

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
			'thumbsize' => '',
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
	 * @param bool $cache whether to use transients in this request
	 *
	 * @access public
	 * @since  0.1
	 * @return string the URL to the base API
	 */
	public function get_api_base( $instance, $cache=true ) {
		$transient_name = sprintf( $this->transient_names['base'], $this->id );
		if ( $cache ) {
			$api_base = get_transient( $transient_name );
		} else {
			$api_base = false;
		}
		if ( false === $api_base ) {
			$request = wp_remote_get( $instance['source'] );
			$link_info = $this->parse_header_link( wp_remote_retrieve_header( $request, 'link' ) );
			if ( is_wp_error( $link_info ) ) {
				return '';
			}

			$api_base = $link_info['link'];
			if ( $cache ) {
				set_transient( $transient_name, $api_base, WEEK_IN_SECONDS );
			}
		}

		return $api_base;
	}

	/**
	 * Find the URL to the main WP API
	 *
	 * @param string $base the base URL for the API
	 * @param bool $cache whether to use transients with this request
	 *
	 * @access public
	 * @since  0.1
	 * @return string the URL to the main WP API
	 */
	public function get_wp_api( $base, $cache=true ) {
		$transient_name = sprintf( $this->transient_names['api'], self::$widget_id );
		if ( $cache ) {
			$wp_api = get_transient( $transient_name );
		} else {
			$wp_api = false;
		}

		if ( false === $wp_api ) {
			$request = wp_remote_get( $base );
			$response = @json_decode( wp_remote_retrieve_body( $request ) );
			if ( is_object( $response ) && property_exists( $response, 'namespaces' ) && is_array( $response->namespaces ) ) {
				foreach ( $response->namespaces as $name ) {
					if ( substr( $name, 0, 2 ) == 'wp' ) {
						$wp_api = sprintf( '%s/%s', untrailingslashit( $base ), $name );
						if ( $cache ) {
							set_transient( $transient_name, $wp_api, HOUR_IN_SECONDS );
						}
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
		$classes = array( 'latest-news-entry' );
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

		foreach ( $feature->media_details->sizes as $key=>$details ) {
			if ( $key == $size ) {
				$url = $details->source_url;
			}
		}

		if ( ! isset( $url ) ) {
			$url = $feature->media_details->sizes->full->source_url;
		}

		return sprintf( '<img src="%s" alt="%s" class="attachment size-%s"/>', $url, $feature->alt_text, $size );
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
    <figure class="featured-image">
      <a href="%3$s">%2$s</a>
    </figure>
    <h1 class="post-title">
      <a href="%3$s">%4$s</a>
    </h1>
  </header>
  <footer>
    <p class="publish-date">
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
		if ( is_array( $header ) ) {
			foreach ( $header as $h ) {
				$tmp = $this->parse_header_link( $h );
				if ( array_key_exists( 'rel', $tmp ) && $tmp['rel'] == 'https://api.w.org/' ) {
					return $tmp;
				}
			}

			return new \WP_Error( 'not-found', __( 'The header link was an array, but the link for the API was not found', 'umw-outreach-mods' ), $header );
		}

		preg_match( '/<(.*?)>; (.*?)="(.*?)"/m', $header, $matches );
		if ( empty( $matches ) ) {
			return new \WP_Error( 'no-response', __( 'The header link could not be parsed', 'umw-outreach-mods' ), $header );
		}

		return array(
			'link' => $matches[1],
			$matches[2] => $matches[3],
		);
	}

	/**
	 * Output the loader graphic that shows when the tags/categories selectors are being updated
	 *
	 * @access private
	 * @since  0.1
	 * @return void
	 */
	private function do_loader_graphic() {
		$out = <<<EOF
<div class="floatingCirclesG">
	<div class="f_circleG frotateG_01"></div>
	<div class="f_circleG frotateG_02"></div>
	<div class="f_circleG frotateG_03"></div>
	<div class="f_circleG frotateG_04"></div>
	<div class="f_circleG frotateG_05"></div>
	<div class="f_circleG frotateG_06"></div>
	<div class="f_circleG frotateG_07"></div>
	<div class="f_circleG frotateG_08"></div>
</div>
<style>
.floatingCirclesG{
	display: none;
	position:relative;
	width:32px;
	height:32px;
	margin:auto;
	transform:scale(0.6);
		-o-transform:scale(0.6);
		-ms-transform:scale(0.6);
		-webkit-transform:scale(0.6);
		-moz-transform:scale(0.6);
}

.f_circleG{
	position:absolute;
	background-color:rgb(255,255,255);
	height:6px;
	width:6px;
	border-radius:3px;
		-o-border-radius:3px;
		-ms-border-radius:3px;
		-webkit-border-radius:3px;
		-moz-border-radius:3px;
	animation-name:f_fadeG;
		-o-animation-name:f_fadeG;
		-ms-animation-name:f_fadeG;
		-webkit-animation-name:f_fadeG;
		-moz-animation-name:f_fadeG;
	animation-duration:0.832s;
		-o-animation-duration:0.832s;
		-ms-animation-duration:0.832s;
		-webkit-animation-duration:0.832s;
		-moz-animation-duration:0.832s;
	animation-iteration-count:infinite;
		-o-animation-iteration-count:infinite;
		-ms-animation-iteration-count:infinite;
		-webkit-animation-iteration-count:infinite;
		-moz-animation-iteration-count:infinite;
	animation-direction:normal;
		-o-animation-direction:normal;
		-ms-animation-direction:normal;
		-webkit-animation-direction:normal;
		-moz-animation-direction:normal;
}

.frotateG_01{
	left:0;
	top:13px;
	animation-delay:0.3095s;
		-o-animation-delay:0.3095s;
		-ms-animation-delay:0.3095s;
		-webkit-animation-delay:0.3095s;
		-moz-animation-delay:0.3095s;
}

.frotateG_02{
	left:4px;
	top:4px;
	animation-delay:0.416s;
		-o-animation-delay:0.416s;
		-ms-animation-delay:0.416s;
		-webkit-animation-delay:0.416s;
		-moz-animation-delay:0.416s;
}

.frotateG_03{
	left:13px;
	top:0;
	animation-delay:0.5225s;
		-o-animation-delay:0.5225s;
		-ms-animation-delay:0.5225s;
		-webkit-animation-delay:0.5225s;
		-moz-animation-delay:0.5225s;
}

.frotateG_04{
	right:4px;
	top:4px;
	animation-delay:0.619s;
		-o-animation-delay:0.619s;
		-ms-animation-delay:0.619s;
		-webkit-animation-delay:0.619s;
		-moz-animation-delay:0.619s;
}

.frotateG_05{
	right:0;
	top:13px;
	animation-delay:0.7255s;
		-o-animation-delay:0.7255s;
		-ms-animation-delay:0.7255s;
		-webkit-animation-delay:0.7255s;
		-moz-animation-delay:0.7255s;
}

.frotateG_06{
	right:4px;
	bottom:4px;
	animation-delay:0.832s;
		-o-animation-delay:0.832s;
		-ms-animation-delay:0.832s;
		-webkit-animation-delay:0.832s;
		-moz-animation-delay:0.832s;
}

.frotateG_07{
	left:13px;
	bottom:0;
	animation-delay:0.9385s;
		-o-animation-delay:0.9385s;
		-ms-animation-delay:0.9385s;
		-webkit-animation-delay:0.9385s;
		-moz-animation-delay:0.9385s;
}

.frotateG_08{
	left:4px;
	bottom:4px;
	animation-delay:1.035s;
		-o-animation-delay:1.035s;
		-ms-animation-delay:1.035s;
		-webkit-animation-delay:1.035s;
		-moz-animation-delay:1.035s;
}



@keyframes f_fadeG{
	0%{
		background-color:rgb(0,0,0);
	}

	100%{
		background-color:rgb(255,255,255);
	}
}

@-o-keyframes f_fadeG{
	0%{
		background-color:rgb(0,0,0);
	}

	100%{
		background-color:rgb(255,255,255);
	}
}

@-ms-keyframes f_fadeG{
	0%{
		background-color:rgb(0,0,0);
	}

	100%{
		background-color:rgb(255,255,255);
	}
}

@-webkit-keyframes f_fadeG{
	0%{
		background-color:rgb(0,0,0);
	}

	100%{
		background-color:rgb(255,255,255);
	}
}

@-moz-keyframes f_fadeG{
	0%{
		background-color:rgb(0,0,0);
	}

	100%{
		background-color:rgb(255,255,255);
	}
}
</style>
EOF;

		echo $out;
	}
}