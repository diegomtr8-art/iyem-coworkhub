<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight, Plus, Ban, X, AlertTriangle } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'

/**
 * Agenda semanal (Fase 3.4).
 *
 * Una columna por espacio y la semana entera a la vista: responde «¿tienes algo
 * libre el jueves?» sin abrir cinco días uno por uno.
 *
 * El sobrecupo tiene su propio paso: si el miembro no tiene saldo, el servidor
 * lo dice y la pantalla **pide autorización explícita con motivo** en vez de
 * dejar pasar o rechazar sin más.
 */
const props = defineProps<{
  semana: any
  espacios: any[]
  dias: any[]
  operacion: Record<string, any>
}>()

const nuevaReserva = ref(false)
const bloqueo = ref(false)
const detalle = ref<any | null>(null)

const reserva = useForm({
  user_id: '', espacio_id: '', fecha: '', hora_inicio: '09:00', hora_fin: '10:00',
  autoriza_sobrecupo: false, motivo_sobrecupo: '',
})

const bloqueoForm = useForm({
  espacio_id: '', fecha: '', hora_inicio: '09:00', hora_fin: '10:00', motivo: '', notas: '',
})

const cancelacion = useForm({ devolver_horas: true, motivo: '' })

/** Sugerencias del buscador de miembro dentro del formulario. */
const busqueda = ref('')
const sugerencias = ref<any[]>([])
const elegido = ref<any | null>(null)
let temporizador: number | undefined

function buscarMiembro() {
  if (temporizador) window.clearTimeout(temporizador)

  if (busqueda.value.trim().length < 2) { sugerencias.value = []; return }

  temporizador = window.setTimeout(async () => {
    const url = new URL(route('buscar.miembro'), window.location.origin)
    url.searchParams.set('q', busqueda.value)
    const r = await fetch(url, { headers: { Accept: 'application/json' } })
    sugerencias.value = r.ok ? (await r.json()).resultados : []
  }, 250)
}

function elegirMiembro(m: any) {
  elegido.value = m
  reserva.user_id = m.id
  busqueda.value = m.nombre
  sugerencias.value = []
}

function abrirReserva(espacioId: number | null = null, fecha: string | null = null) {
  reserva.reset()
  reserva.clearErrors()
  elegido.value = null
  busqueda.value = ''
  sugerencias.value = []
  reserva.espacio_id = espacioId ? String(espacioId) : ''
  reserva.fecha = fecha ?? props.semana.desde
  nuevaReserva.value = true
}

function abrirBloqueo(espacioId: number | null = null, fecha: string | null = null) {
  bloqueoForm.reset()
  bloqueoForm.clearErrors()
  bloqueoForm.espacio_id = espacioId ? String(espacioId) : ''
  bloqueoForm.fecha = fecha ?? props.semana.desde
  bloqueo.value = true
}

const irA = (fecha: string) =>
  router.get(route('agenda.index'), { semana: fecha }, { preserveState: true })

/**
 * Vista de un día para móvil. La retícula semanal no cabe en un iPhone sin
 * volverse ilegible (960 px de ancho mínimo), así que por debajo de `lg` se
 * muestra un solo día, agrupado por espacio. Los datos salen de la misma semana
 * que ya trae el servidor —no se pide nada nuevo—: solo se elige el día.
 */
const diaSelIdx = ref(Math.max(0, props.dias.findIndex((d: any) => d.es_hoy)))
const diaSel = computed(() => props.dias[diaSelIdx.value] ?? props.dias[0])

const espaciosDelDia = computed(() =>
  props.espacios.map((e: any) => {
    const col = diaSel.value?.columnas?.find((c: any) => c.espacio_id === e.id) ?? null
    return { ...e, abierto: !!col?.abierto, eventos: col?.eventos ?? [] }
  }),
)
</script>

<template>
  <Head title="Agenda" />

  <AuthenticatedLayout>
    <template #breadcrumb>
      <span class="font-display font-bold text-dark">Agenda</span>
      <span>· {{ semana.etiqueta }}</span>
    </template>

    <Panel padding="none">
      <template #acciones>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="flex min-h-[32px] items-center gap-1.5 border border-dark bg-nodo-400 px-2.5
                   font-display text-xs font-bold text-dark hover:bg-nodo-500"
            @click="abrirReserva()"
          ><Plus :size="13" aria-hidden="true" /> Reservar</button>

          <button
            type="button"
            class="flex min-h-[32px] items-center gap-1.5 border border-dark/25 px-2.5
                   font-display text-xs font-bold text-dark hover:border-dark"
            @click="abrirBloqueo()"
          ><Ban :size="13" aria-hidden="true" /> Bloquear</button>
        </div>
      </template>

      <div class="flex items-center justify-between gap-3 border-b border-dark/15 bg-cream-50 px-4 py-2.5">
        <button
          type="button" aria-label="Semana anterior"
          class="flex h-8 w-8 items-center justify-center border border-dark/25 hover:border-dark"
          @click="irA(semana.anterior)"
        ><ChevronLeft :size="15" /></button>

        <p class="font-display text-sm font-bold first-letter:uppercase text-dark">{{ semana.etiqueta }}</p>

        <button
          type="button" aria-label="Semana siguiente"
          class="flex h-8 w-8 items-center justify-center border border-dark/25 hover:border-dark"
          @click="irA(semana.siguiente)"
        ><ChevronRight :size="15" /></button>
      </div>

      <!-- ≥ lg: la retícula semanal completa, que ahí sí funciona. Desborda dentro
           de su caja, nunca en el documento. -->
      <div class="hidden overflow-x-auto lg:block">
        <table class="w-full min-w-[60rem] border-collapse text-sm">
          <thead>
            <tr class="border-b border-dark/15 bg-cream-50">
              <th scope="col" class="w-32 px-3 py-2 text-left font-mono text-[0.625rem] uppercase tracking-[0.1em] font-normal text-dark/50">
                Espacio
              </th>
              <th
                v-for="dia in dias" :key="dia.fecha" scope="col"
                class="px-2 py-2 text-center font-normal"
                :class="dia.es_hoy ? 'bg-nodo-400' : ''"
              >
                <span class="block font-mono text-[0.625rem] uppercase tracking-[0.1em] text-dark/60">
                  {{ dia.nombre.slice(0, 3) }}
                </span>
                <span class="block font-display text-sm font-bold text-dark">{{ dia.numero }}</span>
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-dark/10">
            <tr v-for="espacio in espacios" :key="espacio.id">
              <th scope="row" class="px-3 py-2 text-left align-top">
                <span class="block font-display text-xs font-bold text-dark">{{ espacio.nombre }}</span>
                <span class="block text-[0.625rem] font-normal text-dark/50">{{ espacio.tipo }}</span>
              </th>

              <td
                v-for="dia in dias" :key="dia.fecha"
                class="border-l border-dark/10 p-1 align-top"
                :class="dia.es_hoy ? 'bg-nodo-400/10' : ''"
              >
                <template v-for="col in [dia.columnas.find((c: any) => c.espacio_id === espacio.id)]" :key="espacio.id">
                  <div v-if="!col?.abierto" class="px-1 py-2 text-center text-[0.625rem] text-dark/30">
                    —
                  </div>

                  <div v-else class="space-y-1">
                    <button
                      v-for="evento in col.eventos" :key="evento.tipo + evento.id"
                      type="button"
                      class="block w-full border-l-2 px-1.5 py-1 text-left transition-colors"
                      :class="evento.tipo === 'bloqueo'
                        ? 'border-l-red-600 bg-red-50 hover:bg-red-100'
                        : 'border-l-dark bg-nodo-400/70 hover:bg-nodo-400'"
                      @click="detalle = { ...evento, espacio: espacio.nombre, fecha: dia.fecha }"
                    >
                      <span class="block font-mono text-[0.625rem] text-dark">{{ evento.inicio }}–{{ evento.fin }}</span>
                      <span class="block truncate text-[0.6875rem] text-dark">{{ evento.titulo }}</span>
                    </button>

                    <button
                      type="button"
                      class="flex w-full items-center justify-center border border-dashed border-dark/25 py-1
                             text-dark/35 transition-colors hover:border-dark/60 hover:text-dark
                             focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                             focus-visible:outline-dark"
                      :aria-label="`Reservar ${espacio.nombre} el ${dia.fecha}`"
                      @click="abrirReserva(espacio.id, dia.fecha)"
                    ><Plus :size="12" /></button>
                  </div>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- < lg: vista de un día, agrupada por espacio. Misma semana, mismos datos,
           mismo modal de detalle: solo cambia la forma de mostrarla. -->
      <div class="lg:hidden">
        <!-- Tira para elegir el día dentro de la semana. -->
        <div class="flex gap-1.5 overflow-x-auto border-b border-dark/15 bg-cream-50 px-3 py-2.5">
          <button
            v-for="(d, i) in dias" :key="d.fecha" type="button"
            class="flex min-h-[44px] min-w-[44px] shrink-0 flex-col items-center justify-center border px-2 py-1
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
            :class="i === diaSelIdx ? 'border-dark bg-nodo-400 text-dark' : 'border-dark/20 text-dark/70 hover:border-dark'"
            :aria-pressed="i === diaSelIdx"
            @click="diaSelIdx = i"
          >
            <span class="font-mono text-[0.625rem] uppercase tracking-[0.08em]">{{ d.nombre.slice(0, 3) }}</span>
            <span class="font-display text-sm font-bold leading-none">{{ d.numero }}</span>
          </button>
        </div>

        <!-- Cada espacio del día, con sus reservas por hora. -->
        <ul class="divide-y divide-dark/10">
          <li v-for="espacio in espaciosDelDia" :key="espacio.id" class="px-4 py-3">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-display text-sm font-bold text-dark">{{ espacio.nombre }}</p>
                <p class="text-[0.6875rem] text-dark/50">{{ espacio.tipo }}</p>
              </div>
              <button
                v-if="espacio.abierto" type="button"
                class="flex min-h-[36px] shrink-0 items-center gap-1 border border-dark bg-nodo-400 px-2.5
                       font-display text-xs font-bold text-dark hover:bg-nodo-500
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
                :aria-label="`Reservar ${espacio.nombre} el ${diaSel.fecha}`"
                @click="abrirReserva(espacio.id, diaSel.fecha)"
              ><Plus :size="13" aria-hidden="true" /> Reservar</button>
            </div>

            <ul v-if="espacio.abierto" class="mt-2 space-y-1.5">
              <li v-for="evento in espacio.eventos" :key="evento.tipo + evento.id">
                <button
                  type="button"
                  class="flex w-full items-center gap-2.5 border-l-2 px-2.5 py-2 text-left transition-colors
                         focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
                  :class="evento.tipo === 'bloqueo'
                    ? 'border-l-red-600 bg-red-50 hover:bg-red-100'
                    : 'border-l-dark bg-nodo-400/60 hover:bg-nodo-400'"
                  @click="detalle = { ...evento, espacio: espacio.nombre, fecha: diaSel.fecha }"
                >
                  <span class="shrink-0 font-mono text-xs text-dark">{{ evento.inicio }}–{{ evento.fin }}</span>
                  <span class="truncate text-sm text-dark">{{ evento.titulo }}</span>
                </button>
              </li>
              <li v-if="!espacio.eventos.length" class="py-1 text-xs text-dark/45">Libre todo el día.</li>
            </ul>
            <p v-else class="mt-1.5 text-xs text-dark/40">Cerrado este día.</p>
          </li>
        </ul>
      </div>
    </Panel>

    <Teleport to="body">
      <!-- ── Nueva reserva ──────────────────────────────────────────────── -->
      <div v-if="nuevaReserva" class="fixed inset-0 z-50 flex items-center justify-center bg-tinta/60 p-4" role="dialog" aria-modal="true" @click.self="nuevaReserva = false">
        <form
          class="w-full max-w-md border-2 border-dark bg-white"
          @submit.prevent="reserva.post(route('agenda.reservar'), { preserveScroll: true, onSuccess: () => { nuevaReserva = false } })"
        >
          <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
            Reservar desde recepción
          </h2>

          <div class="space-y-3 p-4">
            <div class="relative">
              <label for="ag-miembro" class="mb-1 block text-xs font-bold text-dark">Miembro</label>
              <input
                id="ag-miembro" v-model="busqueda" type="text" autocomplete="off"
                placeholder="Nombre, correo o teléfono…"
                class="w-full border border-dark/25 px-2.5 py-2 text-sm placeholder:text-dark/35 focus:border-dark"
                @input="elegido = null; reserva.user_id = ''; buscarMiembro()"
              />
              <ul v-if="sugerencias.length" class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto border border-dark bg-white">
                <li v-for="m in sugerencias" :key="m.id">
                  <button type="button" class="w-full px-3 py-2 text-left text-sm hover:bg-cream-50" @click="elegirMiembro(m)">
                    <span class="block font-bold text-dark">{{ m.nombre }}</span>
                    <span class="block text-xs text-dark/55">{{ m.plan ?? 'sin plan' }} · {{ m.email }}</span>
                  </button>
                </li>
              </ul>
              <p v-if="reserva.errors.user_id" class="mt-1 text-xs text-red-700">{{ reserva.errors.user_id }}</p>
            </div>

            <div>
              <label for="ag-espacio" class="mb-1 block text-xs font-bold text-dark">Espacio</label>
              <select id="ag-espacio" v-model="reserva.espacio_id" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark">
                <option value="" disabled>Elige un espacio</option>
                <option v-for="e in espacios" :key="e.id" :value="e.id">{{ e.nombre }}</option>
              </select>
              <p v-if="reserva.errors.espacio_id" class="mt-1 text-xs text-red-700">{{ reserva.errors.espacio_id }}</p>
            </div>

            <div class="grid grid-cols-3 gap-2">
              <div>
                <label for="ag-fecha" class="mb-1 block text-xs font-bold text-dark">Día</label>
                <input id="ag-fecha" v-model="reserva.fecha" type="date" required class="w-full border border-dark/25 px-2 py-2 text-sm focus:border-dark" />
              </div>
              <div>
                <label for="ag-ini" class="mb-1 block text-xs font-bold text-dark">Desde</label>
                <input id="ag-ini" v-model="reserva.hora_inicio" type="time" step="1800" required class="w-full border border-dark/25 px-2 py-2 font-mono text-sm focus:border-dark" />
              </div>
              <div>
                <label for="ag-fin" class="mb-1 block text-xs font-bold text-dark">Hasta</label>
                <input id="ag-fin" v-model="reserva.hora_fin" type="time" step="1800" required class="w-full border border-dark/25 px-2 py-2 font-mono text-sm focus:border-dark" />
              </div>
            </div>

            <p v-for="(msg, campo) in reserva.errors" :key="campo" v-show="!['user_id','espacio_id','sobrecupo','motivo_sobrecupo'].includes(String(campo))" class="text-xs text-red-700">
              {{ msg }}
            </p>

            <!-- El sobrecupo: se pide autorización explícita, no se deja pasar. -->
            <div v-if="reserva.errors.sobrecupo || reserva.autoriza_sobrecupo" class="border border-amber-600/40 bg-amber-50 p-3">
              <p class="flex items-start gap-2 text-xs text-amber-900">
                <AlertTriangle :size="14" class="mt-0.5 shrink-0" aria-hidden="true" />
                <span>
                  <strong>Se pasa del cupo:</strong> {{ reserva.errors.sobrecupo }}.
                  Puedes autorizarlo, pero queda en la bitácora con tu nombre.
                </span>
              </p>

              <label class="mt-2.5 flex items-center gap-2 text-xs font-bold text-amber-900">
                <input v-model="reserva.autoriza_sobrecupo" type="checkbox" class="h-4 w-4 rounded-none border-amber-700" />
                Autorizo el sobrecupo
              </label>

              <textarea
                v-if="reserva.autoriza_sobrecupo"
                v-model="reserva.motivo_sobrecupo" rows="2" required
                placeholder="Por qué lo autorizas."
                class="mt-2 w-full border border-amber-600/40 bg-white px-2.5 py-2 text-sm placeholder:text-dark/35 focus:border-amber-700"
              />
              <p v-if="reserva.errors.motivo_sobrecupo" class="mt-1 text-xs text-red-700">{{ reserva.errors.motivo_sobrecupo }}</p>
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="nuevaReserva = false">Cancelar</button>
            <button type="submit" :disabled="reserva.processing" class="min-h-[36px] border border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark">Reservar</button>
          </div>
        </form>
      </div>

      <!-- ── Bloqueo ────────────────────────────────────────────────────── -->
      <div v-if="bloqueo" class="fixed inset-0 z-50 flex items-center justify-center bg-tinta/60 p-4" role="dialog" aria-modal="true" @click.self="bloqueo = false">
        <form
          class="w-full max-w-md border-2 border-dark bg-white"
          @submit.prevent="bloqueoForm.post(route('agenda.bloquear'), { preserveScroll: true, onSuccess: () => { bloqueo = false } })"
        >
          <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
            Bloquear un espacio
          </h2>

          <div class="space-y-3 p-4">
            <p class="text-xs text-dark/70">
              Un bloqueo compite con las reservas: mientras exista, ese horario no se puede reservar.
            </p>

            <div>
              <label for="bl-espacio" class="mb-1 block text-xs font-bold text-dark">Espacio</label>
              <select id="bl-espacio" v-model="bloqueoForm.espacio_id" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark">
                <option value="" disabled>Elige un espacio</option>
                <option v-for="e in espacios" :key="e.id" :value="e.id">{{ e.nombre }}</option>
              </select>
            </div>

            <div class="grid grid-cols-3 gap-2">
              <div>
                <label for="bl-fecha" class="mb-1 block text-xs font-bold text-dark">Día</label>
                <input id="bl-fecha" v-model="bloqueoForm.fecha" type="date" required class="w-full border border-dark/25 px-2 py-2 text-sm focus:border-dark" />
              </div>
              <div>
                <label for="bl-ini" class="mb-1 block text-xs font-bold text-dark">Desde</label>
                <input id="bl-ini" v-model="bloqueoForm.hora_inicio" type="time" step="1800" required class="w-full border border-dark/25 px-2 py-2 font-mono text-sm focus:border-dark" />
              </div>
              <div>
                <label for="bl-fin" class="mb-1 block text-xs font-bold text-dark">Hasta</label>
                <input id="bl-fin" v-model="bloqueoForm.hora_fin" type="time" step="1800" required class="w-full border border-dark/25 px-2 py-2 font-mono text-sm focus:border-dark" />
              </div>
            </div>

            <div>
              <label for="bl-motivo" class="mb-1 block text-xs font-bold text-dark">Motivo</label>
              <input id="bl-motivo" v-model="bloqueoForm.motivo" type="text" required placeholder="Mantenimiento del aire, evento privado…" class="w-full border border-dark/25 px-2.5 py-2 text-sm placeholder:text-dark/35 focus:border-dark" />
              <p v-if="bloqueoForm.errors.motivo" class="mt-1 text-xs text-red-700">{{ bloqueoForm.errors.motivo }}</p>
            </div>

            <p v-if="bloqueoForm.errors.hora_inicio" class="border border-red-600/40 bg-red-50 p-2.5 text-xs text-red-800">
              {{ bloqueoForm.errors.hora_inicio }}
            </p>
          </div>

          <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="bloqueo = false">Cancelar</button>
            <button type="submit" :disabled="bloqueoForm.processing" class="min-h-[36px] border border-dark bg-dark px-4 font-display text-xs font-bold text-white">Bloquear</button>
          </div>
        </form>
      </div>

      <!-- ── Detalle de un evento ───────────────────────────────────────── -->
      <div v-if="detalle" class="fixed inset-0 z-50 flex items-center justify-center bg-tinta/60 p-4" role="dialog" aria-modal="true" @click.self="detalle = null">
        <div class="w-full max-w-sm border-2 border-dark bg-white">
          <h2 class="flex items-center justify-between border-b border-dark/15 bg-cream-50 px-4 py-3">
            <span class="font-display text-sm font-bold text-dark">
              {{ detalle.tipo === 'bloqueo' ? 'Bloqueo' : 'Reserva' }}
            </span>
            <button type="button" aria-label="Cerrar" class="text-dark/50 hover:text-dark" @click="detalle = null"><X :size="16" /></button>
          </h2>

          <div class="space-y-2 p-4 text-sm">
            <p class="font-display font-bold text-dark">{{ detalle.titulo }}</p>
            <p class="text-dark/70">{{ detalle.espacio }}</p>
            <p class="font-mono text-dark">{{ detalle.fecha }} · {{ detalle.inicio }}–{{ detalle.fin }}</p>

            <Link
              v-if="detalle.url" :href="detalle.url"
              class="mt-2 inline-block font-display text-xs font-bold text-dark underline decoration-dark/30 underline-offset-2 hover:decoration-dark"
            >Ver la ficha del miembro</Link>
          </div>

          <div class="border-t border-dark/15 bg-cream-50 p-4">
            <template v-if="detalle.tipo === 'reserva'">
              <label class="flex items-center gap-2 text-xs text-dark">
                <input v-model="cancelacion.devolver_horas" type="checkbox" class="h-4 w-4 rounded-none border-dark" />
                Devolverle las horas
              </label>
              <input
                v-model="cancelacion.motivo" type="text" placeholder="Motivo (opcional)"
                class="mt-2 w-full border border-dark/25 bg-white px-2.5 py-2 text-sm placeholder:text-dark/35 focus:border-dark"
              />
              <button
                type="button"
                class="mt-2 min-h-[36px] w-full border border-red-700 bg-red-700 px-4 font-display text-xs font-bold text-white"
                @click="cancelacion.delete(route('agenda.cancelar', detalle.id), { preserveScroll: true, onSuccess: () => { detalle = null } })"
              >Cancelar la reserva</button>
            </template>

            <button
              v-else
              type="button"
              class="min-h-[36px] w-full border border-dark px-4 font-display text-xs font-bold text-dark"
              @click="router.delete(route('agenda.desbloquear', detalle.id), { preserveScroll: true, onSuccess: () => { detalle = null } })"
            >Quitar el bloqueo</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
