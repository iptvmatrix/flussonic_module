#!/bin/bash
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color
module_name=net.iptvmatrix/flussonic_module
install_dir=/usr/local
repository=https://github.com/iptvmatrix/flussonic_module.git

mkdir -p $install_dir/$module_name &>/dev/null

if [ $? -ne 0 ] ; then
    printf "${RED}Can't create $install_dir/$module_name directory.\nAre you root? $NC\n"
    exit
fi

cp -R . $install_dir/$module_name
rm -rf $install_dir/$module_name/.git

cd $install_dir/$module_name
chmod -R a+x *.sh

printf "${GREEN}Success!\nPlease, add next line to your cron (crontab -e)$NC\n"
echo "*/5 * * * * cd $install_dir/$module_name && ./run_flussonic_matrix_daemon.sh > /dev/null
"
