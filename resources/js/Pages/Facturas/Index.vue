<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Search, CheckCircle, X } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import debounce from 'lodash/debounce'

defineProps<{ facturas: any; filters: any }>()

const search = ref('')
const estatus = ref('')
const showModal = ref(false)
const selectedFactura = ref<any>(null)
const form = useForm({ metodo_pago: 'Transferencia' })

watch([search, estatus], debounce(() => {
  router.get(route('facturas.index'), { search: search.value, estatus: estatus.value }, { preserveState: true, replace: true })
}, 300))

const openPagar = (f: any) => { selectedFactura.value = f; showModal.value = true }
const pagar = () => {
  form.post(route('facturas.pagar', selectedFactura.value.id), {
    onSuccess: () => { showModal.value = false; form.reset() }
  })
}

const estatusBadge: Record<string, string> = {
  Pagada: 'bg-emerald-50 text-emerald-700',
  Pendiente: 'bg-yellow-50 text-yellow-700',
  Cancelada: 'bg-gray-100 text-gray-500',
}
</script>

<template>
  <Head title="Facturas — CoworkHub" />
  <AuthenticatedLayout>
    <template #breadcrumb>Facturas</template>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Facturas</h1>

      <div class="flex gap-3 flex-wrap">
        <div class="relative flex-1 min-w-48">
          <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input v-model="search" type="search" placeholder="Buscar miembro..."
            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-violet-500 outline-none" />
        </div>
        <select v-model="estatus" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
          <option value="">Todos los estatus</option>
          <option>Pendiente</option><option>Pagada</option><option>Cancelada</option>
        </select>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Folio</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Miembro</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Concepto</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Total</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Estatus</th>
              <th class="px-5 py-3" />
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="!facturas.data?.length">
              <td colspan="6" class="py-8 text-center text-gray-400">Sin facturas encontradas</td>
            </tr>
            <tr v-for="f in facturas.data" :key="f.id" class="hover:bg-gray-50">
              <td class="px-5 py-3 font-mono text-xs text-gray-600">{{ f.folio }}</td>
              <td class="px-5 py-3">
                <div class="font-medium text-gray-900">{{ f.user?.name }}</div>
              </td>
              <td class="px-5 py-3 text-gray-600 max-w-xs truncate">{{ f.concepto }}</td>
              <td class="px-5 py-3 font-bold text-gray-900">${{ Number(f.total).toLocaleString('es-MX') }}</td>
              <td class="px-5 py-3">
                <span :class="['px-2.5 py-1 rounded-full text-xs font-medium', estatusBadge[f.estatus] ?? 'bg-gray-100 text-gray-600']">{{ f.estatus }}</span>
              </td>
              <td class="px-5 py-3 text-right">
                <button v-if="f.estatus === 'Pendiente'"
                  @click="openPagar(f)"
                  class="flex items-center gap-1.5 text-xs bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-3 py-1.5 rounded-lg font-medium transition-all">
                  <CheckCircle :size="12" /> Marcar pagada
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="facturas.links?.length > 3" class="px-5 py-3 border-t border-gray-100 flex justify-end gap-1">
          <template v-for="link in facturas.links" :key="link.label">
            <Link v-if="link.url" :href="link.url"
              :class="['px-3 py-1.5 text-xs rounded-lg', link.active ? 'bg-violet-600 text-white' : 'text-gray-600 hover:bg-gray-100']"
              v-html="link.label" />
            <span v-else class="px-3 py-1.5 text-xs text-gray-300" v-html="link.label" />
          </template>
        </div>
      </div>
    </div>

    <!-- Modal pago -->
    <div v-if="showModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-gray-900">Registrar pago</h3>
          <button @click="showModal = false" class="text-gray-400 hover:text-gray-600"><X :size="18" /></button>
        </div>
        <p class="text-sm text-gray-600 mb-4">
          Factura <span class="font-mono font-semibold">{{ selectedFactura?.folio }}</span> —
          <span class="font-bold text-gray-900">${{ Number(selectedFactura?.total).toLocaleString('es-MX') }}</span>
        </p>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
          <select v-model="form.metodo_pago" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
            <option>Efectivo</option><option>Transferencia</option><option>Tarjeta</option>
          </select>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="showModal = false" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50">Cancelar</button>
          <button @click="pagar" :disabled="form.processing"
            class="flex items-center gap-2 px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl disabled:opacity-50">
            <CheckCircle :size="14" /> Confirmar pago
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
