#!/bin/bash
runtime=0
starttime=0
while true
do
    if !(ps aux | grep -q "[f]lussonic_main_loop_mtrx.php"); then
        runtime=0
        starttime=$(date +"%s")
        echo "RUN"

        php flussonic_main_loop_mtrx.php &
    else
        now=$(date +"%s")
        runtime=`expr $now - $starttime`
        if [ "$runtime" -gt 120 ]; then
            kill $(ps aux | grep '[f]lussonic_main_loop_mtrx.php' | awk '{print $2}')
        fi
        echo "Already runned" ;
    fi
    sleep 1
done
