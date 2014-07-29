#!/bin/bash

# Base PRE Setup

GITDIR="/tmp/git"
ENGINEAPIGIT="https://github.com/wvulibraries/engineAPI.git"
ENGINEBRANCH="master"
ENGINEAPIHOME="/home/engineAPI"

SERVERURL="/home/systems.lib.wvu.edu"
DOCUMENTROOT="public_html"

yum -y install httpd httpd-devel httpd-manual httpd-tools \
               mysql-connector-java mysql-connector-odbc mysql-devel mysql-lib mysql-server \
               php php-bcmath php-cli php-common php-gd php-ldap php-mbstring php-mcrypt php-mysql php-odbc php-pdo php-pear php-pear-Benchmark php-pecl-apc php-pecl-imagick php-pecl-memcache php-soap php-xml php-xmlrpc php-phpunit-PHPUnit* php-phpunit-DbUnit \
               emacs emacs-common emacs-nox nano \
               git wget http://prdownloads.sourceforge.net/webadmin/webmin-1.670-1.noarch.rpm \

/etc/init.d/httpd start
chkconfig httpd on

mkdir -p $GITDIR
cd $GITDIR
git clone -b $ENGINEBRANCH $ENGINEAPIGIT
git clone https://github.com/wvulibraries/engineAPITemplates.git

mkdir -p $SERVERURL/phpincludes/
mkdir -p $SERVERURL/public_html/
ln -s $GITDIR/engineAPI/engine/ $SERVERURL/phpincludes/

## Setup the EngineAPI Database
/etc/init.d/mysqld start
chkconfig mysqld on
mysql -u root < /tmp/git/engineAPI/sql/vagrantSetup.sql
mysql -u root < /tmp/git/engineAPI/sql/EngineAPI.sql

/etc/init.d/httpd restart
/etc/init.d/mysqld restart
