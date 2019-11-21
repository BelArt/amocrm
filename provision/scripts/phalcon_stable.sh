#!/bin/bash

SUDO_NEED=$1

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

PHP_VERSION=`php --ini | grep "Configuration File " | sed -e "s|.*:\s*||" | sed -e "s/[^.0-9]//g"`

"$COMMAND_PREFIX" curl -s "https://packagecloud.io/install/repositories/phalcon/stable/script.deb.sh" | "$COMMAND_PREFIX" bash
"$COMMAND_PREFIX" apt-get install -y php"$PHP_VERSION"-phalcon
"$COMMAND_PREFIX" systemctl restart php"$PHP_VERSION"-fpm.service
