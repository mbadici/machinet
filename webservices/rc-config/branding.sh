#!/bin/bash  

RC_PATH="/usr/share/roundcubemail/"
cp machinet-logo.svg $RC_PATH/skins/elastic/images/logo.svg
chown www-data:www-data $RC_PATH/skins/elastic/images/logo.svg

