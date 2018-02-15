<?php
/**
 * Cloudinary wordpress autoloader
 * Include this file to autoload cloudinary files from src/ directory
 */

define ('CLOUDINARY_NAMESPACE', 'Cloudinary');

spl_autoload_register( 'cloudinary_autoloader' );

function cloudinary_autoloader( $class_name ) {
    // Ignore non-cloudinary classes
    if (strpos($class_name, CLOUDINARY_NAMESPACE) === false) {
        return false;
    }

    $classes_dir = realpath(plugin_dir_path(__FILE__)) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR;
    $class_file = str_replace('_', DIRECTORY_SEPARATOR, $class_name) . '.php';
    $ns_prefix = CLOUDINARY_NAMESPACE . '\\';
    if (substr($class_file, 0, strlen($ns_prefix)) == $ns_prefix) {
        $class_file = substr($class_file, strlen($ns_prefix));
    }

    require_once $classes_dir . $class_file;

    return true;
}
