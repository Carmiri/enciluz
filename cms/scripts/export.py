#!/usr/bin/env python3
"""Exporta el WordPress local a HTML estático en /preview para la vista previa en Vercel.

Uso: python3 cms/scripts/export.py [URL_BASE]   (por defecto http://localhost:8088)

- Recorre las páginas públicas siguiendo enlaces internos.
- Descarga CSS, JS, fuentes e imágenes referenciadas (src, srcset, href, url()).
- Quita huellas de WordPress (enlaces a wp-json, nonces, etc.).
- Reemplaza el formulario de contacto por un aviso (en la vista previa no hay PHP).
- Genera preview/vercel.json con cabeceras de seguridad y CSP basada en hashes.
- Falla si queda cualquier referencia al WordPress local o a rutas de administración.
"""
import base64
import hashlib
import json
import re
import shutil
import sys
import urllib.request
from pathlib import Path
from urllib.parse import urljoin, urlparse

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://localhost:8088").rstrip("/")
ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "preview"
START = ["/", "/quienes-somos/", "/que-hacemos/", "/transparencia/", "/contacto/", "/creditos/", "/pagina-que-no-existe/"]
SKIP = re.compile(r"^/(wp-admin|wp-json|acceso-enciluz|xmlrpc\.php|wp-login\.php|feed|comments|wp-sitemap|\?)|/feed/?$")
ASSET_RE = re.compile(r"""(?:src|href|content)=["']([^"']+)["']|url\(\s*["']?([^"')]+)["']?\s*\)|srcset=["']([^"']+)["']""")

FORM_NOTICE = (
    '<p class="enciluz-aviso enciluz-aviso--ok" role="status">'
    "Esta es una vista previa: el formulario funcionará en el sitio definitivo. "
    'Mientras tanto, escríbenos a <a href="mailto:fundacionenciluz@gmail.com">fundacionenciluz@gmail.com</a> '
    'o por <a href="https://wa.me/584265101702">WhatsApp</a>.</p>'
)


def fetch(url: str) -> tuple[int, bytes, str]:
    req = urllib.request.Request(url, headers={"User-Agent": "enciluz-export"})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status, r.read(), r.headers.get("Content-Type", "")
    except urllib.error.HTTPError as e:
        return e.code, e.read(), e.headers.get("Content-Type", "")


def local_path(path: str) -> str | None:
    """Convierte una URL a ruta local si pertenece al sitio; None si es externa."""
    if path.startswith(("mailto:", "tel:", "data:", "#", "javascript:")):
        return None
    u = urlparse(urljoin(BASE + "/", path))
    if u.netloc and u.netloc != urlparse(BASE).netloc:
        return None
    return u.path or "/"


def save(path: str, data: bytes) -> None:
    dest = OUT / path.lstrip("/")
    if path.endswith("/"):
        dest = dest / "index.html"
    dest.parent.mkdir(parents=True, exist_ok=True)
    dest.write_bytes(data)


def clean_html(html: str) -> str:
    html = re.sub(r'<link rel="(?:https://api\.w\.org/|alternate)"[^>]*>\s*', "", html)
    html = re.sub(r'<link[^>]+wp-json[^>]*>\s*', "", html)
    html = re.sub(r'<link rel="shortlink"[^>]*>\s*', "", html)
    html = re.sub(r'\snonce="[^"]*"', "", html)
    html = re.sub(r'<script type="speculationrules">.*?</script>\s*', "", html, flags=re.S)
    html = re.sub(r'<form class="enciluz-form".*?</form>', FORM_NOTICE, html, flags=re.S)
    html = html.replace(BASE, "").replace(BASE.replace("/", "\\/"), "")
    html = html.replace("<head>", '<head>\n<meta name="robots" content="noindex, nofollow">', 1)
    return html


def main() -> int:
    if OUT.exists():
        for child in OUT.iterdir():
            shutil.rmtree(child) if child.is_dir() else child.unlink()
    OUT.mkdir(exist_ok=True)

    pages, assets, seen = list(START), set(), set()
    inline_hashes: set[str] = set()
    while pages:
        page = pages.pop(0)
        if page in seen or SKIP.search(page):
            continue
        seen.add(page)
        status, body, ctype = fetch(BASE + page)
        if "text/html" not in ctype:
            continue
        html = body.decode("utf-8")
        if page == "/pagina-que-no-existe/":
            save("/404.html", clean_html(html).encode())
            html_for_links = html
        elif status != 200:
            print(f"AVISO {page} → {status}")
            continue
        else:
            html_for_links = html
            save(page, clean_html(html).encode())
        for m in re.finditer(r"<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>", clean_html(html), re.S):
            if m.group(1).strip():
                inline_hashes.add("'sha256-" + base64.b64encode(hashlib.sha256(m.group(1).encode()).digest()).decode() + "'")
        for m in ASSET_RE.finditer(html_for_links):
            for raw in filter(None, m.groups()):
                for part in raw.split(","):
                    ref = part.strip().split(" ")[0]
                    lp = local_path(ref)
                    if not lp or SKIP.search(lp):
                        continue
                    if lp.startswith(("/wp-content/", "/wp-includes/")):
                        assets.add(lp)
                    elif lp.endswith("/") and lp not in seen:
                        pages.append(lp)

    done: set[str] = set()
    while assets:
        a = assets.pop()
        if a in done:
            continue
        done.add(a)
        status, body, ctype = fetch(BASE + a)
        if status != 200:
            print(f"FALTA {a} → {status}")
            return 1
        if a.endswith(".css"):
            css = body.decode("utf-8")
            for m in re.finditer(r"url\(\s*[\"']?([^\"')]+)[\"']?\s*\)", css):
                lp = local_path(urljoin(BASE + a, m.group(1)))
                if lp and lp.startswith(("/wp-content/", "/wp-includes/")) and lp not in done:
                    assets.add(lp)
            body = css.replace(BASE, "").encode()
        save(a, body)

    csp = "; ".join([
        "default-src 'self'",
        "script-src 'self' " + " ".join(sorted(inline_hashes)),
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data:",
        "font-src 'self' data:",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "upgrade-insecure-requests",
    ])
    vercel = {
        "$schema": "https://openapi.vercel.sh/vercel.json",
        "cleanUrls": True,
        "trailingSlash": True,
        "headers": [{
            "source": "/(.*)",
            "headers": [
                {"key": "Content-Security-Policy", "value": csp},
                {"key": "Strict-Transport-Security", "value": "max-age=63072000; includeSubDomains"},
                {"key": "X-Frame-Options", "value": "DENY"},
                {"key": "X-Content-Type-Options", "value": "nosniff"},
                {"key": "Referrer-Policy", "value": "strict-origin-when-cross-origin"},
                {"key": "Permissions-Policy", "value": "camera=(), microphone=(), geolocation=(), payment=(), usb=()"},
                {"key": "Cross-Origin-Opener-Policy", "value": "same-origin"},
                {"key": "X-Robots-Tag", "value": "noindex, nofollow"},
            ],
        }],
    }
    (OUT / "vercel.json").write_text(json.dumps(vercel, indent=2, ensure_ascii=False))

    # Verificaciones: nada del WordPress local ni rutas privadas; todo recurso local existe.
    problems = []
    for f in OUT.rglob("*"):
        if f.suffix not in {".html", ".css", ".js"}:
            continue
        text = f.read_text("utf-8", errors="ignore")
        for bad in ("localhost:8088", "/wp-admin/", "/wp-json", "acceso-enciluz", "xmlrpc", 'nonce="'):
            if bad in text:
                problems.append(f"{f.relative_to(OUT)} contiene {bad}")
        if f.suffix == ".html":
            for m in re.finditer(r"""(?:src|href)=["'](/[^"'#?]*)""", text):
                p = m.group(1)
                target = OUT / p.lstrip("/")
                if not (target.exists() or (target / "index.html").exists() or target.with_suffix(".html").exists()):
                    problems.append(f"{f.relative_to(OUT)} enlaza a {p}, que no existe")
    for p in sorted(set(problems)):
        print("ERROR", p)
    n_pages = len(list(OUT.rglob("*.html")))
    print(f"Exportadas {n_pages} páginas y {len(done)} recursos en {OUT}")
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())
