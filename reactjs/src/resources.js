/**
 * All resource functions needed to the app.
 *
*/

var wp_localhost_domain = 'http://wptest.local';

// Ajax Functions (get and post)
export var resautcatAjax = ( function(){

    var noop = function(){};

    var get = function( url, params, callback ){
        callback = ( 'function' === typeof callback ) ? callback : noop;
        ajax( "GET", url, params, callback );
    };

    var post = function( url, params, callback ){
        callback = ( 'function' === typeof callback ) ? callback : noop;
        ajax( "POST", url, params, callback );
    };

    var ajax = function( method, url, params, callback ){
        /* Create XMLHttpRequest object and set variables */
        var xhr = new XMLHttpRequest(),
        target = url,
        args = params,
        valid_methods = ["GET", "POST"];
        method = -1 !== valid_methods.indexOf( method ) ? method : "GET";
        /* Set request method and target URL */
        xhr.open( method, target + ( "GET" === method ? '?' + args : '' ), true );
        /* Set request headers */
        if ( "POST" === method ) {
            xhr.setRequestHeader( "Content-type", "application/x-www-form-urlencoded" );
        }
        xhr.setRequestHeader( "X-Requested-With","XMLHttpRequest" );
        /* Hook into onreadystatechange */
        xhr.onreadystatechange = function() {
            if ( 4 === xhr.readyState && 200 <= xhr.status && 300 > xhr.status ) {
                if ( 'function' === typeof callback ) {
                    callback.call( undefined, JSON.parse(xhr.response) );
                }
            }
        };
        /* Send request */
        xhr.send( ( "POST" === method ? args : null ) );
    };

    return {
        get: get,
        post: post
    };
} )();

/**
 * Host domain name
 * Change "http://wptest.local" to your Wordpress local host domain.
 * Remember to remove CORS restrictions on WP local host while you are at development, so ReactJS Live Server
 * can access WP REST Routes.
 */
export const getHostDomain = () => process.env.NODE_ENV === 'development' ? wp_localhost_domain : ''; // on production, it returns an empty string, since the plugin will run on the WP host domain


/**
 * get the users to be selected
 * @param {int} quant 
 * @param {int} offset 
 * @param {function} callback 
 */
export const getUsersData = (quant, offset, callback) => {
    const url =  getHostDomain() + '/wp-json/resautcat_api_admin/users/' + quant + '/' + offset;
    const params = 'search=';
    resautcatAjax.get(url, params, function(response){
        if(typeof response === "object" && response.result === 'success'){
            callback(response);
        }else{
            console.log('Error on GET users on DB');
        }
    });
}


/**
 * Activate/Deactivate User on DB
 */
export const setUserDB = (userId, isActive) => {    
    setUserDBHandler(null, userId, isActive);
}
export const waitingResponseUsers = {}; // stores all the requests that are running
const modifiedIsActive = {}; // isActive value for the next request
const setUserUrl = (userId, isActive) => { // Route to the request
    return getHostDomain() + '/wp-json/resautcat_api_admin/set_user/' + userId + '/' + isActive + '/';
}

/**
 * Fires the Ajax Get request to set a User State at the plugin's DB table.
 * It's made so it can only fire a request when the previous returns.
 * @param {object} response 
 * @param {int} userId 
 * @param {int} isActive 
 */
const setUserDBHandler = (response, userId, isActive) => {
    if(response !== null){ // a response to the current request came
        delete waitingResponseUsers[userId];

        if(response.result === 'success'){
            const dbIsActive = response.data['isActive'] === 'true' ? true : false;
            const dbUserId = parseInt(response.data['userId']);

            if(
                (dbIsActive === false && modifiedIsActive[dbUserId] === true) ||
                (dbIsActive === true && modifiedIsActive[dbUserId] === false) 
            ){
                // New request has a different value from the current, so fire it
                resautcatAjax.get( setUserUrl(dbUserId, modifiedIsActive[dbUserId]), '', setUserDBHandler );
                delete waitingResponseUsers[dbUserId];
                return;
            }

            // New request has the same value of the current, so do nothing
            delete waitingResponseUsers[dbUserId];
            return;
        }
        return;
    }

    // if there is a user request waiting response, set the new request while waiting the current to return
    if(waitingResponseUsers[userId]){
        modifiedIsActive[userId] = isActive;
        return;
    }

    // set a new request if there is no request running    
    resautcatAjax.get( setUserUrl(userId, isActive), '', setUserDBHandler );
    waitingResponseUsers[userId] = true;
    modifiedIsActive[userId] = isActive;
}


/**
 * Activate/Deactivate Terms on DB
 * @param {int} termId 
 * @param {int} userId 
 * @param {int} canEditPost 
 */
export const setTermDB = (termId, userId, canEditPost) => {    
    setTermDBHandler(null, termId, userId, canEditPost);
}
export const waitingResponseTerms = {}; // stores all the requests that are running
const modified_caneditpost = {}; // canEditPost value for the next request
function set_term_url(term_id, current_user_id, caneditpost){ // Route to the request
    return getHostDomain() + '/wp-json/resautcat_api_admin/set_term/' + term_id + '/' + current_user_id + '/' + caneditpost + '/'; 
}

/**
 * Fires the Ajax Get request to set a Category Term state at the plugin's DB table.
 * It's made so it can only fire a request when the previous returns.
 * @param {object} response 
 * @param {int} tid 
 * @param {int} cuid 
 * @param {int} caneditpost 
 */
function setTermDBHandler(response, tid, cuid, caneditpost){ // tid = term_id | cuid = current_user_id
    if(response !== null){ // a response to the current request came
        delete waitingResponseTerms['u'+cuid+'t'+tid];

        if(response.result === 'success'){
            const dbcuid = parseInt(response.data['userid']);
            const dbtid = parseInt(response.data['termid']);
            const dbcaneditpost = response.data['caneditpost'] === 'true' ? true : false;
            const set_term_uid = 'u' + dbcuid + 't' + dbtid;

            if(
                (dbcaneditpost === false && modified_caneditpost[set_term_uid] === true) ||
                (dbcaneditpost === true && modified_caneditpost[set_term_uid] === false)
            ){
                // New request has a different value from the current, so fire it
                resautcatAjax.get( set_term_url(dbtid, dbcuid, modified_caneditpost[set_term_uid]), '', setTermDBHandler );
                delete waitingResponseTerms[set_term_uid];
                return;
            }

            // New request has the same value of the current, so do nothing
            delete waitingResponseTerms[set_term_uid];
            return;
        }
        return;
    }

    const set_term_uid = 'u' + cuid + 't' + tid;

    if(waitingResponseTerms[set_term_uid]){ // dont set a new request while the current is running
        modified_caneditpost[set_term_uid] = caneditpost;
        return;
    }
    
    // set a new request if there is no request running
    resautcatAjax.get( set_term_url(tid, cuid, caneditpost), '', setTermDBHandler );
    waitingResponseTerms[set_term_uid] = true;
    modified_caneditpost[set_term_uid] = caneditpost;
}


/**
 * Search Categories on DB
 */
let currentUserId = null;
const getCategoriesUrl = (userId, quant, offset, parent) => {
    return getHostDomain() + '/wp-json/resautcat_api_admin/categories/' + userId + '/' + quant + '/' + offset + '/' + parent; 
}

/**
 * Get Terms Data for Subcategories Categories
 * @param {object} queryData 
 */
export const getTermsData = (queryData) => {
    currentUserId = queryData.userId;

    const url =  getCategoriesUrl(queryData.userId, queryData.quant, queryData.offset, queryData.parent); 
    const params = 'search=' + queryData.search;

    resautcatAjax.get(url, params, function(response){

        if(typeof response === "object" && response.result === 'success'){

            if( currentUserId === parseInt(response.userId) ){
                queryData.callback(response);
            }
        }else{
            console.log('Error on GET terms on DB');
        }
    });
}

let primaryCallback = null;
let primaryQueryData = {};
let waitingPrimaryResponse = false; // stores all the requests that are running

/**
 * Get Terms Data for Primary Categories (the ones with no parents))
 * @param {object} queryData 
 */
export const getTermsDataPrimary = (queryData) => {
    primaryQueryData = queryData;
    currentUserId = primaryQueryData.userId;
    primaryCallback = primaryQueryData.callback;

    getCategoriesDBHandler(null, primaryQueryData);
}
const getCategoriesDBHandler = (response, queryData) => {
    if(response !== null){ // a response to the current request came
        waitingPrimaryResponse = false;
        
        if(typeof response === "object" && response.result === 'success'){

            if(
                typeof response.userId === "undefined" ||
                isNaN(parseInt(response.userId)) ||
                parseInt(response.userId) <= 0 ||
                typeof currentUserId === "undefined" ||
                isNaN(parseInt(currentUserId)) ||
                parseInt(currentUserId) <= 0
            ){
                console.log('Error on GET terms on DB');
                return;
            }

            // New request has the same value of the current, so dont fire it
            if(currentUserId === parseInt(response.userId)){
                primaryCallback(response);
                return;
            }else{

                // New request has a different value of the current, so fire it
                resautcatAjax.get(
                    getCategoriesUrl(primaryQueryData.userId, primaryQueryData.quant, primaryQueryData.offset, primaryQueryData.parent),
                    'search=' + primaryQueryData.search,
                    getCategoriesDBHandler
                );
                return;
            }
        }

        console.log('Error on GET terms on DB');
        return;
    }

    if(waitingPrimaryResponse){ // dont set a new request while the current is running
        return;
    }
 
    // set a new request if there is no request running
    resautcatAjax.get(
        getCategoriesUrl(queryData.userId, queryData.quant, queryData.offset, queryData.parent),
        'search=' + queryData.search,
        getCategoriesDBHandler
    );
    waitingPrimaryResponse = true;
}