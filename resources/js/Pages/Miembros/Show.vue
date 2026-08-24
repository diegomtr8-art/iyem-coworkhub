<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ArrowLeft, Calendar, Clock, Receipt, CreditCard, Plus, X, UserCheck, UserX, Users } from 'lucide-vue-next'
import { ref } from 'vue'

const props = defineProps<{ miembro: any; planes: any[] }>()

const activeTab = ref<'suscripcion'|'reservas'|'checkins'|'facturas'>('suscripcion')
const showModal = ref(false)
const showCompanionModal = ref(false)

const form = useForm({
  plan_id:      '',
  fecha_inicio: new Date().toISOString().split('T')[0],
  fecha_fin:    '',
  precio_pagado:0,
  auto_renovar: false,
})

const companionForm = useForm({
  companion_user_id:    '',
  companion_face_id_ok: false,
})

const submit = () => {
  form.post(route('miembros.suscripcion', props.miembro.id), {
    onSuccess: () => { showModal.value = false; form.reset() }
  })
}

const submitCompanion = () => {
  const suscripcion = props.miembro.suscripciones?.find((s: any) => s.estatus === 'Activa')
  if (!suscripcion) return
  companionForm.patch(route('miembros.companion', props.miembro.id), {
    onSuccess: () => { showCompanionModal.value = false; companionForm.reset() }
  })
}

const toggleFaceId = () => {
  router.patch(route('miembros.faceid', props.miembro.id), {}, { preserveScroll: true })
}

const suscripcionActiva = props.miembro.suscripciones?.find((s: any) => s.estatus === 'Activa')

function formatDate(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}
function hora(dt: string) {
  return new Date(dt).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <Head :title="miembro.name + ' — NODICO Admin'" />
  <AuthenticatedLayout>
    <template #breadcrumb>
      <Link :href="route('miembros.index')" class="hover:text-nodo-500">Miembros</Link>
      <span class="mx-1">/</span> {{ miembro.name }}
    </template>

    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-start gap-4">
        <Link :href="route('miembros.index')" class="p-2 hover:bg-gray-100 rounded-lg text-gray-500 mt-1">
          <ArrowLeft :size="18" />
        </Link>
        <div class="flex-1">
          <div class="flex items-start justify-between flex-wrap gap-3">
            <div class="flex items-center gap-4">
              <div class="w-14 h-14 bg-nodo-400 text-dark rounded-2xl flex items-center justify-center text-xl font-black uppercase">
                {{ miembro.name?.charAt(0) }}
              </div>
              <div>
                <h1 class="text-2xl font-black text-dark">{{ miembro.name }}</h1>
                <p class="text-gray-500 text-sm">{{ miembro.email }}</p>
                <div class="flex items-center gap-2 mt-1">
                  <p v-if="miembro.empresa" class="text-gray-400 text-xs">{{ miembro.empresa }}</p>
                  <span v-if="miembro.ocupacion" class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ miembro.ocupacion }}</span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              <!-- Face ID toggle -->
              <button @click="toggleFaceId"
                :class="['flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold transition-all border-2',
                  miembro.face_id_ok
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                    : 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100']">
                <component :is="miembro.face_id_ok ? UserCheck : UserX" :size="15" />
                Face ID: {{ miembro.face_id_ok ? 'Registrado' : 'Pendiente' }}
              </button>

              <button v-if="suscripcionActiva?.plan?.personas === 2" @click="showCompanionModal = true"
                class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold border-2 border-nodo-200 bg-nodo-50 text-nodo-700 hover:bg-nodo-100 transition-all">
                <Users :size="15" /> Acompañante
              </button>

              <button @click="showModal = true"
                class="flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 text-dark px-4 py-2 rounded-xl text-sm font-bold transition-all">
                <Plus :size="15" /> Nueva suscripción
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-1 overflow-x-auto">
          <button v-for="tab in [
            { id: 'suscripcion', label: 'Suscripción', icon: CreditCard },
            { id: 'reservas',    label: 'Reservas',    icon: Calendar },
            { id: 'checkins',    label: 'Check-ins',   icon: Clock },
            { id: 'facturas',    label: 'Facturas',    icon: Receipt },
          ]" :key="tab.id"
            @click="activeTab = tab.id as any"
            :class="['flex items-center gap-2 px-4 pb-3 text-sm font-medium border-b-2 transition-all whitespace-nowrap',
              activeTab === tab.id ? 'border-nodo-400 text-dark' : 'border-transparent text-gray-500 hover:text-dark']">
            <component :is="tab.icon" :size="15" /> {{ tab.label }}
          </button>
        </nav>
      </div>

      <!-- Suscripción -->
      <div v-if="activeTab === 'suscripcion'" class="space-y-4">
        <div v-if="suscripcionActiva" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <div class="flex items-center justify-between mb-5">
            <h3 class="font-bold text-dark">Plan activo</h3>
            <span class="bg-emerald-50 text-emerald-700 px-3 py-1 rounded-full text-xs font-semibold">✓ Activa</span>
          </div>
          <div class="grid sm:grid-cols-3 gap-4 mb-5">
            <div class="bg-gray-50 rounded-xl p-3">
              <p class="text-xs text-gray-400 mb-0.5">Plan</p>
              <p class="font-black text-dark">{{ suscripcionActiva.plan?.nombre }}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
              <p class="text-xs text-gray-400 mb-0.5">Inicio</p>
              <p class="font-medium text-dark">{{ formatDate(suscripcionActiva.fecha_inicio) }}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
              <p class="text-xs text-gray-400 mb-0.5">Vencimiento</p>
              <p class="font-medium text-dark">{{ formatDate(suscripcionActiva.fecha_fin) }}</p>
            </div>
          </div>

          <!-- Uso de horas -->
          <div v-if="suscripcionActiva.plan?.horas_sala_mes" class="mb-3">
            <div class="flex items-center justify-between text-xs mb-1.5">
              <span class="text-gray-500 font-medium">🏢 Horas sala usadas</span>
              <span class="font-bold text-dark">{{ Number(suscripcionActiva.horas_sala_usadas ?? 0).toFixed(1) }} / {{ suscripcionActiva.plan.horas_sala_mes }}h</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full bg-nodo-400 rounded-full transition-all"
                :style="`width: ${Math.min(100, ((suscripcionActiva.horas_sala_usadas ?? 0) / suscripcionActiva.plan.horas_sala_mes) * 100)}%`" />
            </div>
          </div>
          <div v-if="suscripcionActiva.plan?.horas_contenido_mes">
            <div class="flex items-center justify-between text-xs mb-1.5">
              <span class="text-gray-500 font-medium">🎙️ Horas estudio usadas</span>
              <span class="font-bold text-dark">{{ Number(suscripcionActiva.horas_contenido_usadas ?? 0).toFixed(1) }} / {{ suscripcionActiva.plan.horas_contenido_mes }}h</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full bg-nodo-400 rounded-full transition-all"
                :style="`width: ${Math.min(100, ((suscripcionActiva.horas_contenido_usadas ?? 0) / suscripcionActiva.plan.horas_contenido_mes) * 100)}%`" />
            </div>
          </div>

          <!-- Companion info -->
          <div v-if="suscripcionActiva.companion" class="mt-4 pt-4 border-t border-gray-100">
            <p class="text-xs text-gray-400 mb-1">Acompañante (Match)</p>
            <div class="flex items-center gap-2">
              <div class="w-7 h-7 bg-nodo-100 rounded-full flex items-center justify-center text-xs font-bold text-nodo-700">
                {{ suscripcionActiva.companion?.name?.charAt(0) }}
              </div>
              <span class="text-sm font-medium text-dark">{{ suscripcionActiva.companion?.name }}</span>
              <span :class="['text-xs px-2 py-0.5 rounded-full', suscripcionActiva.companion_face_id_ok ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">
                Face ID: {{ suscripcionActiva.companion_face_id_ok ? '✓' : 'Pendiente' }}
              </span>
            </div>
          </div>
        </div>

        <div v-else class="bg-amber-50 border border-amber-200 rounded-2xl p-6 text-center">
          <p class="text-amber-700 font-bold">Sin suscripción activa</p>
          <p class="text-amber-600 text-sm mt-1">Crea una nueva suscripción con el botón superior</p>
        </div>

        <!-- Historial -->
        <div v-if="miembro.suscripciones?.length > 1" class="bg-white rounded-2xl border border-gray-100 shadow-sm">
          <div class="p-4 border-b border-gray-100">
            <h3 class="font-bold text-dark text-sm">Historial de suscripciones</h3>
          </div>
          <div class="divide-y divide-gray-50">
            <div v-for="s in miembro.suscripciones.filter((s: any) => s.estatus !== 'Activa')" :key="s.id"
              class="px-4 py-3 flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-dark">{{ s.plan?.nombre }}</p>
                <p class="text-xs text-gray-400">{{ formatDate(s.fecha_inicio) }} – {{ formatDate(s.fecha_fin) }}</p>
              </div>
              <span :class="['px-2.5 py-1 rounded-full text-xs font-medium', s.estatus === 'Vencida' ? 'bg-gray-100 text-gray-600' : 'bg-red-50 text-red-600']">
                {{ s.estatus }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Reservas -->
      <div v-if="activeTab === 'reservas'" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm min-w-[480px]">
            <thead class="bg-gray-50 border-b border-gray-100">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Espacio</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fecha</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Horario</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Estatus</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="!miembro.reservas?.length"><td colspan="4" class="px-4 py-8 text-center text-gray-400">Sin reservas</td></tr>
              <tr v-for="r in miembro.reservas" :key="r.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-dark">{{ r.espacio?.nombre }}</td>
                <td class="px-4 py-3 text-gray-600">{{ formatDate(r.fecha) }}</td>
                <td class="px-4 py-3 text-gray-600">{{ r.hora_inicio?.slice(0,5) }} – {{ r.hora_fin?.slice(0,5) }}</td>
                <td class="px-4 py-3">
                  <span :class="['px-2 py-0.5 rounded-full text-xs font-medium',
                    r.estatus === 'Confirmada' ? 'bg-emerald-50 text-emerald-700' :
                    r.estatus === 'Cancelada' ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-600']">
                    {{ r.estatus }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Checkins -->
      <div v-if="activeTab === 'checkins'" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm min-w-[480px]">
            <thead class="bg-gray-50 border-b border-gray-100">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Espacio</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Entrada</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Salida</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Duración</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="!miembro.checkins?.length"><td colspan="4" class="px-4 py-8 text-center text-gray-400">Sin check-ins</td></tr>
              <tr v-for="c in miembro.checkins" :key="c.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-dark">{{ c.espacio?.nombre ?? 'General' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ hora(c.hora_entrada) }}</td>
                <td class="px-4 py-3 text-gray-600">{{ c.hora_salida ? hora(c.hora_salida) : '—' }}</td>
                <td class="px-4 py-3">
                  <span v-if="!c.duracion_minutos" class="flex items-center gap-1.5 text-emerald-600 text-xs font-semibold">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse" /> Activo
                  </span>
                  <span v-else class="text-gray-600">{{ c.duracion_minutos }} min</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Facturas -->
      <div v-if="activeTab === 'facturas'" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm min-w-[480px]">
            <thead class="bg-gray-50 border-b border-gray-100">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Folio</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Concepto</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Total</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Estatus</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="!miembro.facturas?.length"><td colspan="4" class="px-4 py-8 text-center text-gray-400">Sin facturas</td></tr>
              <tr v-for="f in miembro.facturas" :key="f.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ f.folio }}</td>
                <td class="px-4 py-3 text-dark">{{ f.concepto }}</td>
                <td class="px-4 py-3 font-bold text-dark">${{ Number(f.total).toLocaleString('es-MX') }}</td>
                <td class="px-4 py-3">
                  <span :class="['px-2 py-0.5 rounded-full text-xs font-medium',
                    f.estatus === 'Pagada' ? 'bg-emerald-50 text-emerald-700' :
                    f.estatus === 'Pendiente' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600']">
                    {{ f.estatus }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal nueva suscripción -->
    <div v-if="showModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-xl max-w-md w-full">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
          <h3 class="font-bold text-dark">Nueva suscripción</h3>
          <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 p-1"><X :size="18" /></button>
        </div>
        <form @submit.prevent="submit" class="p-5 space-y-4">
          <div>
            <label class="block text-sm font-medium text-dark mb-1.5">Plan *</label>
            <select v-model="form.plan_id" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-nodo-400 outline-none">
              <option value="">Selecciona un plan</option>
              <option v-for="p in planes" :key="p.id" :value="p.id">{{ p.nombre }} — ${{ Number(p.precio).toLocaleString('es-MX') }}</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Inicio *</label>
              <input v-model="form.fecha_inicio" type="date" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-nodo-400 outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Fin *</label>
              <input v-model="form.fecha_fin" type="date" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-nodo-400 outline-none" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-dark mb-1.5">Precio pagado (MXN) *</label>
            <input v-model="form.precio_pagado" type="number" min="0" step="0.01" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-nodo-400 outline-none" />
          </div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.auto_renovar" class="rounded text-nodo-500" />
            <span class="text-sm text-dark">Auto renovar</span>
          </label>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showModal = false" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50">Cancelar</button>
            <button type="submit" :disabled="form.processing" class="px-5 py-2 bg-nodo-400 hover:bg-nodo-500 text-dark text-sm font-bold rounded-xl disabled:opacity-50">Crear suscripción</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal acompañante -->
    <div v-if="showCompanionModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-xl max-w-md w-full">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
          <h3 class="font-bold text-dark">Asignar acompañante (Match)</h3>
          <button @click="showCompanionModal = false" class="text-gray-400 hover:text-gray-600 p-1"><X :size="18" /></button>
        </div>
        <form @submit.prevent="submitCompanion" class="p-5 space-y-4">
          <div>
            <label class="block text-sm font-medium text-dark mb-1.5">ID de usuario acompañante *</label>
            <input v-model="companionForm.companion_user_id" type="number" required placeholder="Ingresa el ID del usuario"
              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-nodo-400 outline-none" />
            <p class="text-xs text-gray-400 mt-1">El acompañante debe tener cuenta registrada en NODICO</p>
          </div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="companionForm.companion_face_id_ok" class="rounded" />
            <span class="text-sm text-dark">Face ID registrado</span>
          </label>
          <div class="flex justify-end gap-3">
            <button type="button" @click="showCompanionModal = false" class="px-4 py-2 text-sm border border-gray-200 rounded-xl hover:bg-gray-50">Cancelar</button>
            <button type="submit" :disabled="companionForm.processing" class="px-5 py-2 bg-nodo-400 hover:bg-nodo-500 text-dark text-sm font-bold rounded-xl disabled:opacity-50">Asignar</button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
