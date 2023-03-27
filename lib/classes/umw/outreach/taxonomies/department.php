<?php

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		die( 'You do not have permission to access this file directly.' );
	}
}

namespace UMW\Outreach\Taxonomies {
	if ( ! class_exists( 'Department' ) ) {
		class Department extends Base {
			/**
			 * @var Department $instance holds the single instance of this class
			 * @access private
			 */
			private static $instance;

			protected function __construct() {
				$this->register_taxonomy();
			}

			/**
			 * Returns the instance of this class.
			 *
			 * @access  public
			 * @return  Department
			 * @since   0.1
			 */
			public static function instance(): Department {
				if ( ! isset( self::$instance ) ) {
					$className      = __CLASS__;
					self::$instance = new $className;
				}

				return self::$instance;
			}

			/**
			 * Returns the handle for the taxonomy
			 *
			 * @access protected
			 * @return string
			 * @since  0.1
			 */
			protected function get_handle(): string {
				return 'department';
			}

			/**
			 * Returns the array of post types to associate with this taxonomy
			 *
			 * @access protected
			 * @return array the array of post type handles
			 * @since  0.1
			 */
			protected function get_post_types(): array {
				return array( 'areas' );
			}

			/**
			 * Returns the array of arguments for the taxonomy
			 *
			 * @access protected
			 * @return array the array of arguments
			 * @since  0.1
			 */
			protected function get_args(): array {
				return array(
					'label'                 => __( 'Departments', 'umw/outreach' ),
					'labels'                => $this->get_labels(),
					'public'                => true,
					'publicly_queryable'    => true,
					'hierarchical'          => true,
					'show_ui'               => true,
					'show_in_menu'          => true,
					'show_in_nav_menus'     => true,
					'query_var'             => true,
					'rewrite'               => array(
						'slug'         => 'creator',
						'with_front'   => false,
						'hierarchical' => true,
					),
					'show_admin_column'     => true,
					'show_in_rest'          => true,
					'show_tagcloud'         => false,
					'rest_base'             => 'creator',
					'rest_controller_class' => 'WP_REST_Terms_Controller',
					'show_in_quick_edit'    => true,
					'sort'                  => false,
					'show_in_graphql'       => false,
				);
			}

			/**
			 * Returns the array of labels for this taxonomy
			 *
			 * @access protected
			 * @return array the array of labels
			 * @since  0.1
			 */
			protected function get_labels(): array {
				return array(
					'name'          => __( 'Departments', 'umw/outreach' ),
					'singular_name' => __( 'Department', 'umw/outreach' ),
				);
			}
		}
	}
}