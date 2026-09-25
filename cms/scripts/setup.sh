#!/usr/bin/env bash
# Instala y configura WordPress. Uso: docker compose run --rm cli bash /scripts/setup.sh
set -euo pipefail
URL="${SITE_URL:-http://localhost:8088}"

until wp db check --quiet >/dev/null 2>&1; do sleep 2; done

if ! wp core is-installed 2>/dev/null; then
  ADMIN_PASS="$(php -r 'echo bin2hex(random_bytes(12));')"
  wp core install --url="$URL" --title="Fundación Enciende una Luz" \
    --admin_user="enciluz-admin" --admin_password="$ADMIN_PASS" \
    --admin_email="fundacionenciluz@gmail.com" --skip-email
  echo "======================================================"
  echo " Admin: enciluz-admin   Clave: $ADMIN_PASS"
  echo " Guárdala ahora: no se vuelve a mostrar."
  echo "======================================================"
  # Contenido de ejemplo de WordPress fuera (solo en la instalación inicial)
  wp post delete 1 2 3 --force 2>/dev/null || true
fi

wp language core install es_ES --activate || true
wp option update timezone_string "America/Caracas"
wp option update date_format "j \\d\\e F \\d\\e Y"
wp option update blogdescription "Fundación de Bienestar Social"
wp option update default_comment_status closed
wp option update default_ping_status closed
wp option update users_can_register 0
wp rewrite structure '/%postname%/'

wp theme activate enciluz
for t in $(wp theme list --field=name --status=inactive); do wp theme delete "$t"; done
for p in akismet hello hello-dolly; do wp plugin delete "$p" 2>/dev/null || true; done

wp plugin install two-factor wps-hide-login limit-login-attempts-reloaded --activate
wp option update whl_page "acceso-enciluz"
wp option update whl_redirect_admin "404"

if ! wp user get cliente >/dev/null 2>&1; then
  CLI_PASS="$(php -r 'echo bin2hex(random_bytes(10));')"
  wp user create cliente fundacionenciluz+editor@gmail.com --role=editor --user_pass="$CLI_PASS" --display_name="Equipo ENCILUZ"
  echo " Editor: cliente   Clave: $CLI_PASS"
fi


if [ -f /seed/content.json ] && [ -f /scripts/seed.php ]; then
  wp eval-file /scripts/seed.php
fi
wp rewrite flush
cp /scripts/htaccess /var/www/html/.htaccess
mkdir -p /var/www/html/wp-content/uploads && cp /scripts/uploads-htaccess /var/www/html/wp-content/uploads/.htaccess
rm -f /var/www/html/readme.html /var/www/html/license.txt /var/www/html/wp-config-sample.php
echo "Listo: $URL  (login: $URL/acceso-enciluz/)"
