<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import { Users, CalendarDays, Clock, DollarSign, BarChart3, Receipt } from 'lucide-vue-next'

interface Kpis {
  miembros_activos: number
  reservas_hoy: number
  checkins_activos: number
  ingresos_mes: number
  ocupacion_pct: number
  facturas_pendientes: number
}

const props = defineProps<{
  kpis: Kpis
  reservasHoy: any[]
  checkinsActuales: any[]
}>()

function fmt(n: number) {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(n)
}
function hora(dt: string) {
  return new Date(dt).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <Head title="Dashboard — Nodo Admin" />
  <AuthenticatedLayout>
    <template #breadcrumb>Dashboard</template>

    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-black text-dark">Dashboard</h1>
        <p class="text-sm text-gray-500 mt-1">Resumen operativo en tiempo real</p>
      </div>

      <!-- KPIs -->
      <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="kpi in [
          { label: 'Miembros activos',    value: kpis.miembros_activos,    icon: Users,        border: 'border-l-nodo-400' },
          { label: 'Reservas hoy',        value: kpis.reservas_hoy,        icon: CalendarDays, border: 'border-l-blue-400' },
          { label: 'Check-ins activos',   value: kpis.checkins_activos,    icon: Clock,        border: 'border-l-emerald-400' },
          { label: 'Ingresos del mes',    value: fmt(kpis.ingresos_mes),   icon: DollarSign,   border: 'border-l-nodo-400' },
          { label: 'Ocupación',           value: kpis.ocupacion_pct + '%', icon: BarChart3,    border: 'border-l-indigo-400' },
          { label: 'Facturas pendientes', value: kpis.facturas_pendientes, icon: Receipt,      border: 'border-l-red-400' },
        ]" :key="kpi.label"
          :class="['bg-white rounded-2xl p-5 shadow-sm border border-gray-100 border-l-4', kpi.border]">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ kpi.label }}</span>
            <component :is="kpi.icon" :size="18" class="text-gray-300" />
          </div>
          <div class="text-2xl font-black text-dark">{{ kpi.value }}</div>
        </div>
      </div>

      <div class="grid lg:grid-cols-2 gap-6">
        <!-- Reservas hoy -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
          <div class="p-5 border-b border-gray-100 flex items-center gap-2">
            <div class="w-2 h-2 bg-nodo-400 rounded-full" />
            <h2 class="font-bold text-dark">Reservas de hoy</h2>
          </div>
          <div class="divide-y divide-gray-50">
            <div v-if="!reservasHoy.length" class="p-5 text-sm text-gray-400 text-center">Sin reservas para hoy</div>
            <div v-for="r in reservasHoy" :key="r.id" class="px-5 py-3 flex items-center gap-4">
              <div class="w-8 h-8 bg-nodo-400 rounded-full flex items-center justify-center text-xs font-black uppercase text-dark flex-shrink-0">
                {{ r.user?.name?.charAt(0) }}
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-dark truncate">{{ r.user?.name }}</p>
                <p class="text-xs text-gray-400">{{ r.espacio?.nombre }}</p>
              </div>
              <div class="text-xs text-right flex-shrink-0">
                <div class="text-gray-500">{{ r.hora_inicio?.slice(0,5) }} – {{ r.hora_fin?.slice(0,5) }}</div>
                <span :class="[
                  'px-2 py-0.5 rounded-full font-semibold text-xs',
                  r.estatus === 'Confirmada' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'
                ]">{{ r.estatus }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Checkins activos -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
          <div class="p-5 border-b border-gray-100 flex items-center gap-2">
            <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse" />
            <h2 class="font-bold text-dark">Check-ins activos</h2>
          </div>
          <div class="divide-y divide-gray-50">
            <div v-if="!checkinsActuales.length" class="p-5 text-sm text-gray-400 text-center">Sin check-ins activos</div>
            <div v-for="c in checkinsActuales" :key="c.id" class="px-5 py-3 flex items-center gap-4">
              <div class="w-8 h-8 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center text-xs font-black uppercase flex-shrink-0">
                {{ c.user?.name?.charAt(0) }}
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-dark truncate">{{ c.user?.name }}</p>
                <p class="text-xs text-gray-400">{{ c.espacio?.nombre }}</p>
              </div>
              <div class="text-xs text-right flex-shrink-0">
                <div class="text-gray-500">Desde {{ hora(c.hora_entrada) }}</div>
                <div class="flex items-center gap-1 justify-end mt-0.5">
                  <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse" />
                  <span class="text-emerald-600 font-semibold">Activo</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
