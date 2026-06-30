# Backlog auditoría de ciberseguridad — Partilot

**Fuente:** `Backlog_Auditoria_Ciberseguridad_Partilot_Actualizado.pdf`  
**Rama:** `auditoria`  
**Revisión código:** 17 jun 2026 (panel Laravel `h:/xampp3/htdocs/sipart`)

Este documento cruza cada ítem del informe con el estado actual del repositorio y propone **cómo proceder** (verificación, fix, diseño o descarte documentado).

---

## Leyenda de estado

| Estado | Significado |
|--------|-------------|
| ✅ **Resuelto** | El riesgo está mitigado en código actual |
| 🟡 **Parcial** | Hay controles, pero incompletos o solo en parte del flujo |
| ❌ **Pendiente** | Sigue abierto; requiere desarrollo |
| 🔍 **Verificar** | No reproducido en revisión estática; conviene prueba manual / pentest |
| 📋 **Fuera de alcance actual** | Módulo no implementado o solo en documentación (`proceso_cobro/`) |

---

## Resumen ejecutivo

| Prioridad | Total | ✅ | 🟡 | ❌/🔍/📋 |
|-----------|------:|---:|---:|---------:|
| Crítica | 44 | 2 | 8 | 34 |
| Alta | 14 | 1 | 5 | 8 |
| Media | 18 | 0 | 4 | 14 |
| Control | 1 | 0 | 1 | 0 |

> Los conteos son orientativos: muchos ítems de **F12–F15** apuntan a funcionalidades de cobro avanzado, códigos prepago, logs forenses o certificados que **aún no existen como módulo completo** en el panel.

### Oleadas recomendadas

1. **Oleada 1 — Superficie de ataque expuesta (1–2 sprints):** SEC-001, SEC-033, SEC-035, SEC-034, SEC-037, SEC-006, SEC-005, SEC-032, SEC-039, rutas API públicas en `routes/api.php`.
2. **Oleada 2 — Credenciales e identidad (1 sprint):** SEC-002, SEC-010, SEC-015, SEC-004, SEC-016, SEC-012.
3. **Oleada 3 — Integridad operativa (2 sprints):** SEC-024, SEC-027, SEC-028, SEC-007, SEC-008, SEC-022, SEC-025.
4. **Oleada 4 — Módulo cobro / donaciones / prepago (según `proceso_cobro/`):** SEC-029, SEC-040–SEC-065, SEC-071–SEC-073.
5. **Oleada 5 — SEPA facturación + hardening infra:** SEC-066–SEC-070, SEC-064, SEC-074–SEC-077.

---

## Detalle por ítem

### F1 — Administración

#### SEC-001 · Crítica · Upload / RCE en imagen de administración
- **Estado:** 🟡 Parcial (Oleada A — 2026-06-17)
- **Evidencia:** `SecureImageUpload` + validación `image|mimes` en `AdministratorController` y `CreateAdmin`. Ruta pública `api/upload-image` eliminada. **Pendiente:** mover a `storage/` y bloquear ejecución PHP en Apache/Nginx.

#### SEC-002 · Alta · Contraseña provisional fija `12345678`
- **Estado:** 🟡 Parcial
- **Evidencia:** Sigue creándose con `bcrypt(12345678)` en `EntityController`, `AdministratorController`, `PrintShopPanelUserService`, `ApiController`. Existe mitigación: middleware `RedirectIfEntityManagerLegacyPassword`, vista de cambio obligatorio, rechazo de `12345678` al cambiar (`AuthController`).
- **Proceder:**
  1. Sustituir por contraseña aleatoria (`Str::password(16)`) en todos los altas.
  2. Enviar por canal seguro (email único / magic link); caducar invitación.
  3. Unificar `PrintShopPanelUserService` y seeders de desarrollo.
  4. Tests: gestor nuevo no accede al panel sin cambiar contraseña.

#### SEC-003 · Media · Sin logs de auditoría en permisos de gestores
- **Estado:** ❌ Pendiente
- **Evidencia:** `EntityController::update_manager_permissions` actualiza flags sin tabla de auditoría.
- **Proceder:** Crear `manager_permission_audits` (user_id, entity_id, manager_id, campo, valor_anterior, valor_nuevo, ip, user_agent, created_at). Registrar en cada cambio de permisos/estado de gestor.

#### SEC-004 · Media · NIF/CIF nullable en entidad activa
- **Estado:** 🟡 Parcial
- **Evidencia:** Alta exige NIF (`EntityController::store` → `required`). **Update** permite `nif_cif` nullable (`~L1030`) sin condicionar a `status === 1`.
- **Proceder:** En `update`, si `status === 1` → `nif_cif` required + `EntityDocument`. Migración para detectar activas sin NIF.

#### SEC-005 · Alta · XSS en comentarios de entidad
- **Estado:** 🟡 Parcial (Oleada A — 2026-06-17)
- **Evidencia:** `HtmlText::sanitizePlainText` en setter de `comments` + sesión de alta. **Pendiente:** CSP (SEC-032); auditar vistas `{!! !!}`.

---

### F2 — XML / referencias

#### SEC-006 · Crítica · XXE en importación XML
- **Estado:** ✅ Mitigado (Oleada A — 2026-06-17)
- **Evidencia:** `App\Support\SafeXml` con `LIBXML_NONET` + entity loader nulo; usado en `SetController::importXml`.

#### SEC-007 · Alta · Colisión referencias `min(9999)`
- **Estado:** ❌ Pendiente (mitigación parcial)
- **Evidencia:** `ParticipationTicketReference::generate` trunca entity/reserve a 4 dígitos (`min(9999)`). El bloque aleatorio de 10 dígitos reduce colisiones prácticas pero no garantiza unicidad global si IDs > 9999.
- **Proceder:** Ampliar formato (más dígitos para IDs) o unicidad DB (`unique` en `participations.participation_code`) con reintento. Migración de referencias históricas si cambia formato.

#### SEC-008 · Alta · QR sin firma criptográfica
- **Estado:** 🟡 Parcial
- **Evidencia:** **Tacos:** `DesignFormat::buildTacoRef` + `parseTacoRef` con HMAC ✅. **Participaciones:** `ParticipationTicketReference` usa dígito de control, no HMAC ni expiración ❌.
- **Proceder:** Firmar URL/ref de participación (HMAC + timestamp opcional) en generación QR; validar en `apiCheckByReference` / venta. Mantener compatibilidad con QRs impresos (ventana de gracia).

---

### F3–F4 — Trazabilidad / QR

#### SEC-009 · Media · Duplicidad talonarios físicos
- **Estado:** ❌ Pendiente
- **Proceder:** Hash por lote de impresión + `print_order_id` en participaciones; registro de escaneos duplicados (alerta).

#### SEC-010 · Media · Contraseña mín. 6 en autorregistro
- **Estado:** ❌ Pendiente
- **Evidencia:** `AuthController::apiRegister` → `min:6`.
- **Proceder:** Subir a `min:8` o `Password::min(8)->mixedCase()->numbers()`; opcional ` uncompromised()`; alinear con cambio gestor (`min:8`).

#### SEC-011 · Alta · SMS sin rate limit en validación
- **Estado:** 🟡 Parcial
- **Evidencia:** `POST auth/sms/send-code` tiene `throttle:6,1`. **No hay endpoint dedicado** de verificación con throttle; `verifyCode` se llama desde registro (`AuthController`, `GiftRecipientRegistrationController`) sin límite por IP/teléfono en intentos fallidos.
- **Proceder:** Rate limit en registro por IP+teléfono; contador de intentos fallidos en `PhoneVerificationCode`; bloqueo temporal; log de abuso.

#### SEC-012 · Media · Email case-sensitive
- **Estado:** 🟡 Parcial
- **Evidencia:** Varios flujos usan `LOWER(email)` (`GiftRecipientRegistrationController`, `ParticipationGiftService`). Registro/alta panel sigue `unique:users` case-sensitive en MySQL.
- **Proceder:** Normalizar email a minúsculas en `User` mutator; migración `UPDATE users SET email = LOWER(email)`; índice único funcional o columna `email_normalized`.

#### SEC-013 · Media · Sin log de aceptación RGPD
- **Estado:** ❌ Pendiente
- **Evidencia:** `aceptar_condiciones` validado en registro; no hay tabla de consentimientos.
- **Proceder:** Tabla `user_consents` (user_id, type, version, ip, user_agent, text_hash, accepted_at).

#### SEC-014 · Media · Correos de registro no enviados
- **Estado:** 🟡 Parcial
- **Evidencia:** `CommunicationEmailService::sendAndLog` registra `pending/sent/cancelled` en `email_communication_logs`. Falta alerta automática y reintentos en cola.
- **Proceder:** Revisar logs `cancelled` en panel; job de reintento; notificación admin si falla registro comprador.

---

### F5–F6 — Vendedores / adjudicación

#### SEC-015 · Media · Política contraseña vendedores
- **Estado:** ❌ Pendiente
- **Proceder:** Igual que SEC-010 en flujos `SellerService` / invitación PARTILOT; flag `requires_password_setup`.

#### SEC-016 · Media · NIF opcional vendedores externos
- **Estado:** 🔍 Verificar
- **Evidencia:** `SellerService` acepta `nif_cif` nullable; hay reglas `unique` en controlador según `RESPUESTA_INFORME_AUDITORIA.md`.
- **Proceder:** Auditar `SellerController::store_*`; hacer NIF required para externos antes de `STATUS_ACTIVE` y ventas.

#### SEC-017 · Media · Sin snapshot al adjudicar tacos
- **Estado:** ❌ Pendiente
- **Proceder:** Tabla `participation_assignment_snapshots` o JSON en evento de asignación (donativo, comisión, tarifas, entity_id, seller_id, timestamp).

---

### F7–F8 — Liquidaciones / stock

#### SEC-018 · Alta · Sin conciliación tiempo real Stripe ↔ inventario
- **Estado:** 🟡 Parcial
- **Evidencia:** `PrintOrderPaymentReconciliationService`, webhook Stripe, comparación PI en `DesignController` al crear orden. No hay conciliación global ventas digitales/inventario.
- **Proceder:** Extender servicio de conciliación; job periódico; dashboard discrepancias.

#### SEC-019 · Media · Liquidaciones físicas sin justificante
- **Estado:** ❌ Pendiente
- **Evidencia:** Devoluciones permiten efectivo/transferencia con inputs numéricos en UI (`devolutions/create.blade.php`).
- **Proceder:** Adjunto obligatorio o firma gestor principal; log inmutable de cierre.

#### SEC-020 · Media · Compra directa sin log términos
- **Estado:** ❌ Pendiente
- **Proceder:** Igual SEC-013 en `PendingDigitalSaleService` / checkout web.

#### SEC-021 · Control · UTF-8 regresivo
- **Estado:** 🟡 Parcial
- **Proceder:** Tests Feature: guardar nombre con ñ/emoji; export PDF; email; assert UTF-8 en BD.

#### SEC-022 · Alta · Stock flotante → vendido automático
- **Estado:** 🟡 Parcial (diseño intencional)
- **Evidencia:** `DevolutionsController` marca no devueltas como `vendida` en devolución entidad→admin (flujo de negocio).
- **Proceder:** **Decisión de negocio:** si la auditoría exige separar estados, introducir `pendiente_devolucion` / `extraviado` y no pasar a `vendida` sin cobro; documentar excepción si se mantiene flujo actual.

#### SEC-023 · Media · Sin alertas stock pendiente devolución
- **Estado:** ❌ Pendiente
- **Proceder:** Notificación push/email a gestores; widget en panel; job diario.

#### SEC-024 · Alta · Devolver participaciones vendidas
- **Estado:** ✅ Resuelto
- **Evidencia:** `DevolutionsController` rechaza vendidas/pagadas (~L534–551, ~L1943).
- **Proceder:** Mantener tests de regresión API + panel.

#### SEC-025 · Alta · Error consistencia contable F8
- **Estado:** 🔍 Verificar
- **Proceder:** Ejecutar script de reconciliación (`FixSetInconsistencies` / informe auditoría); documentar diferencias; automatizar chequeo post-devolución.

#### SEC-026 · Media · Correos liquidación no enviados
- **Estado:** 🟡 Parcial
- **Evidencia:** Mismo `CommunicationEmailService`; revisar tipos `devolution` en logs.
- **Proceder:** Igual SEC-014 para plantillas de cierre.

#### SEC-027 · Alta · `round()` vs `ceil()` décimos
- **Estado:** 🟡 Parcial
- **Evidencia:** `ReserveController` usa `ceil` para tickets ✅; escrutinio guarda `total_decimos` decimal (migración 2026_06_17) ✅; aún hay `round()` en agregados de `LotteryScrutinyController`.
- **Proceder:** Revisar todos los cálculos de décimos/participaciones; test con reservas 2.5; recálculo histórico si aplica.

#### SEC-028 · Crítica · Memoria en escrutinio
- **Estado:** ❌ Pendiente
- **Evidencia:** `LotteryScrutinyController::process` carga todas las entidades/reservas/participaciones en memoria; sin `chunk()`/`cursor()`.
- **Proceder:** Procesar por entidad o por set con chunks; queue job `ProcessScrutinyJob`; límite `memory_limit` en tests de volumen.

---

### F9–F11 — Negocio / solvencia

#### SEC-029 · Crítica · `remaining_prize_funds` sin decremento
- **Estado:** 📋 Fuera de alcance actual
- **Evidencia:** No existe campo `remaining_prize_funds` en modelos; sí `funds_deposited_amount` en `entity_lottery_prize_settings` (proceso cobro parcial).
- **Proceder:** Implementar al cerrar módulo F11 según `proceso_cobro/implementacion_pago_participaciones.md`: decremento atómico, idempotency key, transacción.

---

### F12 — Impresión / donaciones / prepago (bloque crítico del PDF)

> Muchos hallazgos de esta fase corresponden a un **scope de producto amplio** (certificados, IRPF, KYC códigos, CSV contable) descrito en `proceso_cobro/` pero **no implementado por completo**. Se indica estado real en código Laravel actual.

#### SEC-030 · Crítica · Underpayment impresión Stripe
- **Estado:** ✅ Resuelto (flujo principal)
- **Evidencia:** `DesignController` compara `piAmount` con quote servidor (~L3270–3290); idempotencia PI con lock.
- **Proceder:** Tests negativos; auditar rutas alternativas (remesa, external invitation).

#### SEC-031 · Crítica · SQL Injection notas orden impresión
- **Estado:** 🔍 No reproducido en código actual
- **Evidencia:** `notes` se guarda vía Eloquent; no hay `whereRaw` con notas. Validación `max:4000`.
- **Proceder:** Pentest del endpoint que reportó la auditoría; si era versión antigua, cerrar con evidencia; si hay otro endpoint, parametrizar.

#### SEC-032 · Crítica · XSS nombre peña / diseño
- **Estado:** 🟡 Parcial
- **Evidencia:** HTML de diseño se renderiza con `{!! $html !!}` en PDFs (`pdf_participation.blade.php`, etc.) — **riesgo inherente al editor**. Nombres de entidad en Blade mayormente escapados.
- **Proceder:** Sanitizar HTML al guardar diseño; CSP; separar contenido usuario vs plantilla.

#### SEC-033 · Crítica · Null byte upload diseño
- **Estado:** 🟡 Parcial (Oleada A — 2026-06-17)
- **Evidencia:** Ruta pública eliminada; `DesignController::uploadImage` con auth + `SecureImageUpload` (`hashName`, MIME). App móvil usa `auth.api` → `utils/upload-image` con mismo helper. **Pendiente:** mover fuera de `public/`.

#### SEC-034 · Crítica · Rate limit verificación Stripe
- **Estado:** 🔍 Verificar endpoint concreto
- **Evidencia:** Webhook Stripe existe; rutas panel impresión con `auth`. Si auditoría apunta a API legacy, localizar ruta.
- **Proceder:** Grep en app Ionic + `routes/api.php`; añadir `throttle` a consultas de estado PI.

#### SEC-035 · Crítica · Impresión sin JWT
- **Estado:** 🟡 Parcial (Oleada A — 2026-06-17)
- **Evidencia:** Rutas públicas `api/design/save-format`, `save-snapshot`, `upload-image`, `generarQr` eliminadas. Editor web usa rutas `design-editor/*` y `design.external/*` con sesión + CSRF. App móvil sigue con `auth.api`.

#### SEC-036 · Crítica · IDOR órdenes impresión
- **Estado:** 🟡 Parcial
- **Evidencia:** `PrintShopController` con scope middleware `print_shop.scope`. Verificar que cada `show` comprueba `print_configuration_id` del usuario imprenta.
- **Proceder:** Policy `PrintOrderPolicy`; tests IDOR entre imprentas.

#### SEC-037 · Crítica · Webhook Stripe manipulable
- **Estado:** ✅ Mitigado (Oleada A — 2026-06-17)
- **Evidencia:** `StripeWebhookController` rechaza peticiones si no hay secrets configurados (ya no hace `return true`).

#### SEC-038 · Crítica · Bypass firma supervisor >1000 ejemplares
- **Estado:** 🔍 No encontrado
- **Evidencia:** No hay regla explícita “1000 ejemplares + firma supervisor” en `DesignController`/`DesignApprovalService`.
- **Proceder:** Confirmar con auditor dónde se probó; si es requisito nuevo, validar en backend `total_participations` + rol supervisor + audit row.

#### SEC-039 · Crítica · Inyección en plantilla PDF raw
- **Estado:** ❌ Pendiente
- **Evidencia:** Múltiples `{!! $html !!}` en plantillas PDF diseño.
- **Proceder:** HTML purifier al exportar; DomPDF con opciones restrictivas; evitar JavaScript en HTML guardado.

#### SEC-040 · Crítica · Donación importe 0
- **Estado:** ❌ Pendiente
- **Evidencia:** `apiRegistrarDonacion` permite `importe_donacion`/`importe_codigo` `min:0`; no exige suma > 0.
- **Proceder:** Validar `importe_donacion + importe_codigo > 0` y coherencia con premio mínimo.

#### SEC-041 · Crítica · Overflow donación millonaria
- **Estado:** ❌ Pendiente
- **Proceder:** `max` según premio disponible; tipos `decimal(12,2)`; validar en servidor antes de persistir.

#### SEC-042 · Crítica · Donar participaciones no premiadas
- **Estado:** 🟡 Parcial
- **Evidencia:** Valida ownership, no cobradas, gates de cobro online; **no verifica explícitamente premio > 0** del escrutinio en todos los casos.
- **Proceder:** Exigir `prize_amount > 0` en `getPrizeInfoForReference` antes de donar.

#### SEC-043 · Crítica · IDOR certificados donación
- **Estado:** 📋 Fuera de alcance actual
- **Evidencia:** No hay endpoint de descarga de certificados PDF en código revisado.
- **Proceder:** Al implementar certificados, policy estricta por `user_id`/donation_id.

#### SEC-044 · Crítica · Evasión premio especial al donar
- **Estado:** 🔍 Verificar
- **Proceder:** Incluir premios especiales en snapshot bloqueado pre-donación (cuando exista módulo completo).

#### SEC-045 · Crítica · Evasión comisiones pasarela al donar
- **Estado:** 📋 Fuera de alcance actual
- **Proceder:** Calcular comisiones en servidor al implementar cobro con pasarela en donaciones.

#### SEC-046 · Crítica · Donación fuera de plazo 3 meses
- **Estado:** 🟡 Parcial
- **Evidencia:** `ParticipationWalletValidityService::isParticipationWalletExpired` en donación ✅; confirmar alineación legal 3 meses post-sorteo.
- **Proceder:** Test con fecha sorteo + 91 días; mensaje claro al usuario.

#### SEC-047 · Crítica · Donaciones anónimas sin datos fiscales
- **Estado:** 🟡 Parcial (por diseño)
- **Evidencia:** `anonima` si faltan nombre/apellidos/NIF; legalmente puede ser voluntario según términos.
- **Proceder:** Confirmar con legal; si certificado fiscal → exigir NIF antes de confirmar.

#### SEC-048 · Crítica · Estado certificado manual
- **Estado:** 📋 No implementado

#### SEC-049 · Crítica · IRPF > 40.000 €
- **Estado:** 📋 No implementado

#### SEC-050 · Crítica · ONG no autorizada
- **Estado:** 📋 Parcial (solo entidad emisora en flujo actual)

#### SEC-051 · Crítica · Peñas suspendidas
- **Estado:** 🟡 Parcial
- **Evidencia:** Gates de cobro online/presencial; verificar `entity.status` y suspensión contable explícita.
- **Proceder:** Bloquear donación si entidad `status != activo` o flag `accounting_suspended`.

#### SEC-052 · Crítica · Fecha donación retroactiva
- **Estado:** 🟡 Parcial
- **Evidencia:** `donated_at` se fija con `now()` en create; campo no en fillable público.
- **Proceder:** Quitar de `$fillable`; policy que impida update de `donated_at`.

#### SEC-053 · Crítica · Códigos recarga importe negativo
- **Estado:** 🟡 Parcial
- **Evidencia:** `PrepagoCodigosService::generateCode` rechaza `<= 0`; API externa podría aceptar negativos.
- **Proceder:** Validación API prepago; CHECK DB si hay tabla local de códigos.

#### SEC-054 · Crítica · Códigos sin saldo virtual
- **Estado:** 📋 No implementado (depende API prepago + contabilidad)

#### SEC-055 · Crítica · CSV importes negativos
- **Estado:** 📋 No implementado

#### SEC-056 · Crítica · Comisiones negativas
- **Estado:** 🔍 Verificar en configuración códigos / facturación

#### SEC-057 · Crítica · IBAN destino manipulable Stripe/imprenta
- **Estado:** 🟡 Parcial
- **Evidencia:** Cobros Stripe usan cuenta configurada en `PrintConfiguration`; IBAN administración en billing SEPA desde modelo, no request libre en `BillingDirectDebitService`.
- **Proceder:** Auditar todos los endpoints que acepten IBAN en request; whitelist FK.

#### SEC-058 · Crítica · Cuenta destino código a terceros
- **Estado:** 📋 Depende módulo prepago completo

#### SEC-059 · Crítica · KYC > 2.000 €
- **Estado:** 📋 No implementado

#### SEC-060 · Crítica · Cierre caja con prepago pendiente
- **Estado:** 📋 No implementado

#### SEC-061 · Crítica · Códigos con fondos anulados
- **Estado:** 📋 No implementado

#### SEC-062 · Crítica · Stress códigos temporales
- **Estado:** 📋 No implementado

#### SEC-063 · Crítica · Rate limit PDFs actas donación
- **Estado:** 📋 No implementado (sin PDF actas)

#### SEC-064 · Crítica · Bypass rate limit X-Forwarded-For
- **Estado:** ❌ Pendiente
- **Evidencia:** `TrustProxies` confía headers forwarded; `$proxies` no restringido en código.
- **Proceder:** Configurar `TRUSTED_PROXIES` en producción; rate limit por IP real (`Request::ip()` tras trust correcto).

#### SEC-065 · Crítica · Idempotencia webhooks bancarios donación
- **Estado:** 📋 No implementado

---

### F13 — SEPA / remesas facturación

#### SEC-066 · Crítica · SEPA importe ≤ 0
- **Estado:** 🟡 Parcial
- **Evidencia:** `BillingDirectDebitService` aborta si `controlSum <= 0` ✅. `SepaPaymentOrderController` exige `min:0.01` por beneficiario ✅.
- **Proceder:** CHECK DB `amount > 0`; validar órdenes legacy.

#### SEC-067 · Crítica · SEPA sin mandato firmado
- **Estado:** 🟡 Parcial
- **Evidencia:** `BillingDirectDebitService` usa `billing_sepa_mandate_signed_at` o **fallback** a `created_at` ⚠️.
- **Proceder:** Bloquear remesa si no hay mandato explícito firmado; UI de captura mandato.

#### SEC-068 · Crítica · IBAN inválido SEPA
- **Estado:** 🟡 Parcial
- **Evidencia:** `AdministrationBillingService::hasValidBillingIban`; XML generator escapa nombres (`escapeXml`).
- **Proceder:** Validar IBAN con regla `Iban`; rechazar caracteres no alfanuméricos en beneficiario.

#### SEC-069 · Crítica · Sobregiro remesa vs saldo premios
- **Estado:** 📋 Aplica a remesas de premios (F14), no a billing charges actuales
- **Proceder:** Al implementar remesas de premios, reservar saldo antes de crear orden.

#### SEC-070 · Crítica · XXE en XML ISO 20022
- **Estado:** ✅ Resuelto (generación)
- **Evidencia:** `BillingDirectDebitXmlGeneratorService` construye XML con `DOMDocument` + `escapeXml`, sin parsear entrada externa.
- **Proceder:** Si en el futuro se **importa** XML bancario, aplicar mismas defensas que SEC-006.

---

### F14 — Transferencias / RBAC

#### SEC-071 · Crítica · Confirmar transferencia sin admin
- **Estado:** 🟡 Parcial (por diseño doble opt-in usuario)
- **Evidencia:** `TransferCollectionVerificationController::confirm` es **público por token** (usuario final confirma su email), no es aprobación admin.
- **Proceder:** Clarificar con auditor; si se refiere a export remesa SEPA → exigir rol `super_admin` en `SepaPaymentOrderController::markPaid`.

#### SEC-072 · Crítica · Transferencia sin saldo
- **Estado:** 🟡 Parcial
- **Evidencia:** `EntityLotteryPrizePaymentService` valida `funds_deposited` vs `funds_required` para activar cobros; falta reserva atómica al crear `ParticipationCollection`.
- **Proceder:** Implementar según `proceso_cobro/`.

#### SEC-073 · Crítica · Transferencia con participaciones ya cobradas
- **Estado:** 🟡 Parcial
- **Evidencia:** API cobro filtra `collected_at` null; confirmación transferencia debe re-validar en `confirmVerification`.
- **Proceder:** Test E2E; lock pesimista en colección.

---

### F15 — Logs forenses

#### SEC-074 · Crítica · Borrado hard logs forenses
- **Estado:** 📋 No implementado
- **Proceder:** Tabla append-only; sin `DELETE` en app; permisos DB restringidos.

#### SEC-075 · Crítica · Edición logs
- **Estado:** 📋 No implementado
- **Proceder:** Sin `UPDATE`; hash encadenado opcional.

#### SEC-076 · Crítica · Spoofing transiciones estado
- **Estado:** 🟡 Parcial
- **Evidencia:** Máquina de estados en participaciones/devoluciones informal; `print_order_status_audits` append-only parcial.
- **Proceder:** Servicio de transiciones con whitelist; rechazar saltos inválidos.

#### SEC-077 · Crítica · CRLF en IP/User-Agent
- **Estado:** ❌ Pendiente
- **Proceder:** Sanitizar `\r\n` al guardar IP/UA en logs; truncar longitud; IP desde proxy de confianza.

---

## Rutas API públicas — acción transversal urgente

Revisar y proteger en `routes/api.php` (sin middleware):

| Ruta | Riesgo |
|------|--------|
| `POST upload-image` | SEC-001, SEC-033 |
| `POST generarQr` | Abuso generación |
| `POST /design/save-format` | SEC-035 |
| `POST /design/save-snapshot` | SEC-035 |
| `GET/DELETE /check-delete`, `/delete` | Autorización |
| `POST /scrutiny/generate` | Abuso computacional |

**Proceder:** Agrupar bajo `auth:sanctum` + permisos; dejar público solo lo estrictamente necesario (ticket check, resultados lotería).

---

## Ítems ya tratados en desarrollo reciente (fuera del PDF)

| Tema | Notas |
|------|--------|
| Bloqueo escrutinio sin devolución entidad→admin | `EntityLotteryPrizePaymentService::administrationCanRunScrutiny` |
| Vendedor bloqueado en asignación | `SellerController::saveAssignments` ~L2699 |
| Décimos escrutinio decimal | Migración `2026_06_17_120000_change_total_decimos_to_decimal` |

---

## Cómo usar este documento

1. **Priorizar** por oleadas y por estado ❌ crítico con código existente (no solo spec).
2. Por cada fix: issue en tracker con ID `SEC-xxx`, PR pequeño, test de regresión si aplica.
3. Marcar ítems 📋 como “depende módulo cobro” para no mezclar con hotfixes de seguridad del panel actual.
4. Tras cada sprint, actualizar la columna **Estado** y la fecha de revisión en este archivo.

---

## Referencias en repo

- `RESPUESTA_INFORME_AUDITORIA.md` — auditoría técnica panel (feb 2026)
- `AUDITORIA_DESARROLLO_SIPART.md` — gap funcional producto
- `proceso_cobro/` — especificación módulo cobro (F11–F15 del PDF)
- `docs/plan_operativo_impresion.md` — impresión y pagos
