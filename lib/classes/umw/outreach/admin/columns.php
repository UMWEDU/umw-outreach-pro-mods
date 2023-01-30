<?php
namespace UMW\Outreach\Admin;

if ( ! class_exists( 'Columns' ) ) {
	class Columns {
		/**
		 * @var static Columns $instance
		 * @access private
		 */
		private static Columns $instance;
		/**
		 * @var bool $is_news
		 * @access private
		 */
		private bool $is_news=false;
		/**
		 * @var bool $is_events
		 * @access private
		 */
		private bool $is_events=false;

		/**
		 * Construct our Columns object
		 *
		 * @access private
		 * @since  0.1
		 */
		private function __construct() {
			add_action( 'admin_init', array( $this, 'init' ) );
		}

		/**
		 * Returns the instance of this class.
		 *
		 * @access  public
		 * @return  Columns
		 * @since   0.1
		 */
		public static function instance(): Columns {
			if ( ! isset( self::$instance ) ) {
				$className      = __CLASS__;
				self::$instance = new $className;
			}

			return self::$instance;
		}

		/**
		 * Instantiate necessary actions
		 *
		 * @access public
		 * @since  2023.01
		 * @return void
		 */
		public function init() {
			$this->is_events = ( defined( 'UMW_LOCALIST_VERSION' ) );
			$this->is_news = is_a( $GLOBALS['umw_outreach_mods_obj'], 'UMW\Outreach\News' );

			/**
			 * Add extra info to the post list on News & Events sites
			 */
			add_filter( 'manage_post_posts_columns', array( $this, 'posts_columns' ) );
			add_filter( 'manage_umw-localist_posts_columns', array( $this, 'posts_columns' ) );
			add_action( 'manage_post_posts_custom_column', array( $this, 'custom_posts_columns' ), 10, 2 );
			add_action( 'manage_umw-localist_posts_custom_column', array( $this, 'custom_posts_columns' ), 10, 2 );
			add_action( 'manage_edit-post_sortable_columns', array( $this, 'custom_posts_sortable' ) );
			add_action( 'manage_edit-umw-localist_sortable_columns', array( $this, 'custom_posts_sortable' ) );
			add_action( 'pre_get_posts', array( $this, 'do_sortable' ) );
		}


		/**
		 * Add extra meta data to the post list on specific sites/post types
		 *
		 * @param array $columns the list of post columns
		 *
		 * @access public
		 * @since  2023.01
		 * @return array the updated list of columns
		 */
		public function posts_columns( array $columns ): array {
			if ( $this->is_events ) {
				// This is the Events site
				if ( isset( $_GET['post_type'] ) && 'umw-localist' === $_GET['post_type'] ) {
					return array_merge( $columns, array( 'featured' => __( 'Featured', 'umw/outreach-mods' ) ) );
				}
			}

			if ( $this->is_news ) {
				return array_merge( $columns, array( 'featured' => __( 'Featured', 'umw/outreach-mods' ) ) );
			}

			return $columns;
		}

		/**
		 * Handle building and outputting the custom meta data columns in the posts list
		 *
		 * @param string $column_name the handle for the column being handled
		 * @param int $post_id the ID of the post being listed
		 *
		 * @access public
		 * @since  2023.01
		 * @return void
		 */
		public function custom_posts_columns( string $column_name, int $post_id ): void {
			if ( 'featured' !== $column_name ) {
				return;
			}

			$featured = false;
			if ( $this->is_events ) {
				// This is the Events site
				if ( 'umw-localist' === get_post_type( $post_id ) ) {
					$featured = get_post_meta( $post_id, 'umw_cb_post_is_featured', true );
				}
			} else if ( $this->is_news ) {
				// This is the News site
				$featured = get_post_meta( $post_id, 'umw_cb_post_is_featured', true );
			} else {
				return;
			}

			if ( in_array( $featured, array( 'true', '1', true, 1 ), true ) ) {
				echo '<span class="dashicons dashicons-yes" aria-hidden="true"></span><span class="screen-reader-text">Yes</span>';
			} else {
				echo '&nbsp;';
			}
		}

		/**
		 * Allow sorting by "Featured"
		 *
		 * @param array $columns the list of sortable columns
		 *
		 * @access public
		 * @since  2023.01
		 * @return array the updated list of columns
		 */
		public function custom_posts_sortable( array $columns ): array {
			if ( ! $this->is_news && ! $this->is_events ) {
				return $columns;
			}

			$columns['featured'] = 'featured';

			return $columns;
		}

		/**
		 * Set up the actual sorting for "Featured"
		 *
		 * @param \WP_Query $query the existing post query
		 *
		 * @access public
		 * @since  2023.01
		 * @return void
		 */
		public function do_sortable( $query ) {
			$orderby = $query->get('orderby');
			if ( 'featured' === $orderby ) {
				$query->set('meta_key', 'umw_cb_post_is_featured');
				$query->set('orderby','meta_value');
			}
		}
	}
}