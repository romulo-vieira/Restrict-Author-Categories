<?php
/**
 * All the functions that runs on users (non-admin users)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once RESAUTCAT_PLUGIN_DIR_PATH . "/database/db_functions.php"; // import resautcat_get_allowed_categories()

// global vars
$resautcat_current_categories = array(); // categories before the post is updated/saved
$resautcat_has_updated_post = false; // check if the post has been updated/saved
$resautcat_updated_post_id = 0; // set the id of the last updated post by the user


/**
 * Show only allowed Categories
 *
 * @param string $query
 * @param array $args
 * @return void
 */
function resautcat_limit_categories_for_specific_user( $query, $args ){
	
 	if( resautcat_check_is_admin() ){ // only exclude categories at admin pages

		$allowed_categories = resautcat_get_allowed_categories(get_current_user_id())[0];
		
		// if no categories allowed, show none
		if(empty($allowed_categories)){

			$query .= ' AND tt.taxonomy <> "category" ';

		// if has categories
		}else{

			// limit user only to allowed categories
			$query .= ' AND ( t.term_id IN (' . implode(",", $allowed_categories) . ') OR tt.taxonomy <> "category" ) '; // only exclude the ids that of the terms that has the 'category' taxonomy
		}
	}

	return $query;
}
add_filter( 'list_terms_exclusions', 'resautcat_limit_categories_for_specific_user', 10, 2 );


/**
 * Getting the post categories before the update
 * You need this to know if the system's default category will be wrongly added
 * The system's default category will be added even if it's not allowed to the user, so you have
 * to check if it has happened to delete the category after the update on the function below (resautcat_change_post_categories)
 *
 * @param int $post_id
 * @param Object $post_data
 * @return void
 */
function resautcat_get_categories_before_update($post_id, $post_data) {

	// First, check if the post type has 'category' taxonomy so it does not affects attachments and pages
	if(in_array( 'category', get_object_taxonomies( get_post_type($post_id) ) ) ){

		global $resautcat_current_categories;
		global $resautcat_has_updated_post;
		global $resautcat_updated_post_id;

		$allowed_categories = resautcat_get_allowed_categories(get_current_user_id())[0];

		// You can only edit a post if you have at least one allowed category set
		if(!empty($allowed_categories)){

			// To wp_get_post_categories() get any category (not only the allowed to the user), you need to remove the filter below
			remove_filter( 'list_terms_exclusions', 'resautcat_limit_categories_for_specific_user', 10, 2 );

			$post_categories = wp_get_post_categories($post_id);

			// Setting back the filter
			add_filter( 'list_terms_exclusions', 'resautcat_limit_categories_for_specific_user', 10, 2 );

			if(!empty($post_categories)){

				// Verifying if post has any of the allowed category
				$has_post_categories_inside_allowed_categories = false;
				foreach ($post_categories as $cat_key => $cat_value) {
					
					if( in_array($cat_value, $allowed_categories) ){
						
						$has_post_categories_inside_allowed_categories = true;
						break;
					}
				}

				// Blocks the user to edit the post does not has an allowed category
				if(!$has_post_categories_inside_allowed_categories){
					wp_die('You are not allowed to edit this post', 'Permission Error');
					exit;
				}
			}else{
				
				// If categories array is empty, the user only can edit it if he's the author
				$author_id = intval(get_post_field( 'post_author', $post_id ));
				if(intval(get_current_user_id()) !== $author_id){
					wp_die('You are not allowed to edit this post', 'Permission Error');
					exit;
				}
			}

			// Setting current posts categories (the ones that exists before the post is updated)
			$resautcat_has_updated_post = true;
			$resautcat_current_categories = $post_categories;
			$resautcat_updated_post_id = $post_id;

		}else{ // If the user does not have any allowed category

			wp_die('You have no categories enabled', 'Permission Error');
			exit;
		}
	}
}
add_action('pre_post_update', 'resautcat_get_categories_before_update', 10, 2);


/**
 * Resetting the categories after post categories are set at the DB
 * The post will be created with some categories. This function verifies if
 * any of the post categories are allowed to the user. If not, it adds the
 * user's default category to the existing ones. It also removes the system's
 * default category if it is automatically added.
 *
 * @param int $post_id
 * @param Object $post
 * @param Object $post_update
 * @return void
 */
function resautcat_change_post_categories($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids){

	global $resautcat_updated_post_id;

	// First, check if the post id is the same of that was set before the post update, and also check if the
	// post type has the 'category' taxonomy, so it does not affects 'attachment' and 'page' post types
	if(
		$resautcat_updated_post_id === $object_id &&
		$taxonomy === 'category'
	){

		global $resautcat_current_categories;
		global $resautcat_has_updated_post;

		$data_ar = resautcat_get_allowed_categories(get_current_user_id());
		$allowed_categories = $data_ar[0];
		$user_default_category = $data_ar[1];

		if(empty($allowed_categories)){
			wp_die('You have no categories enabled', 'Permission Error');
			exit;
		}

		if(!empty($allowed_categories) && $resautcat_has_updated_post && $resautcat_updated_post_id){

			// To wp_get_post_categories() get any category (not only the allowed to the user), you need to remove the filter below
			remove_filter( 'list_terms_exclusions', 'resautcat_limit_categories_for_specific_user', 10, 2 );

			$default_category = intval( get_option('default_category') ); // default system's category

			$post_categories = wp_get_post_categories($resautcat_updated_post_id); // post categories after the update/saving

			$new_post_categories = $post_categories; // new categories that will be replaced with the existing one

			$has_changed_data = false; // Verifies if there is any change to be made at the post categories

			// if default category is in the post, but it is not one of the alloweds and it was not before the post update/saving, remove it
			if(
				in_array($default_category, $post_categories) &&
				!in_array($default_category, $allowed_categories) &&
				!in_array($default_category, $resautcat_current_categories)
			){
				// remove it from new_post_categories
				array_splice(
					$new_post_categories,
					array_search($default_category, $new_post_categories),
					1
				);
				$has_changed_data = true;
			}
			
			// verifies if new categories has any of the allowed ones. If it hasn't, push the default user category
			$has_allowed_category = false;
			foreach ($new_post_categories as $key => $new_id) {
				if (in_array($new_id, $allowed_categories)){
					$has_allowed_category = true;
					break;
				}
			}

			if(!$has_allowed_category){
				array_push($new_post_categories, $user_default_category);
				$has_changed_data = true;
			}

			// Setting new posts if post categories has changed
			if($has_changed_data){
				wp_set_post_categories($resautcat_updated_post_id, $new_post_categories, false);
			}

			// Setting back the filter
			add_filter( 'list_terms_exclusions', 'resautcat_limit_categories_for_specific_user', 10, 2 );
		}
	}
}
add_action( 'set_object_terms', 'resautcat_change_post_categories', 10, 6);


/**
 * Blocking the user to list any post that does not have a allowed category on admin pages
 *
 * @param string $query
 * @return void
 */
function resautcat_exclude_posts_visibility_by_category($query) {
	
	if( resautcat_check_is_admin() ){ // only exclude posts at admin pages

		// Verifying if the post type has 'category' taxonomy (that prevent's filtering to happen at attachments and pages)
		if(
			property_exists( $query, 'query' ) &&
			array_key_exists( 'post_type', $query->query ) &&
			in_array( 'category', get_object_taxonomies( $query->query['post_type'] ) ) &&

			property_exists( $query, 'query_vars' ) &&
			array_key_exists( 'category__in', $query->query_vars ) // query_vars need to has the possibility to filter by 'category_in'
		){
			$allowed_categories = resautcat_get_allowed_categories(get_current_user_id())[0];
			$query->query_vars['category__in'] = array_merge( array(0), $allowed_categories );
		}
	}
}
add_action( 'pre_get_posts', 'resautcat_exclude_posts_visibility_by_category' );


/**
 * Verifies if the user can delete the post or send it to the trash and block these actions if not allowed
 *
 * @param int $post_id
 * @return void
 */
function resautcat_before_delete_post( $post_id ){

	// First, check if the post type has 'category' taxonomy so it does not affects attachments and pages
	if(in_array( 'category', get_object_taxonomies( get_post_type($post_id) ) ) ){

		$allowed_categories = resautcat_get_allowed_categories(get_current_user_id())[0];

		// To wp_get_post_categories() get any category (not only the allowed to the user), you need to remove the filter below
		remove_filter( 'list_terms_exclusions', 'resautcat_limit_categories_for_specific_user', 10, 2 );

		$post_categories = wp_get_post_categories($post_id);

		// Setting back the filter
		add_filter( 'list_terms_exclusions', 'resautcat_limit_categories_for_specific_user', 10, 2 );

		$has_allowed_category = false;

		// If user has access to any of the post categories
		foreach ($post_categories as $post_cat => $post_value) {
			
			if(in_array($post_value, $allowed_categories)){
				$has_allowed_category = true;
			}
		}

		// If categories array is empty, the user only can edit it if he's the author
		$author_id = intval(get_post_field( 'post_author', $post_id ));
		if(
			empty($post_categories) &&
			intval(get_current_user_id()) === $author_id
		){
			$has_allowed_category = true;
		}

		// if the user is not allowed to edit the post categories, block deleting
		if(!$has_allowed_category){

			wp_die('You are not allowed to delete this post', 'Permission Error');
			exit;
		}
	}
}
add_action( 'before_delete_post', 'resautcat_before_delete_post' );
add_action( 'wp_trash_post', 'resautcat_before_delete_post' );


/**
 * Verifies if the user is trying to delete or update a category and denies it
 *
 * @param int $term_id
 * @param string $taxonomy
 * @return void
 */
function resautcat_prevent_delete_or_update_category( $term_id, $taxonomy ){ // $term_id can be used if you want to change this function to permit the user to edit or delete a category
	
	if(
		$taxonomy == 'category'
	){

		/**
		 * Only admin users can delete or edit categories
		 */
		wp_die('You are not allowed to edit categories', 'Permission Error');
		exit;
	}
}

// Verifies if user can delete categories
add_action( 'pre_delete_term', 'resautcat_prevent_delete_or_update_category', 10, 2 );


/**
 * Verifies if user can edit categories
 *
 * @param array $data
 * @param integer $term_id
 * @param string $taxonomy
 * @param array $args
 * @return object
 */
function resautcat_before_update_category($data, $term_id, $taxonomy, $args){

	resautcat_prevent_delete_or_update_category($term_id, $taxonomy);
	
	return $data; 
}
add_filter( 'wp_update_term_data', 'resautcat_before_update_category', 10, 4 );


/**
 * Verifies if user can create categories
 *
 * @return object
 */
function resautcat_before_create_category( $data, $taxonomy, $args ){
	
	if(
		$taxonomy == 'category'
	){

		/**
		 * Only admin users can create categories
		 */
		wp_die('You are not allowed to create categories', 'Permission Error');
		exit;
	}

	return $data;
}
add_filter('wp_insert_term_data', 'resautcat_before_create_category', 10, 3 );


/**
 * Pages the user cannot access
 *
 * @return void
 */
function resautcat_prevent_user_access(){

	if( resautcat_check_is_admin() ){ // only prevent acces of admin pages
		
		$screen = get_current_screen();

		// // Editing Categories Page
		// if( $screen->id == 'edit-category' ){

		// 	wp_die('You are not allowed to access this page', 'Permission Error');
		// 	exit;
		// }

		// Create/Editing Posts Page
		if( $screen->id == 'post' ){

			$allowed_categories = resautcat_get_allowed_categories(get_current_user_id())[0];

			// Getting the current post data
			global $post;

			// Verifies if the post type has the 'category' taxonomy
			if(
				isset( $post ) &&
				property_exists( $post, 'post_type' ) &&
				in_array( 'category', get_object_taxonomies( $post->post_type ) )
			){
				
				if(empty($allowed_categories)){

					wp_die('You have no categories enabled', 'Permission Error');
					exit;
				}

				$current_post_id = $post->ID;
				$can_edit_post = false;

				// To wp_get_post_categories() get any category (not only the allowed to the user), you need to remove the filter below
				remove_filter( 'list_terms_exclusions', 'resautcat_limit_categories_for_specific_user', 10, 2 );

				$post_categories = wp_get_post_categories($current_post_id);

				// Setting back the filter
				add_filter( 'list_terms_exclusions', 'resautcat_limit_categories_for_specific_user', 10, 2 );


				// If categories array is empty, the user only can edit it if he's the author
				$author_id = intval(get_post_field( 'post_author', $current_post_id ));
				if(
					empty($post_categories) &&
					intval(get_current_user_id()) === $author_id
				){
					
					$can_edit_post = true;
				}else{ // Verifying if the post has any of the allowed categories

					foreach ($post_categories as $cat_key => $cat_value) {
						
						if(in_array($cat_value, $allowed_categories)){

							$can_edit_post = true;
							break;
						}
					}
				}

				if(!$can_edit_post){

					wp_die('You are not allowed to edit this post', 'Permission Error');
					exit;
				}
			}
		}
	}
}
add_action('in_admin_header', 'resautcat_prevent_user_access');