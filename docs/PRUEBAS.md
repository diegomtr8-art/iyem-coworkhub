# Pruebas de Nódico

> Fase 4 del trabajo de portales. Este documento existe por una razón concreta:
> **las reglas de negocio son el producto**. Si no están probadas, no existen —
> y el BUG-01 estuvo meses invisible justamente porque nadie podía comprobar
> que dos horas se consumían como dos horas.

```bash
php artisan test          # 290 pruebas, 1.322 aserciones
npm run build             # assets, sin errores
```

---

## El checklist del prompt, una por una

| Lo que pide el prompt | Prueba | Archivo |
|---|---|---|
| Regresión de BUG-01: 2 h consumen 2, no −2 | `test_una_reserva_de_dos_horas_consume_exactamente_dos_horas` | `Portal/MotorDeReservasTest` |
| El tope de 2 h/día suma **varias** reservas del mismo día | `test_el_tope_de_dos_horas_por_dia_suma_varias_reservas_del_mismo_dia` | `Portal/MotorDeReservasTest` |
| No se reserva sin bolsa suficiente | `test_no_se_puede_reservar_sin_bolsa_suficiente` | `Portal/MotorDeReservasTest` |
| …ni fuera del horario de operación | `test_no_se_puede_reservar_antes_de_la_apertura`, `test_una_reserva_no_puede_cruzar_el_cierre`, `test_no_se_puede_reservar_en_fin_de_semana`, `test_no_se_reserva_un_dia_festivo` | `Portal/ReglasDeCalendarioTest` |
| …ni fuera de la vigencia de la membresía | `test_no_se_puede_reservar_fuera_de_la_vigencia_de_la_membresia` | `Portal/MotorDeReservasTest` |
| **Dos reservas simultáneas: solo una gana (concurrencia)** | `php artisan nodico:probar-concurrencia` — ver abajo | comando |
| Cancelar con más de 2 h devuelve | `test_cancelar_devuelve_las_horas` | `Portal/MotorDeReservasTest` |
| …con menos, no | `test_cancelar_con_menos_de_dos_horas_de_antelacion_no_devuelve` | `Portal/MotorDeReservasTest` |
| Cancelar dos veces no devuelve dos veces | `test_cancelar_dos_veces_no_devuelve_las_horas_dos_veces` | `Portal/MotorDeReservasTest` |
| El no-show marca y **no** devuelve | `test_el_no_show_marca_la_reserva_y_no_devuelve_las_horas` | `Portal/LibroDeHorasTest` |
| El reinicio de aniversario es idempotente | `test_abrir_el_ciclo_dos_veces_no_lo_duplica`, `test_el_comando_de_ciclos_es_idempotente` | `Portal/LibroDeHorasTest` |
| El saldo del libro coincide con el contador | `test_el_contador_en_cache_siempre_coincide_con_el_libro`, `test_una_escritura_directa_al_contador_se_detecta_y_se_repara` | `Portal/LibroDeHorasTest` |
| Un miembro no ve ni toca lo de otro | `test_un_miembro_no_ve_ni_toca_nada_de_otro_miembro` y 3 más | `Portal/PantallasDelPortalTest` |
| `staff` no accede a reportes ni a planes | `test_recepcion_no_entra_a_lo_que_es_de_administracion` (6 rutas) | `Panel/PanelOperativoTest` |
| El RFC se valida en formato y se guarda normalizado | `test_un_rfc_bien_formado_pasa` (4 casos), `test_un_rfc_mal_formado_se_rechaza` (7 casos), `test_el_rfc_se_guarda_normalizado` | `Portal/AsesoriasYDatosFiscalesTest` |

Y una que el prompt no pedía pero salió de `/security-review`: que las fórmulas
que un miembro escriba en su nombre o razón social no se ejecuten en el Excel de
administración. Ver «Inyección de fórmulas» abajo.

---

## La prueba de concurrencia

Va **aparte de la suite**, y no por comodidad: PHPUnit corre sobre SQLite en
memoria y en un solo proceso. Ahí se puede comprobar que la lógica *rechaza* un
traslape, pero **no que la carrera esté cerrada**, porque nunca hay dos
escrituras a la vez. Una prueba de concurrencia sin concurrencia no prueba nada.

```bash
php artisan nodico:probar-concurrencia --intentos=12
```

Lanza doce procesos reales contra MySQL, sincronizados a un milisegundo común
—sin esa barrera, el arranque de PHP los escalona y no compiten—, todos
peleando por el mismo hueco. Comprueba que queda **una** reserva y **dos**
bloques (10:00 y 10:30).

### Y que la prueba de verdad detecta el bug

Una prueba que no puede fallar no demuestra nada. El modo `--ingenuo` reproduce
el código anterior al BUG-03 —comprobación fuera de la transacción, sin ocupar
bloques— y debe colarse:

```bash
php artisan nodico:probar-concurrencia --intentos=10 --ingenuo
```

Resultado medido el 01/09/2026:

| Modo | Reservas sobre el mismo hueco |
|---|---|
| Código actual | **1** de 12 intentos (3 pasadas seguidas) |
| Código anterior (`--ingenuo`) | **8** de 10 intentos |

El comando limpia lo que crea y usa una fecha a tres años vista para no tocar
nada real.

---

## Vigilancia continua

Además de la suite, dos comandos que comprueban el estado de los datos:

```bash
php artisan nodico:reconstruir-saldos     # informa; sale con error si el cache diverge del libro
php artisan nodico:reiniciar-ciclos --simular
php artisan nodico:marcar-no-show --simular
```

`nodico:reconstruir-saldos` corre a diario por cron (ver `DEPLOY.md`) y **sale
con código distinto de cero** si algún contador se separó del libro. Es la red
que detectaría un BUG-01 nuevo al día siguiente, no a los seis meses.

La salida de las tres queda en `storage/logs/tareas.log` gracias a
`appendOutputTo` en `routes/console.php`. Sin eso no hay forma de comprobar que
el cron ejecuta algo: `schedule:run` descarta la salida de los comandos que
lanza, y el log del cron se queda vacío aunque todo funcione.

---

## Comprobaciones de interfaz

Hechas en Chrome contra el servidor real, no simuladas.

**Sin scroll horizontal** en 375 / 393 / 430 / 744 / 1024 / 1366 px:

| Zona | Combinaciones |
|---|---|
| Portal del miembro (8 pantallas) | 48 |
| Panel operativo (12 pantallas) | 72 |
| Sitio público y acceso (9 pantallas) | 54 |
| **Total** | **174, ninguna desborda** |

**Área táctil:** todo lo pulsable mide 44 px o más a 375 px, en portal y panel.

**Foco de teclado:** contorno `dark` de 2 px más halo `nodo-400` de 4 px.
Lleva **dos colores** a propósito: un anillo amarillo solo da 1.75:1 sobre
crema y es invisible, y el `outline: transparent` que inyecta
`@tailwindcss/forms` lo anulaba del todo en los campos. Ver el comentario en
`resources/css/app.css`.

**Consola:** limpia en las 20 rutas de panel y portal.

---

## Inyección de fórmulas en los CSV

Encontrada por `/security-review` el 01/09/2026 y cerrada en el mismo commit.
`App\Support\CeldaCsv` neutraliza toda celda que empiece por `=`, `+`, `-`,
`@`, tabulador o retorno de carro antes de escribirla.

`tests/Feature/Panel/ExportacionCsvTest.php` prueba las ocho cargas que una hoja
de cálculo ejecutaría —`HYPERLINK` que exfiltra, DDE hacia el shell,
`WEBSERVICE`— y además el camino completo: un miembro pone la fórmula en su
nombre o en su razón social, y se comprueba que llega neutralizada al archivo
que descarga administración.

## Cómo correr todo antes de un despliegue

```bash
php artisan test
npm run build
php artisan nodico:probar-concurrencia --intentos=12
php artisan nodico:reconstruir-saldos
```

Los cuatro tienen que pasar. El tercero necesita MySQL; los otros no.
