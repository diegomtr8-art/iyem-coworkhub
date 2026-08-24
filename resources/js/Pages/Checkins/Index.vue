<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { LogIn, LogOut, Clock } from 'lucide-vue-next'
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps<{
  activos: any[]
  historial: any[]
  espacios: any[]
  miembros: any[]
}>()

const form = useForm({ user_id: '', espacio_id: '' })
const entrada = () => form.post(route('checkins.entrada'), { preserveScroll: true, onSuccess: () => form.reset() })
const salida = (id: number) => useForm({}).post(route('checkins.salida', id), { preserveScroll: true })

// Live timer
const times = ref<Record<number, string>>({})
let timer: ReturnType<typeof setInterval>

function calcDiff(dt: string) {
  const diff = Math.floor((Date.now() - new Date(dt).getTime()) / 1000)
  const h = Math.floor(diff / 3600)
  const m = Math.floor((diff % 3600) / 60)
  const s = diff % 60
  return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`
}

onMounted(() => {
  props.activos.forEach(c => { times.value[c.id] = calcDiff(c.hora_entrada) })
  timer = setInterval(() => {
    props.activos.forEach(c => { times.value[c.id] = calcDiff(c.hora_entrada) })
  }, 1000)
})
onUnmounted(() => clearInterval(timer))

function hora(dt: string) {
  return new Date(dt).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <Head title="Check-ins — CoworkHub" />
  <AuthenticatedLayout>
    <template #breadcrumb>Check-ins</template>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Check-ins</h1>

      <!-- Registrar entrada -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
          <LogIn :size="18" class="text-violet-600" /> Registrar entrada
        </h2>
        <form @submit.prevent="entrada" class="flex gap-3 flex-wrap">
          <select v-model="form.user_id" required class="flex-1 min-w-40 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
            <option value="">Selecciona miembro</option>
            <option v-for="m in miembros" :key="m.id" :value="m.id">{{ m.name }} {{ m.empresa ? '— ' + m.empresa : '' }}</option>
          </select>
          <select v-model="form.espacio_id" required class="flex-1 min-w-40 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
            <option value="">Selecciona espacio</option>
            <option v-for="e in espacios" :key="e.id" :value="e.id">{{ e.nombre }}</option>
          </select>
          <button type="submit" :disabled="form.processing"
            class="flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-all disabled:opacity-50">
            <LogIn :size="15" /> Check-in
          </button>
        </form>
      </div>

      <!-- Activos ahora -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
          <h2 class="font-semibold text-gray-900 flex items-center gap-2">
            <Clock :size="18" class="text-emerald-600" /> Activos ahora
          </h2>
          <span class="bg-emerald-100 text-emerald-700 text-xs font-semibold px-2.5 py-1 rounded-full">{{ activos.length }}</span>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-if="!activos.length" class="p-6 text-center text-gray-400 text-sm">No hay check-ins activos</div>
          <div v-for="c in activos" :key="c.id" class="px-5 py-4 flex items-center gap-4">
            <div class="w-10 h-10 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center text-sm font-bold uppercase flex-shrink-0">
              {{ c.user?.name?.charAt(0) }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-medium text-gray-900">{{ c.user?.name }}</p>
              <p class="text-xs text-gray-500">{{ c.espacio?.nombre }}</p>
            </div>
            <div class="text-right flex-shrink-0">
              <div class="font-mono text-lg font-bold text-emerald-600">{{ times[c.id] }}</div>
              <div class="text-xs text-gray-400">Desde {{ hora(c.hora_entrada) }}</div>
            </div>
            <button @click="salida(c.id)"
              class="flex items-center gap-1.5 bg-red-50 hover:bg-red-100 text-red-600 px-3 py-2 rounded-xl text-xs font-semibold transition-all">
              <LogOut :size="13" /> Check-out
            </button>
          </div>
        </div>
      </div>

      <!-- Historial del día -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="p-5 border-b border-gray-100">
          <h2 class="font-semibold text-gray-900">Historial de hoy</h2>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-if="!historial.length" class="p-6 text-center text-gray-400 text-sm">Sin salidas registradas hoy</div>
          <div v-for="c in historial" :key="c.id" class="px-5 py-3 flex items-center gap-4">
            <div class="w-8 h-8 bg-gray-100 text-gray-600 rounded-full flex items-center justify-center text-xs font-bold uppercase flex-shrink-0">
              {{ c.user?.name?.charAt(0) }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900">{{ c.user?.name }}</p>
              <p class="text-xs text-gray-500">{{ c.espacio?.nombre }}</p>
            </div>
            <div class="text-xs text-right text-gray-500 flex-shrink-0">
              <div>{{ hora(c.hora_entrada) }} – {{ hora(c.hora_salida) }}</div>
              <div class="font-semibold text-gray-900">{{ c.duracion_minutos }} min</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
