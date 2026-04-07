#!/usr/bin/env bash
# =============================================================
#  TaskOrbit — entrypoint
#  Substituye el placeholder __APP_BASE_PATH__ en el vhost de
#  Apache antes de arrancar, para que la app viva en el subpath
#  configurado por la variable APP_BASE_PATH.
#
#  ej: APP_BASE_PATH=/taskorbit  ->  http://host:port/taskorbit
# =============================================================
set -e

# Default si no se inyecta nada
APP_BASE_PATH="${APP_BASE_PATH:-/taskorbit}"

# Normalizar: debe iniciar con / y NO terminar con /
APP_BASE_PATH="/$(echo "$APP_BASE_PATH" | sed 's:^/*::; s:/*$::')"

VHOST="/etc/apache2/sites-available/000-default.conf"

if [ -f "$VHOST" ]; then
  # Reset al template original (re-copiando del source montado en build)
  if [ -f "/etc/apache2/sites-available/000-default.conf.tpl" ]; then
    cp /etc/apache2/sites-available/000-default.conf.tpl "$VHOST"
  fi
  sed -i "s|__APP_BASE_PATH__|${APP_BASE_PATH}|g" "$VHOST"
  echo "[entrypoint] Apache vhost configurado con APP_BASE_PATH=${APP_BASE_PATH}"
fi

# Asegurar permisos de storage en cada arranque (por si bind-mount los reseteo)
if [ -d /var/www/html/storage ]; then
  chown -R www-data:www-data /var/www/html/storage 2>/dev/null || true
  chmod -R 775 /var/www/html/storage 2>/dev/null || true
fi

exec apache2-foreground
