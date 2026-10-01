# Textos legales: lo que falta decidir

Estado al 1-oct-2026: `resources/legal/aviso-de-privacidad.md` y `resources/legal/terminos.md`
están en la versión **1.1-provisional**. Ya son borradores completos, escritos a partir de lo que
el sistema hace de verdad (inventario del código del 1-oct-2026), pero **nadie de jurídico los
ha revisado**. Siguen marcados `provisional: true` y el sitio enseña el cartel de borrador.

Cada punto abierto aparece en el texto publicado como un recuadro **«Pendiente de jurídico»**,
para que nadie lo tome por definitivo. Este documento los reúne y añade lo que el texto no puede
resolver por sí solo.

**Para cerrar un documento:** resolver sus pendientes, quitar los recuadros, poner
`provisional: false`, **subir la versión** (por ejemplo `2.0`) y desplegar. Al subir la versión,
el sistema pide a todos que acepten el texto nuevo; eso es intencional (ver
`app/Support/DocumentosLegales.php`).

> Pasar de `1.0-provisional` a `1.1-provisional` ya hace que el sistema pida aceptar de nuevo a
> quien había aceptado la 1.0. Es correcto: el contenido cambió por completo.

---

## A. Decisiones de jurídico

No las inventé: alguien con autoridad tiene que tomarlas.

### A1. Marco legal y autoridad

- **¿Qué ley aplica?** El responsable es el IYEM, un organismo público del Gobierno de Yucatán.
  Si actúa como sujeto obligado, el tratamiento se rige por la Ley General de Protección de Datos
  Personales en Posesión de Sujetos Obligados y por la ley estatal, **no** por la ley federal
  para particulares. El código y el borrador anterior mencionaban la ley de particulares
  (LFPDPPP). De esto dependen la estructura del aviso (integral y simplificado), los plazos y la
  autoridad.
- **Autoridad garante.** El encargo pedía «el responsable formal ante el INAI», pero **el INAI
  dejó de existir en 2025** con la reforma de simplificación orgánica, y sus funciones pasaron a
  otras autoridades. Hay que confirmar ante quién responde hoy el IYEM (federal o estatal) y
  cuál es la autoridad para que una persona presente una queja.
- **Responsable formal:** el nombre del área o de la persona responsable de datos personales
  del IYEM (unidad de transparencia o equivalente), con su correo y teléfono.

### A2. Dirección para derechos ARCO

- El aviso pone hoy `[pendiente: dirección de contacto para derechos ARCO]`.
- La versión anterior decía `contacto@nodico.com.mx`, que es también el buzón del formulario
  comercial. Conviene un buzón propio (por ejemplo `privacidad@nodico.com.mx`) que atienda el
  área responsable, y quizá además un domicilio para solicitudes en papel.
- Confirmar los plazos de respuesta. El borrador dice 20 días hábiles para responder y 15 para
  hacerlo efectivo; dependen de la ley aplicable (A1).

### A3. Plazos de conservación

Hoy **el sistema no borra nada solo**: no hay plazos programados. Hay que fijar uno para cada
dato, y después hay que programarlo (ver B2):

| Dato | Dónde vive | Propuesta para discutir |
|---|---|---|
| Fotografías del rostro (registro y captura) | Servidor (`comandos_acceso`) y equipo de acceso | Borrar del servidor al confirmarse el registro; en el equipo, mientras la persona tenga acceso |
| Plantilla biométrica | Equipo de acceso en las instalaciones | Hasta que la persona pida el borrado, se dé de baja o pase un tiempo sin acceso |
| Pasos por la terminal (`eventos_acceso`) | Servidor | ¿1 año? |
| Bitácora de movimientos | Servidor | ¿Plazo de auditoría del IYEM? |
| Registros de seguridad (`eventos_auth`, sesiones) | Servidor | ¿6 a 12 meses? |
| Mensajes del formulario de contacto | Servidor y buzón de correo | ¿1 año sin respuesta comercial? |
| Datos de salones, day-pass del interior, personal y servicio social | Servidor | ¿Tras terminar la relación? |
| Pagos y datos fiscales de facturas | Servidor | Lo que marquen las leyes fiscales |

### A4. Transferencias y proveedores

- ¿El IYEM transfiere datos a otras dependencias? Por ejemplo, informes al Gobierno del Estado,
  padrones de programas de apoyo o estadísticas con nombre. El aviso dice hoy que **no**, salvo
  contabilidad del IYEM, que es el mismo responsable.
- ¿Hay contratos o cláusulas de encargo con Hostinger, la pasarela de BBVA/Openpay, Google y
  Expo?
- Las estadísticas del day-pass del interior: ¿se entregan solo agregadas?

### A5. Dato biométrico

- **Forma del consentimiento expreso y por escrito** para registrar el rostro: un formato en
  papel firmado en recepción, una casilla con su propio texto en el sistema, o los dos.
- Texto de ese consentimiento.
- Si el personal del IYEM y quienes prestan servicio social pueden negarse y qué alternativa
  tienen. El borrador dice que sí: credencial QR o registro en recepción.

### A6. Términos y condiciones

- **Edad mínima** para tener cuenta y, en su caso, autorización de padre o tutor. El sistema no
  pide fecha de nacimiento.
- **Reglamento interno del espacio.** Los términos remiten a él, pero no existe por escrito.
  Reglas de invitados, alimentos, mascotas, ruido y uso de la dirección como domicilio fiscal.
- **Responsabilidad** por objetos extraviados o daños.
- **Devoluciones.** Hoy no hay un proceso (ni en el sistema ni por escrito): días no usados,
  cobro duplicado, renovación no deseada.
- **Facturación de pagos con tarjeta.** Hoy solo se factura el pago por referencia. También:
  hasta cuándo puede pedirse una factura.
- **Salones:** política de cancelación y de devolución del anticipo.
- **Suspensión y baja:** qué faltas las justifican y si se devuelve algo.
- **Ley aplicable y tribunales** (por ejemplo, Mérida), y si procede mencionar a la PROFECO
  cuando quien presta el servicio es un organismo público.

---

## B. Brechas técnicas: lo que el sistema no hace aunque el aviso lo necesita

El aviso cuenta la verdad de cómo funciona hoy el sistema. Las brechas de esta lista no se
corrigen redactando mejor: hay que **programarlas**. Las marcadas ⛔ deberían resolverse antes
de producción.

1. ⛔ **No se recoge el consentimiento biométrico.** `PersonasAccesoController::enrolar()`
   registra el rostro sin comprobar ningún consentimiento. El aviso dice que solo se hace con
   consentimiento expreso; hasta que exista (A5), recepción tiene que recogerlo en papel.
2. ⛔ **Las fotos del rostro se quedan en el servidor, sin cifrar y sin plazo.** La foto de
   registro (`foto_base64`) y la de captura del equipo (`foto_capturada`) se guardan en
   `comandos_acceso.payload` como texto. Ningún proceso las borra. Habría que quitarlas del
   payload en cuanto el comando se resuelva.
3. ⛔ **Borrar la cuenta no borra el rostro del equipo de acceso.** El comando `borrar_rostro`
   existe en el agente, pero nadie lo encola: ni al eliminar la cuenta, ni al desvincular, ni al
   borrar la ficha de una persona del personal. El aviso lo dice tal cual y pide solicitarlo en
   recepción, pero debería hacerse solo.
4. **La copia de «Mis datos personales» está incompleta.** No incluye datos fiscales, contacto
   de emergencia, entradas y salidas, pasos por la terminal, horas, pagos ni asesorías. El aviso
   lo reconoce («pídalo por escrito»), pero el derecho de acceso pide la copia completa.
5. **Borrar la cuenta deja el archivo de la foto de perfil** en el disco (`avatares/`).
6. **No hay borrado programado** de nada (A3). Cuando jurídico fije los plazos, programarlos
   con `model:prune`.
7. **La página de registro facial del panel puede no tener cámara.** La cabecera
   `Permissions-Policy: camera=()` (`app/Http/Middleware/CabecerasDeSeguridad.php`) bloquea la
   webcam del navegador. Comprobarlo antes de enrolar con la cámara en producción.
8. **La app de Android declara permiso de micrófono** (`RECORD_AUDIO`) sin usarlo. Las tiendas
   y la gente preguntan por eso. Quitarlo.
9. **Directorio de Comunidad:** no queda constancia de que cada emprendedor autorizó que se
   publiquen sus datos y su foto.

### Resuelto en este mismo trabajo

- **Borrar la cuenta seguía cobrando la renovación en Openpay/BBVA.** `BorradorDeCuenta` solo
  cancelaba Stripe. Ahora cancela también las suscripciones de la pasarela del banco, y si la
  pasarela no responde no borra nada (`tests/Feature/Pagos/BorrarCuentaConSuscripcionTest.php`).
  Sin este arreglo, los términos («al eliminarla, primero se cancela cualquier renovación
  automática») habrían sido falsos.
