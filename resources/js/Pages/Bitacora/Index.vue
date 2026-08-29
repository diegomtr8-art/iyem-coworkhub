<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ShieldAlert, Search } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import debounce from 'lodash/debounce'

const props = defineProps<{
  eventos: any
  tipos: Array<{ valor: string; etiqueta: string }>
  filtros: { tipo: string; correo: string; fallos: boolean }
}>()

const tipo = ref(props.filtros.tipo)
const correo = ref(props.filtros.correo)
const soloFallos = ref(props.filtros.fallos)

const aplicar = debounce(() => {
  router.get(
    route('bitacora.index'),
    { tipo: tipo.value || undefined, correo: correo.value || undefined, fallos: soloFallos.value || undefined },
    { preserveState: true, replace: true },
  )
}, 300)

watch([tipo, correo, soloFallos], aplicar)
</script>

<template>
  <Head title="Bitácora de acceso" />

  <AuthenticatedLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Bitácora de acceso</h1>
        <p class="mt-1 text-sm text-gray-500">
          Ingresos, fallos y bloqueos. Los intentos contra correos que no existen también
          aparecen: son los que delatan un barrido.
        </p>
      </div>

      <!-- Filtros -->
      <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4">
        <label class="relative flex-1 min-w-[220px]">
          <span class="sr-only">Buscar por correo</span>
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" aria-hidden="true" />
          <input
            v-model="correo"
            type="search"
            placeholder="Buscar por correo"
            class="min-h-[44px] w-full rounded-xl border-gray-200 pl-9 text-base focus:border-nodo-400 focus:ring-nodo-400"
          />
        </label>

        <label class="min-w-[200px]">
          <span class="sr-only">Tipo de evento</span>
          <select
            v-model="tipo"
            class="min-h-[44px] w-full rounded-xl border-gray-200 text-base focus:border-nodo-400 focus:ring-nodo-400"
          >
            <option value="">Todos los eventos</option>
            <option v-for="t in tipos" :key="t.valor" :value="t.valor">{{ t.etiqueta }}</option>
          </select>
        </label>

        <label class="flex min-h-[44px] cursor-pointer items-center gap-2 px-2 text-sm font-medium text-gray-700">
          <input v-model="soloFallos" type="checkbox" class="rounded border-gray-300 text-nodo-500 focus:ring-nodo-400" />
          Solo fallos
        </label>
      </div>

      <!-- Tabla -->
      <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
            <tr>
              <th scope="col" class="px-4 py-3 font-semibold">Evento</th>
              <th scope="col" class="px-4 py-3 font-semibold">Cuenta</th>
              <th scope="col" class="px-4 py-3 font-semibold">Cuándo</th>
              <th scope="col" class="px-4 py-3 font-semibold">IP</th>
              <th scope="col" class="px-4 py-3 font-semibold">Navegador</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="evento in eventos.data" :key="evento.id" :class="{ 'bg-red-50/40': !evento.exito }">
              <td class="px-4 py-3">
                <span class="flex items-center gap-2 font-medium text-gray-900">
                  <ShieldAlert v-if="evento.delicado" class="h-4 w-4 shrink-0 text-amber-500" aria-hidden="true" />
                  {{ evento.etiqueta }}
                </span>
                <span
                  v-if="evento.contexto?.espera_segundos"
                  class="mt-0.5 block text-xs text-gray-500"
                >Espera impuesta: {{ evento.contexto.espera_segundos }} s</span>
              </td>
              <td class="px-4 py-3">
                <span v-if="evento.usuario" class="block font-medium text-gray-900">{{ evento.usuario.name }}</span>
                <span class="block text-gray-500">{{ evento.correo || '—' }}</span>
                <span v-if="!evento.usuario && evento.correo" class="text-xs text-gray-400">Sin cuenta</span>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-gray-500">{{ evento.cuando }}</td>
              <td class="whitespace-nowrap px-4 py-3 text-gray-500">{{ evento.ip || '—' }}</td>
              <td class="max-w-xs truncate px-4 py-3 text-gray-400" :title="evento.agente">{{ evento.agente || '—' }}</td>
            </tr>

            <tr v-if="!eventos.data.length">
              <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                No hay eventos que coincidan con el filtro.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <nav v-if="eventos.links.length > 3" class="flex flex-wrap gap-1" aria-label="Paginación">
        <component
          :is="enlace.url ? Link : 'span'"
          v-for="enlace in eventos.links"
          :key="enlace.label"
          :href="enlace.url"
          class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg px-3 text-sm"
          :class="enlace.active
            ? 'bg-gray-900 font-semibold text-white'
            : enlace.url ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-300'"
          v-html="enlace.label"
        />
      </nav>
    </div>
  </AuthenticatedLayout>
</template>
