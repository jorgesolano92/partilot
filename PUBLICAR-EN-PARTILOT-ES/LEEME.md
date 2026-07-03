# PUBLICAR EN partilot.es

**Contenido de esta carpeta = subir al VPS de `partilot.es` (document root del dominio).**

No subir al VPS del panel (`panel.partilot.es`). El panel solo expone la API JSON.

---

## Requisito previo (panel.partilot.es)

Desplegar en el **panel** la ruta:

```
GET https://panel.partilot.es/api/public/participation-check?ref=...&sig=...
```

Respuesta JSON: `{ "success": true|false, "error": "...", "ticket": { ... } }`

(Código en repo Laravel: `ApiController::publicParticipationCheckJson` + `ParticipationPublicCheckService`.)

---

## Instalación en partilot.es

1. Copiar **todo el contenido** de `PUBLICAR-EN-PARTILOT-ES/` al document root de `partilot.es`.
2. Copiar `config.example.php` → `config.php` y revisar URLs.
3. Dar permisos de escritura a `data/` (caché + SQLite de bloqueos IP):
   ```bash
   chmod -R 0755 data
   chown -R www-data:www-data data
   ```
4. PHP **8.1+** con extensiones: `pdo_sqlite`, `json`, `openssl`.
5. Comprobar URL:
   - Sin ref: `https://partilot.es/comprobar-participaciones/` → mensaje app / QR
   - Con QR: `https://partilot.es/comprobar-participaciones/?ref=...&sig=...` → resultado

### Estructura en el servidor

```
/var/www/partilot.es/          (ejemplo)
├── .htaccess
├── config.php
├── comprobar-participaciones/
│   ├── index.php
│   └── .htaccess
├── lib/
├── data/                      ← escribible
│   ├── cache/
│   └── ip_blocks.sqlite
├── .well-known/
│   ├── assetlinks.json
│   └── apple-app-site-association
└── deeplinks-app/             ← referencia para la app (no servir al público)
```

---

## Configuración (`config.php`)

| Clave | Descripción |
|-------|-------------|
| `panel_api_url` | API del panel (por defecto `https://panel.partilot.es/api/public/participation-check`) |
| `cache_ttl` | Segundos de caché de resultados válidos (60) |
| `app_package` | `com.partilot.app` (Play + App Store) |
| `ip_block_steps` | `[60, 300, 600]` segundos; el 4.º fallo = **permanente** |

---

## Bloqueo IP (sin panel admin)

Escalado por intentos con referencia inválida:

1. 1 min → 2. 5 min → 3. 10 min → 4. **Permanente**

Desbloqueo manual (SQLite en el VPS):

```bash
sqlite3 data/ip_blocks.sqlite "DELETE FROM ip_blocks WHERE ip = '1.2.3.4';"
```

---

## Deep links (App Links)

**Paquete app:** `com.partilot.app` (Android + iOS)

### Android

1. Obtener SHA-256 del keystore release:
   ```bash
   keytool -list -v -keystore partilot.jks -alias partilot
   ```
2. Pegar fingerprint en `.well-known/assetlinks.json`
3. Pegar `deeplinks-app/AndroidManifest-intent-filter.xml` en la app
4. Verificar: https://digitalassetlinks.googleapis.com/v1/statements:list?source.web.site=https://partilot.es&relation=delegate_permission/common.handle_all_urls

### iOS

1. Sustituir `REEMPLAZAR_TEAM_ID` en `.well-known/apple-app-site-association`
2. Añadir Associated Domain `applinks:partilot.es` (ver `deeplinks-app/iOS-Associated-Domains.txt`)
3. El archivo debe servirse como JSON sin redirect en:
   - `https://partilot.es/.well-known/apple-app-site-association`

### App Ionic (fase 5 simplificada)

Ver `deeplinks-app/app.component-snippet.ts`:

- Con sesión → reutilizar flujo existente (p. ej. tabs/cartera)
- Sin sesión → `/registro` (sin guardar `ref`)

---

## Redirects

`.htaccess` en raíz redirige `/comprobar-participacion` → `/comprobar-participaciones`.

Los QR ya generados apuntan a `comprobar-participaciones` vía `PARTICIPATION_QR_PUBLIC_URL`.

---

## Notas

- **No hay iframe:** la comprobación se renderiza en este VPS; el panel solo devuelve datos JSON.
- Si la app está instalada y App Links verificados, al abrir el URL del QR el sistema puede abrir **com.partilot.app** directamente (sin pasar por el navegador).
- Actualizar `app_store_url` en `config.php` cuando tengáis el ID real de App Store.
