<?php

namespace Cloudinary {


    class Cli
    {
        protected $plugin;

        public function __construct($plugin)
        {
            $this->plugin = $plugin;
        }

        public function __invoke()
        {
            $this->plugin->config();
            $args = array(
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'post_status' => 'inherit',
                'posts_per_page' => -1,
            );

            $images = get_posts($args);
            \WP_CLI::line('Uploading ' . count($images) . ' media images to Cloudinary.');
            if (count($images) > 0) {
                foreach ($images as $attachment) {
                    $upload = $this->uploadAttachment($attachment);
                    \WP_CLI::line(basename($attachment->guid) . ': ' . ($upload ? $upload : 'Uploaded.'));
                }
            }
        }

        public function uploadAttachment($attachment)
        {
            return $this->plugin->upload_to_cloudinary($attachment->ID, true);
        }

    }
}