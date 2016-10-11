#!/bin/bash
while true
do
    if !(ps aux | grep -q "[f]lussonic_main_loop_mtrx.php"); then 
        php flussonic_main_loop_mtrx.php  
    else 
        echo "Already runned" ; 
    fi
    sleep 1
done
