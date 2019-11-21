#!/bin/bash

SUDO_NEED=$1

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

"$COMMAND_PREFIX" add-apt-repository --yes ppa:ondrej/php
"$COMMAND_PREFIX" apt-get update

"$COMMAND_PREFIX" apt-get install -y php7.3 php7.3-fpm php7.3-cli php7.3-common php7.3-curl php7.3-gd php7.3-json php7.3-mbstring php7.3-intl php7.3-mysql php7.3-xml php7.3-zip php7.3-bcmath php7.3-imap php7.3-sqlite3 php7.3-xmlrpc php7.3-dev php-pear
"$COMMAND_PREFIX" sed -i "s/.*listen\.mode.*/listen.mode = 0660/" /etc/php/7.3/fpm/pool.d/www.conf
"$COMMAND_PREFIX" sed -i "s/pm\.max_children \=.*/pm.max_children = 40/" /etc/php/7.3/fpm/pool.d/www.conf
"$COMMAND_PREFIX" sed -i "s/pm\.start_servers \=.*/pm.start_servers = 15/" /etc/php/7.3/fpm/pool.d/www.conf
"$COMMAND_PREFIX" sed -i "s/pm\.min_spare_servers \=.*/pm.min_spare_servers = 15/" /etc/php/7.3/fpm/pool.d/www.conf
"$COMMAND_PREFIX" sed -i "s/pm\.max_spare_servers \=.*/pm.max_spare_servers = 25/" /etc/php/7.3/fpm/pool.d/www.conf
"$COMMAND_PREFIX" sed -i "s/.*pm\.max_requests \=.*/pm.max_requests = 400/" /etc/php/7.3/fpm/pool.d/www.conf
"$COMMAND_PREFIX" sed -i "s/.*cgi\.fix_pathinfo\=.*/cgi.fix_pathinfo = 0/" /etc/php/7.3/fpm/php.ini
"$COMMAND_PREFIX" sed -i "s/.*date.timezone \=.*/date.timezone = UTC/" /etc/php/7.3/fpm/php.ini
"$COMMAND_PREFIX" sed -i "s/.*date.timezone \=.*/date.timezone = UTC/" /etc/php/7.3/cli/php.ini
"$COMMAND_PREFIX" sed -i "s/post_max_size = .*/post_max_size = 35M/" /etc/php/7.3/fpm/php.ini
"$COMMAND_PREFIX" sed -i "s/post_max_size = .*/post_max_size = 8M/" /etc/php/7.3/cli/php.ini
"$COMMAND_PREFIX" sed -i "s/upload_max_filesize = .*/upload_max_filesize = 30M/" /etc/php/7.3/fpm/php.ini
"$COMMAND_PREFIX" sed -i "s/upload_max_filesize = .*/upload_max_filesize = 8M/" /etc/php/7.3/cli/php.ini
"$COMMAND_PREFIX" sed -i "s/.*error_log \= syslog/error_log = \/var\/log\/php\/cli.error.log/" /etc/php/7.3/cli/php.ini
"$COMMAND_PREFIX" sed -i "s/.*error_log \= syslog/error_log = \/var\/log\/php\/fpm.error.log/" /etc/php/7.3/fpm/php.ini
"$COMMAND_PREFIX" sed -i "s/error_log \=.*/error_log = \/var\/log\/php\/fpm.error.log/" /etc/php/7.3/fpm/php-fpm.conf
"$COMMAND_PREFIX" systemctl restart php7.3-fpm.service
