#!/usr/bin/env bash
# Verifica que las páginas sembradas existen y muestran el contenido clave.
set -u
B="${1:-http://localhost:8088}"
fail=0; n=0
ok()  { n=$((n+1)); echo "OK   $1"; }
bad() { n=$((n+1)); fail=$((fail+1)); echo "FAIL $1 — $2"; }
check_page() { # ruta, textos...
	local path="$1"; shift
	local html code
	code="$(curl -s -o /tmp/enciluz_page.html -w '%{http_code}' "$B$path")"; html="$(cat /tmp/enciluz_page.html)"
	[ "$code" = "200" ] || { bad "$path responde 200" "obtenido $code"; return; }
	for t in "$@"; do grep -qF "$t" <<<"$html" && ok "$path contiene «$t»" || bad "$path contiene «$t»" "no aparece"; done
	grep -q '<html lang="es' <<<"$html" && ok "$path lang=es" || bad "$path lang=es" "sin lang es"
	local emails; emails="$(grep -oE '[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+\.[A-Za-z.]{2,}' <<<"$html" | sort -u | tr '\n' ' ')"
	[ -z "$emails" ] || [ "$emails" = "fundacionenciluz@gmail.com " ] && ok "$path solo correo general" || bad "$path solo correo general" "$emails"
	grep -qiE 'PHP (Warning|Notice|Fatal|Deprecated)|<b>Warning</b>' <<<"$html" && bad "$path sin errores PHP" "hay avisos" || ok "$path sin errores PHP"
}
check_page /                 "Encendemos una luz" "15.733" "Salud Integral" "Quiero colaborar" "Acción Contra el Hambre" "enciluz-hero" "de Venezuela" "Ver nuestros programas" "Dona o colabora" '<meta name="description"'
check_page /quienes-somos/   "Nuestra historia" "Eudenis Olano" "Transparencia" "Red PEAS"
check_page /que-hacemos/     "Vidas con Valor" "Aulas" "Amazonas" "id=\"salud-integral\"" 'alt="Familia acompañada en un espacio de acogida"'
check_page /transparencia/   "J-29721263-9" "Manual de Conflicto de Interés" "PEAS" "motivo=denuncia" "Solicitar un documento" "mailto:fundacionenciluz@gmail.com?subject="
check_page /contacto/        "fundacionenciluz@gmail.com" "wa.me/584265101702" "id=\"colabora\"" "Donación económica y cooperación"
check_page /creditos/        "Unsplash"
nav="$(curl -s "$B/")"
for l in "Quiénes somos" "Qué hacemos" "Transparencia" "Contacto"; do grep -q "wp-block-navigation-item__label\">$l<" <<<"$nav" && ok "menú tiene $l" || bad "menú tiene $l" "falta"; done
grep -q "wp-emoji" <<<"$nav" && bad "sin script de emojis" "presente" || ok "sin script de emojis"
grep -q 'custom-logo' <<<"$nav" && ok "logo en cabecera" || bad "logo en cabecera" "falta"
echo "---- $((n-fail))/$n OK"
[ "$fail" -eq 0 ]
