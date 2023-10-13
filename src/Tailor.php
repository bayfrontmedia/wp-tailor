<?php /** @noinspection PhpUndefinedFunctionInspection */

namespace Bayfront\Tailor;

use Bayfront\ArrayHelpers\Arr;
use WP_Admin_Bar;
use WP_Error;

class Tailor {

	/*
	 * |--------------------------------------------------------------------------
	 * | Notices
	 * |--------------------------------------------------------------------------
	 */

	/**
	 * Display an admin notice.
	 *
	 * @param string $type (error, warning, success, info)
	 * @param string $message
	 * @param bool $dismissible
	 */

	public static function displayAdminNotice( string $type, string $message, bool $dismissible = false ): void {

		if ( ! in_array( $type, [
			'error',
			'warning',
			'success',
			'info'
		] ) ) {
			$type = 'info'; // Default
		}

		$notice = '<div class="notice notice-' . $type;

		if ( true === $dismissible ) {

			$notice .= ' is-dismissible';

		}

		$notice .= '">' . $message . '</div>';

		echo $notice;

	}

	/**
	 * @return void
	 */
	public static function showNoticeActivationFailed(): void {
		self::displayAdminNotice( 'error', '<p>Unable to activate WP-Tailor: ACF plugin is not installed and/or activated.</p>' );
	}

	/*
	 * |--------------------------------------------------------------------------
	 * |Initialization
	 * |--------------------------------------------------------------------------
	 */

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public static function initialize(): void {

		// Ensure ACF is installed

		if ( ! function_exists( 'get_field' ) ) {
			return;
		}

		self::addOptionsPage();
		self::addPageExcerpts();
		self::removeAutoParagraphTags();
		self::disableEmojis();
		self::filterWpHead();
		self::disableXmlRpc();
		self::disableOembed();
		self::disableRestApi();
		self::updateLoginImageLink();
		self::updateLoginImage();

	}

	/**
	 * Add options page using ACF.
	 *
	 * @return void
	 */
	private static function addOptionsPage(): void {

		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return;
		}

		acf_add_options_page( [
			'page_title' => 'WP-Tailor Settings',
			'menu_title' => 'Tailor',
			'menu_slug'  => 'tailor-settings',
			'capability' => 'activate_plugins',
			'position'   => '80',
			'icon_url'   => 'dashicons-businessman',
			'redirect'   => true,
			'autoload'   => false
		] );

	}

	/**
	 * Add support for page excerpts.
	 *
	 * @return void
	 */
	private static function addPageExcerpts(): void {
		if ( get_field( 'tailor_enable_page_excerpts', 'option' ) ) {
			add_post_type_support( 'page', 'excerpt' );
		}
	}

	/**
	 * Remove automatic <p> tags in category descriptions and excerpts.
	 *
	 * @return void
	 */
	private static function removeAutoParagraphTags(): void {

		if ( get_field( 'tailor_remove_tags_category_description', 'option' ) ) {
			remove_filter( 'term_description', 'wpautop' );
		}

		if ( get_field( 'tailor_remove_tags_excerpts', 'option' ) ) {
			remove_filter( 'the_excerpt', 'wpautop' );
		}

	}

	/**
	 * Disable native WordPress emoji support.
	 *
	 * @return void
	 */
	private static function disableEmojis(): void {

		if ( get_field( 'tailor_disable_emojis', 'option' ) ) {

			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );

			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );

			add_filter( 'tiny_mce_plugins', [ 'Bayfront\Tailor\TailorFilters', 'filterTinyMceEmoji' ] );
			add_filter( 'wp_resource_hints', [ 'Bayfront\Tailor\TailorFilters', 'removeEmojiFromDnsPrefetch' ], 10, 2 );

		}

	}

	/**
	 * Remove elements from wp_head.
	 *
	 * @return void
	 */
	private static function filterWpHead(): void {

		$remove = get_field( 'tailor_remove_wp_head', 'option' );

		if ( is_array( $remove ) && ! empty( $remove ) ) {

			foreach ( $remove as $r ) {
				remove_action( 'wp_head', $r );
			}

		}

	}

	/**
	 * Disable XML-RPC.
	 *
	 * @return void
	 */
	private static function disableXmlRpc(): void {
		if ( get_field( 'tailor_disable_xmlrpc', 'option' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' ); // Disable xmlrpc
		}
	}

	/**
	 * Disable oEmbeds.
	 *
	 * @return void
	 */
	private static function disableOembed(): void {

		if ( get_field( 'tailor_disable_oembed', 'option' ) ) {

			// Remove REST API endpoint
			remove_action( 'rest_api_init', 'wp_oembed_register_route' );

			// Turn off oEmbed auto discovery.
			add_filter( 'embed_oembed_discover', '__return_false' );

			// Don't filter oEmbed results.
			remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result' );

			// Remove oEmbed discovery links.
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

			// Remove oEmbed-specific JavaScript from the front-end and back-end.
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );

			// Remove filter of the oEmbed result before any HTTP requests are made.
			remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result' );

			add_filter( 'tiny_mce_plugins', [ 'Bayfront\Tailor\TailorFilters', 'removeOembedFromTinyMce' ] );

			add_filter( 'rewrite_rules_array', [ 'Bayfront\Tailor\TailorFilters', 'removeEmbedRewriteRules' ] );

		}

	}

	/**
	 * Disable WordPress REST API.
	 *
	 * @return void
	 */
	private static function disableRestApi(): void {

		if ( get_field( 'tailor_disable_rest_api', 'option' ) ) {

			add_filter( 'rest_authentication_errors', function () {
				return new WP_Error( 'rest_disabled', __( 'The REST API has been disabled.' ), array( 'status' => rest_authorization_required_code() ) );
			} );

			remove_action( 'wp_head', 'rest_output_link_wp_head' );
			remove_action( 'template_redirect', 'rest_output_link_header', 11 );

		}

	}

	/**
	 * Update login image link to home URL.
	 *
	 * @return void
	 */
	private static function updateLoginImageLink(): void {

		if ( get_field( 'tailor_update_login_image_link', 'option' ) ) {
			add_filter( 'login_headerurl', function () {
				return home_url();
			} );
		}

	}

	private static function updateLoginImage(): void {

		if ( get_field( 'tailor_enable_login_image', 'option' ) && get_field( 'tailor_login_image_url', 'option' ) ) {

			add_action( 'login_enqueue_scripts', function () {

				$width = get_field( 'tailor_login_image_width', 'option' );

				if ( ! $width ) {
					$width = 320;
				}

				$height = get_field( 'tailor_login_image_height', 'option' );

				if ( ! $height ) {
					$height = 80;
				}

				echo '<style>#login h1 a, .login h1 a { background-image: url(' . get_field( 'tailor_login_image_url', 'option' ) . ');height: ' . $height . 'px;width: ' . $width . 'px;background-size: contain;background-repeat: no-repeat;padding-bottom: 10px;}</style>';

			} );

		}

	}

	/**
	 * Add field groups to ACF options page.
	 *
	 * @return void
	 */
	public static function addFieldGroups(): void {

		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group( array(
			'key'                   => 'group_6529a29574171',
			'title'                 => 'WP-Tailor Admin Bar',
			'fields'                => array(
				array(
					'key'                       => 'field_65295875a0801',
					'label'                     => 'Remove from admin bar',
					'name'                      => 'tailor_remove_admin_bar',
					'aria-label'                => '',
					'type'                      => 'checkbox',
					'instructions'              => '',
					'required'                  => 0,
					'conditional_logic'         => 0,
					'wrapper'                   => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'choices'                   => array(
						'menu-toggle'      => 'Menu toggle (for mobile)',
						'wp-logo'          => 'WordPress logo',
						'about'            => 'About (WordPress logo)',
						'contribute'       => 'Contribute (WordPress logo)',
						'wp-logo-external' => 'WordPress logo external',
						'wporg'            => 'WordPress org (WordPress logo external)',
						'documentation'    => 'Documentation (WordPress logo external)',
						'support-forums'   => 'Support forums (WordPress logo external)',
						'feedback'         => 'Feedback (WordPress logo external)',
						'site-name'        => 'Site name',
						'view-site'        => 'View site (Site name)',
						'comments'         => 'Comments',
						'new-content'      => 'New content',
						'new-post'         => 'New post (New content)',
						'new-media'        => 'New media (New content)',
						'new-page'         => 'New page (New content)',
						'new-user'         => 'New user (New content)',
						'top-secondary'    => 'Top secondary',
						'my-account'       => 'My account (Top secondary)',
						'user-actions'     => 'User actions (Top secondary > My account)',
						'user-info'        => 'User info (Top secondary > My account > User actions)',
						'edit-profile'     => 'Edit profile (Top secondary > My account > User actions)',
						'logout'           => 'Logout (Top secondary > My account > User actions)',
					),
					'default_value'             => array(),
					'return_format'             => 'value',
					'allow_custom'              => 0,
					'layout'                    => 'vertical',
					'toggle'                    => 0,
					'save_custom'               => 0,
					'custom_choice_button_text' => 'Add new choice',
				),
				array(
					'key'               => 'field_65297661f6449',
					'label'             => 'Replace "Howdy, "',
					'name'              => 'tailor_replace_howdy',
					'aria-label'        => '',
					'type'              => 'text',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'default_value'     => 'Hello,',
					'maxlength'         => '',
					'placeholder'       => '',
					'prepend'           => '',
					'append'            => '',
				),
				array(
					'key'               => 'field_652958de0f805',
					'label'             => 'Add page ID to admin bar "edit" link',
					'name'              => 'tailor_enable_edit_page_id',
					'aria-label'        => '',
					'type'              => 'true_false',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'message'           => 'Add page ID to admin bar "edit" link?',
					'default_value'     => 0,
					'ui_on_text'        => '',
					'ui_off_text'       => '',
					'ui'                => 1,
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'tailor-settings',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => '',
			'show_in_rest'          => 0,
		) );

		acf_add_local_field_group( array(
			'key'                   => 'group_652955f879a84',
			'title'                 => 'WP-Tailor fields',
			'fields'                => array(
				array(
					'key'                       => 'field_652958234104f',
					'label'                     => 'Remove from dashboard',
					'name'                      => 'tailor_remove_dashboard',
					'aria-label'                => '',
					'type'                      => 'checkbox',
					'instructions'              => '',
					'required'                  => 0,
					'conditional_logic'         => 0,
					'wrapper'                   => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'choices'                   => array(
						'welcome_panel'                     => 'Welcome panel',
						'normal.core.dashboard_site_health' => 'Site health',
						'normal.core.dashboard_right_now'   => 'Right now',
						'normal.core.dashboard_activity'    => 'Activity',
						'side.core.dashboard_quick_press'   => 'Quick press',
						'side.core.dashboard_primary'       => 'WordPress events and News',
					),
					'default_value'             => array(),
					'return_format'             => 'value',
					'allow_custom'              => 0,
					'layout'                    => 'vertical',
					'toggle'                    => 0,
					'save_custom'               => 0,
					'custom_choice_button_text' => 'Add new choice',
				),
				array(
					'key'               => 'field_6529573065c7f',
					'label'             => 'Enable support for page excerpts',
					'name'              => 'tailor_enable_page_excerpts',
					'aria-label'        => '',
					'type'              => 'true_false',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'message'           => 'Enable support for page excerpts?',
					'default_value'     => 0,
					'ui_on_text'        => '',
					'ui_off_text'       => '',
					'ui'                => 1,
				),
				array(
					'key'               => 'field_652956b1a3b20',
					'label'             => 'Remove paragraph tags from category description',
					'name'              => 'tailor_remove_tags_category_description',
					'aria-label'        => '',
					'type'              => 'true_false',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'message'           => 'Remove paragraph tags from category description?',
					'default_value'     => 0,
					'ui_on_text'        => '',
					'ui_off_text'       => '',
					'ui'                => 1,
				),
				array(
					'key'               => 'field_6529570780adf',
					'label'             => 'Remove paragraph tags from excerpts',
					'name'              => 'tailor_remove_tags_excerpts',
					'aria-label'        => '',
					'type'              => 'true_false',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'message'           => 'Remove paragraph tags from excerpts?',
					'default_value'     => 0,
					'ui_on_text'        => '',
					'ui_off_text'       => '',
					'ui'                => 1,
				),
				array(
					'key'               => 'field_652955f849e9a',
					'label'             => 'Disable emojis',
					'name'              => 'tailor_disable_emojis',
					'aria-label'        => '',
					'type'              => 'true_false',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'message'           => 'Disable emojis?',
					'default_value'     => 0,
					'ui_on_text'        => '',
					'ui_off_text'       => '',
					'ui'                => 1,
				),
				array(
					'key'                       => 'field_6529575cc039f',
					'label'                     => 'Remove from wp_head',
					'name'                      => 'tailor_remove_wp_head',
					'aria-label'                => '',
					'type'                      => 'checkbox',
					'instructions'              => '',
					'required'                  => 0,
					'conditional_logic'         => 0,
					'wrapper'                   => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'choices'                   => array(
						'wp_generator' => 'WordPress generator (version)',
						'rsd_link'     => 'RSD link',
					),
					'default_value'             => array(),
					'return_format'             => 'value',
					'allow_custom'              => 0,
					'layout'                    => 'vertical',
					'toggle'                    => 0,
					'save_custom'               => 0,
					'custom_choice_button_text' => 'Add new choice',
				),
				array(
					'key'               => 'field_652957bcffe1a',
					'label'             => 'Disable xmlrpc',
					'name'              => 'tailor_disable_xmlrpc',
					'aria-label'        => '',
					'type'              => 'true_false',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'message'           => 'Disable xmlrpc?',
					'default_value'     => 0,
					'ui_on_text'        => '',
					'ui_off_text'       => '',
					'ui'                => 1,
				),
				array(
					'key'               => 'field_652957dce738c',
					'label'             => 'Disable oembed',
					'name'              => 'tailor_disable_oembed',
					'aria-label'        => '',
					'type'              => 'true_false',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'message'           => 'Disable oembed?',
					'default_value'     => 0,
					'ui_on_text'        => '',
					'ui_off_text'       => '',
					'ui'                => 1,
				),
				array(
					'key'               => 'field_6529823b2cf37',
					'label'             => 'Remove Gutenberg block library CSS',
					'name'              => 'tailor_remove_block_library_css',
					'aria-label'        => '',
					'type'              => 'true_false',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'message'           => 'Remove Gutenberg block library CSS?',
					'default_value'     => 0,
					'ui_on_text'        => '',
					'ui_off_text'       => '',
					'ui'                => 1,
				),
				array(
					'key'               => 'field_652984089e181',
					'label'             => 'Remove global inline styles',
					'name'              => 'tailor_remove_global_styles',
					'aria-label'        => '',
					'type'              => 'true_false',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'message'           => 'Remove global inline styles?',
					'default_value'     => 0,
					'ui_on_text'        => '',
					'ui_off_text'       => '',
					'ui'                => 1,
				),
				array(
					'key'               => 'field_65298548c6ac5',
					'label'             => 'Disable REST API',
					'name'              => 'tailor_disable_rest_api',
					'aria-label'        => '',
					'type'              => 'true_false',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'message'           => 'Disable REST API? (WARNING: May be required by some plugins/themes)',
					'default_value'     => 0,
					'ui_on_text'        => '',
					'ui_off_text'       => '',
					'ui'                => 1,
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'tailor-settings',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => '',
			'show_in_rest'          => 0,
		) );

		acf_add_local_field_group( array(
			'key'                   => 'group_6529a2f3eec93',
			'title'                 => 'WP-Tailor Login Page',
			'fields'                => array(
				array(
					'key'               => 'field_6529a1685c5ec',
					'label'             => 'Update login image link',
					'name'              => 'tailor_update_login_image_link',
					'aria-label'        => '',
					'type'              => 'true_false',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'message'           => 'Update login image link to site home URL?',
					'default_value'     => 0,
					'ui_on_text'        => '',
					'ui_off_text'       => '',
					'ui'                => 1,
				),
				array(
					'key'               => 'field_652993c1ca683',
					'label'             => 'Enable custom login image',
					'name'              => 'tailor_enable_login_image',
					'aria-label'        => '',
					'type'              => 'true_false',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'message'           => 'Enable custom login image?',
					'default_value'     => 0,
					'ui_on_text'        => '',
					'ui_off_text'       => '',
					'ui'                => 1,
				),
				array(
					'key'               => 'field_65299bf2c2569',
					'label'             => 'Login image URL',
					'name'              => 'tailor_login_image_url',
					'aria-label'        => '',
					'type'              => 'url',
					'instructions'      => '',
					'required'          => 1,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'default_value'     => '',
					'placeholder'       => '',
				),
				array(
					'key'               => 'field_6529952ad7099',
					'label'             => 'Login image width (px)',
					'name'              => 'tailor_login_image_width',
					'aria-label'        => '',
					'type'              => 'number',
					'instructions'      => '',
					'required'          => 1,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '50',
						'class' => '',
						'id'    => '',
					),
					'default_value'     => 320,
					'min'               => '',
					'max'               => '',
					'placeholder'       => '',
					'step'              => 1,
					'prepend'           => '',
					'append'            => '',
				),
				array(
					'key'               => 'field_652995624e0df',
					'label'             => 'Login image height (px)',
					'name'              => 'tailor_login_image_height',
					'aria-label'        => '',
					'type'              => 'number',
					'instructions'      => '',
					'required'          => 1,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '50',
						'class' => '',
						'id'    => '',
					),
					'default_value'     => 80,
					'min'               => '',
					'max'               => '',
					'placeholder'       => '',
					'step'              => 1,
					'prepend'           => '',
					'append'            => '',
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'tailor-settings',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => '',
			'show_in_rest'          => 0,
		) );

	}

	/*
	 * |--------------------------------------------------------------------------
	 * | wp-tailor.php
	 * |--------------------------------------------------------------------------
	 */

	/**
	 * Customize admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 *
	 * @return void
	 */
	public static function customizeAdminBar( WP_Admin_Bar $wp_admin_bar ): void {

		$nodes = get_field( 'tailor_remove_admin_bar', 'option' );

		if ( is_array( $nodes ) && ! empty( $nodes ) ) {

			foreach ( $nodes as $node ) {
				$wp_admin_bar->remove_node( $node );
			}

		}

		// Replace "Howdy, "

		$my_account = $wp_admin_bar->get_node( 'my-account' );

		$wp_admin_bar->add_node( [
			'id'    => 'my-account',
			'title' => str_replace( 'Howdy, ', get_field( 'tailor_replace_howdy', 'option' ), $my_account->title )
		] );

		// Add page ID to edit link

		if ( get_field( 'tailor_enable_edit_page_id', 'option' ) ) {

			/*
			 * Page ID is extracted from existing href link, as querying the page ID
			 * may return different results.
			 */

			$edit = $wp_admin_bar->get_node( 'edit' );

			if ( $edit ) { // If node exists (does not exist in admin area)

				$edit_title = $edit->title;

				$href_part = explode( 'post=', $edit->href, 2 );

				if ( isset( $href_part[1] ) ) {

					$page_id = explode( '&amp;', $href_part[1], 2 );

					$edit_title = $edit_title . ' (' . $page_id[0] . ')';

				}

				$wp_admin_bar->add_node( [
					'id'     => $edit->id,
					'title'  => $edit_title,
					'parent' => $edit->parent,
					'href'   => $edit->href,
					'group'  => $edit->group,
					'meta'   => $edit->meta
				] );

			}

		}

	}

	public static function customizeDashboard(): void {

		global $wp_meta_boxes;

		$boxes = get_field( 'tailor_remove_dashboard', 'option' );

		if ( is_array( $boxes ) && ! empty( $boxes ) ) {

			foreach ( $boxes as $box ) {

				if ( $box == 'welcome_panel' ) {
					remove_action( 'welcome_panel', 'wp_welcome_panel' );
				} else {
					Arr::forget( $wp_meta_boxes['dashboard'], $box );
				}

			}

		}

	}

	/**
	 * Remove Gutenberg block library CSS.
	 *
	 * @return void
	 */
	public static function removeBlockLibraryCss(): void {

		if ( get_field( 'tailor_remove_block_library_css', 'option' ) ) {
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'wp-block-library-theme' );
		}

	}

	/**
	 * Remove global inline styles from wp_head.
	 * See: https://fullsiteediting.com/lessons/global-styles
	 *
	 * @return void
	 */
	public static function removeGlobalStyles(): void {

		if ( get_field( 'tailor_remove_global_styles', 'option' ) ) {
			wp_dequeue_style( 'global-styles' );
		}

	}

}