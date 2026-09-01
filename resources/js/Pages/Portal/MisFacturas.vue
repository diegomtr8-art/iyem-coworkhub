<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { FileText, FileCode } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import EncabezadoPortal from '@/Components/Portal/EncabezadoPortal.vue'

/** «Mis facturas»: las facturas que contabilidad ya emitió, listas para descargar. */
defineProps<{
  facturas: Array<{
    id: number; referencia: string; plan: string; monto: number
    folio_fiscal: string | null; estado_factura: string; estado_factura_label: string
    emitida_en: string | null; enviada_en: string | null; tiene_xml: boolean
  }>
}>()

const precio = (v: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v)

const fecha = (iso: string | null) =>
  iso ? new Date(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
</script>

<template>
  <Head title="Mis facturas" />

  <PortalLayout>
    <EncabezadoPortal titulo="Mis facturas" etiqueta="Facturación" numero="02" />

    <p v-if="!facturas.length" class="border-2 border-dashed border-dark/25 bg-cream-50 p-8 text-center font-body text-dark/60">
      Aún no tienes facturas emitidas. Cuando pidas factura de un pago y contabilidad la emita, aparecerá aquí para descargarla.
    </p>

    <ul v-else class="space-y-4">
      <li
        v-for="f in facturas" :key="f.id"
        class="border-2 border-dark bg-white p-5 shadow-dura-sm"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="font-display text-lg font-extrabold tracking-tight text-dark">
              {{ f.folio_fiscal || f.referencia }}
            </p>
            <p class="mt-0.5 font-body text-sm text-dark/70">
              {{ f.plan }} · {{ precio(f.monto) }}
              <span v-if="f.folio_fiscal" class="text-dark/50"> · ref. {{ f.referencia }}</span>
            </p>
          </div>
          <span class="inline-flex items-center border-2 border-emerald-600/40 bg-emerald-50 px-2.5 py-1 font-mono text-[0.6875rem] uppercase tracking-[0.08em] text-emerald-800">
            {{ f.estado_factura_label }}
          </span>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-dark/10 pt-3">
          <p class="font-body text-xs text-dark/60">
            Emitida el {{ fecha(f.emitida_en) }}
            <template v-if="f.enviada_en"> · enviada a tu correo el {{ fecha(f.enviada_en) }}</template>
          </p>

          <div class="flex gap-2">
            <a
              :href="route('portal.pagos.pdf', { orden: f.id })"
              class="inline-flex min-h-[40px] items-center gap-1.5 border-2 border-dark px-3 font-display text-xs font-bold text-dark hover:bg-dark hover:text-white"
            ><FileText :size="14" aria-hidden="true" /> PDF</a>
            <a
              v-if="f.tiene_xml" :href="route('portal.pagos.xml', { orden: f.id })"
              class="inline-flex min-h-[40px] items-center gap-1.5 border-2 border-dark px-3 font-display text-xs font-bold text-dark hover:bg-dark hover:text-white"
            ><FileCode :size="14" aria-hidden="true" /> XML</a>
          </div>
        </div>
      </li>
    </ul>
  </PortalLayout>
</template>
