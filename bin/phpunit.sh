#!/bin/bash

if [[ $@ ]]; then
    docker-compose run --rm -u 1000 --workdir=/var/www/html/wp-content/plugins/cloudinary wordpress -- composer run test-coverage
else
    docker-compose run --rm -u 1000 --workdir=/var/www/html/wp-content/plugins/cloudinary wordpress -- composer run test
fi
