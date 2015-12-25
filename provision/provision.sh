#!/bin/sh
set -e

# Install MySQL
sudo apt-get install mysql-server mysql-client

# Install PHP 5.6.*
sudo add-apt-repository ppa:ondrej/php5-5.6
sudo apt-get update
sudo apt-get install php5-cli php5-fpm
sudo apt-get install php5-mysql

# Install Nginx
sudo apt-get install nginx

# Install git
sudo apt-get install git

# Install curl
sudo apt-get install curl

# Install Composer
curl -sS 'https://getcomposer.org/installer' | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Install Bower
sudo apt-get install nodejs npm
ln -s '/usr/bin/nodejs' '/usr/bin/node'
sudo npm install -g bower

# Install Java 8
sudo add-apt-repository ppa:webupd8team/java
sudo apt-get update
sudo apt-get install oracle-java8-installer
