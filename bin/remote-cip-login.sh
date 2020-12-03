#!/bin/sh

echo "$(hostname);$PPID;$(date -R)" >> "$HOME/.remote-cip.session"
