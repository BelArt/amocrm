#!/bin/bash

SUDO_NEED=$1

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

"$COMMAND_PREFIX" rm /etc/nginx/sites-enabled/default

echo "user www-data;
worker_processes 2;
timer_resolution 100ms;
worker_rlimit_nofile 131072;
worker_priority -5;
pid /run/nginx.pid;
events {
    worker_connections 16384;
    multi_accept on;
    use epoll;
}
http {
    ##
    # Basic Settings
    ##

    sendfile on;
    disable_symlinks off;
    tcp_nopush on;
    tcp_nodelay on;
    types_hash_max_size 2048;
    server_tokens off;
    expires off;

    client_max_body_size 32M;
    client_body_buffer_size 10K;
    client_header_buffer_size 1k;
    large_client_header_buffers 4 8k;

    client_body_timeout 12;
    client_header_timeout 12;
    keepalive_timeout 40;
    keepalive_requests 100;
    send_timeout 20;
    reset_timedout_connection on;

    # server_names_hash_bucket_size 64;
    # server_name_in_redirect off;

    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    ##
    # Logging Settings
    ##

    #access_log /var/log/nginx/access.log;
    #error_log /var/log/nginx/error.log;
    access_log off;

    # Caches information about open FDs, freqently accessed files.
    open_file_cache max=200000 inactive=20s;
    open_file_cache_valid 30s;
    open_file_cache_min_uses 2;
    open_file_cache_errors on;

    ##
    # Gzip Settings
    ##
    gzip on;
    gzip_proxied any;
    gzip_comp_level 3;
    gzip_static on;
    gzip_types
    text/css
    text/plain
    text/json
    text/x-js
    text/javascript
    text/xml
    application/json
    application/x-javascript
    application/xml
    application/xml+rss
    application/javascript
    application/x-font-ttf
    application/x-font-opentype
    application/vnd.ms-fontobject
    image/svg+xml
    image/x-icon
    application/atom_xml;
    gzip_min_length 1024;
    gzip_disable \"msie6\";
    gzip_vary on;

    ##
    # Virtual Host Configs
    ##

    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}" | "$COMMAND_PREFIX" tee /etc/nginx/nginx.conf

"$COMMAND_PREFIX" systemctl restart nginx
