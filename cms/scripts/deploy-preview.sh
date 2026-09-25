#!/usr/bin/env bash
# Exporta el WordPress local y publica la vista previa en Vercel.
# Requiere: stack arriba (cd cms && docker compose up -d) y `npx vercel login` hecho una vez.
set -euo pipefail
cd "$(dirname "$0")/../.."
python3 cms/scripts/export.py
cd preview
npx --yes vercel@latest deploy --prod --yes --name enciluz-preview
