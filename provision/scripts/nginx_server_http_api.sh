#!/bin/bash

SERVER_NAME=$1
USER_NAME=$2
SUDO_NEED=$3

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

VAGRANT_ROOT="/home/ubuntu"
PHP_VERSION=`php --ini | grep "Configuration File " | sed -e "s|.*:\s*||" | sed -e "s/[^.0-9]//g"`

echo "server {
    listen   80;
    server_name core.${SERVER_NAME};

    index index.php;
    root /var/www/${SERVER_NAME}/${USER_NAME}/public/api;

    access_log /var/log/nginx/core.${SERVER_NAME}.access.log;
    error_log /var/log/nginx/core.${SERVER_NAME}.error.log;

    keepalive_timeout 60;

    try_files \$uri \$uri/ @rewrite;

    location @rewrite {
        rewrite ^/(.*)$ /index.php?_url=/\$1;
    }

    location ^~ /.well-known {
        alias /var/www/letsencrypt/.well-known;
    }

    location = /favicon.ico {
        return 204;
        access_log     off;
        log_not_found  off;
    }

    location ~ \.php {
        try_files \$uri =404;

        add_header 'Access-Control-Allow-Origin' '*';
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS, PUT, DELETE, PATCH';
        add_header 'Access-Control-Allow-Headers' 'X-Requested-With,Accept,Content-Type, Origin';

        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_index /index.php;

        include /etc/nginx/fastcgi_params;

        fastcgi_param HTTPS off;

        fastcgi_split_path_info       ^(.+\.php)(/.+)$;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
    }

    location ~ /\.ht {
        deny all;
    }
}" | "$COMMAND_PREFIX" tee /etc/nginx/sites-available/core.${SERVER_NAME}

"$COMMAND_PREFIX" ln -s /etc/nginx/sites-available/core.${SERVER_NAME} /etc/nginx/sites-enabled/core.${SERVER_NAME}

"$COMMAND_PREFIX" systemctl restart nginx.service
