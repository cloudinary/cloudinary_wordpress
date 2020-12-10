#!/bin/bash

source ./bin/includes.sh

# Grab full name of wordpress container
WORDPRESS_CONTAINER=$(docker ps | grep wordpress | awk '{print $1}')

if [[ '' == $WORDPRESS_CONTAINER ]]; then
	echo -e "$(error_message "The WordPress Docker container must be running!")"
	echo -e "Execute the following command: $(action_format "npm run env:start")"
	echo ""
	exit 0
fi

# From: http://patorjk.com/software/taag/#p=display&c=echo&f=Standard&t=Cloudinary
echo "   ____ _                 _ _                        ";
echo "  / ___| | ___  _   _  __| (_)_ __   __ _ _ __ _   _ ";
echo " | |   | |/ _ \| | | |/ _\` | | '_ \ / _\` | '__| | | |";
echo " | |___| | (_) | |_| | (_| | | | | | (_| | |  | |_| |";
echo "  \____|_|\___/ \__,_|\__,_|_|_| |_|\__,_|_|   \__, |";
echo "                                               |___/ ";

echo -e "Here comes the logs in real-time ... $(action_format "done")"
echo ""

docker-compose logs -f
