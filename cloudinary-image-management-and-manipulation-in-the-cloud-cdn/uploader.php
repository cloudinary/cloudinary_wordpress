<?php
    /**
     * @deprecated CloudinaryUploader is deprecated, use \Cloudinary\Uploader instead
     *
     * THIS FILE IS FOR BACKWARDS COMPATIBILITY ONLY
     *
     * If you were not already including this file in your project, please ignore it
     */

    trigger_error('CloudinaryUploader is deprecated, use \Cloudinary\Uploader instead', E_USER_DEPRECATED);

    require_once plugin_dir_path(__FILE__) . 'lib' . DIRECTORY_SEPARATOR . "Uploader.php";

    // Create class alias for old class
    \class_alias(\Cloudinary\Uploader::class, CloudinaryUploader::class);

    // This tricks IDE and marks old class as deprecated
    if (! \class_exists(CloudinaryUploader::class)) {     // essentially this is "if(false)"
        /** @deprecated use \Cloudinary\Uploader */
        class CloudinaryUploader {}
    }
