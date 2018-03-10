<?php

// function:
class ka_Utils {

	static function ClassDump( $class ) {
		//$class = get_class( $obj );

		Logger::Log( "____________________________________________" );
		Logger::Log( "Class: $class" );
        Logger::Log( print_r( get_class_methods($class), true) ); 
        Logger::Log( print_r( get_class_vars($class), true) ) ;
		Logger::Log( "____________________________________________" );
	}
}