#!/bin/bash

SUDO_NEED=$1

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

"$COMMAND_PREFIX" curl -LsS https://codeception.com/codecept.phar -o /usr/local/bin/codecept
"$COMMAND_PREFIX" chmod a+x /usr/local/bin/codecept