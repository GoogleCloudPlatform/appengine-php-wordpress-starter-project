#!/bin/sh

#  move_files_after_editing.sh
#  
#
#  Created by John Mulhausen on 10/17/13.
#
cp -fr wp-config.php wordpress/
cp -fr batcache/advanced-cache.php wordpress/wp-content/
cp -fr batcache/batcache.php wordpress/wp-content/plugins/
cp -fr wp-memcache/object-cache.php wordpress/wp-content/
cp -fr appengine-wordpress-plugin/. wordpress/wp-content/plugins/
