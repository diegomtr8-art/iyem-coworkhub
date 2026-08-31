<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Plus, Search, Download } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'

/**
 * Day-pass gratuito del interior (Fase 4.E). Alta rápida de mostrador: pensada
 * para registrar a alguien en menos de un minuto con otra persona esperando.
 */
const props = defineProps<{
  visitas: any
  filtros: any
  municipios: string[]
  giros: string[]
  resumen: any
}>()

const abierto = ref(false)
const busqueda = ref('')
const sugerencias = ref<any[]>([])

const form = useForm({
  visitante_id: null as number | null,
  nombre: '', telefono: '', municipio: '', giro: '', como_se_entero: '',
})

// Buscar un visitante que ya vino, para no recapturar.
let t: number | undefined
watch(busqueda, (q) => {
  clearTimeout(t)
  if (q.trim().length < 2) { sugerencias.value = []; return }
  t = window.setTimeout(async () => {
    const r = await fetch(route('daypass.buscar', { q }), { headers: { Accept: 'application/json' } })
    sugerencias.value = (await r.json()).resultados
  }, 250)
})

function elegir(v: any) {
  form.visitante_id = v.id
  form.nombre = v.nombre
  form.telefono = v.telefono ?? ''
  form.municipio = v.municipio
  form.giro = v.giro ?? ''
  form.como_se_entero = v.como_se_entero ?? ''
  sugerencias.value = []
  busqueda.value = v.nombre
}

function nuevo() {
  form.reset(); form.clearErrors(); busqueda.value = ''; sugerencias.value = []
  abierto.value = true
}

function registrar() {
  form.post(route('daypass.registrar'), {
    preserveScroll: true,
    onSuccess: () => { abierto.value = false; form.reset(); busqueda.value = '' },
  })
}

const filtro = useForm({ ...props.filtros })
function aplicarFiltros() {
  router.get(route('daypass.index'), filtro.data(), { preserveState: true, preserveScroll: true })
}
function exportar() {
  window.location.href = route('daypass.exportar', filtro.data())
}
</script>

<template>
  <Head title="Day-pass del interior" />

  <AuthenticatedLayout>
    <template #breadcrumb>
      <span class="font-display font-bold text-dark">Day-pass del interior</span>
    </template>

    <!-- Resumen del mes: el dato para el IYEM. -->
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
      <div class="border-2 border-dark bg-nodo-400 p-4">
        <p class="font-mono text-xs uppercase text-dark/70">Este mes</p>
        <p class="font-display text-3xl font-black text-dark">{{ resumen.total_mes }}</p>
        <p class="font-body text-xs text-dark/70">day-passes gratuitos</p>
      </div>
      <div class="border-2 border-dark/15 bg-white p-4">
        <p class="mb-1 font-mono text-xs uppercase text-dark/60">Por municipio</p>
        <ul class="space-y-0.5 text-sm text-dark">
          <li v-for="(n, m) in resumen.por_municipio" :key="m" class="flex justify-between">
            <span>{{ m }}</span><span class="font-bold">{{ n }}</span>
          </li>
          <li v-if="!Object.keys(resumen.por_municipio).length" class="text-dark/40">Sin registros aún</li>
        </ul>
      </div>
      <div class="border-2 border-dark/15 bg-white p-4">
        <p class="mb-1 font-mono text-xs uppercase text-dark/60">Por giro</p>
        <ul class="space-y-0.5 text-sm text-dark">
          <li v-for="(n, g) in resumen.por_giro" :key="g" class="flex justify-between">
            <span>{{ g }}</span><span class="font-bold">{{ n }}</span>
          </li>
          <li v-if="!Object.keys(resumen.por_giro).length" class="text-dark/40">Sin registros aún</li>
        </ul>
      </div>
    </div>

    <Panel titulo="Registro de day-pass" :contador="visitas.total" padding="none">
      <template #acciones>
        <button type="button" @click="nuevo" class="flex min-h-[36px] items-center gap-1.5 border-2 border-dark bg-nodo-400 px-3 font-display text-xs font-bold text-dark">
          <Plus :size="14" aria-hidden="true" /> Registrar visita
        </button>
        <button type="button" @click="exportar" class="flex min-h-[36px] items-center gap-1.5 border border-dark/25 px-3 font-display text-xs font-bold text-dark hover:border-dark">
          <Download :size="14" aria-hidden="true" /> Exportar Excel
        </button>
      </template>

      <!-- Filtros. -->
      <div class="flex flex-wrap items-end gap-3 border-b border-dark/10 bg-cream-50 px-4 py-3">
        <label class="text-xs text-dark">Municipio
          <select v-model="filtro.municipio" @change="aplicarFiltros" class="mt-1 block border border-dark/25 px-2 py-1.5 text-sm">
            <option value="">Todos</option>
            <option v-for="m in municipios" :key="m" :value="m">{{ m }}</option>
          </select>
        </label>
        <label class="text-xs text-dark">Giro
          <select v-model="filtro.giro" @change="aplicarFiltros" class="mt-1 block border border-dark/25 px-2 py-1.5 text-sm">
            <option value="">Todos</option>
            <option v-for="g in giros" :key="g" :value="g">{{ g }}</option>
          </select>
        </label>
        <label class="text-xs text-dark">Desde
          <input type="date" v-model="filtro.desde" @change="aplicarFiltros" class="mt-1 block border border-dark/25 px-2 py-1.5 text-sm" />
        </label>
        <label class="text-xs text-dark">Hasta
          <input type="date" v-model="filtro.hasta" @change="aplicarFiltros" class="mt-1 block border border-dark/25 px-2 py-1.5 text-sm" />
        </label>
      </div>

      <!-- En pantallas anchas, tabla. En movil, tarjetas apiladas (nada de scroll
           horizontal en una tabla densa que no se puede leer). -->
      <table class="hidden w-full text-sm sm:table">
        <thead class="border-b border-dark/15 bg-cream-50 text-left text-xs uppercase text-dark/60">
          <tr>
            <th class="px-4 py-2">Fecha</th><th class="px-4 py-2">Nombre</th>
            <th class="px-4 py-2">Teléfono</th><th class="px-4 py-2">Municipio</th><th class="px-4 py-2">Giro</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-dark/10">
          <tr v-for="v in visitas.data" :key="v.id">
            <td class="px-4 py-2 font-mono text-xs">{{ v.fecha }}</td>
            <td class="px-4 py-2 font-medium text-dark">{{ v.nombre }}</td>
            <td class="px-4 py-2 text-dark/70">{{ v.telefono }}</td>
            <td class="px-4 py-2 text-dark/70">{{ v.municipio }}</td>
            <td class="px-4 py-2 text-dark/70">{{ v.giro }}</td>
          </tr>
          <tr v-if="!visitas.data.length"><td colspan="5" class="px-4 py-8 text-center text-dark/40">Sin visitas registradas.</td></tr>
        </tbody>
      </table>

      <ul class="divide-y divide-dark/10 sm:hidden">
        <li v-for="v in visitas.data" :key="v.id" class="px-4 py-3">
          <div class="flex items-baseline justify-between gap-2">
            <p class="font-medium text-dark">{{ v.nombre }}</p>
            <p class="font-mono text-xs text-dark/50">{{ v.fecha }}</p>
          </div>
          <p class="mt-0.5 text-sm text-dark/70">
            {{ v.municipio }}<template v-if="v.giro"> · {{ v.giro }}</template>
          </p>
          <p v-if="v.telefono" class="text-sm text-dark/60">{{ v.telefono }}</p>
        </li>
        <li v-if="!visitas.data.length" class="px-4 py-8 text-center text-dark/40">Sin visitas registradas.</li>
      </ul>
    </Panel>

    <!-- Alta rápida de mostrador. -->
    <Teleport to="body">
      <div v-if="abierto" class="fixed inset-0 z-50 flex items-center justify-center bg-tinta/60 p-4" role="dialog" aria-modal="true" @click.self="abierto = false">
        <form class="w-full max-w-md border-2 border-dark bg-white" @submit.prevent="registrar">
          <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">Registrar day-pass</h2>
          <div class="space-y-3 p-4">
            <!-- Buscar si ya vino. -->
            <div class="relative">
              <label for="dp-busca" class="mb-1 block text-xs font-bold text-dark">¿Ya vino antes? Búscalo</label>
              <div class="flex items-center gap-2 border border-dark/25 px-2">
                <Search :size="14" class="text-dark/40" aria-hidden="true" />
                <input id="dp-busca" v-model="busqueda" type="text" placeholder="Nombre o teléfono" class="w-full py-2 text-sm focus:outline-none" />
              </div>
              <ul v-if="sugerencias.length" class="absolute z-10 mt-1 w-full border border-dark bg-white shadow-lg">
                <li v-for="s in sugerencias" :key="s.id">
                  <button type="button" @click="elegir(s)" class="block w-full px-3 py-2 text-left text-sm hover:bg-cream-50">
                    {{ s.nombre }} <span class="text-dark/50">· {{ s.municipio }}{{ s.telefono ? ' · ' + s.telefono : '' }}</span>
                  </button>
                </li>
              </ul>
            </div>

            <div>
              <label for="dp-nombre" class="mb-1 block text-xs font-bold text-dark">Nombre</label>
              <input id="dp-nombre" v-model="form.nombre" type="text" :required="!form.visitante_id" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-700">{{ form.errors.nombre }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label for="dp-tel" class="mb-1 block text-xs font-bold text-dark">Teléfono</label>
                <input id="dp-tel" v-model="form.telefono" type="tel" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              </div>
              <div>
                <label for="dp-mun" class="mb-1 block text-xs font-bold text-dark">Municipio</label>
                <input id="dp-mun" v-model="form.municipio" type="text" :required="!form.visitante_id" placeholder="Del interior" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
                <p v-if="form.errors.municipio" class="mt-1 text-xs text-red-700">{{ form.errors.municipio }}</p>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label for="dp-giro" class="mb-1 block text-xs font-bold text-dark">Giro / artesanía</label>
                <input id="dp-giro" v-model="form.giro" type="text" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              </div>
              <div>
                <label for="dp-ent" class="mb-1 block text-xs font-bold text-dark">¿Cómo se enteró?</label>
                <input id="dp-ent" v-model="form.como_se_entero" type="text" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="abierto = false">Cancelar</button>
            <button type="submit" :disabled="form.processing" class="min-h-[36px] border border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark">Registrar visita</button>
          </div>
        </form>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
