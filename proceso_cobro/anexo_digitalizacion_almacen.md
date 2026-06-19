# Anexo: digitalización, almacén y modalidad de pago

**Referencias:** `docs/flujo_pago_participaciones.md` · `proceso_cobro/implementacion_pago_participaciones.md`  
**Estado:** En implementación (junio 2026)  
**Relación con sprints 1–5:** Este anexo **complementa** el módulo de cobro de premios ya implementado.

---

## 1. Tres conceptos que no se deben mezclar

| Concepto | Qué es | Dónde se configura | Estado |
|----------|--------|-------------------|--------|
| **Modalidad de pago de premios** | ¿Presencial, online PARTILOT u online entidad? | Modal al **liquidar devolución entidad→admin** | ✅ |
| **Cierre de digitalización** | Hasta cuándo el usuario puede digitalizar o guardar en almacén | **Configuración del sorteo** (`deadline_date`, status, `digitalization_closed_at`) | ✅ |
| **Almacén** | Física registrada en app solo para consulta de premio | Acción del **usuario** al escanear | ✅ |

---

## 2. Modalidad de pago

### 2.1 Presencial — paga la entidad

- Físicas **no digitalizadas** → cobro en sede (app gestor / entidad).
- Sin bloqueo general PARTILOT si **solo** hay físicas.
- Caso híbrido (digitales vendidas): PARTILOT paga las digitales tras ingreso + contrato + activación superadmin.

### 2.2 Online — paga PARTILOT

- Remesa gestionada por PARTILOT.
- Requiere: **100 % fondos** + **contrato** + **activación superadmin**.
- Campo: `prize_payment_mode=online`, `online_payer=partilot`.

### 2.3 Online — paga la entidad (legacy)

- La entidad gestiona remesas desde su panel.
- Tras el escrutinio el cobro online se habilita **automáticamente** para usuarios, **sin** bloqueo PARTILOT (fondos/contrato/activación).
- Campo: `prize_payment_mode=online`, `online_payer=entity`.
- Opción **C** en modal de devolución (web + app gestor).

---

## 3. Digitalización de participación física

- **Irreversible** (`wallet_mode=digital`).
- Si tiene premio → solo cobro **online** (nunca presencial en gestor).
- Plazo: hasta **cierre del sorteo** (`LotteryDigitalizationService`).
- Aviso **solo** en flujo de digitalización (app usuario).

---

## 4. Almacén

- `wallet_mode=storage` — solo consulta en cartera.
- **No** cobrar, donar, regalar ni transferir online.
- Copy informativo en detalle de cartera.
- Notificación push/email tras escrutinio (`NotifyWalletStorageAfterScrutinyJob`).

---

## 5. Matriz resumen

| Estado en app | Cobro presencial (entidad) | Cobro online | Donar / regalar / código |
|---------------|----------------------------|--------------|---------------------------|
| Física sin registrar | Sí (papel) | No | No |
| **Almacén** | Sí (papel) | No | No |
| **Digitalizada** | No | Sí (según gates) | Sí (según gates) |
| Nativa digital (1D) | No | Sí (según gates) | Sí (según gates) |

---

## 6. Implementación técnica

| Componente | Archivo / notas |
|------------|-----------------|
| Migración | `2026_06_18_100000_add_annexo_digitalization_wallet_fields.php` |
| Cierre digitalización | `LotteryDigitalizationService` |
| Pagador online | `online_payer` en `entity_lottery_prize_settings` |
| API digitalizar | `POST /wallet/participations/link` |
| API almacén | `POST /wallet/participations/store-warehouse` |
| Notificación almacén | `NotifyWalletStorageAfterScrutinyJob` |

---

*Actualizar guía QA (`guia_pruebas_cobro_premios.md`) con escenarios almacén / online entidad.*
