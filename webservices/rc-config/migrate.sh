#!/bin/bash
RC_PATH="/usr/share/roundcubemail/"
for plugin in `ls  $RC_PATH/plugins`; do
echo $plugin
if [ -f $RC_PATH/plugins/$plugin/config.inc.php ] ; then
mkdir /etc/machinet/roundcubemail/plugins/$plugin
mv $RC_PATH/plugins/$plugin/config.inc.php /etc/machinet/roundcubemail/plugins/$plugin
echo "creating symlink"
ln -s /etc/machinet/roundcubemail/plugins/$plugin/config.inc.php $RC_PATH"plugins/$plugin"/config.inc.php
fi

#cp $plugin/config.inc.php $RC_PATH/plugins/$plugin/
done
