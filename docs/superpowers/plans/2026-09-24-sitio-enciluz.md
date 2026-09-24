# Sitio ENCILUZ (WordPress completo) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Sitio WordPress de la Fundación Enciende una Luz, 100 % editable con bloques, endurecido para auditoría, con vista previa estática en Vercel.

**Architecture:** WordPress en Docker con tema de bloques propio `enciluz` (theme.json + plantillas + partes + patrones) y un mu-plugin `enciluz-core` (endurecimiento, cabeceras, formulario). El contenido del briefing se siembra con wp-cli como páginas de bloques. `wget --mirror` exporta el sitio a `/preview`, que se publica en Vercel con `vercel.json` de cabeceras.

**Tech Stack:** WordPress 6 (`wordpress:php8.3-apache`), MariaDB 11, wp-cli (`wordpress:cli-php8.3`), Docker Compose v2, bash + curl para pruebas, Vercel CLI (`npx vercel`), Lighthouse.

**Spec:** `docs/superpowers/specs/2026-09-24-sitio-enciluz-design.md` (revisión 2)

## Global Constraints

- Paleta: `#3ba7bf`, `#f2cf1d`, `#f2a922`, `#73541a`, `#0d0d0d`, `#ffffff` (+ `#1f5f6d`, `#fbf7ee`). Texto sobre amarillo/naranja siempre `#0d0d0d`.
- Único correo público: `fundacionenciluz@gmail.com`. Sin correos personales ni datos bancarios.
- Todo editable en Gutenberg; sin page builders; sin shortcodes para contenido.
- Plugins: solo Two Factor, WPS Hide Login, Limit Login Attempts Reloaded.
- Sin scripts/iframes/fuentes de terceros. Imágenes solo Unsplash/Pexels con créditos.
- `lang="es"`; WCAG 2.1 AA; Lighthouse accesibilidad ≥ 95.
- Secretos en `cms/.env` (ignorado por git).

## Review Focus

1. Anónimo enumera usuarios (`/wp-json/wp/v2/users`, `?author=1`, `/author/x`) → debe fallar (T1 security.sh).
2. Formulario: honeypot, envío instantáneo, correo inválido, nombre con `\r\n`, mensaje gigante, ráfaga >5 → rechazado sin enviar (T4 contact.sh).
3. Editor pega `<script>`/`onerror` en un bloque HTML → se elimina al guardar (T1 security.sh vía wp-cli como editor).
4. Vista previa exportada con enlaces a `localhost:8080` o a `/wp-admin` → cero coincidencias (T5).
5. Cabeceras de seguridad ausentes en WP o en Vercel → presentes en ambos (T1, T5).

---

### Task 1: Stack Docker + instalación + endurecimiento (TDD con security.sh)

**Files:** `cms/docker-compose.yml`, `cms/.env.example`, `cms/scripts/setup.sh`, `cms/mu-plugins/enciluz-core.php`, `cms/mu-plugins/enciluz/hardening.php`, `cms/tests/security.sh`, `.gitignore`

- [ ] Compose con `db`, `wordpress` (puerto `127.0.0.1:8080`, `WORDPRESS_CONFIG_EXTRA` con `DISALLOW_FILE_EDIT`, `WP_POST_REVISIONS 10`, `WP_AUTO_UPDATE_CORE 'minor'`), `cli` (perfil `cli`, user 33). Volúmenes: `data/wp`, `data/db`, `mu-plugins` (ro), `theme/enciluz` → `wp-content/themes/enciluz` (ro), `seed` (ro), `scripts` (ro).
- [ ] `setup.sh`: core install (admin con clave aleatoria impresa una vez), idioma `es_ES`, zona `America/Caracas`, permalinks `/%postname%/`, borrar plugins/temas por defecto salvo el tema activo, instalar y activar los 3 plugins, `whl_page=acceso-enciluz`, usuario `cliente` rol editor, activar tema `enciluz`.
- [ ] Escribir `security.sh` primero (users API 401/404, xmlrpc deshabilitado, `?author=1` 404, sin `generator`, wp-login no 200, `/acceso-enciluz/` 200, cabeceras CSP/X-Frame/nosniff/Referrer/Permissions, subida .svg rechazada, editor sin unfiltered_html → `<script>` eliminado). Correr → FAIL.
- [ ] Implementar `hardening.php` → correr → PASS. Commit.

### Task 2: Tema de bloques `enciluz`

**Files:** `cms/theme/enciluz/{style.css,theme.json,functions.php}`, `templates/{index,front-page,page,404,search,single}.html`, `parts/{header,footer}.html`, `patterns/*.php`, `assets/fonts/*.woff2`, `assets/images/{logo.png,logo-mark.png}`, `screenshot.png`

- [ ] `theme.json` v3: paleta (slugs `azul`, `azul-oscuro`, `amarillo`, `naranja`, `marron`, `tinta`, `blanco`, `crema`), fuentes locales Nunito (títulos) y Nunito Sans (cuerpo), escalas de tamaño fluidas, espaciado, estilos de botones (naranja con texto tinta), enlaces con foco visible, `layout` contentSize 760 / wideSize 1200, desactivar paleta/gradientes personalizados para mantener la marca.
- [ ] Partes: header (logo, Navegación, botón "Colabora"), footer (misión, enlaces, contacto, redes, RIF, créditos). Plantillas limpias.
- [ ] Patrones (categoría `enciluz`): hero, cifras, sectores, programa (tarjeta), valores, equipo, aliados, documentos, cobertura, cta, contacto. Solo bloques nativos, textos traducibles, imágenes desde `assets` o medios.
- [ ] Verificar: `wp theme activate enciluz`, página de prueba con cada patrón renderiza sin errores PHP (`docker compose logs wordpress | grep -i "fatal\|warning"` vacío). Commit.

### Task 3: Contenido (agente ONG) + imágenes + siembra

**Files:** `cms/seed/content.json`, `cms/seed/images/*`, `cms/seed/images/CREDITS.md`, `cms/scripts/seed.php`

- [ ] Agente ONG → `content.json`; agente imágenes → `seed/images`, logos → `theme/enciluz/assets/images`.
- [ ] `seed.php` (idempotente): importa imágenes, crea las 5 páginas con bloques generados desde los patrones y `content.json`, fija portada estática, crea el menú de navegación (wp_navigation), llena header/footer.
- [ ] Verificar: `curl` de las 5 URLs → 200 y contienen textos clave (15.733, "Vidas con Valor", "PEAS"); `grep` de correos en HTML → solo el general. Commit.

### Task 4: Formulario de contacto seguro (TDD con contact.sh)

**Files:** `cms/mu-plugins/enciluz/contact.php`, `cms/theme/enciluz/patterns/contacto.php` (usa el bloque), `cms/tests/contact.sh`

- [ ] Bloque dinámico `enciluz/contact-form` (registrado en PHP, render server-side, editable su título/intro en el editor), POST a `admin-post.php?action=enciluz_contact` con nonce, honeypot `website`, `started_at`, motivo (informacion/voluntariado/alianza/donacion/denuncia), validación, límite 5/10 min por IP (transient con hash de IP), `wp_mail` a la opción `enciluz_contact_to` (por defecto el correo general), redirección con `?enviado=1|error=<code>`. Mail capturado en local con un `pre_wp_mail` que escribe a log cuando `WP_ENVIRONMENT_TYPE=local`.
- [ ] `contact.sh` primero → FAIL; implementar → PASS. Commit.

### Task 5: Export estático + Vercel

**Files:** `cms/scripts/export.sh`, `preview/vercel.json`, `cms/scripts/deploy-preview.sh`

- [ ] `export.sh`: `wget --mirror --page-requisites --adjust-extension --convert-links --no-host-directories -e robots=off` desde `http://localhost:8080`, excluyendo `wp-admin`, `wp-json`, `acceso-enciluz`, `xmlrpc.php`, feeds; reemplazar restos de `http://localhost:8080`; formulario → aviso "vista previa". Prueba: `grep -r "localhost:8080\|wp-admin" preview` vacío.
- [ ] `vercel.json` con cabeceras (CSP estricta para HTML estático, HSTS, X-Frame DENY, nosniff, Referrer, Permissions) y `cleanUrls`.
- [ ] Deploy: `npx vercel@latest deploy preview --prod` (proyecto `enciluz-preview`), verificar cabeceras con curl. Commit.

### Task 6: Revisión UX (agente) + accesibilidad + documentación

**Files:** `SECURITY.md`, `README.md`, `docs/GUIA-CLIENTE.md`, `docs/MIGRACION-GODADDY.md`

- [ ] Agente UX revisa el sitio (capturas móvil/escritorio) y el código del tema; aplicar hallazgos críticos/altos.
- [ ] Lighthouse sobre `/`, `/quienes-somos/`, `/que-hacemos/` → accesibilidad ≥ 95.
- [ ] Documentos: SECURITY.md (controles, cómo verificar, decisiones), guía del cliente (cómo editar), migración GoDaddy.
- [ ] Revisión final de código por un revisor independiente; re-exportar y re-desplegar. Commit.
