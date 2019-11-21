#!/bin/bash

SERVER_NAME=$1
SUDO_NEED=$2
AURORA_LOGIN=$3
AURORA_PASSWORD=$4
SERVER_IP=$5

if [ "$SUDO_NEED" == true ]
    then COMMAND_PREFIX="sudo"
else
    COMMAND_PREFIX=""
fi

# Установка Beanstalkd:
"$COMMAND_PREFIX" apt-get install -y beanstalkd
"$COMMAND_PREFIX" echo "
## Defaults for the beanstalkd init script, /etc/init.d/beanstalkd on
## Debian systems.

BEANSTALKD_LISTEN_ADDR=127.0.0.1
BEANSTALKD_LISTEN_PORT=11300

# You can use BEANSTALKD_EXTRA to pass additional options. See beanstalkd(1)
# for a list of the available options. Uncomment the following line for
# persistent job storage.
BEANSTALKD_EXTRA=\"-b /var/lib/beanstalkd\"
# Start beanstalkd server at service/os boot or beanstalkd start/restart
START=yes" | "$COMMAND_PREFIX" tee /etc/default/beanstalkd
"$COMMAND_PREFIX" systemctl restart beanstalkd.service

# Установка Beanwalker:
"$COMMAND_PREFIX" apt-get install -y golang-go
"$COMMAND_PREFIX" mkdir /opt/go
export GOPATH=/opt/go
"$COMMAND_PREFIX" -E go get -u github.com/kadekcipta/beanwalker/...
"$COMMAND_PREFIX" mv /opt/go/bin/beanwalker /usr/local/bin/beanwalker
"$COMMAND_PREFIX" chmod +x /usr/local/bin/beanwalker

# Установка веб-консоли Aurora
"$COMMAND_PREFIX" mkdir /opt/beantalks_gui
cd /opt/beantalks_gui

"$COMMAND_PREFIX" wget https://github.com/xuri/aurora/releases/download/2.2/aurora_linux_i386_v2.2.tar.gz
"$COMMAND_PREFIX" tar -xvf aurora_linux_*
"$COMMAND_PREFIX" rm -r aurora_linux_*

# Настройка aurora.service:
"$COMMAND_PREFIX" touch /etc/systemd/system/aurora.service
"$COMMAND_PREFIX" echo "[Unit]
Description=Gui for beantalkd

[Service]
WorkingDirectory=/opt/beantalks_gui
ExecStart=/opt/beantalks_gui/aurora
Restart=always

[Install]
WantedBy=multi-user.target
" | "$COMMAND_PREFIX" tee /etc/systemd/system/aurora.service

"$COMMAND_PREFIX" systemctl daemon-reload
"$COMMAND_PREFIX" systemctl enable aurora.service
"$COMMAND_PREFIX" systemctl start aurora.service

"$COMMAND_PREFIX" echo "${AURORA_LOGIN}:`openssl passwd -apr1 ${AURORA_PASSWORD}`" | "$COMMAND_PREFIX" tee -a /etc/nginx/htpasswd.users

# Настройка nginx для работы с aurora из браузера.
"$COMMAND_PREFIX" touch /etc/nginx/sites-available/bean."$SERVER_NAME"
"$COMMAND_PREFIX" echo "server {
    listen 80;

    server_name bean.${SERVER_NAME};

    auth_basic \"Please login\";
    auth_basic_user_file /etc/nginx/htpasswd.users;

    location / {
        #fastcgi_param HTTPS on;
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_cache_bypass \$http_upgrade;
    }
}" | "$COMMAND_PREFIX" tee /etc/nginx/sites-available/bean."$SERVER_NAME"
"$COMMAND_PREFIX" ln -s /etc/nginx/sites-available/bean."$SERVER_NAME" /etc/nginx/sites-enabled/bean."$SERVER_NAME"
"$COMMAND_PREFIX" systemctl restart nginx.service
