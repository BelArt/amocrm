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
    server_name pannel.${SERVER_NAME};
    rewrite ^ https://\$server_name\$request_uri? permanent;
}

map \$http_upgrade \$connection_upgrade {
    default upgrade;
    '' close;
}

server {
    listen 443 ssl;
    server_name pannel.${SERVER_NAME};

    index index.php;
    root /var/www/${SERVER_NAME}/${USER_NAME}/public/api;

    access_log /var/log/nginx/pannel.${SERVER_NAME}.access.log;
    error_log /var/log/nginx/pannel.${SERVER_NAME}.error.log;

    client_max_body_size 30m;

    keepalive_timeout 60;
    resolver 8.8.8.8;
    ssl_stapling on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 24h;
    ssl_certificate /etc/letsencrypt/live/core.${SERVER_NAME}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/core.${SERVER_NAME}/privkey.pem;
    ssl_dhparam /etc/ssl/dhparam.pem;
    ssl_protocols TLSv1 TLSv1.1 TLSv1.2;
    ssl_ciphers kEECDH+AES128:kEECDH:kEDH:-3DES:kRSA+AES128:kEDH+3DES:DES-CBC3-SHA:!RC4:!aNULL:!eNULL:!MD5:!EXPORT:!LOW:!SEED:!CAMELLIA:!IDEA:!PSK:!SRP:!SSLv2;
    ssl_prefer_server_ciphers on;
    add_header Strict-Transport-Security 'max-age=604800';

    location ~ /ws/([0-9]+)/ {
        rewrite ^/ws/([0-9]+)/$ / break;
        # switch off logging
        access_log off;

        # redirect all HTTP traffic to localhost:8080
        proxy_pass http://127.0.0.1:\$1;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;

        # WebSocket support (nginx 1.4)
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection \"upgrade\";
    }

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

        fastcgi_param HTTPS on;

        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;

        fastcgi_read_timeout 200s;
        fastcgi_send_timeout 200s;
    }

    location ~ /\.ht {
        deny all;
    }
}" | "$COMMAND_PREFIX" tee /etc/nginx/sites-available/pannel.${SERVER_NAME}

"$COMMAND_PREFIX" ln -s /etc/nginx/sites-available/pannel.${SERVER_NAME} /etc/nginx/sites-enabled/pannel.${SERVER_NAME}

"$COMMAND_PREFIX" systemctl restart nginx.service
