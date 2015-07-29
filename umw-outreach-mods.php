<?php
/**
 * Plugin Name: UMW Outreach Customizations
 * Description: Implements various UMW-specific tweaks to the Outreach Pro Genesis child theme
 * Version: 0.1.31
 * Author: cgrymala
 * License: GPL2
 */
if ( ! class_exists( 'UMW_Outreach_Mods' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . '/classes/class-umw-outreach-mods.php' );
	
	function inst_umw_outreach_mods_obj() {
		global $umw_outreach_mods_obj, $blog_id;
		if ( defined( 'UMW_IS_ROOT' ) && is_numeric( UMW_IS_ROOT ) ) {
			if ( absint( UMW_IS_ROOT ) === absint( $blog_id ) ) {
				$umw_outreach_mods_obj = new UMW_Outreach_Mods;
			} else if ( 4 == absint( $blog_id ) ) {
				require_once( plugin_dir_path( __FILE__ ) . '/classes/class-umw-outreach-mods-directory.php' );
				$umw_outreach_mods_obj = new UMW_Outreach_Mods_Directory;
			} else if ( 5 == absint( $blog_id ) ) {
				require_once( plugin_dir_path( __FILE__ ) . '/classes/class-umw-outreach-mods-study.php' );
				$umw_outreach_mods_obj = new UMW_Outreach_Mods_Study;
			} else {
				$umw_outreach_mods_obj = new UMW_Outreach_Mods_Sub;
			}
		} else {
			$umw_outreach_mods_obj = new UMW_Outreach_Mods_Sub;
		}
	}
	inst_umw_outreach_mods_obj();
}