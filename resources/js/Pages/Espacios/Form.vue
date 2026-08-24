<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Save } from 'lucide-vue-next'

const props = defineProps<{ espacio?: any }>()
const isEditing = !!props.espacio

const tipoOptions = [
  { value: 'coworking',    label: '🖥️ Área Coworking',         desc: 'Espacio abierto compartido' },
  { value: 'privado',      label: '🏢 Cubículo Privado',       desc: 'Espacio individual privado' },
  { value: 'sala_juntas',  label: '👥 Sala de Juntas',         desc: 'Para reuniones y presentaciones' },
  { value: 'contenido',    label: '🎙️ Estudio Podcast',        desc: 'Grabación de contenido audio' },
  { value: 'fotografia',   label: '📸 Estudio Fotografía',     desc: 'Sesiones fotográficas' },
]

const amenidadesOpciones = [
  'WiFi', 'Monitor', 'Dock Station', 'Cargadores', 'Proyector', 'Pantalla TV',
  'Pizarrón', 'Videoconferencia', 'Café', 'Agua', 'Insonorización', 'Impresora',
  'Aire acondicionado', 'Locker', 'Luz natural', 'Cámara', 'Micrófono', 'Fondo backdrop',
]

const form = useForm({
  nombre:      props.espacio?.nombre      ?? '',
  tipo:        props.espacio?.tipo        ?? 'coworking',
  capacidad:   props.espacio?.capacidad   ?? 1,
  precio_hora: props.espacio?.precio_hora ?? '',
  piso:        props.espacio?.piso        ?? 1,
  descripcion: props.espacio?.descripcion ?? '',
  amenidades:  (props.espacio?.amenidades ?? []) as string[],
  disponible:  props.espacio?.disponible  ?? true,
})

const toggleAmenidad = (a: string) => {
  const idx = form.amenidades.indexOf(a)
  if (idx >= 0) form.amenidades.splice(idx, 1)
  else form.amenidades.push(a)
}

const submit = () => {
  if (isEditing) {
    form.patch(route('espacios.update', props.espacio.id))
  } else {
    form.post(route('espacios.store'))
  }
}
</script>

<template>
  <Head :title="(isEditing ? 'Editar' : 'Nuevo') + ' espacio — NODICO'" />
  <AuthenticatedLayout>
    <template #breadcrumb>
      <Link :href="route('espacios.index')" class="hover:text-nodo-500">Espacios</Link>
      <span class="mx-1">/</span> {{ isEditing ? 'Editar' : 'Nuevo' }}
    </template>

    <div class="max-w-2xl space-y-5">
      <div class="flex items-center gap-3">
        <Link :href="route('espacios.index')" class="p-2 hover:bg-gray-100 rounded-lg text-gray-500">
          <ArrowLeft :size="18" />
        </Link>
        <div>
          <h1 class="text-2xl font-black text-dark">{{ isEditing ? 'Editar espacio' : 'Nuevo espacio' }}</h1>
          <p class="text-sm text-gray-400 mt-0.5">{{ isEditing ? 'Modifica los datos del espacio' : 'Registra un nuevo espacio de NODICO' }}</p>
        </div>
      </div>

      <form @submit.prevent="submit" class="space-y-5">

        <!-- Tipo de espacio -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wide">Tipo de espacio *</h2>
          <div class="grid sm:grid-cols-2 gap-3">
            <label v-for="t in tipoOptions" :key="t.value"
              :class="['flex items-start gap-3 p-3.5 rounded-xl border-2 cursor-pointer transition-all',
                form.tipo === t.value ? 'border-nodo-400 bg-nodo-50' : 'border-gray-200 hover:border-gray-300']">
              <input type="radio" :value="t.value" v-model="form.tipo" class="hidden" />
              <span class="text-2xl">{{ t.label.split(' ')[0] }}</span>
              <div>
                <p class="font-semibold text-dark text-sm">{{ t.label.split(' ').slice(1).join(' ') }}</p>
                <p class="text-xs text-gray-400">{{ t.desc }}</p>
              </div>
            </label>
          </div>
        </div>

        <!-- Datos básicos -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wide">Datos básicos</h2>
          <div>
            <label class="block text-sm font-medium text-dark mb-1.5">Nombre del espacio *</label>
            <input v-model="form.nombre" type="text" required maxlength="100" placeholder="Ej: Cubículo Privado 1"
              class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
            <p v-if="form.errors.nombre" class="text-red-500 text-xs mt-1">{{ form.errors.nombre }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-dark mb-1.5">Descripción</label>
            <textarea v-model="form.descripcion" rows="2" maxlength="500" placeholder="Describe brevemente este espacio..."
              class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition resize-none" />
          </div>
          <div class="grid sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Capacidad (personas) *</label>
              <input v-model="form.capacidad" type="number" min="1" required
                class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
            </div>
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Precio / hora (MXN)</label>
              <input v-model="form.precio_hora" type="number" min="0" step="0.01" placeholder="0"
                class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
            </div>
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Piso</label>
              <input v-model="form.piso" type="number" min="1"
                class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
            </div>
          </div>
        </div>

        <!-- Amenidades -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wide">Amenidades</h2>
          <div class="flex flex-wrap gap-2">
            <button v-for="a in amenidadesOpciones" :key="a" type="button" @click="toggleAmenidad(a)"
              :class="['px-3 py-1.5 rounded-xl text-xs font-semibold transition-all border-2', form.amenidades.includes(a) ? 'bg-nodo-400 text-dark border-nodo-400' : 'bg-white text-gray-600 border-gray-200 hover:border-nodo-300 hover:text-dark']">
              {{ a }}
            </button>
          </div>
          <p v-if="form.amenidades.length" class="text-xs text-gray-500">
            Seleccionadas: <span class="font-medium text-dark">{{ form.amenidades.join(', ') }}</span>
          </p>
        </div>

        <!-- Disponibilidad -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <label class="flex items-center justify-between cursor-pointer hover:bg-gray-50 -m-2 p-2 rounded-xl transition-colors">
            <div>
              <p class="font-semibold text-dark">Espacio disponible</p>
              <p class="text-xs text-gray-500 mt-0.5">Los miembros podrán ver y reservar este espacio</p>
            </div>
            <div @click="form.disponible = !form.disponible"
              :class="['w-11 h-6 rounded-full transition-all cursor-pointer flex-shrink-0', form.disponible ? 'bg-emerald-500' : 'bg-gray-300']">
              <div :class="['w-5 h-5 bg-white rounded-full shadow mt-0.5 transition-transform', form.disponible ? 'translate-x-5' : 'translate-x-0.5']" />
            </div>
          </label>
        </div>

        <div class="flex items-center justify-between">
          <Link :href="route('espacios.index')" class="text-sm text-gray-500 hover:text-dark font-medium">← Cancelar</Link>
          <button type="submit" :disabled="form.processing"
            class="flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 disabled:opacity-50 text-dark px-6 py-3 rounded-xl text-sm font-bold transition-all shadow-sm">
            <Save :size="16" /> {{ form.processing ? 'Guardando...' : (isEditing ? 'Guardar cambios' : 'Crear espacio') }}
          </button>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
