<?php

/**
 * This is a copy of our Batcache-stats.php file but it is suffixed so that you don't use it by default.
 * You probably don't want huge log files filling up your server by default, but this gives you and idea
 * of how we use this function. We have a separate parsing script that comes along and reads these files
 * and enters them into our internal stats.
 */

if ( !function_exists( 'batcache_stats' ) ) {
	function batcache_stats( $name, $value, $num = 1, $today = FALSE, $hour = FALSE ) {
		if ( !$today )
			$today = gmdate( 'Y-m-d' );
		if ( !$hour )
			$hour = gmdate( 'Y-m-d H:00:00' );

		// batcache never loads wpdb so always do this async
		if ( !file_exists( '/var/spool/wpcom/extra' ) )
			mkdir( '/var/spool/wpcom/extra', 0777 );

		$value = rawurlencode( $value );
		$stats_filename = "/var/spool/wpcom/extra/{$today}_" . gmdate( 'H' . '-' . 'i' );
		if ( ! file_exists( $stats_filename ) ) {
			touch( $stats_filename );
			chmod( $stats_filename, 0777 );
		}

		$fp = fopen( $stats_filename,  'a' );
		fwrite( $fp, "{$hour}\t{$name}\t{$value}\t{$num}" . chr( 10 ) );
		fclose( $fp );
	}
}
