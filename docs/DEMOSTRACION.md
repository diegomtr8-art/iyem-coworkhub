# Datos de demostración

> Fase 4.F. Deja Nódico como si llevara meses operando, para verlo funcionando
> antes de conectar el Face ID. **No es producción**: el seeder se niega a correr
> si `APP_ENV=production` (arrasaría con datos reales).

## Cómo poblar (o restablecer desde cero)

El orden importa. `sembrar-libro-horas` va **entre** el seed base y el de
demostración: siembra el saldo inicial de las suscripciones base que no tienen
libro; corrido después chocaría con las suscripciones de demo, que ya nacen con
su libro.

```bash
php artisan migrate:fresh --seed              # base: planes, espacios, admin
php artisan nodico:sembrar-libro-horas --solo-si-falta
php artisan db:seed --class=DemoSeeder        # la demostración
```

Comprobación de que todo cuadra (debe decir «los contadores cuadran con el libro»):

```bash
php artisan nodico:reconstruir-saldos
```

El `DemoSeeder` es repetible sin `migrate:fresh`: borra y rehace los usuarios de
demo (los de sufijo `.demo@nodico.com.mx`) en cada pasada, y usa `updateOrCreate`
para los catálogos.

## Accesos

Todas las cuentas de demostración usan la contraseña **`demo1234`**.

### Equipo

| Correo | Rol | Qué se ve |
|---|---|---|
| `admin.demo@nodico.com.mx` | Administrador | Todo el panel operativo: reportes, planes, espacios, bitácora, además de la operación diaria. |
| `recepcion.demo@nodico.com.mx` | Recepción (staff) | La operación de mostrador: hoy, check-in, agenda, miembros, asesorías. **No** ve reportes ni planes. |

### Miembros

| Correo | Plan / estado | Qué se ve en su portal |
|---|---|---|
| `pro.demo@nodico.com.mx` | Nodo Pro, activa | **El miembro con historial completo.** Bolsa a media (sala 5/10, contenido 3/10), reservas pasadas y futuras, un no-show, una cancelación devuelta y otra tardía, check-ins, una asesoría realizada y otra pendiente, datos fiscales y sus cobros. |
| `match.demo@nodico.com.mx` | Nodo Match, activa | Membresía de dupla, con reservas repartidas por los espacios. |
| `flex.demo@nodico.com.mx` | Nódico Flex, activa | Plan por días, con check-ins de coworking. |
| `daypass.demo@nodico.com.mx` | Day-Pass | Un pase de día, con su check-in. |
| `pendiente.demo@nodico.com.mx` | Pendiente de activación | Cuenta creada sin membresía: puede completar perfil y contratar, no reservar. |
| `suspendida.demo@nodico.com.mx` | Suspendida | La pantalla de cuenta suspendida. |
| `porvencer.demo@nodico.com.mx` | Nodo Pro, vence en 3 días | El aviso de membresía por vencer. |

## Qué se puede recorrer

- **Panel operativo** (admin o recepción): agenda semanal con reservas repartidas
  por los espacios en la semana pasada y la próxima; bandeja de asesorías con una
  pendiente por confirmar; miembros en todos los estados; check-ins del día.
- **Portal del miembro** (Ana Pro): medidores de bolsa a media, «Mis reservas»
  con confirmadas, cancelada y no-show, «Accesos y pagos», «Datos fiscales»,
  «Asesoría IYEM» con su historial, y «Mi seguridad» con el cambio de correo.
- **Sitio público**: emprendedores del directorio (Salabtún destacado), eventos
  y avisos.

## Lo que todavía no siembra, y por qué

El orden de las fases (F antes que D, E, H) hace que parte de lo que pide el
prompt aún no tenga estructura. Se completará al llegar cada fase:

- **Day-pass gratuito del interior** (municipio, giro, de dónde se enteró):
  la estructura llega en la **Fase E**. Por ahora hay check-ins de day-pass, sin
  esos campos.
- **Catálogo de temas de asesoría**: llega en la **Fase D**. Aquí el tema de cada
  asesoría es texto libre, que es lo que el modelo admite hoy.
- **Catálogo de emprendimientos con rotación del destacado**: llega en la
  **Fase H**. Hoy se siembra el directorio con un destacado fijo.

## Horas de asesoría por plan

Definido por Diego el 2026-08-30 y ya en el seeder base (`NodicoWebSeeder`):

- **Nodo Pro:** 4 h de asesoría IYEM al mes, máximo 1 h por día.
- **Nodo Match:** 4 h al mes también, **compartidas entre los dos** integrantes
  de la dupla (la bolsa de Match es compartida por diseño), máximo 1 h por día.
- **Nódico Flex y Day-Pass:** sin asesoría.
