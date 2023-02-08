<?php

namespace UMW\Outreach\Admin;

use UMW\Outreach\Base;

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
		private bool $is_news = false;
		/**
		 * @var bool $is_events
		 * @access private
		 */
		private bool $is_events = false;

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
		 * @return void
		 * @since  2023.01
		 */
		public function init() {
			$this->is_events = ( defined( 'UMW_LOCALIST_VERSION' ) );
			$this->is_news   = is_a( $GLOBALS['umw_outreach_mods_obj'], 'UMW\Outreach\News' );

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

			add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_boxes' ), 10, 2 );
			add_action( 'bulk_edit_custom_box', array( $this, 'quick_edit_boxes' ), 10, 2 );
			add_action( 'save_post', array( $this, 'save_quick_edit' ) );
			add_action( 'save_post', array( $this, 'save_bulk_edit' ) );
			add_action( 'admin_print_footer_scripts', array( $this, 'admin_footer_scripts' ) );
		}


		/**
		 * Add extra meta data to the post list on specific sites/post types
		 *
		 * @param array $columns the list of post columns
		 *
		 * @access public
		 * @return array the updated list of columns
		 * @since  2023.01
		 */
		public function posts_columns( array $columns ): array {
			if ( $this->is_events ) {
				// This is the Events site
				if ( isset( $_GET['post_type'] ) && 'umw-localist' === $_GET['post_type'] ) {
                    $new_columns = array(
                        'featured' => __( 'Featured', 'umw/outreach-mods' ),
                        'event-date' => __( 'Event Date', 'umw/outreach-mods' ),
                    );

					return array_merge( $columns, $new_columns );
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
		 * @return void
		 * @since  2023.01
		 */
		public function custom_posts_columns( string $column_name, int $post_id ): void {
			if ( 'featured' !== $column_name && 'event-date' !== $column_name ) {
				return;
			}

            if ( 'featured' === $column_name ) {
                $this->do_featured_column( $post_id );
                return;
            } else if ( $this->is_events ) {
                $this->do_event_date_column( $post_id );
                return;
            }
		}

		/**
		 * Output the content of the "Featured" column
         *
         * @param int $post_id the ID of the post being displayed
         *
         * @access private
         * @since  0.1
         * @return void
		 */
        private function do_featured_column( int $post_id ) {
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
		 * Output the contents of the event date column
         *
         * @param int $post_id the ID of the post being displayed
         *
         * @access private
         * @since  0.1
         * @return void
		 */
        private function do_event_date_column( int $post_id ) {
	        $dates = array(
		        'start-date' => get_post_meta( $post_id, 'umw_localist_start_timestamp', true ),
		        'end-date' => get_post_meta( $post_id, 'umw_localist_end_timestamp', true ),
	        );

	        Base::log( 'Retrieved the following values for start & end dates: ' . print_r( $dates, true ) );

	        if ( empty( $dates['start-date'] ) ) {
		        Base::log( 'The event with an ID of ' . $post_id . ' does not appear to have a start date' );
		        echo '&nbsp;';
		        return;
	        }

	        $eq = false;

	        if ( $dates['end-date'] === $dates['start-date'] || empty( $dates['end-date'] ) ) {
		        $eq = true;
	        }

	        $continue = false;

	        if ( $dates['start-date'] = \DateTime::createFromFormat( 'U', $dates['start-date'] ) ) {
		        $continue = true;
	        } else {
		        Base::log( 'There was an error processing start date; it appears the date was empty' );
	        }

	        if ( $eq ) {
		        $dates['end-date'] = $dates['start-date'];
	        } else {
		        if ( $dates['end-date'] = \DateTime::createFromFormat( 'U', $dates['end-date'] ) ) {
			        $continue = true;
		        } else {
			        Base::log( 'There was an error processing end date; it appears the date was empty' );
		        }
	        }

	        if ( ! $continue ) {
		        echo '&nbsp;';
		        return;
	        }

	        if ( $dates['start-date'] === $dates['end-date'] ) {
		        if ( $dates['start-date']->format( 'Hi' ) === '0000' ) {
			        echo $dates['start-date']->format( 'Y-m-d' );
		        } else {
			        echo $dates['start-date']->format( 'Y-m-d g:i a' );
		        }
	        } else if ( $dates['start-date']->format( 'Y-m-d' ) === $dates['end-date']->format( 'Y-m-d' ) ) {
		        if ( $dates['start-date']->format( 'a' ) === $dates['end-date']->format( 'a' ) ) {
			        echo $dates['start-date']->format( 'Y-m-d g:i' ) . '-' . $dates['end-date']->format( 'g:i a' );
		        } else {
			        echo $dates['start-date']->format( 'Y-m-d g:i a' ) . '-' . $dates['end-date']->format( 'g:i a' );
		        }
	        } else {
		        echo $dates['start-date']->format( 'Y-m-d g:i a' ) . '-' . $dates['end-date']->format( 'Y-m-d g:i a' );
	        }
        }

		/**
		 * Allow sorting by "Featured"
		 *
		 * @param array $columns the list of sortable columns
		 *
		 * @access public
		 * @return array the updated list of columns
		 * @since  2023.01
		 */
		public function custom_posts_sortable( array $columns ): array {
			if ( ! $this->is_news && ! $this->is_events ) {
				return $columns;
			}

			$columns['featured'] = 'featured';

            if ( $this->is_events ) {
                $columns['event-date'] = 'event-date';
            }

			return $columns;
		}

		/**
		 * Set up the actual sorting for "Featured"
		 *
		 * @param \WP_Query $query the existing post query
		 *
		 * @access public
		 * @return void
		 * @since  2023.01
		 */
		public function do_sortable( $query ) {
			$orderby = $query->get( 'orderby' );
			if ( 'featured' === $orderby ) {
				$query->set( 'meta_key', 'umw_cb_post_is_featured' );
				$query->set( 'orderby', 'meta_value' );
			} else if ( 'event-date' === $orderby ) {
                $query->set( 'meta_key', 'umw_localist_start_timestamp' );
                $query->set( 'orderby', 'meta_value_num' );
            }
		}

		/**
		 * Set up the Quick Edit field(s)
		 *
		 * @param string $column the name of the column being handled
		 * @param string $post_type the post type handle
		 *
		 * @access public
		 * @return void
		 * @since  2023.01
		 */
		public function quick_edit_boxes( string $column, string $post_type ) {
			if ( ! $this->is_events && ! $this->is_news ) {
				return;
			}

			if ( 'featured' !== $column ) {
				return;
			}

			if ( ( $this->is_events && 'umw-localist' === $post_type ) || ( $this->is_news && 'post' === $post_type ) ) {
				$text = $this->is_news ? __( 'Is this a Featured news article?', 'umw/outreach' ) : __( 'Is this a Featured event?', 'umw/outreach' );
				printf( '<div class="inline-edit-col-right">
                        <h4>%2$s</h4>
						<label>
							<input type="checkbox" name="umw_cb_post_is_featured" value="1"> %1$s
						</label>
					</div>', __( 'Featured', 'umw/outreach' ), $text );

				return;
			}
		}

		/**
		 * Save the changes made during Quick Edit
		 *
		 * @param int $post_id
		 *
		 * @access public
		 * @return void
		 * @since  2023.01
		 */
		public function save_quick_edit( int $post_id ) {
			if ( ! $this->is_events && ! $this->is_news ) {
				return;
			}

			$post_type = get_post_type( $post_id );

			if ( ( $this->is_events && 'umw-localist' === $post_type ) || ( $this->is_news && 'post' === $post_type ) ) {

				// check inline edit nonce
				if ( ! wp_verify_nonce( $_POST['_inline_edit'], 'inlineeditnonce' ) ) {
					return;
				}

				// update checkbox
				$featured = ( isset( $_POST['umw_cb_post_is_featured'] ) && '1' == $_POST['umw_cb_post_is_featured'] ) ? '1' : '0';
				update_post_meta( $post_id, 'umw_cb_post_is_featured', $featured );

			}
		}

		/**
		 * Save the changes made during Bulk Edit
		 *
		 * @param int $post_id
		 *
		 * @access public
		 * @return void
		 * @since  2023.01
		 */
		public function save_bulk_edit( int $post_id ) {
			if ( ! $this->is_events && ! $this->is_news ) {
				return;
			}

			$post_type = get_post_type( $post_id );

			if ( ( $this->is_events && 'umw-localist' === $post_type ) || ( $this->is_news && 'post' === $post_type ) ) {

				// check bulk edit nonce
				if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-posts' ) ) {
					return;
				}

				// update checkbox
				$featured = ( isset( $_REQUEST['umw_cb_post_is_featured'] ) && '1' == $_REQUEST['umw_cb_post_is_featured'] ) ? '1' : '0';
				update_post_meta( $post_id, 'umw_cb_post_is_featured', $featured );

			}
		}

		/**
		 * Output some javascript for the quick edit box
		 *
		 * @access public
		 * @return void
		 * @since  2023.01
		 */
		public function admin_footer_scripts() {
			?>
            <script>
                jQuery(function ($) {

                    const wp_inline_edit_function = inlineEditPost.edit;

                    // we overwrite the it with our own
                    inlineEditPost.edit = function (post_id) {

                        // let's merge arguments of the original function
                        wp_inline_edit_function.apply(this, arguments);

                        // get the post ID from the argument
                        if (typeof (post_id) == 'object') { // if it is object, get the ID number
                            post_id = parseInt(this.getId(post_id));
                        }

                        // add rows to variables
                        const edit_row = $('#edit-' + post_id)
                        const post_row = $('#post-' + post_id)

                        const featuredProduct = 'Yes' === $('.column-featured .screen-reader-text', post_row).text();

                        // populate the inputs with column data
                        $(':input[name="umw_cb_post_is_featured"]', edit_row).prop('checked', featuredProduct);

                    }
                });
            </script>
			<?php
		}
	}
}