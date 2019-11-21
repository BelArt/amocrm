#!/bin/bash

SUDO_NEED=$1

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

"$COMMAND_PREFIX" curl -sS https://getcomposer.org/installer | "$COMMAND_PREFIX" php
"$COMMAND_PREFIX" mv composer.phar /usr/local/bin/composer
"$COMMAND_PREFIX" chmod +x /usr/local/bin/composer
