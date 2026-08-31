<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Plus, Calculator, AlertTriangle, Inbox, Wallet } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'
import Estado from '@/Components/Panel/Estado.vue'
import ListaResponsiva, { type Columna } from '@/Components/Panel/ListaResponsiva.vue'

/**
 * Salones Yucatán Emprende (Fase 3.5).
 *
 * Es un cotizador antes que un formulario: lo que hace recepción con un salón
 * es **decir cuánto cuesta**, y ahí es donde se equivoca la gente echando
 * cuentas a mano. El total se recalcula en el servidor mientras se escribe, y
 * se vuelve a calcular al guardar: nunca llega desde el navegador.
 */
const props = defineProps<{
  rentas: any
  filtros: Record<string, any>
  estados: any[]
  salones: any[]
  montajes: any[]
  tarifas: any
  prospectos: any[]
}>()

const abierto = ref(false)
const editando = ref<any | null>(null)
const cotizacion = ref<any | null>(null)
const cobrando = ref<any | null>(null)

const form = useForm({
  espacio_id: '', cliente_nombre: '', cliente_email: '', cliente_telefono: '', cliente_empresa: '',
  contacto_id: null as number | null,
  evento_nombre: '', fecha: '', hora_inicio: '09:00', hora_fin: '13:00',
  montaje: '', personas: 0,
  con_coffee_break: false, coffee_personas: 0, coffee_precio_persona: '' as any,
  descuento: 0, anticipo: 0, estado: 'cotizacion', notas: '',
})

const anticipoForm = useForm({ anticipo: 0, anticipo_pagado_el: '' })

const precio = (v: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0)

const fecha = (iso: string | null) =>
  iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'

/** Recotiza en el servidor: el mismo cálculo que al guardar, sin dos verdades. */
let temporizador: number | undefined
async function recotizar() {
  if (temporizador) window.clearTimeout(temporizador)

  temporizador = window.setTimeout(async () => {
    const r = await fetch(route('salones.cotizar'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
      },
      body: JSON.stringify({
        espacio_id: form.espacio_id || null,
        horas: horasCalculadas.value,
        con_coffee_break: form.con_coffee_break,
        coffee_personas: Number(form.coffee_personas) || 0,
        coffee_precio_persona: form.coffee_precio_persona === '' ? null : Number(form.coffee_precio_persona),
        descuento: Number(form.descuento) || 0,
        montaje: form.montaje || null,
        personas: Number(form.personas) || 0,
      }),
    })

    cotizacion.value = r.ok ? await r.json() : null
  }, 200)
}

const horasCalculadas = computed(() => {
  if (!form.hora_inicio || !form.hora_fin) return 0
  const [hi, mi] = form.hora_inicio.split(':').map(Number)
  const [hf, mf] = form.hora_fin.split(':').map(Number)
  return Math.max(0, ((hf * 60 + mf) - (hi * 60 + mi)) / 60)
})

watch(
  () => [form.espacio_id, form.hora_inicio, form.hora_fin, form.con_coffee_break,
         form.coffee_personas, form.coffee_precio_persona, form.descuento, form.montaje, form.personas],
  recotizar,
)

/** Aforo de cada montaje en el salón elegido, para no prometer lo que no cabe. */
const capacidades = computed(() => {
  const salon = props.salones.find((s) => String(s.id) === String(form.espacio_id))
  return salon?.capacidades ?? {}
})

function abrir(renta: any | null = null, prospecto: any | null = null) {
  editando.value = renta
  form.reset()
  form.clearErrors()
  cotizacion.value = null

  if (prospecto) {
    // Un prospecto de «Hablemos» llega con lo que la persona escribió: se
    // arrastra tal cual para no volver a teclearlo.
    form.cliente_nombre = prospecto.nombre
    form.cliente_email = prospecto.email ?? ''
    form.cliente_telefono = prospecto.telefono ?? ''
    form.cliente_empresa = prospecto.empresa ?? ''
    form.evento_nombre = prospecto.asunto ?? ''
    form.notas = prospecto.comentarios ?? ''
    form.contacto_id = prospecto.id
  }

  if (renta) {
    Object.assign(form, {
      espacio_id: renta.espacio_id ?? '',
      cliente_nombre: renta.cliente ?? '',
      cliente_empresa: renta.empresa ?? '',
      evento_nombre: renta.evento ?? '',
      fecha: renta.fecha ?? '',
      hora_inicio: renta.inicio ?? '09:00',
      hora_fin: renta.fin ?? '13:00',
      personas: renta.personas ?? 0,
      estado: renta.estado,
      anticipo: renta.anticipo ?? 0,
    })
  }

  abierto.value = true
  recotizar()
}

function guardar() {
  const opciones = { preserveScroll: true, onSuccess: () => { abierto.value = false } }

  editando.value
    ? form.patch(route('salones.update', editando.value.id), opciones)
    : form.post(route('salones.store'), opciones)
}

const filtrar = (estado: string) =>
  router.get(route('salones.index'), { estado: estado || undefined }, { preserveState: true, replace: true })

/**
 * De un evento, lo que se consulta de pie es el salón y la fecha, el total y en
 * qué estado va (cotización o confirmado). El montaje y el anticipo son detalle.
 * El orden es el de la tabla ancha, que no cambia. Tocar la fila abre el mismo
 * cotizador de siempre (por eso `seleccionable`, no navega a otra página).
 */
const columnas: Columna[] = [
  { clave: 'cliente', etiqueta: 'Cliente', rol: 'identidad', clase: 'px-4' },
  { clave: 'salonfecha', etiqueta: 'Salón y fecha', rol: 'resumen' },
  { clave: 'montaje', etiqueta: 'Montaje', rol: 'detalle' },
  { clave: 'total', etiqueta: 'Total', rol: 'resumen', clase: 'text-right' },
  { clave: 'anticipo', etiqueta: 'Anticipo', rol: 'detalle', clase: 'text-right' },
  { clave: 'estado', etiqueta: 'Estado', rol: 'resumen', sinEtiqueta: true },
]
</script>

<template>
  <Head title="Eventos" />

  <AuthenticatedLayout>
    <template #breadcrumb>
      <span class="font-display font-bold text-dark">Eventos</span>
      <span>· Salones Yucatán Emprende</span>
    </template>

    <div class="grid gap-4 lg:grid-cols-4">
      <div class="lg:col-span-3">
        <Panel padding="none">
          <template #acciones>
            <div class="flex flex-wrap items-center gap-2">
              <select
                :value="filtros.estado ?? ''" aria-label="Filtrar por estado"
                class="min-h-[30px] border border-dark/20 bg-white px-2 text-xs focus:border-dark"
                @change="filtrar(($event.target as HTMLSelectElement).value)"
              >
                <option value="">Todos</option>
                <option v-for="e in estados" :key="e.valor" :value="e.valor">{{ e.etiqueta }}</option>
              </select>

              <button
                type="button"
                class="flex min-h-[30px] items-center gap-1.5 border border-dark bg-nodo-400 px-2.5
                       font-display text-xs font-bold text-dark hover:bg-nodo-500"
                @click="abrir()"
              ><Plus :size="13" aria-hidden="true" /> Cotizar</button>
            </div>
          </template>

          <ListaResponsiva
            :columnas="columnas"
            :filas="rentas.data"
            seleccionable
            etiqueta-abrir="Ver detalle"
            vacio="Sin cotizaciones ni eventos."
            @seleccionar="abrir"
          >
            <template #cliente="{ fila: r }">
              <button
                type="button"
                class="text-left font-display font-bold text-dark
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
                @click.stop="abrir(r)"
              >{{ r.cliente }}</button>
              <span class="block text-xs font-normal text-dark/55">
                {{ r.empresa }}<template v-if="r.evento"> · {{ r.evento }}</template>
              </span>
            </template>

            <template #salonfecha="{ fila: r }">
              <span class="block text-dark">{{ r.salon }}</span>
              <span class="block font-mono text-dark/55">
                {{ fecha(r.fecha) }}<template v-if="r.inicio"> · {{ r.inicio }}–{{ r.fin }}</template>
              </span>
            </template>

            <template #montaje="{ fila: r }">
              {{ r.montaje ?? '—' }}
              <span v-if="r.personas" class="block font-mono text-[0.6875rem] text-dark/50">{{ r.personas }} pax</span>
            </template>

            <template #total="{ fila: r }">
              <span class="font-display font-bold text-dark">{{ precio(r.total) }}</span>
            </template>

            <template #anticipo="{ fila: r }">
              <span :class="r.anticipo_pagado ? 'text-emerald-700' : 'text-dark/55'">{{ precio(r.anticipo) }}</span>
              <button
                type="button"
                class="mt-1 block font-mono text-[0.625rem] text-dark/45 underline hover:text-dark"
                @click.stop="cobrando = r; anticipoForm.anticipo = r.anticipo"
              >registrar</button>
            </template>

            <template #estado="{ fila: r }"><Estado :tono="r.tono" :texto="r.estado_etiqueta" /></template>
          </ListaResponsiva>
        </Panel>
      </div>

      <!-- Prospectos de «Hablemos» -->
      <Panel titulo="Prospectos" :contador="prospectos.length" padding="none">
        <p class="px-4 py-2 text-[0.6875rem] text-dark/50">
          Del formulario del sitio público, sin atender.
        </p>

        <p v-if="!prospectos.length" class="px-4 pb-4 text-sm text-dark/50">
          <Inbox :size="15" class="mb-1 inline" aria-hidden="true" /> Nada pendiente.
        </p>

        <ul v-else class="divide-y divide-dark/10">
          <li v-for="p in prospectos" :key="p.id" class="px-4 py-2.5">
            <p class="font-display text-sm font-bold text-dark">{{ p.nombre }}</p>
            <p class="text-xs text-dark/60">{{ p.empresa }}</p>
            <p v-if="p.asunto" class="mt-0.5 truncate text-xs text-dark/70">{{ p.asunto }}</p>
            <button
              type="button"
              class="mt-1.5 font-display text-xs font-bold text-dark underline decoration-dark/30
                     underline-offset-2 hover:decoration-dark"
              @click="abrir(null, p)"
            >Cotizar para esta persona</button>
          </li>
        </ul>
      </Panel>
    </div>

    <!-- ── Cotizador ────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="abierto" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-tinta/60 p-4" role="dialog" aria-modal="true" @click.self="abierto = false">
        <form class="my-4 w-full max-w-2xl border-2 border-dark bg-white" @submit.prevent="guardar">
          <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
            {{ editando ? 'Editar renta' : 'Nueva cotización' }}
          </h2>

          <div class="grid gap-4 p-4 md:grid-cols-2">
            <!-- Cliente -->
            <fieldset class="space-y-3 md:col-span-2">
              <legend class="font-mono text-[0.625rem] uppercase tracking-[0.1em] text-dark/50">Cliente</legend>

              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label for="sa-nombre" class="mb-1 block text-xs font-bold text-dark">Nombre</label>
                  <input id="sa-nombre" v-model="form.cliente_nombre" type="text" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
                  <p v-if="form.errors.cliente_nombre" class="mt-1 text-xs text-red-700">{{ form.errors.cliente_nombre }}</p>
                </div>
                <div>
                  <label for="sa-empresa" class="mb-1 block text-xs font-bold text-dark">Empresa</label>
                  <input id="sa-empresa" v-model="form.cliente_empresa" type="text" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
                </div>
                <div>
                  <label for="sa-mail" class="mb-1 block text-xs font-bold text-dark">Correo</label>
                  <input id="sa-mail" v-model="form.cliente_email" type="email" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
                </div>
                <div>
                  <label for="sa-tel" class="mb-1 block text-xs font-bold text-dark">Teléfono</label>
                  <input id="sa-tel" v-model="form.cliente_telefono" type="tel" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
                </div>
              </div>
            </fieldset>

            <!-- Evento -->
            <fieldset class="space-y-3 md:col-span-2">
              <legend class="font-mono text-[0.625rem] uppercase tracking-[0.1em] text-dark/50">El evento</legend>

              <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                  <label for="sa-evento" class="mb-1 block text-xs font-bold text-dark">Nombre del evento</label>
                  <input id="sa-evento" v-model="form.evento_nombre" type="text" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
                </div>

                <div>
                  <label for="sa-salon" class="mb-1 block text-xs font-bold text-dark">Salón</label>
                  <select id="sa-salon" v-model="form.espacio_id" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark">
                    <option value="" disabled>Elige un salón</option>
                    <option v-for="s in salones" :key="s.id" :value="s.id">{{ s.nombre }} ({{ s.medidas }})</option>
                  </select>
                </div>

                <div>
                  <label for="sa-fecha" class="mb-1 block text-xs font-bold text-dark">Fecha</label>
                  <input id="sa-fecha" v-model="form.fecha" type="date" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
                  <p v-if="form.errors.fecha" class="mt-1 text-xs text-red-700">{{ form.errors.fecha }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label for="sa-ini" class="mb-1 block text-xs font-bold text-dark">Desde</label>
                    <input id="sa-ini" v-model="form.hora_inicio" type="time" step="1800" class="w-full border border-dark/25 px-2 py-2 font-mono text-sm focus:border-dark" />
                  </div>
                  <div>
                    <label for="sa-fin" class="mb-1 block text-xs font-bold text-dark">Hasta</label>
                    <input id="sa-fin" v-model="form.hora_fin" type="time" step="1800" class="w-full border border-dark/25 px-2 py-2 font-mono text-sm focus:border-dark" />
                  </div>
                </div>

                <div>
                  <label for="sa-pax" class="mb-1 block text-xs font-bold text-dark">Personas</label>
                  <input id="sa-pax" v-model.number="form.personas" type="number" min="0" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
                </div>

                <div class="sm:col-span-2">
                  <label for="sa-montaje" class="mb-1 block text-xs font-bold text-dark">Montaje</label>
                  <select id="sa-montaje" v-model="form.montaje" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark">
                    <option value="">Sin definir</option>
                    <option v-for="m in montajes" :key="m.valor" :value="m.valor">
                      {{ m.etiqueta }}<template v-if="capacidades[m.valor]"> — hasta {{ capacidades[m.valor] }} pax</template>
                    </option>
                  </select>
                </div>
              </div>

              <!-- El aforo: lo que evita prometer lo que no cabe. -->
              <ul v-if="cotizacion?.avisos_aforo?.length" class="space-y-1">
                <li
                  v-for="(aviso, i) in cotizacion.avisos_aforo" :key="i"
                  class="flex items-start gap-2 border border-amber-600/40 bg-amber-50 p-2.5 text-xs text-amber-900"
                >
                  <AlertTriangle :size="13" class="mt-0.5 shrink-0" aria-hidden="true" /> {{ aviso }}
                </li>
              </ul>
            </fieldset>

            <!-- Coffee break -->
            <fieldset class="space-y-3 md:col-span-2">
              <legend class="font-mono text-[0.625rem] uppercase tracking-[0.1em] text-dark/50">Coffee break</legend>

              <label class="flex items-center gap-2 text-sm text-dark">
                <input v-model="form.con_coffee_break" type="checkbox" class="h-4 w-4 rounded-none border-dark" />
                Incluir coffee break
              </label>

              <div v-if="form.con_coffee_break" class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label for="cb-pax" class="mb-1 block text-xs font-bold text-dark">Personas</label>
                  <input id="cb-pax" v-model.number="form.coffee_personas" type="number" min="0" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
                </div>

                <div>
                  <label for="cb-precio" class="mb-1 block text-xs font-bold text-dark">
                    Precio por persona
                    <span v-if="!cotizacion?.coffee_requiere_precio" class="font-normal text-dark/50">(automático)</span>
                  </label>
                  <input
                    id="cb-precio" v-model="form.coffee_precio_persona" type="number" step="0.01" min="0"
                    :placeholder="String(cotizacion?.coffee_precio_persona ?? '')"
                    :required="cotizacion?.coffee_requiere_precio"
                    class="w-full border px-2.5 py-2 text-sm"
                    :class="cotizacion?.coffee_requiere_precio ? 'border-amber-600 bg-amber-50' : 'border-dark/25 focus:border-dark'"
                  />
                  <p v-if="cotizacion?.coffee_nota" class="mt-1 text-[0.6875rem]" :class="cotizacion.coffee_requiere_precio ? 'text-amber-800' : 'text-dark/55'">
                    {{ cotizacion.coffee_nota }}
                  </p>
                  <p v-if="form.errors.coffee_precio_persona" class="mt-1 text-xs text-red-700">{{ form.errors.coffee_precio_persona }}</p>
                </div>
              </div>
            </fieldset>

            <!-- Importe -->
            <div class="border border-dark/20 bg-cream-50 p-3 md:col-span-2">
              <p class="mb-2 flex items-center gap-1.5 font-mono text-[0.625rem] uppercase tracking-[0.1em] text-dark/50">
                <Calculator :size="12" aria-hidden="true" /> Cotización
              </p>

              <dl class="space-y-1 text-sm">
                <div class="flex justify-between">
                  <dt class="text-dark/70">
                    Salón · {{ cotizacion?.horas ?? 0 }} h × {{ precio(cotizacion?.precio_hora ?? 0) }}
                  </dt>
                  <dd class="font-mono text-dark">{{ precio(cotizacion?.subtotal_salon ?? 0) }}</dd>
                </div>

                <div v-if="form.con_coffee_break" class="flex justify-between">
                  <dt class="text-dark/70">
                    Coffee · {{ form.coffee_personas }} × {{ precio(cotizacion?.coffee_precio_persona ?? 0) }}
                  </dt>
                  <dd class="font-mono text-dark">{{ precio(cotizacion?.subtotal_coffee ?? 0) }}</dd>
                </div>

                <div class="flex items-center justify-between gap-3">
                  <dt class="text-dark/70">Descuento</dt>
                  <dd>
                    <input
                      v-model.number="form.descuento" type="number" min="0" step="0.01"
                      aria-label="Descuento"
                      class="w-28 border border-dark/25 bg-white px-2 py-1 text-right font-mono text-sm focus:border-dark"
                    />
                  </dd>
                </div>

                <div class="flex justify-between border-t border-dark/20 pt-2">
                  <dt class="font-display font-bold text-dark">Total</dt>
                  <dd class="font-display text-lg font-extrabold text-dark">{{ precio(cotizacion?.total ?? 0) }}</dd>
                </div>
              </dl>
            </div>

            <!-- Estado y notas -->
            <div>
              <label for="sa-estado" class="mb-1 block text-xs font-bold text-dark">Estado</label>
              <select id="sa-estado" v-model="form.estado" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark">
                <option v-for="e in estados" :key="e.valor" :value="e.valor">{{ e.etiqueta }}</option>
              </select>
              <p class="mt-1 text-[0.6875rem] text-dark/55">
                Confirmar aparta el salón en la agenda; cotizar no.
              </p>
            </div>

            <div>
              <label for="sa-anticipo" class="mb-1 block text-xs font-bold text-dark">Anticipo acordado</label>
              <input id="sa-anticipo" v-model.number="form.anticipo" type="number" min="0" step="0.01" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              <p class="mt-1 text-[0.6875rem] text-dark/55">Se negocia por evento; no hay porcentaje fijo.</p>
            </div>

            <div class="md:col-span-2">
              <label for="sa-notas" class="mb-1 block text-xs font-bold text-dark">Notas</label>
              <textarea id="sa-notas" v-model="form.notas" rows="2" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="abierto = false">Cancelar</button>
            <button type="submit" :disabled="form.processing" class="min-h-[36px] border border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark">Guardar</button>
          </div>
        </form>
      </div>

      <!-- Registrar anticipo -->
      <div v-if="cobrando" class="fixed inset-0 z-50 flex items-center justify-center bg-tinta/60 p-4" role="dialog" aria-modal="true" @click.self="cobrando = null">
        <form
          class="w-full max-w-sm border-2 border-dark bg-white"
          @submit.prevent="anticipoForm.post(route('salones.anticipo', cobrando.id), { preserveScroll: true, onSuccess: () => { cobrando = null } })"
        >
          <h2 class="flex items-center gap-2 border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
            <Wallet :size="15" aria-hidden="true" /> Anticipo de {{ cobrando.cliente }}
          </h2>

          <div class="space-y-3 p-4">
            <p class="text-xs text-dark/70">Total del evento: <strong>{{ precio(cobrando.total) }}</strong></p>

            <div>
              <label for="an-monto" class="mb-1 block text-xs font-bold text-dark">Monto recibido</label>
              <input id="an-monto" v-model.number="anticipoForm.anticipo" type="number" min="0" step="0.01" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              <p v-if="anticipoForm.errors.anticipo" class="mt-1 text-xs text-red-700">{{ anticipoForm.errors.anticipo }}</p>
            </div>

            <div>
              <label for="an-fecha" class="mb-1 block text-xs font-bold text-dark">Fecha del pago</label>
              <input id="an-fecha" v-model="anticipoForm.anticipo_pagado_el" type="date" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="cobrando = null">Cancelar</button>
            <button type="submit" class="min-h-[36px] border border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark">Registrar</button>
          </div>
        </form>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
