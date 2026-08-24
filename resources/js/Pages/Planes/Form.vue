<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Save } from 'lucide-vue-next'

const props = defineProps<{ plan?: any }>()
const isEditing = !!props.plan

const form = useForm({
  nombre:              props.plan?.nombre              ?? '',
  subtitulo:           props.plan?.subtitulo           ?? '',
  tipo:                props.plan?.tipo                ?? 'mes',
  precio:              props.plan?.precio              ?? '',
  dias_cowork_mes:     props.plan?.dias_cowork_mes     ?? '',
  horas_sala_mes:      props.plan?.horas_sala_mes      ?? '',
  horas_contenido_mes: props.plan?.horas_contenido_mes ?? '',
  max_horas_sala_dia:  props.plan?.max_horas_sala_dia  ?? '',
  personas:            props.plan?.personas            ?? 1,
  horas_incluidas:     props.plan?.horas_incluidas     ?? '',
  max_reservas_mes:    props.plan?.max_reservas_mes    ?? '',
  acceso_24h:          props.plan?.acceso_24h          ?? false,
  incluye_sala_juntas: props.plan?.incluye_sala_juntas ?? false,
  color:               props.plan?.color               ?? '#F5C600',
  destacado:           props.plan?.destacado           ?? false,
  activo:              props.plan?.activo              ?? true,
})

const submit = () => {
  if (isEditing) {
    form.patch(route('planes.update', props.plan.id))
  } else {
    form.post(route('planes.store'))
  }
}

const tipoOptions = [
  { value: 'dia', label: 'Día / Day-Pass' },
  { value: 'semana', label: 'Semana' },
  { value: 'mes', label: 'Mensual' },
  { value: 'anual', label: 'Anual' },
  { value: 'horas', label: 'Por horas' },
]

const coloresPreset = ['#F5C600', '#3B82F6', '#10B981', '#8B5CF6', '#EF4444', '#F97316', '#6B7280', '#1B1B2F']
</script>

<template>
  <Head :title="(isEditing ? 'Editar' : 'Nuevo') + ' plan — NODICO'" />
  <AuthenticatedLayout>
    <template #breadcrumb>
      <Link :href="route('planes.index')" class="hover:text-nodo-500">Planes</Link>
      <span class="mx-1">/</span> {{ isEditing ? 'Editar' : 'Nuevo' }}
    </template>

    <div class="max-w-2xl space-y-5">
      <div class="flex items-center gap-3">
        <Link :href="route('planes.index')" class="p-2 hover:bg-gray-100 rounded-lg text-gray-500">
          <ArrowLeft :size="18" />
        </Link>
        <div>
          <h1 class="text-2xl font-black text-dark">{{ isEditing ? 'Editar plan' : 'Nuevo plan' }}</h1>
          <p class="text-sm text-gray-400 mt-0.5">{{ isEditing ? 'Modifica los datos del plan' : 'Configura un nuevo plan de membresía NODICO' }}</p>
        </div>
      </div>

      <form @submit.prevent="submit" class="space-y-5">

        <!-- Info básica -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wide">Información básica</h2>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Nombre del plan *</label>
              <input v-model="form.nombre" type="text" required maxlength="100" placeholder="Ej: Nodico PRO"
                class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
              <p v-if="form.errors.nombre" class="text-red-500 text-xs mt-1">{{ form.errors.nombre }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Subtítulo</label>
              <input v-model="form.subtitulo" type="text" maxlength="200" placeholder="Ej: Para profesionales activos"
                class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
            </div>
          </div>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Tipo de plan *</label>
              <select v-model="form.tipo" required class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition">
                <option v-for="o in tipoOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Precio (MXN) *</label>
              <input v-model="form.precio" type="number" min="0" step="0.01" required placeholder="599"
                class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
              <p v-if="form.errors.precio" class="text-red-500 text-xs mt-1">{{ form.errors.precio }}</p>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-dark mb-1.5">Personas incluidas *</label>
            <div class="flex gap-3">
              <label v-for="n in [1, 2]" :key="n"
                :class="['flex-1 flex items-center justify-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all text-sm font-semibold',
                  form.personas === n ? 'border-nodo-400 bg-nodo-50 text-dark' : 'border-gray-200 text-gray-500 hover:border-gray-300']">
                <input type="radio" :value="n" v-model="form.personas" class="hidden" />
                {{ n === 1 ? '👤 1 persona' : '👥 2 personas (Match)' }}
              </label>
            </div>
          </div>
        </div>

        <!-- Acceso y horas -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wide">Acceso y horas incluidas</h2>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Días coworking / mes</label>
              <input v-model="form.dias_cowork_mes" type="number" min="1" placeholder="Vacío = ilimitado"
                class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
              <p class="text-xs text-gray-400 mt-1">Dejar vacío para acceso ilimitado</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Horas sala privada / mes</label>
              <input v-model="form.horas_sala_mes" type="number" min="0" step="0.5" placeholder="Vacío = sin acceso"
                class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
            </div>
          </div>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Horas estudio contenido / mes</label>
              <input v-model="form.horas_contenido_mes" type="number" min="0" step="0.5" placeholder="Vacío = sin acceso"
                class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
            </div>
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Máx. horas sala por día</label>
              <input v-model="form.max_horas_sala_dia" type="number" min="0" step="0.5" placeholder="Ej: 2"
                class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
            </div>
          </div>
          <div class="space-y-3 pt-2">
            <label class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
              <div>
                <p class="text-sm font-semibold text-dark">Acceso 24/7</p>
                <p class="text-xs text-gray-500">Permite entrada fuera del horario normal</p>
              </div>
              <div @click="form.acceso_24h = !form.acceso_24h"
                :class="['w-11 h-6 rounded-full transition-all cursor-pointer flex-shrink-0', form.acceso_24h ? 'bg-nodo-400' : 'bg-gray-300']">
                <div :class="['w-5 h-5 bg-white rounded-full shadow mt-0.5 transition-transform', form.acceso_24h ? 'translate-x-5' : 'translate-x-0.5']" />
              </div>
            </label>
            <label class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
              <div>
                <p class="text-sm font-semibold text-dark">Incluye sala de juntas</p>
                <p class="text-xs text-gray-500">Permite reservar salas privadas y sala de juntas</p>
              </div>
              <div @click="form.incluye_sala_juntas = !form.incluye_sala_juntas"
                :class="['w-11 h-6 rounded-full transition-all cursor-pointer flex-shrink-0', form.incluye_sala_juntas ? 'bg-nodo-400' : 'bg-gray-300']">
                <div :class="['w-5 h-5 bg-white rounded-full shadow mt-0.5 transition-transform', form.incluye_sala_juntas ? 'translate-x-5' : 'translate-x-0.5']" />
              </div>
            </label>
          </div>
        </div>

        <!-- Apariencia -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wide">Apariencia</h2>
          <div>
            <label class="block text-sm font-medium text-dark mb-2">Color del plan</label>
            <div class="flex items-center gap-2 flex-wrap">
              <button v-for="c in coloresPreset" :key="c" type="button" @click="form.color = c"
                :style="`background: ${c}`"
                :class="['w-8 h-8 rounded-full border-4 transition-all', form.color === c ? 'border-dark scale-110' : 'border-transparent hover:scale-105']" />
              <input type="color" v-model="form.color" class="w-8 h-8 rounded-full cursor-pointer border border-gray-200 p-0.5" />
            </div>
          </div>
          <div class="space-y-3">
            <label class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
              <div>
                <p class="text-sm font-semibold text-dark">Plan destacado ⭐</p>
                <p class="text-xs text-gray-500">Muestra badge "Más popular" con borde dorado</p>
              </div>
              <div @click="form.destacado = !form.destacado"
                :class="['w-11 h-6 rounded-full transition-all cursor-pointer flex-shrink-0', form.destacado ? 'bg-nodo-400' : 'bg-gray-300']">
                <div :class="['w-5 h-5 bg-white rounded-full shadow mt-0.5 transition-transform', form.destacado ? 'translate-x-5' : 'translate-x-0.5']" />
              </div>
            </label>
            <label class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
              <div>
                <p class="text-sm font-semibold text-dark">Plan activo</p>
                <p class="text-xs text-gray-500">Visible para nuevos miembros en el sitio público</p>
              </div>
              <div @click="form.activo = !form.activo"
                :class="['w-11 h-6 rounded-full transition-all cursor-pointer flex-shrink-0', form.activo ? 'bg-emerald-500' : 'bg-gray-300']">
                <div :class="['w-5 h-5 bg-white rounded-full shadow mt-0.5 transition-transform', form.activo ? 'translate-x-5' : 'translate-x-0.5']" />
              </div>
            </label>
          </div>

          <!-- Preview -->
          <div class="pt-2">
            <p class="text-xs text-gray-400 mb-3 font-medium">Vista previa:</p>
            <div class="max-w-[200px]">
              <div :class="['rounded-2xl border-2 overflow-hidden text-sm', form.destacado ? 'border-nodo-400' : 'border-gray-200']">
                <div v-if="form.destacado" class="bg-nodo-400 text-dark text-xs font-black text-center py-1">⭐ Popular</div>
                <div class="h-1" :style="`background: ${form.color}`" />
                <div class="p-3">
                  <p class="font-black text-dark">{{ form.nombre || 'Plan' }}</p>
                  <p class="text-lg font-black text-dark mt-1">${{ Number(form.precio || 0).toLocaleString('es-MX') }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <Link :href="route('planes.index')" class="text-sm text-gray-500 hover:text-dark font-medium">← Cancelar</Link>
          <button type="submit" :disabled="form.processing"
            class="flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 disabled:opacity-50 text-dark px-6 py-3 rounded-xl text-sm font-bold transition-all shadow-sm">
            <Save :size="16" /> {{ form.processing ? 'Guardando...' : (isEditing ? 'Guardar cambios' : 'Crear plan') }}
          </button>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
