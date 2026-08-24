<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Plus, X, Pencil, Trash2, Megaphone } from 'lucide-vue-next'
import { ref } from 'vue'

defineProps<{ anuncios: any[] }>()

const showModal = ref(false)
const editing = ref<any>(null)

const form = useForm({
  titulo: '', contenido: '', tipo: 'general',
  fecha_inicio: new Date().toISOString().split('T')[0],
  fecha_fin: '', activo: true,
})

const openCreate = () => {
  editing.value = null
  form.reset()
  form.fecha_inicio = new Date().toISOString().split('T')[0]
  form.activo = true
  showModal.value = true
}

const openEdit = (a: any) => {
  editing.value = a
  form.titulo = a.titulo
  form.contenido = a.contenido
  form.tipo = a.tipo
  form.fecha_inicio = a.fecha_inicio
  form.fecha_fin = a.fecha_fin ?? ''
  form.activo = a.activo
  showModal.value = true
}

const submit = () => {
  if (editing.value) {
    form.put(route('anuncios.update', editing.value.id), { onSuccess: () => { showModal.value = false } })
  } else {
    form.post(route('anuncios.store'), { onSuccess: () => { showModal.value = false } })
  }
}

const destroy = (id: number) => {
  if (confirm('¿Eliminar este anuncio?')) useForm({}).delete(route('anuncios.destroy', id), { preserveScroll: true })
}

const tipoBadge: Record<string, string> = {
  general: 'bg-blue-50 text-blue-700',
  oferta: 'bg-emerald-50 text-emerald-700',
  evento: 'bg-violet-50 text-violet-700',
  mantenimiento: 'bg-orange-50 text-orange-700',
}
</script>

<template>
  <Head title="Anuncios — CoworkHub" />
  <AuthenticatedLayout>
    <template #breadcrumb>Anuncios</template>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Anuncios</h1>
        <button @click="openCreate"
          class="flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-all">
          <Plus :size="16" /> Nuevo anuncio
        </button>
      </div>

      <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <div v-if="!anuncios.length" class="col-span-full text-center py-12 text-gray-400">
          <Megaphone :size="48" class="mx-auto mb-3 opacity-30" />
          <p>Sin anuncios publicados</p>
        </div>
        <div v-for="a in anuncios" :key="a.id"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <div class="flex items-start justify-between mb-3">
            <span :class="['text-xs font-medium px-2.5 py-1 rounded-full', tipoBadge[a.tipo] ?? 'bg-gray-100 text-gray-600']">
              {{ a.tipo.charAt(0).toUpperCase() + a.tipo.slice(1) }}
            </span>
            <div class="flex items-center gap-1">
              <span :class="['w-2 h-2 rounded-full', a.activo ? 'bg-emerald-500' : 'bg-gray-300']" />
              <span class="text-xs" :class="a.activo ? 'text-emerald-600' : 'text-gray-400'">
                {{ a.activo ? 'Activo' : 'Inactivo' }}
              </span>
            </div>
          </div>
          <h3 class="font-semibold text-gray-900 mb-1">{{ a.titulo }}</h3>
          <p class="text-sm text-gray-600 line-clamp-2 mb-3">{{ a.contenido }}</p>
          <p class="text-xs text-gray-400 mb-3">
            Desde {{ new Date(a.fecha_inicio).toLocaleDateString('es-MX') }}
            <template v-if="a.fecha_fin"> hasta {{ new Date(a.fecha_fin).toLocaleDateString('es-MX') }}</template>
          </p>
          <div class="flex gap-2">
            <button @click="openEdit(a)"
              class="flex-1 flex items-center justify-center gap-1.5 text-xs text-gray-600 border border-gray-200 hover:border-violet-300 hover:text-violet-700 py-2 rounded-lg transition-all">
              <Pencil :size="12" /> Editar
            </button>
            <button @click="destroy(a.id)"
              class="flex items-center gap-1.5 text-xs text-red-500 border border-red-100 hover:bg-red-50 px-3 py-2 rounded-lg transition-all">
              <Trash2 :size="12" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900">{{ editing ? 'Editar anuncio' : 'Nuevo anuncio' }}</h3>
          <button @click="showModal = false" class="text-gray-400 hover:text-gray-600"><X :size="18" /></button>
        </div>
        <form @submit.prevent="submit" class="p-5 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Título *</label>
            <input v-model="form.titulo" type="text" required class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Contenido *</label>
            <textarea v-model="form.contenido" required rows="3" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none resize-none" />
          </div>
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
              <select v-model="form.tipo" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <option value="general">General</option>
                <option value="oferta">Oferta</option>
                <option value="evento">Evento</option>
                <option value="mantenimiento">Mantenimiento</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Inicio *</label>
              <input v-model="form.fecha_inicio" type="date" required class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fin</label>
              <input v-model="form.fecha_fin" type="date" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none" />
            </div>
          </div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.activo" class="rounded text-violet-600" />
            <span class="text-sm text-gray-700">Publicar anuncio</span>
          </label>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showModal = false" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50">Cancelar</button>
            <button type="submit" :disabled="form.processing" class="px-5 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl disabled:opacity-50">
              {{ editing ? 'Actualizar' : 'Publicar' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
