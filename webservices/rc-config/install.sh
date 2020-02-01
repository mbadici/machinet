#!/bin/bash
RC_PATH="/usr/share/roundcubemail/"
for conf in `ls *.cfg`; do
echo $conf
plug="${conf%.*}"
echo $plug
cp $conf $RC_PATH/plugins/$plug/config.inc.php
done
