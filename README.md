# Sitio web — Fundación Enciende una Luz (ENCILUZ)

Sitio WordPress con tema de bloques propio: **todo el contenido se edita desde el editor de WordPress**
(textos, imágenes, secciones, orden, menús, cabecera y pie). Vista previa estática en Vercel para el cliente.

```
cms/
  docker-compose.yml        WordPress + MariaDB + wp-cli (solo en 127.0.0.1:8088)
  theme/enciluz/            tema de bloques (theme.json, plantillas, patrones, estilos, fuentes)
  mu-plugins/               seguridad, cabeceras y formulario de contacto
  seed/                     contenido del briefing (content.json) e imágenes libres (CREDITS.md)
  scripts/                  setup.sh, seed.php, export.py, deploy-preview.sh, htaccess
  tests/                    security.sh, content.sh, contact.sh
docs/                       guía del cliente, migración a GoDaddy, spec y plan
SECURITY.md                 controles de seguridad para la auditoría
```

## Poner en marcha (local)

```bash
cd cms
cp .env.example .env                                  # y pon claves aleatorias
docker compose up -d
docker compose run --rm --no-deps -T cli bash /scripts/setup.sh   # instala, endurece y siembra el contenido
```

Sitio: http://localhost:8088 · Acceso: http://localhost:8088/acceso-enciluz/

Para reescribir las páginas sembradas desde `seed/content.json` (borra los cambios hechos en esas páginas):
`docker compose run --rm --no-deps -T -e ENCILUZ_SEED_FORCE=1 cli wp eval-file /scripts/seed.php`

## Vista previa en Vercel

```bash
npx vercel login              # una sola vez
bash cms/scripts/deploy-preview.sh
```

## Pruebas

`bash cms/tests/security.sh && bash cms/tests/content.sh && bash cms/tests/contact.sh`

Más información: [Guía del cliente](docs/GUIA-CLIENTE.md) · [Migración a GoDaddy](docs/MIGRACION-GODADDY.md) · [Seguridad](SECURITY.md)
