#!/usr/bin/env bash
# Pruebas del formulario de contacto (requiere el stack local arriba).
set -u
B="${1:-http://localhost:8088}"
DIR="$(cd "$(dirname "$0")/.." && pwd)"
PAGE="$B/contacto/"
fail=0; n=0
ok()  { n=$((n+1)); echo "OK   $1"; }
bad() { n=$((n+1)); fail=$((fail+1)); echo "FAIL $1 — $2"; }
dc()  { (cd "$DIR" && docker compose "$@"); }
wpe() { dc run --rm --no-deps -T cli wp eval "$1" 2>/dev/null; }

dc exec -T wordpress sh -c 'rm -f /tmp/enciluz-mail.log' >/dev/null 2>&1
dc run --rm --no-deps -T cli wp transient delete --all >/dev/null 2>&1

html="$(curl -s "$PAGE")"
grep -q 'name="enciluz_contact"' <<<"$html" && ok "el formulario se muestra" || bad "el formulario se muestra" "no está en /contacto/"
for f in nombre correo motivo mensaje website enciluz_t _wpnonce; do
	grep -q "name=\"$f\"" <<<"$html" && ok "campo $f" || bad "campo $f" "falta"
done
grep -q '<label for="enciluz-nombre"' <<<"$html" && ok "campos con label" || bad "campos con label" "sin label"
grep -q '(obligatorio)' <<<"$html" && ok "campos obligatorios marcados" || bad "campos obligatorios marcados" "sin marca"
grep -q 'value="cooperacion"' <<<"$html" && ok "motivo donación económica o cooperación" || bad "motivo donación económica o cooperación" "falta"
curl -s "$PAGE?aviso=correo" | grep -q 'id="enciluz-correo"[^>]*aria-invalid="true"' && ok "campo con error marcado aria-invalid" || bad "campo con error marcado aria-invalid" "sin aria-invalid"
curl -s "$PAGE?aviso=correo" | grep -q 'aria-describedby="enciluz-error"' && ok "error asociado al campo" || bad "error asociado al campo" "sin aria-describedby"
NONCE="$(grep -oP 'name="_wpnonce" value="\K[^"]+' <<<"$html" | head -1)"
OLD="$(wpe 'echo enciluz_contact_token(time() - 10);')"
NOW="$(wpe 'echo enciluz_contact_token(time());')"

# post <descripción> <esperado en Location: regex> campos...
post() {
	local desc="$1" want="$2"; shift 2
	local loc
	loc="$(curl -s -o /dev/null -w '%{redirect_url}' "$PAGE" --data-urlencode "enciluz_contact=1" --data-urlencode "_wpnonce=$NONCE" "$@")"
	[[ "$loc" =~ $want ]] && ok "$desc" || bad "$desc" "Location: $loc"
}
V=(--data-urlencode "nombre=Ana Pérez" --data-urlencode "correo=ana@ejemplo.com" --data-urlencode "motivo=voluntariado" --data-urlencode "mensaje=Hola, quiero sumarme como voluntaria.")

post "rechaza honeypot"                 'aviso=' "${V[@]}" --data-urlencode "website=spam.example" --data-urlencode "enciluz_t=$OLD"
post "rechaza envío instantáneo"        'aviso=' "${V[@]}" --data-urlencode "website=" --data-urlencode "enciluz_t=$NOW"
post "rechaza token manipulado"         'aviso=' "${V[@]}" --data-urlencode "website=" --data-urlencode "enciluz_t=1.abc"
post "rechaza nonce inválido"           'aviso=' "${V[@]}" --data-urlencode "website=" --data-urlencode "enciluz_t=$OLD" --data-urlencode "_wpnonce=x"
dc run --rm --no-deps -T cli wp transient delete --all >/dev/null 2>&1
post "rechaza correo inválido"          'aviso=' --data-urlencode "nombre=Ana" --data-urlencode "correo=no-es-correo" --data-urlencode "motivo=informacion" --data-urlencode "mensaje=Hola, información." --data-urlencode "website=" --data-urlencode "enciluz_t=$OLD"
post "rechaza nombre con salto de línea" 'aviso=' --data-urlencode $'nombre=Ana\r\nBcc: x@y.z' --data-urlencode "correo=ana@ejemplo.com" --data-urlencode "motivo=informacion" --data-urlencode "mensaje=Hola, información." --data-urlencode "website=" --data-urlencode "enciluz_t=$OLD"
post "rechaza mensaje de más de 3000"   'aviso=' --data-urlencode "nombre=Ana" --data-urlencode "correo=ana@ejemplo.com" --data-urlencode "motivo=informacion" --data-urlencode "mensaje=$(head -c 3001 /dev/zero | tr '\0' a)" --data-urlencode "website=" --data-urlencode "enciluz_t=$OLD"
post "rechaza motivo desconocido"       'aviso=' --data-urlencode "nombre=Ana" --data-urlencode "correo=ana@ejemplo.com" --data-urlencode "motivo=otro" --data-urlencode "mensaje=Hola, información." --data-urlencode "website=" --data-urlencode "enciluz_t=$OLD"
[ -z "$(dc exec -T wordpress sh -c 'cat /tmp/enciluz-mail.log 2>/dev/null')" ] && ok "ningún rechazo envió correo" || bad "ningún rechazo envió correo" "hay correos"

dc run --rm --no-deps -T cli wp transient delete --all >/dev/null 2>&1
post "acepta un envío válido"           'enviado=1' "${V[@]}" --data-urlencode "website=" --data-urlencode "enciluz_t=$OLD"
mail="$(dc exec -T wordpress sh -c 'cat /tmp/enciluz-mail.log 2>/dev/null')"
grep -q 'TO: fundacionenciluz@gmail.com' <<<"$mail" && ok "correo enviado al destinatario configurado" || bad "correo enviado al destinatario configurado" "$mail"
grep -q 'Reply-To: ana@ejemplo.com' <<<"$mail" && ok "responder a quien escribe" || bad "responder a quien escribe" "sin Reply-To"
grep -q 'Voluntariado' <<<"$mail" && ok "asunto con el motivo" || bad "asunto con el motivo" "sin motivo"
curl -s "$PAGE?enviado=1" | grep -q 'enciluz-aviso--ok' && ok "muestra confirmación" || bad "muestra confirmación" "sin aviso"

for i in 2 3 4 5; do post "envío $i dentro del límite" 'enviado=1' "${V[@]}" --data-urlencode "website=" --data-urlencode "enciluz_t=$OLD"; done
post "bloquea el sexto envío en 10 min" 'aviso=limite' "${V[@]}" --data-urlencode "website=" --data-urlencode "enciluz_t=$OLD"

# Sin datos personales guardados
cnt="$(wpe 'global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE \"%ana@ejemplo.com%\"") + (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE \"%ana@ejemplo.com%\"");')"
[ "$cnt" = "0" ] && ok "no guarda datos personales" || bad "no guarda datos personales" "$cnt coincidencias"

echo "---- $((n-fail))/$n OK"
[ "$fail" -eq 0 ]
