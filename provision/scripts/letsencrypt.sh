#!/bin/bash

SUDO_NEED=$1
SERVER_NAME=$2
USER_NAME=$3

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

"$COMMAND_PREFIX" mkdir -p /var/www/letsencrypt/.well-known
"$COMMAND_PREFIX" mkdir /root/letsencrypt-config/
"$COMMAND_PREFIX" touch /root/letsencrypt-config/${USER_NAME}.ini
"$COMMAND_PREFIX" chown -R "$USER_NAME":www-data /var/www/letsencrypt
"$COMMAND_PREFIX" chmod -R g+rwX /var/www/letsencrypt
"$COMMAND_PREFIX" apt-get install -y letsencrypt
"$COMMAND_PREFIX" echo "
# this is the let's Encrypt config for our gitlab instance
# use the webroot authenticator.
authenticator = webroot
# the following path needs to be served by our webserver
# to validate our domains
webroot-path = /var/www/letsencrypt
# generate certificates for the specified domains.
domains = core.${SERVER_NAME},pannel.${SERVER_NAME}
# register certs with the following email address
email = philippov.snder@gmail.com
# use a 4096 bit RSA key instead of 2048
rsa-key-size = 4096
expand = True
" | "$COMMAND_PREFIX" tee -a /root/letsencrypt-config/${USER_NAME}.ini
"$COMMAND_PREFIX" letsencrypt certonly -c /root/letsencrypt-config/${USER_NAME}.ini
crontab -l | { cat; echo "30 2 * * 1 /usr/bin/letsencrypt renew >> /var/log/le-renew.log"; } | crontab -
crontab -l | { cat; echo "35 2 * * 1 /bin/systemctl reload nginx"; } | crontab -



