<?php
/**
 * Plugin Name: UMW Outreach Customizations
 * Description: Implements various UMW-specific tweaks to the Outreach Pro Genesis child theme
 * Version: 2.0
 * Author: cgrymala
 * License: GPL2
 */

namespace {
	/**
	 * Set up an autoloader to automatically pull in the appropriate taxonomy class definitions
	 *
	 * @param string $class_name the full name of the class being invoked
	 *
	 * @since 2018.1
	 * @return void
	 */
	spl_autoload_register( function ( $class_name ) {
		if ( ! stristr( $class_name, 'UMW\Outreach\\' ) ) {
			return;
		}

		$filename = plugin_dir_path( __FILE__ ) . 'lib/classes/' . strtolower( str_replace( array(
				'\\',
				'_'
			), array( '/', '-' ), $class_name ) ) . '.php';
		if ( ! file_exists( $filename ) ) {
			return;
		}

		include_once $filename;
	} );
}

namespace UMW\Outreach {
	function inst_umw_outreach_mods_obj() {
		global $umw_outreach_mods_obj, $blog_id;
		if ( defined( 'UMW_IS_ROOT' ) && is_numeric( UMW_IS_ROOT ) ) {
			if ( absint( UMW_IS_ROOT ) === absint( $blog_id ) ) {
				$umw_outreach_mods_obj = new Root;
			} else if ( 4 === absint( $blog_id ) ) {
				$umw_outreach_mods_obj = new Direc;
			} else if ( 5 === absint( $blog_id ) ) {
				$umw_outreach_mods_obj = new Study;
			} else if ( 30 === absint( $blog_id ) ) {
				$umw_outreach_mods_obj = new Residence;
			} else if ( 7 === absint( $blog_id ) ) {
				$umw_outreach_mods_obj = new News;
			} else {
				$umw_outreach_mods_obj = new Base;
			}
		} else {
			$umw_outreach_mods_obj = new Base;
		}
	}

	inst_umw_outreach_mods_obj();
}