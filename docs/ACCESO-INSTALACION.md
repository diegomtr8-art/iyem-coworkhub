# Control de acceso — instalación y reconexión del torno

## El motor de reconexión NO es el arreglo

> Esta reconexión automática es un **parche**. La causa de que el torno se caiga es
> un enlace de red inestable (cuando estaba en wifi perdía ~12–30 % de los
> paquetes). La solución de fondo es **cable de red y reserva de DHCP** para el
> terminal y para el servidor. Mientras eso no se haga, el equipo se seguirá
> cayendo — solo que ahora **se levanta solo y queda registrado**.

El valor del motor no es esconder el problema, sino **medirlo**: el panel de
Accesos muestra «hoy: N caídas · N reconexiones · X min sin registrar». Ese número
es el argumento para exigir el cable («ayer se cayó 14 veces y estuvo 40 min sin
registrar» consigue un cable mucho más rápido que «a veces se desconecta»).

## Cómo funciona (Fases 1-4)

- **Detectar (Fase 1):** el agente lee `is_online` + antigüedad del último latido
  directo de la BD de Smart Pass (barato, no desgasta). Una sola lectura caída no
  cuenta (puede ser un paquete perdido): la caída se **confirma** tras
  `RECONEXION_CONFIRMAR_SEG` sin latido.
- **Reconectar con freno (Fase 2):** al confirmar la caída, reescribe la misma
  contraseña LAN del torno (`PUT /admin/devices/network/set_password`) para forzar
  el re-registro. Con **espera creciente** entre intentos
  (`RECONEXION_ESCALONES`), **tope por hora** (`RECONEXION_TOPE_HORA`; al llegar,
  se detiene y **avisa**) y verificación en el siguiente ciclo.
- **No estorbar (Fase 3):** si hubo un reconocimiento/apertura hace menos de
  `RECONEXION_SILENCIO_SEG`, pospone (no cortar una apertura). Fuera del horario de
  operación (`OPERACION_HORA_INICIO`–`OPERACION_HORA_FIN`, lun-vie) deja descansar
  al equipo. Si Smart Pass no responde, no intenta nada contra el torno.
- **Medir (Fase 4):** cada caída y reconexión se cuenta y se muestra en el panel;
  al tope por hora aparece una alerta visible.

## Interruptor

Todo vive en el `.env` del agente (ver `.env.example`). El motor está **apagado
por defecto** (`RECONEXION_AUTO=0`): se enciende con `RECONEXION_AUTO=1` cuando
está validado. La contraseña de comunicación LAN no se guarda en el repo: el
`reconectar` lee la actual de `/admin/devices/network/{id}` y la reescribe igual.

## Pendiente físico (el arreglo de verdad)

1. **Cable de red al TORNO** (no solo a la computadora del servidor).
2. **Reserva de DHCP / IP fija** para el terminal y para el servidor de Smart Pass.

Hasta hacer esto, el motor es la red de seguridad; con esto hecho, deja de hacer
falta.
