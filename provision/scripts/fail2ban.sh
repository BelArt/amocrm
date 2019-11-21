#!/bin/bash

# https://www.8host.com/blog/zashhita-nginx-s-pomoshhyu-fail2ban-ubuntu-14-04/
# Для просмотра информации забанен ли кто: sudo fail2ban-client status nginx-http-auth
# Для разблокировки {{IP}}: sudo fail2ban-client set nginx-http-auth unbanip {{IP}}

SUDO_NEED=$1
SCRIPTS_DIR=$2

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

"$COMMAND_PREFIX" apt-get install -y fail2ban
"$COMMAND_PREFIX" cp "$SCRIPTS_DIR"jail.local /etc/fail2ban/jail.local

cd /etc/fail2ban/filter.d

"$COMMAND_PREFIX" cp apache-badbots.conf nginx-badbots.conf

"$COMMAND_PREFIX" touch nginx-noscript.conf
"$COMMAND_PREFIX" echo "[Definition]
failregex = ^<HOST> -.*GET.*(\.php|\.asp|\.exe|\.pl|\.cgi|\.scgi)
ignoreregex =" | "$COMMAND_PREFIX" tee nginx-noscript.conf

"$COMMAND_PREFIX" touch nginx-noproxy.conf
"$COMMAND_PREFIX" echo "[Definition]
failregex = ^<HOST> -.*GET http.*
ignoreregex =" | "$COMMAND_PREFIX" tee nginx-noproxy.conf

"$COMMAND_PREFIX" service fail2ban restart