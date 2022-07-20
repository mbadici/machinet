#!/bin/bash
RC_PATH="/usr/share/roundcubemail/"
for plugin in `ls -d */`; do
echo $plugin

cp $plugin/config.inc.php $RC_PATH/plugins/$plugin/
done
