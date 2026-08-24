<script setup lang="ts">
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { Receipt } from 'lucide-vue-next'

defineProps<{ facturas: any }>()

const estatusBadge: Record<string, string> = {
  Pagada: 'bg-emerald-50 text-emerald-700',
  Pendiente: 'bg-yellow-50 text-yellow-700',
  Cancelada: 'bg-gray-100 text-gray-500',
}
</script>

<template>
  <Head title="Mis facturas — CoworkHub" />
  <PortalLayout>
    <div class="max-w-3xl mx-auto space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Mis facturas</h1>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div v-if="!facturas.data?.length" class="py-16 text-center text-gray-400">
          <Receipt :size="48" class="mx-auto mb-3 opacity-30" />
          <p>Sin facturas registradas</p>
        </div>
        <table v-else class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Folio</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Concepto</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fecha</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Total</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Estatus</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="f in facturas.data" :key="f.id" class="hover:bg-gray-50">
              <td class="px-5 py-3 font-mono text-xs text-gray-600">{{ f.folio }}</td>
              <td class="px-5 py-3 text-gray-700 max-w-xs">
                <div class="truncate">{{ f.concepto }}</div>
                <div v-if="f.suscripcion?.plan" class="text-xs text-gray-400">{{ f.suscripcion.plan.nombre }}</div>
              </td>
              <td class="px-5 py-3 text-gray-600">
                {{ f.fecha ? new Date(f.fecha).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : '—' }}
              </td>
              <td class="px-5 py-3 font-bold text-gray-900">${{ Number(f.total).toLocaleString('es-MX') }}</td>
              <td class="px-5 py-3">
                <div class="flex flex-col gap-0.5">
                  <span :class="['px-2.5 py-1 rounded-full text-xs font-medium inline-block w-fit', estatusBadge[f.estatus] ?? 'bg-gray-100 text-gray-600']">
                    {{ f.estatus }}
                  </span>
                  <span v-if="f.fecha_pago" class="text-xs text-gray-400">
                    Pagada {{ new Date(f.fecha_pago).toLocaleDateString('es-MX') }}
                  </span>
                </div>
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
  </PortalLayout>
</template>
