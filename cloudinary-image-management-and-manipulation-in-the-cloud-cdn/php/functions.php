<?php

function myguten_register_meta() {
    register_meta( 'post', 'overwrite_transformations_featured_image', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'boolean',
    ) );
}

add_action( 'init', 'myguten_register_meta' );