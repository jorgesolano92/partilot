PARTILOT — Especificacion funcional: Activacion y pago de participaciones
Pagina 1 de 4
Flujo de activacion y pago de participaciones
Fisica y Digital — Especificacion funcional v1.0
1. Configuracion del modo de pago en la devolución
Cuando la entidad va a confirma la transmision de devolución a la administración, el sistema
presenta un modal con un checkbox obligatorio para seleccionar la modalidad de pago. Sin
esta selección, el botón 'Aceptar y liquidar' permanece deshabilitado.
Opcion A — Pago presencial
• La entidad gestiona el cobro en sus
instalaciones
• No requiere firma de contrato previa (salvo
participaciones digitales)
• No requiere ingreso de fondos en PARTILOT
(salvo participaciones digitales)
• El sistema habilita automáticamente el panel
presencial si no hay participaciones digitales
vendidas
• Si hay participaciones digitales, el panel lo
habilitara Partilot de forma manual.
Opcion B — Pago online
• PARTILOT gestiona la remesa bancaria
completa
• Requiere ingreso del 100% del importe
premiado
• Requiere firma de contrato especifico por
sorteo
• El superadministrador activa el pago desde
su panel
Bloqueo tras confirmacion:
Una vez confirmada la devolucion, la entidad no puede modificar la modalidad de pago elegida.
Solo el superadministrador tiene capacidad de cambiarla a posteriori.
――― Despues del sorteo ―――
2. Escrutinio y asignacion de premios
Tras el escrutinio oficial, el sistema asigna de forma automatica el premio proporcional a
todas las participaciones vendidas, tanto fisicas como digitales. Sin embargo, el pago queda
bloqueado en ambas modalidades hasta que se cumplan las condiciones específicas de
cada una.
Participaciones fisicas
• Se leen mediante número secuencial en el
panel presencial
• Tabla con: sorteo, cantidad jugada, premio y
estado
• Si ya fue pagada: se muestra fecha, hora y
gestor
Participaciones digitales
• Cartera del usuario (app/web) se actualiza
automaticamente
• Distintivo 'Premiada' o 'No premiada' con
importe exacto
• Se envia mail y notificacion push al usuario
tras el escrutinio
――― Gestion segun modalidad ―――
3. Modalidad presencial — Flujo detallado
PARTILOT — Especificacion funcional: Activacion y pago de participaciones
Pagina 2 de 4
3.1 Caso A: Solo participaciones físicas
El sistema habilita automáticamente el cobro en el panel presencial sin necesidad de
ingreso previo de fondos ni firma de contrato si no hay participaciones digitales vendidas.
Texto por defecto (editable por la entidad):
"[Entidad] será la encargada de pagar las participaciones. Póngase en contacto con ellos si tiene
alguna duda o problema.
- Dirección
- Localidad
- Provincia
- Horario
- Teléfono
- Email
- Observaciones"
3.2 Campos de contacto configurables por la entidad
La entidad puede personalizar la información de contacto que se muestra al usuario. Los
siguientes campos generan enlaces automáticos:
Campo Descripcion Enlace automatico
Dirección Calle y numero del local Google Maps (script automatico)
Localidad Ciudad o municipio Google Maps (combinado)
Provincia Provincia del local Google Maps (combinado)
Horario Dias y horas de atencion —
Telefono Numero de contacto Enlace tel:
Email Correo de contacto Enlace mailto:
Observaciones Texto libre adicional —
3.3 Caso B: La entidad tiene ademas participaciones digitales vendidas
BLOQUEO CRITICO:
Si la entidad tiene participaciones digitales vendidas, NINGUN pago se habilita (ni físico ni digital)
hasta que la entidad ingrese el importe de las participaciones digitales y firme el contrato
correspondiente. PARTILOT pagara esas participaciones digitales en nombre de la entidad.
Flujo de desbloqueo en este caso:
1
Envío de mail con enlace a firma de contrato al mail de la entidad almacenado (poder
seleccionar de forma individual o todas
2
La entidad ingresa el importe de las participaciones digitales premiadas en la cuenta de
PARTILOT.
3 La entidad firma el contrato específico para ese sorteo.
4
PARTILOT valida el ingreso y activa el cobro de las participaciones digitales (pago
PARTILOT).
PARTILOT — Especificacion funcional: Activacion y pago de participaciones
Pagina 3 de 4
5
Simultáneamente, se habilita el panel presencial para que la entidad pague las
participaciones físicas.
4. Modalidad online — Flujo detallado
4.1 Estado inicial tras el escrutinio: BLOQUEADO
Mensaje al usuario (bloqueado) — editable SOLO por PARTILOT:
"Enhorabuena!! Tu participación tiene un premio de [X]€. Estamos en contacto con la entidad
para habilitar el cobro lo antes posible."
4.2 Panel del superadministrador — activación de pagos
El superadministrador dispone de una sección especifica donde se listan todas las
entidades con premio. Desde ahí se activa el pago una vez confirmado el ingreso de fondos.
1 Listado completo de entidades con premio y estado de ingreso (Pendiente / Confirmado).
2 Campo de texto con mensaje predeterminado, editable antes de activar el pago.
3 Botón de activación: desbloquea los pagos online para esa entidad concreta.
4 El mensaje al usuario cambia automáticamente al texto de cobro disponible (ver abajo).
Mensaje al usuario (desbloqueado):
"Enhorabuena!! Tu participacion tiene un premio de [X]€."
4.3 Opciones de cobro disponibles tras el desbloqueo
Una vez desbloqueado, el usuario puede gestionar su premio desde la cartera:
• Transferencia bancaria (IBAN): agrupable con participaciones de cualquier entidad
habilitada.
• Donacion a la entidad: solo con participaciones de la misma entidad.
• Generacion de codigo de recarga para la administracion: solo con participaciones de
la misma entidad.
• Cobro hibrido (donacion + codigo): el usuario distribuye el importe mediante campos
o barra deslizante.
――― Reglas transversales del sistema ―――
5. Restricciones y reglas permanentes
Regla Descripcion
Eleccion de modalidad
irrevocable por la entidad
Una vez confirmada la devolucion, la entidad no puede
cambiar la modalidad. Solo el superadministrador puede
modificarla.
PARTILOT — Especificacion funcional: Activacion y pago de participaciones
Pagina 4 de 4
Mensaje bloqueado solo editable
por PARTILOT
El texto que ve el usuario cuando el pago esta bloqueado
(online) no puede ser modificado por la entidad. Depende
del estado de fondos. (no lo ve la entidad)
Texto de contacto presencial
editable por la entidad
El texto con la información de contacto y horario es
configurable libremente por la entidad y aparece siempre
que un usuario lee una participación física.
Superadministrador tiene
override total
Puede cambiar modalidad de pago, activar o bloquear
pagos online, y editar mensajes en cualquier momento.
100% de fondos antes de pago
online
Sin la validación del ingreso completo por parte de
PARTILOT, ningún pago online se procesa para esa
entidad.
Documento generado por PARTILOT — Especificacion funcional interna v1.0