<?php
/**
 * Autoloads classes from the WebberZone\Code_Block_Highlighting namespace.
 *
 * @package WebberZone\Code_Block_Highlighting
 */

namespace WebberZone\Code_Block_Highlighting;

defined( 'ABSPATH' ) || exit;

/**
 * Autoloader for WebberZone\Code_Block_Highlighting classes.
 *
 * @param string $class_name The name of the class to load.
 */
function autoload( $class_name ) {
	$namespace = __NAMESPACE__;

	if ( class_exists( $class_name, false ) ) {
		return;
	}

	if ( false !== strpos( $class_name, $namespace ) ) {
		// Project namespace.
		$project_namespace = $namespace . '\\';
		$length            = strlen( $project_namespace );

		$class_file = substr( $class_name, $length ); // Remove top-level namespace.
		$class_file = str_replace( '_', '-', strtolower( $class_file ) ); // Swap underscores for dashes and lowercase.

		// Prepend `class-` to the filename (last class part).
		$class_parts                = explode( '\\', $class_file );
		$last_index                 = count( $class_parts ) - 1;
		$class_parts[ $last_index ] = 'class-' . $class_parts[ $last_index ];

		// Join everything back together and add the file extension.
		$class_file = implode( DIRECTORY_SEPARATOR, $class_parts ) . '.php';
		$location   = __DIR__ . DIRECTORY_SEPARATOR . $class_file;

		if ( ! is_file( $location ) ) {
			return;
		}

		require_once $location;
	}
}

$autoload_functions = spl_autoload_functions();
if ( ! in_array( __NAMESPACE__ . '\autoload', $autoload_functions ? $autoload_functions : array(), true ) ) {
	spl_autoload_register( __NAMESPACE__ . '\autoload' );
}
