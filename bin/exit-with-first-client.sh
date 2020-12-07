#!/bin/bash

LOGFILE="${XPRA_LOG_DIR}/${DISPLAY}.log"

while [[ ! -f "$LOGFILE" ]] ; do
	sleep 1
done

while ! grep 'xpra client 1 disconnected.$' "$LOGFILE" >/dev/null 2>&1 ; do
	sleep 1
done

echo "First client quit - shutting down..."

if ! /local/xpra-4.0.X/bin/xpra stop >/dev/null 2>&1 ; then
	echo "Force quit of $PPID"
	kill -9 $PPID
fi

