<?php

function overwrite_transformations_featured_image() {
    register_meta( 'post', 'overwrite_transformations_featured_image', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'boolean',
    ) );
}

add_action( 'init', 'overwrite_transformations_featured_image' );
