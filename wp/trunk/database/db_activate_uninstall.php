<?php
/**
 * Activation and Exclusion hooks
 * This file CANNOT be required inside other hook, because the functions below will fire before any other hook
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once RESAUTCAT_PLUGIN_DIR_PATH . "/database/db_globals.php";


/**
 * Activate Plugin (it runs everytime the plugin is activated)
 */
function resautcat_activate_plugin(){

    $sql = "
    CREATE TABLE " . RESAUTCAT_DB_USERS . " (
        user_id bigint(20) NOT NULL,
        is_active TINYINT(1) NOT NULL,
        PRIMARY KEY (user_id)
    ) " . RESAUTCAT_DB_CHARSET_COLLATE . " ENGINE=InnoDB;
    CREATE TABLE " . RESAUTCAT_DB_USERSTERMS . " (
        user_id bigint(20) NOT NULL,
        term_id bigint(20) NOT NULL,
        can_see_post TINYINT(1) NOT NULL DEFAULT 1,
        can_edit_post TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (user_id, term_id)
    ) " . RESAUTCAT_DB_CHARSET_COLLATE . " ENGINE=InnoDB;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql); // dbDelta() prevents table recreation

    global $wpdb;
    if(
        $wpdb->get_var("SHOW TABLES LIKE '" . RESAUTCAT_DB_USERS . "'") == RESAUTCAT_DB_USERS &&
        $wpdb->get_var("SHOW TABLES LIKE '" . RESAUTCAT_DB_USERSTERMS . "'") == RESAUTCAT_DB_USERSTERMS
    ){
        if(!get_option('resautcat_plugin_state'))
            add_option('resautcat_plugin_state', 'ready');
        else
            update_option('resautcat_plugin_state', 'ready');

        if(!get_option('resautcat_plugin_version'))
            add_option('resautcat_plugin_version', '0.01');
        else
            update_option('resautcat_plugin_version', '0.01');
    }
}


/**
 * Uninstall Plugin
 *
 */
function resautcat_uninstall_plugin(){
    global $wpdb;

    $wpdb->query("DROP TABLE IF EXISTS " . RESAUTCAT_DB_USERS . ";");
    $wpdb->query("DROP TABLE IF EXISTS " . RESAUTCAT_DB_USERSTERMS . ";");

    if(get_option('resautcat_plugin_state'))
        delete_option('resautcat_plugin_state');

    if(get_option('resautcat_plugin_version'))
        delete_option('resautcat_plugin_version');
}