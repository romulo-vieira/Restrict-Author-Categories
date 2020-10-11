<?php
/**
 * All DB globals
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $wpdb;
define( 'RESAUTCAT_DB_USERS', ( isset($wpdb) ?  $wpdb->prefix . "resautcat_users" : null ) );
define( 'RESAUTCAT_DB_USERSTERMS', ( isset($wpdb) ?  $wpdb->prefix . "resautcat_usersterms" : null ) );
define( 'RESAUTCAT_DB_CHARSET_COLLATE',
    (isset($wpdb) && !empty($wpdb->charset)
    ?
        (!empty($wpdb->collate)
        ?
            "DEFAULT CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}"
        :
            "DEFAULT CHARACTER SET {$wpdb->charset} ")
    :
        "")
);