<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Save } from 'lucide-vue-next'

const props = defineProps<{ evento?: any }>()
const isEditing = !!props.evento

const form = useForm({
  titulo:        props.evento?.titulo        ?? '',
  descripcion:   props.evento?.descripcion   ?? '',
  fecha:         props.evento?.fecha         ?? '',
  hora_inicio:   props.evento?.hora_inicio   ? props.evento.hora_inicio.slice(0, 5) : '',
  hora_fin:      props.evento?.hora_fin      ? props.evento.hora_fin.slice(0, 5) : '',
  lugar:         props.evento?.lugar         ?? 'NODICO Coworking',
  cupo_maximo:   props.evento?.cupo_maximo   ?? '',
  precio:        props.evento?.precio        ?? 0,
  solo_miembros: props.evento?.solo_miembros ?? false,
  activo:        props.evento?.activo        ?? true,
})

const submit = () => {
  if (isEditing) {
    form.patch(route('eventos.admin.update', props.evento.id))
  } else {
    form.post(route('eventos.admin.store'))
  }
}
</script>

<template>
  <Head :title="(isEditing ? 'Editar' : 'Nuevo') + ' evento — NODICO'" />
  <AuthenticatedLayout>
    <template #breadcrumb>
      <Link :href="route('eventos.admin.index')" class="hover:text-nodo-500">Eventos</Link>
      <span class="mx-1">/</span> {{ isEditing ? 'Editar' : 'Nuevo' }}
    </template>

    <div class="max-w-2xl space-y-5">
      <div class="flex items-center gap-3">
        <Link :href="route('eventos.admin.index')" class="p-2 hover:bg-gray-100 rounded-lg text-gray-500">
          <ArrowLeft :size="18" />
        </Link>
        <div>
          <h1 class="text-2xl font-black text-dark">{{ isEditing ? 'Editar evento' : 'Nuevo evento' }}</h1>
          <p class="text-sm text-gray-400 mt-0.5">{{ isEditing ? 'Modifica los datos del evento' : 'Crea un evento para la comunidad NODICO' }}</p>
        </div>
      </div>

      <form @submit.prevent="submit" class="space-y-5">
        <!-- Info principal -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wide">Información del evento</h2>
          <div>
            <label class="block text-sm font-medium text-dark mb-1.5">Título *</label>
            <input v-model="form.titulo" type="text" required maxlength="200" placeholder="Ej: Taller de Productividad"
              class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
            <p v-if="form.errors.titulo" class="text-red-500 text-xs mt-1">{{ form.errors.titulo }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-dark mb-1.5">Descripción</label>
            <textarea v-model="form.descripcion" rows="3" maxlength="1000" placeholder="¿De qué trata este evento?"
              class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition resize-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-dark mb-1.5">Lugar</label>
            <input v-model="form.lugar" type="text" maxlength="200"
              class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
          </div>
        </div>

        <!-- Fecha y hora -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wide">Fecha y hora</h2>
          <div>
            <label class="block text-sm font-medium text-dark mb-1.5">Fecha *</label>
            <input v-model="form.fecha" type="date" required
              class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
            <p v-if="form.errors.fecha" class="text-red-500 text-xs mt-1">{{ form.errors.fecha }}</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Hora inicio *</label>
              <input v-model="form.hora_inicio" type="time" required
                class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
            </div>
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Hora fin</label>
              <input v-model="form.hora_fin" type="time"
                class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
            </div>
          </div>
        </div>

        <!-- Capacidad y precio -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wide">Capacidad y costo</h2>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Cupo máximo</label>
              <input v-model="form.cupo_maximo" type="number" min="1" placeholder="Sin límite"
                class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
            </div>
            <div>
              <label class="block text-sm font-medium text-dark mb-1.5">Precio (MXN)</label>
              <input v-model="form.precio" type="number" min="0" step="0.01" placeholder="0 = Gratis"
                class="w-full border border-gray-200 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-nodo-400 outline-none transition" />
            </div>
          </div>
          <div class="space-y-3">
            <label class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
              <div>
                <p class="text-sm font-semibold text-dark">Solo para miembros</p>
                <p class="text-xs text-gray-500">Solo los miembros con membresía activa podrán ver este evento</p>
              </div>
              <div @click="form.solo_miembros = !form.solo_miembros"
                :class="['w-11 h-6 rounded-full transition-all cursor-pointer flex-shrink-0', form.solo_miembros ? 'bg-nodo-400' : 'bg-gray-300']">
                <div :class="['w-5 h-5 bg-white rounded-full shadow mt-0.5 transition-transform', form.solo_miembros ? 'translate-x-5' : 'translate-x-0.5']" />
              </div>
            </label>
            <label class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
              <div>
                <p class="text-sm font-semibold text-dark">Evento activo</p>
                <p class="text-xs text-gray-500">Visible en el sitio público</p>
              </div>
              <div @click="form.activo = !form.activo"
                :class="['w-11 h-6 rounded-full transition-all cursor-pointer flex-shrink-0', form.activo ? 'bg-emerald-500' : 'bg-gray-300']">
                <div :class="['w-5 h-5 bg-white rounded-full shadow mt-0.5 transition-transform', form.activo ? 'translate-x-5' : 'translate-x-0.5']" />
              </div>
            </label>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <Link :href="route('eventos.admin.index')" class="text-sm text-gray-500 hover:text-dark font-medium">← Cancelar</Link>
          <button type="submit" :disabled="form.processing"
            class="flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 disabled:opacity-50 text-dark px-6 py-3 rounded-xl text-sm font-bold transition-all shadow-sm">
            <Save :size="16" /> {{ form.processing ? 'Guardando...' : (isEditing ? 'Guardar cambios' : 'Crear evento') }}
          </button>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
