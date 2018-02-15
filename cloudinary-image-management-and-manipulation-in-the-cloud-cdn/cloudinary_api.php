<?php
    /**
     * @deprecated Cloudinary in cloudinary_api.php is deprecated, use src/Cloudinary.php instead
     *
     * THIS FILE IS FOR BACKWARDS COMPATIBILITY ONLY
     *
     * If you were not already including this file in your project, please ignore it
     */

    trigger_error('Cloudinary in cloudinary_api.php is deprecated, use src/Cloudinary.php instead', E_USER_DEPRECATED);

    require_once plugin_dir_path(__FILE__) . 'lib' . DIRECTORY_SEPARATOR . "Cloudinary.php";
