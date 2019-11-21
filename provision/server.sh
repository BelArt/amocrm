#!/bin/bash

# Для установки ПО на сервер следует:
# 1. Запустить скрипт c локальной машины из корня проекта: `./provision/server.sh`

# Устанавливаем переменные для скриптов.
SERVER_NAME="mmco-expo.ru"
SERVER_IP="79.143.30.36"
USER_NAME="mmcoexpo"
USER_PASSWORD="3mXi7m37xVHuAHNv"
SERVER_TIMEZONE="Etc/UTC"
SCRIPTS_DIR="/tmp/scripts/"
SUDO_NEED=true
VAGRANT_ONLY=false
AURORA_LOGIN="aurora"
AURORA_PASSWORD="rUD4FwkX79vYkiLh"
SWAP_MEMORY=1024

# Копируем файлы для поднятие окружения на сервер.
scp -pr ./provision/scripts/ root@${SERVER_IP}:/tmp/

# 1.Установка базового ПО.
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}prepare.sh ${SERVER_NAME} ${USER_NAME} ${SUDO_NEED} ${SCRIPTS_DIR} ${SWAP_MEMORY} ${VAGRANT_ONLY}";

# 2. Создание пользователя
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}createuser.sh ${USER_NAME} ${USER_PASSWORD}";

# 3. Установка zsh для пользователя
/usr/bin/ssh ${USER_NAME}@${SERVER_IP} "${SCRIPTS_DIR}zsh_for_user.sh ${USER_NAME}";

# 4. PHP 7.3
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}php_7_3.sh ${SUDO_NEED}";

# 5. MongoDB
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}mongodb_4_0.sh ${SUDO_NEED} ${VAGRANT_ONLY}";

# 6. YAML
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}yaml.sh ${SUDO_NEED}";

# 7. Composer
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}composer.sh ${SUDO_NEED}";

# 8. Codecept
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}codecept.sh ${SUDO_NEED}";

# 9. Xdebug
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}xdebug.sh ${SUDO_NEED}";

# 10. Phalcon 3
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}phalcon_stable.sh ${SUDO_NEED}";

# 11. Redis
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}redis.sh ${SUDO_NEED}";

# 12. Beanstalkd, Beanwalker, Aurora
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}bean.sh ${SERVER_NAME} ${SUDO_NEED} ${AURORA_LOGIN} ${AURORA_PASSWORD}";

# 13. Nginx conf
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}nginx_conf.sh ${SUDO_NEED}";

# 14. Nginx sites-available HTTP
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}nginx_server_http_api.sh ${SERVER_NAME} ${USER_NAME} ${SUDO_NEED}";
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}nginx_server_http_gui.sh ${SERVER_NAME} ${USER_NAME} ${SUDO_NEED}";

# 15. Letsencrypt
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}letsencrypt.sh ${SUDO_NEED} ${SERVER_NAME} ${USER_NAME}";

# 16. Dhparam
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}dhparam.sh ${SUDO_NEED}";

# 17. Nginx sites-available HTTPS
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}nginx_server_https_api.sh ${SERVER_NAME} ${USER_NAME} ${SUDO_NEED}";
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}nginx_server_https_gui.sh ${SERVER_NAME} ${USER_NAME} ${SUDO_NEED}";

# 18. Fail2ban
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}fail2ban.sh ${SUDO_NEED} ${SCRIPTS_DIR}";

# 19. Monit
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}monit.sh ${SUDO_NEED} ${SERVER_NAME}";

# 20. Logrotate
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}logrotate.sh ${SUDO_NEED} ${SERVER_NAME}";

# 21. Ntp
/usr/bin/ssh root@${SERVER_IP} "${SCRIPTS_DIR}ntp.sh ${SUDO_NEED}";
