<script setup lang="ts">
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { CalendarDays, ChevronRight, Check, AlertCircle, Clock } from 'lucide-vue-next'
import { ref, computed } from 'vue'

const props = defineProps<{
  espacios: any[]
  suscripcion: any
  resumen: any
}>()

const step = ref(1)
const form = useForm({
  espacio_id: null as number | null,
  fecha: '',
  hora_inicio: '09:00',
  hora_fin: '11:00',
})

const selectedEspacio = computed(() => props.espacios.find(e => e.id === form.espacio_id))

const horas = Array.from({ length: 15 }, (_, i) => {
  const h = 7 + i
  return `${String(h).padStart(2, '0')}:00`
})

const duracionHoras = computed(() => {
  if (!form.hora_inicio || !form.hora_fin) return 0
  return (new Date(`2000-01-01 ${form.hora_fin}`).getTime() - new Date(`2000-01-01 ${form.hora_inicio}`).getTime()) / 3600000
})

const horasRestantes = computed(() => {
  if (!selectedEspacio.value || !props.resumen) return null
  const tipo = selectedEspacio.value.tipo
  if (['privado', 'sala_juntas'].includes(tipo)) return props.resumen.horas_sala_restantes
  if (['contenido', 'fotografia'].includes(tipo)) return props.resumen.horas_contenido_restantes
  return null
})

const tieneHorasSuficientes = computed(() => {
  if (horasRestantes.value === null) return true
  return duracionHoras.value <= horasRestantes.value
})

const maxHorasDia = computed(() => props.resumen?.max_horas_sala_dia ?? null)

const submit = () => form.post(route('portal.reservar.store'))

const tipoLabel: Record<string, { label: string; emoji: string }> = {
  privado:    { label: 'Cubículo Privado', emoji: '🏢' },
  sala_juntas:{ label: 'Sala de Juntas',   emoji: '👥' },
  contenido:  { label: 'Estudio Podcast',  emoji: '🎙️' },
  fotografia: { label: 'Estudio Fotografía',emoji: '📸' },
  coworking:  { label: 'Coworking',        emoji: '🖥️' },
}

const stepLabels = ['Tipo de espacio', 'Seleccionar espacio', 'Fecha y hora', 'Confirmación']

// Agrupar espacios por tipo
const tiposDisponibles = computed(() => {
  const tipos = [...new Set(props.espacios.map(e => e.tipo))]
  return tipos.filter(t => tipoLabel[t])
})

const espaciosFiltrados = computed(() => {
  if (!filtroTipo.value) return props.espacios
  return props.espacios.filter(e => e.tipo === filtroTipo.value)
})

const filtroTipo = ref<string | null>(null)
</script>

<template>
  <Head title="Reservar espacio — NODICO" />
  <PortalLayout>
    <div class="max-w-2xl mx-auto space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-dark">Reservar espacio</h1>
        <p class="text-gray-500 text-sm mt-1">Selecciona el espacio, fecha y horario que necesitas.</p>
      </div>

      <!-- Resumen de horas disponibles -->
      <div v-if="resumen" class="grid grid-cols-2 gap-3">
        <div v-if="resumen.horas_sala_max" class="bg-nodo-50 rounded-xl p-3 border border-nodo-100">
          <p class="text-xs text-nodo-700 font-semibold">Horas sala/cubículo</p>
          <p class="text-xl font-black text-dark">{{ resumen.horas_sala_restantes?.toFixed(1) }}h</p>
          <p class="text-xs text-nodo-600">de {{ resumen.horas_sala_max }}h disponibles</p>
        </div>
        <div v-if="resumen.horas_contenido_max" class="bg-nodo-50 rounded-xl p-3 border border-nodo-100">
          <p class="text-xs text-nodo-700 font-semibold">Horas estudio</p>
          <p class="text-xl font-black text-dark">{{ resumen.horas_contenido_restantes?.toFixed(1) }}h</p>
          <p class="text-xs text-nodo-600">de {{ resumen.horas_contenido_max }}h disponibles</p>
        </div>
      </div>

      <!-- Sin membresía -->
      <div v-if="!suscripcion" class="bg-amber-50 border border-amber-200 rounded-2xl p-5 flex items-start gap-3">
        <AlertCircle :size="20" class="text-amber-500 flex-shrink-0" />
        <div>
          <p class="font-semibold text-amber-800 text-sm">Sin membresía activa</p>
          <p class="text-amber-700 text-xs mt-0.5">Necesitas una membresía activa para hacer reservas. Contacta a Nodico.</p>
        </div>
      </div>

      <!-- Steps indicator -->
      <div class="flex items-center gap-2">
        <div v-for="(label, i) in stepLabels" :key="i" class="flex items-center gap-1.5 flex-1">
          <div :class="['w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0',
            step > i + 1 ? 'bg-emerald-500 text-white' :
            step === i + 1 ? 'bg-nodo-400 text-dark' :
            'bg-gray-200 text-gray-400']">
            <Check v-if="step > i + 1" :size="14" />
            <span v-else>{{ i + 1 }}</span>
          </div>
          <span :class="['text-xs hidden sm:block truncate', step === i + 1 ? 'text-nodo-600 font-medium' : 'text-gray-400']">{{ label }}</span>
          <ChevronRight v-if="i < 3" :size="14" class="text-gray-300 flex-shrink-0" />
        </div>
      </div>

      <!-- Step 1: Tipo de espacio -->
      <div v-if="step === 1" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="font-semibold text-dark mb-5">¿Qué tipo de espacio necesitas?</h2>
        <div v-if="!tiposDisponibles.length" class="text-center py-6">
          <p class="text-gray-400 text-sm">No hay espacios disponibles para tu membresía.</p>
          <p class="text-gray-400 text-xs mt-1">Contacta a Nodico para más información.</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <button v-for="tipo in tiposDisponibles" :key="tipo"
            @click="filtroTipo = tipo; step = 2"
            class="flex flex-col items-center gap-3 p-5 rounded-xl border-2 border-gray-100 hover:border-nodo-400 hover:bg-nodo-50 transition-all text-center">
            <span class="text-4xl">{{ tipoLabel[tipo]?.emoji }}</span>
            <span class="text-sm font-semibold text-dark">{{ tipoLabel[tipo]?.label }}</span>
          </button>
        </div>
      </div>

      <!-- Step 2: Selección de espacio -->
      <div v-if="step === 2" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="font-semibold text-dark mb-4">Selecciona un espacio</h2>
        <div class="space-y-3">
          <button v-for="e in espaciosFiltrados" :key="e.id"
            @click="form.espacio_id = e.id; step = 3"
            :class="['w-full flex items-center gap-4 p-4 rounded-xl border-2 transition-all text-left',
              form.espacio_id === e.id ? 'border-nodo-400 bg-nodo-50' : 'border-gray-100 hover:border-nodo-200 hover:bg-gray-50']">
            <div class="text-2xl flex-shrink-0">{{ tipoLabel[e.tipo]?.emoji || '🏢' }}</div>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-dark text-sm">{{ e.nombre }}</p>
              <p class="text-xs text-gray-500">Cap. {{ e.capacidad }} persona{{ e.capacidad !== 1 ? 's' : '' }}</p>
              <div class="flex flex-wrap gap-1 mt-1">
                <span v-for="a in (e.amenidades ?? []).slice(0, 3)" :key="a"
                  class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ a }}</span>
              </div>
            </div>
          </button>
        </div>
        <button @click="step = 1; filtroTipo = null" class="mt-4 text-sm text-gray-500 hover:text-dark">← Volver</button>
      </div>

      <!-- Step 3: Fecha y hora -->
      <div v-if="step === 3" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        <h2 class="font-semibold text-dark">Selecciona fecha y horario</h2>

        <!-- Aviso límite diario -->
        <div v-if="maxHorasDia && ['privado','sala_juntas'].includes(selectedEspacio?.tipo || '')"
          class="bg-amber-50 border border-amber-200 rounded-xl p-3 flex items-start gap-2">
          <Clock :size="15" class="text-amber-500 flex-shrink-0 mt-0.5" />
          <p class="text-xs text-amber-700">Máximo <strong>{{ maxHorasDia }} horas</strong> por día en salas privadas y sala de juntas.</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-dark mb-1.5">Fecha *</label>
          <input v-model="form.fecha" type="date" :min="new Date().toISOString().split('T')[0]" required
            class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-dark mb-1.5">Hora inicio *</label>
            <select v-model="form.hora_inicio" class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition">
              <option v-for="h in horas" :key="h" :value="h">{{ h }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-dark mb-1.5">Hora fin *</label>
            <select v-model="form.hora_fin" class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition">
              <option v-for="h in horas" :key="h" :value="h">{{ h }}</option>
            </select>
          </div>
        </div>

        <!-- Duración y validación -->
        <div v-if="duracionHoras > 0" class="bg-gray-50 rounded-xl p-3">
          <div class="flex items-center justify-between text-sm">
            <span class="text-gray-600">Duración</span>
            <span class="font-bold text-dark">{{ duracionHoras }}h</span>
          </div>
          <div v-if="!tieneHorasSuficientes" class="mt-2 flex items-center gap-2 text-red-600 text-xs">
            <AlertCircle :size="14" />
            <span>No tienes suficientes horas disponibles ({{ horasRestantes?.toFixed(1) }}h restantes)</span>
          </div>
        </div>

        <p v-if="form.errors.hora_fin" class="text-red-500 text-xs">{{ form.errors.hora_fin }}</p>

        <div class="flex justify-between pt-2">
          <button @click="step = 2" type="button" class="text-sm text-gray-500 hover:text-dark">← Volver</button>
          <button @click="step = 4" type="button" :disabled="!form.fecha || duracionHoras <= 0 || !tieneHorasSuficientes"
            class="bg-nodo-400 hover:bg-nodo-500 disabled:opacity-40 text-dark px-5 py-2.5 rounded-xl text-sm font-semibold transition">
            Continuar →
          </button>
        </div>
      </div>

      <!-- Step 4: Confirmación -->
      <div v-if="step === 4" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        <h2 class="font-semibold text-dark">Confirma tu reserva</h2>
        <div class="bg-nodo-50 border border-nodo-100 rounded-xl p-4 space-y-3">
          <div class="flex justify-between text-sm">
            <span class="text-gray-500">Espacio</span>
            <span class="font-semibold text-dark">{{ selectedEspacio?.nombre }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-500">Fecha</span>
            <span class="font-semibold text-dark">{{ form.fecha ? new Date(form.fecha + 'T00:00:00').toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' }) : '' }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-500">Horario</span>
            <span class="font-semibold text-dark">{{ form.hora_inicio }} – {{ form.hora_fin }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-500">Duración</span>
            <span class="font-semibold text-dark">{{ duracionHoras }}h</span>
          </div>
          <div class="border-t border-nodo-200 pt-3 flex justify-between text-sm">
            <span class="text-gray-700 font-medium">Costo</span>
            <span class="font-bold text-emerald-600">Incluido en tu membresía</span>
          </div>
        </div>

        <p v-for="(err, k) in form.errors" :key="k" class="text-red-500 text-xs">{{ err }}</p>

        <div class="flex justify-between">
          <button @click="step = 3" type="button" class="text-sm text-gray-500 hover:text-dark">← Volver</button>
          <button @click="submit" :disabled="form.processing"
            class="flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 disabled:opacity-50 text-dark px-5 py-2.5 rounded-xl text-sm font-bold transition">
            <Check :size="15" /> {{ form.processing ? 'Reservando...' : 'Confirmar reserva' }}
          </button>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>
