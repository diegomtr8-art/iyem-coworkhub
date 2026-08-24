<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Search, Eye, UserPlus } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import debounce from 'lodash/debounce'

interface PaginatedData<T> { data: T[]; links: any[]; meta: any }
interface Miembro {
  id: number; name: string; email: string; empresa: string|null
  telefono: string|null; face_id_ok: boolean; suscripciones: any[]
}

const props = defineProps<{
  miembros: PaginatedData<Miembro>
  filters: { search?: string }
}>()

const search = ref(props.filters.search ?? '')
const applyFilters = () => {
  router.get(route('miembros.index'), { search: search.value }, { preserveState: true, replace: true })
}
watch(search, debounce(applyFilters, 300))

function suscripcionActiva(suscripciones: any[]) {
  const s = suscripciones?.find(s => s.estatus === 'Activa')
  if (!s) return null
  const dias = Math.max(0, Math.ceil((new Date(s.fecha_fin).getTime() - Date.now()) / 86400000))
  return { dias, plan: s.plan?.nombre }
}
</script>

<template>
  <Head title="Miembros — NODICO Admin" />
  <AuthenticatedLayout>
    <template #breadcrumb>Miembros</template>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-black text-dark">Miembros</h1>
          <p class="text-sm text-gray-400 mt-1">{{ miembros.meta?.total ?? miembros.data.length }} miembros registrados</p>
        </div>
      </div>

      <!-- Search -->
      <div class="relative max-w-sm">
        <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input v-model="search" type="search" placeholder="Buscar por nombre o email..."
          class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-nodo-400 focus:border-transparent outline-none" />
      </div>

      <!-- Table -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm min-w-[640px]">
            <thead class="bg-gray-50 border-b border-gray-100">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Miembro</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Empresa</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Plan activo</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Vence</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Face ID</th>
                <th class="px-5 py-3" />
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="!miembros.data.length">
                <td colspan="6" class="px-5 py-10 text-center text-gray-400">
                  Sin miembros encontrados
                </td>
              </tr>
              <tr v-for="m in miembros.data" :key="m.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-nodo-100 text-nodo-700 rounded-full flex items-center justify-center text-sm font-bold uppercase flex-shrink-0">
                      {{ m.name?.charAt(0) }}
                    </div>
                    <div>
                      <div class="font-semibold text-dark">{{ m.name }}</div>
                      <div class="text-xs text-gray-400">{{ m.email }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4 text-gray-600">{{ m.empresa ?? '—' }}</td>
                <td class="px-5 py-4">
                  <span v-if="suscripcionActiva(m.suscripciones)" class="px-2.5 py-1 bg-nodo-50 text-nodo-700 rounded-full text-xs font-semibold">
                    {{ suscripcionActiva(m.suscripciones)?.plan }}
                  </span>
                  <span v-else class="text-gray-400 text-xs">Sin plan</span>
                </td>
                <td class="px-5 py-4">
                  <span v-if="suscripcionActiva(m.suscripciones)"
                    :class="['font-semibold text-sm', (suscripcionActiva(m.suscripciones)?.dias ?? 99) <= 5 ? 'text-red-600' : 'text-gray-700']">
                    {{ suscripcionActiva(m.suscripciones)?.dias }}d
                  </span>
                  <span v-else class="text-gray-300">—</span>
                </td>
                <td class="px-5 py-4">
                  <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', m.face_id_ok ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">
                    {{ m.face_id_ok ? '✓ Registrado' : '⏳ Pendiente' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-right">
                  <Link :href="route('miembros.show', m.id)"
                    class="inline-flex items-center gap-1.5 text-xs text-nodo-600 hover:text-nodo-800 font-semibold bg-nodo-50 hover:bg-nodo-100 px-3 py-1.5 rounded-lg transition-all">
                    <Eye :size="13" /> Ver detalles
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="miembros.links?.length > 3" class="px-5 py-3 border-t border-gray-100 flex justify-end gap-1">
          <template v-for="link in miembros.links" :key="link.label">
            <Link v-if="link.url" :href="link.url"
              :class="['px-3 py-1.5 text-xs rounded-lg', link.active ? 'bg-nodo-400 text-dark font-bold' : 'text-gray-600 hover:bg-gray-100']"
              v-html="link.label" />
            <span v-else class="px-3 py-1.5 text-xs text-gray-300" v-html="link.label" />
          </template>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
