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
	 * @return void
	 * @since 2018.1
	 */
	spl_autoload_register( function ( $class_name ) {
		if ( ! stristr( $class_name, 'UMW\Outreach\\' ) &&
		     ! stristr( $class_name, 'UMW\Common\\' ) &&
		     ! stristr( $class_name, 'League\HTMLToMarkdown\\' ) &&
			 ! stristr( $class_name, 'GravityWiz\Add_Ons\\' ) ) {
			return;
		}

		if ( stristr( $class_name, 'League\HTMLToMarkdown\\' ) ) {
			$filename = plugin_dir_path( __FILE__ ) . 'lib/classes/' . str_replace( array(
					'\\',
					'_'
				), array( '/', '-' ), $class_name ) . '.php';
		} else if ( stristr( $class_name, 'GravityWiz\Add_Ons\\' ) ) {
			$filename = plugin_dir_path( __FILE__ ) . 'lib/classes/' . str_replace( array(
					'\\',
					'_'
				), array( '/', '-' ), $class_name ) . '.php';
		} else {
			$filename = plugin_dir_path( __FILE__ ) . 'lib/classes/' . strtolower( str_replace( array(
					'\\',
					'_'
				), array( '/', '-' ), $class_name ) ) . '.php';
		}
		if ( ! file_exists( $filename ) ) {
			error_log( 'Attempted to autoload the ' . $class_name . ' class, but the file ' . $filename . ' was not found.' );

			return;
		}

		include_once $filename;
	} );
}

namespace UMW\Outreach {
	function inst_umw_outreach_mods_obj() {
		if ( defined( 'UMW_IS_ROOT' ) && is_numeric( UMW_IS_ROOT ) ) {
			$root = absint( UMW_IS_ROOT );
			if ( defined( 'UMW_EMPLOYEE_DIRECTORY' ) && is_numeric( UMW_EMPLOYEE_DIRECTORY ) ) {
				$direc = absint( UMW_EMPLOYEE_DIRECTORY );
			} else {
				$direc = 4;
			}
			if ( defined( 'UMW_ADVISORIES_SITE' ) && is_numeric( UMW_ADVISORIES_SITE ) ) {
				$advis = absint( UMW_ADVISORIES_SITE );
			} else {
				$advis = 27;
			}
			if ( defined( 'UMW_STUDY_SITE' ) && is_numeric( UMW_STUDY_SITE ) ) {
				$study = absint( UMW_STUDY_SITE );
			} else {
				$study = 5;
			}
			if ( defined( 'UMW_RESIDENCE_SITE' ) && is_numeric( UMW_RESIDENCE_SITE ) ) {
				$res = absint( UMW_RESIDENCE_SITE );
			} else {
				$res = 30;
			}
			if ( defined( 'UMW_NEWS_SITE' ) && is_numeric( UMW_NEWS_SITE ) ) {
				$news = absint( UMW_NEWS_SITE );
			} else {
				$news = 7;
			}
		} else {
			$root = false;
		}

		global $umw_outreach_mods_obj, $blog_id;
		if ( false !== $root ) {
			if ( $root === absint( $blog_id ) ) {
				$umw_outreach_mods_obj = new Root;
			} else if ( $direc === absint( $blog_id ) ) {
				$umw_outreach_mods_obj = new Direc;
			} else if ( $study === absint( $blog_id ) ) {
				$umw_outreach_mods_obj = new Study;
			} else if ( $res === absint( $blog_id ) ) {
				$umw_outreach_mods_obj = new Residence;
			} else if ( $news === absint( $blog_id ) ) {
				$umw_outreach_mods_obj = new News;
			} else {
				$umw_outreach_mods_obj = new Base;
			}
		} else {
			if ( defined( 'UMW_OUTREACH_ENABLE_SIDEBAR' ) ) {
				$umw_outreach_mods_obj = new Extra_Layouts;
			} else {
				$umw_outreach_mods_obj = new Base;
			}
		}
	}

	inst_umw_outreach_mods_obj();
}