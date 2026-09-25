# Seguridad — Sitio web de la Fundación Enciende una Luz (ENCILUZ)

Documento para auditoría. Describe la superficie de ataque, los controles aplicados, cómo verificarlos
y las decisiones tomadas con su justificación.

## 1. Arquitectura y superficie de ataque

| Componente | Qué expone | Notas |
|---|---|---|
| WordPress 7.x (producción: GoDaddy) | Sitio público, `/acceso-enciluz/` (login), panel `/wp-admin/` solo con sesión | Tema propio `enciluz` (sin page builders) y mu-plugin `enciluz-core` |
| Plugins | Two Factor, WPS Hide Login, Limit Login Attempts Reloaded | No hay más plugins activos (verificado por prueba automática) |
| Vista previa (Vercel) | HTML estático, sin PHP ni base de datos | Solo para revisión del cliente; `noindex` |

No se cargan scripts, fuentes, iframes ni analíticas de terceros. Las fuentes y las imágenes se sirven desde el propio dominio.

## 2. Controles

| Control | Implementación | Verificación |
|---|---|---|
| XML-RPC deshabilitado (sin métodos, sin pingbacks) | `cms/mu-plugins/enciluz/hardening.php` + `.htaccess` deniega `xmlrpc.php` | `security.sh`: «XML-RPC deshabilitado» |
| Sin enumeración de usuarios (`/wp-json/wp/v2/users`, `?author=N`, archivos de autor, sitemap de usuarios) | `hardening.php` | `security.sh`: 4 pruebas |
| Login en URL no estándar; `/wp-login.php` y `/wp-admin/` no revelan la ruta | WPS Hide Login (`whl_page=acceso-enciluz`, redirección a 404) | `security.sh` |
| Límite de intentos de acceso | Limit Login Attempts Reloaded | Configuración del plugin |
| Doble factor (2FA) | Plugin Two Factor (TOTP). **Obligatorio activarlo en cada cuenta al entregar** | Perfil de usuario → «Opciones de dos factores» |
| Sin versión de WordPress visible (meta generator, `?ver=` de núcleo, `readme.html`, `license.txt`) | `hardening.php`, `.htaccess`, `setup.sh` borra los archivos | `security.sh`: 4 pruebas |
| Sin edición de código desde el panel | `DISALLOW_FILE_EDIT` | `security.sh` |
| Nadie puede publicar HTML sin filtrar (`<script>`, `onerror=`…), ni el administrador | `DISALLOW_UNFILTERED_HTML`; el contenido pasa por `wp_kses` | `security.sh`: inserta `<script>` como editor y comprueba que se elimina |
| Subidas limitadas a JPG, PNG, WebP y PDF | Filtro `upload_mimes` | `security.sh` |
| Nunca se ejecuta PHP dentro de `uploads/` | `wp-content/uploads/.htaccess` | Revisión de archivo |
| Archivos sensibles denegados (`wp-config.php`, `.htaccess`, `*.sql`, `*.log`, `*.bak`, `*.ini`, `*.sh`) y sin listado de directorios | `cms/scripts/htaccess` | Revisión de archivo |
| Comentarios y pingbacks deshabilitados | `hardening.php`, opciones en `setup.sh` | — |
| Cabeceras: CSP con nonce por petición, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, `COOP`, HSTS en HTTPS, sin `X-Powered-By` | `cms/mu-plugins/enciluz/headers.php` | `security.sh`: 6 pruebas |
| Formulario de contacto: nonce, token firmado con HMAC y tiempo mínimo de 3 s, honeypot, límite de 5 envíos cada 10 min por IP (hash de la IP, no se guarda en claro), validación de longitud y formato, bloqueo de saltos de línea (inyección de cabeceras), tamaño máximo de 10 KB, redirección POST→GET | `cms/mu-plugins/enciluz/contact.php` | `contact.sh`: 29 pruebas |
| Protección de datos: el formulario **no guarda** mensajes ni datos personales; solo envía un correo al destinatario configurado | `contact.php` | `contact.sh`: «no guarda datos personales» |
| Base de datos y panel no expuestos en local | Docker publica solo `127.0.0.1:8088` | `docker-compose.yml` |
| Secretos fuera del repositorio | `cms/.env` y `CREDENCIALES-LOCAL.txt` ignorados por git; claves generadas aleatoriamente | `.gitignore` |
| Vista previa estática con CSP basada en hashes SHA-256 de cada script en línea, HSTS, `DENY`, `noindex` | `cms/scripts/export.py` genera `preview/vercel.json` | `curl -sI <url de vercel>` |

## 3. Decisiones y riesgos aceptados

1. **`style-src 'unsafe-inline'`**: el editor de bloques genera estilos en línea (colores y espaciados que el
   cliente elige). Bloquearlos rompería la edición. El riesgo es bajo porque **los scripts sí están restringidos**
   (nonce por petición en WordPress y hashes en la vista previa), y la inyección de estilos no ejecuta código.
2. **En el panel (`/wp-admin/`) no se aplica la CSP estricta**, porque el editor de WordPress la necesita más
   permisiva. Mitigación: login oculto, 2FA, límite de intentos y cuentas con el mínimo rol necesario.
3. **El límite de envíos del formulario usa transients de WordPress**: si una red distribuye el ataque entre
   muchas IP, el límite por IP no basta. Mitigación: honeypot, token con tiempo mínimo y nonce. Como mejora
   opcional se puede añadir un firewall de aplicación del hosting.
4. **La vista previa en Vercel no procesa el formulario** (no tiene PHP): muestra un aviso con el correo y el
   WhatsApp. El sitio definitivo en GoDaddy sí lo procesa.

## 4. Cuentas y roles

- `enciluz-admin` (Administrador): solo para el equipo técnico. 2FA obligatorio.
- `cliente` (Editor): la fundación edita páginas, imágenes y menús. No puede instalar plugins, cambiar temas,
  editar código ni publicar HTML sin filtrar. 2FA obligatorio.
- El correo que recibe el formulario se cambia en **Ajustes → Generales** (lo edita el administrador).

## 5. Cómo ejecutar las pruebas

```bash
cd cms && docker compose up -d
bash tests/security.sh     # 24 pruebas de endurecimiento
bash tests/content.sh      # 45 pruebas de contenido
bash tests/contact.sh      # 29 pruebas del formulario
python3 scripts/export.py  # exporta y verifica la vista previa
```

Contra producción: `bash tests/security.sh https://enciluz.com` (las pruebas con wp-cli requieren acceso al servidor).

## 6. Mantenimiento

- Actualizaciones menores de WordPress automáticas (`WP_AUTO_UPDATE_CORE = minor`). Activar actualizaciones
  automáticas de los 3 plugins en GoDaddy.
- Copias de seguridad diarias del hosting (base de datos + `wp-content/uploads`).
- Revisar cada trimestre: usuarios activos, 2FA en todas las cuentas, plugins sin actualizar.

## 7. Reporte de vulnerabilidades

Escribir a fundacionenciluz@gmail.com con el asunto «Seguridad web». Se responde en un máximo de 5 días hábiles.
