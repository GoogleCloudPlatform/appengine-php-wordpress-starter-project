=== Google App Engine for WordPress ===
Contributors: google, sennza
Tags: google, app engine, gae, mail, email, uploads, uploading, cloud storage
Requires at least: 3.5
Tested up to: 4.1.1
Stable tag: 1.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Optimize your WordPress installation for the Google App Engine platform.


== Description ==

Google App Engine for WordPress enables seamless operation of your
WordPress-powered site on the App Engine PHP runtime.

This plugin adds overrides to core functionality in WordPress to use App Engine
infrastructure, such as the Mail functionality, and uploading media to Google
Cloud Storage

= Note: This plugin is designed to be used with Google App Engine only and will not work with any other hosting. = 

== Installation ==

This plugin should be downloaded and placed in your `/wp-content/plugins/`
directory. The App Engine infrastructure does not allow you to install plugins
and themes without deploying them via `appcfg.py`.

1. Unzip `appengine/` to the `/wp-content/plugins/` directory
2. Deploy your code to App Engine
3. Activate the plugin through the 'Plugins' menu in WordPress


== Frequently Asked Questions ==

= Why can't I use the plugin installer/upgrader? =

For security reasons, all code on App Engine must be deployed via `appcfg.py`.
This includes WordPress plugins and themes, as well as editing files via the
administration panel.

= Is this plugin required to run WordPress on App Engine? =

We recommend running App Engine for WordPress to ensure that all WordPress
functionality works correctly. Without this plugin, you will not be able to send
email or upload files, and some UI may be broken.


== Caching ==

We recommend using the [Batcache][] and [Memcache][] drop-ins to cache your
WordPress site. Batcache integrates with Memcache to cache your site on the App
Engine memcache server and will ensure that CloudSQL is used as little as
possible, reducing your costs.

If you host a rarely updated site, we suggest adding the following configuration
to your `wp-config.php`:

	$batcache = [
		'seconds' => 0,
		'max_age' => 60 * 30,  // 30 minutes.
	];

This will cache pages on your site for a year and ensure that they do not expire
in memcache. With this setup and a fully primed cache, all anonymous users will
be served via Memcache with no hits to CloudSQL.

Note that using WordPress' built-in comments will enable commenters to bypass
the cache, so if you want to use comments on a fully-cached site, we recommend
[Intense Debate][] or [Disqus][] (with the synchronization disabled).

[Batcache]: http://wordpress.org/plugins/batcache/
[Memcache]: http://wordpress.org/extend/plugins/memcached/
[Intense Debate]: http://wordpress.org/plugins/intensedebate/
[Disqus]: http://wordpress.org/plugins/disqus-comment-system/


== Changelog ==

= 1.6 =
* Fixed image sizes after uploading images (credit: tuanmh).
* Set 24 hour validity on upload URLs for media uploads (Requires App Engine PHP
  SDK 1.9.18 or greater).
* Fix link in readme for Memcache.

= 1.5 =
* Fix for media uploads failing in WordPress 4.0 due to incorrect auth cookies
  being copied.

= 1.4 =
* Use a default 30 second timeout for URLFetch requests.
* Use auto loading for GAE SDK now that it is available.
* Use CloudStorageTools::getPublicUrl() for Cloud Storage URLs so that they
  work correctly on the development server.
* Add support for serving uploaded media files over HTTPs.

= 1.3 =
* Add support for importing a WDX file from Google Cloud Storage into the site.
* Provide a URL Fetch based HTTP client, which is optimized for the App Engine
  environment. This also corrects issues caused by fsockopen only being available
  to paid application in the production environment.
* Fix bug detecting if the Cloud Storage bucket is writable during plugin setup.

= 1.2 =
* Use CloudStorageTools::getPublicUrl in the dev environment so PIL is not a requirement.
* Fix Readme file to highlight that the plugin is for Google App Engine only.
* Work around is_writable check in the development environment.

= 1.1 =
* Fix uploads issue on the development server where PyCrypto is not available.
* include 'max_bytes_per_blob' in createUploadUrl options only if wp_max_upload_size() is a positive int
* Remove writable bucket check work around is this is now natively supported.

= 1.0 =
* Initial release


== Upgrade Notice ==

= 1.4 =
* Use a default 30 second timeout for URLFetch requests.
* Use auto loading for GAE SDK.
* Use CloudStorageTools::getPublicUrl() for Cloud Storage URLs so that they
  work correctly on the development server.
* Add support for serving uploaded media files over HTTPs.

= 1.3 =
* Add support for importing a WDX file from Google Cloud Storage into the site.
* Provide a URL Fetch based HTTP client, which is optimized for the App Engine
  environment. This also corrects issues caused by fsockopen only being available
  to paid application in the production environment.

= 1.2 =
* Use CloudStorageTools::getPublicUrl in the dev environment so PIL is not a requirement.
* Work around is_writable check in the development environment.

= 1.1 =
* Fix uploads issue on the development server where PyCrypto is not available.
* include 'max_bytes_per_blob' in createUploadUrl options only if wp_max_upload_size() is a positive int
* Remove writable bucket check work around is this is now natively supported.

= 1.0 =
This version is the initial release of Google App Engine for WordPress.
