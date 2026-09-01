<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { FileText, FileCode, Clock } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import EncabezadoPortal from '@/Components/Portal/EncabezadoPortal.vue'

/** Fase 3 — «Mis pagos»: las órdenes del miembro con estado de pago y de factura. */
defineProps<{
  ordenes: Array<{
    id: number; referencia: string; plan: string; monto: number
    metodo_label: string; estado_pago: string; estado_pago_label: string
    estado_factura: string; estado_factura_label: string; pide_factura: boolean
    vence_el: string; reportado: boolean; tiene_pdf: boolean; tiene_xml: boolean; creada: string
  }>
}>()

const precio = (v: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v)

const fecha = (iso: string) =>
  new Date(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' })

/** Color del estado de pago (portal cálido: sin abusar del amarillo de marca para estado). */
const tonoPago = (estado: string) => ({
  confirmada: 'border-emerald-600/40 bg-emerald-50 text-emerald-800',
  generada:   'border-amber-600/40 bg-amber-50 text-amber-900',
  vencida:    'border-dark/25 bg-cream-50 text-dark/70',
  cancelada:  'border-dark/25 bg-cream-50 text-dark/60',
}[estado] ?? 'border-dark/20 bg-white text-dark/70')

const tonoFactura = (estado: string) => ({
  enviada:  'border-emerald-600/40 bg-emerald-50 text-emerald-800',
  emitida:  'border-emerald-600/40 bg-emerald-50 text-emerald-800',
  solicitada: 'border-amber-600/40 bg-amber-50 text-amber-900',
  no_solicitada: 'border-dark/20 bg-cream-50 text-dark/60',
}[estado] ?? 'border-dark/20 bg-white text-dark/70')
</script>

<template>
  <Head title="Mis pagos" />

  <PortalLayout>
    <EncabezadoPortal titulo="Mis pagos" etiqueta="Pagos" numero="01" />

    <p v-if="!ordenes.length" class="border-2 border-dashed border-dark/25 bg-cream-50 p-8 text-center font-body text-dark/60">
      Todavía no tienes órdenes de pago con referencia.
    </p>

    <ul v-else class="space-y-4">
      <li
        v-for="o in ordenes" :key="o.id"
        class="border-2 border-dark bg-white p-5 shadow-dura-sm"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="font-display text-lg font-extrabold tracking-tight text-dark">{{ o.referencia }}</p>
            <p class="mt-0.5 font-body text-sm text-dark/70">
              {{ o.plan }} · {{ precio(o.monto) }} · {{ o.metodo_label }}
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center border-2 px-2.5 py-1 font-mono text-[0.6875rem] uppercase tracking-[0.08em]" :class="tonoPago(o.estado_pago)">
              {{ o.estado_pago_label }}
            </span>
            <span v-if="o.pide_factura" class="inline-flex items-center border-2 px-2.5 py-1 font-mono text-[0.6875rem] uppercase tracking-[0.08em]" :class="tonoFactura(o.estado_factura)">
              {{ o.estado_factura_label }}
            </span>
          </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-dark/10 pt-3">
          <p class="font-body text-xs text-dark/60">
            Generada el {{ fecha(o.creada) }}
            <template v-if="o.estado_pago === 'generada'"> · vence el {{ fecha(o.vence_el) }}</template>
            <template v-if="o.reportado && o.estado_pago === 'generada'"> · reportada como pagada</template>
          </p>

          <div v-if="o.tiene_pdf || o.tiene_xml" class="flex gap-2">
            <a
              v-if="o.tiene_pdf" :href="route('portal.pagos.pdf', { orden: o.id })"
              class="inline-flex min-h-[40px] items-center gap-1.5 border-2 border-dark px-3 font-display text-xs font-bold text-dark hover:bg-dark hover:text-white"
            ><FileText :size="14" aria-hidden="true" /> PDF</a>
            <a
              v-if="o.tiene_xml" :href="route('portal.pagos.xml', { orden: o.id })"
              class="inline-flex min-h-[40px] items-center gap-1.5 border-2 border-dark px-3 font-display text-xs font-bold text-dark hover:bg-dark hover:text-white"
            ><FileCode :size="14" aria-hidden="true" /> XML</a>
          </div>
          <p v-else-if="o.pide_factura && o.estado_pago === 'confirmada'" class="inline-flex items-center gap-1.5 font-body text-xs text-dark/60">
            <Clock :size="13" aria-hidden="true" /> Tu factura está en proceso.
          </p>
        </div>
      </li>
    </ul>
  </PortalLayout>
</template>
