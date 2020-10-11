<?php
/*
	Plugin Name: Restrict Author Categories
	Plugin URI: https://github.com/romulo-vieira/Restrict-Author-Categories
	Description: Restrict on which categories a user can post. Just that! It also prevents the users from creating/editing/deleting categories. Works with block editor and classic editor.
	Version: 1.1
	Author: romulovs
	Author URI: https://github.com/romulo-vieira
	License: GPLv2

	'Restrict Author Categories' is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 2 of the License, or
	any later version.
	
	'Restrict Author Categories' is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define('RESAUTCAT_USER_ADMIN_ROLE', 'administrator');

define('RESAUTCAT_PLUGIN_URL', plugins_url('/restrict-author-categories') );

define('RESAUTCAT_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );

// First plugin hook ever to be executed (after activation hook)
add_action( 'plugins_loaded', 'resautcat_plugin_load', 10 );
function resautcat_plugin_load(){
	
	// Loading Plugin's Admin page
	if(is_user_logged_in() && current_user_can( RESAUTCAT_USER_ADMIN_ROLE )){

		add_action('admin_menu', 'resautcat_admin_menu');
		wp_register_style('resautcat_menulayout', RESAUTCAT_PLUGIN_URL . '/admin/menu-layout.css');
		wp_enqueue_style('resautcat_menulayout');
	}

	// Loading '/content/functions.php'
	resautcat_load_functions();
}

function resautcat_admin_menu(){
	add_menu_page('Restrict Author Categories', 'Restrict Author Categories', RESAUTCAT_USER_ADMIN_ROLE, 'resautcat-admin-page', 'resautcat_show_admin_page', RESAUTCAT_PLUGIN_URL . '/content/logo.png');
}

function resautcat_show_admin_page(){

	if(get_option('resautcat_plugin_state') == 'ready')
		require_once RESAUTCAT_PLUGIN_DIR_PATH . '/admin/admin-page.php';
	else
		require_once RESAUTCAT_PLUGIN_DIR_PATH . '/admin/error-page.php';
}

function resautcat_load_functions(){

	if(get_option('resautcat_plugin_state') == 'ready'){

		// Plugin functions can only affects non admin users
		if( is_user_logged_in() && !current_user_can( RESAUTCAT_USER_ADMIN_ROLE ) ){

			require_once RESAUTCAT_PLUGIN_DIR_PATH . "/database/db_functions.php"; // import resautcat_check_user_is_active()

			// Load functions.php only if user is active
			if( resautcat_check_user_is_active(get_current_user_id()) ){
				
				require_once RESAUTCAT_PLUGIN_DIR_PATH . '/content/functions.php';
			}
		}
	}
}

/**
 * Verifies if current page is a backend wp page
 *
 * @return bool
 */
function resautcat_check_is_admin(){
	if(
		( is_admin() ||
		( !is_page() && !is_single() && !is_front_page() && !is_home() && !is_archive() ) ) // block editor does not recognize is_admin() because it runs on REST API, so you've got the check if it's not the other pages
	){
		return true;
	}

	return false;
}


/**
 * Plugin Activation and Exclusion
 */
require_once RESAUTCAT_PLUGIN_DIR_PATH . "/database/db_activate_uninstall.php"; // loading 'resautcat_activate_plugin()' and 'resautcat_uninstall_plugin()' to be used below
register_activation_hook( __FILE__, 'resautcat_activate_plugin' ); // register_activation_hook() and register_uninstall_hook() needs to be at this file (restrict-author-categories.php)
register_uninstall_hook( __FILE__, 'resautcat_uninstall_plugin' );


/**
 * Setting the routes
 */
require_once RESAUTCAT_PLUGIN_DIR_PATH . "/database/db_rest_api.php";