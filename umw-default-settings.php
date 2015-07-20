<?php
if ( ! class_exists( 'UMW_Default_Settings' ) ) {
	class UMW_Default_Settings {
		function __construct() {
			add_action( 'after_setup_theme', array( $this, 'after_setup_theme' ) );
			add_action( 'plugins_loaded', array( $this, 'set_gforms_defaults' ) );
			add_action( 'plugins_loaded', array( $this, 'set_cas_maestro_defaults' ) );
			add_filter( 'option_blogdescription', array( $this, 'get_default_tagline' ), 99 );
			add_action( 'populate_options', array( $this, 'default_tagline' ) );
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
			$opt = get_option( 'wpCAS_settings', false );
			if ( false !== $opt )
				return;
			
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