<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Upload, Send, X, Copy, Download, ArrowLeft } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'
import Estado from '@/Components/Panel/Estado.vue'

/** Fase 5 — bandeja de facturas por emitir. */
defineProps<{ ordenes: any }>()

const abierta = ref<any | null>(null)
const subir = useForm<{ folio_fiscal: string; pdf: File | null; xml: File | null }>({ folio_fiscal: '', pdf: null, xml: null })

function abrir(orden: any) { abierta.value = orden; subir.reset(); subir.clearErrors() }
function enviarSubida() {
  subir.post(route('caja.factura.subir', { orden: abierta.value.id }), {
    forceFormData: true, preserveScroll: true, onSuccess: () => { abierta.value = null },
  })
}
function enviarAlMiembro(orden: any) {
  router.post(route('caja.factura.enviar', { orden: orden.id }), {}, { preserveScroll: true })
}

const copiar = (t: string) => { try { navigator.clipboard.writeText(t) } catch { /* nada */ } }
const precio = (v: number) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v)
const fecha = (iso: string) => iso ? new Date(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }) : '—'
</script>

<template>
  <Head title="Caja · Facturas por emitir" />

  <AuthenticatedLayout>
    <template #breadcrumb><span class="font-display font-bold text-dark">Caja · Facturas por emitir</span></template>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <Link :href="route('caja.ordenes')" class="inline-flex items-center gap-1.5 font-display text-sm font-bold text-dark underline decoration-dark/30 underline-offset-4 hover:decoration-dark">
        <ArrowLeft :size="15" aria-hidden="true" /> Volver a órdenes
      </Link>
      <a :href="route('caja.facturas.exportar')" class="inline-flex min-h-[40px] items-center gap-1.5 border-2 border-dark px-3 font-display text-xs font-bold text-dark hover:bg-dark hover:text-white">
        <Download :size="14" aria-hidden="true" /> Exportar a Excel
      </a>
    </div>

    <Panel titulo="Confirmadas que piden factura" :contador="ordenes.total" padding="none">
      <p v-if="!ordenes.data.length" class="px-4 py-10 text-center text-sm text-dark/50">No hay facturas pendientes de emitir.</p>

      <ul v-else class="divide-y divide-dark/10">
        <li v-for="o in ordenes.data" :key="o.id" class="p-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-mono text-sm font-bold text-dark">{{ o.referencia }}</p>
              <p class="mt-0.5 text-sm text-dark/70">{{ o.miembro }} · {{ o.concepto }} · {{ precio(o.monto) }}</p>
              <p class="mt-0.5 font-mono text-[0.6875rem] uppercase text-dark/55">Forma de pago: {{ o.forma_pago }} · confirmada {{ fecha(o.confirmada) }}</p>
            </div>
            <div class="flex items-center gap-2">
              <Estado :tono="o.estado_factura === 'emitida' ? 'bien' : 'atencion'" :texto="o.estado_factura_label" />
              <button v-if="o.estado_factura === 'solicitada'" type="button" @click="abrir(o)"
                class="flex min-h-[36px] items-center gap-1.5 border-2 border-dark bg-nodo-400 px-3 font-display text-xs font-bold text-dark">
                <Upload :size="13" aria-hidden="true" /> Subir factura
              </button>
              <button v-else-if="o.estado_factura === 'emitida'" type="button" @click="enviarAlMiembro(o)"
                class="flex min-h-[36px] items-center gap-1.5 border-2 border-dark px-3 font-display text-xs font-bold text-dark hover:bg-dark hover:text-white">
                <Send :size="13" aria-hidden="true" /> Enviar al miembro
              </button>
            </div>
          </div>

          <!-- Datos fiscales tal como estaban al generarse, listos para copiar. -->
          <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 border-t border-dark/10 pt-3 text-xs sm:grid-cols-3">
            <div v-for="d in [
              { k: 'RFC', v: o.fiscal.rfc }, { k: 'Razón social', v: o.fiscal.razon_social },
              { k: 'Régimen', v: o.fiscal.regimen }, { k: 'Uso CFDI', v: o.fiscal.uso_cfdi },
              { k: 'CP', v: o.fiscal.cp }, { k: 'Correo', v: o.fiscal.email },
            ]" :key="d.k" class="min-w-0">
              <dt class="font-mono uppercase tracking-[0.08em] text-dark/45">{{ d.k }}</dt>
              <dd class="flex items-center gap-1.5 text-dark">
                <span class="truncate">{{ d.v || '—' }}</span>
                <button v-if="d.v" type="button" @click="copiar(d.v)" class="shrink-0 text-dark/40 hover:text-dark" :aria-label="`Copiar ${d.k}`"><Copy :size="12" /></button>
              </dd>
            </div>
          </dl>
        </li>
      </ul>
    </Panel>

    <!-- Modal: subir la factura -->
    <Teleport to="body">
      <div v-if="abierta" class="fixed inset-0 z-50 flex items-end justify-center bg-tinta/60 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" @click.self="abierta = null">
        <form class="w-full max-w-md border-2 border-dark bg-white" @submit.prevent="enviarSubida">
          <div class="flex items-center justify-between border-b border-dark/15 bg-cream-50 px-4 py-3">
            <h2 class="font-display text-sm font-bold text-dark">Subir factura · {{ abierta.referencia }}</h2>
            <button type="button" aria-label="Cerrar" class="text-dark/50 hover:text-dark" @click="abierta = null"><X :size="18" /></button>
          </div>
          <div class="space-y-3 p-4">
            <p class="font-body text-xs text-dark/60">Sube el PDF y el XML que emitió contabilidad, y captura el folio fiscal.</p>
            <div>
              <label for="fa-folio" class="mb-1 block text-xs font-bold text-dark">Folio fiscal (UUID)</label>
              <input id="fa-folio" v-model="subir.folio_fiscal" type="text" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              <p v-if="subir.errors.folio_fiscal" class="mt-1 text-xs text-red-700">{{ subir.errors.folio_fiscal }}</p>
            </div>
            <div>
              <label for="fa-pdf" class="mb-1 block text-xs font-bold text-dark">Archivo PDF</label>
              <input id="fa-pdf" type="file" accept="application/pdf" class="w-full text-sm" @change="subir.pdf = ($event.target as HTMLInputElement).files?.[0] ?? null" />
              <p v-if="subir.errors.pdf" class="mt-1 text-xs text-red-700">{{ subir.errors.pdf }}</p>
            </div>
            <div>
              <label for="fa-xml" class="mb-1 block text-xs font-bold text-dark">Archivo XML</label>
              <input id="fa-xml" type="file" accept="text/xml,application/xml,.xml" class="w-full text-sm" @change="subir.xml = ($event.target as HTMLInputElement).files?.[0] ?? null" />
              <p v-if="subir.errors.xml" class="mt-1 text-xs text-red-700">{{ subir.errors.xml }}</p>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[40px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="abierta = null">Cancelar</button>
            <button type="submit" :disabled="subir.processing" class="min-h-[40px] border-2 border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark disabled:opacity-50">
              {{ subir.processing ? 'Subiendo…' : 'Cargar factura' }}
            </button>
          </div>
        </form>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
