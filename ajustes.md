
Propósito de la reunión
Definir los flujos de trabajo de diseño e impresión y aclarar los ajustes de las entidades.

Puntos clave
Flujo de trabajo de impresión: Se añade un paso intermedio para evitar pagos por trabajos rechazados. El cliente envía el diseño → la imprenta lo acepta/rechaza → el cliente paga.
Ajustes de las entidades: Se mejora la incorporación con un modal obligatorio para los ajustes críticos (p. ej., quién paga) y se aclara la etiqueta de "entidad sin ánimo de lucro" a "puede emitir certificado de donación".
Lógica de donaciones: La opción de "donar" se vincula directamente con la capacidad de una entidad para emitir certificados de donación, simplificando la interfaz de usuario.
Permisos de PDF: Los permisos de descarga de PDF se vinculan directamente con el rol de diseño para evitar la impresión no autorizada.
Temas
Flujo de trabajo de impresión
Problema: El flujo actual de "Enviar a imprimir" obliga al cliente a pagar antes de que la imprenta revise el diseño, lo que genera problemas si el trabajo es rechazado.
Solución: Añadir un paso intermedio para evitar pagos por trabajos rechazados.
El cliente envía el diseño.
La imprenta revisa y acepta el trabajo (o lo rechaza, con un motivo).
El cliente recibe una solicitud de pago.
Tras el pago, la imprenta comienza a trabajar.
Justificación: Este flujo protege al cliente de pagos por trabajos rechazados y permite a la imprenta proporcionar feedback sobre el diseño antes de comprometerse.
Ajustes de las entidades y onboarding
Problema: Los ajustes críticos de las entidades (p. ej., quién paga) son fáciles de pasar por alto durante la incorporación, lo que provoca errores y retrabajos.
Solución: Mostrar un modal obligatorio con los ajustes de la entidad antes de crear el gestor.
Justificación: Obliga al usuario a revisar los ajustes, reduciendo los errores y eliminando la necesidad de una sección de ajustes dedicada y más compleja.
Problema: La etiqueta "entidad sin ánimo de lucro" es ambigua, ya que no todas las entidades sin ánimo de lucro pueden emitir certificados de donación.
Solución: Cambiar la etiqueta a "puede emitir certificado de donación" para mayor claridad.
Lógica de pago: La configuración de pago se verifica en tiempo real. Si un usuario cambia el pagador mientras hay una factura pendiente, la solicitud de pago se reasigna automáticamente al nuevo pagador.
Lógica de donaciones
Problema: La opción de "donar" puede resultar confusa para las entidades que no pueden emitir certificados de donación.
Solución: Vincular la opción de "donar" con la capacidad de una entidad para emitir certificados de donación.
Entidad puede emitir certificado: Se muestra la opción de "donar".
Entidad no puede emitir certificado: Se oculta la opción de "donar".
Justificación: Simplifica la interfaz de usuario. Una donación anónima se gestiona simplemente no cobrando el pago.
Permisos de PDF
Regla: Los permisos de descarga de PDF se vinculan directamente con el rol de diseño.
El cliente diseña: Puede descargar el PDF.
La administración diseña: Puede previsualizar, pero no descargar el PDF.
Justificación: Evita que los clientes impriman participaciones no autorizadas.
Bug reportado
Problema: Un usuario que inició sesión como superadministrador intentó recuperar la contraseña de un gestor en la misma sesión del navegador. El sistema lo redirigió a la sesión de superadministrador activa en lugar de a la página de recuperación de contraseña.
Conclusión: Se trata de un comportamiento esperado del navegador y no de un bug.
Próximos pasos
Jorge Solano:
Implementar el flujo de trabajo de impresión de 3 pasos (Enviar → Aceptar → Pagar).
Añadir el modal obligatorio de ajustes de la entidad durante la incorporación.
Actualizar la etiqueta de "entidad sin ánimo de lucro" a "entidad que puede emitir certificado de donación".
Vincular la opción de "donar" con la capacidad de la entidad para emitir certificados.

Cliente puede referirse a adminitración o entidad segun el caso.