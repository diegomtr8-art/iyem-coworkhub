<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { Plus, Pencil, Star, CalendarClock } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'

/**
 * Catálogo de emprendimientos (Fase 4.H). Una sola tabla alimenta el directorio
 * de /actividades y el emprendedor de la semana, que rota solo.
 */
defineProps<{ emprendedores: any[]; calendario: any[] }>()

const abierto = ref(false)
const editando = ref<any | null>(null)

const form = useForm({
  nombre: '', foto: '', descripcion: '', giro: '', municipio: '',
  instagram: '', sitio_web: '', url_destino: '',
  egresado_iyem: false, activo: true, elegible_destacado: true, orden: 0,
})

function abrir(e: any | null = null) {
  editando.value = e
  form.reset(); form.clearErrors()
  if (e) {
    form.nombre = e.nombre; form.foto = e.foto ?? ''; form.descripcion = e.descripcion ?? ''
    form.giro = e.giro ?? ''; form.municipio = e.municipio ?? ''
    form.instagram = e.instagram ?? ''; form.sitio_web = e.sitio_web ?? ''; form.url_destino = e.url_destino ?? ''
    form.egresado_iyem = e.egresado_iyem; form.activo = e.activo
    form.elegible_destacado = e.elegible; form.orden = e.orden
  }
  abierto.value = true
}

function guardar() {
  const opts = { preserveScroll: true, onSuccess: () => { abierto.value = false } }
  editando.value ? form.patch(route('emprendedores.update', editando.value.id), opts) : form.post(route('emprendedores.store'), opts)
}

function fijar(e: any) {
  router.post(route('emprendedores.fijar', e.id), {}, { preserveScroll: true })
}

const fechaCorta = (iso: string) => new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' })
</script>

<template>
  <Head title="Emprendedores" />

  <AuthenticatedLayout>
    <template #breadcrumb>
      <span class="font-display font-bold text-dark">Emprendedores</span>
    </template>

    <!-- Calendario de rotación (H.3). -->
    <div class="mb-6 border-2 border-dark/15 bg-white p-4">
      <div class="mb-3 flex items-center gap-2">
        <CalendarClock :size="16" class="text-dark/60" aria-hidden="true" />
        <h2 class="font-display text-sm font-bold text-dark">Calendario de rotación</h2>
      </div>
      <div v-if="calendario.length" class="flex flex-wrap gap-2">
        <div v-for="(s, i) in calendario" :key="s.semana"
             class="border px-3 py-2 text-sm" :class="i === 0 ? 'border-dark bg-nodo-400 font-bold' : 'border-dark/20 bg-cream-50'">
          <p class="font-mono text-[0.625rem] uppercase text-dark/60">{{ i === 0 ? 'Esta semana' : 'Semana del ' + fechaCorta(s.semana) }}</p>
          <p class="text-dark">{{ s.nombre }}</p>
        </div>
      </div>
      <p v-else class="text-sm text-dark/50">No hay emprendedores elegibles para destacar. Marca alguno como elegible.</p>
    </div>

    <Panel titulo="Catálogo de emprendimientos" :contador="emprendedores.length" padding="none">
      <template #acciones>
        <button type="button" @click="abrir()" class="flex min-h-[36px] items-center gap-1.5 border-2 border-dark bg-nodo-400 px-3 font-display text-xs font-bold text-dark">
          <Plus :size="14" aria-hidden="true" /> Agregar
        </button>
      </template>

      <ul class="divide-y divide-dark/10">
        <li v-for="e in emprendedores" :key="e.id" class="flex items-center justify-between gap-4 px-4 py-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-display text-sm font-bold text-dark">{{ e.nombre }}</p>
              <span v-if="e.destacado" class="flex items-center gap-1 border border-dark bg-nodo-400 px-1.5 text-[0.625rem] uppercase text-dark"><Star :size="10" /> Esta semana</span>
              <span v-if="!e.activo" class="border border-dark/25 px-1.5 text-[0.625rem] uppercase text-dark/50">Inactivo</span>
              <span v-if="e.egresado_iyem" class="border border-dark/25 px-1.5 text-[0.625rem] uppercase text-dark/60">Egresado IYEM</span>
              <span v-if="!e.elegible" class="border border-dark/25 px-1.5 text-[0.625rem] uppercase text-dark/50">No rota</span>
            </div>
            <p class="mt-0.5 text-xs text-dark/60">
              <template v-if="e.giro">{{ e.giro }}</template>
              <template v-if="e.municipio"> · {{ e.municipio }}</template>
              <template v-if="e.instagram"> · @{{ e.instagram }}</template>
            </p>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <button v-if="!e.destacado && e.activo" type="button" @click="fijar(e)" class="flex min-h-[32px] items-center gap-1 border border-dark/25 px-2.5 font-display text-xs font-bold text-dark hover:border-dark" title="Destacar esta semana">
              <Star :size="12" aria-hidden="true" /> Fijar
            </button>
            <button type="button" @click="abrir(e)" class="flex min-h-[32px] items-center gap-1.5 border border-dark/25 px-2.5 font-display text-xs font-bold text-dark hover:border-dark">
              <Pencil :size="12" aria-hidden="true" /> Editar
            </button>
          </div>
        </li>
      </ul>
    </Panel>

    <Teleport to="body">
      <div v-if="abierto" class="fixed inset-0 z-50 flex items-center justify-center bg-tinta/60 p-4" role="dialog" aria-modal="true" @click.self="abierto = false">
        <form class="max-h-[90vh] w-full max-w-md overflow-y-auto border-2 border-dark bg-white" @submit.prevent="guardar">
          <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
            {{ editando ? 'Editar emprendimiento' : 'Agregar emprendimiento' }}
          </h2>
          <div class="space-y-3 p-4">
            <div>
              <label for="e-nombre" class="mb-1 block text-xs font-bold text-dark">Nombre</label>
              <input id="e-nombre" v-model="form.nombre" type="text" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-700">{{ form.errors.nombre }}</p>
            </div>
            <div>
              <label for="e-desc" class="mb-1 block text-xs font-bold text-dark">Descripción</label>
              <textarea id="e-desc" v-model="form.descripcion" rows="2" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label for="e-giro" class="mb-1 block text-xs font-bold text-dark">Giro</label>
                <input id="e-giro" v-model="form.giro" type="text" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              </div>
              <div>
                <label for="e-mun" class="mb-1 block text-xs font-bold text-dark">Municipio</label>
                <input id="e-mun" v-model="form.municipio" type="text" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label for="e-ig" class="mb-1 block text-xs font-bold text-dark">Instagram (usuario)</label>
                <input id="e-ig" v-model="form.instagram" type="text" placeholder="sin @" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              </div>
              <div>
                <label for="e-web" class="mb-1 block text-xs font-bold text-dark">Sitio web</label>
                <input id="e-web" v-model="form.sitio_web" type="text" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label for="e-foto" class="mb-1 block text-xs font-bold text-dark">Foto (URL)</label>
                <input id="e-foto" v-model="form.foto" type="text" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              </div>
              <div>
                <label for="e-orden" class="mb-1 block text-xs font-bold text-dark">Orden</label>
                <input id="e-orden" v-model.number="form.orden" type="number" min="0" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              </div>
            </div>
            <div class="flex flex-wrap gap-4">
              <label class="flex items-center gap-2 text-xs text-dark"><input v-model="form.activo" type="checkbox" class="h-4 w-4 rounded-none border-dark" /> Activo</label>
              <label class="flex items-center gap-2 text-xs text-dark"><input v-model="form.elegible_destacado" type="checkbox" class="h-4 w-4 rounded-none border-dark" /> Entra en la rotación</label>
              <label class="flex items-center gap-2 text-xs text-dark"><input v-model="form.egresado_iyem" type="checkbox" class="h-4 w-4 rounded-none border-dark" /> Egresado del IYEM</label>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="abierto = false">Cancelar</button>
            <button type="submit" :disabled="form.processing" class="min-h-[36px] border border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark">Guardar</button>
          </div>
        </form>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
