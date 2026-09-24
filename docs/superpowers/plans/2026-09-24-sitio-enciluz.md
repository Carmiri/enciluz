# Sitio ENCILUZ Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sitio institucional de la Fundación Enciende una Luz, editable desde WordPress headless y publicado en Vercel, endurecido para auditoría.

**Architecture:** `/web` es Next.js 16 (App Router) en Vercel; lee `WP_API_URL/wp-json/enciluz/v1/site` (un solo endpoint agregado) validado con zod, y si falla usa `web/content/fallback.json`. `/cms` es WordPress en Docker con un mu-plugin `enciluz-core` que registra tipos de contenido, campos nativos, página de ajustes, endpoint, endurecimiento y webhook de revalidación.

**Tech Stack:** Next.js 16.3, React 19, TypeScript, CSS Modules, zod 4, sanitize-html 2, resend 6, vitest 5; WordPress 6 (imagen oficial `wordpress:php8.3-apache`), MariaDB 11, Docker Compose v2.

**Spec:** `docs/superpowers/specs/2026-09-24-sitio-enciluz-design.md`

## Global Constraints

- Paleta exacta: `#3ba7bf` primario, `#f2cf1d` y `#f2a922` acentos, `#73541a` titulares, `#0d0d0d` texto, `#ffffff` fondo. Texto sobre amarillo/naranja siempre `#0d0d0d`.
- Único correo público: `fundacionenciluz@gmail.com`. Nunca publicar correos personales ni datos bancarios.
- Idioma del sitio: español (`<html lang="es">`).
- Sin scripts de terceros en el front. Fuentes autoalojadas con `next/font/google`. Sin iframes.
- Imágenes: solo Unsplash/Pexels, guardadas en `web/public/images/`, crédito en `web/public/images/CREDITS.md`.
- Plugins WP permitidos: Two Factor, WPS Hide Login, Limit Login Attempts Reloaded. Nada más.
- El sitio debe compilar y funcionar sin `WP_API_URL` (usa fallback).
- Accesibilidad WCAG 2.1 AA; Lighthouse accesibilidad ≥ 95.
- Secretos solo en variables de entorno; `.env*` en `.gitignore`.

## Review Focus

1. WP responde HTML de error, JSON incompleto o tarda >5 s → el sitio debe renderizar con fallback, nunca romperse (test en Task 1).
2. Contenido HTML de WP con `<script>`, `onerror=`, `javascript:` → debe salir sanitizado (test en Task 1).
3. Formulario de contacto con campos vacíos, correo inválido, texto gigante, honeypot relleno o ráfaga de envíos → 400/429 sin enviar correo (tests en Task 5).
4. `/api/revalidate` sin secreto, secreto incorrecto o método GET → 401/405 (test en Task 6).
5. WP: `/wp-json/wp/v2/users`, `/xmlrpc.php`, `/?author=1` y el front público no deben filtrar usuarios ni servir HTML (test de integración en Task 8).

---

## File Structure

```
/cms
  docker-compose.yml            WordPress + MariaDB + wp-cli
  .env.example                  credenciales de ejemplo
  mu-plugins/enciluz-core.php   carga los módulos
  mu-plugins/enciluz/cpt.php        tipos de contenido + meta boxes
  mu-plugins/enciluz/settings.php   página "Ajustes ENCILUZ"
  mu-plugins/enciluz/api.php        endpoint /enciluz/v1/site
  mu-plugins/enciluz/hardening.php  endurecimiento
  mu-plugins/enciluz/webhook.php    revalidación Vercel
  scripts/setup.sh              instala WP, plugins, rol, contenido semilla
  scripts/seed.php              crea contenido desde web/content/fallback.json
  tests/security.sh             pruebas de integración con curl
/web
  proxy.ts                      CSP con nonce + cabeceras
  next.config.ts                cabeceras estáticas, imágenes remotas del CMS
  lib/types.ts                  esquema zod + tipos
  lib/content.ts                getContent(): WP → fallback
  lib/sanitize.ts               sanitizeHtml()
  lib/contact.ts                validación + rate limit
  content/fallback.json         contenido del briefing
  app/layout.tsx, app/page.tsx, app/quienes-somos/page.tsx,
  app/que-hacemos/page.tsx, app/transparencia/page.tsx, app/contacto/page.tsx
  app/api/contact/route.ts, app/api/revalidate/route.ts
  components/*                  Header, Footer, Hero, StatsBar, SectorGrid, ProgramCard,
                                TeamGrid, AllyGrid, DocumentList, CoverageList, ContactForm, Icon
  app/globals.css, components/*.module.css
  tests/*.test.ts
SECURITY.md, README.md
```

---

### Task 1: Scaffold web + modelo de contenido + cargador con fallback

**Files:**
- Create: `web/` (create-next-app), `web/lib/types.ts`, `web/lib/content.ts`, `web/lib/sanitize.ts`, `web/content/fallback.json` (mínimo válido), `web/vitest.config.ts`, `web/tests/content.test.ts`, `web/tests/sanitize.test.ts`, `.gitignore`

**Interfaces:**
- Produces: `SiteContentSchema`, `type SiteContent`, `getContent(): Promise<SiteContent>`, `parseContent(raw: unknown): SiteContent | null`, `sanitizeHtml(html: string): string`, tag de caché `"wp"`.

- [ ] **Step 1: Scaffold**

```bash
cd /home/mcardozo/Documentos/proyectos/paginaFundacion
npx create-next-app@16.3.6 web --ts --app --eslint --no-tailwind --no-src-dir --import-alias "@/*" --use-npm --turbopack --yes
cd web && npm i zod@4 sanitize-html@2 resend@6 && npm i -D vitest@5 @types/sanitize-html
```
Añadir a `web/package.json` scripts: `"test": "vitest run"`. Crear `web/vitest.config.ts`:
```ts
import { defineConfig } from 'vitest/config'
import path from 'node:path'
export default defineConfig({ resolve: { alias: { '@': path.resolve(__dirname) } }, test: { environment: 'node' } })
```
`.gitignore` raíz: `node_modules/`, `.next/`, `.env*`, `!.env.example`, `.vercel/`, `cms/data/`.

- [ ] **Step 2: Esquema** `web/lib/types.ts`

```ts
import { z } from 'zod'
const str = z.string().trim()
export const StatSchema = z.object({ value: str.min(1), label: str.min(1) })
export const SettingsSchema = z.object({
  siteName: str, tagline: str, heroTitle: str, heroSubtitle: str, heroImage: str,
  email: z.string().email(), phones: z.array(str), whatsapp: str, address: str,
  rif: str, registry: str,
  social: z.object({ facebook: str, instagram: str, tiktok: str, x: str }),
  stats: z.array(StatSchema).max(4),
  mission: str, identity: str,
})
export const ValueSchema = z.object({ title: str.min(1), text: str })
export const SectorSchema = z.object({ slug: str, title: str.min(1), icon: str, summary: str, order: z.number() })
export const ProgramSchema = z.object({ slug: str, title: str.min(1), image: str, summary: str, body: str,
  states: z.array(str), highlight: str.optional().default(''), order: z.number() })
export const TeamMemberSchema = z.object({ name: str.min(1), role: str.min(1), photo: str.optional().default(''), order: z.number() })
export const AllySchema = z.object({ name: str.min(1), logo: str.optional().default(''), url: str.optional().default(''), kind: z.enum(['donante', 'red']) })
export const DocumentSchema = z.object({ title: str.min(1), category: z.enum(['protocolo', 'manual', 'codigo']), file: str.optional().default('') })
export const CoverageSchema = z.object({ state: str.min(1), municipalities: z.array(str), ethnicities: z.array(str).default([]) })
export const SiteContentSchema = z.object({
  settings: SettingsSchema, history: str, values: z.array(ValueSchema), sectors: z.array(SectorSchema),
  programs: z.array(ProgramSchema), team: z.array(TeamMemberSchema), allies: z.array(AllySchema),
  documents: z.array(DocumentSchema), coverage: z.array(CoverageSchema), networks: z.array(str),
})
export type SiteContent = z.infer<typeof SiteContentSchema>
export type Program = z.infer<typeof ProgramSchema>
export type Sector = z.infer<typeof SectorSchema>
```

- [ ] **Step 3: Tests fallidos** `web/tests/content.test.ts`

```ts
import { describe, it, expect, vi, afterEach } from 'vitest'
import fallback from '@/content/fallback.json'
import { parseContent, getContent } from '@/lib/content'

afterEach(() => { vi.unstubAllGlobals(); delete process.env.WP_API_URL })

describe('parseContent', () => {
  it('acepta el fallback', () => { expect(parseContent(fallback)).not.toBeNull() })
  it('rechaza JSON incompleto', () => { expect(parseContent({ settings: {} })).toBeNull() })
  it('ordena programas por order', () => {
    const c = structuredClone(fallback) as any
    c.programs = [{ ...c.programs[0], slug: 'b', order: 2 }, { ...c.programs[0], slug: 'a', order: 1 }]
    expect(parseContent(c)!.programs.map(p => p.slug)).toEqual(['a', 'b'])
  })
})

describe('getContent', () => {
  it('usa fallback sin WP_API_URL', async () => {
    expect((await getContent()).settings.email).toBe('fundacionenciluz@gmail.com')
  })
  it('usa fallback si WP devuelve HTML', async () => {
    process.env.WP_API_URL = 'https://cms.example.com'
    vi.stubGlobal('fetch', vi.fn(async () => new Response('<html>error</html>', { status: 500 })))
    expect((await getContent()).settings.siteName).toBe(fallback.settings.siteName)
  })
  it('usa fallback si fetch lanza (timeout)', async () => {
    process.env.WP_API_URL = 'https://cms.example.com'
    vi.stubGlobal('fetch', vi.fn(async () => { throw new DOMException('timeout', 'TimeoutError') }))
    expect((await getContent()).settings.siteName).toBe(fallback.settings.siteName)
  })
  it('usa datos de WP cuando son válidos y sanitiza history', async () => {
    process.env.WP_API_URL = 'https://cms.example.com'
    const wp = { ...structuredClone(fallback), history: '<p>Hola</p><script>alert(1)</script>' }
    wp.settings.siteName = 'Desde WP'
    vi.stubGlobal('fetch', vi.fn(async () => Response.json(wp)))
    const c = await getContent()
    expect(c.settings.siteName).toBe('Desde WP')
    expect(c.history).not.toContain('<script')
  })
})
```
`web/tests/sanitize.test.ts`:
```ts
import { it, expect } from 'vitest'
import { sanitizeHtml } from '@/lib/sanitize'
it('elimina scripts, handlers y javascript:', () => {
  const out = sanitizeHtml('<p onclick="x()">a</p><img src=x onerror=alert(1)><a href="javascript:alert(1)">b</a><script>1</script>')
  expect(out).not.toMatch(/onclick|onerror|javascript:|<script|<img/)
  expect(out).toContain('<p>a</p>')
})
it('conserva formato básico y enlaces https', () => {
  expect(sanitizeHtml('<h2>T</h2><ul><li><strong>x</strong></li></ul><a href="https://a.org">l</a>'))
    .toBe('<h2>T</h2><ul><li><strong>x</strong></li></ul><a href="https://a.org" rel="noopener noreferrer">l</a>')
})
```
Crear `web/content/fallback.json` mínimo válido (1 elemento por lista, `siteName: "Fundación Enciende una Luz"`, `email: "fundacionenciluz@gmail.com"`). Añadir `"resolveJsonModule": true` si falta en tsconfig.

- [ ] **Step 4: Ejecutar** `cd web && npm test` → FAIL (módulos inexistentes).

- [ ] **Step 5: Implementar** `web/lib/sanitize.ts`

```ts
import sanitize from 'sanitize-html'
export function sanitizeHtml(html: string): string {
  return sanitize(html, {
    allowedTags: ['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'em', 'a', 'blockquote', 'br'],
    allowedAttributes: { a: ['href'] },
    allowedSchemes: ['https', 'mailto', 'tel'],
    transformTags: { a: sanitize.simpleTransform('a', { rel: 'noopener noreferrer' }) },
  })
}
```
`web/lib/content.ts`:
```ts
import 'server-only'
import fallback from '@/content/fallback.json'
import { SiteContentSchema, type SiteContent } from './types'
import { sanitizeHtml } from './sanitize'

const byOrder = <T extends { order: number }>(a: T, b: T) => a.order - b.order

export function parseContent(raw: unknown): SiteContent | null {
  const r = SiteContentSchema.safeParse(raw)
  if (!r.success) return null
  const c = r.data
  return { ...c, history: sanitizeHtml(c.history),
    programs: [...c.programs].sort(byOrder).map(p => ({ ...p, body: sanitizeHtml(p.body) })),
    sectors: [...c.sectors].sort(byOrder), team: [...c.team].sort(byOrder) }
}

const FALLBACK = parseContent(fallback)!

export async function getContent(): Promise<SiteContent> {
  const base = process.env.WP_API_URL
  if (!base) return FALLBACK
  try {
    const res = await fetch(`${base.replace(/\/$/, '')}/wp-json/enciluz/v1/site`, {
      next: { revalidate: 3600, tags: ['wp'] }, signal: AbortSignal.timeout(5000),
    })
    if (!res.ok) throw new Error(`WP ${res.status}`)
    return parseContent(await res.json()) ?? FALLBACK
  } catch (e) {
    console.error('[content] usando fallback:', (e as Error).message)
    return FALLBACK
  }
}
```
Instalar `server-only` (`npm i server-only`) y en vitest añadir alias `'server-only': path.resolve(__dirname, 'tests/empty.ts')` con `tests/empty.ts` = `export {}`.

- [ ] **Step 6: Ejecutar** `npm test` → PASS (7 tests). `npm run build` → OK.
- [ ] **Step 7: Commit** `git add -A && git commit -m "feat(web): scaffold, modelo de contenido y cargador con fallback"`

---

### Task 2: Contenido del briefing (subagente experto en ONGs)

**Files:** Modify: `web/content/fallback.json`

**Interfaces:** Consumes `SiteContentSchema`. Produces el `fallback.json` completo que usan Tasks 4 y 8.

- [ ] **Step 1:** Despachar subagente "redactor experto en ONGs/fundaciones" con el briefing (`/home/mcardozo/Descargas/Briefing (1) (1).pdf`) y el esquema. Reglas: tono cercano, profesional, de rendición de cuentas; no inventar cifras, fechas ni nombres; usar solo datos del briefing; solo el correo general; `history` en HTML simple (p, h2, ul); 8 valores coherentes con una ONG basada en fe, liderada por mujeres y con enfoque de protección (Solidaridad, Dignidad, Fe, Transparencia, Protección, Equidad, Compromiso, Empatía) con una frase cada uno; 6 sectores (salud, nutrición, protección, saneamiento e higiene [WASH], seguridad alimentaria y medios de vida, educación) con `icon` ∈ {`health`,`nutrition`,`shield`,`water`,`seed`,`book`}; 9 programas (Vidas con Valor, Gestión de Documentos, Fortaleciendo Habilidades Emocionales, Habilidades Económicas y Financieras, Aulas Integrales, Salud Integral, Salud Sexual y Reproductiva, Protección a NNA, Servicios de Emergencia) con estados y `highlight` cuando hay cifra (15.733 personas 2025; 312 mujeres 2021–2024, 90 %; 600 personas/año acogida); equipo de 9 del briefing; aliados: 4 donantes + redes (PEAS, Igualdad de Género, CAFI, OBF) como `kind:"red"`; documentos: los 22 protocolos listados, categorizados; cobertura de 8 estados con municipios y etnias; stats: `15.733` "personas atendidas en salud (2025)", `312` "mujeres fortalecidas", `8` "estados con presencia", `2008` "año de fundación"; `whatsapp: "584265101702"`; imágenes con rutas `/images/<slug>.jpg` que Task 3 creará.
- [ ] **Step 2:** `cd web && npm test` → PASS (el test `acepta el fallback` valida el JSON completo).
- [ ] **Step 3:** Revisión manual: grep de correos → solo `fundacionenciluz@gmail.com`: `grep -oE '[a-z0-9._]+@[a-z.]+' content/fallback.json | sort -u`.
- [ ] **Step 4: Commit** `feat(web): contenido completo desde el briefing`

---

### Task 3: Imágenes libres y logo

**Files:** Create: `web/public/images/*.jpg`, `web/public/images/CREDITS.md`, `web/public/logo.png`, `web/public/logo-mark.png`, `web/app/icon.png`

- [ ] **Step 1:** Buscar en Unsplash/Pexels (WebSearch/WebFetch) fotos de comunidades latinoamericanas, niñez, salud comunitaria, educación, mujeres emprendedoras, agua/higiene, huertos. Una por: `hero`, `historia`, cada programa (9 slugs de Task 2), `cta`. Descargar a 1600px de ancho con `curl -L`, convertir a JPG calidad 80 (`ffmpeg`/`convert` si existe, si no se usa tal cual; next/image optimiza).
- [ ] **Step 2:** `CREDITS.md` con archivo → autor → URL → licencia (Unsplash License / Pexels License).
- [ ] **Step 3:** Copiar logo: `cp "/home/mcardozo/Descargas/Logo completo.png" web/public/logo.png`; recortar la marca (antorcha + arco) para header/favicon con `convert` (si no hay ImageMagick, usar python PIL o el logo completo).
- [ ] **Step 4:** Verificar que cada ruta de imagen en `fallback.json` existe: `node -e "const c=require('./content/fallback.json');const fs=require('fs');[c.settings.heroImage,...c.programs.map(p=>p.image)].forEach(i=>{if(!fs.existsSync('public'+i))throw i})"` → sin error.
- [ ] **Step 5: Commit** `feat(web): imágenes libres de derechos y logo`

---

### Task 4: Sistema visual, layout y las 5 páginas (con frontend-design)

**Files:** Create: `web/app/globals.css`, `web/app/layout.tsx`, `web/components/{Header,Footer,Hero,StatsBar,SectorGrid,ProgramCard,TeamGrid,AllyGrid,DocumentList,CoverageList,Icon,Section}.tsx` + `.module.css`, las 5 `page.tsx`. Borrar `app/page.module.css` de plantilla.

**Interfaces:** Consumes `getContent()`, tipos de Task 1. Cada página es `async` server component que llama `getContent()` y exporta `generateMetadata` con título y descripción.

- [ ] **Step 1:** Invocar skill `frontend-design:frontend-design` para la dirección visual con las restricciones globales. Tokens en `globals.css`:
```css
:root{--azul:#3ba7bf;--azul-900:#1f5f6d;--amarillo:#f2cf1d;--naranja:#f2a922;--marron:#73541a;
--tinta:#0d0d0d;--blanco:#fff;--crema:#fbf7ee;--radius:14px;--max:1160px}
```
Fuentes vía `next/font/google`: titulares `Nunito` 800, cuerpo `Nunito Sans` 400/600 (variables CSS). Texto de cuerpo sobre `--azul` solo si ≥ 18.66px bold; de lo contrario usar `--azul-900` (contraste AA).
- [ ] **Step 2:** `layout.tsx`: `<html lang="es">`, skip link "Saltar al contenido", Header (logo + nav: Inicio, Quiénes somos, Qué hacemos, Transparencia, Contacto + botón "Colabora" naranja), menú móvil accesible con `<details>`/botón `aria-expanded` (sin JS de terceros), Footer (misión, enlaces, contacto, redes, RIF, registro, créditos de fotos). `metadataBase` desde `NEXT_PUBLIC_SITE_URL`.
- [ ] **Step 3:** Páginas:
  - `/` Hero (heroImage, heroTitle, heroSubtitle, CTA "Conoce lo que hacemos" + "Colabora") → StatsBar → identidad → SectorGrid → 3 programas destacados → AllyGrid donantes → CTA banda amarilla.
  - `/quienes-somos` Hero corto → historia (HTML sanitizado) + imagen → valores (grid 4×2 con Icon) → TeamGrid (iniciales en círculo si no hay foto) → redes → AllyGrid.
  - `/que-hacemos` sectores → programas (ProgramCard con imagen, estados como chips, highlight) con ancla `#<slug>` → CoverageList (8 estados, municipios, etnias).
  - `/transparencia` intro RIF/registro → DocumentList agrupada por categoría (enlace de descarga si `file`, si no "disponible bajo solicitud") → bloque PEAS/salvaguardas → canal de denuncias (enlace a `/contacto?motivo=denuncia`).
  - `/contacto` datos, WhatsApp `https://wa.me/<whatsapp>`, enlace a Google Maps (no iframe), `ContactForm` (Task 5), sección "Cómo colaborar" (voluntariado, alianzas, donaciones en especie).
- [ ] **Step 4:** `Icon.tsx` con SVG inline propios (health, nutrition, shield, water, seed, book, heart, users, star, etc.), `aria-hidden`.
- [ ] **Step 5:** `npm run build` → OK; `npm run dev` y revisar cada ruta en 375px y 1280px (Chrome o capturas).
- [ ] **Step 6: Commit** `feat(web): diseño y páginas`

---

### Task 5: Formulario de contacto seguro

**Files:** Create: `web/lib/contact.ts`, `web/app/api/contact/route.ts`, `web/components/ContactForm.tsx` (+css), `web/tests/contact.test.ts`

**Interfaces:** Produces `validateContact(input: unknown): { ok: true; data: ContactData } | { ok: false; error: string }`, `rateLimit(key: string, now?: number): boolean` (true = permitido; 5 por 10 min), `POST /api/contact` → 200 `{ok:true}` | 400 | 413 | 429 | 503.

- [ ] **Step 1: Tests fallidos** `web/tests/contact.test.ts`
```ts
import { describe, it, expect } from 'vitest'
import { validateContact, rateLimit, _resetRateLimit } from '@/lib/contact'
const good = { name: 'Ana', email: 'ana@ejemplo.com', reason: 'informacion', message: 'Hola, quiero colaborar', website: '', startedAt: Date.now() - 5000 }
describe('validateContact', () => {
  it('acepta datos válidos', () => expect(validateContact(good).ok).toBe(true))
  it('rechaza correo inválido', () => expect(validateContact({ ...good, email: 'x' }).ok).toBe(false))
  it('rechaza vacíos', () => expect(validateContact({ ...good, name: ' ', message: '' }).ok).toBe(false))
  it('rechaza mensaje > 3000', () => expect(validateContact({ ...good, message: 'a'.repeat(3001) }).ok).toBe(false))
  it('rechaza honeypot', () => expect(validateContact({ ...good, website: 'spam.com' }).ok).toBe(false))
  it('rechaza envío en < 3 s', () => expect(validateContact({ ...good, startedAt: Date.now() }).ok).toBe(false))
  it('rechaza motivo desconocido', () => expect(validateContact({ ...good, reason: 'otro' }).ok).toBe(false))
  it('rechaza saltos de línea en nombre (inyección de cabeceras)', () => expect(validateContact({ ...good, name: 'a\r\nBcc: x@y.z' }).ok).toBe(false))
})
describe('rateLimit', () => {
  it('permite 5 y bloquea el 6º; libera a los 10 min', () => {
    _resetRateLimit(); const t = 1_000_000
    for (let i = 0; i < 5; i++) expect(rateLimit('ip', t)).toBe(true)
    expect(rateLimit('ip', t)).toBe(false)
    expect(rateLimit('ip', t + 600_001)).toBe(true)
  })
})
```
- [ ] **Step 2:** `npm test` → FAIL.
- [ ] **Step 3: Implementar** `web/lib/contact.ts`
```ts
import { z } from 'zod'
const oneLine = z.string().trim().min(2).max(100).refine(s => !/[\r\n]/.test(s))
export const ContactSchema = z.object({
  name: oneLine, email: z.string().trim().email().max(150),
  reason: z.enum(['informacion', 'voluntariado', 'alianza', 'donacion', 'denuncia']),
  message: z.string().trim().min(5).max(3000),
  website: z.string().max(0), startedAt: z.number(),
})
export type ContactData = z.infer<typeof ContactSchema>
export function validateContact(input: unknown): { ok: true; data: ContactData } | { ok: false; error: string } {
  const r = ContactSchema.safeParse(input)
  if (!r.success) return { ok: false, error: 'Datos inválidos' }
  if (Date.now() - r.data.startedAt < 3000) return { ok: false, error: 'Datos inválidos' }
  return { ok: true, data: r.data }
}
const hits = new Map<string, number[]>()
const WINDOW = 600_000, MAX = 5
export function rateLimit(key: string, now = Date.now()): boolean {
  const recent = (hits.get(key) ?? []).filter(t => now - t < WINDOW)
  if (recent.length >= MAX) { hits.set(key, recent); return false }
  recent.push(now); hits.set(key, recent); return true
}
export function _resetRateLimit() { hits.clear() }
```
`web/app/api/contact/route.ts`:
```ts
import { NextResponse } from 'next/server'
import { Resend } from 'resend'
import { validateContact, rateLimit } from '@/lib/contact'
const REASONS: Record<string, string> = { informacion: 'Información', voluntariado: 'Voluntariado', alianza: 'Alianza', donacion: 'Donación', denuncia: 'Denuncia (canal confidencial)' }
export async function POST(req: Request) {
  if (Number(req.headers.get('content-length') ?? 0) > 10_000) return NextResponse.json({ ok: false }, { status: 413 })
  const ip = req.headers.get('x-forwarded-for')?.split(',')[0]?.trim() ?? 'anon'
  if (!rateLimit(ip)) return NextResponse.json({ ok: false, error: 'Demasiados envíos, intenta más tarde' }, { status: 429 })
  const body = await req.json().catch(() => null)
  const v = validateContact(body)
  if (!v.ok) return NextResponse.json({ ok: false, error: v.error }, { status: 400 })
  const key = process.env.RESEND_API_KEY, to = process.env.CONTACT_TO
  if (!key || !to) return NextResponse.json({ ok: false, error: 'Formulario no disponible, escríbenos a fundacionenciluz@gmail.com' }, { status: 503 })
  const { name, email, reason, message } = v.data
  const { error } = await new Resend(key).emails.send({
    from: process.env.CONTACT_FROM ?? 'Web ENCILUZ <onboarding@resend.dev>', to, replyTo: email,
    subject: `[Web] ${REASONS[reason]} — ${name}`, text: `Nombre: ${name}\nCorreo: ${email}\nMotivo: ${REASONS[reason]}\n\n${message}`,
  })
  if (error) return NextResponse.json({ ok: false, error: 'No se pudo enviar' }, { status: 502 })
  return NextResponse.json({ ok: true })
}
```
`ContactForm.tsx` (client): campos con `<label>`, `required`, `maxLength`, honeypot oculto visualmente (`website`, `tabIndex={-1}`, `autoComplete="off"`, `aria-hidden`), `startedAt` al montar, preselecciona `motivo` desde `?motivo=`, mensajes de estado con `role="status"`, aviso de privacidad ("No almacenamos tus datos; se envían solo al correo de la fundación").
- [ ] **Step 4:** `npm test` → PASS.
- [ ] **Step 5: Commit** `feat(web): formulario de contacto con validación y rate limit`

---

### Task 6: Revalidación y cabeceras de seguridad

**Files:** Create: `web/app/api/revalidate/route.ts`, `web/lib/secret.ts`, `web/proxy.ts`, `web/tests/secret.test.ts`, `web/tests/proxy.test.ts`; Modify: `web/next.config.ts`

**Interfaces:** Produces `safeEqual(a: string, b: string): boolean`, `buildCsp(nonce: string, dev: boolean): string`, `POST /api/revalidate` con cabecera `x-revalidate-secret` → 200 | 401. GET → 405 (por defecto de Next al no exportar GET).

- [ ] **Step 1: Tests fallidos**
```ts
// web/tests/secret.test.ts
import { it, expect } from 'vitest'
import { safeEqual } from '@/lib/secret'
it('compara', () => { expect(safeEqual('abc', 'abc')).toBe(true); expect(safeEqual('abc', 'abd')).toBe(false); expect(safeEqual('abc', '')).toBe(false) })
// web/tests/proxy.test.ts
import { it, expect } from 'vitest'
import { buildCsp } from '@/proxy'
it('CSP estricta en prod', () => {
  const c = buildCsp('N', false)
  expect(c).toContain("script-src 'self' 'nonce-N' 'strict-dynamic'")
  expect(c).toContain("frame-ancestors 'none'"); expect(c).toContain("object-src 'none'")
  expect(c).not.toContain('unsafe-eval')
})
```
- [ ] **Step 2:** `npm test` → FAIL.
- [ ] **Step 3: Implementar**
```ts
// web/lib/secret.ts
import { timingSafeEqual, createHash } from 'node:crypto'
export function safeEqual(a: string, b: string): boolean {
  if (!a || !b) return false
  const h = (s: string) => createHash('sha256').update(s).digest()
  return timingSafeEqual(h(a), h(b))
}
// web/app/api/revalidate/route.ts
import { NextResponse } from 'next/server'
import { revalidateTag } from 'next/cache'
import { safeEqual } from '@/lib/secret'
export async function POST(req: Request) {
  if (!safeEqual(req.headers.get('x-revalidate-secret') ?? '', process.env.REVALIDATE_SECRET ?? ''))
    return NextResponse.json({ ok: false }, { status: 401 })
  revalidateTag('wp', 'max')
  return NextResponse.json({ ok: true })
}
```
```ts
// web/proxy.ts
import { NextRequest, NextResponse } from 'next/server'
export function buildCsp(nonce: string, dev: boolean): string {
  const cms = process.env.WP_API_URL ? ' ' + new URL(process.env.WP_API_URL).origin : ''
  return [
    "default-src 'self'",
    `script-src 'self' 'nonce-${nonce}' 'strict-dynamic'${dev ? " 'unsafe-eval'" : ''}`,
    "style-src 'self' 'unsafe-inline'",
    `img-src 'self' blob: data:${cms}`,
    "font-src 'self'", "connect-src 'self'", "object-src 'none'", "base-uri 'self'",
    "form-action 'self'", "frame-ancestors 'none'", 'upgrade-insecure-requests',
  ].join('; ')
}
export function proxy(request: NextRequest) {
  const nonce = Buffer.from(crypto.randomUUID()).toString('base64')
  const csp = buildCsp(nonce, process.env.NODE_ENV === 'development')
  const h = new Headers(request.headers); h.set('x-nonce', nonce); h.set('Content-Security-Policy', csp)
  const res = NextResponse.next({ request: { headers: h } })
  res.headers.set('Content-Security-Policy', csp)
  return res
}
export const config = { matcher: [{ source: '/((?!api|_next/static|_next/image|images|favicon.ico|icon.png|logo).*)', missing: [{ type: 'header', key: 'next-router-prefetch' }] }] }
```
`next.config.ts`:
```ts
import type { NextConfig } from 'next'
const security = [
  { key: 'Strict-Transport-Security', value: 'max-age=63072000; includeSubDomains; preload' },
  { key: 'X-Frame-Options', value: 'DENY' }, { key: 'X-Content-Type-Options', value: 'nosniff' },
  { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
  { key: 'Permissions-Policy', value: 'camera=(), microphone=(), geolocation=(), interest-cohort=()' },
  { key: 'Cross-Origin-Opener-Policy', value: 'same-origin' },
]
const cms = process.env.WP_API_URL ? new URL(process.env.WP_API_URL) : null
const config: NextConfig = {
  poweredByHeader: false,
  headers: async () => [{ source: '/:path*', headers: security }],
  images: { remotePatterns: cms ? [{ protocol: 'https', hostname: cms.hostname, pathname: '/wp-content/uploads/**' }] : [] },
}
export default config
```
Style-src usa `'unsafe-inline'` porque next/image y next/font generan atributos `style`; documentarlo en SECURITY.md (riesgo bajo: no hay scripts inline permitidos).
- [ ] **Step 4:** `npm test` → PASS; `npm run build && npm start` y `curl -sI localhost:3000 | grep -iE 'content-security|strict-transport|x-frame|x-powered'` → CSP, HSTS, DENY presentes; sin `x-powered-by`. `curl -s -o /dev/null -w '%{http_code}' -X POST localhost:3000/api/revalidate` → `401`; con GET → `405`.
- [ ] **Step 5: Commit** `feat(web): CSP con nonce, cabeceras de seguridad y revalidación`

---

### Task 7: WordPress headless en Docker + mu-plugin

**Files:** Create: todo `/cms` (ver File Structure).

**Interfaces:** Produces `GET /wp-json/enciluz/v1/site` con exactamente la forma de `SiteContentSchema` (Task 1); webhook POST a `${VERCEL_REVALIDATE_URL}` con `x-revalidate-secret`.

- [ ] **Step 1:** `cms/docker-compose.yml`
```yaml
services:
  db:
    image: mariadb:11
    restart: unless-stopped
    environment: { MARIADB_DATABASE: wordpress, MARIADB_USER: wp, MARIADB_PASSWORD: "${DB_PASSWORD}", MARIADB_ROOT_PASSWORD: "${DB_ROOT_PASSWORD}" }
    volumes: [ "./data/db:/var/lib/mysql" ]
  wordpress:
    image: wordpress:php8.3-apache
    restart: unless-stopped
    depends_on: [ db ]
    ports: [ "127.0.0.1:8080:80" ]
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: wp
      WORDPRESS_DB_PASSWORD: "${DB_PASSWORD}"
      WORDPRESS_DB_NAME: wordpress
      WORDPRESS_CONFIG_EXTRA: |
        define('DISALLOW_FILE_EDIT', true);
        define('WP_POST_REVISIONS', 10);
        define('ENCILUZ_REVALIDATE_URL', getenv('REVALIDATE_URL') ?: '');
        define('ENCILUZ_REVALIDATE_SECRET', getenv('REVALIDATE_SECRET') ?: '');
        define('ENCILUZ_FRONTEND_URL', getenv('FRONTEND_URL') ?: 'http://localhost:3000');
      REVALIDATE_URL: "${REVALIDATE_URL}"
      REVALIDATE_SECRET: "${REVALIDATE_SECRET}"
      FRONTEND_URL: "${FRONTEND_URL}"
    volumes: [ "./data/wp:/var/www/html", "./mu-plugins:/var/www/html/wp-content/mu-plugins:ro" ]
  cli:
    image: wordpress:cli-php8.3
    profiles: [ cli ]
    depends_on: [ db, wordpress ]
    user: "33:33"
    environment: { WORDPRESS_DB_HOST: db, WORDPRESS_DB_USER: wp, WORDPRESS_DB_PASSWORD: "${DB_PASSWORD}", WORDPRESS_DB_NAME: wordpress }
    volumes: [ "./data/wp:/var/www/html", "./mu-plugins:/var/www/html/wp-content/mu-plugins:ro", "./scripts:/scripts:ro", "../web/content:/seed:ro", "../web/public/images:/seed-images:ro" ]
```
Puerto ligado a `127.0.0.1`. `.env.example` con `DB_PASSWORD=`, `DB_ROOT_PASSWORD=`, `REVALIDATE_URL=`, `REVALIDATE_SECRET=`, `FRONTEND_URL=http://localhost:3000`. Generar `.env` real con `openssl rand -hex 24`.
- [ ] **Step 2:** `cpt.php`: registrar CPTs `programa`, `sector`, `miembro`, `aliado`, `documento` (`public => false`, `show_ui => true`, `show_in_rest => false`, `menu_icon` dashicons, etiquetas en español, `supports` title/editor/thumbnail/page-attributes según tipo). Meta boxes nativos con `register_post_meta` + `add_meta_box` + `save_post_<cpt>` con nonce `wp_nonce_field`, `current_user_can('edit_post')`, `sanitize_text_field`/`esc_url_raw`/`absint`. Campos: programa (`resumen`, `estados` csv, `destacado`), sector (`icono` select de 6 valores, `resumen`), miembro (`cargo`), aliado (`tipo` select donante/red, `url`), documento (`categoria` select, `archivo_id` vía `wp.media`). Cada campo con texto de ayuda.
- [ ] **Step 3:** `settings.php`: menú "Ajustes ENCILUZ" (capability `edit_pages` para que el Editor acceda) con Settings API; una opción `enciluz_settings` (array) con todos los campos de `SettingsSchema`, 4 pares de cifras, `valores` (textarea: `Título | texto` por línea), `cobertura` (textarea: `Estado: mun1, mun2 | etnia1, etnia2`), `redes` (una por línea), `historia_page_id` (select de páginas). `sanitize_callback` que limpia cada campo.
- [ ] **Step 4:** `api.php`: `register_rest_route('enciluz/v1','/site',['methods'=>'GET','permission_callback'=>'__return_true','callback'=>...])` que arma el JSON con la forma exacta de `SiteContentSchema` (imágenes → URL absoluta de `wp_get_attachment_image_url(id,'large')`; `order` desde `menu_order`; `body` y `history` con `apply_filters('the_content')` + `wp_kses_post`). Cabecera `Cache-Control: public, max-age=60`.
- [ ] **Step 5:** `hardening.php`:
```php
<?php
defined('ABSPATH') || exit;
add_filter('xmlrpc_enabled', '__return_false');
add_filter('wp_headers', fn($h) => array_diff_key($h, ['X-Pingback' => 1]));
add_filter('rest_endpoints', function ($e) {
  if (!is_user_logged_in()) { foreach (array_keys($e) as $r) if (str_starts_with($r, '/wp/v2/users')) unset($e[$r]); }
  return $e;
});
add_filter('rest_authentication_errors', function ($r) {
  if (!empty($r) || is_user_logged_in()) return $r;
  $route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
  return (str_starts_with($route, '/enciluz/v1') || $route === '/' || $route === '') ? $r
    : new WP_Error('rest_forbidden', 'No autorizado', ['status' => 401]);
});
remove_action('wp_head', 'wp_generator'); add_filter('the_generator', '__return_empty_string');
add_action('template_redirect', function () {           // front público → sitio Next
  if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) return;
  if (isset($_GET['author'])) { status_header(404); exit; }
  wp_redirect(ENCILUZ_FRONTEND_URL, 301); exit;
});
add_action('init', function () {                        // Editor sin HTML sin filtrar
  if ($role = get_role('editor')) $role->remove_cap('unfiltered_html');
});
add_action('send_headers', function () {
  header('X-Frame-Options: SAMEORIGIN'); header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: strict-origin-when-cross-origin');
});
add_filter('upload_mimes', fn($m) => array_intersect_key($m, array_flip(['jpg|jpeg|jpe','png','webp','pdf'])));
```
- [ ] **Step 6:** `webhook.php`: en `save_post` (tipos `programa, sector, miembro, aliado, documento, page`, no autosave/revisión) y `update_option_enciluz_settings`, `wp_remote_post(ENCILUZ_REVALIDATE_URL, ['headers'=>['x-revalidate-secret'=>ENCILUZ_REVALIDATE_SECRET],'timeout'=>5,'blocking'=>false])` si ambos constantes no están vacías. Una sola llamada por request (flag estático).
- [ ] **Step 7:** `scripts/setup.sh` (vía `docker compose run --rm cli bash /scripts/setup.sh`): `wp core install` (url `http://localhost:8080`, admin con contraseña aleatoria mostrada una vez), `wp plugin install two-factor wps-hide-login limit-login-attempts-reloaded --activate`, `wp option update whl_page acceso-enciluz`, `wp plugin delete akismet hello`, `wp theme delete` temas extra salvo uno, `wp rewrite structure '/%postname%/'`, crear usuario `cliente` rol `editor`, `wp eval-file /scripts/seed.php`. `seed.php` lee `/seed/fallback.json`, importa imágenes de `/seed-images` con `media_handle_sideload` y crea posts/opciones (idempotente: no duplica si ya existe título).
- [ ] **Step 8:** Levantar: `cd cms && docker compose up -d && docker compose run --rm cli bash /scripts/setup.sh`. Verificar `curl -s localhost:8080/wp-json/enciluz/v1/site | head -c 300`.
- [ ] **Step 9:** Contrato: `cd web && WP_API_URL=http://localhost:8080 node --experimental-strip-types -e "..."` o test vitest opcional `tests/wp-contract.test.ts` (salta si no hay `WP_CONTRACT=1`) que hace fetch real y `expect(SiteContentSchema.safeParse(json).success).toBe(true)`. Ejecutar con `WP_CONTRACT=1 npm test` → PASS.
- [ ] **Step 10: Commit** `feat(cms): WordPress headless en Docker con mu-plugin`

---

### Task 8: Pruebas de seguridad de integración WP

**Files:** Create: `cms/tests/security.sh`

- [ ] **Step 1:**
```bash
#!/usr/bin/env bash
set -u; B=${1:-http://localhost:8080}; fail=0
check(){ [ "$2" = "$3" ] && echo "OK  $1" || { echo "FAIL $1 (esperado $3, obtenido $2)"; fail=1; }; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
check "users API bloqueada"      "$(code $B/wp-json/wp/v2/users)" 401
check "xmlrpc deshabilitado"     "$(curl -s -X POST -d '<methodCall><methodName>demo.sayHello</methodName></methodCall>' $B/xmlrpc.php | grep -c 'XML-RPC services are disabled')" 1
check "author enum 404"          "$(code $B/?author=1)" 404
check "front redirige"           "$(code $B/)" 301
check "wp-login no responde 200" "$([ "$(code $B/wp-login.php)" = 200 ] && echo expuesto || echo oculto)" oculto
check "login real existe"        "$(code $B/acceso-enciluz/)" 200
check "endpoint enciluz OK"      "$(code $B/wp-json/enciluz/v1/site)" 200
check "posts API bloqueada"      "$(code $B/wp-json/wp/v2/posts)" 401
check "sin versión en feed"      "$(curl -s $B/wp-json/ | grep -c 'generator')" 0
exit $fail
```
- [ ] **Step 2:** `bash cms/tests/security.sh` → todas OK (ajustar mu-plugin si alguna falla y repetir).
- [ ] **Step 3: Commit** `test(cms): pruebas de seguridad de integración`

---

### Task 9: Revisión UX y accesibilidad (subagente experto UX)

- [ ] **Step 1:** Despachar subagente "experto en experiencia de usuario para ONGs" con acceso al código y al sitio en `npm start`: revisar jerarquía, CTA, navegación móvil, contraste de la paleta, foco visible, textos alternativos, orden de encabezados, tamaños táctiles ≥ 44px. Devuelve lista priorizada.
- [ ] **Step 2:** Aplicar hallazgos críticos/altos.
- [ ] **Step 3:** Lighthouse: `npx lighthouse http://localhost:3000 --only-categories=accessibility,best-practices,seo,performance --chrome-flags="--headless" --quiet --output=json` en `/` y `/que-hacemos` → accesibilidad ≥ 95.
- [ ] **Step 4: Commit** `fix(web): mejoras UX y accesibilidad`

---

### Task 10: SECURITY.md, README y despliegue en Vercel

**Files:** Create: `SECURITY.md`, `README.md`, `cms/README-migracion-godaddy.md`

- [ ] **Step 1:** `SECURITY.md`: arquitectura y superficie de ataque, tabla de controles (cabecera/control → dónde se implementa → cómo verificarlo), decisión `style-src 'unsafe-inline'` justificada, rate limit en memoria (limitación: por instancia; mitigado con honeypot + tiempo mínimo; mejora: Vercel Firewall/KV), gestión de secretos, roles WP, checklist GoDaddy (SSL, PHP 8.3, backups, actualizaciones automáticas, 2FA obligatorio, cambiar URL de login, `.env`/wp-config fuera de git), cómo reportar vulnerabilidades.
- [ ] **Step 2:** `README.md`: estructura, cómo correr (`cms` y `web`), variables de entorno (`WP_API_URL`, `REVALIDATE_SECRET`, `RESEND_API_KEY`, `CONTACT_TO`, `CONTACT_FROM`, `NEXT_PUBLIC_SITE_URL`), guía corta para el cliente (qué editar dónde). `cms/README-migracion-godaddy.md`: pasos de migración y configuración de variables en Vercel.
- [ ] **Step 3:** Revisión de seguridad final: skill `security-review` sobre el repo; corregir hallazgos.
- [ ] **Step 4:** Despliegue: `cd web && npx vercel@latest link` (usuario confirma cuenta/proyecto) → `npx vercel@latest deploy` (preview). Sin `WP_API_URL` todavía (fallback). Verificar `curl -sI <url>` cabeceras y navegar las 5 páginas.
- [ ] **Step 5: Commit** `docs: SECURITY.md, README y guía de migración`

---

## Self-review

- Cobertura spec: arquitectura (T1, T7), modelo de contenido (T1, T7), páginas (T4), diseño (T3, T4), seguridad front (T5, T6), seguridad WP (T7, T8), agentes ONG/UX (T2, T9), SECURITY.md (T10), Vercel (T10), verificación Lighthouse (T9). Sin huecos.
- Nombres consistentes: `getContent`, `parseContent`, `SiteContentSchema`, tag `wp`, cabecera `x-revalidate-secret`, `enciluz/v1/site`.
- Review Focus cubierto por tests en T1, T5, T6, T8.
