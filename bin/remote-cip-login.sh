#!/bin/sh

# Disable Logout Beep at XFCE
xset -b

echo "$(hostname);$PPID;$(date -R)" >> "$HOME/.remote-cip.session"
