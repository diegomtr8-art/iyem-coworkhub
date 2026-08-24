<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import { BarChart3 } from 'lucide-vue-next'

const props = defineProps<{
  ingresosPorMes: { mes: string; total: number }[]
  distribucionPlanes: { plan: string; total: number }[]
  topEspacios: { nombre: string; tipo: string; reservas_count: number }[]
}>()

const maxIngreso = Math.max(...props.ingresosPorMes.map(i => i.total), 1)
const totalMiembros = props.distribucionPlanes.reduce((a, c) => a + c.total, 0)
const maxReservas = Math.max(...props.topEspacios.map(e => e.reservas_count), 1)

function fmt(n: number) {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(n)
}

const planColors = ['bg-violet-500', 'bg-blue-500', 'bg-emerald-500', 'bg-amber-500', 'bg-pink-500']
</script>

<template>
  <Head title="Reportes — CoworkHub" />
  <AuthenticatedLayout>
    <template #breadcrumb>Reportes</template>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Reportes</h1>

      <div class="grid lg:grid-cols-2 gap-6">
        <!-- Ingresos por mes -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <h2 class="font-semibold text-gray-900 mb-6 flex items-center gap-2">
            <BarChart3 :size="18" class="text-violet-600" /> Ingresos últimos 6 meses
          </h2>
          <div class="flex items-end gap-2 h-48">
            <div v-for="item in ingresosPorMes" :key="item.mes"
              class="flex-1 flex flex-col items-center gap-2">
              <span class="text-xs text-gray-500 font-medium">{{ fmt(item.total) }}</span>
              <div class="w-full bg-violet-100 rounded-t-lg relative overflow-hidden"
                :style="`height: ${Math.max((item.total / maxIngreso) * 160, 4)}px`">
                <div class="absolute inset-0 bg-violet-500 rounded-t-lg" />
              </div>
              <span class="text-xs text-gray-500">{{ item.mes }}</span>
            </div>
          </div>
        </div>

        <!-- Distribución de planes -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <h2 class="font-semibold text-gray-900 mb-6">Distribución de planes activos</h2>
          <div v-if="!distribucionPlanes.length" class="text-center text-gray-400 py-12">Sin datos</div>
          <div v-else class="space-y-4">
            <div v-for="(p, i) in distribucionPlanes" :key="p.plan" class="flex items-center gap-3">
              <div :class="['w-3 h-3 rounded-full flex-shrink-0', planColors[i % planColors.length]]" />
              <div class="flex-1 min-w-0">
                <div class="flex justify-between text-sm mb-1">
                  <span class="font-medium text-gray-900 truncate">{{ p.plan }}</span>
                  <span class="text-gray-500 flex-shrink-0">{{ p.total }} miembros</span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                  <div :class="['h-full rounded-full', planColors[i % planColors.length]]"
                    :style="`width: ${totalMiembros > 0 ? (p.total / totalMiembros * 100) : 0}%`" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Top espacios -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 lg:col-span-2">
          <h2 class="font-semibold text-gray-900 mb-6">Top espacios más reservados</h2>
          <div v-if="!topEspacios.length" class="text-center text-gray-400 py-8">Sin datos</div>
          <div v-else class="space-y-4">
            <div v-for="(e, i) in topEspacios" :key="e.nombre" class="flex items-center gap-4">
              <div class="w-8 h-8 bg-violet-50 text-violet-700 rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0">
                {{ i + 1 }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex justify-between text-sm mb-1">
                  <span class="font-medium text-gray-900">{{ e.nombre }}</span>
                  <span class="text-gray-500">{{ e.reservas_count }} reservas</span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                  <div class="h-full bg-violet-500 rounded-full"
                    :style="`width: ${(e.reservas_count / maxReservas) * 100}%`" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
