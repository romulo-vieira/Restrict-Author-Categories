<?php
/**
 * All the functions that runs on users (non-admin users)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once RESAUTCAT_PLUGIN_DIR_PATH . "/database/db_globals.php";


/**
 * Verifies if user is activated on the plugin's admin page
 *
 * @param int $user_id
 * @return bool
 */
function resautcat_check_user_is_active($user_id){

	// Verifying if user is active (active = selected user on the admin page)
	global $wpdb;
	$wpdb->query(
		'SELECT is_active AS isActive ' .
		'FROM ' . RESAUTCAT_DB_USERS . ' ' .
		'WHERE user_id = ' . $user_id . ' ' .
		'AND is_active = 1;'
	);

	if(count($wpdb->last_result) > 0 && intval($wpdb->last_result[0]->isActive) === 1){
		return true;
	}

	return false;
}


/**
 * Returns an array with user's allowed categories and user's default category
 *
 * @return array
 */
function resautcat_get_allowed_categories($user_id){

	$allowed_ids = array();
	$user_default_category = null;

	global $wpdb;
	$wpdb->query(
		'SELECT resacut.term_id AS termId, wpt.name ' .
		'FROM ' . RESAUTCAT_DB_USERSTERMS . ' AS resacut, ' . $wpdb->terms . ' AS wpt ' .
		'WHERE resacut.user_id = ' . $user_id . ' ' .
		'AND resacut.can_edit_post = 1 ' .
		'AND resacut.term_id = wpt.term_id ' .
		'ORDER BY wpt.name;'
	);

	foreach ($wpdb->last_result as $key => $row) {
		array_push( $allowed_ids, intval($row->termId) );
	}

	if(!empty($allowed_ids)){

		// Users can choose they own default category. this feature its still not done.
		// Meanwhile, the default category will be the first of the allowed categories
		$user_default_category = $allowed_ids[0];
	}

	return array($allowed_ids, $user_default_category);
}