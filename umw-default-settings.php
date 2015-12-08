<?php
if ( ! class_exists( 'UMW_Default_Settings' ) ) {
	class UMW_Default_Settings {
		function __construct() {
			add_action( 'after_setup_theme', array( $this, 'after_setup_theme' ) );
			add_action( 'plugins_loaded', array( $this, 'set_gforms_defaults' ) );
			add_action( 'plugins_loaded', array( $this, 'set_cas_maestro_defaults' ) );
			add_action( 'plugins_loaded', array( $this, 'set_genesis_a11y_defaults' ) );
			add_action( 'plugins_loaded', array( $this, 'set_wpa11y_defaults' ) );
			add_filter( 'option_blogdescription', array( $this, 'get_default_tagline' ), 99 );
			add_action( 'populate_options', array( $this, 'default_tagline' ) );
			add_action( 'update_option_genwpacc-settings', array( $this, 'clear_genesis_a11y_sync_status' ) );
			add_action( 'update_option_wpCAS_settings', array( $this, 'clear_cas_maestro_sync_status' ) );
			add_action( 'update_option', array( $this, 'clear_wp_a11y_sync_status' ) );
		}
		
		function after_setup_theme() {
			/**
			 * Register an oEmbed for Storify
			 */
			wp_oembed_add_provider( 'http://storify.com/*', 'http://api.embed.ly/1/oembed?url=http://storify.com/' );
		}
		
		/**
		 * Set the license and API keys for Gravity Forms
		 */
		function set_gforms_defaults() {
			// Not needed anymore, since these can be defined as constants in wp-config
			return;
			
			if( false === get_option( 'rg_gforms_key', false ) )
				update_option( 'rg_gforms_key', 'c5f8b8573c7d0f5de0716a7b0440b181' );
			if( false === get_option( 'rg_gforms_captcha_public_key', false ) )
				update_option( 'rg_gforms_captcha_public_key', '6Le63cQSAAAAAColfQbTEq3dtYO_kKvg1jPjtpiw' );
			if( false === get_option( 'rg_gforms_captcha_private_key', false ) )
				update_option( 'rg_gforms_captcha_private_key', '6Le63cQSAAAAAAj4dj2K218IwzOLHWdAnKSdRWjQ' );
		}
		
		/**
		 * Set the default settings for CAS Maestro
		 */
		function set_cas_maestro_defaults() {
			if ( 1 == $GLOBALS['blog_id'] )
				return;
			
			$done = get_site_option( 'synced-cas-maestro-settings', array() );
			if ( is_array( $done ) && in_array( $GLOBALS['blog_id'], $done ) )
				return;
			
			$opts = get_blog_option( 1, 'wpCAS_settings', false );
			if ( false !== $opts ) {
				update_option( 'wpCAS_settings', $opts );
				$done[] = $GLOBALS['blog_id'];
				update_site_option( 'synced-cas-maestro-settings', $done );
				return;
			}
			
			$opts = array (
				'cas_menu_location' => 'settings',
				'new_user' => '1',
				'email_suffix' => '',
				'cas_version' => '2.0',
				'server_hostname' => 'auth.umw.edu',
				'server_port' => '443',
				'server_path' => '/cas',
				'e-mail_registration' => '1',
				'global_sender' => 'webmaster@umw.edu',
				'full_name' => 'UMW Webmaster',
				'welcome_mail' => 
				array (
					'send_user' => true,
					'send_global' => true,
					'subject' => 'Welcome to the UMW Website',
					'user_body' => 'Thank you for signing into the new %sitename% website for the first time. Your user account has been automatically created on the new site for you. You can sign in to the site at any time by entering your Banner username and password in the login screen.',
					'global_body' => 'A new user %realname% (%username%) has signed into the UMW website for the first time.',
				),
				'wait_mail' => 
				array (
					'send_user' => true,
					'send_global' => false,
					'subject' => '',
					'user_body' => '',
					'global_body' => '',
				),
				'ldap_protocol' => '3',
				'ldap_server' => '',
				'ldap_username_rdn' => '',
				'ldap_password' => '',
				'ldap_basedn' => '',
				'ldap_port' => NULL,
			);
			
			update_option( 'wpCAS_settings', $opts );
			$done[] = $GLOBALS['blog_id'];
			update_site_option( 'synced-cas-maestro-settings', $done );
		}
		
		function clear_cas_maestro_sync_status() {
			delete_site_option( 'synced-cas-maestro-settings' );
		}
		
		/**
		 * Set up default options for the Genesis Accessible plugin
		 * In this case, we want to set our options on the root site, 
		 * 		and not allow any other sub-sites to override those
		 * 		options.
		 */
		function set_genesis_a11y_defaults() {
			if ( 1 == $GLOBALS['blog_id'] ) {
				return;
			}
			
			$done = get_site_option( 'synced-genwpacc-settings', array() );
			if ( is_array( $done ) && in_array( $GLOBALS['blog_id'], $done ) )
				return;
			
			$opts = get_blog_option( 1, 'genwpacc-settings', false );
			if ( false !== $opts ) {
				update_option( 'genwpacc-settings', $opts );
				$done[] = $GLOBALS['blog_id'];
				update_site_option( 'synced-genwpacc-settings', $done );
				return;
			}
			
			$opts = array (
				'genwpacc_skiplinks' => 1,
				'genwpacc_skiplinks_css' => 1,
				'genwpacc_widget_headings' => 1,
				'genwpacc_404' => '1',
				'genwpacc_sitemap' => '1',
				'genwpacc_read_more' => 1,
				'genwpacc_tinymce' => 1,
				'genwpacc_screen_reader_text' => 0,
				'genwpacc_no_title_attr' => 0,
				'genwpacc_dropdown' => 0,
				'genwpacc_remove_genesis_widgets' => 0,
			);
			
			update_option( 'genwpacc-settings', $opts );
			$done[] = $GLOBALS['blog_id'];
			update_site_option( 'synced-genwpacc-settings', $done );
		}
		
		/**
		 * Clear out the fact that we've already synced Genesis A11y settings
		 */
		function clear_genesis_a11y_sync_status() {
			delete_site_option( 'synced-genwpacc-settings' );
		}
		
		/**
		 * Get a list of all of the options WPA11y Uses
		 */
		function get_wp_a11y_option_names() {
			return array(
				/* Title Attribute Settings */
				'rta_from_tag_clouds', 
				'rta_from_archive_links', 
				/* Add Skiplinks Settings */
				'asl_enable', 
				'asl_visible', 
				'asl_content', 
				'asl_navigation', 
				'asl_sitemap', 
				'asl_extra_target', 
				'asl_extra_text', 
				'asl_styles_focus', 
				'asl_styles_passive', 
				/* Miscellaneous Accessibility Settings */
				'wpa_lang', 
				'wpa_more', 
				'wpa_continue', 
				'wpa_insert_roles', 
				'wpa_complementary_container', 
				'wpa_labels', 
				'wpa_target', 
				'wpa_search', 
				'wpa_tabindex', 
				'wpa_underline', 
				'wpa_longdesc', 
				'wpa_admin_css', 
				'wpa_row_actions', 
				'wpa_image_titles', 
				'wpa_toolbar', 
				'wpa_toolbar_size', 
				'wpa_widget_toolbar', 
				'wpa_toolbar_gs', 
				'wpa_diagnostics', 
				'wpa_focus', 
				'wpa_focus_color', 
			);
		}
		
		/**
		 * Sync the WP A11y Settings from the main site
		 */
		function set_wpa11y_defaults() {
			if ( 1 == $GLOBALS['blog_id'] ) {
				return;
			}
			
			$done = get_site_option( 'synced-wpa11y-settings', array() );
			if ( is_array( $done ) && in_array( $GLOBALS['blog_id'], $done ) )
				return;
			
			$optnames = $this->get_wp_a11y_option_names();
			remove_action( 'update_option', array( $this, 'clear_wp_a11y_sync_status' ) );
			foreach ( $optnames as $optname ) {
				$opt = get_blog_option( 1, $optname, false );
				if ( false !== $opt ) {
					update_option( $optname, $opt );
				}
			}
			add_action( 'update_option', array( $this, 'clear_wp_a11y_sync_status' ) );

			$done[] = $GLOBALS['blog_id'];
			update_site_option( 'synced-wpa11y-settings', $done );
		}
		
		/**
		 * Clear out the fact that we've already synced the WPA11y settings
		 */
		function clear_wp_a11y_sync_status( $option ) {
			$optnames = $this->get_wp_a11y_option_names();
			if ( ! in_array( $option, $optnames ) )
				return;
			
			delete_site_option( 'synced-wpa11y-settings' );
		}
		
		/**
		 * Replace the default tagline with a branded one
		 */
		function get_default_tagline() {
			if ( empty( $tagline ) || ! stristr( $tagline, 'just another' ) )
				return $tagline;
			
			return __( 'Where great minds get to work' );
		}
		
		/**
		 * Populate the tagline with a branded one when a new site is created
		 */
		function default_tagline() {
			remove_filter( 'option_blogdescription', 'umw_get_default_tagline', 99 );
			$tagline = get_option( 'blogdescription', false );
			if ( false === $tagline )
				add_option( 'blogdescription', __( 'Where great minds get to work' ) );
			
			add_filter( 'option_blogdescription', 'umw_get_default_tagline', 99 );
		}
	}
}

global $umw_default_settings_obj;
$umw_default_settings_obj = new UMW_Default_Settings;