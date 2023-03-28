<?php

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		die( 'You do not have permission to access this file directly.' );
	}
}

namespace UMW\Outreach\Post_Types {

	if ( ! class_exists( 'Areas' ) ) {
		class Areas extends Base {
			/**
			 * @var Areas $instance holds the single instance of this class
			 * @access private
			 */
			private static Areas $instance;

			/**
			 * Videos constructor.
			 */
			protected function __construct() {
				$this->register_post_type();
			}

			/**
			 * Returns the instance of this class.
			 *
			 * @access  public
			 * @return  Areas
			 * @since   0.1
			 */
			public static function instance(): Areas {
				if ( ! isset( self::$instance ) ) {
					$className      = __CLASS__;
					self::$instance = new $className;
				}

				return self::$instance;
			}

			/**
			 * Returns the handle for the post type
			 *
			 * @access protected
			 * @return string the post type handle
			 * @since  0.1
			 */
			protected function get_handle(): string {
				return 'areas';
			}

			/**
			 * Gathers the post type arguments
			 *
			 * @access protected
			 * @return array the array of arguments
			 * @since  0.1
			 */
			protected function get_args(): array {
				return array(
					'label'                 => __( 'Areas of Study', 'umw/outreach' ),
					'labels'                => $this->get_labels(),
					'description'           => '',
					'public'                => true,
					'publicly_queryable'    => true,
					'show_ui'               => true,
					'delete_with_user'      => false,
					'show_in_rest'          => true,
					'rest_base'             => '',
					'rest_controller_class' => 'WP_REST_Posts_Controller',
					'has_archive'           => true,
					'show_in_menu'          => true,
					'show_in_nav_menus'     => true,
					'exclude_from_search'   => false,
					'capability_type'       => 'post',
					'map_meta_cap'          => true,
					'hierarchical'          => false,
					'rewrite'               => array( 'slug' => 'area', 'with_front' => false ),
					'query_var'             => true,
					'supports'              => array( 'title', 'editor', 'thumbnail' ),
					'show_in_graphql'       => false,
					'taxonomies'            => array( 'department', 'key' ),
					'menu_icon'             => 'dashicons-book-alt',
				);
			}

			/**
			 * Gather the labels for the post type
			 *
			 * @access protected
			 * @return array the array of labels
			 * @since  0.1
			 */
			protected function get_labels(): array {
				return array(
					'name'          => __( 'Areas of Study', 'umw/outreach' ),
					'singular_name' => __( 'Area of Study', 'umw/outreach' ),
				);
			}
		}
	}
}