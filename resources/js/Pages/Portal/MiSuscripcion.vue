<script setup lang="ts">
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Head } from '@inertiajs/vue3'
import { CreditCard, Clock, Calendar } from 'lucide-vue-next'

const props = defineProps<{
  suscripcion: any
  historial: any[]
  horasUsadas: number
}>()

const diasRestantes = props.suscripcion
  ? Math.max(0, Math.ceil((new Date(props.suscripcion.fecha_fin).getTime() - Date.now()) / 86400000))
  : 0

const diasTotales = props.suscripcion
  ? Math.ceil((new Date(props.suscripcion.fecha_fin).getTime() - new Date(props.suscripcion.fecha_inicio).getTime()) / 86400000)
  : 1

const progresoPct = Math.max(0, Math.min(100, 100 - (diasRestantes / diasTotales) * 100))

function formatDate(d: string) {
  return d ? new Date(d).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' }) : '—'
}
</script>

<template>
  <Head title="Mi suscripción — CoworkHub" />
  <PortalLayout>
    <div class="max-w-2xl mx-auto space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Mi suscripción</h1>

      <!-- Plan activo -->
      <div v-if="suscripcion" class="bg-gradient-to-br from-violet-600 to-violet-800 text-white rounded-2xl p-6 shadow-sm">
        <div class="flex items-start justify-between mb-6">
          <div>
            <p class="text-violet-200 text-sm mb-1">Plan activo</p>
            <h2 class="text-2xl font-bold">{{ suscripcion.plan?.nombre }}</h2>
            <p class="text-violet-200 text-sm mt-1">
              ${{ Number(suscripcion.precio_pagado).toLocaleString('es-MX') }} MXN
            </p>
          </div>
          <div class="bg-white/20 px-3 py-1.5 rounded-full">
            <span class="text-sm font-semibold">Activa</span>
          </div>
        </div>

        <!-- Progreso días -->
        <div class="mb-4">
          <div class="flex justify-between text-sm mb-2">
            <span class="text-violet-200">Días utilizados</span>
            <span class="font-semibold">{{ diasTotales - diasRestantes }} de {{ diasTotales }} días</span>
          </div>
          <div class="h-3 bg-white/20 rounded-full overflow-hidden">
            <div class="h-full bg-white rounded-full transition-all" :style="`width: ${progresoPct}%`" />
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div class="bg-white/10 rounded-xl p-3 text-center">
            <div class="text-2xl font-black">{{ diasRestantes }}</div>
            <div class="text-xs text-violet-200">Días restantes</div>
          </div>
          <div class="bg-white/10 rounded-xl p-3 text-center">
            <div class="text-2xl font-black">{{ horasUsadas }}h</div>
            <div class="text-xs text-violet-200">Horas este mes</div>
          </div>
          <div class="bg-white/10 rounded-xl p-3 text-center">
            <div class="text-2xl font-black">{{ suscripcion.auto_renovar ? 'Sí' : 'No' }}</div>
            <div class="text-xs text-violet-200">Auto renovar</div>
          </div>
        </div>
      </div>

      <!-- Sin suscripción -->
      <div v-else class="bg-yellow-50 border border-yellow-200 rounded-2xl p-8 text-center">
        <CreditCard :size="48" class="mx-auto mb-3 text-yellow-400" />
        <h2 class="font-semibold text-yellow-800">Sin suscripción activa</h2>
        <p class="text-yellow-600 text-sm mt-2">Contacta a recepción para activar tu membresía</p>
      </div>

      <!-- Detalles -->
      <div v-if="suscripcion" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h3 class="font-semibold text-gray-900 mb-4">Detalles del plan</h3>
        <dl class="space-y-3 text-sm">
          <div class="flex justify-between">
            <dt class="text-gray-500 flex items-center gap-2"><Calendar :size="15" /> Inicio</dt>
            <dd class="font-medium text-gray-900">{{ formatDate(suscripcion.fecha_inicio) }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-500 flex items-center gap-2"><Calendar :size="15" /> Vencimiento</dt>
            <dd class="font-medium text-gray-900">{{ formatDate(suscripcion.fecha_fin) }}</dd>
          </div>
          <div v-if="suscripcion.plan?.horas_incluidas" class="flex justify-between">
            <dt class="text-gray-500 flex items-center gap-2"><Clock :size="15" /> Horas incluidas</dt>
            <dd class="font-medium text-gray-900">{{ suscripcion.plan.horas_incluidas }} horas</dd>
          </div>
          <div v-if="suscripcion.plan?.max_reservas_mes" class="flex justify-between">
            <dt class="text-gray-500">Reservas por mes</dt>
            <dd class="font-medium text-gray-900">{{ suscripcion.plan.max_reservas_mes }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-500">Acceso 24/7</dt>
            <dd :class="['font-medium', suscripcion.plan?.acceso_24h ? 'text-emerald-600' : 'text-gray-400']">
              {{ suscripcion.plan?.acceso_24h ? 'Incluido' : 'No incluido' }}
            </dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-500">Sala de juntas</dt>
            <dd :class="['font-medium', suscripcion.plan?.incluye_sala_juntas ? 'text-emerald-600' : 'text-gray-400']">
              {{ suscripcion.plan?.incluye_sala_juntas ? 'Incluida' : 'No incluida' }}
            </dd>
          </div>
        </dl>
      </div>

      <!-- Historial -->
      <div v-if="historial.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <div class="p-5 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900 text-sm">Historial de suscripciones</h3>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-for="s in historial" :key="s.id" class="px-5 py-3 flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-900">{{ s.plan?.nombre }}</p>
              <p class="text-xs text-gray-400">{{ formatDate(s.fecha_inicio) }} – {{ formatDate(s.fecha_fin) }}</p>
            </div>
            <span :class="['px-2.5 py-1 rounded-full text-xs font-medium',
              s.estatus === 'Vencida' ? 'bg-gray-100 text-gray-600' : 'bg-red-50 text-red-600']">
              {{ s.estatus }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>
