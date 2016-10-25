<?php
/**
 * Google App Engine uploading functionality
 *
 * Hijacks the uploading functionality in WordPress to use Google Cloud Storage
 * for the media library.
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
 * @package App Engine
 * @subpackage Uploads
 */
namespace google\appengine\WordPress\Uploads;

require_once ABSPATH . '/wp-includes/class-wp-image-editor.php';
require_once ABSPATH . '/wp-includes/class-wp-image-editor-gd.php';

use google\appengine\api\app_identity\AppIdentityService;
use google\appengine\api\cloud_storage\CloudStorageException;
use google\appengine\api\cloud_storage\CloudStorageTools;
use WP_Image_Editor_GD;
use WP_User;

Uploads::bootstrap();
Admin::bootstrap();

/**
 * Main handlers for the Upload module
 *
 * @package App Engine
 * @subpackage Uploads
 */
class Uploads {
	/**
	 * Cookie age in days
	 *
	 * This is based on the value used in WordPress core
	 */
	const COOKIE_AGE = 2;

	/**
	 * High priority for filters
	 */
	const HIGH_PRIORITY = -100;

	/**
	 * Normal priority for filters
	 */
	const NORMAL_PRIORITY = 10;

  // Various configuration names
  const USE_SECURE_URLS_OPTION = 'appengine_use_secure_urls';

	/**
	 * Image sizes cache
	 *
	 * @see self::image_sizes()
	 * @var array|null
	 */
	protected static $image_sizes = null;

	/**
	 * Should we skip filtering the image data?
	 *
	 * This ensures we don't filter recursively when falling back
	 * @var boolean
	 */
	protected static $skip_image_filters = false;

  /**
	 * Register our filters and actions
	 */
	public static function bootstrap() {
		add_filter( 'upload_dir', __CLASS__ . '::filter_directory' );

		// We have to return null here rather than false, since pre_option_...
		// only applies when `false !== $pre`
		//
		// TODO: Remove this once GCS streams support listdir
		add_filter( 'pre_option_uploads_use_yearmonth_folders', '__return_null' );

		add_action( 'all_admin_notices', __CLASS__ . '::add_form_hooks' );
		add_filter( 'plupload_default_settings', __CLASS__ . '::plupload_settings' );
		add_filter( 'plupload_init', __CLASS__ . '::plupload_settings' );

		// ::authenticate() takes 3 parameters
		add_filter( 'authenticate', __CLASS__ . '::authenticate', self::HIGH_PRIORITY, 3 );
		add_action( 'plugins_loaded', __CLASS__ . '::preauthenticate', self::HIGH_PRIORITY );

		// ::get_intermediate_url() takes 3 parameters
		add_filter( 'image_downsize', __CLASS__ . '::get_intermediate_url', self::NORMAL_PRIORITY, 3 );
		add_filter( 'wp_image_editors', __CLASS__ . '::custom_image_editor' );
	}

	/**
	 * Ensure that we always authenticate correctly
	 *
	 * WP does this internally for the built-in methods, but we need to take
	 * care of it ourselves for the custom authentication
	 *
	 * @wp-action plugins_loaded
	 *
	 * @uses $current_user
	 */
	public static function preauthenticate() {
		$user = self::authenticate( null, '', '' );

		if ( $user ) {
			global $current_user;
			$current_user = $user;
		}
	}

	/**
	 * Authenticate App Engine using the internal key and signature
	 *
	 * @wp-filter authenticate
	 *
	 * @param null|WP_User $user
	 * @param string $username
	 * @param string $password
	 * @return null|WP_User
	 */
	public static function authenticate( $user, $username, $password ) {
		if ( is_a( $user, 'WP_User' ) ) {
			return $user;
		}

		if ( !empty( $username ) || !empty( $password ) ) {
			return $user;
		}

		if ( empty( $_GET['gae_auth_user'] ) || empty( $_GET['gae_auth_key'] ) || empty( $_GET['gae_auth_signature'] ) ) {
			return $user;
		}

		$user_id     = absint( $_GET['gae_auth_user'] );
		$sign_result = self::sign_auth_key( AUTH_KEY . $user_id );

		if ( $sign_result['key_name'] !== $_GET['gae_auth_key'] ) {
			return $user;
		}

		if ( base64_decode( $_GET['gae_auth_signature'] ) !== $sign_result['signature'] ) {
			return $user;
		}

		// Generate fake cookies for wp_validate_auth_cookie
		self::set_fake_cookies( $user_id );

		return new WP_User( $user_id );
	}

	/**
	 * Set the $_COOKIE values for our custom authentication
	 *
	 * Certain areas of WordPress use the $_COOKIE value directly rather than
	 * passing through the authentication filter, so we need to work
	 * around this.
	 *
	 * @param int $user_id
	 */
	protected static function set_fake_cookies( $user_id ) {
		$expiration = time() + apply_filters( 'auth_cookie_expiration', self::COOKIE_AGE * DAY_IN_SECONDS, $user_id, false );
		$expire     = 0;

		$secure                  = apply_filters( 'secure_auth_cookie', is_ssl(), $user_id );
		$secure_logged_in_cookie = apply_filters( 'secure_logged_in_cookie', false, $user_id, $secure );

		if ( $secure ) {
			$auth_cookie_name = SECURE_AUTH_COOKIE;
			$scheme           = 'secure_auth';
		} else {
			$auth_cookie_name = AUTH_COOKIE;
			$scheme           = 'auth';
		}

		$auth_cookie      = wp_generate_auth_cookie( $user_id, $expiration, $scheme );
		$logged_in_cookie = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in' );

		if ( !isset($_COOKIE[$auth_cookie_name]) ) $_COOKIE[$auth_cookie_name] = $auth_cookie;
		if ( !isset($_COOKIE[LOGGED_IN_COOKIE]) ) $_COOKIE[LOGGED_IN_COOKIE] = $logged_in_cookie;
	}

	/**
	 * Change the upload directory to point at our upload bucket
	 *
	 * The keys required in values are:
	 *   - path (string): Full path to upload directory
	 *   - url (string): Full URL to uploads (we correct this in
	 *                   {@see self::handle_upload()})
	 *   - subdir (string): Subdirectories in upload directory
	 *   - basedir (string): path + subdir
	 *   - baseurl (string): url + subdir
	 *   - error (boolean): Did we hit an error?
	 *
	 * @wp-filter upload_dir
	 *
	 * @param array $values Default upload directory values
	 * @return array Filtered values
	 */
	public static function filter_directory( $values ) {
		if ( self::$skip_image_filters ) {
			return $values;
		}

		$default = stream_context_get_options( stream_context_get_default() );
		$gcs_opts = [
			'gs' => [
				'acl' => 'public-read',
			],
		];
		$context = array_replace_recursive( $default, $gcs_opts );
		stream_context_set_default( $context );

    $bucket_name = get_option('appengine_uploads_bucket', '');
		$values = array(
			'path' => 'gs://' . $bucket_name,
			'subdir' => '',
			'error' => false,
		);
    $secure_urls = (bool) get_option(self::USE_SECURE_URLS_OPTION, '' );
    $public_url = CloudStorageTools::getPublicUrl('gs://' . $bucket_name,
                                                  $secure_urls);
		$values['url'] = rtrim($public_url, '/');
		$values['basedir'] = $values['path'];
		$values['baseurl'] = $values['url'];
		return $values;
	}

	/**
	 * Register the upload form mangle hook
	 *
	 * We need to register this later than plugin load to avoid changing the
	 * interface URLs (e.g. admin bar, menu)
	 *
	 * @wp-action all_admin_notices
	 */
	public static function add_form_hooks() {
		add_filter( 'admin_url', __CLASS__ . '::mangle_upload_form', self::NORMAL_PRIORITY, 2 );
	}

	/**
	 * Change Plupload's upload URL to point at GCS
	 *
	 * @wp-filter plupload_default_settings
	 * @wp-filter plupload_init
	 *
	 * @param array $settings
	 * @return array
	 */
	public static function plupload_settings( $settings ) {
		$settings['url'] = self::get_wrapped_url( $settings['url'] );
		return $settings;
	}

	/**
	 * Change the upload form's URL to point to the GCS uploader
	 *
	 * @wp-filter admin_url
	 * @param string $url Existing URL
	 * @param string $path Path to get URL for
	 * @return string URL for specificed path
	 */
	public static function mangle_upload_form( $url, $path ) {
		global $parent_file;

		if ( 'upload.php' === $parent_file && 'media-new.php' === $path ) {
			// Only run once
			remove_filter( 'admin_url', __CLASS__ . '::mangle_upload_form', self::NORMAL_PRIORITY, 2 );

			return self::get_wrapped_url( $url );
		}

		return $url;
	}

	/**
	 * Wrap a URL with its Cloud Storage uploader proxy
	 *
	 * @param string $url
	 * @return string Wrapped URL
	 */
	protected static function get_wrapped_url( $url ) {
		$options = [
			'gs_bucket_name' => get_option( 'appengine_uploads_bucket', '' ),
      'url_expiry_time_seconds' => 60 * 60 * 24,  // One day is the maximum
		];
		$wp_maxupsize = wp_max_upload_size();
		// set max_bytes_per_blob option only if max upload size is a positive int
		if (is_int($wp_maxupsize) && $wp_maxupsize > 0) {
			$options['max_bytes_per_blob'] = $wp_maxupsize;
		}
		// Setup internal authentication
		$sign_result = self::sign_auth_key( AUTH_KEY . get_current_user_id() );
		$url = add_query_arg( 'gae_auth_user', get_current_user_id(), $url );
		$url = add_query_arg( 'gae_auth_key', $sign_result['key_name'], $url );
		$url = add_query_arg( 'gae_auth_signature', urlencode( base64_encode( $sign_result['signature'] ) ), $url );

		return CloudStorageTools::createUploadUrl( $url, $options );
	}

	/**
	 * Get a resized image URL for an attachment image
	 *
	 * Uses Google Cloud Storage to resize and serve an attachment image.
	 *
	 * @wp-filter image_downsize
	 *
	 * @param null|array $data Existing data (we always override)
	 * @param int $id Attachment ID
	 * @param string $size Size ID
	 * @return array Indexed array of URL, width, height, is intermediate
	 */
	public static function get_intermediate_url( $data, $id, $size ) {
		$file = get_attached_file( $id );
		if ( 0 !== strpos( $file, 'gs://' ) || self::$skip_image_filters ) {
			return $data;
		}

		$sizes = self::image_sizes();
		if ( is_array( $size ) ) {
			$size = ['width' => $size[0], 'height' => $size[1], 'crop' => false];
		}
		else {
			$size = $sizes[ $size ];
		}
		$options = [];

		// If height or width is null (i.e. full size), $real_size will be
		// null, providing us a way to tell if the size is intermediate
		$real_size = max( $size['height'], $size['width'] );
		if ( $real_size ) {
			$options = [
				'size' => $real_size,
				'crop' => (bool) $size['crop']
			];
		}
		else {
			$options = [
				'size' => 0,
				'crop' => false
			];
		}

		$baseurl     = get_post_meta( $id, '_appengine_imageurl', true );
		$cached_file = get_post_meta( $id, '_appengine_imageurl_file', true );
    $secure_urls = (bool) get_option(self::USE_SECURE_URLS_OPTION, false);

		if ( empty( $baseurl ) || $cached_file !== $file ) {
			try {
				if (self::is_production()) {
          $options = ['secure_url' => $secure_urls];
					$baseurl = CloudStorageTools::getImageServingUrl($file, $options);
				}
				// If running on the development server, use getPublicUrl() instead
				// of getImageServingUrl().
				// This removes the requirement for the Python PIL library to be installed
				// in the development environment.
				// TODO: this is a temporary modification.
				else {
					$baseurl = CloudStorageTools::getPublicUrl($file, $secure_urls);
				}
				update_post_meta( $id, '_appengine_imageurl', $baseurl );
				update_post_meta( $id, '_appengine_imageurl_file', $file );
			}
			catch ( CloudStorageException $e ) {
        syslog(LOG_ERR,
            'There was an exception creating the Image Serving URL, details ' .
            $e->getMessage());
				self::$skip_image_filters = true;
				$data = image_downsize( $id, $size );
				self::$skip_image_filters = false;

				return $data;
			}
		}

		$url = $baseurl;

		// Only append image options to the URL if we're running in production,
		// since in the development context getPublicUrl() is currently used to
		// generate the URL.
		if (self::is_production()) {
			if ( ! is_null( $options['size'] ) ) {
				$url .= ( '=s' . $options['size'] );
				if ( $options['crop'] ) {
					$url .= '-c';
				}
			}
			else {
				$url .= '=s0';
			}
		}

		$data = [
			$url, // URL
			$size['width'],
			$size['height'],
			(bool) $real_size // image is intermediate
		];
		return $data;
	}

	/**
	 * Provide an array of available image sizes and corresponding dimensions.
	 * Similar to get_intermediate_image_sizes() except that it includes image
	 * sizes' dimensions, not just their names.
	 *
	 * @author Jetpack (http://jetpack.me/)
	 *
	 * @global $wp_additional_image_sizes
	 * @uses get_option
	 * @return array
	 */
	protected static function image_sizes() {
		if ( null === self::$image_sizes ) {
			global $_wp_additional_image_sizes;

			// Populate an array matching the data structure of $_wp_additional_image_sizes so we have a consistent structure for image sizes
			$images = [
				'thumb' => [
					'width' => intval( get_option( 'thumbnail_size_w' ) ),
					'height' => intval( get_option( 'thumbnail_size_h' ) ),
					'crop' => (bool) get_option( 'thumbnail_crop' )
				],
				'medium' => [
					'width' => intval( get_option( 'medium_size_w' ) ),
					'height' => intval( get_option( 'medium_size_h' ) ),
					'crop' => false
				],
				'large' => [
					'width' => intval( get_option( 'large_size_w' ) ),
					'height' => intval( get_option( 'large_size_h' ) ),
					'crop' => false
				],
				'full' => [
					'width' => null,
					'height' => null,
					'crop' => false
				]
			];

			// Compatibility mapping as found in wp-includes/media.php
			$images['thumbnail'] = $images['thumb'];

			// Update class variable, merging in $_wp_additional_image_sizes if any are set
			if ( is_array( $_wp_additional_image_sizes ) && ! empty( $_wp_additional_image_sizes ) ) {
				self::$image_sizes = array_merge( $images, $_wp_additional_image_sizes );
			}
			else {
				self::$image_sizes = $images;
			}
		}

		return is_array( self::$image_sizes ) ? self::$image_sizes : array();
	}

  private static function is_production() {
    return isset( $_SERVER['SERVER_SOFTWARE'] ) && strpos( $_SERVER['SERVER_SOFTWARE'], 'Google App Engine' ) !== false;
  }

  private static function sign_auth_key($auth_key) {
    if (self::is_production()) {
      return AppIdentityService::signForApp($auth_key);
    } else {
      // In the development server we are not concerned with trying to generate
      // a secure signature.
      return [
        'key_name' => 'development_hash',
        'signature' => sha1($auth_key),
      ];
    }
  }

	public static function custom_image_editor( $editors ) {
		$editors = [ __NAMESPACE__ . '\\Editor' ] + $editors;
		return $editors;
	}
}

/**
 * Custom App Engine image editor based on GD
 */
class Editor extends WP_Image_Editor_GD {
	/**
	 * Resize to multiple sizes
	 *
	 * We override this to give nothing, as we handle image resizes via the
	 * GCS APIs instead.
	 *
	 * @param array $sizes
	 * @return array
	 */
	public function multi_resize( $sizes ) {
		return [];
	}

	/**
	 * Either calls editor's save function or handles file as a stream.
	 *
	 * @since 3.5.0
	 * @access protected
	 *
	 * @param string|stream $filename
	 * @param callable $function
	 * @param array $arguments
	 * @return boolean
	 */
	protected function make_image( $filename, $function, $arguments ) {
		// Setup the stream wrapper context
		$default = stream_context_get_options( stream_context_get_default() );

		$gcs_opts = [
			'gs' => [
				'acl' => 'public-read',
			],
		];
		switch ( $function ) {
			case 'imagepng':
				$gcs_opts['gs']['Content-Type'] = 'image/png';
				break;

			case 'imagejpeg':
				$gcs_opts['gs']['Content-Type'] = 'image/jpeg';
				break;

			case 'imagegif':
				$gcs_opts['gs']['Content-Type'] = 'image/gif';
				break;
		}
		$context = array_merge( $default, $gcs_opts );
		stream_context_set_default( $context );

		// Work around bug in core WordPress
		// http://core.trac.wordpress.org/ticket/24459
		$arguments[1] = null;

		$result = parent::make_image( $filename, $function, $arguments );

		// Restore the default wrapper context
		stream_context_set_default( $default );

		return $result;
	}
}

/**
 * Administration settings for the Upload module
 *
 * @package App Engine
 * @subpackage Uploads
 */
class Admin {
	public static function bootstrap(){
		add_action( 'appengine_register_settings', __CLASS__ . '::register_google_settings' );
		add_action( 'appengine_activation', __CLASS__ . '::set_default_bucket' );
	}

	public static function register_google_settings() {
    register_setting('appengine_settings',
                     Uploads::USE_SECURE_URLS_OPTION,
                     __CLASS__ . '::secure_urls_validation');

		register_setting('appengine_settings',
                     'appengine_uploads_bucket',
                     __CLASS__ . '::bucket_validation');

		add_settings_section('appengine-uploads',
                         __( 'Upload Settings', 'appengine' ),
                         __CLASS__ . '::section_text',
                         'appengine');

		add_settings_field('appengine_uploads_bucket',
                       __( 'Bucket Name', 'appengine' ),
                       __CLASS__ . '::bucket_input',
                       'appengine',
                       'appengine-uploads',
                       ['label_for' => 'appengine_uploads_bucket']);

    add_settings_field(Uploads::USE_SECURE_URLS_OPTION,
                       __('Use secure URLs for serving media files', 'appengine'),
                       __CLASS__ . '::enable_secure_urls',
                       'appengine',
                       'appengine-uploads',
                       ['label_for' => Uploads::USE_SECURE_URLS_OPTION]);
	}

	public static function section_text() {
		$prereq = 'https://developers.google.com/appengine/docs/python/googlestorage/overview#Prerequisites';

		$text = sprintf(
			__( "Uploads from WordPress are handled by the Google Cloud Storage architecture. Note that you'll need to first <a href='%s'>set up PHP to upload to GCS</a>.", 'appengine' ),
			esc_attr( $prereq )
		);

		echo '<p>' . $text . '</p>';

		$default = CloudStorageTools::getDefaultGoogleStorageBucketName();
		if ( ! empty( $default ) ) {
			echo '<p>'
				. sprintf(
					__( 'Your current default bucket for this app is <code>%s</code>', 'appengine' ),
					$default
				) . '</p>';
		}
	}

  public static function enable_secure_urls() {
    $enabled = get_option(Uploads::USE_SECURE_URLS_OPTION, false );
    echo '<input id="appengine_use_secure_urls" name="appengine_use_secure_urls"
				type="checkbox" ' . checked( $enabled, true, false ) . ' />';
    echo '<p class="description">' . __(
          'Check to serve uploaded media files over HTTPs. ' .
          '<strong>Note:</strong> This setting only effects new uploads, it will not ' .
          'change the HTTP scheme for files that were previously uploaded.',
          'appengine') .
          '</p>';

  }

	public static function bucket_input() {
		$bucket = get_option( 'appengine_uploads_bucket', '' );
		echo '<input id="appengine_uploads_bucket" name="appengine_uploads_bucket"
			type="text" value="' . esc_attr( $bucket ) . '" />';

		echo '<p class="description">' . __( 'Leave blank to use the default bucket', 'appengine' ) . '</p>';
	}

  public static function secure_urls_validation($input) {
    return (bool) $input;
  }

	public static function bucket_validation( $input ) {
		if ( empty( $input ) ) {
			return CloudStorageTools::getDefaultGoogleStorageBucketName();
		}

    $bucket_name = 'gs://' . $input;
    $valid_bucket_name = true;
    // In the devappserver there is a chicken and egg problem with bucket
    // creation - so we need to special case this check for the time being.
    if ( self::is_production() ) {
      if (!file_exists( $bucket_name ) || !is_writable( $bucket_name ) ) {
        $valid_bucket_name = false;
      }
    } else {
      $valid_bucket_name = self::bucket_is_writable($bucket_name);
    }

    if (!$valid_bucket_name) {
      add_settings_error( 'appengine_settings', 'invalid-bucket', __( 'You have entered an invalid bucket name, or the bucket is not writable.', 'appengine' ) );
    }

		return $input;
	}

	public static function set_default_bucket() {
		$current = get_option( 'appengine_uploads_bucket', false );
		if ( ! empty( $current ) ) {
			return;
		}

		$default = CloudStorageTools::getDefaultGoogleStorageBucketName();
		update_option( 'appengine_uploads_bucket', $default );
	}

  /**
   * Workaround for Windows bug in is_writable() function
   *
   * @since 2.8.0
   *
   * @param string $path
   * @return bool
   */
  public static function bucket_is_writable( $bucket ) {
    $path = $bucket . '/wordpress-write-check.tmp';

    // check tmp file for read/write capabilities
    $f = fopen( $path, 'w' );
    if ( $f === false ) {
      return false;
    }

    fclose( $f );
    unlink( $path );
    return true;
  }

  // TODO: Cleanup with common method.
  private static function is_production() {
    return isset( $_SERVER['SERVER_SOFTWARE'] ) && strpos( $_SERVER['SERVER_SOFTWARE'], 'Google App Engine' ) !== false;
  }
}
