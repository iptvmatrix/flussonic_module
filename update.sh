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
#Remove configs and temporary files
rm -rf App/tmp
rm -rf config
rm -rf .git

cp -R . $install_dir/$module_name

cd $install_dir/$module_name
chmod -R a+x *.sh

printf "${GREEN}Success!$NC\n"
