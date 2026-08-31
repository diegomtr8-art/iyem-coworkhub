<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { reactive, watch } from 'vue'
import debounce from 'lodash/debounce'
import ListaResponsiva, { type Columna } from '@/Components/Panel/ListaResponsiva.vue'
import Paginacion from '@/Components/Panel/Paginacion.vue'

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

const fecha = (iso: string) => new Date(iso).toLocaleDateString('es-MX')

/**
 * En el mostrador, de una reserva se mira: quién, qué espacio, cuándo y en qué
 * estado. Por eso espacio, fecha, horario y estatus van siempre visibles en la
 * tarjeta —el horario y el estado, en particular, sin abrir nada—. El orden es
 * el de la tabla en escritorio, que queda igual.
 */
const columnas: Columna[] = [
  { clave: 'miembro', etiqueta: 'Miembro', rol: 'identidad', clase: 'px-5' },
  { clave: 'espacio', etiqueta: 'Espacio', rol: 'resumen' },
  { clave: 'fecha', etiqueta: 'Fecha', rol: 'resumen' },
  { clave: 'horario', etiqueta: 'Horario', rol: 'resumen' },
  { clave: 'estatus', etiqueta: 'Estatus', rol: 'resumen', sinEtiqueta: true },
  { clave: 'acciones', etiqueta: 'Acción', rol: 'resumen', clase: 'text-right' },
]
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

      <!-- Lista: tabla en escritorio, tarjetas apiladas por debajo de lg. -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <ListaResponsiva
          :columnas="columnas"
          :filas="reservas.data ?? []"
          vacio="Sin reservas encontradas"
        >
          <template #miembro="{ fila: r }">
            <span class="font-medium text-gray-900">{{ r.user?.name }}</span>
            <span class="block text-xs font-normal text-gray-400">{{ r.user?.email }}</span>
          </template>

          <template #espacio="{ fila: r }">{{ r.espacio?.nombre }}</template>

          <template #fecha="{ fila: r }">{{ fecha(r.fecha) }}</template>

          <template #horario="{ fila: r }">{{ r.hora_inicio?.slice(0,5) }} – {{ r.hora_fin?.slice(0,5) }}</template>

          <template #estatus="{ fila: r }">
            <span :class="['inline-block px-2.5 py-1 rounded-full text-xs font-medium', estatusBadge[r.estatus] ?? 'bg-gray-100 text-gray-600']">{{ r.estatus }}</span>
          </template>

          <template #acciones="{ fila: r }">
            <select
              :value="r.estatus"
              aria-label="Cambiar estatus de la reserva"
              class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-violet-500 outline-none"
              @click.stop
              @change="updateEstatus(r, ($event.target as HTMLSelectElement).value)"
            >
              <option>Confirmada</option>
              <option>Cancelada</option>
              <option>Completada</option>
              <option>No_Show</option>
            </select>
          </template>
        </ListaResponsiva>

        <Paginacion v-if="reservas.links?.length > 3" :links="reservas.links" />
      </div>
    </div>
  </AuthenticatedLayout>
</template>
