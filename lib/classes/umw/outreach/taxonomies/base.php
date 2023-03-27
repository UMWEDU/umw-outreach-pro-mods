<?php

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		die( 'You do not have permission to access this file directly.' );
	}
}

namespace UMW\Outreach\Taxonomies {
	if ( ! class_exists( 'Base' ) ) {
		abstract class Base {
			abstract protected function __construct();

			/**
			 * Returns the handle for the taxonomy
			 *
			 * @access protected
			 * @return string
			 * @since  0.1
			 */
			abstract protected function get_handle(): string;

			/**
			 * Returns the array of labels for this taxonomy
			 *
			 * @access protected
			 * @return array the array of labels
			 * @since  0.1
			 */
			abstract protected function get_labels(): array;

			/**
			 * Returns the array of arguments for the taxonomy
			 *
			 * @access protected
			 * @return array the array of arguments
			 * @since  0.1
			 */
			abstract protected function get_args(): array;

			/**
			 * Returns the array of post types to associate with this taxonomy
			 *
			 * @access protected
			 * @return array the array of post type handles
			 * @since  0.1
			 */

			abstract protected function get_post_types(): array;

			/**
			 * Register the new taxonomy
			 *
			 * @access public
			 * @return void
			 * @since  0.1
			 */
			public function register_taxonomy() {
				register_taxonomy( $this->get_handle(), $this->get_post_types(), $this->get_args() );
			}
		}
	}
}