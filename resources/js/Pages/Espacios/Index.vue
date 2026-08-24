<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { Plus, Pencil, Trash2, Building2 } from 'lucide-vue-next'

interface Espacio {
  id: number; nombre: string; tipo: string; capacidad: number
  precio_hora: number; amenidades: string[]; disponible: boolean; piso: number
  reservas_count: number; checkins_count: number
}

defineProps<{ espacios: Espacio[] }>()

const tipoInfo: Record<string, { emoji: string; label: string }> = {
  coworking:   { emoji: '🖥️', label: 'Coworking' },
  privado:     { emoji: '🏢', label: 'Cubículo Privado' },
  sala_juntas: { emoji: '👥', label: 'Sala de Juntas' },
  contenido:   { emoji: '🎙️', label: 'Estudio Podcast' },
  fotografia:  { emoji: '📸', label: 'Estudio Fotografía' },
  escritorio:  { emoji: '🖥️', label: 'Escritorio' },
  lounge:      { emoji: '☕', label: 'Lounge' },
}

const destroy = (id: number) => {
  if (confirm('¿Eliminar este espacio?')) {
    useForm({}).delete(route('espacios.destroy', id), { preserveScroll: true })
  }
}

const toggleDisponible = (espacio: Espacio) => {
  router.patch(route('espacios.update', espacio.id), { disponible: !espacio.disponible }, { preserveScroll: true })
}
</script>

<template>
  <Head title="Espacios — NODICO Admin" />
  <AuthenticatedLayout>
    <template #breadcrumb>Espacios</template>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-black text-dark">Espacios de trabajo</h1>
          <p class="text-sm text-gray-400 mt-1">{{ espacios.length }} espacios configurados</p>
        </div>
        <Link :href="route('espacios.create')"
          class="flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 text-dark px-4 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm">
          <Plus :size="16" /> Nuevo espacio
        </Link>
      </div>

      <div v-if="!espacios.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-10 text-center">
        <Building2 :size="40" class="mx-auto text-gray-200 mb-3" />
        <p class="text-gray-400 font-medium">No hay espacios registrados</p>
        <Link :href="route('espacios.create')" class="mt-3 inline-block text-sm text-nodo-600 font-semibold hover:text-nodo-700">Crear primer espacio →</Link>
      </div>

      <div v-else class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5">
        <div v-for="e in espacios" :key="e.id"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition-all">
          <div :class="['h-1.5', e.disponible ? 'bg-nodo-400' : 'bg-gray-300']" />
          <div class="p-5">
            <div class="flex items-start justify-between mb-3">
              <div>
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-xl">{{ tipoInfo[e.tipo]?.emoji ?? '🏢' }}</span>
                  <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">{{ tipoInfo[e.tipo]?.label ?? e.tipo }}</span>
                </div>
                <h3 class="font-bold text-dark">{{ e.nombre }}</h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ e.piso ? 'Piso ' + e.piso + ' · ' : '' }}Cap. {{ e.capacidad }} persona{{ e.capacidad !== 1 ? 's' : '' }}</p>
              </div>
              <button @click="toggleDisponible(e)"
                :class="['px-2.5 py-1 rounded-lg text-xs font-semibold transition-all',
                  e.disponible ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-gray-100 text-gray-500 hover:bg-gray-200']">
                {{ e.disponible ? 'Disponible' : 'No disponible' }}
              </button>
            </div>

            <div class="flex items-center justify-between text-sm mb-3">
              <span v-if="e.precio_hora > 0" class="font-bold text-dark">${{ Number(e.precio_hora).toLocaleString('es-MX') }}/hr</span>
              <span v-else class="text-gray-400 text-xs">Sin costo extra</span>
              <div class="flex items-center gap-3 text-xs text-gray-400">
                <span>{{ e.reservas_count }} reservas</span>
                <span>{{ e.checkins_count }} checkins</span>
              </div>
            </div>

            <div v-if="e.amenidades?.length" class="flex flex-wrap gap-1 mb-4">
              <span v-for="a in (e.amenidades ?? []).slice(0, 4)" :key="a"
                class="text-xs bg-nodo-50 text-nodo-700 px-2 py-0.5 rounded-full">{{ a }}</span>
              <span v-if="e.amenidades.length > 4" class="text-xs text-gray-400">+{{ e.amenidades.length - 4 }}</span>
            </div>

            <div class="flex gap-2">
              <Link :href="route('espacios.edit', e.id)"
                class="flex-1 flex items-center justify-center gap-1.5 text-sm text-gray-600 border border-gray-200 hover:border-nodo-400 hover:text-dark py-2 rounded-xl transition-all">
                <Pencil :size="13" /> Editar
              </Link>
              <button @click="destroy(e.id)"
                class="flex items-center gap-1.5 text-sm text-red-500 border border-red-100 hover:bg-red-50 px-3 py-2 rounded-xl transition-all">
                <Trash2 :size="13" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
