#!/bin/bash

SUDO_NEED=$1
SERVER_NAME=$2

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

PHP_VERSION=`php --ini | grep "Configuration File " | sed -e "s|.*:\s*||" | sed -e "s/[^.0-9]//g"`

"$COMMAND_PREFIX" apt-get install -y monit

# Скрипт для отправки данных в лог.
"$COMMAND_PREFIX" touch /etc/monit/slack.shz
echo "echo \"\$HOST | \$MONIT_DATE | \$MONIT_SERVICE | \$MONIT_DESCRIPTION\" \
| tee /var/www/${SERVER_NAME}/shared/log/monit.log
"| "$COMMAND_PREFIX" tee /etc/monit/slack.sh
"$COMMAND_PREFIX" chmod +x /etc/monit/slack.sh

# Добавляем в monit filesystem
"$COMMAND_PREFIX" touch /etc/monit/conf.d/filesystem
echo "check filesystem rootfs with path /
    if failed permission 755 then exec \"/etc/monit/slack.sh\"
    if failed uid root then exec \"/etc/monit/slack.sh\"
    if inode usage > 85% then exec \"/etc/monit/slack.sh\"
    if space usage > 85% then exec \"/etc/monit/slack.sh\"
" | "$COMMAND_PREFIX" tee /etc/monit/conf.d/filesystem

# Добавляем в monit mongodb
"$COMMAND_PREFIX" touch /etc/monit/conf.d/mongodb
echo "check process mongodb
    with pidfile \"/var/lib/mongodb/mongod.lock\"
    start program = \"/bin/systemctl start mongod\"
    stop program = \"/bin/systemctl stop mongod\"
    if failed host 127.0.0.1 port 27017 then restart
    if 5 restart within 5 cycles then exec \"/etc/monit/slack.sh\"
" | "$COMMAND_PREFIX" tee /etc/monit/conf.d/mongodb

# Добавляем в monit nginx
"$COMMAND_PREFIX" touch /etc/monit/conf.d/nginx
echo "check process nginx with pidfile /run/nginx.pid
    start program = \"/bin/systemctl start nginx.service\"
    stop program = \"/bin/systemctl stop nginx.service\"
    if children > 250 then restart
    if loadavg(5min) greater than 10 for 8 cycles then stop
    if 3 restarts within 5 cycles then exec \"/etc/monit/slack.sh\"
    if changed pid then exec \"/etc/monit/slack.sh\"
    if failed host 127.0.0.1 port 80 then exec \"/etc/monit/slack.sh\"
" | "$COMMAND_PREFIX" tee /etc/monit/conf.d/nginx

# Добавляем в monit php
"$COMMAND_PREFIX" touch /etc/monit/conf.d/php
echo "### Monitoring UNIX socket php-fpm: the parent process.
check process php$PHP_VERSION-fpm with pidfile /var/run/php/php$PHP_VERSION-fpm.pid
    group phpcgi-unix
    start program = \"/bin/systemctl start php$PHP_VERSION-fpm\"
    stop program = \"/bin/systemctl stop php$PHP_VERSION-fpm\"
    if failed unixsocket /run/php/php$PHP_VERSION-fpm.sock then restart
    if 3 restarts within 5 cycles then exec \"/etc/monit/slack.sh\"
    depends on php$PHP_VERSION-fpm_bin
    depends on php$PHP_VERSION-fpm_init
    depends on nginx
## Test the php$PHP_VERSION-fpm binary.
check file php$PHP_VERSION-fpm_bin with path /usr/sbin/php-fpm$PHP_VERSION
   group phpcgi-unix
   if failed checksum then unmonitor
   if failed permission 755 then unmonitor
   if failed uid root then unmonitor
   if failed gid root then unmonitor
## Test the init scripts.
check file php$PHP_VERSION-fpm_init with path /etc/init.d/php$PHP_VERSION-fpm
   group phpcgi-unix
   if failed checksum then unmonitor
   if failed permission 755 then unmonitor
   if failed uid root then unmonitor
   if failed gid root then unmonitor
" | "$COMMAND_PREFIX" tee /etc/monit/conf.d/php

# Добавляем в monit redis
"$COMMAND_PREFIX" touch /etc/monit/conf.d/redis
echo "check process redis-server
    with pidfile \"/var/run/redis/redis-server.pid\"
    start program = \"/bin/systemctl start redis-server.service\"
    stop program = \"/bin/systemctl stop redis-server.service\"
    if 2 restarts within 3 cycles then exec \"/etc/monit/slack.sh\"
    if totalmem > 100 Mb then exec \"/etc/monit/slack.sh\"
    if children > 255 for 5 cycles then stop
    if cpu usage > 95% for 3 cycles then restart
    if failed host 127.0.0.1 port 6379 then restart
    if 5 restarts within 5 cycles then exec \"/etc/monit/slack.sh\"
" | "$COMMAND_PREFIX" tee /etc/monit/conf.d/redis

# Добавляем в monit rsyslog
"$COMMAND_PREFIX" touch /etc/monit/conf.d/rsyslog
echo "check process syslogd with pidfile /var/run/rsyslogd.pid
    group system
    start program = \"/bin/systemctl start rsyslog.service\"
    stop program = \"/bin/systemctl stop rsyslog.service\"
    if 5 restarts within 5 cycles then exec \"/etc/monit/slack.sh\"
" | "$COMMAND_PREFIX" tee /etc/monit/conf.d/rsyslog

# Добавляем в monit server
"$COMMAND_PREFIX" touch /etc/monit/conf.d/server
echo "check system server
    if loadavg (1min) > 4 then exec \"/etc/monit/slack.sh\"
    if loadavg (5min) > 2 then exec \"/etc/monit/slack.sh\"
    if loadavg (15min) > 1 then exec \"/etc/monit/slack.sh\"
    if memory usage > 85% then exec \"/etc/monit/slack.sh\"
    if swap usage > 50% for 4 cycles then exec \"/etc/monit/slack.sh\"
    if cpu usage (user) > 90% for 2 cycles then exec \"/etc/monit/slack.sh\"
    if cpu usage (system) > 20% for 2 cycles then exec \"/etc/monit/slack.sh\"
    if cpu usage (wait) > 80% for 2 cycles then exec \"/etc/monit/slack.sh\"
" | "$COMMAND_PREFIX" tee /etc/monit/conf.d/server

# Добавляем в monit sshd
"$COMMAND_PREFIX" touch /etc/monit/conf.d/sshd
echo "check process sshd with pidfile /var/run/sshd.pid
    start program = \"/bin/systemctl start sshd.service\"
    stop program = \"/bin/systemctl stop sshd.service\"
    if failed port 22 protocol ssh then restart
    if 5 restarts within 5 cycles then exec \"/etc/monit/slack.sh\"
" | "$COMMAND_PREFIX" tee /etc/monit/conf.d/sshd

# Добавляем в monit beanstalkd
"$COMMAND_PREFIX" touch /etc/monit/conf.d/beanstalkd
echo "check process beanstalkd with match beanstalkd
    start program = \"/bin/systemctl start beanstalkd.service\"
    stop program = \"/bin/systemctl stop beanstalkd.service\"
    if changed pid then exec \"/etc/monit/slack.sh\"
    if failed host 127.0.0.1 port 11300
        send \"stats\\r\\n\"
        expect \"OK [0-9]{1,}\\r\\n\"
    then restart
    if 5 restarts within 5 cycles then exec \"/etc/monit/slack.sh\"
" | "$COMMAND_PREFIX" tee /etc/monit/conf.d/beanstalkd

"$COMMAND_PREFIX" sed -i "s/# set httpd.*/  set httpd port 2812 and/" /etc/monit/monitrc
"$COMMAND_PREFIX" sed -i "s/#     allow localhost/      allow localhost/" /etc/monit/monitrc
"$COMMAND_PREFIX" sed -i "s/   include \/etc\/monit\/conf-enabled\/\*/#      include \/etc\/monit\/conf-enabled\/\*/" /etc/monit/monitrc

# Перезапуск сервиса
"$COMMAND_PREFIX" monit reload