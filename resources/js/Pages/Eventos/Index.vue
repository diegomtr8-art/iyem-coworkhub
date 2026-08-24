<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Plus, Pencil, Trash2, CalendarDays, Users, Eye, EyeOff } from 'lucide-vue-next'

defineProps<{ proximos: any[]; pasados: any[] }>()

const destroy = (id: number) => {
  if (confirm('¿Eliminar este evento?')) {
    useForm({}).delete(route('eventos.admin.destroy', id), { preserveScroll: true })
  }
}

function formatFecha(d: string) {
  return new Date(d).toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <Head title="Eventos — NODICO Admin" />
  <AuthenticatedLayout>
    <template #breadcrumb>Eventos</template>

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-black text-dark">Eventos y actividades</h1>
          <p class="text-sm text-gray-400 mt-1">Gestiona los eventos de la comunidad NODICO</p>
        </div>
        <Link :href="route('eventos.admin.create')"
          class="flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 text-dark px-4 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm">
          <Plus :size="16" /> Nuevo evento
        </Link>
      </div>

      <!-- Próximos -->
      <div>
        <h2 class="font-bold text-dark text-sm uppercase tracking-wide text-gray-500 mb-3">Próximos eventos</h2>
        <div v-if="!proximos.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-10 text-center">
          <CalendarDays :size="40" class="mx-auto text-gray-200 mb-3" />
          <p class="text-gray-400">No hay eventos próximos. <Link :href="route('eventos.admin.create')" class="text-nodo-600 font-medium">Crear uno →</Link></p>
        </div>
        <div v-else class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
          <div v-for="e in proximos" :key="e.id"
            class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition-all">
            <div class="bg-nodo-50 border-b border-nodo-100 px-4 py-3 flex items-center justify-between">
              <span class="text-sm font-semibold text-nodo-700 flex items-center gap-1.5">
                <CalendarDays :size="14" />{{ formatFecha(e.fecha) }}
              </span>
              <div class="flex items-center gap-2">
                <span v-if="e.solo_miembros" class="text-xs bg-dark text-nodo-400 px-2 py-0.5 rounded-full">Solo miembros</span>
                <component :is="e.activo ? Eye : EyeOff" :size="14" :class="e.activo ? 'text-emerald-500' : 'text-gray-400'" />
              </div>
            </div>
            <div class="p-4">
              <h3 class="font-black text-dark">{{ e.titulo }}</h3>
              <p v-if="e.descripcion" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ e.descripcion }}</p>
              <div class="flex items-center gap-3 mt-3 text-xs text-gray-400">
                <span v-if="e.hora_inicio">🕐 {{ e.hora_inicio?.slice(0,5) }}{{ e.hora_fin ? ' – ' + e.hora_fin?.slice(0,5) : '' }}</span>
                <span v-if="e.cupo_maximo" class="flex items-center gap-1"><Users :size="12" /> {{ e.cupo_maximo }}</span>
                <span v-if="e.precio > 0" class="font-semibold text-dark">${{ Number(e.precio).toLocaleString('es-MX') }}</span>
                <span v-else class="text-emerald-600 font-medium">Gratis</span>
              </div>
              <div class="flex gap-2 mt-4 pt-3 border-t border-gray-100">
                <Link :href="route('eventos.admin.edit', e.id)"
                  class="flex-1 flex items-center justify-center gap-1.5 text-xs text-gray-600 border border-gray-200 hover:border-nodo-400 hover:text-dark py-2 rounded-lg transition-all">
                  <Pencil :size="12" /> Editar
                </Link>
                <button @click="destroy(e.id)"
                  class="flex items-center gap-1.5 text-xs text-red-500 border border-red-100 hover:bg-red-50 px-3 py-2 rounded-lg transition-all">
                  <Trash2 :size="12" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pasados -->
      <div v-if="pasados.length">
        <h2 class="font-bold text-dark text-sm uppercase tracking-wide text-gray-500 mb-3">Eventos pasados</h2>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Evento</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fecha</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Precio</th>
                <th class="px-5 py-3" />
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="e in pasados" :key="e.id" class="opacity-70 hover:opacity-100 transition-opacity">
                <td class="px-5 py-3 font-medium text-dark">{{ e.titulo }}</td>
                <td class="px-5 py-3 text-gray-500">{{ formatFecha(e.fecha) }}</td>
                <td class="px-5 py-3 text-gray-500">{{ e.precio > 0 ? '$' + Number(e.precio).toLocaleString('es-MX') : 'Gratis' }}</td>
                <td class="px-5 py-3 text-right">
                  <button @click="destroy(e.id)" class="text-xs text-red-400 hover:text-red-600">
                    <Trash2 :size="13" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
