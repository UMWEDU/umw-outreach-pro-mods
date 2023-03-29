<?php
/**
 * Implements the Areas of Study Navigation widget for use on individual Areas of Study
 */

namespace UMW\Outreach\Widgets;

class Study_Navigation extends \WP_Widget {
	static $widget_id = 0;
	static $version = '0.1';

	/**
	 * Study_Navigation constructor.
	 *
	 * @param string $id_base the base used for HTML IDs
	 * @param string $name the full-text name of the widget
	 * @param array $widget_options
	 * @param array $control_options
	 *
	 * @access public
	 * @since  0.1
	 */
	function __construct( $id_base = '', $name = '', array $widget_options = array(), array $control_options = array() ) {
		$id_base        = 'umw-study-navigation';
		$name           = __( 'UMW Areas of Study Navigation', 'umw-outreach-mods' );
		$widget_options = array(
			'description' => __( 'Outputs a navigation menu for individual Areas of Study', 'umw-outreach-mods' ),
		);

		parent::__construct( $id_base, $name, $widget_options, $control_options );
	}

	/**
	 * Generate the form that allows control of the widget
	 *
	 * @param array $instance the existing settings for this instance of the widget
	 *
	 * @access public
	 * @return string
	 * @since  0.1
	 */
	public function form( $instance ): string {
		$instance = wp_parse_args( $instance, array(
			'title' => '',
			'menu'  => 0,
		) );

		$title = esc_attr( $instance['title'] );
		$menu  = intval( $instance['menu'] );

		$textfield   = '<p><label for="%1$s">%2$s</label><input type="%5$s" name="%3$s" id="%1$s" value="%4$s" class="widefat"/></p>';
		$selectfield = '<p><label for="%1$s">%2$s</label><select name="%3$s" id="%1$s" class="widefat" %5$s>%6$s</select></p>';

		printf( $textfield, $this->get_field_id( 'title' ), __( 'Title', 'umw-outreach-mods' ), $this->get_field_name( 'title' ), $title, 'text' );
		printf( $selectfield, $this->get_field_id( 'menu' ), __( 'Menu', 'umw-outreach-mods' ), $this->get_field_name( 'menu' ), '', '', $this->get_menu_options( $instance['menu'] ) );

		return 'form';
	}

	/**
	 * Save the settings for an individual widget
	 *
	 * @param array $new_instance the new settings for the widget
	 * @param array $old_instance the existing settings for the widget
	 *
	 * @access public
	 * @return array
	 * @since  0.1
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = wp_parse_args( $new_instance, array(
			'title' => '',
			'menu'  => 0,
		) );

		$instance['title'] = esc_attr( $new_instance['title'] );
		$instance['menu']  = intval( $new_instance['menu'] );

		return $instance;
	}

	/**
	 * Output the widget itself
	 *
	 * @param array $args the general arguments for the widget
	 * @param array $instance the specific settings for this instance of the widget
	 *
	 * @access public
	 * @return void
	 * @since  0.1
	 */
	function widget( $args, $instance ) {
		/* Only show this widget on individual Areas of Study items */
		if ( ! is_singular( 'areas' ) ) {
			return;
		}

		if ( ! array_key_exists( 'menu', $instance ) || empty( $instance['menu'] ) ) {
			return;
		}

		$instance              = wp_parse_args( $instance, array(
			'title' => '',
			'menu'  => 0,
		) );
		$instance['widget_id'] = $args['id'];
		self::$widget_id       = $instance['widget_id'];

		$args = wp_parse_args( $args, array(
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '',
			'after_title'   => '',
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
	 * Retrieve and build the list of navigation menus to choose from in a select field
	 *
	 * @param int $menu the currently selected navigation menu ID
	 *
	 * @access private
	 * @return string the HTML list of option elements
	 * @since  0.1
	 */
	private function get_menu_options( int $menu = 0 ): string {
		$list = $this->get_menus();

		$format = '<option value="%1$d" %3$s>%2$s</option>';

		$rt = sprintf( $format, 0, __( '-- Select a Menu --', 'umw-outreach-mods' ), '' );

		foreach ( $list as $key => $value ) {
			$rt .= sprintf( $format, $key, $value, selected( $menu, $key, false ) );
		}

		return $rt;
	}

	/**
	 * Retrieve an array of custom navigation menus
	 *
	 * @access private
	 * @return array the array of menus
	 * @since  0.1
	 */
	private function get_menus(): array {
		$menus = wp_get_nav_menus();
		$rt    = array();

		if ( is_wp_error( $menus ) ) {
			return array();
		}

		foreach ( $menus as $menu ) {
			$rt[ $menu->term_id ] = $menu->name;
		}

		return $rt;
	}

	/**
	 * Output the content of the widget
	 *
	 * @param array $instance the specific settings for this instance of the widget
	 *
	 * @access public
	 * @return void
	 * @since  0.1
	 */
	public function content( array $instance = array() ) {
		if ( empty( $instance['menu'] ) ) {
			return;
		}

		add_filter( 'wp_nav_menu_items', array( $this, 'add_menu_items' ), 10, 2 );
		wp_nav_menu( array( 'menu' => $instance['menu'] ) );
		remove_filter( 'wp_nav_menu_items', array( $this, 'add_menu_items' ), 10, 2 );
	}

	/**
	 * Add the "Department" and "Course List" links to the nav menu
	 *
	 * @param string $items The HTML list content for the menu items.
	 * @param \stdClass $args An object containing wp_nav_menu() arguments.
	 *
	 * @access public
	 * @return string the updated menu items
	 * @since  0.1
	 */
	public function add_menu_items( string $items, \stdClass $args ): string {
		$format = '<li class="%3$s"><a href="%1$s">%2$s</a></li>';

		$extra = array(
			'department' => array(
				'url'   => get_field( 'department', get_the_ID(), false ),
				'label' => __( 'Department Website', 'umw-outreach-mods' ),
			),
			'courses'    => array(
				'url'   => get_field( 'courses', get_the_ID(), false ),
				'label' => __( 'Course Listings', 'umw-outreach-mods' ),
			),
		);

		$rt = '';

		foreach ( $extra as $item ) {
			$rt .= sprintf( $format, $item['url'], $item['label'], 'menu-item menu-item-type-custom menu-item-object-custom' );
		}

		return $rt . $items;
	}
}