#!/bin/bash
if pidof -x "flussonic_matrix_daemon.sh" >/dev/null; then 
    echo "exit"
    exit
fi

echo "run"
./flussonic_matrix_daemon.sh
