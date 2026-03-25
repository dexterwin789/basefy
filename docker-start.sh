#!/usr/bin/env bash
set -euo pipefail

PORT_TO_USE="${PORT:-80}"

# Garante apenas um MPM ativo (evita AH00534: More than one MPM loaded)
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf
rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

if grep -qE '^Listen ' /etc/apache2/ports.conf; then
  sed -ri "s/^Listen\s+[0-9]+/Listen ${PORT_TO_USE}/" /etc/apache2/ports.conf
else
  echo "Listen ${PORT_TO_USE}" >> /etc/apache2/ports.conf
fi

sed -ri "s#<VirtualHost \*:[0-9]+>#<VirtualHost *:${PORT_TO_USE}>#" /etc/apache2/sites-available/000-default.conf

apache2ctl -t

exec apache2-foreground
