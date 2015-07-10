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
	
	define( 'WP_MANAGE_GLOBAL_HEADER_FOOTER', true );
	
	function inst_umw_outreach_mods_obj() {
		global $umw_outreach_mods_obj, $blog_id;
		if ( defined( 'WP_MANAGE_GLOBAL_HEADER_FOOTER' ) && WP_MANAGE_GLOBAL_HEADER_FOOTER && 1 === absint( $blog_id ) ) {
			$umw_outreach_mods_obj = new UMW_Outreach_Mods;
		} else {
			$umw_outreach_mods_obj = new UMW_Outreach_Mods_Sub;
		}
	}
	inst_umw_outreach_mods_obj();
}