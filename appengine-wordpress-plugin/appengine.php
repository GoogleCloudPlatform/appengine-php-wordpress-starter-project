<?php
/*
Plugin Name: Google App Engine for WordPress
Plugin URI: http://developers.google.com/appengine/
Description: Optimize your WordPress installation for Google App Engine
Version: 1.6
Author: Google
Author URI: http://developers.google.com/appengine/
License: GPL2

Copyright 2013 Google Inc.

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/

namespace google\appengine\WordPress;

define( __NAMESPACE__ . '\\PLUGIN_DIR', __DIR__ );
define( __NAMESPACE__ . '\\PLUGIN_PATH', __FILE__ );

/**
 * Are we running on the App Engine production environment?
 *
 * @return bool
 */
function is_production() {
	return isset( $_SERVER['SERVER_SOFTWARE'] ) && strpos( $_SERVER['SERVER_SOFTWARE'], 'Google App Engine' ) !== false;
}

/**
 * Are we running on the App Engine local development environment?
 *
 * @return bool
 */
function is_development() {
	return isset( $_SERVER['SERVER_SOFTWARE'] ) && strpos( $_SERVER['SERVER_SOFTWARE'], 'Development/' ) === 0;
}

/**
 * Callback for plugin activation
 *
 * This just calls the action to allow the modules to act independently.
 */
function activation() {
	do_action( 'appengine_activation' );
}

// Load the App Engine modules
$modules = [];
if ( $modules_dir = @ opendir( __DIR__ . '/modules/' ) ) {
	while ( false !== ( $file = readdir( $modules_dir ) ) ) {
		if ( '.php' === substr( $file, -4 ) )
			$modules[] = $file;
	}
	@closedir( $modules_dir );
	sort( $modules );

	foreach ( $modules as $file ) {
		require_once __DIR__ . '/modules/' . $file ;
	}
}

// Include the App Engine specific WordPress importer.
require_once __DIR__ . '/importer/wordpress-importer.php';

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activation' );
