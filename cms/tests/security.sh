#!/usr/bin/env bash
# Pruebas de seguridad de WordPress. Uso: bash cms/tests/security.sh [URL_BASE]
# Requiere que el stack esté arriba (docker compose up -d) para las pruebas con wp-cli.
set -u
B="${1:-http://localhost:8088}"
DIR="$(cd "$(dirname "$0")/.." && pwd)"
fail=0; n=0
ok()   { n=$((n+1)); echo "OK   $1"; }
bad()  { n=$((n+1)); fail=$((fail+1)); echo "FAIL $1 — $2"; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
expect_code() { local got; got="$(code "${@:3}")"; [[ "$got" =~ ^($2)$ ]] && ok "$1" || bad "$1" "esperado $2, obtenido $got"; }

# Enumeración de usuarios
expect_code "API de usuarios bloqueada"            "401|403|404" "$B/wp-json/wp/v2/users"
expect_code "API de usuarios (?rest_route) bloqueada" "401|403|404" "$B/?rest_route=/wp/v2/users"
expect_code "?author=1 no enumera"                  "404"         "$B/?author=1"
body="$(curl -s "$B/wp-json/")"
grep -q '"/wp/v2/users"' <<<"$body" && bad "índice REST sin rutas de usuarios" "aparece /wp/v2/users" || ok "índice REST sin rutas de usuarios"

# XML-RPC
r="$(curl -s -X POST -H 'Content-Type: text/xml' -d '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>' "$B/xmlrpc.php")"
grep -q 'wp.getUsersBlogs' <<<"$r" && bad "XML-RPC deshabilitado" "expone métodos" || ok "XML-RPC deshabilitado"

# Login oculto
got="$(code "$B/wp-login.php")"; [ "$got" != "200" ] && ok "wp-login.php no expuesto ($got)" || bad "wp-login.php no expuesto" "responde 200"
loc="$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$B/wp-admin/")"
grep -qE '^200|acceso-enciluz' <<<"$loc" && bad "wp-admin no revela el login" "$loc" || ok "wp-admin no revela el login ($loc)"
expect_code "login real en /acceso-enciluz/"        "200"         "$B/acceso-enciluz/"

# Versión y huellas
html="$(curl -s "$B/")"
grep -qi 'name="generator"' <<<"$html" && bad "sin meta generator" "presente" || ok "sin meta generator"
grep -q '?ver=[0-9]\.[0-9]' <<<"$html" && bad "sin versión de WP en assets" "ver= presente" || ok "sin versión de WP en assets"
expect_code "readme.html bloqueado"                 "403|404"     "$B/readme.html"
expect_code "license.txt bloqueado"                 "403|404"     "$B/license.txt"
hdr="$(curl -sI "$B/")"
grep -qi '^x-pingback' <<<"$hdr" && bad "sin X-Pingback" "presente" || ok "sin X-Pingback"
grep -qi '^x-powered-by' <<<"$hdr" && bad "sin X-Powered-By" "presente" || ok "sin X-Powered-By"

# Cabeceras de seguridad
for h in 'content-security-policy' 'x-frame-options' 'x-content-type-options: nosniff' 'referrer-policy' 'permissions-policy'; do
  grep -qi "^$h" <<<"$hdr" && ok "cabecera $h" || bad "cabecera $h" "ausente"
done

# Cabeceras también en la API REST y en archivos estáticos
rh="$(curl -sI "$B/wp-json/")"
grep -qi '^x-powered-by' <<<"$rh" && bad "REST sin X-Powered-By" "presente" || ok "REST sin X-Powered-By"
grep -qi '^x-content-type-options: nosniff' <<<"$rh" && ok "REST con nosniff" || bad "REST con nosniff" "ausente"
grep -qi '^x-frame-options' <<<"$rh" && ok "REST con X-Frame-Options" || bad "REST con X-Frame-Options" "ausente"
sh="$(curl -sI "$B/wp-content/themes/enciluz/assets/css/theme.css")"
grep -qi '^x-content-type-options: nosniff' <<<"$sh" && ok "estáticos con nosniff" || bad "estáticos con nosniff" "ausente"
hs="$(curl -sI -H 'X-Forwarded-Proto: https' "$B/")"
[ "$(grep -ci '^strict-transport-security' <<<"$hs")" = "1" ] && ok "HSTS (una vez) detrás de proxy TLS" || bad "HSTS (una vez) detrás de proxy TLS" "$(grep -ci '^strict-transport-security' <<<"$hs") cabeceras"

# oEmbed y REST de páginas no revelan autores
oe="$(curl -s "$B/wp-json/oembed/1.0/embed?url=$B/quienes-somos/")"
grep -q 'author_name\|author_url' <<<"$oe" && bad "oEmbed sin autor" "expone autor" || ok "oEmbed sin autor"

# CSP también con sesión iniciada
PASS="$(grep -oP 'cliente / \K\S+' "$DIR/CREDENCIALES-LOCAL.txt" 2>/dev/null)"
JAR="$(mktemp)"
curl -s -c "$JAR" -b "$JAR" -o /dev/null "$B/acceso-enciluz/"
curl -s -c "$JAR" -b "$JAR" -o /dev/null --data-urlencode "log=cliente" --data-urlencode "pwd=$PASS" --data-urlencode "wp-submit=Acceder" --data-urlencode "testcookie=1" "$B/acceso-enciluz/"
if grep -q wordpress_logged_in "$JAR"; then
  grep -qi '^content-security-policy' <<<"$(curl -sI -b "$JAR" "$B/")" && ok "CSP con sesión iniciada" || bad "CSP con sesión iniciada" "ausente"
  pg="$(curl -s -b "$JAR" "$B/wp-json/wp/v2/pages?per_page=1&_fields=author" -o /dev/null -w '%{http_code}')"
else
  bad "login de prueba" "no se pudo iniciar sesión"
fi
rm -f "$JAR"
pa="$(curl -s "$B/wp-json/wp/v2/pages?per_page=1")"
grep -q '"author":' <<<"$pa" && bad "REST de páginas sin ID de autor (anónimo)" "expone author" || ok "REST de páginas sin ID de autor (anónimo)"

# Pruebas con wp-cli (capacidades y contenido)
wpcli() { (cd "$DIR" && docker compose run --rm --no-deps -T cli wp "$@" 2>/dev/null); }
if (cd "$DIR" && docker compose ps --status running -q wordpress | grep -q .); then
  caps="$(wpcli eval 'echo user_can(get_user_by("login","cliente"),"unfiltered_html") || user_can(1,"unfiltered_html") ? "si" : "no";')"
  [ "$caps" = "no" ] && ok "nadie tiene unfiltered_html" || bad "nadie tiene unfiltered_html" "tiene la capacidad ($caps)"
  out="$(wpcli eval 'wp_set_current_user(get_user_by("login","cliente")->ID); $id=wp_insert_post(["post_type"=>"page","post_status"=>"draft","post_title"=>"xss","post_content"=>"<p>hola</p><script>alert(1)</script><img src=x onerror=alert(1)>"]); echo get_post_field("post_content",$id); wp_delete_post($id,true);')"
  grep -qiE '<script|onerror' <<<"$out" && bad "contenido del editor sanitizado" "$out" || ok "contenido del editor sanitizado"
  mimes="$(wpcli eval 'echo implode(",", array_keys(get_allowed_mime_types()));')"
  [ "$mimes" = "jpg|jpeg|jpe,png,webp,pdf" ] && ok "subidas limitadas a jpg/png/webp/pdf" || bad "subidas limitadas a jpg/png/webp/pdf" "$mimes"
  fe="$(wpcli eval 'echo defined("DISALLOW_FILE_EDIT") && DISALLOW_FILE_EDIT ? "si":"no";')"
  [ "$fe" = "si" ] && ok "edición de archivos deshabilitada" || bad "edición de archivos deshabilitada" "$fe"
  pl="$(wpcli plugin list --status=active --field=name | sort | tr '\n' ' ')"
  [ "$pl" = "limit-login-attempts-reloaded two-factor wps-hide-login " ] && ok "solo plugins permitidos activos" || bad "solo plugins permitidos activos" "$pl"
else
  bad "pruebas wp-cli" "el contenedor wordpress no está corriendo"
fi

echo "---- $((n-fail))/$n OK"
[ "$fail" -eq 0 ]
