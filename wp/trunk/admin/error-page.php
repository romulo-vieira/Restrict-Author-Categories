<?php
/**
 * Error page
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

wp_enqueue_style( 'style', RESAUTCAT_PLUGIN_URL . '/admin/admin-page.css' );

?>

<h1><?php esc_html_e( 'Error on Plugin installation.', 'resautcat_txt_domain' ) ?></h1>
<h2><?php esc_html_e( 'Please reinstall the plugin.', 'resautcat_txt_domain' ) ?></h2>

<?php

