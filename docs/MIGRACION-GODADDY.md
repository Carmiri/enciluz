# Migración a GoDaddy (producción)

Requisitos del plan: WordPress administrado o cPanel, PHP 8.3, MySQL/MariaDB, SSL activo.

1. **Crear WordPress** en GoDaddy con el dominio (p. ej. `enciluz.com`). Forzar HTTPS.
2. **Subir el tema y los mu-plugins** por SFTP/Administrador de archivos:
   - `cms/theme/enciluz/` → `wp-content/themes/enciluz/`
   - `cms/mu-plugins/` → `wp-content/mu-plugins/` (el archivo `enciluz-core.php` y la carpeta `enciluz/`)
3. **wp-config.php** (añadir antes de «That's all»):
   ```php
   define('DISALLOW_FILE_EDIT', true);
   define('DISALLOW_UNFILTERED_HTML', true);
   define('WP_POST_REVISIONS', 10);
   define('WP_AUTO_UPDATE_CORE', 'minor');
   define('WP_ENVIRONMENT_TYPE', 'production');
   ```
   Asegúrate de que las claves y sales (`AUTH_KEY`, etc.) sean únicas: https://api.wordpress.org/secret-key/1.1/salt/
4. **.htaccess**: reemplazar el de la raíz por `cms/scripts/htaccess` y copiar `cms/scripts/uploads-htaccess`
   como `wp-content/uploads/.htaccess`. Borrar `readme.html`, `license.txt` y `wp-config-sample.php`.
5. **Contenido**: la forma más simple es exportar desde el local con el plugin *All-in-One WP Migration*
   (instalarlo solo durante la migración y **desinstalarlo después**) o con `wp db export` + `wp search-replace
   'http://localhost:8088' 'https://enciluz.com'` y subir `wp-content/uploads/`.
6. **Plugins**: instalar y activar solo Two Factor, WPS Hide Login (URL `acceso-enciluz`) y
   Limit Login Attempts Reloaded. Activar sus actualizaciones automáticas. Borrar cualquier otro plugin que traiga
   GoDaddy por defecto que no se use.
7. **Cuentas**: crear las cuentas definitivas, activar 2FA en todas, borrar las de prueba.
8. **Correo**: configurar en **Ajustes → Generales** el correo del formulario. Si GoDaddy no entrega correos con
   `wp_mail`, configurar SMTP del dominio con su herramienta de correo o un plugin SMTP liviano.
9. **Verificar**: `bash cms/tests/security.sh https://enciluz.com` (las pruebas HTTP deben pasar) y revisar
   https://securityheaders.com.
10. **Copias de seguridad** diarias en el panel de GoDaddy.

La vista previa de Vercel se puede dar de baja cuando el sitio esté en producción.
