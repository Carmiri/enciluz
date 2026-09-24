# Sitio web Fundación Enciende una Luz (ENCILUZ) — Diseño

Fecha: 2026-09-24 · Estado: aprobado en conversación, pendiente revisión escrita

## 1. Objetivo

Sitio institucional para la Fundación de Bienestar Social Enciende una Luz (ENCILUZ, RIF J-29721263-9),
inspirado en la estructura de https://www.fundana.org/quiénes-somos, **administrable por el cliente**
desde WordPress, publicado en **Vercel** para revisión continua, y con **seguridad preparada para auditoría**.

Criterios de éxito:
- El cliente edita textos, imágenes, programas, equipo, aliados y documentos sin tocar código.
- Cada guardado en WordPress se refleja en Vercel en segundos (sin redeploy manual).
- El sitio funciona en Vercel aunque WordPress no esté disponible (contenido de respaldo).
- Cabeceras de seguridad con calificación A en securityheaders.com; WordPress endurecido.
- Accesible (WCAG 2.1 AA) y correcto en móvil.

Fuentes: `Briefing (1) (1).pdf`, `Logo completo.png`, paleta (WhatsApp 2026-09-24).

## 2. Arquitectura

```
wp-admin (Docker local → GoDaddy en cms.enciluz.com)
   │ REST API solo lectura (endpoint propio /wp-json/enciluz/v1/*)
   ▼
Next.js (App Router, SSG + ISR) en Vercel → enciluz.com
   └─ /web/content/fallback.json si WP no responde o WP_API_URL no está definida
```

Monorepo:
- `/cms` — `docker-compose.yml` (WordPress + MariaDB), mu-plugin `enciluz-core` (CPTs, campos,
  endpoint agregado, endurecimiento, webhook de revalidación), script `seed` con el contenido del briefing.
- `/web` — Next.js + TypeScript + CSS Modules (sin framework CSS pesado).

Flujo de datos: `web/lib/content.ts` intenta `WP_API_URL/wp-json/enciluz/v1/site` (timeout 5 s);
si falla, usa `fallback.json`. Mismo esquema en ambos (tipos en `web/lib/types.ts`).
Revalidación: WP `save_post` → POST a `/api/revalidate` en Vercel con `REVALIDATE_SECRET`.

Migración a GoDaddy: subir WP (plugin/export estándar), definir `WP_API_URL` y `REVALIDATE_SECRET`
en Vercel. Sin cambios de código.

## 3. Modelo de contenido (editable)

Campos registrados en código (mu-plugin, sin ACF Pro; se usa ACF gratuito o meta boxes nativos):
- **Ajustes del sitio** (página de opciones): teléfonos, correo general, dirección, redes, RIF,
  título/subtítulo del hero, imagen hero, cifras de impacto (valor + etiqueta, repetible).
- **Programa** (CPT): título, imagen destacada, resumen, descripción, estados, cifra destacada, orden.
- **Sector** (CPT): título, ícono, descripción, orden.
- **Miembro del equipo** (CPT): nombre, cargo, foto opcional, orden. Sin correos personales.
- **Aliado** (CPT): nombre, logo, URL, tipo (donante/red).
- **Documento** (CPT): título, PDF adjunto, categoría (protocolo/manual/código).
- **Páginas** estándar con Gutenberg para "Historia" y textos libres.

Solo se publica el correo general `fundacionenciluz@gmail.com`.

## 4. Páginas

1. **Inicio** — hero con misión, cifras (15.733 atenciones de salud 2025, 312 mujeres fortalecidas,
   8 estados, desde 2008), sectores, programas destacados, aliados, llamada a colaborar.
2. **Quiénes somos** — historia, identidad (ONG nacional basada en fe, liderada por mujeres, enfoque
   comunitario, especialista en protección), valores, equipo, redes (PEAS, Igualdad de Género, CAFI, OBF), aliados.
3. **Qué hacemos** — 6 sectores, 9 programas, cobertura por estado/municipio/etnia.
4. **Transparencia** — protocolos y manuales, PEAS/salvaguardas, canal de denuncias (correo de reclamos
   solo si el cliente lo autoriza; por defecto formulario).
5. **Contacto / Colabora** — datos, WhatsApp, mapa (enlace, sin iframe de terceros), formulario.

## 5. Diseño visual

Paleta: `#3ba7bf` primario, `#f2cf1d` / `#f2a922` acentos (llama), `#73541a` titulares, `#0d0d0d` texto,
`#ffffff` fondo; neutro cálido derivado para secciones. Texto sobre amarillo/naranja siempre en `#0d0d0d`
(contraste AA). Tipografía humanista autoalojada (next/font). Fotos libres de derechos
(Unsplash/Pexels) descargadas al repo, con crédito en `web/public/images/CREDITS.md`.

## 6. Seguridad

Front (Vercel):
- CSP estricta, HSTS, X-Frame-Options DENY, X-Content-Type-Options, Referrer-Policy,
  Permissions-Policy (en `next.config`).
- Sin scripts de terceros; fuentes e imágenes propias.
- `/api/contact`: validación de esquema, honeypot, límite de tamaño, rate limit básico por IP,
  envío por Resend (clave en env); no almacena datos personales.
- `/api/revalidate`: secreto comparado en tiempo constante.

WordPress:
- XML-RPC off, endpoint `wp/v2/users` bloqueado a anónimos, `DISALLOW_FILE_EDIT`, front público
  redirigido (solo API), login oculto (WPS Hide Login), 2FA (plugin Two Factor), límite de intentos,
  cliente con rol Editor, claves/sales únicas en env, sin exponer versión.
- `SECURITY.md` con checklist para el auditor.

## 7. Proceso y verificación

- Subagente "experto en ONGs" redacta textos desde el briefing; subagente "experto UX" revisa
  navegación, accesibilidad y móvil.
- Verificación: `next build` sin errores, prueba del fallback sin WP, prueba con WP en Docker,
  revisión de cabeceras, Lighthouse (accesibilidad ≥ 95), revisión de seguridad final.
- Despliegue: Vercel preview para el cliente.

## Fuera de alcance

Pasarela de donaciones en línea, multilenguaje, blog/noticias (se puede añadir luego como CPT).
