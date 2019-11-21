#!/bin/bash

SUDO_NEED=$1

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

"$COMMAND_PREFIX" timedatectl set-ntp no
"$COMMAND_PREFIX" apt-get install -y ntp
"$COMMAND_PREFIX" echo "
pool 0.au.pool.ntp.org iburst
pool 1.au.pool.ntp.org iburst
pool 2.au.pool.ntp.org iburst
pool 3.au.pool.ntp.org iburst
" | "$COMMAND_PREFIX" tee -a /etc/ntp.conf
"$COMMAND_PREFIX" service ntp restart
