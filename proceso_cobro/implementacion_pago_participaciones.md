# Implementación: pago de participaciones (app-only + multientidad condicionada)

**Referencia funcional:** `docs/flujo_pago_participaciones.md`  
**Alcance acordado:** presencial solo en app gestor (sin panel web); transferencia multientidad solo en modalidad online PARTILOT y solo con entidades habilitadas (ingreso 100% validado + activación superadmin).  
**Fecha:** junio 2026

---

## 1. Decisiones de alcance (cerradas)

| Decisión | Implicación técnica |
|----------|---------------------|
| Panel presencial **solo app** (`gestor-pago`) | No crear vistas web de cobro en sede en esta fase. Reglas, bloqueos y auditoría viven en **backend + app**. |
| Multientidad **solo transferencia online** | `apiRegistrarCobro` puede agrupar varias entidades; `apiRegistrarDonacion` sigue monoentidad. |
| Multientidad **solo si PARTILOT paga** | Aplica a modalidad `online` con remesa PARTILOT. Presencial no usa remesa multientidad. |
| Cada entidad del lote debe estar **habilitada** | Gate: `funds_status = confirmed` + `online_payments_enabled = true` (o equivalente presencial). |
| Contrato por sorteo (modalidad online y caso presencial+digitales) | Flujo de firma + registro; bloqueo hasta firmado cuando aplique. |

---

## 2. Modelo de datos propuesto

### 2.1 Tabla `entity_lottery_prize_settings` (una fila por entidad + sorteo)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `entity_id` | FK | Entidad |
| `lottery_id` | FK | Sorteo |
| `prize_payment_mode` | enum | `presencial` \| `online` |
| `mode_locked_at` | timestamp | Al confirmar devolución entidad→admin |
| `mode_locked_by_user_id` | FK nullable | Gestor que confirmó |
| `has_sold_digital_participations` | bool | Calculado o snapshot al cierre devolución |
| `funds_required_amount` | decimal | Importe a ingresar (según reglas) |
| `funds_deposited_amount` | decimal | Importe declarado/ingresado |
| `funds_status` | enum | `not_required` \| `pending` \| `confirmed` |
| `funds_confirmed_at` | timestamp nullable | Superadmin valida ingreso |
| `funds_confirmed_by_user_id` | FK nullable | |
| `contract_status` | enum | `not_required` \| `pending` \| `signed` |
| `contract_signed_at` | timestamp nullable | |
| `online_payments_enabled` | bool | Superadmin activa cobro online |
| `presencial_payments_enabled` | bool | Auto o manual según reglas |
| `blocked_user_message` | text nullable | Mensaje usuario bloqueado (solo PARTILOT edita) |
| `unlocked_user_message` | text nullable | Mensaje tras activación |
| `presencial_contact_text` | text nullable | Plantilla contacto (entidad edita) |
| `presencial_contact_address` | string nullable | |
| `presencial_contact_city` | string nullable | |
| `presencial_contact_province` | string nullable | |
| `presencial_contact_schedule` | string nullable | |
| `presencial_contact_phone` | string nullable | |
| `presencial_contact_email` | string nullable | |
| `presencial_contact_notes` | text nullable | |

**Índice único:** `(entity_id, lottery_id)`.

### 2.2 Tabla `entity_lottery_prize_activation_logs` (auditoría)

Eventos: `mode_selected`, `mode_changed_by_superadmin`, `funds_marked_pending`, `funds_confirmed`, `contract_sent`, `contract_signed`, `online_activated`, `presencial_activated`, `online_blocked`, `payment_registered_presencial`, etc.

### 2.3 Ajustes en tablas existentes

| Tabla | Cambio |
|-------|--------|
| `participation_collection_items` | Añadir `entity_id`, `amount` (desglose por entidad en multientidad) |
| `participation_collections` | Mantener `importe_total`; validar suma items = total |
| `devolutions` (opcional) | `prize_payment_mode` en devolución de cierre entidad→admin como respaldo |

### 2.4 Servicio central

`App\Services\EntityLotteryPrizePaymentService` — única fuente de verdad para:

- ¿Puede cobrar online esta participación?
- ¿Puede pagar presencial el gestor?
- ¿Qué mensaje ve el usuario?
- ¿Cuánto debe ingresar la entidad?
- ¿Requiere contrato?

---

## 3. Regla del documento → implementación

Leyenda estado actual: ✅ existe · ⚠️ parcial · ❌ falta

### Bloque A — Elección de modalidad (devolución)

| # | Regla del doc | Implementación | Backend | Web | App | Estado |
|---|---------------|----------------|---------|-----|-----|--------|
| A1 | Modal obligatorio presencial vs online al confirmar devolución entidad→admin | Modal con radio/checkbox; botón deshabilitado sin selección | Validar `prize_payment_mode` en `DevolutionsController::store` cuando `tipo_devolucion=administracion` y no `solo_devolucion` | `devolutions/create.blade.php` — modal antes de liquidación admin | `gestor-devolucion` — mismo modal en `confirmarLiquidacion` (solo flujo admin) | ❌ |
| A2 | Elección irrevocable para la entidad | Tras `mode_locked_at`, entidad no puede PATCH; solo superadmin | Policy + endpoint superadmin `PUT .../prize-settings/mode` | — | — | ❌ |
| A3 | Solo superadmin puede cambiar modalidad después | Panel superadmin (ver F4) | `PrizePaymentSuperAdminController` | Vista nueva o sección en lotería/admin | — | ❌ |

### Bloque B — Escrutinio y premios

| # | Regla del doc | Implementación | Backend | Web | App | Estado |
|---|---------------|----------------|---------|-----|-----|--------|
| B1 | Premio proporcional automático tras escrutinio | Sin cambio | `LotteryScrutinyController`, `ApiController::getPrizeInfoForReference` | `lottery/show_entity_prizes` | Cartera | ✅ |
| B2 | Digitales: cartera actualizada, Premiada/No premiada | Incluir `payment_blocked`, `block_reason`, `user_message` en payload cartera | `formatParticipationForWallet`, `apiGetCobrables` | — | `tab3`, `cobrar-gestionar` | ⚠️ |
| B3 | Mail + push tras escrutinio (digitales premiadas) | Job al guardar escrutinio: usuarios con participaciones en sorteo | `NotifyScrutinyPrizesJob` + `AppInboxNotificationService` | — | Push + bandeja | ❌ |
| B4 | Físicas: tabla sorteo, cantidad, premio, estado en panel presencial | Lista en app gestor tras validar rango | `apiValidateParticipationsForPayment` ampliado con estado pago | — | `gestor-pago.page` | ⚠️ |

### Bloque C — Modalidad presencial (app-only)

| # | Regla del doc | Implementación | Backend | Web | App | Estado |
|---|---------------|----------------|---------|-----|-----|--------|
| C1 | Solo físicas vendidas → presencial auto-habilitado | Tras escrutinio, si `mode=presencial` y `!has_sold_digital` → `presencial_payments_enabled=true` | `EntityLotteryPrizePaymentService::syncAfterScrutiny()` | — | Gate en `apiValidate/RegisterPayment` | ❌ |
| C2 | Texto contacto editable por entidad | Formulario entidad en ficha sorteo o configuración premios | CRUD `presencial_contact_*` | Vista entidad (editar contacto) | — | ❌ |
| C3 | Texto contacto visible al leer participación física | Mostrar en detalle participación / ticket social | API detalle participación | `participation-ticket` / social | Cartera detalle física | ❌ |
| C4 | Presencial + digitales vendidas → **todo bloqueado** hasta ingreso digitales + contrato | `presencial_payments_enabled=false` y online digitales bloqueado hasta `funds_confirmed` + `contract_signed` | Servicio + emails contrato | Panel entidad: estado bloqueo | App gestor: mensaje bloqueo | ❌ |
| C5 | Tras desbloqueo 3.3: PARTILOT paga digitales; app habilita físicas | Activar `presencial_payments_enabled` y flujo online solo para digitales de esa entidad | Mismo registro `entity_lottery_prize_settings` | — | `gestor-pago` + cartera | ❌ |
| C6 | Pago presencial: fecha, hora, gestor | Ya en `ParticipationActivityLog` tipo `paid` | `apiRegisterPayment` — asegurar log completo | — | UI historial en app (opcional fase 2) | ⚠️ |
| C7 | No pagar si ya cobrada online / digitalizada cobrada online | Rechazar en validate/register; mensaje LOPD genérico | Gate en `apiValidateParticipationsForPayment` | — | `gestor-pago` muestra error | ❌ |
| C8 | No pagar participación **nativa digital** en presencial | Excluir sets `digital_participations > 0` sin físicas | Filtro en validate | — | Mensaje en app | ❌ |
| C9 | Panel presencial web individual + arco | **Fuera de alcance** — solo app con rango/referencia | — | — | `gestor-pago` (ya tiene rango + ref) | ⚠️ |

### Bloque D — Modalidad online (PARTILOT)

| # | Regla del doc | Implementación | Backend | Web | App | Estado |
|---|---------------|----------------|---------|-----|-----|--------|
| D1 | Tras escrutinio: cobro online **bloqueado** | `apiGetCobrables` excluye o devuelve `cobrable=false` + mensaje | Gate en servicio | — | `cobrar-gestionar`: no seleccionable o aviso | ❌ |
| D2 | Mensaje bloqueado editable solo PARTILOT | Campos `blocked_user_message` en panel superadmin | CRUD superadmin | Vista superadmin | Mostrar en cartera | ❌ |
| D3 | 100% fondos antes de procesar online | `funds_required_amount` = suma premios online de entidad; `funds_status=confirmed` obligatorio | Cálculo post-escrutinio | — | — | ❌ |
| D4 | Contrato específico por sorteo (online) | Email enlace firma; `contract_status` | Integración firma (simple: checkbox + PDF + timestamp fase 1) | — | — | ❌ |
| D5 | Superadmin: listado entidades con premio, Pendiente/Confirmado | Panel activación | `PrizePaymentSuperAdminController::index` | `prize-payments/index.blade.php` | — | ❌ |
| D6 | Botón activar desbloquea online para esa entidad | `online_payments_enabled=true`; notificación usuarios | Endpoint `POST .../activate-online` | Botón + mensaje editable | Push/email segmentado | ❌ |
| D7 | Mensaje desbloqueado al usuario | `unlocked_user_message` default | Al activar | — | Cartera | ❌ |
| D8 | Transferencia multientidad (entidades **habilitadas**) | Quitar validación monoentidad en `apiRegistrarCobro`; validar cada `entity_id` habilitado | `ParticipationController::apiRegistrarCobro` | — | `cobrar-gestionar`: permitir selección multi-entidad si todas habilitadas | ❌ |
| D9 | Donación/código solo misma entidad | Sin cambio | `apiRegistrarDonacion` | — | `cobrar-gestionar` | ✅ |
| D10 | Cobro híbrido donación+código | Sin cambio | App + API | — | `cobrar-gestionar` | ✅ |
| D11 | Doble opt-in transferencia | Sin cambio | `ParticipationCollection` + mail verificación | — | Flujo cobro | ✅ |
| D12 | Remesa SEPA tras verificación | Desglose por entidad en items; verificar solvencia por entidad al exportar | `ConfigurationController::crearSepa` + gate | `ordenes-pago-entidades` | — | ⚠️ |

### Bloque E — Reglas transversales

| # | Regla del doc | Implementación | Backend | Web | App | Estado |
|---|---------------|----------------|---------|-----|-----|--------|
| E1 | Superadmin override total | Endpoints bloquear/activar/cambiar modo/editar mensajes | Controller superadmin | Panel | — | ❌ |
| E2 | Anti-doble cobro online ↔ presencial | `collected_at` / `status=pagada` + participación en colección verificada | Gates en cobro y pago gestor | — | Ambas apps | ❌ |
| E3 | Caducidad 3 meses | Sin cambio | `ParticipationWalletValidityService` | — | Cartera | ✅ |
| E4 | Reserva temporal en selección cartera | Evitar doble solicitud paralela | `ParticipationCollection::reservedParticipationIds` (ya existe parcial) | — | UX selección | ⚠️ |

---

## 4. Matriz de gates (lógica a centralizar)

Pseudo-código para `EntityLotteryPrizePaymentService`:

```
canCollectOnline(participation):
  settings = get(entity, lottery)
  if settings.prize_payment_mode != 'online': return false
  if !settings.online_payments_enabled: return false
  if settings.funds_status != 'confirmed' (cuando required): return false
  if settings.contract_status == 'pending': return false
  if participation.collected_at || participation.donated_at: return false
  if participation.status == 'pagada': return false
  if wallet_expired: return false
  return has_prize

canPayPresencial(participation):
  settings = get(entity, lottery)
  if settings.prize_payment_mode != 'presencial': return false
  if !settings.presencial_payments_enabled: return false
  if is_native_digital(participation): return false
  if collected_online(participation): return false  // mensaje LOPD
  if participation.status == 'pagada': return false
  return has_prize

canGroupInMultientityTransfer(participation_ids):
  entities = unique entity_ids
  for each entity in entities:
    if !canCollectOnline(any participation of entity): return false
  return true  // donación sigue exigiendo entities.count == 1

fundsRequiredAmount(entity, lottery):
  if mode == 'online':
    return sum(premio participaciones vendidas/digitalizadas sujetas a PARTILOT)
  if mode == 'presencial' && has_sold_digital:
    return sum(premio solo digitales vendidas)
  return 0  // presencial solo físicas
```

---

## 5. Endpoints nuevos (propuesta)

| Método | Ruta | Quién | Acción |
|--------|------|-------|--------|
| POST | `/api/devolutions` (existente) | Gestor | Añadir `prize_payment_mode` en cierre admin |
| GET | `/api/entity/{entity}/lottery/{lottery}/prize-settings` | Gestor entidad | Ver estado, contacto presencial |
| PUT | `/api/entity/{entity}/lottery/{lottery}/prize-settings/contact` | Gestor entidad | Editar texto contacto presencial |
| GET | `/admin/prize-payments` | Superadmin | Listado entidades/sorteos con premio |
| POST | `/admin/prize-payments/{settings}/confirm-funds` | Superadmin | Marcar ingreso confirmado |
| POST | `/admin/prize-payments/{settings}/activate-online` | Superadmin | Activar cobro online + mensaje |
| POST | `/admin/prize-payments/{settings}/activate-presencial` | Superadmin | Activar presencial (caso digitales) |
| PUT | `/admin/prize-payments/{settings}` | Superadmin | Cambiar modo, mensajes, bloquear |
| GET | `/api/wallet/participations` (existente) | Usuario | Incluir `payment_state`, `user_message` |
| GET | `/api/wallet/cobrables` (existente) | Usuario | Filtrar por gates o flag `cobrable` |

---

## 6. Archivos a tocar (por fase)

### Fase 1 — Fundamentos (empezar aquí)

| Archivo | Cambio |
|---------|--------|
| `database/migrations/..._create_entity_lottery_prize_settings_table.php` | Nueva tabla + logs |
| `app/Models/EntityLotteryPrizeSetting.php` | Modelo |
| `app/Services/EntityLotteryPrizePaymentService.php` | Gates y cálculos |
| `app/Http/Controllers/DevolutionsController.php` | Persistir modo en cierre admin |
| `resources/views/devolutions/create.blade.php` | Modal modalidad |
| `src/app/gestor-devolucion/*` | Modal modalidad |
| `app/Http/Controllers/ParticipationController.php` | Gates en `apiGetCobrables`, `apiRegistrarCobro`, `apiValidate/RegisterPayment` |
| `src/app/cobrar-gestionar/*` | Estados bloqueado, multientidad UI |
| `src/app/gestor-pago/*` | Gate bloqueo, mensajes error |

### Fase 2 — Superadmin y fondos

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/PrizePaymentSuperAdminController.php` | Nuevo |
| `resources/views/prize_payments/*` | Panel activación |
| `routes/web.php` | Rutas admin |
| `app/Http/Controllers/LotteryScrutinyController.php` | Tras save: calcular `funds_required`, sync settings |
| `app/Jobs/NotifyScrutinyPrizesJob.php` | Notificaciones |

### Fase 3 — Contrato, contacto, remesas

| Archivo | Cambio |
|---------|--------|
| Mail + ruta firma contrato | Flujo entidad |
| Vista entidad editar contacto presencial | Web entidad |
| `participation_collection_items` migration | Desglose multientidad |
| `ConfigurationController` / SEPA | Verificar solvencia por entidad al remesar |

---

## 7. Orden de implementación (tickets)

### Sprint 1 — Candados mínimos (MVP interno)

1. **Migración + modelo** `entity_lottery_prize_settings`
2. **Servicio** `EntityLotteryPrizePaymentService` con tests unitarios de gates
3. **Devolución:** modal + persistir `prize_payment_mode` (web + app)
4. **Gates en APIs** existentes (cobrables, cobro, donación, pago gestor) — por defecto bloqueado si no hay settings
5. **Crear settings automáticamente** al guardar escrutinio (modo desde devolución; fondos pendientes si online)

### Sprint 2 — Superadmin activación

6. **Panel listado** entidades con premio + estado fondos
7. **Confirmar fondos** + **activar online** + mensajes
8. **Cartera app:** mostrar bloqueado/desbloqueado
9. **Notificación** push/email al activar

### Sprint 3 — Presencial app + caso digitales

10. **Auto-habilitar presencial** (solo físicas)
11. **Bloqueo 3.3** (presencial + digitales) hasta fondos + contrato
12. **Anti-doble cobro** online/presencial
13. **Excluir nativas digitales** del pago gestor
14. **Contacto presencial** editable + visible en ticket/cartera

### Sprint 4 — Multientidad condicionada

15. **Quitar monoentidad** en `apiRegistrarCobro` con validación por entidad habilitada
16. **Items con desglose** `entity_id` + `amount`
17. **App `cobrar-gestionar`:** selección multi-entidad si todas `cobrable`
18. **SEPA:** comprobar solvencia de cada entidad del lote

### Sprint 5 — Contrato y pulido

19. Flujo firma contrato (email + página)
20. Logs auditoría panel superadmin
21. Override superadmin (cambiar modo, bloquear)

---

## 8. Criterios de aceptación por escenario

| Escenario | Resultado esperado |
|-----------|-------------------|
| Entidad elige **presencial**, solo físicas, tras escrutinio | Gestor puede pagar en app; usuario físico ve contacto entidad; cartera online no ofrece cobro PARTILOT para esas físicas no digitalizadas |
| Entidad elige **presencial** + hay digitales vendidas | Nada cobrable hasta ingreso + contrato; luego PARTILOT cobra digitales en cartera y app permite físicas |
| Entidad elige **online** | Usuario ve premio bloqueado hasta superadmin confirma fondos y activa; luego transferencia/donación/código según reglas |
| Usuario selecciona participaciones de entidad A (habilitada) y B (habilitada) | Transferencia única permitida; donación rechazada si mezcla entidades |
| Usuario mezcla entidad habilitada y no habilitada | API rechaza con mensaje claro |
| Participación cobrada online | Gestor no puede pagar en app (mensaje LOPD) |
| Participación pagada en app | No aparece en cobrables |

---

## 9. Fuera de alcance (esta entrega)

- Panel presencial web (individual/arco en navegador)
- Dashboard contable completo entidad (`proceso_cobro` Fase 4)
- Panel remesas 3 pestañas centralizado (parcial en configuración actual)
- Modo legacy ENTIDAD descarga 34.14 desde su banco
- Cierre digitalización por deadline (24h) — decidir en sprint posterior

---

## 10. Primer paso concreto (hoy)

```bash
# 1. Crear migración y modelo
php artisan make:model EntityLotteryPrizeSetting -m

# 2. Crear servicio (manual)
# app/Services/EntityLotteryPrizePaymentService.php

# 3. Test mínimo
php artisan make:test EntityLotteryPrizePaymentServiceTest --unit
```

**Primer commit funcional:** migración + servicio + gate en `apiGetCobrables` devolviendo `cobrable: false` cuando `prize_payment_mode=online` y `online_payments_enabled=false`.

---

*Documento vivo: actualizar columna Estado a medida que se implementen tickets.*
