<?php
namespace DBisso\Plugin\SkeletonPlugin;

/**
 * Class DBisso\Plugin\SkeletonPlugin\Plugin
 */
class Plugin {
	static $_hooker;

	function bootstrap( $hooker = null ) {
		if ( $hooker ) {
			if ( !method_exists( $hooker, 'hook' ) )
				throw new \BadMethodCallException( 'Class ' . get_class( $hooker ) . ' has no hook() method.', 1 );

			self::$_hooker = $hooker;
			self::$_hooker->hook( __CLASS__, 'spliced-static-content' );
		} else {
			throw new \BadMethodCallException( 'Hooking class for ' . __CLASS__ . ' not specified.' , 1 );
		}

		register_activation_hook( __FILE__, array( __CLASS__, 'activation' ) );
	}

	function activation() {}
}