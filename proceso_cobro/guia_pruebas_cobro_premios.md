# Guía de pruebas integrales — Cobro de premios

**Proyecto:** PARTILOT / SIPART  
**Ámbito:** Sprints 1–5 (modalidad devolución, activación superadmin, presencial, multientidad, contrato y override)  
**Referencias:** `docs/flujo_pago_participaciones.md` · `proceso_cobro/implementacion_pago_participaciones.md`

**Cómo usar esta guía**

1. Asigna un responsable y fecha a cada bloque.
2. Marca `[x]` cuando la prueba pase; deja `[ ]` si falla o queda pendiente.
3. En **Incidencias**, anota URL, usuario, sorteo/entidad y qué ocurrió.
4. Ejecuta primero **Preparación** y al menos un flujo end-to-end de la sección G.

**Leyenda de entorno**

| Campo | Valor |
|-------|-------|
| Entorno | |
| Fecha inicio | |
| Fecha cierre | |
| Superadmin | |
| Gestor entidad | |
| Usuario cartera | |
| Sorteo de prueba | |
| Entidad A | |
| Entidad B (multientidad) | |

---

## A. Preparación del entorno

- [ ] Migraciones aplicadas:
  - [ ] `entity_lottery_prize_settings` + `entity_lottery_prize_activation_logs`
  - [ ] `participation_collection_items` (`entity_id`, `amount`)
  - [ ] Campos de contrato (`contract_token`, `contract_sent_at`, etc.)
- [ ] Cola/correo configurado (o revisar `storage/logs` si mail en log)
- [ ] Sorteo con **escrutinio publicado** y premios para al menos una entidad
- [ ] Participaciones de prueba:
  - [ ] Físicas premiadas (vinculadas o no a cartera)
  - [ ] Digitales premiadas (`1D/...`) en cartera de usuario
- [ ] Usuarios de prueba creados y con acceso:
  - [ ] Superadmin (panel web)
  - [ ] Gestor de entidad (web + app `gestor-devolucion` / `gestor-pago`)
  - [ ] Usuario final (app cartera + `cobrar-gestionar`)
- [ ] Devolución entidad→administración **pendiente de liquidar** (para probar modalidad)

**Notas preparación:**

```
(espacio libre)
```

---

## B. Sprint 1 — Modalidad en devolución y candados API

### B.1 Elección de modalidad (web)

- [ ] Ir a liquidación devolución entidad→admin (`devolutions/create`)
- [ ] Al confirmar liquidación admin, aparece modal **presencial vs online**
- [ ] Sin elegir modalidad, el botón de confirmar permanece deshabilitado o muestra error
- [ ] Tras elegir y confirmar, la devolución se guarda correctamente
- [ ] En BD existe fila en `entity_lottery_prize_settings` con `prize_payment_mode` y `mode_locked_at`

### B.2 Elección de modalidad (app gestor)

- [ ] En `gestor-devolucion`, flujo liquidación a administración muestra el mismo modal
- [ ] Misma validación: no confirma sin modalidad
- [ ] Modalidad guardada coincide con la elegida en web (misma entidad/sorteo)

### B.3 Escrutinio y sincronización

- [ ] Tras publicar/guardar escrutinio, se actualiza `funds_required_amount` en settings
- [ ] Modalidad **online** → `funds_status = pending` (si hay premio digital a cargo PARTILOT)
- [ ] Modalidad **presencial sin digitales** → `presencial_payments_enabled = true` tras escrutinio

### B.4 Gates en API (sin activación)

- [ ] `GET /api/wallet/participations` → participación premiada con `payment_blocked: true` y `user_message`
- [ ] `GET /api/wallet/participations/cobrables` → no incluye participaciones bloqueadas
- [ ] `POST /api/wallet/cobro` → rechaza si no cobrable
- [ ] `POST /api/wallet/donacion` → rechaza si no cobrable
- [ ] `POST /api/management/participations/validate-for-payment` → no devuelve bloqueadas
- [ ] `POST /api/management/participations/register-payment` → no registra bloqueadas

**Incidencias B:**

| # | Descripción | Severidad | Responsable |
|---|-------------|-----------|-------------|
| | | | |

---

## C. Sprint 2 — Panel superadmin y cartera

### C.1 Panel superadmin

- [ ] Menú **Cobro de premios** visible solo como superadmin
- [ ] Listado `/prize-payments` carga entidades/sorteos con settings
- [ ] Filtros por modalidad y estado de fondos funcionan
- [ ] Detalle de entidad/sorteo muestra resumen (fondos, contrato, estado cobro)

### C.2 Activación modalidad online

- [ ] **Confirmar ingreso de fondos** → `funds_status = confirmed`
- [ ] **Marcar contrato firmado** (manual) → `contract_status = signed`
- [ ] **Activar cobro online** → `online_payments_enabled = true`
- [ ] Mensajes bloqueado/desbloqueado editables y guardados

### C.3 Notificaciones y app usuario

- [ ] Tras activar online: usuario premiado recibe push y/o email
- [ ] **Cartera:** badge pasa de «Premio bloqueado» a «Cobro disponible»
- [ ] Detalle de participación muestra `user_message` correcto
- [ ] **Cobrar/Gestionar:** participación aparece en lista cobrable
- [ ] **Cobrar/Gestionar:** desaparece de «Premios pendientes de activación»

**Incidencias C:**

| # | Descripción | Severidad | Responsable |
|---|-------------|-----------|-------------|
| | | | |

---

## D. Sprint 3 — Presencial, digitales y anti-doble cobro

### D.1 Presencial solo físicas

- [ ] Devolución con modalidad **presencial** y **sin** digitales vendidas
- [ ] Tras escrutinio: `presencial_payments_enabled = true` sin intervención superadmin
- [ ] App **gestor-pago:** validar rango/referencia y registrar pago OK
- [ ] Usuario con participación física premiada ve **contacto presencial** en cartera (detalle)
- [ ] Entidad edita contacto en web (`lottery` → premio de mi entidad) y se refleja en cartera

### D.2 Presencial + digitales vendidas (caso 3.3)

- [ ] Devolución presencial con digitales vendidas en el sorteo
- [ ] Tras escrutinio: cobros **bloqueados** (online y presencial gestor)
- [ ] Usuario digital ve premio bloqueado con mensaje PARTILOT
- [ ] Superadmin: confirmar fondos + contrato + **activar presencial**
- [ ] Digitales pasan a cobrables online; físicas habilitadas en gestor-pago

### D.3 Exclusiones y LOPD

- [ ] Participación **nativa digital** (`1D/...`): gestor-pago la rechaza con mensaje claro
- [ ] Participación ya **cobrada online** (colección verificada): gestor-pago muestra mensaje LOPD genérico
- [ ] Participación **pagada en gestor**: no aparece en cobrables online del usuario
- [ ] `validate-for-payment` devuelve array `rejected` con motivo cuando aplica

### D.4 Auditoría pago presencial

- [ ] Tras pago en gestor, participación `status = pagada` y `collected_at` informado
- [ ] Log `payment_registered_presencial` en historial superadmin

**Incidencias D:**

| # | Descripción | Severidad | Responsable |
|---|-------------|-----------|-------------|
| | | | |

---

## E. Sprint 4 — Multientidad y SEPA

### E.1 Cobro online multientidad (app)

- [ ] Usuario con premios de **entidad A** y **B**, ambas con cobro online activado
- [ ] En **Cobrar/Gestionar** (modo cobro) puede seleccionar participaciones de A y B a la vez
- [ ] Transferencia única se crea con importe total correcto
- [ ] En BD, `participation_collection_items` tienen `entity_id` y `amount` por ítem

### E.2 Validaciones multientidad

- [ ] Mezcla entidad habilitada + no habilitada → API rechaza con mensaje claro
- [ ] **Donación** sigue exigiendo **una sola entidad** (rechaza mezcla)

### E.3 Remesa SEPA

- [ ] Con colección verificada de entidad solvente → generar SEPA OK
- [ ] Con entidad del lote **sin** fondos confirmados o cobro inactivo → error al crear SEPA
- [ ] Colección multientidad: falla si **cualquier** entidad implicada no está solvente

**Incidencias E:**

| # | Descripción | Severidad | Responsable |
|---|-------------|-----------|-------------|
| | | | |

---

## F. Sprint 5 — Contrato, auditoría y override

### F.1 Firma de contrato por email

- [ ] Superadmin: **Enviar contrato por email** (contrato en estado `pending`)
- [ ] Entidad recibe email con enlace `/contrato-premio/firmar/{token}`
- [ ] Página muestra texto del contrato y campos nombre + checkbox
- [ ] Sin aceptar términos → no firma
- [ ] Con nombre + aceptación → `contract_status = signed`, enlace invalidado
- [ ] Segundo uso del mismo enlace → error «enlace no válido»
- [ ] Historial superadmin: eventos `contract_sent` y `contract_signed` con detalle

### F.2 Firma manual superadmin

- [ ] **Marcar firmado (manual)** funciona si la entidad firmó en papel
- [ ] Permite continuar flujo de activación

### F.3 Auditoría ampliada

- [ ] Tabla historial muestra: fecha, evento legible, usuario, detalle
- [ ] Eventos esperados visibles según flujo: `mode_selected`, `funds_confirmed`, `online_activated`, `presencial_activated`, `payment_registered_presencial`, etc.

### F.4 Override superadmin

- [ ] **Cambiar modalidad** (online ↔ presencial) resetea cobros activos
- [ ] Tras cambio, usuarios/gestor vuelven a ver bloqueos hasta reactivar
- [ ] **Bloquear cobros** desactiva online y presencial
- [ ] Tras bloquear, reactivar según modalidad y comprobar que vuelve a funcionar

**Incidencias F:**

| # | Descripción | Severidad | Responsable |
|---|-------------|-----------|-------------|
| | | | |

---

## G. Flujos end-to-end (obligatorios antes de cerrar)

Marca cada flujo completo cuando todos sus pasos intermedios hayan pasado.

### G.1 Online puro

| Paso | OK |
|------|-----|
| Devolución → modalidad **online** | [ ] |
| Escrutinio publicado | [ ] |
| Superadmin: fondos + contrato + activar online | [ ] |
| Usuario: cobro por transferencia (doble opt-in email) | [ ] |
| Participación desaparece de cobrables; gestor no puede pagar | [ ] |

**Flujo G.1 completo:** [ ]

---

### G.2 Presencial solo físicas

| Paso | OK |
|------|-----|
| Devolución → modalidad **presencial** (sin digitales) | [ ] |
| Escrutinio → presencial auto-habilitado | [ ] |
| Gestor paga en app | [ ] |
| Usuario ve contacto presencial en cartera | [ ] |

**Flujo G.2 completo:** [ ]

---

### G.3 Presencial híbrido (físicas + digitales)

| Paso | OK |
|------|-----|
| Devolución presencial con digitales vendidas | [ ] |
| Todo bloqueado tras escrutinio | [ ] |
| Superadmin: fondos + contrato (email o manual) + activar presencial | [ ] |
| Usuario cobra digital online | [ ] |
| Gestor paga física en app | [ ] |

**Flujo G.3 completo:** [ ]

---

### G.4 Anti-doble cobro

| Paso | OK |
|------|-----|
| Usuario inicia y **confirma** cobro online | [ ] |
| Gestor intenta pagar misma participación → bloqueado (LOPD) | [ ] |

**Flujo G.4 completo:** [ ]

---

### G.5 Multientidad

| Paso | OK |
|------|-----|
| Dos entidades con cobro online activo | [ ] |
| Usuario cobra A+B en una sola transferencia | [ ] |
| Donación con mezcla de entidades → rechazada | [ ] |

**Flujo G.5 completo:** [ ]

---

### G.6 Override de emergencia

| Paso | OK |
|------|-----|
| Superadmin bloquea cobros | [ ] |
| Usuario y gestor no pueden cobrar/pagar | [ ] |
| Superadmin reactiva según modalidad | [ ] |
| Cobros vuelven a funcionar | [ ] |

**Flujo G.6 completo:** [ ]

---

## H. Regresión (no debe romperse)

- [ ] Donación + código prepago (monoentidad, misma administración)
- [ ] Caducidad cartera (3 meses desde sorteo) — participación caducada no cobrable
- [ ] Regalo de participación (pendiente / aceptado) sin afectar gates incorrectamente
- [ ] Devolución vendedor / `solo_devolucion` **sin** modal de modalidad de premios
- [ ] Ticket social / consulta participación por referencia
- [ ] Escáner y vinculación de participación física a cartera

**Incidencias H:**

| # | Descripción | Severidad | Responsable |
|---|-------------|-----------|-------------|
| | | | |

---

## I. Cierre de la ronda de pruebas

| Criterio | OK |
|----------|-----|
| Todos los flujos G.1–G.6 completos | [ ] |
| Sin incidencias **críticas** abiertas | [ ] |
| Incidencias menores documentadas en tracker | [ ] |
| Demo realizada al responsable de producto | [ ] |

**Responsable QA / fecha cierre:**

```
Nombre:
Fecha:
Versión / commit:
Comentarios finales:
```

---

## Anexo — Rutas y pantallas útiles

| Qué | Dónde |
|-----|-------|
| Panel activación premios | Web superadmin → **Cobro de premios** (`/prize-payments`) |
| Liquidación devolución | Web → Devoluciones |
| Premio entidad + contacto presencial | Web entidad → Sorteo → Premio de mi entidad |
| Firma contrato (público) | `/contrato-premio/firmar/{token}` |
| Cartera usuario | App → Cartera (tab3) |
| Cobrar / donar | App → Cobrar/Gestionar |
| Pago presencial gestor | App → Gestor → Pago (`gestor-pago`) |
| Modalidad en devolución | App → Gestor devolución |
| Órdenes SEPA entidad | Web → Configuración → Órdenes pago entidades |

---

## J. Automatización (terminal)

Puedes ejecutar gran parte de esta guía **sin abrir la web**, usando los scripts incluidos en el proyecto.

### Comando Artisan (recomendado en local/staging)

Crea datos temporales (`QA_PRIZE_*`), ejecuta checks de las secciones A, B, D y Anexo, y los elimina al terminar:

```powershell
cd H:\xampp3\htdocs\sipart
php artisan qa:prize-payments --bootstrap --with-phpunit
```

Genera informe en `proceso_cobro/resultados_qa_cobro_premios.md` (ruta personalizable con `--report=ruta.md`).

Con datos ya preparados en el entorno (sección A manual):

```powershell
php artisan qa:prize-payments --entity=ID_ENTIDAD --lottery=ID_SORTEO --user=ID_USUARIO_CARTERA
```

Salida esperada: líneas `[OK]` / `[FAIL]` por check. Exit code `0` = todo OK, `1` = hay fallos.

**Requisitos:** MySQL en marcha (XAMPP), migraciones aplicadas, `APP_URL` coherente con el entorno.

### PHPUnit (CI / regresión)

```powershell
php artisan test --testsuite=PrizePayment
```

Incluye tests unitarios del servicio de gates y tests Feature de API cartera.

**Nota:** SQLite no es compatible con todas las migraciones del proyecto; los tests Feature usan la misma BD MySQL configurada en `.env`.

### Qué cubren los scripts vs. qué sigue manual

| Cubierto por scripts | Sigue siendo manual |
|----------------------|---------------------|
| Schema / migraciones (A) | Modales visuales web/app |
| Gates API cartera (B.4) | Push/email en dispositivo |
| lockMode / online entidad (B, anexo) | Flujos G.1–G.6 completos con email doble opt-in |
| LOPD / nativa digital (D.3) | Panel superadmin UI (C.1) |
| Almacén / digitalización (anexo) | SEPA con banco real (E.3) |
| | Firma contrato email real (F.1) |

Implementación: `app/Services/Qa/PrizePaymentQaRunner.php`, `app/Console/Commands/QaPrizePaymentsCommand.php`, `tests/Feature/PrizePayment/`.

---

*Documento vivo. Actualizar si se añaden escenarios o cambia el alcance funcional.*
