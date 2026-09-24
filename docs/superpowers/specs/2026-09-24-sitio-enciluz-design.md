# Sitio web Fundación Enciende una Luz (ENCILUZ) — Diseño

Fecha: 2026-09-24 · Revisión 2 (el cliente pidió WordPress completo con todo el contenido administrable)

## 1. Objetivo

Sitio institucional para la Fundación de Bienestar Social Enciende una Luz (ENCILUZ, RIF J-29721263-9),
inspirado en la estructura de https://www.fundana.org/quiénes-somos, **hecho en WordPress con todo el
contenido administrable** (textos, imágenes, secciones, orden, menús, cabecera, pie y páginas nuevas),
con una **vista previa en Vercel** para el cliente y **seguridad preparada para auditoría**.
Producción final: WordPress en GoDaddy.

Criterios de éxito:
- El cliente edita cualquier parte del sitio con el editor de bloques (Gutenberg), sin código ni page builders.
- Secciones prearmadas (patrones) con la paleta, para crear páginas nuevas coherentes.
- Vista previa estática en Vercel, actualizable con un comando.
- WordPress endurecido con pruebas de seguridad automatizadas; `SECURITY.md` para el auditor.
- Accesible (WCAG 2.1 AA) y correcto en móvil.

Fuentes: `Briefing (1) (1).pdf`, `Logo completo.png`, paleta (WhatsApp 2026-09-24).

## 2. Arquitectura

```
WordPress 6 (Docker local → GoDaddy)
  ├─ tema de bloques "enciluz" (theme.json, plantillas, partes, patrones)
  ├─ mu-plugin "enciluz-core" (endurecimiento, cabeceras, formulario de contacto)
  └─ plugins de seguridad: Two Factor, WPS Hide Login, Limit Login Attempts Reloaded
        │
        └─ export estático (wget mirror) ──▶ Vercel (vista previa del cliente, con cabeceras de seguridad)
```

Repositorio:
- `/cms/docker-compose.yml` — WordPress (`wordpress:php8.3-apache`) + MariaDB 11 + wp-cli; puerto solo en 127.0.0.1.
- `/cms/theme/enciluz/` — tema de bloques (montado en `wp-content/themes/enciluz`).
- `/cms/mu-plugins/` — `enciluz-core.php` y módulos.
- `/cms/seed/` — `content.json` (contenido del briefing) e imágenes libres; `scripts/seed.php` crea páginas,
  menú y biblioteca de medios a partir de los patrones.
- `/cms/scripts/` — `setup.sh` (instalación), `export.sh` (sitio estático → `/preview`), `deploy-preview.sh`.
- `/preview/` — salida estática + `vercel.json` con cabeceras (lo que se publica en Vercel).

## 3. Modelo de contenido (todo editable)

- Todas las páginas son páginas normales de WordPress compuestas con bloques nativos y patrones.
- Cabecera y pie son *template parts* editables en el Editor del sitio (Apariencia → Editor).
- Menú con el bloque Navegación nativo.
- Patrones del tema (categoría "ENCILUZ"): Hero, Cifras de impacto, Sectores, Tarjetas de programa,
  Valores, Equipo, Aliados/Donantes, Documentos de transparencia, Cobertura, Llamado a colaborar, Datos de contacto.
- El cliente usa el rol **Editor** (edita contenido y páginas). La edición de plantillas, cabecera y pie
  requiere Administrador; se entrega una cuenta de administrador aparte, con 2FA.

Solo se publica el correo general `fundacionenciluz@gmail.com`.

## 4. Páginas

1. **Inicio** — hero con misión, cifras (15.733 atenciones de salud 2025, 312 mujeres fortalecidas,
   8 estados, desde 2008), sectores, programas destacados, aliados, llamado a colaborar.
2. **Quiénes somos** — historia, identidad (ONG nacional basada en fe, liderada por mujeres, enfoque
   comunitario, especialista en protección), valores, equipo, redes (PEAS, Igualdad de Género, CAFI, OBF), aliados.
3. **Qué hacemos** — 6 sectores, 9 programas, cobertura por estado/municipio/etnia.
4. **Transparencia** — protocolos y manuales, PEAS/salvaguardas, canal de denuncias.
5. **Contacto / Colabora** — datos, WhatsApp, enlace a mapa (sin iframe), formulario.

## 5. Diseño visual

Paleta: `#3ba7bf` primario, `#f2cf1d` / `#f2a922` acentos (llama), `#73541a` titulares, `#0d0d0d` texto,
`#ffffff` fondo; derivados `#1f5f6d` (azul oscuro para texto AA) y `#fbf7ee` (crema). Texto sobre
amarillo/naranja siempre `#0d0d0d`. Tipografías Nunito / Nunito Sans autoalojadas en el tema.
Fotos libres (Unsplash/Pexels) con créditos en `cms/seed/images/CREDITS.md`.

## 6. Seguridad

WordPress (mu-plugin + configuración):
- XML-RPC off; `wp/v2/users` y REST para anónimos bloqueados (salvo lo que usa el editor autenticado);
  enumeración `?author=` → 404; sin versión en cabeceras/HTML; `DISALLOW_FILE_EDIT`; subida limitada a
  jpg/png/webp/pdf; Editor sin `unfiltered_html`; login en URL oculta; 2FA; límite de intentos.
- Cabeceras: CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS (en HTTPS).
- Formulario de contacto propio (sin plugin): nonce, honeypot, tiempo mínimo, límite por IP (transients),
  validación y protección contra inyección de cabeceras; se envía con `wp_mail`, no guarda datos personales.
- Sin scripts ni iframes de terceros; fuentes locales.

Vista previa en Vercel: HTML estático, sin PHP ni base de datos; `vercel.json` con las mismas cabeceras.
El formulario en la vista previa no envía (se indica al visitante que use correo/WhatsApp).

## 7. Proceso y verificación

- Subagente "experto en ONGs" redacta los textos desde el briefing; subagente "experto UX" revisa
  navegación, accesibilidad y móvil.
- Pruebas de seguridad automatizadas (`cms/tests/security.sh`) y del formulario (`cms/tests/contact.sh`).
- Lighthouse (accesibilidad ≥ 95) sobre la vista previa.

## Fuera de alcance

Pasarela de donaciones en línea, multilenguaje, blog (WordPress lo trae; se activa cuando el cliente lo pida).
