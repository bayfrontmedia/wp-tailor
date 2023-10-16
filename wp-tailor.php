<?php

/**
 * Plugin Name:  WP-Tailor
 * Plugin URI:   https://github.com/bayfrontmedia/wp-tailor
 * Description:  Create a tailored WordPress environment by customizing its default functionality.
 * Version:      1.1.0
 * Author:       Bayfront Media
 * Author URI:   https://www.bayfrontmedia.com
 * License:      MIT License
 */

require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
require_once( 'src/Tailor.php' );

/*
 * Check ACF is installed and active.
 */

if ( ! function_exists( 'get_field' ) ) {
	add_action( 'admin_notices', [ 'Bayfront\Tailor\Tailor', 'showNoticeActivationFailed' ] );
	deactivate_plugins( plugin_basename( __FILE__ ), true );

	return;
}

add_action( 'acf/init', [ 'Bayfront\Tailor\Tailor', 'initialize' ] );
add_action( 'acf/include_fields', [ 'Bayfront\Tailor\Tailor', 'addFieldGroups' ] );

add_action( 'admin_bar_menu', [ 'Bayfront\Tailor\Tailor', 'customizeAdminBar' ], 9999 );
add_action( 'wp_dashboard_setup', [ 'Bayfront\Tailor\Tailor', 'customizeDashboard' ] );
add_action( 'wp_print_styles', [ 'Bayfront\Tailor\Tailor', 'removeBlockLibraryCss' ], 9999 );
add_action( 'wp_enqueue_scripts', [ 'Bayfront\Tailor\Tailor', 'removeGlobalStyles' ], 9999 );
add_action('wp_head', ['Bayfront\Tailor\Tailor', 'addHeadHtml']);
add_action('wp_footer', ['Bayfront\Tailor\Tailor', 'addFooterHtml']);