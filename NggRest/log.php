<?php

// ToDo: put a date/time header on the first call for this session.
//

class Logger {

	static $ClearLog = false;
	static $count = 0;
	static function LogfilePath() { 
		return plugin_dir_path( __DIR__ ) . "NggPlusPlus/__Log.log"; // double score puts it at the top
	 }
	 static function Log( $s ) {

		if ( ! is_string( $s ) && get_class( $s ) == 'stdClass' ) {
			$a = get_object_vars( $s );
			$b = get_class_methods( $s );
			file_put_contents( self::LogfilePath(), print_r( $a, true ), self::$ClearLog ? 0 : FILE_APPEND );
			file_put_contents( self::LogfilePath(), print_r( $b, true ), self::$ClearLog ? 0 : FILE_APPEND );
			self::$ClearLog = false;
		} else {
			$s = $s . "\n";
			file_put_contents( self::LogfilePath(), $s, self::$ClearLog ? 0 : FILE_APPEND );
			self::$ClearLog = false;
		}
	 }
	 static function Delete() {
		 unlink( self::LogFilePath() );
	 }

}