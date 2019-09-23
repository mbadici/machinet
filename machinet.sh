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
slackpkg install mariadb php-5.6
echo "download packages "
wget http://mihai.badici.ro/linux/machinet/slackware64-14.1/dovecot-2.2.36-x86_64-1_mb.tgz -P /tmp
wget http://mihai.badici.ro/linux/slackware64-14.1/openldap-server-2.4.31-x86_64-2.txz -P /tmp
wget http://mihai.badici.ro/linux/slackware64-14.1/postfix-3.3.1-x86_64-2_mb.tgz    -P  /tmp
installpkg /tmp/dovecot*mb.t*z
installpkg /tmp/postfix*mb.t*z
installpkg /tmp/openldap-server*.t*z
VERSION=SLACK
fi
fi


