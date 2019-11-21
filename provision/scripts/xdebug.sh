#!/bin/bash

SUDO_NEED=$1

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

PHP_VERSION=`php --ini | grep "Configuration File " | sed -e "s|.*:\s*||" | sed -e "s/[^.0-9]//g"`

"$COMMAND_PREFIX" apt-get install -y php-xdebug

declare -A EXTENSION_DIR
EXTENSION_DIR["5.6"]="/usr/lib/php/20131226/xdebug.so"
EXTENSION_DIR["7.0"]="/usr/lib/php/20151012/xdebug.so"
EXTENSION_DIR["7.1"]="/usr/lib/php/20160303/xdebug.so"
EXTENSION_DIR["7.2"]="/usr/lib/php/20170718/xdebug.so"
EXTENSION_DIR["7.3"]="/usr/lib/php/20180731/xdebug.so"

"$COMMAND_PREFIX" echo "zend_extension=${EXTENSION_DIR["$PHP_VERSION"]}
xdebug.remote_enable = 1
xdebug.remote_connect_back = 1
xdebug.scream=0
xdebug.cli_color=1
xdebug.show_local_vars=1
xdebug.remote_log = /var/log/xdebug_remote.log

xdebug.var_display_max_depth = 5
xdebug.var_display_max_children = 256
xdebug.var_display_max_data = 1024" | "$COMMAND_PREFIX" tee `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||" | sed "s/.\{11\}$//" | sed "s/$/mods-available\/xdebug.ini/"`
