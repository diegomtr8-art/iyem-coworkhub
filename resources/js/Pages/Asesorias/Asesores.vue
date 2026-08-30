<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Plus, Pencil } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'
import Estado from '@/Components/Panel/Estado.vue'

/**
 * Catálogo de asesores del IYEM (Fase 3.7).
 *
 * Decisión de Nódico: son un catálogo, **no usuarios**. No tienen cuenta ni
 * entran al sistema. Darlos de baja no borra el histórico: cada asesoría guarda
 * el nombre tal como era al confirmarse.
 */
defineProps<{ asesores: any[] }>()

const editando = ref<any | null>(null)
const abierto = ref(false)

const form = useForm({
  nombre: '', especialidad: '', email: '', telefono: '', notas: '', activo: true,
})

function abrir(asesor: any | null = null) {
  editando.value = asesor
  form.reset()
  form.clearErrors()

  if (asesor) {
    form.nombre = asesor.nombre
    form.especialidad = asesor.especialidad ?? ''
    form.email = asesor.email ?? ''
    form.telefono = asesor.telefono ?? ''
    form.notas = asesor.notas ?? ''
    form.activo = asesor.activo
  }

  abierto.value = true
}

function guardar() {
  const opciones = { preserveScroll: true, onSuccess: () => { abierto.value = false } }

  editando.value
    ? form.patch(route('asesores.update', editando.value.id), opciones)
    : form.post(route('asesores.store'), opciones)
}
</script>

<template>
  <Head title="Asesores" />

  <AuthenticatedLayout>
    <template #breadcrumb>
      <Link :href="route('asesorias.index')" class="hover:text-dark">Asesorías</Link>
      <span class="font-display font-bold text-dark">· Catálogo de asesores</span>
    </template>

    <Panel titulo="Asesores del IYEM" :contador="asesores.length" padding="none">
      <template #acciones>
        <button
          type="button"
          class="flex min-h-[32px] items-center gap-1.5 border border-dark bg-nodo-400 px-2.5
                 font-display text-xs font-bold text-dark transition-colors hover:bg-nodo-500
                 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                 focus-visible:outline-dark"
          @click="abrir()"
        ><Plus :size="13" aria-hidden="true" /> Alta</button>
      </template>

      <p v-if="!asesores.length" class="px-4 py-10 text-center text-sm text-dark/50">
        Todavía no hay asesores. Sin al menos uno, recepción no puede confirmar asesorías.
      </p>

      <ul v-else class="divide-y divide-dark/10">
        <li
          v-for="a in asesores" :key="a.id"
          class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
          :class="a.activo ? '' : 'bg-cream-50/60'"
        >
          <div class="min-w-0">
            <p class="flex items-center gap-2 font-display text-sm font-bold" :class="a.activo ? 'text-dark' : 'text-dark/50'">
              {{ a.nombre }}
              <Estado v-if="!a.activo" tono="neutro" texto="baja" :punto="false" />
            </p>
            <p class="mt-0.5 text-xs text-dark/60">
              <template v-if="a.especialidad">{{ a.especialidad }}</template>
              <template v-if="a.email"> · {{ a.email }}</template>
              <template v-if="a.telefono"> · {{ a.telefono }}</template>
            </p>
          </div>

          <div class="flex shrink-0 items-center gap-3">
            <span class="font-mono text-[0.6875rem] text-dark/50">{{ a.asesorias }} asesorías</span>
            <button
              type="button"
              class="flex min-h-[32px] items-center gap-1.5 border border-dark/25 px-2.5
                     font-display text-xs font-bold text-dark transition-colors hover:border-dark
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                     focus-visible:outline-dark"
              @click="abrir(a)"
            ><Pencil :size="12" aria-hidden="true" /> Editar</button>
          </div>
        </li>
      </ul>
    </Panel>

    <Teleport to="body">
      <div
        v-if="abierto"
        class="fixed inset-0 z-50 flex items-center justify-center bg-tinta/60 p-4"
        role="dialog" aria-modal="true"
        @click.self="abierto = false"
      >
        <form class="w-full max-w-md border-2 border-dark bg-white" @submit.prevent="guardar">
          <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
            {{ editando ? 'Editar asesor' : 'Alta de asesor' }}
          </h2>

          <div class="space-y-3 p-4">
            <div>
              <label for="as-nombre" class="mb-1 block text-xs font-bold text-dark">Nombre</label>
              <input id="as-nombre" v-model="form.nombre" type="text" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-700">{{ form.errors.nombre }}</p>
            </div>

            <div>
              <label for="as-esp" class="mb-1 block text-xs font-bold text-dark">Especialidad</label>
              <input id="as-esp" v-model="form.especialidad" type="text" placeholder="Finanzas, modelo de negocio, legal…" class="w-full border border-dark/25 px-2.5 py-2 text-sm placeholder:text-dark/35 focus:border-dark" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label for="as-mail" class="mb-1 block text-xs font-bold text-dark">Correo</label>
                <input id="as-mail" v-model="form.email" type="email" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              </div>
              <div>
                <label for="as-tel" class="mb-1 block text-xs font-bold text-dark">Teléfono</label>
                <input id="as-tel" v-model="form.telefono" type="tel" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              </div>
            </div>

            <div>
              <label for="as-notas" class="mb-1 block text-xs font-bold text-dark">Notas</label>
              <textarea id="as-notas" v-model="form.notas" rows="2" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
            </div>

            <label class="flex items-center gap-2 text-xs text-dark">
              <input v-model="form.activo" type="checkbox" class="h-4 w-4 rounded-none border-dark" />
              Activo (aparece al confirmar asesorías)
            </label>

            <p v-if="editando" class="text-[0.6875rem] text-dark/55">
              Darlo de baja no borra su historial: las asesorías que dio siguen a su nombre.
            </p>
          </div>

          <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="abierto = false">Cancelar</button>
            <button type="submit" :disabled="form.processing" class="min-h-[36px] border border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark">Guardar</button>
          </div>
        </form>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
