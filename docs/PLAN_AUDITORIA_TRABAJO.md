# Plan de trabajo — Auditoría ciberseguridad Partilot

**Fecha:** 17 jun 2026  
**Rama:** `auditoria`  
**Fuentes (referencia, no editar en día a día):**
- `informesflujodetrabajopanelpartilotv2/` — informes PDF por fase
- `Backlog_Auditoria_Ciberseguridad_Partilot_Actualizado.pdf` — tabla SEC original

### Documentos de trabajo en el repo

| Documento | Rol |
|-----------|-----|
| **`docs/PLAN_AUDITORIA_TRABAJO.md`** (este) | **Plan de ejecución:** oleadas, orden, tareas, matriz fase→SEC, plazos |
| **`docs/BACKLOG_AUDITORIA_CIBERSEGURIDAD.md`** | **Ficha técnica por SEC-xxx:** estado en código, evidencia, pasos de implementación |

**Flujo:** planificar y priorizar con este documento; al implementar o cerrar un ítem, actualizar el estado en el backlog SEC.

---

## 1. Resumen ejecutivo

| Origen | Hallazgos extraídos |
|--------|---------------------|
| Fases 1–10 (sección «Resumen de Defectos») | **38** |
| Fases 12–15 (sondas `[FAIL]`) | **55** |
| Fase 11 (sonda FAIL, formato distinto) | **1** |
| **Total único operativo** | **~94** (algunos duplicados entre fases) |

| Estado en código (estimado tras cruce) | Cantidad orientativa |
|----------------------------------------|----------------------|
| ✅ Resuelto o mitigado | ~8 |
| 🟡 Parcial | ~22 |
| ❌ Pendiente | ~45 |
| 📋 Módulo no implementado (F12–15 avanzado) | ~19 |

**Objetivo del plan:** cerrar primero lo que reduce superficie de ataque y riesgo financiero **en el código actual**, y encajar el resto en oleadas alineadas con `proceso_cobro/`.

---

## 2. Leyenda

| Símbolo | Significado |
|---------|-------------|
| ✅ | Corregido o mitigación suficiente verificada en código |
| 🟡 | Control parcial; falta endurecer o cubrir otro flujo |
| ❌ | Abierto |
| 📋 | Depende de módulo futuro (donaciones avanzadas, forense, KYC…) |
| 🔍 | Contradicción auditoría vs código; verificar con pentest |

---

## 3. Matriz Fase → Backlog → Estado → Acción

### Fase 1 — Alta administraciones

| # | Hallazgo (informe) | SEC | Estado | Acción prioritaria |
|---|-------------------|-----|--------|-------------------|
| 1.1 | RCE subida `image` sin MIME (`AdministratorController`) | SEC-001 | ❌ | Validar `image\|mimes`, MIME real, mover fuera de `public/`; bloquear PHP en uploads |
| 1.2 | Contraseña gestor fija `12345678` | SEC-002 | 🟡 | `Str::password()` + email; middleware cambio obligatorio ya existe |
| 1.3 | `prepago_api_key` en texto plano | — | 🟡 | Modelo `Administration` ya usa cast `encrypted`; auditar que no quede legacy en BD/logs y rotar claves |
| 1.4 | Sin log auditoría cambio IBAN (`account`) | SEC-003 / nuevo | ❌ | Tabla `administration_audit_logs` en cambios de `account`, IBAN billing |
| 1.5 | Código postal sin formato estricto | — | ❌ | Regla `regex:/^\d{5}$/` en update admin |

### Fase 2 — Alta entidades y gestores

| # | Hallazgo | SEC | Estado | Acción |
|---|----------|-----|--------|--------|
| 2.1 | Contraseña provisional `12345678` gestores | SEC-002 | 🟡 | Igual que 1.2 en `EntityController` |
| 2.2 | Sin logs permisos/estado gestor | SEC-003 | ❌ | `manager_permission_audits` + hook en `update_manager_permissions` / `toggle_manager_status` |
| 2.3 | NIF nullable en update entidad activa | SEC-004 | 🟡 | `required_if:status,1` + `EntityDocument` en `EntityController::update` |
| 2.4 | XSS stored en `comments` | SEC-005 | 🟡 | Sanitizar al guardar (`strip_tags` o HTMLPurifier); auditar vistas `{!! !!}` |

### Fase 3 — Reservas y sets

| # | Hallazgo | SEC | Estado | Acción |
|---|----------|-----|--------|--------|
| 3.1 | XXE en `simplexml_load_file` (`SetController`) | SEC-006 | ❌ | `LIBXML_NONET`, deshabilitar entidades; validar XSD; límite tamaño |
| 3.2 | `deadline_date` nullable (ventas indefinidas) | **NEW-F3-01** | ❌ | Hacer obligatorio en sets con venta; endurecer `DeadlineBeforeLottery` |
| 3.3 | Modificar set tras celebración sorteo | **NEW-F3-02** | ❌ | Bloquear update set si `lottery.draw_date` pasada o escrutinio hecho |
| 3.4 | Sin email al editar reserva | **NEW-F3-03** | ❌ | Notificación en `ReserveController` update (baja prioridad producto) |
| 3.5 | Sin tope máximo `total_tickets` reserva | **NEW-F3-04** | ❌ | Límite configurable por administración/sorteo |

### Fase 4 — Talonarios y participaciones

| # | Hallazgo | SEC | Estado | Acción |
|---|----------|-----|--------|--------|
| 4.1 | Colisión refs `min(9999)` | SEC-007 | ❌ | Ampliar formato o unicidad DB + reintento |
| 4.2 | QR participación sin HMAC | SEC-008 | 🟡 | HMAC en ref participación; tacos ya firmados (`DesignFormat::buildTacoRef`) |
| 4.3 | Sin serial/hash impresión física | SEC-009 | ❌ | `print_batch_id` + hash por taco en PDF |

### Fase 5 — Alta compradores

| # | Hallazgo | SEC | Estado | Acción |
|---|----------|-----|--------|--------|
| 5.1 | Contraseña mín. 6 caracteres | SEC-010 | ❌ | `Password::min(8)` en `AuthController` y `DigitalBuyerRegistrationController` |
| 5.2 | SMS sin rate limit en verificación | SEC-011 | 🟡 | Throttle en registro; límite intentos fallidos en `PhoneVerificationService` |
| 5.3 | Email case-sensitive en duplicados | SEC-012 | 🟡 | Normalizar email en mutator `User`; migración `LOWER(email)` |
| 5.4 | Sin log consentimiento RGPD | SEC-013 | ❌ | Tabla `user_consents` |

### Fase 6 — Adjudicación vendedores

| # | Hallazgo | SEC | Estado | Acción |
|---|----------|-----|--------|--------|
| 6.1 | Sin política contraseña vendedor | SEC-015 | ❌ | Igual SEC-010 en flujos `SellerService` |
| 6.2 | NIF nullable vendedor externo | SEC-016 | 🔍 | Auditar `SellerController`; required antes de activar ventas |
| 6.3 | Sin snapshot condiciones adjudicación | SEC-017 | ❌ | JSON snapshot al asignar taco |
| 6.4 | Vendedor `user_id = 0` huérfano | — | 🟡 | Deuda conocida (`RESPUESTA_INFORME_AUDITORIA` §3.3); documentar o migrar a NULL |

### Fase 7 — Ventas y liquidaciones

| # | Hallazgo | SEC | Estado | Acción |
|---|----------|-----|--------|--------|
| 7.1 | Sin conciliación Stripe ↔ inventario | SEC-018 | 🟡 | Idempotencia + webhook; job reconciliación ventas digitales |
| 7.2 | Liquidación manual sin justificante | SEC-019 | ❌ | Adjunto/firma gestor en cierre caja vendedor |
| 7.3 | Venta directa sin log términos | SEC-020 | ❌ | `user_consents` en venta digital/presencial |

### Fase 8 — Devolución vendedor → entidad

| # | Hallazgo | SEC | Estado | Acción |
|---|----------|-----|--------|--------|
| 8.1 | Stock flotante → vendido automático | SEC-022 | 🟡 | **Decisión negocio:** mantener flujo devolución entidad→admin o nuevos estados `extraviado` |
| 8.2 | Sin alertas stock pendiente | SEC-023 | ❌ | Notificación push/email + widget panel |
| 8.3 | Devolver participaciones ya vendidas | SEC-024 | 🟡 | ✅ en `DevolutionsController`; 🔍 verificar API vendedor/`SellerController` |

### Fase 9 — Devolución entidad → administración

| # | Hallazgo | SEC | Estado | Acción |
|---|----------|-----|--------|--------|
| 9.1 | `round()` décimos huérfanos | SEC-027 | 🟡 | Migración decimal; revisar `ceil` en reservas; recálculo histórico |
| 9.2 | Email devolución solo a admin | SEC-026 | 🟡 | Añadir mail a gestor entidad |
| 9.3 | Sin recibo en `addPayment` | SEC-026 | ❌ | Email comprobante vía `CommunicationEmailService` |
| 9.4 | Sin cron alertas pre-sorteo | SEC-023 | ❌ | Comando `sipart:devolution-deadline-reminder` + schedule |

### Fase 10 — Escrutinio y premios

| # | Hallazgo | SEC | Estado | Acción |
|---|----------|-----|--------|--------|
| 10.1 | Memoria escrutinio (`->get()` masivo) | SEC-028 | ❌ | `chunk()`/`cursor()`; job en cola |
| 10.2 | Décimos huérfanos (round) | SEC-027 | 🟡 | Igual 9.1 |
| 10.3 | Sin bloqueo acciones post-escrutinio | **NEW-F10-01** | 🟡 | Bloqueo devolución entidad→admin hecho; falta ventas/asignaciones post-escrutinio |

### Fase 11 — Solvencia y premios

| # | Hallazgo | SEC | Estado | Acción |
|---|----------|-----|--------|--------|
| 11.1 | Sonda 2: sin deducción bolsa solvencia tras retiro | SEC-029 | 📋 | Implementar con módulo cobro (`remaining_prize_funds`, transacción atómica) |

### Fases 12–15 — Sondas `[FAIL]` (resumen)

| Bloque | Sondas FAIL | SEC aprox. | Estado global |
|--------|-------------|------------|---------------|
| Imprenta / Stripe (F12) | 43 | SEC-030–039, parte 033–035 | 🟡 Underpayment ✅; resto ❌/🔍 |
| Donaciones / códigos (F12) | incluidas arriba | SEC-040–065 | 📋/❌ según endpoint |
| Remesas SEPA (F13) | 5 | SEC-066–070 | 🟡 importe/mandato parcial en billing |
| Verificación manual (F14) | 3 | SEC-071–073 | 📋/🟡 transferencia doble opt-in |
| Trazabilidad forense (F15) | 4 | SEC-074–077 | 📋 módulo no existe |

**F12 — ya mitigado en código (verificar en pentest):**

| Sonda | Hallazgo | SEC | Estado |
|-------|----------|-----|--------|
| 1 | Replay PaymentIntent | — | ✅ Lock + idempotencia PI |
| 2 | Underpayment print order | SEC-030 | ✅ Comparación importe servidor |
| 9–10 | Cuenta suspendida / UUID PI | — | ✅ Revisar en QA |

**F12 — crítico pendiente inmediato:**

| Sonda | Hallazgo | SEC |
|-------|----------|-----|
| 3 | SQL injection notas orden | SEC-031 🔍 |
| 4 | XSS nombre peña | SEC-032 |
| 5 | Null byte upload diseño | SEC-033 |
| 6 | Fuerza bruta estado Stripe | SEC-034 |
| 7 | Impresión sin JWT | SEC-035 |
| 8 | IDOR órdenes impresión | SEC-036 |
| 10+ | Webhook / supervisor / PDF raw | SEC-037–039 |
| 11+ | Donaciones 0€ / overflow / IDOR cert | SEC-040–052 📋/❌ |

---

## 4. Hallazgos del informe NO listados en backlog SEC (nuevos)

| ID | Fase | Descripción | Prioridad sugerida |
|----|------|-------------|-------------------|
| NEW-F1-01 | 1 | Log auditoría cambio IBAN administración | Alta |
| NEW-F1-02 | 1 | Validación estricta código postal | Baja |
| NEW-F3-01 | 3 | Fecha límite venta obligatoria | Alta |
| NEW-F3-02 | 3 | Bloquear edición set post-sorteo | Alta |
| NEW-F3-03 | 3 | Email al modificar reserva | Media |
| NEW-F3-04 | 3 | Tope máximo décimos por reserva | Media |
| NEW-F10-01 | 10 | Bloqueo global post-escrutinio (ventas, asignación) | Alta |

---

## 5. Plan de trabajo por oleadas

### Oleada A — Hotfix seguridad (1–2 semanas, 1 dev)

**Objetivo:** Cerrar vectores de compromiso del servidor y API abierta.

| Tarea | Archivos / ámbito | SEC / ID | Esfuerzo |
|-------|-------------------|----------|----------|
| A.1 Validar y endurecer uploads admin | `AdministratorController`, `EntityController` | SEC-001 | 0,5 d |
| A.2 Proteger rutas API diseño/upload | `routes/api.php`, middleware `auth:sanctum` | SEC-033, SEC-035 | 1 d |
| A.3 Endurecer XML import | `SetController::importXml` | SEC-006 | 0,5 d |
| A.4 Webhook Stripe: rechazar sin secret | `StripeWebhookController` | SEC-037 | 0,25 d |
| A.5 Sanitizar HTML comentarios entidad | `EntityController`, `HtmlText` o Purifier | SEC-005 | 1 d |
| A.6 CSP básica panel | `layouts/layout.blade.php`, headers | SEC-032 | 0,5 d |

**Criterio de cierre:** Pentest repetido F1 + F3 + rutas `api/upload-image` bloqueadas sin auth.

---

### Oleada B — Credenciales e identidad (1 semana)

| Tarea | SEC / ID | Esfuerzo |
|-------|----------|----------|
| B.1 Eliminar `12345678`; contraseña aleatoria + email | SEC-002 | 1 d |
| B.2 Política contraseña ≥8 (registro, vendedor, comprador) | SEC-010, SEC-015 | 0,5 d |
| B.3 NIF obligatorio entidad activa + vendedor externo | SEC-004, SEC-016 | 0,5 d |
| B.4 Email normalizado + migración duplicados | SEC-012 | 1 d |
| B.5 Rate limit verificación SMS + intentos fallidos | SEC-011 | 0,5 d |
| B.6 Tabla `user_consents` (registro + venta) | SEC-013, SEC-020 | 1 d |

---

### Oleada C — Integridad operativa lotería (2 semanas)

| Tarea | SEC / ID | Esfuerzo |
|-------|----------|----------|
| C.1 Escrutinio por chunks / job cola | SEC-028 | 2 d |
| C.2 `ceil` décimos + script recálculo | SEC-027 | 1,5 d |
| C.3 Bloqueo edición set/reserva post-sorteo | NEW-F3-02, NEW-F10-01 | 1 d |
| C.4 Fecha límite obligatoria | NEW-F3-01 | 0,5 d |
| C.5 Referencias sin colisión 9999 | SEC-007 | 1 d |
| C.6 HMAC QR participación (compat. legacy) | SEC-008 | 1,5 d |
| C.7 Verificar devolución vendidas en todos los endpoints | SEC-024 | 0,5 d |
| C.8 Logs auditoría gestores + IBAN admin | SEC-003, NEW-F1-01 | 1,5 d |

---

### Oleada D — Devoluciones, stock y comunicaciones (1–2 semanas)

| Tarea | SEC / ID | Esfuerzo |
|-------|----------|----------|
| D.1 Decisión negocio stock flotante → vendido | SEC-022 | 0,5 d reunión + 1–3 d dev |
| D.2 Alertas stock / pre-sorteo (cron + notif) | SEC-023, 9.4 | 2 d |
| D.3 Emails devolución: gestor + recibo pago | SEC-026 | 1 d |
| D.4 Justificantes liquidación manual | SEC-019 | 1,5 d |
| D.5 Snapshot adjudicación vendedor | SEC-017 | 1 d |

---

### Oleada E — Imprenta y órdenes (1 semana)

| Tarea | SEC | Esfuerzo |
|-------|-----|----------|
| E.1 IDOR `PrintOrderPolicy` + tests | SEC-036 | 1 d |
| E.2 Rate limit consultas PI / estados | SEC-034 | 0,5 d |
| E.3 Purificar HTML plantillas PDF | SEC-039 | 1 d |
| E.4 Pentest SQL notas orden | SEC-031 | 0,5 d |
| E.5 Firma supervisor >1000 ejemplares (si aplica negocio) | SEC-038 | 1 d |

---

### Oleada F — Módulo cobro / donaciones / forense (epic, ver `proceso_cobro/`)

Agrupa SEC-029, SEC-040–065, SEC-071–077 y la mayoría de sondas F12–F15.

| Sub-epic | Contenido | Dependencia |
|----------|-----------|-------------|
| F.1 Solvencia y retiros | SEC-029, bolsa premios | Diseño contable |
| F.2 Donaciones robustas | SEC-040–052 | API + reglas premio |
| F.3 Códigos prepago / KYC / CSV | SEC-053–062 | API externa prepago |
| F.4 Remesas premios usuario | SEC-071–073 | ParticipationCollection |
| F.5 Logs forenses WORM | SEC-074–077 | Infra + BD |

**No mezclar con Oleadas A–E** salvo fixes triviales (p. ej. `importe_donacion > 0` en `apiRegistrarDonacion`).

---

## 6. Orden recomendado (primer mes)

```
Semana 1:  A.1 → A.4 → A.2 → A.3
Semana 2:  A.5 → A.6 → B.1 → B.2 → B.5
Semana 3:  B.3 → B.4 → B.6 → C.7
Semana 4:  C.1 → C.2 → C.4 → NEW-F3-01
Paralelo:  E.1, E.2 (si hay tráfico imprenta)
Backlog:   Oleada F cuando cierre funcional cobro online
```

---

## 7. Verificaciones ya hechas en código (no reabrir sin regresión)

| Hallazgo informe | Evidencia actual |
|------------------|------------------|
| Underpayment Stripe impresión (F12 sonda 2) | `DesignController` compara PI vs quote |
| Replay PI duplicado | Lock `print-order-stripe-pi:` |
| Devolución participaciones vendidas (panel) | `DevolutionsController` ~L534–551 |
| `prepago_api_key` cifrado | `Administration::$casts['prepago_api_key'] = 'encrypted'` |
| QR taco con HMAC | `DesignFormat::buildTacoRef` |
| Bloqueo escrutinio sin devolución entidad→admin | `EntityLotteryPrizePaymentService` (reciente) |
| Remesa billing importe > 0 | `BillingDirectDebitService` |
| Vendedor bloqueado en asignación | `SellerController::saveAssignments` |

---

## 8. Cómo mantener el plan vivo

1. Marcar tareas completadas en las oleadas de **este** documento (o en issues/PR vinculados a SEC-xxx).
2. Actualizar el estado (✅/🟡/❌) en `docs/BACKLOG_AUDITORIA_CIBERSEGURIDAD.md` con evidencia de archivo/línea.
3. Añadir test Feature por hallazgo crítico (upload, XXE, devolución vendida, PI amount).
4. Pentest de regresión por fase antes de release `auditoria` → `main`.
