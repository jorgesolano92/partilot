# Plan de textos legales — PARTILOT

Documento de trabajo derivado de `legales2/` (Guía de Flujos, Textos de Roles v3, Implementación Legal v2, Mapa Comunicaciones v1).

**Rama:** `auditoria`  
**Última actualización:** 2026-06-17

---

## Principio rector

La aceptación legal debe producirse **antes** de cualquier acto jurídico o económico. Cada aceptación queda registrada de forma **inmutable** (quién, qué versión, cuándo, IP, canal).

---

## Inventario de fuentes (`legales2/`)

| Archivo | Contenido |
|---------|-----------|
| `PARTILOT_Guia_Flujos_Legales_v1` | 7 flujos: Admin, Entidad, Roles, Usuario, Sorteo, Premios, Baja |
| `PARTILOT_Textos_Aceptacion_Roles_v3` | Textos exactos GR / Gestor / Vendedor + datos a registrar |
| `PARTILOT_textos_legales_menores_Implementacion_Legal_v2` | Puntos L1–L9 con especificación técnica |
| `PARTILOT_Mapa_Comunicaciones_v1` | Emails y push por evento |

---

## Los 9 puntos legales (L1–L9)

| ID | Momento | Acción BD | Canal | Estado |
|----|---------|-----------|-------|--------|
| **L1** | Registro usuario | `REGISTRO_ACEPTACION_TCU` | Web, App, Web entidad | 🟡 Parcial — registro API + `user_consents` + `legal_acceptances`; app debe consumir `/api/legal/config` |
| **L2** | Banner cookies | `COOKIES_ACEPTACION` | Web, Web entidad | 🟡 Parcial — banner panel + API; falta bloquear GA/Firebase hasta consentimiento |
| **L3** | Aceptación GR | `ACEPTACION_ROL_GESTOR_RESPONSABLE` | Web, App | 🔴 Pendiente — pantalla bloqueante + textos v3 |
| **L4** | Aceptación Gestor | `ACEPTACION_ROL_GESTOR` | Web, App | 🔴 Pendiente |
| **L5** | Aceptación Vendedor | `ACEPTACION_ROL_VENDEDOR` | Web, App | 🔴 Pendiente — hoy solo enlace email sin textos completos |
| **L6** | Cobro premio | `COBRO_PREMIO_CONFIRMADO` | Web, App | 🟡 Parcial — doble opt-in transferencia; falta doble confirmación app |
| **L7** | Donación premio | `DONACION_PREMIO_CONFIRMADA` | Web, App | 🟡 Parcial — API; falta UI certificado fiscal |
| **L8** | Liquidación definitiva | `LIQUIDACION_DEFINITIVA_CONFIRMADA` | Panel web | 🔴 Pendiente — campo `CONFIRMO LIQUIDACIÓN` |
| **L9** | Baja cuenta | `SOLICITUD_BAJA_CUENTA` | Web, App | 🔴 Pendiente — requisito App Store / Play |

Leyenda: ✅ Hecho · 🟡 Parcial · 🔴 Pendiente

---

## Infraestructura implementada (Fase 0)

### Configuración

- `config/legal.php` — versiones, hashes, textos L1, documentos públicos, cookie name.

### Base de datos

| Tabla | Uso |
|-------|-----|
| `legal_acceptances` | Registro inmutable de todas las aceptaciones (L1–L9, roles) |
| `cookie_consents` | Elección de cookies (L2) |
| `user_consents` | Compatibilidad con registro existente; duplica en `legal_acceptances` |

Migraciones:

- `2026_06_17_150000_create_legal_acceptances_table.php`
- `2026_06_17_150001_create_cookie_consents_table.php`

### Servicios

| Clase | Responsabilidad |
|-------|-----------------|
| `LegalAcceptanceService` | Registrar aceptaciones, listar documentos, config cliente, pendientes |
| `CookieConsentService` | Banner, persistencia cookie, registro L2 |
| `UserConsentService` | Registro L1 en registro API (delega en `LegalAcceptanceService`) |

### API (`/api/legal/*`)

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/legal/config` | Texto checkbox L1, URLs documentos, versiones (app móvil) |
| GET | `/api/legal/documents` | Listado documentos públicos |
| GET | `/api/legal/documents/{slug}` | Metadatos de un documento |
| GET | `/api/legal/cookies/status` | ¿Mostrar banner? + estado actual |
| POST | `/api/legal/cookies` | Guardar elección (`all` / `necessary` / `custom`) |
| GET | `/api/legal/pending-acceptances` | Cola bloqueante (auth; extensible L3–L5) |

Header opcional para canal: `X-Partilot-Channel: app_ios | app_android | web_entidad`

### Vistas web

- Páginas estáticas: `resources/views/legal/*` (aviso, privacidad, cookies, términos)
- Banner L2: `resources/views/partials/cookie-consent-banner.blade.php` (panel + páginas legales)

---

## Mapa flujos → pantalla → código

### Flujo 1 — Alta Administración (B1)

| Paso | Pantalla | Estado | Notas |
|------|----------|--------|-------|
| 1.1 | Info previa contrato | 🔴 | Epic contrato SaaS + eFirma Go |
| 1.3 | Firma contrato B1 | 🔴 | Bloquear panel hasta firma |
| 1.4 | Checkbox Marco Legal primer acceso | 🔴 | |

### Flujo 2 — Entidad y GR (B2)

| Paso | Pantalla | Código actual | Siguiente paso |
|------|----------|---------------|----------------|
| 2.2 | Registro GR + L1 | `EntityController::confirmManagerAccept` | Añadir textos v3 |
| 2.3 | Aceptación cargo GR | Checkbox genérico `terms_accepted` | Pantalla bloqueante L3 + `legal_acceptances` |

### Flujo 3 — Gestor y Vendedor (C1)

| Rol | Código actual | Siguiente paso |
|-----|---------------|----------------|
| Gestor | Invitación email | Pantalla L4 + `pendingAcceptances` |
| Vendedor | `SellerController::confirmAccept` (1 click) | Pantalla L5 antes de asignar participaciones |

### Flujo 4 — Usuario final

| Paso | Código | Siguiente paso |
|------|--------|----------------|
| L1 Registro | `AuthController::apiRegister` + `UserConsentService` | App: `GET /api/legal/config` + WebView documentos |
| L2 Cookies | Banner panel | App: permisos OS; web entidad: mismo banner |

### Flujo 5 — Método pago premios

| Paso | Código | Siguiente paso |
|------|--------|----------------|
| 5.1 Elección irreversible | `devolutions/create.blade.php` (`prize_payment_mode`) | Copy legal doc + confirmación explícita |

### Flujo 6 — Premios

| Paso | Código | Siguiente paso |
|------|--------|----------------|
| 6.1 Mandato B3 | `PrizePaymentContractController` | eFirma antes de IBAN |
| 6.2 Cobro L6 | `ParticipationController::apiRegistrarCobro` | Doble confirmación app + `COBRO_PREMIO_CONFIRMADO` |
| 6.3 Donación L7 | `apiRegistrarDonacion` | UI certificado fiscal + `DONACION_PREMIO_CONFIRMADA` |
| 6.4 Liquidación L8 | Devoluciones | Campo `CONFIRMO LIQUIDACIÓN` |

### Flujo 7 — Baja cuenta (L9)

| Requisito | Estado |
|-----------|--------|
| Opción en app | 🔴 |
| Bloqueo si premios pendientes | 🔴 |
| Confirmación escribiendo email | 🔴 |
| Baja en 2 fases (30 días) | 🔴 |

---

## Comunicaciones (Mapa v1)

Prioridad alta tras L3–L5:

| ID | Evento | Estado |
|----|--------|--------|
| R1 | Bienvenida registro | 🟡 `UserWelcomeMail` existe — alinear texto |
| G1 / G1b | Designación GR + recordatorio 48h | 🔴 |
| G2 / G3 | GR acepta / rechaza | 🔴 |
| G4 / G5 | Invitación Gestor / Vendedor | 🟡 Parcial |
| P1 | Asignación participaciones vendedor | 🔴 |

---

## Fases de implementación

### Fase 0 — Fundamentos ✅ (este commit)

- [x] `config/legal.php` ampliado
- [x] Tablas `legal_acceptances`, `cookie_consents`
- [x] Servicios y API `/api/legal/*`
- [x] Banner cookies panel web (L2 base)
- [x] L1 registra en `legal_acceptances` vía `UserConsentService`
- [x] Este documento

### Fase 1 — L1 + L2 completos

- [ ] App Ionic: consumir `/api/legal/config`, checkbox texto exacto, WebView URLs
- [ ] Deshabilitar Analytics/Firebase en web hasta `cookies_analiticas=true`
- [ ] Banner en web de entidad (si layout separado)

### Fase 2 — L3, L4, L5 (roles)

- [ ] Pantallas bloqueantes con textos `Textos_Aceptacion_Roles_v3`
- [ ] `pendingAcceptancesForUser()` con managers/sellers pendientes
- [ ] Emails G1–G5

### Fase 3 — Premios L6, L7, Flujo 5

- [ ] Doble confirmación cobro en app
- [ ] Donación + certificado fiscal
- [ ] Copy método pago irreversible

### Fase 4 — L8, L9

- [ ] Liquidación con texto manual
- [ ] Eliminación cuenta app + API

### Fase 5 — Administración B1

- [ ] Contrato SaaS + eFirma Go (epic comercial)

---

## Variables de entorno

```env
LEGAL_TERMS_VERSION=10
LEGAL_TERMS_TEXT_HASH=marco_legal_v10
LEGAL_MARCO_VERSION=10
LEGAL_MARCO_HASH=marco_legal_v10
LEGAL_COOKIE_CONSENT_DAYS=365
```

---

## Checklist legal (Implementación v2)

```
□ L1 — checkbox bloqueante + REGISTRO_ACEPTACION_TCU
□ L2 — banner 3 botones equivalentes + COOKIES_ACEPTACION
□ L3 — GR pantalla bloqueante
□ L4 — Gestor pantalla bloqueante
□ L5 — Vendedor antes de asignar participaciones
□ L6 — doble confirmación cobro
□ L7 — donación + certificado opcional
□ L8 — CONFIRMO LIQUIDACIÓN
□ L9 — baja cuenta con email manual
```

---

## Referencias en código

```
config/legal.php
app/Services/LegalAcceptanceService.php
app/Services/CookieConsentService.php
app/Http/Controllers/LegalApiController.php
resources/views/partials/cookie-consent-banner.blade.php
resources/views/legal/
legales2/   (documentación fuente, no desplegar)
```

---

© PARTILOT — Plan interno de implementación legal
