#!/bin/bash

source ./bin/includes.sh

printf "Shutting down containers ... "

docker-compose down 2>/dev/null

printf "$(action_format "done")"
echo ""

# From: http://patorjk.com/software/taag/#p=display&c=echo&f=Standard&t=Cloudinary
echo "   ____ _                 _ _                        ";
echo "  / ___| | ___  _   _  __| (_)_ __   __ _ _ __ _   _ ";
echo " | |   | |/ _ \| | | |/ _\` | | '_ \ / _\` | '__| | | |";
echo " | |___| | (_) | |_| | (_| | | | | | (_| | |  | |_| |";
echo "  \____|_|\___/ \__,_|\__,_|_|_| |_|\__,_|_|   \__, |";
echo "                                               |___/ ";


echo "See you again soon, same bat time, same bat channel?"
echo ""
