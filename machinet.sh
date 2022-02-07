#!/bin/bash
which apt-get >/dev/null
if [ $? == 0 ]; then
echo "debian based system"
apt-get install postfix  postfix-ldap
apt-get  install dovecot-core dovecot-ldap  dovecot-sieve dovecot-managesieved dovecot-imapd dovecot-pop3d dovecot-lmtpd
apt-get  install  dovecot-dev
apt-get  install ldap-server  ldap-client 
apt-get install php5-ldap  smarty  mysql-server mysql-client php5-mysql
else 
which slackpkg
if [ $? == 0 ]; then
echo "this is a slackware system, congrats"
slackpkg update
slackpkg install mysql php
mysql_install_db; chown -R mysql:mysql /var/lib/mysql
chmod +x /etc/rc.d/rc.mysqld 
/etc/rc.d/rc.mysqld start


echo "download packages "
#wget http://mihai.badici.ro/linux/machinet/slackware64-14.1/dovecot-2.2.36-x86_64-1_mb.tgz -P /tmp
#wget http://mihai.badici.ro/linux/slackware64-14.1/openldap-server-2.4.31-x86_64-2.txz -P /tmp
#wget http://mihai.badici.ro/linux/slackware64-14.1/postfix-3.3.1-x86_64-2_mb.tgz    -P  /tmp
#installpkg /tmp/dovecot*mb.t*z
#installpkg /tmp/postfix*mb.t*z
#installpkg /tmp/openldap-server*.t*z
#we need lessc and composer for roundcube:
https://cdn.jsdelivr.net/npm/less@4
mv less@4 /usr/local/bin/lessc

php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === '906a84df04cea2aa72f40b5f787e49f22d4c2f19492ac310e8cba5b96ac8b64115ac402c8cd292b8a03482574915d1a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"

slackpkg install postfix
chmod +x /etc/rc.d/rc.postfix
/etc/rc.d/rc.postfix start
slackpkg install dovecot
chmod +x /etc/rc.d/rc.dovecot
/etc/rc.d/rc.dovecot start

slackp	 install openldap
chmod +x /etc/rc.d/rc.openldap
/etc/rc.d/rc.openldap start

VERSION=SLACK
else

which yum >/dev/null
if [ $? == 0 ]; then
echo "centos system"
yum install postfix 
yum  install dovecot
yum  install  dovecot-dev
yum  install openldap  openldap-clients
yum install   mariadb-server mysql-client php

fi


fi


fi

mkdir /etc/machinet
