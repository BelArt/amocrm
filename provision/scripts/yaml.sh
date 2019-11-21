#!/bin/bash

SUDO_NEED=$1

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

PHP_VERSION=`php --ini | grep "Configuration File " | sed -e "s|.*:\s*||" | sed -e "s/[^.0-9]//g"`

# Установка парсера YAML
"$COMMAND_PREFIX" apt-get install -y libyaml-dev
echo '' | "$COMMAND_PREFIX" pecl install yaml
"$COMMAND_PREFIX" echo "extension=yaml.so" | "$COMMAND_PREFIX" tee -a `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||" | sed "s/.\{11\}$//" | sed "s/$/mods-available\/yaml.ini/"`
"$COMMAND_PREFIX" phpenmod -s cli yaml
"$COMMAND_PREFIX" phpenmod -s fpm yaml
"$COMMAND_PREFIX" systemctl restart php"$PHP_VERSION"-fpm.service
