<?php
/**
 * Routes and DB functions
 */
if ( ! defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly.

require_once RESAUTCAT_PLUGIN_DIR_PATH . "/database/db_globals.php";


/**
 * Registers REST endpoints.
 */
function resautcat_register_routes() {

    if(
        !get_option('resautcat_plugin_state') == 'ready' ||
        !is_user_logged_in() ||
        !current_user_can( RESAUTCAT_USER_ADMIN_ROLE )
    ){
        return;
    }

    $namespace = 'resautcat_api_admin';

    register_rest_route($namespace, '/categories/(?P<userid>\d+)/(?P<quant>\d+)/(?P<offset>\d+)/(?P<parent>\d+)/', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'resautcat_api_get_categories',
        'permission_callback' => '__return_true',
        'args' => [
            'userid',
            'quant',
            'offset',
            'parent'
        ],
    ]);

    register_rest_route($namespace, '/users/(?P<quant>\d+)/(?P<offset>\d+)/', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'resautcat_api_get_users',
        'permission_callback' => '__return_true',
        'args' => [
            'quant',
            'offset'
        ],
    ]);

    register_rest_route($namespace, '/set_user/(?P<userid>\d+)/(?P<userset>(true|false))/', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'resautcat_api_set_user',
        'permission_callback' => '__return_true',
        'args' => [
            'userid',
            'userset'
        ],
    ]);

    register_rest_route($namespace, '/set_term/(?P<termid>\d+)/(?P<userid>\d+)/(?P<caneditpost>(true|false))/', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'resautcat_api_set_term',
        'permission_callback' => '__return_true',
        'args' => [
            'termid',
            'userid',
            'caneditpost'
        ],
    ]);
}
add_action('rest_api_init', 'resautcat_register_routes');

/**
 * 
 * Verify is query params are valid. Verifies only query names, not values
 *
 * @param array $query_params
 * @param array $valid_params
 * @return bool
 */
function resautcat_check_valid_params($query_params, $valid_params){

    foreach ($query_params as $param_key => $param_value) {
        
        if( !in_array($param_key, $valid_params) )
            return false;
    }

    return true;
}

/**
 * Returns success (code 200)
 *
 * @param object $data
 * @param string $message
 * @return object
 */
function resautcat_return_success($data, $otherData = array(), $message = ''){
    $resautcat_response = array(
        'result' => 'success',
        'message' => $message,
        'data' => $data
    );
    return new WP_REST_Response(array_merge($resautcat_response, $otherData), 200);
}

/**
 * Returns error (code 500)
 *
 * @param string $message
 * @return object
 */
function resautcat_return_fail($message = ''){
    $resautcat_response = array(
        'result' => 'fail',
        'message' => $message
    );
    return new WP_REST_Response($resautcat_response, 500);
}

/**
 * Validating if user exists and if is Admin
 *
 * @param int $userid
 * @return bool
 */
function resautcat_validate_user_id($userid){
    // Validating User Id
    if( !(intval($userid) >= 0) )
        return false;

    // Validating User Id (cannot be an Admin User)
    $user_data = get_userdata( $userid );
    if(is_object($user_data) && property_exists( $user_data, 'roles' )){
        if(in_array(RESAUTCAT_USER_ADMIN_ROLE, $user_data->roles)){
            return false;
        }
    }else{
        return false;
    }

    return true;
}

/**
 * Returns all categories
 *
 * @param object $request
 * @return object
 */
function resautcat_api_get_categories($request){
    $params = $request->get_params();

    // Validating GET parameters
    $valid_params = array('offset', 'search', 'quant', 'parent', 'userid');
    if( !resautcat_check_valid_params( $params, $valid_params ) )
        return resautcat_return_fail('Some Invalid parameter on Categories query');

    // Validating Parameters Values
    $offset = 0;
    if( array_key_exists('offset', $params) && intval($params['offset']) >= 0 )
        $offset = intval($params['offset']);

    if( !(array_key_exists('parent', $params) && intval($params['parent']) >= 0) )
        return resautcat_return_fail('Invalid Parent Number');

    $parent = intval($params['parent']);
    
    if(!resautcat_validate_user_id($params['userid']))
        return resautcat_return_fail('Invalid User ID');

    $userid = intval($params['userid']);

    $quant = 100;
    if( array_key_exists('quant', $params) && intval($params['quant']) > 0 )
        $quant = intval($params['quant']);

    $search = '';
    if( array_key_exists('search', $params) )
        $search = trim($params['search']);

    global $wpdb;

    // Querying Terms
    $wpdb->query(
        "SELECT DISTINCT " .
            "wpt.term_id AS termId, " .
            "wpt.name AS termName, " .
            "wptt.parent AS termParent, " .
            "resacut.can_see_post AS termCanSeePost, " .
            "resacut.can_edit_post AS termCanEditPost, " .
            "( SELECT COUNT(*) FROM " . $wpdb->term_taxonomy . " WHERE " . $wpdb->term_taxonomy . ".parent = termId ) AS termChildren " .
        "FROM " .
            $wpdb->term_taxonomy . " AS wptt, " .
            $wpdb->terms . " AS wpt " .
        "LEFT JOIN " .
            "( " .
                "SELECT * FROM " . RESAUTCAT_DB_USERSTERMS . " WHERE " . RESAUTCAT_DB_USERSTERMS . ".user_id = " . $userid . " " .
            ") AS resacut " .
        "ON " .
            "wpt.term_id = resacut.term_id " .
        "WHERE " .
                "wpt.term_id = wptt.term_id " .
            "AND " .
                "wptt.taxonomy = 'category' " .
            "AND " .
                "wptt.parent = " . $parent . " " .
        "ORDER BY FIELD(termCanEditPost, 1)  DESC, " .
        "wpt.name ASC " .
        "LIMIT " . $offset . ", " . $quant . ";"
    );
    $terms = $wpdb->last_result;


    // Querying Terms Count(*)
    $wpdb->query(
        "SELECT DISTINCT " .
            "COUNT(*) AS totalTerms " .
        "FROM " .
            $wpdb->term_taxonomy . " AS wptt, " .
            $wpdb->terms . " AS wpt " .
        "LEFT JOIN " .
            "( " .
                "SELECT * FROM " . RESAUTCAT_DB_USERSTERMS . " WHERE " . RESAUTCAT_DB_USERSTERMS . ".user_id = " . $userid . " " .
            ") AS resacut " .
        "ON " .
            "wpt.term_id = resacut.term_id " .
        "WHERE " .
                "wpt.term_id = wptt.term_id " .
            "AND " .
                "wptt.taxonomy = 'category' " .
            "AND " .
                "wptt.parent = " . $parent . ";"
    );
    $totalTerms = $wpdb->last_result[0];

    return resautcat_return_success(
        $terms,
        array(
            'userId' => $userid,
            'quant' => $quant,
            'offset' => $offset,
            'parent' => $parent,
            'totalTerms' => $totalTerms->totalTerms
        )
    );
}

/**
 * Returns all non-admin users
 *
 * @param object $request
 * @return object
 */
function resautcat_api_get_users($request){
    $params = $request->get_params();
    
    // Validating GET parameters
    $valid_params = array('offset', 'search', 'quant');
    if( !resautcat_check_valid_params( $params, $valid_params ) )
        return resautcat_return_fail('Some Invalid parameter on Users query');

    // Validating Parameters Values
    $offset = 0;
    if( array_key_exists('offset', $params) && intval($params['offset']) >= 0 )
        $offset = intval($params['offset']);

    $quant = 100;
    if( array_key_exists('quant', $params) && intval($params['quant']) > 0 )
        $quant = intval($params['quant']);

    $search = '';
    if( array_key_exists('search', $params) )
        $search = trim($params['search']);


    global $wpdb;

    // Querying Users
    $wpdb->query(
        "SELECT DISTINCT " .
            "wpusrs.ID as userId, " .
            "wpusrs.display_name as userName, " .
            "resacusrs.is_active as isActive " .
        "FROM " .
            $wpdb->usermeta . " AS wpumt, " .
            $wpdb->users . " AS wpusrs " .
        "LEFT JOIN " .
            RESAUTCAT_DB_USERS . " AS resacusrs " .
        "ON " .
            "wpusrs.ID = resacusrs.user_id " .
        "WHERE " .
            "wpusrs.ID = wpumt.user_id " .
        "AND " .
            "wpumt.meta_key = 'wp_capabilities' " .
        "AND " .
            "wpumt.meta_value NOT LIKE '%administrator%' " .
        (
            $search
            ?
                "AND wpusrs.display_name LIKE '%" . $search . "%'"
            :
                ""
        ) . " " .
        "ORDER BY FIELD(isActive, 1) DESC, " .
        "userName ASC " .
        "LIMIT " . $offset . ", " . $quant . ";"
    );
    $users = $wpdb->last_result;

    // Querying Users COUNT(*)
    $wpdb->query(
        "SELECT DISTINCT " .
            "COUNT(*) AS totalUsers " .
        "FROM " .
            $wpdb->usermeta . " AS wpumt, " .
            $wpdb->users . " AS wpusrs " .
        "LEFT JOIN " .
            RESAUTCAT_DB_USERS . " AS resacusrs " .
        "ON " .
            "wpusrs.ID = resacusrs.user_id " .
        "WHERE " .
            "wpusrs.ID = wpumt.user_id " .
        "AND " .
            "wpumt.meta_key = 'wp_capabilities' " .
        "AND " .
            "wpumt.meta_value NOT LIKE '%administrator%' " .
        (
            $search
            ?
                "AND wpusrs.display_name LIKE '%" . $search . "%'"
            :
                ""
        ) . " " .
        ";"
    );
    $totalUsers = $wpdb->last_result[0];

    return resautcat_return_success(
        $users,
        array( 'totalUsers' => $totalUsers->totalUsers )
    );
}

/**
 * Set User Route
 *
 * @param object $request
 * @return json
 */
function resautcat_api_set_user($request){
    $params = $request->get_params();
    
    // Validating GET parameters
    $valid_params = array('userid','userset');
    if( !resautcat_check_valid_params( $params, $valid_params ) )
        return resautcat_return_fail('Some Invalid parameter on Set User query');


    // Validating Parameters Values
    if(!resautcat_validate_user_id($params['userid']))
        return resautcat_return_fail('Invalid User ID');

    $userid = intval($params['userid']);

    if($params['userset'] != 'true' && $params['userset'] != 'false' )
        return resautcat_return_fail('Invalid User Set var');

    $userset = $params['userset'];

    // Setting user
    if($set_user_result = resautcat_db_set_user($userid, $userset))
        return resautcat_return_success($set_user_result);
    else
        return resautcat_return_fail('Error on query execution');
}

/**
 * Updates plugin's User Table changing user is_active column. Create row if not exists
 *
 * @param int $userid
 * @param string $userset
 * @return bool
 */
function resautcat_db_set_user($userid, $userset){
    global $wpdb;

    $result_success = array(
        'userId' => $userid,
        'isActive' => $userset
    );

    $userset = $userset == 'true' ? '1'  : '0'; // changing string to number to match db data

    // Verifying if there is a register
    $affected_rows = $wpdb->query('SELECT * FROM `' . RESAUTCAT_DB_USERS . '` WHERE `user_id` = ' . $userid . ' LIMIT 1;');
    if($affected_rows === false || $affected_rows === null) // error on query
        return false;

    // no register found
    if($affected_rows === 0){
        if(!$wpdb->query('INSERT INTO `' . RESAUTCAT_DB_USERS . '` (`user_id`, `is_active`) VALUES (' . $userid . ',' . $userset . ');'))
            return false; // error on query
        else
            return $result_success;

    // has register
    }else{

        // if is_active is the same as $userset
        if($wpdb->last_result[0]->is_active == $userset){
            return $result_success;
        }else{
            if(!$wpdb->query('UPDATE `' . RESAUTCAT_DB_USERS . '` SET `is_active` = ' . $userset . ' WHERE (`user_id` = ' . $userid . ');'))
                return false; // error on query
            else
                return $result_success;
        }
    }

    return false;
}


/**
 * Set Term Route
 *
 * @param object $request
 * @return json
 */
function resautcat_api_set_term($request){
    $params = $request->get_params();
    
    // Validating GET parameters
    $valid_params = array('userid','termid','caneditpost');
    if( !resautcat_check_valid_params( $params, $valid_params ) )
        return resautcat_return_fail('Some Invalid parameter on Set User query');


    // Validating Parameters Values
    if(!resautcat_validate_user_id($params['userid']))
        return resautcat_return_fail('Invalid User ID');

    $userid = intval($params['userid']);

    $termid = intval($params['termid']);
    if(!is_integer($termid))
        return resautcat_return_fail('Invalid Term ID');
    
    $term_data = get_term( $termid );
    if( !(is_object($term_data) && property_exists( $term_data, 'term_id' )) )
        return resautcat_return_fail('Invalid Term ID');

    $caneditpost = $params['caneditpost'];
    if($caneditpost != 'true' && $caneditpost != 'false' )
        return resautcat_return_fail('Invalid "Can Edit Post" var');

    // Setting Term
    if($set_user_result = resautcat_db_set_term($userid, $termid, $caneditpost))
        return resautcat_return_success($set_user_result);
    else
        return resautcat_return_fail('Error on query execution');
}

/**
 * Updates plugin's UsersTerms Table changing can_edit_post column. Create row if not exists
 *
 * @param int $userid
 * @param string $userset
 * @return bool
 */
function resautcat_db_set_term($userid, $termid, $caneditpost){
    global $wpdb;

    $result_success = array(
        'userid' => $userid,
        'termid' => $termid,
        'caneditpost' => $caneditpost
    );

    $caneditpost = $caneditpost == 'true' ? '1'  : '0'; // changing string to number to match db data

    // Verifying if there is a register
    $affected_rows = $wpdb->query('SELECT * FROM `' . RESAUTCAT_DB_USERSTERMS . '` WHERE `user_id` = ' . $userid . ' AND `term_id` = ' . $termid . ' LIMIT 1;');
    if($affected_rows === false || $affected_rows === null) // error on query
        return false;

    // no register found
    if($affected_rows === 0){
        if(!$wpdb->query('INSERT INTO `' . RESAUTCAT_DB_USERSTERMS . '` (`user_id`, `term_id`, `can_edit_post`) VALUES (' . $userid . ',' . $termid . ',' . $caneditpost . ');')){
            return false; // error on query
        }else{
            return $result_success;
        }

    // has register
    }else{

        // if can_edit_post is the same as $caneditpost
        if($wpdb->last_result[0]->can_edit_post == $caneditpost){
            return $result_success;
        }else{
            if(!$wpdb->query('UPDATE `' . RESAUTCAT_DB_USERSTERMS . '` SET `can_edit_post` = ' . $caneditpost . ' WHERE (`user_id` = ' . $userid . ' AND `term_id` = '. $termid . ');'))
                return false; // error on query
            else
                return $result_success;
        }
    }

    return false;
}