#!/bin/bash

SUDO_NEED=$1

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

"$COMMAND_PREFIX" openssl dhparam -out /etc/ssl/dhparam.pem 4096
