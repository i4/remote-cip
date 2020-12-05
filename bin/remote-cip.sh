#!/bin/bash
set -f
CIPSSHKEY="$HOME/.ssh/remote-cip"
SESSIONFILE="$HOME/.remote-cip.session"
HOST=$(hostname)

if [[ $# -ge 1 ]] ; then
	action=$1
fi
if [[ $# -eq 3 ]] ; then
	host=$2
	pid=$3
	
	if [[ $host != $HOST ]] ; then
		ssh $host -i $CIPSSHKEY -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no "$0" "$action" "$host" "$pid" 2>/dev/null
		exit $?
	elif ! grep "^$host;$pid;" $SESSIONFILE >/dev/null 2>&1 ; then
		echo -e "\e[31mNo entry of \e[31;1m$host (PID $pid)\e[0;31m in session record\e[0m"
		exit 1
	fi
fi

mkdir -p $(dirname $CIPSSHKEY)
if [[ ! -f $CIPSSHKEY ]] ; then
	echo "Creating new Remote CIP SSH key..."
	ssh-keygen -f $CIPSSHKEY -N ""
	cat $CIPSSHKEY.pub >> $HOME/.ssh/authorized_keys
fi

if [[ $action == "status" ]] ; then
	if kill -0 $pid 2>/dev/null ; then
		echo -e "\e[32mactive\e[0m"
	else
		echo -e "\e[31mclosed\e[0m"
		sed -i "/^$HOST;$pid;/d" "$SESSIONFILE" >/dev/null 2>&1
	fi
elif [[ $action == "close" ]] ; then
	if kill $pid 2>/dev/null ; then
		echo -e "\e[32msession closed\e[0m"
	else
		echo -e "\e[31minvalid session / already closed\e[0m"
	fi
	sed -i "/^$HOST;$pid;/d" "$SESSIONFILE" >/dev/null 2>&1
elif [[ $action == "list" ]] ; then
	active=0
	echo "Active Remote CIP Sessions:"
	if [[ -f "$SESSIONFILE" ]] ; then
		lines=$(<$SESSIONFILE)
		while IFS=$'\n' read line ; do
			if [[ $line =~ ^([a-z0-9]+)\;([0-9]+)\;(.*)$ ]] ; then
				echo -e " [$($0 status "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}" || echo -e "\e[33munknown\e[0m")] ${BASH_REMATCH[1]} (PID ${BASH_REMATCH[2]}) \e[2mstarted ${BASH_REMATCH[3]}\e[0m" &
				active=$((active + 1))
			fi
		done <<< "$lines"
		wait
	fi

	if [[ $active -eq 0 ]] ; then
		echo "(none)"
	else
		echo "($active sessions)"
		echo
		echo "Stop session with"
		echo "   $0 close [HOST] [PID]"
	fi
else 
	echo "Remote CIP Session Manager"
	echo
	echo "Usage:"
	echo " $0 [help|list]"
	echo " $0 [status|close] [host] [pid]"
fi
