<?php
/**
 * Core functionality to get WordPress working on App Engine
 *
 * Copyright 2013 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @package WordPress
 * @subpackage Mail
 */

namespace google\appengine\WordPress;

Core::bootstrap();

/**
 * Core functionality for WordPress on App Engine
 *
 * This includes setting some sensible defaults (e.g. rewrites, SSL admin, file
 * editing), as well as infrastructure for the other modules.
 *
 * @package WordPress
 * @subpackage Mail
 */
class Core {
	/**
	 * Normal priority for filters
	 */
	const NORMAL_PRIORITY = 10;

	/**
	 * Low priority for filters
	 */
	const LOW_PRIORITY = 100;

	/**
	 * When was the admin.css file last updated?
	 */
	const CSS_VERSION = '201305100635';

	/**
	 * Set required settings and register our actions
	 */
	public static function bootstrap() {
		global $PHP_SELF;
		$_SERVER['PHP_SELF'] = $PHP_SELF = preg_replace( '/(\?.*)?$/', '', $_SERVER['REQUEST_URI'] );

		add_filter( 'got_rewrite', '__return_true', self::LOW_PRIORITY );
		if( is_production() ) {
			add_filter( 'secure_auth_redirect', '__return_true' );
			force_ssl_admin( true );
			
			defined( 'DISALLOW_FILE_EDIT' ) or define( 'DISALLOW_FILE_EDIT', true );
			defined( 'DISALLOW_FILE_MODS' ) or define( 'DISALLOW_FILE_MODS', true );
		}
		defined( 'DISABLE_WP_CRON' ) or define( 'DISABLE_WP_CRON', true );

    // We don't want to use fsockopen as on App Engine it's not efficient
    add_filter( 'use_fsockopen_transport', '__return_false' );

		// ::settings_link() takes 2 parameters
		add_filter( 'plugin_action_links', __CLASS__ . '::settings_link', self::NORMAL_PRIORITY, 2 );
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::register_styles' );
		add_action( 'admin_menu', __CLASS__ . '::register_settings_page' );
		add_action( 'admin_init', __CLASS__ . '::register_settings' );
		add_action( 'init', __CLASS__ . '::load_textdomain' );

	}

	/**
	 * Load the translation text domain for App Engine
	 *
	 * @wp-action init
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'appengine', false, dirname( plugin_basename( PLUGIN_PATH ) ) . '/languages/' );
	}

	/**
	 * Add a settings link to the plugin
	 *
	 * @wp-action plugin_action_links
	 */
	public static function settings_link( $links, $file ) {
		if ( $file == plugin_basename( PLUGIN_PATH ) ) {
			$links[] = '<a href="' . admin_url( 'options-general.php?page=appengine' ) . '">'
				. __( 'Settings', 'appengine' ) . '</a>';
		}

		return $links;
	}

	/**
	 * Register the App Engine settings page
	 *
	 * @wp-action admin_menu
	 */
	public static function register_settings_page() {
		add_options_page(
			__( 'App Engine Options', 'appengine' ),
			__( 'App Engine', 'appengine' ),
			'manage_options',
			'appengine',
			__CLASS__ . '::settings_view'
		);
	}

	/**
	 * Register the styles for the App Engine administration UI
	 *
	 * @wp-action admin_enqueue_scripts
	 */
	public static function register_styles() {
		wp_enqueue_style( 'appengine-admin', plugins_url( 'static/admin.css', PLUGIN_PATH ), array(), self::CSS_VERSION, 'all' );
	}

	/**
	 * Display the App Engine options page
	 *
	 * This is registered in {@see self::register_settings_page()}
	 */
	public static function settings_view() {
?>
		<div class="wrap">
			<?php screen_icon( 'appengine' ); ?>
			<h2><?php _e( 'Google App Engine Options', 'appengine' ) ?></h2>
			<form action="options.php" method="POST">
				<?php
					settings_fields( 'appengine_settings' );
					do_settings_sections( 'appengine' );
					submit_button();
				?>
			</form>
		</div>
<?php
	}

	/**
	 * Register the App Engine settings
	 *
	 * This is a better hook for the modules to use, as it's much more
	 * descriptive, plus using it ensures that the Core module has loaded.
	 *
	 * @wp-action admin_init
	 */
	public static function register_settings() {
		do_action( 'appengine_register_settings' );
	}
}
