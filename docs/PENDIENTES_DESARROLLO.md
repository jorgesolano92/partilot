# Pendientes de desarrollo (lista única)

> **No duplicar tareas en otros `.md`.**  
> Completadas → `../TAREAS_REALIZADAS.md` · Visión global → `../AUDITORIA_DESARROLLO_SIPART.md`

Última revisión: 12/08/2026

---

## Accesos panel/app y roles — audios 12-08 (cliente)

> Origen: `audios/` + detalle en `audios/tareas-cliente-audios.md`.  
> **Dos superficies:** (1) panel de administración Laravel; (2) **web app** tipo Ionic para usuario / vendedor / gestor.  
> El escáner es comportamiento de la app/web app, no del panel admin.

### Decisión / producto
- [x] **Web app:** SPA Vue 3 en `H:\Users\Jorge\proyectos\partilot-webapp` (API `/api/auth/login-usuario`). Scaffold + login + modos usuario/vendedor/gestor + selector entidad MVP.
- [x] **Panel administración:** gestores no responsables bloqueados en login web (mensaje hacia app/web app).
- [ ] **Alcance gestor responsable en panel admin:** todo lo actual vs solo acciones de responsable (p. ej. devolución a administración); resto por web app.
- [ ] **Venta por gestor responsable:** confirmar que solo vende si también es vendedor.

### Panel de administración (Laravel)
- [x] **Bloquear acceso al panel admin** a gestores no responsables.
- [ ] **Selector de entidad activa** en cabecera (logo/nombre + desplegable); persistir y aplicar a devoluciones, altas, asignaciones. *(Parcial: `ActiveEntityContext`; revisar UX y cobertura.)*
- [ ] **Devolución multi-entidad:** no liquidar sin entidad activa clara.

### Web app / app Ionic
- [x] **Cambio a modo gestor (web app):** si multi-entidad, preguntar de cuál; al volver a “gestor”, repreguntar.
- [x] **Vendedor multi-entidad:** al vender, preguntar entidad (SPA Vue).
- [ ] **Escáner por rol** (cámara en web pendiente; MVP por referencia):
  - [x] Usuario: consulta por referencia.
  - [x] Vendedor: venta por referencia/QR (+ manual/digital en SPA).
  - [x] Gestor: sin escáner genérico; home de acciones (+ devolución/pago en SPA).

### SPA Vue (`partilot-webapp`) — estado 31/08/2026
- [x] Usuario: cartera, digitalizar, código, regalo, cobro/donación, historial, notificaciones, perfil (lectura), registro.
- [x] Vendedor: multi-entidad, venta referencia/manual/digital, participaciones, mis ventas.
- [x] Gestor: selector entidad, participaciones, vendedores + invitar, devolución vendedor→entidad, pago presencial.
- [ ] Cámara / escáner nativo en navegador (MVP por referencia OK).
- [ ] Edición de perfil vía API `/profile` (backend aún sin métodos).
- [ ] Devolución entidad→administración (sigue en panel Laravel para responsable).


### Ya tocado en esta línea (no reabrir sin necesidad)
- [-] Topbar panel admin: un solo rol (`ENTIDAD` / `GESTOR RESPONSABLE` / `GESTOR`).
- [-] Listado vendedores: botón **Añadir** para gestor con `permission_sellers`; editar/eliminar ficha solo superadmin.

---

## Alta prioridad

- [-] **Diseño — Configurar salida:** tras “Siguiente”, pantalla resumen, PDFs visibles/descargables, vuelta al listado sets.
- [-] **Diseño — Imagen de fondo** y **agregar imágenes** (subida + persistencia + render PDF).
- [-] **Diseño — Icono imprimir** (error/timeout; valorar jobs async).
- [-] **XML sets:** referencias reales 21 dígitos (físicas); referencias únicas (digitales); `<urlweb>`; importe por número; `floor` en cálculo máx. participaciones vs reserva.
- [ ] **Sets digitales:** generar imagen participación; flujo asignación; vincular venta a `user_id` cliente (`tacos.md` / 16-02 T13). Ver también **venta digital + SMS** (abajo).
- [-] **Magic links administración** (`nuevo desarrollo magic links.md`): envío solo por superadmin, usuario inmutable, establecer contraseña por enlace.
- [-] **Gestor responsable:** aceptar/denegar invitación; bloquear entidad si no hay responsable activo (`PRESUPUESTO_ALTA_ADMIN_ENTIDADES.md`).
- [-] **Asignación:** rechazar vendedor no activo en `saveAssignments` (auditoría §3.2).

## Media prioridad

### Venta participaciones digitales — email y SMS (no implementado)

**Estado en código:** no hay envío de SMS ni integración (Twilio, etc.) en Laravel ni en la app Ionic.

| Fuente | Qué dice |
|--------|----------|
| **`tacos.md`** (§ venta digital) | Tras vender, modal con **email** del cliente. Si existe usuario → cartera directa. Si no → confirmación y **email** de invitación a registrarse; venta pendiente en BD/caché hasta registro. **No menciona SMS.** |
| **`nuevas tareas 16-02.md` T13** (texto archivado en git) | Opción **B — código/enlace de reclamación:** tras la venta, enlace o código por participación o lote; enviar por **email/SMS**; el cliente abre en app/web y el backend asocia a su cartera. Solo propuesta; no está en spec ni en código. |
| **`especificacion.md`** | Sin SMS en venta digital. |

**Pendiente a decidir e implementar:**

- [ ] Confirmar con negocio si el SMS es requisito (además o en lugar del email en algún caso).
- [ ] Si aplica SMS: proveedor, plantillas, coste, consentimiento/opt-in y en qué paso del flujo de venta digital se dispara (comprador no registrado, código de reclamación, recordatorio de registro, etc.).
- [ ] Alinear con el flujo de `tacos.md` (email + `pending_digital_sales`) y, si se usa opción B, unificar “reclamar participación” por enlace/código vía email y/o SMS.

Referencias: `../tacos.md` (líneas ~50, ~127–134) · historial `nuevas tareas 16-02.md` (Tarea 13, opción B, ~l. 121 y 137 en git).

---

- [-] Imágenes admin/entidad en: alta gestor, `sets/store-entity`, devoluciones, ficha vendedor (`mejoras.md`).
- [-] Navegación atrás en `sets/add/information` → `sets/store-entity`.
- [-] Editor: zoom con scroll (no desbordar contenedor); bordes al seleccionar/redimensionar textos.
- [-] QR visible en editor de **portada** (16-02 T7.2).
- [-] Corregir **total participaciones** por defecto (600) donde no lee del set.
- [ ] Plantillas diseño SIPART / TIKET / ASG + selector reverso (`especificacion-vs-desarrollo.md`).
- [ ] Web venta / TPV / iframes flujo completo spec.
- [ ] “Enviar a imprimir”: imprenta, tacos, pago entidad, estados, bloqueo edición (`plan_operativo_impresion.md`).
- [-] Informe 22-ene: `Seller.status` sin cast boolean; sync `group_id` solo si viene en request; `SpanishDocument` en User/Entity/Seller; limpiar prefijo ES duplicado en IBAN.
- [-] Performance `POST /api/sellers/save-assignments`.
- [-] App: API aceptar/rechazar invitación vendedor (hoy “próximamente”).
- [ ] App: mostrar etiquetas `kind` en notificaciones (mapa en `notification-kind.labels.ts`).

## Baja / fase 2

- [ ] Implementar lógica en `php artisan sipart:pending-payments-check`, `sipart:new-lotteries-announce` + descomentar en `Kernel::schedule()`.
- [x] `sipart:lottery-deadline-reminder` (avisos 3/2/1/0 días, email + modal).
- [-] Autosave diseño en servidor; conflictos edición simultánea.
- [ ] Asignación vendedor: firma digital offline + email entidad (spec).
- [-] Devoluciones por series/fracciones (pendiente definición negocio).
- [-] Diseño externo: revocación, expiración 4 semanas, una invitación activa (`cambios dedign.md`).
- [-] Devoluciones participaciones digitales en app (`devoluciones-participaciones-digitales.md`).
- [-] Participaciones asignadas: permitir editar comentario sin editar vendedor (`mejoras.md`).
- [ ] Solo puede ver el comentario el superadmin y administracion
