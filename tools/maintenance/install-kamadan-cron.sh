#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-$(command -v php)}"
LOG_DIR="$ROOT/storage/logs"
mkdir -p "$LOG_DIR"
LINE="* * * * * cd $ROOT && $PHP_BIN tools/maintenance/collect-kamadan.php --quiet >> $LOG_DIR/kamadan-collector.log 2>&1"
( crontab -l 2>/dev/null | grep -v 'tools/maintenance/collect-kamadan.php' || true; echo "$LINE" ) | crontab -
echo "Kamadan collector cron geïnstalleerd:"
echo "$LINE"
