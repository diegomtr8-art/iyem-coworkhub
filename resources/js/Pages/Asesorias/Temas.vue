<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Plus, Pencil } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'

/**
 * Catálogo de temas de asesoría IYEM (Fase 4.D.1).
 *
 * Los temas se sembraron como punto de partida y **sin validar**: son los
 * servicios del IYEM, que tiene que revisarlos. `validado_iyem` distingue los
 * que ya aprobó.
 */
defineProps<{ temas: any[]; categorias: any[]; hayProvisionales: boolean }>()

const editando = ref<any | null>(null)
const abierto = ref(false)

const form = useForm({
  nombre: '', descripcion_corta: '', categoria: 'basicos',
  duracion_min: 60, activo: true, validado_iyem: false, orden: 0,
})

function abrir(tema: any | null = null) {
  editando.value = tema
  form.reset()
  form.clearErrors()
  if (tema) {
    form.nombre = tema.nombre
    form.descripcion_corta = tema.descripcion_corta ?? ''
    form.categoria = tema.categoria
    form.duracion_min = tema.duracion_min
    form.activo = tema.activo
    form.validado_iyem = tema.validado_iyem
    form.orden = tema.orden
  }
  abierto.value = true
}

function guardar() {
  const opciones = { preserveScroll: true, onSuccess: () => { abierto.value = false } }
  editando.value
    ? form.patch(route('temas.update', editando.value.id), opciones)
    : form.post(route('temas.store'), opciones)
}

const etiquetaCategoria = (v: string) => (v === 'basicos' ? 'Básicos' : 'Especializados')
</script>

<template>
  <Head title="Temas de asesoría" />

  <AuthenticatedLayout>
    <template #breadcrumb>
      <Link :href="route('asesorias.index')" class="hover:text-dark">Asesorías</Link>
      <span class="font-display font-bold text-dark">· Catálogo de temas</span>
    </template>

    <Panel titulo="Temas de asesoría IYEM" :contador="temas.length" padding="none">
      <template #acciones>
        <button
          type="button" @click="abrir()"
          class="flex min-h-[36px] items-center gap-1.5 border-2 border-dark bg-nodo-400 px-3
                 font-display text-xs font-bold text-dark focus-visible:outline focus-visible:outline-2
                 focus-visible:outline-offset-2 focus-visible:outline-dark"
        ><Plus :size="14" aria-hidden="true" /> Agregar tema</button>
      </template>

      <div v-if="hayProvisionales" class="border-b border-amber-300 bg-amber-50 px-4 py-2.5 text-xs text-amber-900">
        Parte de esta oferta se sembró como punto de partida y sigue <strong>pendiente de que el IYEM la valide</strong>.
        Marca cada tema como validado cuando lo aprueben.
      </div>

      <ul class="divide-y divide-dark/10">
        <li v-for="t in temas" :key="t.id" class="flex items-center justify-between gap-4 px-4 py-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-display text-sm font-bold text-dark">{{ t.nombre }}</p>
              <span class="border border-dark/25 px-1.5 text-[0.625rem] uppercase text-dark/60">{{ etiquetaCategoria(t.categoria) }}</span>
              <span v-if="!t.activo" class="border border-dark/25 px-1.5 text-[0.625rem] uppercase text-dark/50">Inactivo</span>
              <span v-if="!t.validado_iyem" class="border border-amber-400 bg-amber-50 px-1.5 text-[0.625rem] uppercase text-amber-800">Sin validar</span>
            </div>
            <p v-if="t.descripcion_corta" class="mt-0.5 text-xs text-dark/60">{{ t.descripcion_corta }}</p>
          </div>
          <div class="flex shrink-0 items-center gap-3">
            <span class="font-mono text-[0.6875rem] text-dark/50">{{ t.duracion_min }} min · {{ t.asesores }} asesor{{ t.asesores === 1 ? '' : 'es' }}</span>
            <button
              type="button" @click="abrir(t)"
              class="flex min-h-[32px] items-center gap-1.5 border border-dark/25 px-2.5 font-display text-xs font-bold text-dark hover:border-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
            ><Pencil :size="12" aria-hidden="true" /> Editar</button>
          </div>
        </li>
      </ul>
    </Panel>

    <Teleport to="body">
      <div v-if="abierto" class="fixed inset-0 z-50 flex items-center justify-center bg-tinta/60 p-4" role="dialog" aria-modal="true" @click.self="abierto = false">
        <form class="w-full max-w-md border-2 border-dark bg-white" @submit.prevent="guardar">
          <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
            {{ editando ? 'Editar tema' : 'Agregar tema' }}
          </h2>
          <div class="space-y-3 p-4">
            <div>
              <label for="t-nombre" class="mb-1 block text-xs font-bold text-dark">Nombre</label>
              <input id="t-nombre" v-model="form.nombre" type="text" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-700">{{ form.errors.nombre }}</p>
            </div>
            <div>
              <label for="t-desc" class="mb-1 block text-xs font-bold text-dark">Descripción corta</label>
              <input id="t-desc" v-model="form.descripcion_corta" type="text" maxlength="255" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label for="t-cat" class="mb-1 block text-xs font-bold text-dark">Categoría</label>
                <select id="t-cat" v-model="form.categoria" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark">
                  <option v-for="c in categorias" :key="c.valor" :value="c.valor">{{ c.etiqueta }}</option>
                </select>
              </div>
              <div>
                <label for="t-dur" class="mb-1 block text-xs font-bold text-dark">Duración (min)</label>
                <input id="t-dur" v-model.number="form.duracion_min" type="number" min="15" max="480" step="15" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              </div>
            </div>
            <div class="flex flex-wrap gap-4">
              <label class="flex items-center gap-2 text-xs text-dark">
                <input v-model="form.activo" type="checkbox" class="h-4 w-4 rounded-none border-dark" /> Activo
              </label>
              <label class="flex items-center gap-2 text-xs text-dark">
                <input v-model="form.validado_iyem" type="checkbox" class="h-4 w-4 rounded-none border-dark" /> Validado por el IYEM
              </label>
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
