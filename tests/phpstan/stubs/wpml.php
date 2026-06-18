<?php
/**
 * Minimal WPML stubs for static analysis.
 *
 * WPML is an optional integration that is only loaded when the WPML plugin is
 * active. These stubs describe the small subset of WPML's API used by the
 * Cloudinary integration so PHPStan can analyse php/integrations/class-wpml.php.
 *
 * @package Cloudinary
 */

namespace WPML\Container {
	/**
	 * Resolve an instance from the WPML dependency injection container.
	 *
	 * @param string $class The fully qualified class name to resolve.
	 *
	 * @return mixed
	 */
	function make( $class ) {}
}

namespace WPML\FP {
	class Obj {
		/**
		 * Read a property from an array or object.
		 *
		 * @param string       $key    The property name.
		 * @param array|object $target The source to read from.
		 *
		 * @return mixed
		 */
		public static function prop( $key, $target = null ) {}
	}
}

namespace WPML\Records {
	class Translations {
		/**
		 * Get the source record for a given TRID.
		 *
		 * @param int $trid The translation group ID.
		 *
		 * @return object|null
		 */
		public static function getSourceByTrid( $trid ) {}
	}
}

namespace WPML\Auryn {
	class InjectionException extends \Exception {}
}
