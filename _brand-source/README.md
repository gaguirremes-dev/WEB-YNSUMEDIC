# Brand source files

Masters de marca (6000x6000, fondo transparente). **No se despliegan**: `cpanel.yml`
elimina esta carpeta del `public_html` después del `cp`.

Los assets que consume el sitio se generan desde acá y viven en `assets/logos/` y
`assets/img/`:

| Master | Asset publicado | Tamaño |
| --- | --- | --- |
| `icon-master-6000.png` | `assets/logos/favicon.ico`, `favicon-32.png`, `apple-touch-icon.png`, `icon-192.png`, `icon-512.png`, `ynsumedic-mark.png`, `ynsumedic-mark-white.png` | 32-512 px |
| `logo-master-6000.png` | `assets/logos/logo-ynsumedic.png` (isotipo + wordmark, transparente) | 788x658 |
| `logo-master-6000.png` | `assets/img/og-cover.jpg` (portada para redes, 1200x630) | 1200x630 |

Para regenerar la portada y el logo completo:
`python scripts/build-brand-assets.py` (requiere Pillow + numpy).
