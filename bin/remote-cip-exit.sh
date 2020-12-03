#!/bin/sh

sed -i "/^$(hostname);$PPID;/d" "$HOME/.remote-cip.session"
