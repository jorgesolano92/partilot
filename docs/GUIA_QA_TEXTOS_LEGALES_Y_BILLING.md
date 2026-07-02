# Guía de pruebas QA — Textos legales (L1–L9) + Billing switches

**Rama backend:** `feat/entity-billing-switches`  
**Rama app Ionic:** `cobro`  
**Fecha objetivo:** pruebas manuales  
**Alcance:** cumplimiento legal implementado + modalidad de cobro administración (tarjeta / remesa)

---

## 1. Preparación del entorno

### 1.1 Backend (Laravel)

```bash
git checkout feat/entity-billing-switches
git pull origin feat/entity-billing-switches
composer install
cp .env.example .env   # solo si no existe
php artisan key:generate
```

Configurar en `.env` (mínimo):

| Variable | Uso |
|----------|-----|
| `APP_URL` | URL del panel (ej. `http://localhost/sipart/public`) |
| `DB_*` | Conexión MySQL |
| `MAIL_*` | Para probar emails G1–G5 (Mailtrap recomendado) |
| `QUEUE_CONNECTION` | `sync` para pruebas sin worker, o `database` + `php artisan queue:work` |

### 1.2 Migraciones legales y billing (ejecutar en orden)

> Si la BD ya está en uso y `migrate:status` muestra muchas pendientes antiguas, ejecutar **solo** estas rutas:

```bash
php artisan migrate --path=database/migrations/2026_06_17_150000_create_legal_acceptances_table.php
php artisan migrate --path=database/migrations/2026_06_17_150001_create_cookie_consents_table.php
php artisan migrate --path=database/migrations/2026_06_17_160000_add_certificado_fiscal_to_participation_donations_table.php
php artisan migrate --path=database/migrations/2026_06_17_170000_add_account_deletion_fields_to_users_table.php
php artisan migrate --path=database/migrations/2026_06_17_170001_add_role_invitation_reminder_sent_at.php
php artisan migrate --path=database/migrations/2026_06_05_130000_add_billing_payment_mode_to_administrations_table.php
php artisan migrate --path=database/migrations/2026_06_05_130001_create_billing_charges_table.php
php artisan migrate --path=database/migrations/2026_06_05_140001_create_billing_direct_debit_orders_table.php
```

Verificar tablas clave:

```sql
SHOW TABLES LIKE 'legal_acceptances';
SHOW TABLES LIKE 'cookie_consents';
SHOW TABLES LIKE 'billing_charges';
DESCRIBE users;  -- columnas deletion_requested_at, deletion_scheduled_at, deletion_status
```

### 1.3 App Ionic

```bash
git checkout cobro
git pull origin cobro
npm install
```

En `src/environments/environment.ts`, apuntar `apiUrl` al backend de pruebas (ej. `http://192.168.x.x/sipart/public/api`).

Compilar y ejecutar:

```bash
ionic serve
# o
ionic cap run android
```

### 1.4 Usuarios de prueba recomendados

| Rol | Para probar |
|-----|-------------|
| Super Admin | Billing switches, configuración remesas |
| Administración | Designar GR, recibir email G3 |
| Gestor Responsable (GR) | L3, L8 liquidación, invitar gestor/vendedor |
| Gestor secundario | L4 |
| Vendedor pendiente / activo | L5, bloqueo asignación |
| Usuario app (client) | L1, L6, L7, L9 |

---

## 2. Verificación rápida API (smoke test)

Con Postman o curl:

```bash
# L1 config pública
curl -s http://TU_HOST/api/legal/config | jq .

# L2 cookies
curl -s http://TU_HOST/api/legal/cookies/status | jq .

# Documentos
curl -s http://TU_HOST/api/legal/documents | jq .
```

Resultado esperado: JSON con `success: true`, textos de registro, documentos y `needs_banner: true` sin cookie previa.

Tests automáticos backend:

```bash
php artisan test --filter=Legal
```

Esperado: **14 tests passed**.

---

## 3. L1 — Registro usuario (App)

| Paso | Acción | Resultado esperado |
|------|--------|-------------------|
| 1 | Abrir app → Registro | Checkbox con texto legal desde API (no hardcoded vacío) |
| 2 | Intentar registrar sin marcar checkbox | Error de validación |
| 3 | Completar registro válido | Usuario creado, login OK |
| 4 | BD: `SELECT * FROM legal_acceptances WHERE action='REGISTRO_ACEPTACION_TCU' ORDER BY id DESC LIMIT 1` | Fila con `user_id`, `version`, `text_hash`, `ip_address`, canal `app_android` o `app_ios` |

También comprobar en app: **Perfil → Condiciones legales** carga documentos en iframe.

---

## 4. L2 — Banner cookies (Panel web)

| Paso | Acción | Resultado esperado |
|------|--------|-------------------|
| 1 | Abrir panel en ventana incógnito / borrar cookie `partilot_cookie_consent` | Banner visible abajo |
| 2 | Pulsar «Solo necesarias» | Banner desaparece; cookie establecida |
| 3 | BD: `SELECT * FROM cookie_consents ORDER BY id DESC LIMIT 1` | Registro con elección |
| 4 | Recargar página | Banner NO vuelve a aparecer |
| 5 | (Opcional) Aceptar «Todas» | Scripts analíticos cargables si `LEGAL_ANALYTICS_SCRIPTS` configurado |

---

## 5. L3 / L4 / L5 — Invitaciones de rol

### 5.1 Gestor Responsable (L3) — Panel + App

| Paso | Acción | Resultado esperado |
|------|--------|-------------------|
| 1 | Super Admin / Admin designa GR para una entidad | Email G1 al designado (asunto: «Tienes una solicitud pendiente…») |
| 2 | Usuario designado abre app y hace login | Redirige a pantalla **Aceptación de rol** (bloqueante) |
| 3 | Revisar bullets de responsabilidades | Textos alineados con config `legal_roles.gestor_responsable` |
| 4 | Aceptar | Acceso normal a tabs; email G2 al gestor; admin notificada |
| 5 | BD: `legal_acceptances` action `ACEPTACION_ROL_GESTOR_RESPONSABLE`, result `ACEPTADO` | Registro OK |

**Rechazo GR (G3):**

| Paso | Acción | Resultado esperado |
|------|--------|-------------------|
| 1 | Designar otro GR y rechazar desde web o app | Email a administración «ha rechazado el cargo…» |
| 2 | BD | `legal_acceptances` result `RECHAZADO` |

### 5.2 Gestor secundario (L4)

| Paso | Acción | Resultado esperado |
|------|--------|-------------------|
| 1 | GR invita gestor secundario | Email G4 «Invitación para colaborar…» |
| 2 | Invitado login app | Pantalla aceptación rol tipo `gestor` |
| 3 | Aceptar | Email confirmación gestor; `ACEPTACION_ROL_GESTOR` en BD |

### 5.3 Vendedor (L5)

| Paso | Acción | Resultado esperado |
|------|--------|-------------------|
| 1 | Invitar vendedor desde panel | Email G5 con bullets de responsabilidades |
| 2 | Vendedor pendiente intenta login app | Invitación pendiente en aceptación rol |
| 3 | **Sin aceptar**, GR intenta asignar participaciones | Error 422: «No se pueden asignar participaciones hasta que el vendedor acepte…» |
| 4 | Vendedor acepta invitación | Asignación permitida; `ACEPTACION_ROL_VENDEDOR` en BD |

### 5.4 Recordatorio 48h (G1b)

| Paso | Acción | Resultado esperado |
|------|--------|-------------------|
| 1 | Dejar invitación pendiente; en BD poner `confirmation_sent_at` hace >48h | — |
| 2 | Ejecutar `php artisan sipart:send-role-invitation-reminders` | Email recordatorio; `role_invitation_reminder_sent_at` rellenado |

---

## 6. L6 / L7 — Cobro y donación de premio (App)

**Precondición:** usuario con participación premiada, escrutinio publicado, modalidad de pago de entidad que permita cobro online/presencial según caso.

### L6 — Cobro

| Paso | Acción | Resultado esperado |
|------|--------|-------------------|
| 1 | Cartera / Cobrar y gestionar → seleccionar premio | Aviso irreversibilidad visible |
| 2 | Pulsar confirmar una vez | Botón pide segunda confirmación |
| 3 | Confirmar de nuevo | Cobro procesado |
| 4 | BD | `legal_acceptances` action `COBRO_PREMIO_CONFIRMADO` |

### L7 — Donación

| Paso | Acción | Resultado esperado |
|------|--------|-------------------|
| 1 | Elegir donar premio | Texto entidad + pregunta certificado fiscal |
| 2 | Marcar/desmarcar certificado fiscal | Flujo continúa |
| 3 | Doble confirmación | Donación registrada |
| 4 | BD | `DONACION_PREMIO_CONFIRMADA`; si certificado, campo fiscal en `participation_donations` |

---

## 7. L8 — Liquidación definitiva (Panel web)

**Precondición:** gestor responsable logueado; devolución entidad → administración (no solo devolución, no vendedor).

| Paso | Acción | Resultado esperado |
|------|--------|-------------------|
| 1 | Ir a **Devoluciones → Nueva devolución** | Formulario carga |
| 2 | Completar liquidación y pulsar **Aceptar liquidación** | Modal L8: pedir escribir `CONFIRMO LIQUIDACIÓN` |
| 3 | Escribir texto incorrecto | Botón continuar deshabilitado |
| 4 | Escribir exactamente `CONFIRMO LIQUIDACIÓN` | Abre modal modalidad pago premios (Flujo 5) |
| 5 | Elegir modalidad + checkbox + confirmar | Devolución encolada/procesada |
| 6 | BD | `legal_acceptances` action `LIQUIDACION_DEFINITIVA_CONFIRMADA` con contexto devolución |

**Negativo:** intentar API/store sin la frase → HTTP 422.

---

## 8. L9 — Baja de cuenta (App)

| Paso | Acción | Resultado esperado |
|------|--------|-------------------|
| 1 | Perfil → **Eliminar cuenta** | Pantalla con avisos legales |
| 2 | Usuario **con** premios pendientes de cobro | `can_request: false`, mensaje bloqueo |
| 3 | Usuario **sin** premios; email confirmación incorrecto | Botón eliminar deshabilitado / error |
| 4 | Escribir email exacto + confirmar | Cuenta desactivada; logout automático |
| 5 | Intentar login de nuevo | Error «cuenta desactivada por solicitud de baja» |
| 6 | BD | `users.deletion_requested_at` y `deletion_status = scheduled`; `legal_acceptances` action `SOLICITUD_BAJA_CUENTA` |

**Fase 2 (opcional QA avanzado):** poner `deletion_scheduled_at` en el pasado y ejecutar:

```bash
php artisan sipart:process-account-deletions
```

Verificar email anonimizado y `deletion_status = executed`.

---

## 9. Billing switches — Modalidad cobro administración

**Rol:** Super Admin  
**Ruta:** Ficha administración → tarjeta «Modalidad de cobro PARTILOT»

| Paso | Acción | Resultado esperado |
|------|--------|-------------------|
| 1 | Admin sin IBAN válido | Opción «Remesa periódica» deshabilitada |
| 2 | Configurar IBAN en datos legales | Remesa habilitada |
| 3 | Guardar «Tarjeta (Stripe)» | `administrations.billing_payment_mode = card` |
| 4 | Guardar «Remesa» + periodicidad mensual/quincenal | Modo `remittance` + frecuencia guardada |
| 5 | Generar cargo de prueba (cuota diseño / gestión) | Aparece en cargos pendientes si modo remesa |
| 6 | Configuración → Facturación y cobros → Remesas | Crear orden remesa, exportar XML, marcar cobrada |

---

## 10. Consultas SQL útiles para QA

```sql
-- Últimas aceptaciones legales
SELECT id, user_id, action, result, version, channel, ip_address, accepted_at
FROM legal_acceptances
ORDER BY id DESC LIMIT 20;

-- Invitaciones rol pendientes
SELECT id, user_id, confirmation_token, confirmation_sent_at, pending_primary
FROM managers WHERE confirmation_token IS NOT NULL;

SELECT id, email, status, confirmation_sent_at
FROM sellers WHERE status = 2;

-- Baja de cuenta
SELECT id, email, status, deletion_requested_at, deletion_scheduled_at, deletion_status
FROM users WHERE deletion_requested_at IS NOT NULL;

-- Cookies
SELECT * FROM cookie_consents ORDER BY id DESC LIMIT 5;
```

---

## 11. Checklist resumen (marcar al probar)

### Legal
- [ ] L1 Registro app + registro BD
- [ ] L2 Banner cookies panel
- [ ] L3 GR aceptar / rechazar + emails G1–G3
- [ ] L4 Gestor secundario + email G4
- [ ] L5 Vendedor + bloqueo asignación + email G5
- [ ] L6 Cobro premio doble tap app
- [ ] L7 Donación + certificado fiscal opcional
- [ ] L8 Liquidación `CONFIRMO LIQUIDACIÓN`
- [ ] L9 Eliminar cuenta app + bloqueo login
- [ ] Recordatorio 48h (comando manual)

### Billing
- [ ] Switch tarjeta / remesa superadmin
- [ ] Remesa bloqueada sin IBAN
- [ ] Cargos pendientes y orden remesa

### Automatizado
- [ ] `php artisan test --filter=Legal` → 14 passed

---

## 12. Fuera de alcance (no probar en esta entrega)

- Epic **B1**: contrato SaaS administración + eFirma Go
- Flujo **6.1** mandato B3 eFirma
- Push notifications G1/G4/G5 (emails sí; push pendiente de verificar en dispositivo)
- Pantalla web de baja de cuenta (solo app)

---

## 13. Contacto / incidencias

Al reportar bugs incluir:

1. Rol de usuario y entidad/sorteo
2. Captura o vídeo
3. Timestamp UTC
4. Fila relevante de `legal_acceptances` o respuesta API
5. Rama desplegada: `feat/entity-billing-switches` (backend) / `cobro` (app)
