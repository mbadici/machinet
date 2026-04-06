#!/bin/bash
RC_PATH="/usr/share/roundcubemail/"
cp -r plugins /etc/machinet/roundcubemail/

for plugin in `ls .`; do
echo $plugin

#if [ -f $RC_PATH/plugins/$plugin/config.inc.php ] ; then
#mkdir /etc/machinet/roundcubemail/plugins/$plugin

ln -s /etc/machinet/roundcubemail/plugins/$plugin/config.inc.php $RC_PATH"plugins/$plugin"config.inc.php


#cp $plugin/config.inc.php $RC_PATH/plugins/$plugin/
done
