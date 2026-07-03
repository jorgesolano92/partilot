# Plan: comprobación pública por QR, anti-abuso y deep links

Documento de trabajo para el flujo **externo** (partilot.es) vs **panel** (panel.partilot.es) vs **app** (com.partilot.app).

**Fecha:** 2026-07-03 (actualizado)  
**Repos:** Laravel `sipart` (panel) · Ionic `sipart` (app) · VPS `partilot.es` (PHP standalone)

---

## Decisiones cerradas

| Tema | Decisión |
|------|----------|
| Infraestructura | **`partilot.es` y `panel.partilot.es` son VPS distintos** — no comparten Laravel |
| Comprobación web | En **partilot.es** (PHP propio), **sin iframe** al panel |
| Panel | Solo **API JSON** (`/api/public/participation-check`) |
| Landing sin `ref` | Mensaje: descargar app o escanear QR — **sin input manual** |
| Caché | **60 s** en partilot.es (resultados válidos) |
| Bloqueo IP | 1 min → 5 min → 10 min → **permanente** — **sin desbloqueo admin** (solo SQL manual) |
| App stores | Paquete **`com.partilot.app`** (Android + iOS) |
| Deep link sin sesión | Ir a **registro** — **no guardar ref** post-registro |
| Deep link con sesión | **Reutilizar flujo existente** de la app |
| Fases 0–4 (panel) | Sin cambios de alcance respecto al plan original |
| Entregable partilot.es | Carpeta **`PUBLICAR-EN-PARTILOT-ES/`** en el repo Laravel |

---

## 1. Arquitectura (definitiva)

```
QR impreso
  → https://partilot.es/comprobar-participaciones?ref=&sig=
        │
        ├─ App instalada + App Links OK → com.partilot.app
        │     ├─ sesión → flujo existente (QR/ref en app)
        │     └─ sin sesión → /registro
        │
        └─ Navegador (VPS partilot.es)
              ├─ sin ref → landing (app / escanear QR)
              └─ con ref → PHP llama panel API → HTML premio
                    ├─ caché 60 s
                    └─ IP guard (fallos inválidos)
```

**No iframe.** El panel no renderiza la página pública de premio para usuarios finales en partilot.es.

---

## 2. Carpeta de despliegue partilot.es

**Ruta en repo:** `PUBLICAR-EN-PARTILOT-ES/`

| Contenido | Uso |
|-----------|-----|
| `LEEME.md` | Instrucciones de instalación en el VPS |
| `comprobar-participaciones/index.php` | Entrada principal |
| `lib/*` | API client, caché, IP guard, HTML |
| `config.example.php` | → copiar a `config.php` |
| `.well-known/assetlinks.json` | Android App Links |
| `.well-known/apple-app-site-association` | iOS Universal Links |
| `deeplinks-app/*` | Snippets para el repo Ionic (no subir al VPS web) |

---

## 3. Fases panel (panel.partilot.es) — sin novedad de alcance

### Fase 0 — Decisiones ✅

- 0.1 VPS separados → **confirmado**
- 0.2 HMAC en prod cuando toque
- 0.3 Bloqueo IP **sin admin** → SQLite en partilot.es; desbloqueo manual SQL
- 0.4 Copy landing + enlaces stores (`com.partilot.app`)

### Fase 1 — API + servicio (panel)

| ID | Tarea | Estado |
|----|-------|--------|
| 1.1 | `ParticipationPublicCheckService` | ✅ Implementado |
| 1.2 | `GET /api/public/participation-check` JSON | ✅ Implementado |
| 1.3 | Refactor `showParticipationTicket` usa servicio | ✅ Implementado |
| 1.4 | Optimizar búsqueda por referencia | Pendiente |
| 1.5 | Rutas panel `comprobar-participaciones` | Opcional / legacy |

### Fase 2 — Caché 60 s

| ID | Dónde | Tarea |
|----|-------|-------|
| 2.1 | **partilot.es** | `FileCache` en `PUBLICAR-EN-PARTILOT-ES` ✅ |
| 2.2 | Panel API | Header `Cache-Control: max-age=60` en JSON ✅ |

### Fase 3 — Rate limiting IP

| ID | Dónde | Tarea |
|----|-------|-------|
| 3.1 | **partilot.es** | SQLite `ip_blocks` ✅ |
| 3.2 | **partilot.es** | Escalado 60 / 300 / 600 / permanente ✅ |
| 3.3 | — | Sin panel admin; desbloqueo: `DELETE FROM ip_blocks WHERE ip=...` |
| 3.4 | Panel | Opcional rate limit adicional en API | Pendiente |

### Fase 4 — Seguridad

| ID | Tarea |
|----|-------|
| 4.1 | `PARTICIPATION_QR_REQUIRE_HMAC=true` en prod |
| 4.2 | QRs con `ref` + `sig` |
| 4.3 | Log intentos fallidos en panel (opcional) |

---

## 4. Fase 5 — Deep links (app + partilot.es) — simplificada

**Paquete:** `com.partilot.app`

| ID | Tarea | Dificultad | Notas |
|----|-------|------------|-------|
| 5.1 | Intent-filter Android | Media | Ver `deeplinks-app/AndroidManifest-intent-filter.xml` |
| 5.2 | `assetlinks.json` en partilot.es | Media | Sustituir SHA-256 release |
| 5.3 | Universal Links iOS | Media-Alta | TEAM_ID + Associated Domains |
| 5.4 | `App.addListener('appUrlOpen')` | Baja | Ver `deeplinks-app/app.component-snippet.ts` |
| 5.5 | Con sesión → **flujo existente** | Baja | p. ej. navegar a tab cartera/escáner con `ref` |
| 5.6 | Sin sesión → `/registro` | Baja | **Sin** guardar ref |
| 5.7 | Publicar nueva versión en stores | Baja | Tras QA |

**¿Abre la app al entrar en el URL del QR?**  
Sí, si App Links / Universal Links están verificados en **partilot.es** y la app incluye el intent-filter. Funciona con el URL HTTPS del QR, no requiere iframe.

---

## 5. Fase 6 — Sitio partilot.es (VPS)

| ID | Tarea | Estado |
|----|-------|--------|
| 6.1 | Subir `PUBLICAR-EN-PARTILOT-ES/` al document root | Pendiente despliegue |
| 6.2 | `config.php` + permisos `data/` | Pendiente |
| 6.3 | Redirect `comprobar-participacion` → plural | ✅ en `.htaccess` |
| 6.4 | ~~Iframe panel~~ | **Descartado** |

---

## 6. Fase 7 — QA

| # | Escenario | Esperado |
|---|-----------|----------|
| 1 | QR válido, sin app | partilot.es muestra premio; 2.ª carga < 60 s desde caché |
| 2 | QR válido, app instalada | Abre `com.partilot.app` |
| 3 | App con sesión + deep link | Flujo existente consulta participación |
| 4 | App sin sesión + deep link | Pantalla registro (sin ref guardado) |
| 5 | `/comprobar-participaciones` sin params | Solo mensaje app/QR |
| 6 | 4 refs inválidas misma IP | 1 / 5 / 10 min → permanente |
| 7 | Desbloqueo | SQL manual en VPS partilot.es |
| 8 | App interna QR + ref manual | Sin regresiones |

---

## 7. Estimación

| Bloque | Días aprox. |
|--------|-------------|
| Panel API (fase 1 parcial) | ✅ hecho |
| partilot.es PHP (fase 6) | ✅ hecho — falta desplegar |
| Deep links app (fase 5) | 2–4 d |
| QA + stores | 1–2 d |

---

## 8. Desbloqueo IP (solo manual)

En el VPS **partilot.es**:

```bash
sqlite3 data/ip_blocks.sqlite "DELETE FROM ip_blocks WHERE ip = '1.2.3.4';"
```

---

## 9. Deep links — referencia rápida

### Android — `com.partilot.app`

Archivo: `PUBLICAR-EN-PARTILOT-ES/.well-known/assetlinks.json`

### iOS — `TEAM_ID.com.partilot.app`

Archivo: `PUBLICAR-EN-PARTILOT-ES/.well-known/apple-app-site-association`

### App Ionic

Archivos en: `PUBLICAR-EN-PARTILOT-ES/deeplinks-app/`

---

## 10. Próximos pasos

1. Desplegar panel con `/api/public/participation-check`
2. Subir `PUBLICAR-EN-PARTILOT-ES/` a partilot.es y configurar `config.php`
3. Obtener SHA-256 del keystore release → `assetlinks.json`
4. Implementar deep links en app (`com.partilot.app`) y publicar update
5. Activar HMAC en prod cuando todos los QRs lleven `sig`

---

*Ver también: `PUBLICAR-EN-PARTILOT-ES/LEEME.md`*
