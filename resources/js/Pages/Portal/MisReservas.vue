<script setup lang="ts">
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { CalendarDays, Plus, X, Clock, CheckCircle } from 'lucide-vue-next'
import { ref, computed } from 'vue'

defineProps<{ proximas: any[]; pasadas: any[] }>()

const page = usePage()
const flash = computed(() => (page.props.flash as any) ?? {})

const activeTab = ref<'proximas' | 'pasadas'>('proximas')

const cancelar = (id: number) => {
  if (confirm('¿Cancelar esta reserva? Las horas serán devueltas a tu cuenta.')) {
    useForm({}).delete(route('portal.reservas.cancel', id), { preserveScroll: true })
  }
}

const tipoEmoji: Record<string, string> = {
  privado: '🏢', sala_juntas: '👥', contenido: '🎙️', fotografia: '📸', coworking: '🖥️',
}

function formatFecha(d: string) {
  return new Date(d + 'T00:00:00').toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' })
}
</script>

<template>
  <Head title="Mis reservas — NODICO" />
  <PortalLayout>
    <div class="space-y-6 max-w-3xl">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-black text-dark">Mis reservas</h1>
          <p class="text-sm text-gray-400 mt-0.5">Gestiona tus espacios reservados</p>
        </div>
        <Link :href="route('portal.reservar')"
          class="flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 text-dark px-4 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm">
          <Plus :size="15" /> Nueva reserva
        </Link>
      </div>

      <!-- Flash success -->
      <div v-if="flash.success" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-center gap-3">
        <CheckCircle :size="18" class="text-emerald-500 flex-shrink-0" />
        <p class="text-sm text-emerald-700 font-medium">{{ flash.success }}</p>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 bg-gray-100 rounded-xl p-1 w-fit">
        <button v-for="tab in [{ id: 'proximas', label: 'Próximas' }, { id: 'pasadas', label: 'Historial' }]" :key="tab.id"
          @click="activeTab = tab.id as any"
          :class="['px-5 py-2 text-sm font-semibold rounded-lg transition-all', activeTab === tab.id ? 'bg-white text-dark shadow-sm' : 'text-gray-500 hover:text-dark']">
          {{ tab.label }}
        </button>
      </div>

      <!-- Próximas -->
      <div v-if="activeTab === 'proximas'">
        <div v-if="!proximas.length" class="text-center py-16">
          <CalendarDays :size="48" class="mx-auto text-gray-200 mb-4" />
          <p class="font-semibold text-dark mb-1">No tienes reservas próximas</p>
          <p class="text-gray-400 text-sm mb-5">Reserva un espacio cuando lo necesites</p>
          <Link :href="route('portal.reservar')"
            class="inline-flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 text-dark font-bold px-5 py-2.5 rounded-xl text-sm transition-all">
            <Plus :size="15" /> Hacer una reserva
          </Link>
        </div>
        <div v-else class="space-y-3">
          <div v-for="r in proximas" :key="r.id"
            class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4 hover:border-nodo-200 transition-all">
            <div class="w-12 h-12 bg-nodo-50 rounded-xl flex items-center justify-center flex-shrink-0 text-2xl">
              {{ tipoEmoji[r.espacio?.tipo] ?? '🏢' }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-bold text-dark">{{ r.espacio?.nombre }}</p>
              <p class="text-sm text-gray-500 mt-0.5">{{ formatFecha(r.fecha) }}</p>
              <div class="flex items-center gap-1.5 mt-1 text-xs text-gray-400">
                <Clock :size="12" />
                {{ r.hora_inicio?.slice(0,5) }} – {{ r.hora_fin?.slice(0,5) }}
                <span class="text-gray-200">·</span>
                <span class="font-medium text-dark">
                  {{ Math.round((new Date(`2000-01-01 ${r.hora_fin}`).getTime() - new Date(`2000-01-01 ${r.hora_inicio}`).getTime()) / 3600000 * 10) / 10 }}h
                </span>
              </div>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0">
              <span class="bg-emerald-50 text-emerald-700 text-xs font-semibold px-2.5 py-1 rounded-full">Confirmada</span>
              <button @click="cancelar(r.id)"
                class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all"
                title="Cancelar reserva">
                <X :size="16" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Historial -->
      <div v-if="activeTab === 'pasadas'">
        <div v-if="!pasadas.length" class="text-center py-16">
          <Clock :size="48" class="mx-auto text-gray-200 mb-4" />
          <p class="text-gray-400">Sin historial de reservas todavía</p>
        </div>
        <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[480px]">
              <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                  <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Espacio</th>
                  <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fecha</th>
                  <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Horario</th>
                  <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Estatus</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <tr v-for="r in pasadas" :key="r.id" class="hover:bg-gray-50">
                  <td class="px-5 py-3 font-medium text-dark">{{ r.espacio?.nombre }}</td>
                  <td class="px-5 py-3 text-gray-600">{{ new Date(r.fecha).toLocaleDateString('es-MX') }}</td>
                  <td class="px-5 py-3 text-gray-600">{{ r.hora_inicio?.slice(0,5) }} – {{ r.hora_fin?.slice(0,5) }}</td>
                  <td class="px-5 py-3">
                    <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold',
                      r.estatus === 'Completada' ? 'bg-blue-50 text-blue-700' :
                      r.estatus === 'Cancelada' ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-600']">
                      {{ r.estatus }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>
