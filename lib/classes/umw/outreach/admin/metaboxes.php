<?php

namespace UMW\Outreach\Admin;

use UMW\Outreach\Base;

if ( ! class_exists( 'Metaboxes' ) ) {
	class Metaboxes {
		/**
		 * @var static Metaboxes $instance
		 * @access private
		 */
		private static Metaboxes $instance;
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
		 * @return  Metaboxes
		 * @since   0.1
		 */
		public static function instance(): Metaboxes {
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

			/**
			 * Add extra info to the post list on News & Events sites
			 */
			if ( ! is_admin() ) {
				return;
			}

			add_action( 'load-post.php', array( $this, 'load_meta_boxes' ) );
			add_action( 'load-post-new.php', array( $this, 'load_meta_boxes' ) );
		}

		/**
		 * Register the necessary meta boxes
		 *
		 * @access public
		 * @since  2023.02
		 * @return void
		 */
		public function load_meta_boxes() {
			if ( ! $this->is_events ) {
				return;
			}

			add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		}

		/**
		 * Register the meta boxes
		 *
		 * @param string $post_type the slug to the screen being shown
		 *
		 * @access public
		 * @since  2023.02
		 * @return void
		 */
		public function add_meta_box( string $post_type ) {
			if ( 'umw-localist' !== $post_type ) {
				return;
			}

			add_meta_box(
				'event-information',
				__( 'Event Information', 'umw/outreach-mods' ),
				array( $this, 'event_information_metabox' ),
				$post_type,
				'advanced',
				'high'
			);
		}

		/**
		 * Output the Event Information metabox
		 *
		 * @access public
		 * @since  2023.02
		 * @return void
		 */
		public function event_information_metabox() {
			$post_id = 0;
			if ( isset( $_REQUEST['post'] ) ) {
				$post_id = $_REQUEST['post'];
			} else if ( isset( $GLOBALS['post'] ) ) {
				if ( is_numeric( $GLOBALS['post'] ) ) {
					$post_id = $GLOBALS['post'];
				} else if ( is_a( $GLOBALS['post'], '\WP_Post' ) ) {
					$post_id = $GLOBALS['post']->ID;
				}
			}

			$event_info = array(
				'umw_localist_event_id' => __( 'Localist Event ID', 'umw/outreach-mods' ),
				'umw_localist_instance_id' => __( 'Localist Instance ID', 'umw/outreach-mods' ),
				'umw_localist_event_url' => __( 'Localist Event URL', 'umw/outreach-mods' ),
				'umw_localist_start_timestamp' => __( 'Event Start', 'umw/outreach-mods' ),
				'umw_localist_end_timestamp' => __( 'Event End', 'umw/outreach-mods' ),
			);

			$meta = array();

			foreach ( $event_info as $key => $item ) {
				$meta[$key] = '';

				switch( $key ) {
					case 'umw_localist_event_url' :
						$meta[$key] = esc_url( get_post_meta( $post_id, $key, true ) );
						$meta[$key] = sprintf( '<p><strong>%2$s:</strong> <a href="%1$s">%1$s</a></p>', $meta[$key], $item );
						break;
					case 'umw_localist_start_timestamp' :
					case 'umw_localist_end_timestamp' :
						$timezone = new \DateTimeZone( 'GMT' );
						$local_tz = wp_timezone();

						$time = get_post_meta( $post_id, $key, true );
						if ( ! empty( $time ) ) {
							$date = \DateTime::createFromFormat( 'U', $time, $timezone );
							if ( false === $date ) {
								break;
							}

							$date->setTimezone( $local_tz );
							$meta[$key] = sprintf( '<p><strong>%2$s:</strong> <span>%1$s</span>', $date->format( get_option( 'date_format') . ' ' . get_option( 'time_format' ) ), $item );
							break;
						}
					default :
						$value = get_post_meta( $post_id, $key, true );
						if ( empty( $value ) ) {
							break;
						}
						$meta[$key] = sprintf( '<p><strong>%2$s:</strong> <span>%1$s</span>', $value, $item );
						break;
				}
			}

			echo implode( '', $meta );
		}
	}
}