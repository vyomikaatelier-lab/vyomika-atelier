#!/usr/bin/bash
# Invoke this file from exactly one Vyomika hPanel cron entry.
# The lock blocks overlapping runs only. A second entry in the same minute
# runs after the first exits and will execute the due tasks again.

set -u

if [ "${VYOMIKA_SCHEDULER_TEST:-}" = "1" ]; then
  app=${VYOMIKA_APP_DIR:?}
  php_bin=${VYOMIKA_PHP_BIN:?}
  lock=${VYOMIKA_LOCK_FILE:?}
  log=${VYOMIKA_SCHEDULER_LOG:?}
  flock_bin=${VYOMIKA_FLOCK_BIN:-/usr/bin/flock}
  date_bin=${VYOMIKA_DATE_BIN:-/usr/bin/date}
  stat_bin=${VYOMIKA_STAT_BIN:-/usr/bin/stat}
  log_limit=${VYOMIKA_LOG_MAX_BYTES:-5242880}
else
  app=/home/u550969814/vyomika-atelier
  php_bin=/opt/alt/php83/usr/bin/php
  lock=$app/storage/framework/scheduler.lock
  log=$app/storage/logs/scheduler.log
  flock_bin=/usr/bin/flock
  date_bin=/usr/bin/date
  stat_bin=/usr/bin/stat
  log_limit=5242880
fi

umask 077

stamp() {
  "$date_bin" -Is 2>/dev/null || printf '%s' "unknown-time"
}

lock_error() {
  printf '%s lock-error %s\n' "$(stamp)" "$1" >&2
  exit 73
}

if [ ! -x "$flock_bin" ] || [ ! -x "$date_bin" ] || [ ! -x "$stat_bin" ]; then
  printf '%s lock-error missing-tool\n' "$(stamp)" >&2
  exit 73
fi

if ! exec 9>>"$lock"; then
  lock_error "open-failed"
fi

flock_status=0
"$flock_bin" -n -E 75 9 || flock_status=$?
if [ "$flock_status" -eq 75 ]; then
  printf '%s lock-contention\n' "$(stamp)" >&2
  exit 75
fi
if [ "$flock_status" -ne 0 ]; then
  lock_error "flock-failed exit=${flock_status}"
fi

chmod 600 "$lock" || lock_error "chmod-failed"
cd "$app" || lock_error "chdir-failed"

if [ -f "$log" ]; then
  log_size=$("$stat_bin" -c %s "$log") || lock_error "stat-failed"
  if [ "$log_size" -gt "$log_limit" ]; then
    mv -f "$log" "$log.1" || lock_error "rotate-failed"
    chmod 600 "$log.1" || lock_error "chmod-failed"
  fi
fi

printf '%s start\n' "$(stamp)" >> "$log" || lock_error "log-write-failed"
chmod 600 "$log" || lock_error "chmod-failed"

set +e
"$php_bin" artisan schedule:run >> "$log" 2>&1
scheduler_status=$?
set -e

if ! printf '%s end exit=%s\n' "$(stamp)" "$scheduler_status" >> "$log"; then
  printf '%s lock-error log-write-failed\n' "$(stamp)" >&2
  if [ "$scheduler_status" -ne 0 ]; then
    exit "$scheduler_status"
  fi
  exit 73
fi

chmod 600 "$log" || true
exit "$scheduler_status"
