<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import {
  ChevronLeft, ChevronRight, Check, AlertCircle, CalendarX2, Users, Loader2, CalendarPlus,
} from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import EncabezadoPortal from '@/Components/Portal/EncabezadoPortal.vue'
import TarjetaPortal from '@/Components/Portal/TarjetaPortal.vue'

/**
 * Reservar (Fase 2.5). El corazón del portal.
 *
 * Tres decisiones que ordenan la pantalla:
 *
 * 1. **La disponibilidad se enseña, no se descubre.** El calendario ya sabe qué
 *    días tienen hueco y la franja qué bloques están ocupados. Un formulario que
 *    acepta cualquier hora y luego dice «ocupado» hace trabajar a la persona
 *    para averiguar algo que el servidor ya sabía.
 * 2. **La validación va en vivo, con el número exacto que falta.** «No te
 *    alcanza» no sirve; «te faltan 0.5 h» sí.
 * 3. **El resumen dice cuánto quedará después.** Nadie confirma a ciegas.
 *
 * La franja se elige tocando: primer toque el inicio, segundo el fin. En
 * escritorio además se puede arrastrar. Escribir horas a mano no es una opción
 * en un iPhone, que es donde más se va a usar esto.
 */
const props = defineProps<{
  espacios: any[]
  suscripcion: any
  medidores: any[]
  operacion: Record<string, any>
  horizonte: { desde: string; hasta: string } | null
}>()

const GRANULARIDAD = props.operacion.granularidad_minutos ?? 30
const DURACION_MINIMA = props.operacion.duracion_minima_horas ?? 1

// ── Paso 1 · espacio ────────────────────────────────────────────────────────

const espacioId = ref<number | null>(null)
const espacio = computed(() => props.espacios.find((e) => e.id === espacioId.value) ?? null)

/** Los espacios se agrupan por tipo: se elige «una sala de juntas», no la número 3. */
const porTipo = computed(() => {
  const grupos = new Map<string, { etiqueta: string; bolsa: string; espacios: any[] }>()
  for (const e of props.espacios) {
    if (!grupos.has(e.tipo)) {
      grupos.set(e.tipo, { etiqueta: e.tipo_label, bolsa: e.bolsa, espacios: [] })
    }
    grupos.get(e.tipo)!.espacios.push(e)
  }
  return [...grupos.values()]
})

const medidorDe = (bolsa: string) => props.medidores.find((m) => m.bolsa === bolsa) ?? null

const numero = (v: number | null | undefined) => {
  if (v === null || v === undefined) return '—'
  return Number.isInteger(v) ? String(v) : Number(v).toFixed(1).replace(/\.0$/, '')
}

// ── Paso 2 · día ────────────────────────────────────────────────────────────

const mesVisible = ref(new Date())
const diasDelMes = ref<any[]>([])
const cargandoMes = ref(false)
const fecha = ref<string | null>(null)

const claveDia = (d: Date) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

async function cargarMes() {
  if (!espacioId.value) return
  cargandoMes.value = true
  try {
    const url = new URL(route('portal.disponibilidad'), window.location.origin)
    url.searchParams.set('espacio_id', String(espacioId.value))
    url.searchParams.set('fecha', claveDia(mesVisible.value))
    url.searchParams.set('mes', '1')

    const respuesta = await fetch(url, { headers: { Accept: 'application/json' } })
    diasDelMes.value = respuesta.ok ? (await respuesta.json()).dias : []
  } finally {
    cargandoMes.value = false
  }
}

/** Rejilla del mes con los huecos delante para que el 1 caiga en su día. */
const rejilla = computed(() => {
  const primero = new Date(mesVisible.value.getFullYear(), mesVisible.value.getMonth(), 1)
  const huecos = (primero.getDay() + 6) % 7 // lunes primero
  const info = new Map(diasDelMes.value.map((d) => [d.fecha, d]))

  const celdas: any[] = Array.from({ length: huecos }, () => null)
  const ultimo = new Date(mesVisible.value.getFullYear(), mesVisible.value.getMonth() + 1, 0).getDate()

  for (let dia = 1; dia <= ultimo; dia++) {
    const clave = claveDia(new Date(mesVisible.value.getFullYear(), mesVisible.value.getMonth(), dia))
    const datos = info.get(clave)
    celdas.push({
      dia,
      fecha: clave,
      fuera: !datos,
      abierto: datos?.abierto ?? false,
      libres: datos?.libres ?? 0,
      motivo: datos?.motivo_cierre ?? null,
    })
  }
  return celdas
})

const mesLegible = computed(() =>
  mesVisible.value.toLocaleDateString('es-MX', { month: 'long', year: 'numeric' }),
)

const puedeMesAnterior = computed(() => {
  if (!props.horizonte) return false
  const inicio = new Date(`${props.horizonte.desde}T12:00:00`)
  return mesVisible.value > new Date(inicio.getFullYear(), inicio.getMonth(), 1)
})

const puedeMesSiguiente = computed(() => {
  if (!props.horizonte) return false
  const tope = new Date(`${props.horizonte.hasta}T12:00:00`)
  return mesVisible.value < new Date(tope.getFullYear(), tope.getMonth(), 1)
})

function moverMes(delta: number) {
  mesVisible.value = new Date(mesVisible.value.getFullYear(), mesVisible.value.getMonth() + delta, 1)
  cargarMes()
}

// ── Paso 3 · horario ────────────────────────────────────────────────────────

const dia = ref<any>(null)
const cargandoDia = ref(false)
const desde = ref<number | null>(null)
const hasta = ref<number | null>(null)
const arrastrando = ref(false)

async function cargarDia() {
  if (!espacioId.value || !fecha.value) return
  cargandoDia.value = true
  desde.value = null
  hasta.value = null
  try {
    const url = new URL(route('portal.disponibilidad'), window.location.origin)
    url.searchParams.set('espacio_id', String(espacioId.value))
    url.searchParams.set('fecha', fecha.value)

    const respuesta = await fetch(url, { headers: { Accept: 'application/json' } })
    dia.value = respuesta.ok ? await respuesta.json() : null
  } finally {
    cargandoDia.value = false
  }
}

watch(espacioId, () => {
  fecha.value = null
  dia.value = null
  cargarMes()
})

watch(fecha, (valor) => { if (valor) cargarDia() })

/** Rango seleccionado, siempre ordenado aunque se haya elegido de fin a inicio. */
const rango = computed(() => {
  if (desde.value === null) return null
  const fin = hasta.value ?? desde.value
  return { inicio: Math.min(desde.value, fin), fin: Math.max(desde.value, fin) }
})

const enRango = (indice: number) =>
  !!rango.value && indice >= rango.value.inicio && indice <= rango.value.fin

/**
 * Un rango solo vale si **todos** sus bloques están libres: seleccionar de 9 a
 * 12 saltándose un hueco ocupado a las 10 no es una reserva posible.
 */
const rangoContinuo = computed(() => {
  if (!rango.value || !dia.value) return false
  return dia.value.bloques
    .filter((b: any) => b.indice >= rango.value!.inicio && b.indice <= rango.value!.fin)
    .every((b: any) => b.libre)
})

const horaInicio = computed(() => {
  if (!rango.value || !dia.value) return null
  return dia.value.bloques.find((b: any) => b.indice === rango.value!.inicio)?.hora ?? null
})

const horaFin = computed(() => {
  if (!rango.value) return null
  const minutos = (rango.value.fin + 1) * GRANULARIDAD
  return `${String(Math.floor(minutos / 60)).padStart(2, '0')}:${String(minutos % 60).padStart(2, '0')}`
})

const horas = computed(() =>
  rango.value ? ((rango.value.fin - rango.value.inicio + 1) * GRANULARIDAD) / 60 : 0,
)

function tocarBloque(bloque: any) {
  if (!bloque.libre) return
  if (desde.value === null || hasta.value !== null) {
    desde.value = bloque.indice
    hasta.value = null
  } else {
    hasta.value = bloque.indice
  }
}

function empezarArrastre(bloque: any) {
  if (!bloque.libre) return
  arrastrando.value = true
  desde.value = bloque.indice
  hasta.value = bloque.indice
}

function seguirArrastre(bloque: any) {
  if (arrastrando.value && bloque.libre) hasta.value = bloque.indice
}

// ── Validación en vivo ──────────────────────────────────────────────────────

/**
 * Los avisos se calculan aquí y no al enviar. El servidor vuelve a validarlo
 * todo —esto es comodidad, no seguridad— pero la persona se entera antes de
 * pulsar nada, y con el número exacto que le falta.
 */
const problemas = computed<string[]>(() => {
  const lista: string[] = []
  if (!rango.value || !dia.value) return lista

  if (!rangoContinuo.value) {
    lista.push('Hay un bloque ocupado dentro del horario que elegiste. Elige un tramo seguido.')
  }

  if (horas.value < DURACION_MINIMA) {
    lista.push(`La reserva mínima es de ${numero(DURACION_MINIMA)} hora.`)
  }

  const saldo = dia.value.saldo_ciclo
  if (saldo !== null && horas.value > saldo) {
    const faltan = (horas.value - saldo).toFixed(1).replace(/\.0$/, '')
    lista.push(`Te faltan ${faltan} h: en este ciclo te quedan ${numero(saldo)} h de esta bolsa.`)
  }

  const tope = dia.value.tope_diario
  if (tope !== null && dia.value.usado_ese_dia + horas.value > tope) {
    const libres = Math.max(0, tope - dia.value.usado_ese_dia)
    lista.push(
      libres > 0
        ? `Tu plan permite ${numero(tope)} h al día y ese día ya tienes ${numero(dia.value.usado_ese_dia)} h. Te queda ${numero(libres)} h.`
        : `Tu plan permite ${numero(tope)} h al día y ese día ya las usaste.`,
    )
  }

  return lista
})

const puedeConfirmar = computed(() =>
  !!rango.value && problemas.value.length === 0 && !form.processing,
)

/** Cuánto quedará después: el dato que nadie debería tener que calcular. */
const quedaraDespues = computed(() => {
  if (!dia.value || dia.value.saldo_ciclo === null) return null
  return Math.max(0, dia.value.saldo_ciclo - horas.value)
})

// ── Envío ───────────────────────────────────────────────────────────────────

const form = useForm({ espacio_id: null as number | null, fecha: '', hora_inicio: '', hora_fin: '' })
const confirmando = ref(false)

function confirmar() {
  form.espacio_id = espacioId.value
  form.fecha = fecha.value!
  form.hora_inicio = horaInicio.value!
  form.hora_fin = horaFin.value!
  form.post(route('portal.reservar.store'), {
    onFinish: () => { confirmando.value = false },
  })
}

const fechaLarga = (iso: string) =>
  new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', {
    weekday: 'long', day: 'numeric', month: 'long',
  })

const DIAS = ['L', 'M', 'X', 'J', 'V', 'S', 'D']
</script>

<template>
  <Head title="Reservar" />

  <PortalLayout>
    <EncabezadoPortal
      titulo="Reservar un espacio"
      etiqueta="Reservar"
      numero="01"
      descripcion="Elige el espacio, el día y la hora. Te decimos cuánto consume antes de confirmar."
    />

    <!-- Sin membresía o sin espacios: no hay nada que hacer aquí. -->
    <TarjetaPortal v-if="!suscripcion" fondo="crema">
      <p class="font-body text-cuerpo text-dark/70">
        Necesitas una membresía activa para reservar.
      </p>
      <Link
        :href="route('membresias')"
        class="mt-4 inline-flex min-h-[48px] items-center border-2 border-dark bg-nodo-400 px-5
               font-display text-sm font-bold text-dark hover:-translate-y-0.5 hover:shadow-dura-sm
               transition-all duration-200 ease-salida"
      >Ver los planes</Link>
    </TarjetaPortal>

    <TarjetaPortal v-else-if="!espacios.length" fondo="crema">
      <p class="font-body text-cuerpo text-dark/70">
        Tu plan no incluye espacios reservables. El área de coworking es de acceso libre:
        entra cuando quieras y registra tu entrada.
      </p>
      <Link
        :href="route('portal.suscripcion')"
        class="mt-4 inline-flex min-h-[48px] items-center border-2 border-dark bg-nodo-400 px-5
               font-display text-sm font-bold text-dark hover:-translate-y-0.5 hover:shadow-dura-sm
               transition-all duration-200 ease-salida"
      >Ver planes con salas</Link>
    </TarjetaPortal>

    <div v-else class="space-y-6">
      <!-- ── Paso 1 · el espacio ──────────────────────────────────────── -->
      <section>
        <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
          <span class="font-mono">01</span> ¿Qué espacio?
          <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
        </h2>

        <div class="grid gap-4 sm:grid-cols-2">
          <div v-for="grupo in porTipo" :key="grupo.etiqueta">
            <p class="mb-2 font-mono text-[0.6875rem] uppercase tracking-[0.14em] text-dark/70">
              {{ grupo.etiqueta }}
              <template v-if="medidorDe(grupo.bolsa)">
                ·
                <span :class="medidorDe(grupo.bolsa)!.agotada ? 'text-coral' : ''">
                  {{ medidorDe(grupo.bolsa)!.ilimitada
                    ? 'sin límite'
                    : `te quedan ${numero(medidorDe(grupo.bolsa)!.restante)} h` }}
                </span>
              </template>
            </p>

            <ul class="space-y-2">
              <li v-for="e in grupo.espacios" :key="e.id">
                <button
                  type="button"
                  class="flex w-full min-h-[56px] items-center justify-between gap-3 border-2 px-4 py-3
                         text-left transition-all duration-200 ease-salida focus-visible:outline
                         focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
                  :class="espacioId === e.id
                    ? 'border-dark bg-nodo-400 shadow-dura-sm'
                    : 'border-dark/25 bg-white hover:border-dark hover:-translate-y-0.5 hover:shadow-dura-sm'"
                  :aria-pressed="espacioId === e.id"
                  @click="espacioId = e.id"
                >
                  <span class="min-w-0">
                    <span class="block font-display text-sm font-bold text-dark">{{ e.nombre }}</span>
                    <span class="mt-0.5 flex items-center gap-1.5 font-body text-xs text-dark/70">
                      <Users :size="13" aria-hidden="true" /> Hasta {{ e.capacidad }}
                    </span>
                  </span>
                  <Check v-if="espacioId === e.id" :size="18" class="shrink-0 text-dark" aria-hidden="true" />
                </button>
              </li>
            </ul>
          </div>
        </div>
      </section>

      <!-- ── Paso 2 · el día ──────────────────────────────────────────── -->
      <section v-if="espacio">
        <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
          <span class="font-mono">02</span> ¿Qué día?
          <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
        </h2>

        <TarjetaPortal padding="sm">
          <div class="mb-4 flex items-center justify-between">
            <button
              type="button" :disabled="!puedeMesAnterior"
              class="flex h-11 w-11 items-center justify-center border-2 border-dark/25 text-dark
                     transition-colors hover:border-dark disabled:opacity-30 disabled:hover:border-dark/25
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                     focus-visible:outline-dark"
              aria-label="Mes anterior" @click="moverMes(-1)"
            ><ChevronLeft :size="18" /></button>

            <p class="font-display text-base font-extrabold first-letter:uppercase text-dark">{{ mesLegible }}</p>

            <button
              type="button" :disabled="!puedeMesSiguiente"
              class="flex h-11 w-11 items-center justify-center border-2 border-dark/25 text-dark
                     transition-colors hover:border-dark disabled:opacity-30 disabled:hover:border-dark/25
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                     focus-visible:outline-dark"
              aria-label="Mes siguiente" @click="moverMes(1)"
            ><ChevronRight :size="18" /></button>
          </div>

          <div class="grid grid-cols-7 gap-1" role="grid">
            <span
              v-for="d in DIAS" :key="d"
              class="py-1 text-center font-mono text-[0.625rem] uppercase tracking-[0.1em] text-dark/50"
            >{{ d }}</span>

            <template v-for="(celda, i) in rejilla" :key="i">
              <span v-if="!celda" aria-hidden="true" />

              <button
                v-else
                type="button"
                :disabled="!celda.abierto || celda.libres === 0"
                class="relative flex min-h-[44px] flex-col items-center justify-center gap-0.5 border-2
                       font-display text-sm font-bold transition-all duration-150 ease-salida
                       disabled:cursor-not-allowed focus-visible:outline focus-visible:outline-2
                       focus-visible:outline-offset-2 focus-visible:outline-dark"
                :class="fecha === celda.fecha
                  ? 'border-dark bg-dark text-white'
                  : celda.abierto && celda.libres > 0
                    ? 'border-dark/20 bg-white text-dark hover:border-dark'
                    : 'border-transparent bg-cream-200/50 text-dark/30'"
                :title="celda.motivo ?? (celda.libres === 0 ? 'Sin huecos' : `${celda.libres} bloques libres`)"
                :aria-label="`${celda.dia}: ${celda.motivo ?? (celda.libres === 0 ? 'sin huecos' : celda.libres + ' bloques libres')}`"
                @click="fecha = celda.fecha"
              >
                {{ celda.dia }}
                <!-- El punto informa de un vistazo; el `title` y el aria dan el detalle. -->
                <span
                  v-if="celda.abierto && celda.libres > 0"
                  class="h-1 w-1 rounded-full"
                  :class="fecha === celda.fecha ? 'bg-nodo-400' : 'bg-nodo-500'"
                  aria-hidden="true"
                />
              </button>
            </template>
          </div>

          <p v-if="cargandoMes" class="mt-3 flex items-center gap-2 font-body text-xs text-dark/70">
            <Loader2 :size="14" class="animate-spin motion-reduce:animate-none" aria-hidden="true" />
            Buscando huecos…
          </p>
        </TarjetaPortal>
      </section>

      <!-- ── Paso 3 · la hora ─────────────────────────────────────────── -->
      <section v-if="espacio && fecha">
        <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
          <span class="font-mono">03</span> ¿A qué hora?
          <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
        </h2>

        <TarjetaPortal padding="sm">
          <p class="mb-3 font-body text-sm first-letter:uppercase text-dark/70">{{ fechaLarga(fecha) }}</p>

          <div v-if="cargandoDia" class="flex items-center gap-2 py-8 font-body text-sm text-dark/70">
            <Loader2 :size="16" class="animate-spin motion-reduce:animate-none" aria-hidden="true" />
            Cargando horarios…
          </div>

          <div v-else-if="dia && !dia.abierto" class="flex items-center gap-3 py-8 text-dark/70">
            <CalendarX2 :size="20" aria-hidden="true" />
            <p class="font-body text-sm">{{ dia.motivo_cierre }}</p>
          </div>

          <template v-else-if="dia">
            <p class="mb-3 font-body text-xs text-dark/70">
              Toca la hora de inicio y luego la de fin. Bloques de {{ GRANULARIDAD }} minutos.
            </p>

            <div
              class="grid grid-cols-4 gap-1.5 sm:grid-cols-6 lg:grid-cols-8"
              @pointerup="arrastrando = false"
              @pointerleave="arrastrando = false"
            >
              <button
                v-for="bloque in dia.bloques"
                :key="bloque.indice"
                type="button"
                :disabled="!bloque.libre"
                class="min-h-[44px] border-2 font-mono text-xs transition-all duration-150 ease-salida
                       disabled:cursor-not-allowed focus-visible:outline focus-visible:outline-2
                       focus-visible:outline-offset-2 focus-visible:outline-dark"
                :class="enRango(bloque.indice)
                  ? 'border-dark bg-nodo-400 text-dark'
                  : bloque.libre
                    ? 'border-dark/20 bg-white text-dark hover:border-dark'
                    : 'border-transparent bg-cream-200 text-dark/35 line-through'"
                :title="bloque.motivo === 'ocupado' ? 'Ocupado'
                  : bloque.motivo === 'pasado' ? 'Ya pasó'
                  : bloque.motivo === 'demasiado_pronto' ? 'Muy justo: pásate por recepción'
                  : undefined"
                :aria-pressed="enRango(bloque.indice)"
                @click="tocarBloque(bloque)"
                @pointerdown="empezarArrastre(bloque)"
                @pointerenter="seguirArrastre(bloque)"
              >{{ bloque.hora }}</button>
            </div>

            <p class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 font-body text-xs text-dark/60">
              <span class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 border-2 border-dark bg-nodo-400" aria-hidden="true" /> Tu selección
              </span>
              <span class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 bg-cream-200" aria-hidden="true" /> Ocupado o pasado
              </span>
            </p>
          </template>
        </TarjetaPortal>
      </section>

      <!-- ── Resumen y confirmación ───────────────────────────────────── -->
      <section v-if="rango && dia">
        <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
          <span class="font-mono">04</span> Confirma
          <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
        </h2>

        <TarjetaPortal fondo="oscuro">
          <dl class="grid gap-4 sm:grid-cols-2">
            <div>
              <dt class="etiqueta-tecnica text-nodo-400">Espacio</dt>
              <dd class="mt-1 font-display text-lg font-bold text-white">{{ espacio.nombre }}</dd>
            </div>
            <div>
              <dt class="etiqueta-tecnica text-nodo-400">Día</dt>
              <dd class="mt-1 font-body first-letter:uppercase text-cream">{{ fechaLarga(fecha!) }}</dd>
            </div>
            <div>
              <dt class="etiqueta-tecnica text-nodo-400">Horario</dt>
              <dd class="mt-1 font-mono text-lg text-white">{{ horaInicio }} – {{ horaFin }}</dd>
            </div>
            <div>
              <dt class="etiqueta-tecnica text-nodo-400">Consume</dt>
              <dd class="mt-1 font-body text-cream">
                {{ numero(horas) }} h de {{ espacio.bolsa_etiqueta.toLowerCase() }}
                <span v-if="quedaraDespues !== null" class="mt-1 block text-sm text-cream/70">
                  Te quedarán <strong class="text-nodo-400">{{ numero(quedaraDespues) }} h</strong> este ciclo
                </span>
              </dd>
            </div>
          </dl>

          <!-- Los problemas, con el número exacto que falta. -->
          <ul v-if="problemas.length" class="mt-5 space-y-2">
            <li
              v-for="(problema, i) in problemas" :key="i"
              class="flex items-start gap-2.5 border-2 border-coral bg-coral/15 p-3"
            >
              <AlertCircle :size="17" class="mt-0.5 shrink-0 text-coral" aria-hidden="true" />
              <span class="font-body text-sm text-cream">{{ problema }}</span>
            </li>
          </ul>

          <!-- Los errores del servidor, por si algo cambió entre medias. -->
          <ul v-if="Object.keys(form.errors).length" class="mt-5 space-y-2">
            <li
              v-for="(mensaje, campo) in form.errors" :key="campo"
              class="flex items-start gap-2.5 border-2 border-coral bg-coral/15 p-3"
            >
              <AlertCircle :size="17" class="mt-0.5 shrink-0 text-coral" aria-hidden="true" />
              <span class="font-body text-sm text-cream">{{ mensaje }}</span>
            </li>
          </ul>

          <p class="mt-5 font-body text-xs text-cream/70">
            Puedes cancelar sin perder las horas hasta
            {{ operacion.horas_para_cancelar_sin_penalizacion }} horas antes.
            Después, y si no te presentas, las horas se consumen igual.
          </p>

          <button
            type="button"
            :disabled="!puedeConfirmar"
            class="mt-5 inline-flex min-h-[56px] w-full items-center justify-center gap-2 border-2
                   border-nodo-400 bg-nodo-400 px-6 font-display text-base font-bold text-dark
                   transition-all duration-200 ease-salida hover:-translate-y-0.5
                   disabled:cursor-not-allowed disabled:border-white/25 disabled:bg-transparent
                   disabled:text-white/40 disabled:hover:translate-y-0 sm:w-auto
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                   focus-visible:outline-nodo-400"
            @click="confirmar"
          >
            <Loader2 v-if="form.processing" :size="18" class="animate-spin motion-reduce:animate-none" aria-hidden="true" />
            <CalendarPlus v-else :size="18" aria-hidden="true" />
            Confirmar reserva
          </button>
        </TarjetaPortal>
      </section>
    </div>
  </PortalLayout>
</template>
