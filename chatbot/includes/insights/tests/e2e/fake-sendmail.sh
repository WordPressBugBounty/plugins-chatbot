#!/bin/sh
# Test-only stand-in for sendmail: saves each message as a file in
# $QCIN_MAIL_DIR (default: /tmp/qcin-mail) instead of sending it.
DIR="${QCIN_MAIL_DIR:-/tmp/qcin-mail}"
mkdir -p "$DIR"
cat > "$DIR/$(date +%s%N).eml"
exit 0
