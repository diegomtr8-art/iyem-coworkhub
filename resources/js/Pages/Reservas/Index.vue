<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { CalendarDays, Filter, MoreHorizontal } from 'lucide-vue-next'
import { ref, reactive, watch } from 'vue'
import debounce from 'lodash/debounce'

defineProps<{
  reservas: any
  espacios: any[]
  miembros: any[]
  filters: any
}>()

const filters = reactive({
  fecha: '',
  espacio_id: '',
  estatus: '',
  user_id: '',
})

const applyFilters = () => {
  router.get(route('reservas.index'), filters, { preserveState: true, replace: true })
}
watch(filters, debounce(applyFilters, 300))

function updateEstatus(reserva: any, estatus: string) {
  useForm({ estatus }).patch(route('reservas.update', reserva.id), { preserveScroll: true })
}

const estatusBadge: Record<string, string> = {
  Confirmada: 'bg-emerald-50 text-emerald-700',
  Cancelada: 'bg-red-50 text-red-600',
  Completada: 'bg-blue-50 text-blue-700',
  No_Show: 'bg-gray-100 text-gray-600',
}
</script>

<template>
  <Head title="Reservas — CoworkHub" />
  <AuthenticatedLayout>
    <template #breadcrumb>Reservas</template>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Reservas</h1>

      <!-- Filtros -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <div class="grid sm:grid-cols-4 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Fecha</label>
            <input v-model="filters.fecha" type="date" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Espacio</label>
            <select v-model="filters.espacio_id" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
              <option value="">Todos</option>
              <option v-for="e in espacios" :key="e.id" :value="e.id">{{ e.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Estatus</label>
            <select v-model="filters.estatus" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
              <option value="">Todos</option>
              <option>Confirmada</option><option>Cancelada</option><option>Completada</option><option>No_Show</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Miembro</label>
            <select v-model="filters.user_id" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
              <option value="">Todos</option>
              <option v-for="m in miembros" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[700px]">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Miembro</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Espacio</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fecha</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Horario</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Estatus</th>
              <th class="px-5 py-3" />
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="!reservas.data?.length">
              <td colspan="6" class="py-8 text-center text-gray-400">Sin reservas encontradas</td>
            </tr>
            <tr v-for="r in reservas.data" :key="r.id" class="hover:bg-gray-50">
              <td class="px-5 py-3">
                <div class="font-medium text-gray-900">{{ r.user?.name }}</div>
                <div class="text-xs text-gray-400">{{ r.user?.email }}</div>
              </td>
              <td class="px-5 py-3 text-gray-600">{{ r.espacio?.nombre }}</td>
              <td class="px-5 py-3 text-gray-600">{{ new Date(r.fecha).toLocaleDateString('es-MX') }}</td>
              <td class="px-5 py-3 text-gray-600">{{ r.hora_inicio?.slice(0,5) }} – {{ r.hora_fin?.slice(0,5) }}</td>
              <td class="px-5 py-3">
                <span :class="['px-2.5 py-1 rounded-full text-xs font-medium', estatusBadge[r.estatus] ?? 'bg-gray-100 text-gray-600']">{{ r.estatus }}</span>
              </td>
              <td class="px-5 py-3 text-right">
                <select @change="updateEstatus(r, ($event.target as HTMLSelectElement).value)"
                  :value="r.estatus"
                  class="text-xs border border-gray-200 rounded-lg px-2 py-1 focus:ring-1 focus:ring-violet-500 outline-none">
                  <option>Confirmada</option>
                  <option>Cancelada</option>
                  <option>Completada</option>
                  <option>No_Show</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
        </div>

        <div v-if="reservas.links?.length > 3" class="px-5 py-3 border-t border-gray-100 flex justify-end gap-1">
          <template v-for="link in reservas.links" :key="link.label">
            <Link v-if="link.url" :href="link.url"
              :class="['px-3 py-1.5 text-xs rounded-lg', link.active ? 'bg-nodo-400 text-dark font-bold' : 'text-gray-600 hover:bg-gray-100']"
              v-html="link.label" />
            <span v-else class="px-3 py-1.5 text-xs text-gray-300" v-html="link.label" />
          </template>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
