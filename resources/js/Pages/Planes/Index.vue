<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Plus, Check, X, Pencil, Trash2 } from 'lucide-vue-next'

interface Plan {
  id: number; nombre: string; tipo: string; precio: number
  horas_incluidas: number|null; max_reservas_mes: number|null
  acceso_24h: boolean; incluye_sala_juntas: boolean
  color: string; destacado: boolean; activo: boolean
  suscripciones_count: number
}

defineProps<{ planes: Plan[] }>()

const destroy = (id: number) => {
  if (confirm('¿Eliminar este plan?')) {
    useForm({}).delete(route('planes.destroy', id), { preserveScroll: true })
  }
}

const tipoBadge: Record<string, string> = {
  dia: 'Diario', semana: 'Semanal', mes: 'Mensual', anual: 'Anual', horas: 'Por Horas'
}
</script>

<template>
  <Head title="Planes — Nodo Admin" />
  <AuthenticatedLayout>
    <template #breadcrumb>Planes de membresía</template>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-black text-dark">Planes de membresía</h1>
          <p class="text-sm text-gray-400 mt-1">{{ planes.length }} planes configurados</p>
        </div>
        <Link :href="route('planes.create')"
          class="flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 text-dark px-4 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm">
          <Plus :size="16" /> Nuevo plan
        </Link>
      </div>

      <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <div v-for="plan in planes" :key="plan.id"
          :class="['bg-white rounded-2xl border-2 shadow-sm overflow-hidden relative flex flex-col', plan.destacado ? 'border-nodo-400' : 'border-gray-100']">
          <div v-if="plan.destacado" class="bg-nodo-400 text-dark text-xs font-black tracking-widest uppercase text-center py-1.5">
            ⭐ Más popular
          </div>
          <div class="h-1.5" :style="`background: ${plan.color}`" />

          <div class="p-5 flex-1 flex flex-col">
            <div class="mb-4">
              <span class="text-xs font-semibold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">{{ tipoBadge[plan.tipo] }}</span>
              <h3 class="text-lg font-black text-dark mt-2">{{ plan.nombre }}</h3>
              <div class="flex items-baseline gap-1 mt-1">
                <span class="text-3xl font-black text-dark">${{ Number(plan.precio).toLocaleString('es-MX') }}</span>
                <span class="text-gray-400 text-sm">MXN</span>
              </div>
            </div>

            <ul class="space-y-2 mb-4 text-sm flex-1">
              <li v-if="plan.horas_incluidas" class="flex items-center gap-2 text-gray-600">
                <Check :size="14" class="text-nodo-500 flex-shrink-0" />
                {{ plan.horas_incluidas }} horas incluidas
              </li>
              <li v-if="plan.max_reservas_mes" class="flex items-center gap-2 text-gray-600">
                <Check :size="14" class="text-nodo-500 flex-shrink-0" />
                {{ plan.max_reservas_mes }} reservas/mes
              </li>
              <li class="flex items-center gap-2" :class="plan.acceso_24h ? 'text-gray-600' : 'text-gray-300'">
                <component :is="plan.acceso_24h ? Check : X" :size="14" :class="plan.acceso_24h ? 'text-nodo-500' : 'text-gray-300'" class="flex-shrink-0" />
                Acceso 24/7
              </li>
              <li class="flex items-center gap-2" :class="plan.incluye_sala_juntas ? 'text-gray-600' : 'text-gray-300'">
                <component :is="plan.incluye_sala_juntas ? Check : X" :size="14" :class="plan.incluye_sala_juntas ? 'text-nodo-500' : 'text-gray-300'" class="flex-shrink-0" />
                Sala de juntas
              </li>
            </ul>

            <div class="flex items-center justify-between pt-3 border-t border-gray-100 mb-4">
              <span class="text-xs text-gray-400">{{ plan.suscripciones_count }} miembros</span>
              <div class="flex items-center gap-1">
                <span :class="['w-2 h-2 rounded-full', plan.activo ? 'bg-emerald-500' : 'bg-gray-300']" />
                <span class="text-xs" :class="plan.activo ? 'text-emerald-600' : 'text-gray-400'">{{ plan.activo ? 'Activo' : 'Inactivo' }}</span>
              </div>
            </div>

            <div class="flex gap-2">
              <Link :href="route('planes.edit', plan.id)"
                class="flex-1 flex items-center justify-center gap-1.5 text-sm text-gray-600 border border-gray-200 hover:border-nodo-400 hover:text-dark py-2 rounded-lg transition-all">
                <Pencil :size="13" /> Editar
              </Link>
              <button @click="destroy(plan.id)"
                class="flex items-center justify-center gap-1.5 text-sm text-red-500 border border-red-100 hover:bg-red-50 px-3 py-2 rounded-lg transition-all">
                <Trash2 :size="13" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
