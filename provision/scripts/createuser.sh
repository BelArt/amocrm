#!/bin/bash

USER_NAME=$1
USER_PASSWORD=$2

# Создаём пользователя для редактирования по ftp директории /var/www:
adduser --quiet --disabled-password --gecos "First Last,RoomNumber,WorkPhone,HomePhone" ${USER_NAME} --home /var/www
adduser ${USER_NAME} www-data
adduser ${USER_NAME} sudo
echo "${USER_NAME}:$USER_PASSWORD" | chpasswd
mkdir /var/www/.ssh
cp ~/.ssh/authorized_keys /var/www/.ssh/authorized_keys
chown -R ${USER_NAME}:www-data /var/www
chmod -R g+rwX /var/www
chmod go-w /var/www
chmod 700 /var/www/.ssh
chmod 600 /var/www/.ssh/authorized_keys
