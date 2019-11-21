#!/bin/bash

# Устанавливаем zsh для пользователя:
wget https://github.com/robbyrussell/oh-my-zsh/raw/master/tools/install.sh -O - | zsh
chsh -s `which zsh`
mkdir ~/.dotfiles
cp -rp /tmp/scripts/dotfiles/*  ~/.dotfiles/
ln -fs ~/.dotfiles/zshrc ~/.zshrc
ln -fs ~/.dotfiles/curlrc ~/.curlrc
ln -fs ~/.dotfiles/inputrc ~/.inputrc
