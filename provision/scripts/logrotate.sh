#!/bin/bash

SUDO_NEED=$1
SERVER_NAME=$2

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

PHP_VERSION=`php --ini | grep "Configuration File " | sed -e "s|.*:\s*||" | sed -e "s/[^.0-9]//g"`

"$COMMAND_PREFIX" rm /etc/logrotate.d/php*
"$COMMAND_PREFIX" touch /etc/logrotate.d/app
"$COMMAND_PREFIX" echo "/var/www/${SERVER_NAME}/shared/log/*/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    copytruncate
    notifempty
}" | "$COMMAND_PREFIX" tee /etc/logrotate.d/app

"$COMMAND_PREFIX" touch /etc/logrotate.d/php
"$COMMAND_PREFIX" echo "/var/log/php/*.log {
    rotate 14
    weekly
    missingok
    notifempty
    compress
    delaycompress
    postrotate
        /usr/lib/php/php${PHP_VERSION}-fpm-reopenlogs
    endscript
}" | "$COMMAND_PREFIX" tee /etc/logrotate.d/php

"$COMMAND_PREFIX" touch /etc/logrotate.d/nginx
"$COMMAND_PREFIX" echo "/var/log/nginx/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
    prerotate
        if [ -d /etc/logrotate.d/httpd-prerotate ]; then \\
            run-parts /etc/logrotate.d/httpd-prerotate; \\
        fi \\
    endscript
    postrotate
        invoke-rc.d nginx rotate >/dev/null 2>&1
    endscript
}" | "$COMMAND_PREFIX" tee /etc/logrotate.d/nginx

"$COMMAND_PREFIX" touch /etc/logrotate.d/mongodb
"$COMMAND_PREFIX" echo "/var/log/mongodb/*.log {
    weekly
    missingok
    rotate 12
    compress
    dateext
    delaycompress
    copytruncate
    notifempty
}" | "$COMMAND_PREFIX" tee /etc/logrotate.d/mongodb
