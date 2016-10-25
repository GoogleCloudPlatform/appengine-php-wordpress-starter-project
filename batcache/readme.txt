=== Batcache ===
Contributors: automattic, andy, orensol, markjaquith, vnsavage, batmoo, yoavf
Tags: cache, memcache, memcached, speed, performance, load, server
Requires at least: 3.2
Tested up to: 3.5
Stable tag: 1.2

Batcache uses Memcached to store and serve rendered pages.

== Description ==

Batcache uses Memcached to store and serve rendered pages. It can also optionally cache redirects. It's not as fast as Donncha's WP-Super-Cache but it can be used where file-based caching is not practical or not desired. For instance, any site that is run on more than one server should use Batcache because it allows all servers to use the same storage.

Development testing showed a 40x reduction in page generation times: pages generated in 200ms were served from the cache in 5ms. Traffic simulations with Siege demonstrate that WordPress can handle up to twenty times more traffic with Batcache installed.

Batcache is aimed at preventing a flood of traffic from breaking your site. It does this by serving old pages to new users. This reduces the demand on the web server CPU and the database. It also means some people may see a page that is a few minutes old. However this only applies to people who have not interacted with your web site before. Once they have logged in or left a comment they will always get fresh pages.

Possible future features:

* Comments, edits, and new posts will trigger cache regeneration
* Online installation assistance
* Configuration page
* Stats

== Installation ==

1. Get the Memcached backend working. See below.

1. Upload `advanced-cache.php` to the `/wp-content/` directory

1. Add this line the top of `wp-config.php` to activate Batcache:

`define('WP_CACHE', true);`

1. Test by reloading a page in your browser several times and then viewing the source. Just above the `</head>` closing tag you should see some Batcache stats.

1. Tweak the options near the top of `advanced-cache.php`

1. *Optional* Upload `batcache.php` to the `/wp-content/plugins/` directory.

= Memcached backend =

1. Install [memcached](http://danga.com/memcached) on at least one server. Note the connection info. The default is `127.0.0.1:11211`.

1. Install the [PECL memcached extension](http://pecl.php.net/package/memcache) and [Ryan's Memcached backend 2.0](http://svn.wp-plugins.org/memcached/trunk/). Use the [1.0 branch](http://svn.wp-plugins.org/memcached/branches/1.0/) if you don't have or can't install the PECL extension.

== Frequently Asked Questions ==

= Should I use this? =

Batcache can be used anywhere Memcached is available. WP-Super-Cache is preferred for most blogs. If you have more than one web server, try Batcache.

= Why was this written? =

Batcache was written to help WordPress.com cope with the massive and prolonged traffic spike on Gizmodo's live blog during Apple events. Live blogs were famous for failing under the load of traffic. Gizmodo's live blog stays up because of Batcache.

Actually all of WordPress.com stays up during Apple events because of Batcache. The traffic is twice the average during Apple events. But the web servers and databases barely feel the difference.

= What does it have to do with bats? =

Batcache was named "supercache" when it was written. (It's still called that on WordPress.com.) A few months later, while "supercache" was still private, Donncha released the WP-Super-Cache plugin. It wouldn't be fun to dispute the name or create confusion for users so a name change seemed best. The move from "Super" to "Bat" was inspired by comic book heroes. It has nothing to do with the fact that the author's city is home to the [world's largest urban bat colony](http://www.batcon.org/home/index.asp?idPage=122).

== Changelog ==

= trunk =
* Add REQUEST_METHOD to the cache keys. Prevents GET requests receiving bodyless HEAD responses. This change invalidates the entire cache at upgrade time.

= 1.1 =
* Many bugfixes and updates from trunk
