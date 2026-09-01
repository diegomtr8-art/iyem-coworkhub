<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Copy, Check, Landmark, Store, Clock, ArrowRight } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import EncabezadoPortal from '@/Components/Portal/EncabezadoPortal.vue'
import TarjetaPortal from '@/Components/Portal/TarjetaPortal.vue'

/** Fase 3 — la referencia y cómo pagarla, sin dudas. */
const props = defineProps<{
  orden: {
    id: number; referencia: string; plan: string; monto: number
    metodo: string; metodo_label: string; vence_el: string; reportado: boolean
    pide_factura: boolean
  }
  datosBancarios: { banco: string; clabe: string; beneficiario: string; cuenta: string } | null
}>()

const copiado = ref(false)
async function copiar(texto: string) {
  try {
    await navigator.clipboard.writeText(texto)
    copiado.value = true
    setTimeout(() => (copiado.value = false), 1800)
  } catch { /* sin portapapeles: la referencia igual está a la vista */ }
}

function yaPague() {
  router.post(route('portal.referencia.ya-pague', { orden: props.orden.id }), {}, { preserveScroll: true })
}

const precio = (v: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v)

const fecha = (iso: string) =>
  new Date(iso).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' })
</script>

<template>
  <Head title="Tu referencia de pago" />

  <PortalLayout>
    <div class="mx-auto max-w-xl">
      <EncabezadoPortal seccion="Pago" titulo="Tu referencia" />

      <!-- La referencia, en grande. -->
      <TarjetaPortal padding="lg" fondo="oscuro">
        <p class="etiqueta-tecnica text-nodo-400">Referencia</p>
        <div class="mt-2 flex flex-wrap items-center gap-3">
          <span class="font-display text-display-md font-black tracking-tight text-white">{{ orden.referencia }}</span>
          <button
            type="button" @click="copiar(orden.referencia)"
            class="inline-flex min-h-[44px] items-center gap-2 border-2 border-nodo-400 px-4 font-display text-sm font-bold
                   text-nodo-400 transition-colors hover:bg-nodo-400 hover:text-dark focus-visible:outline
                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-400"
          >
            <component :is="copiado ? Check : Copy" :size="16" aria-hidden="true" />
            {{ copiado ? 'Copiada' : 'Copiar' }}
          </button>
        </div>

        <dl class="mt-6 grid grid-cols-2 gap-4 border-t-2 border-white/15 pt-5">
          <div>
            <dt class="etiqueta-tecnica text-nodo-400">Monto exacto</dt>
            <dd class="mt-1 font-display text-xl font-extrabold text-white">{{ precio(orden.monto) }}</dd>
          </div>
          <div>
            <dt class="etiqueta-tecnica text-nodo-400">Paga antes del</dt>
            <dd class="mt-1 font-body text-sm first-letter:uppercase text-cream">{{ fecha(orden.vence_el) }}</dd>
          </div>
        </dl>
      </TarjetaPortal>

      <!-- Instrucciones según el método. -->
      <TarjetaPortal etiqueta="Cómo pagar" numero="01" class="mt-6">
        <template v-if="datosBancarios">
          <div class="flex items-start gap-3">
            <Landmark :size="22" class="mt-0.5 shrink-0 text-dark" aria-hidden="true" />
            <div class="min-w-0 font-body text-sm text-dark">
              <p>Haz una transferencia con estos datos y <strong>pon tu referencia en el concepto</strong>:</p>
              <dl class="mt-3 space-y-1.5">
                <div v-if="datosBancarios.banco" class="flex justify-between gap-3 border-b border-dark/10 pb-1.5">
                  <dt class="text-dark/60">Banco</dt><dd class="font-bold text-dark">{{ datosBancarios.banco }}</dd>
                </div>
                <div v-if="datosBancarios.clabe" class="flex justify-between gap-3 border-b border-dark/10 pb-1.5">
                  <dt class="text-dark/60">CLABE</dt>
                  <dd class="flex items-center gap-2 font-mono font-bold text-dark">
                    {{ datosBancarios.clabe }}
                    <button type="button" @click="copiar(datosBancarios.clabe)" class="text-dark/50 hover:text-dark" aria-label="Copiar CLABE"><Copy :size="14" /></button>
                  </dd>
                </div>
                <div v-if="datosBancarios.beneficiario" class="flex justify-between gap-3 border-b border-dark/10 pb-1.5">
                  <dt class="text-dark/60">Beneficiario</dt><dd class="font-bold text-dark">{{ datosBancarios.beneficiario }}</dd>
                </div>
              </dl>
              <p v-if="!datosBancarios.clabe" class="mt-3 border-2 border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                Aún no están cargados los datos bancarios. Pásate por recepción o escríbenos y te los damos.
              </p>
            </div>
          </div>
        </template>

        <template v-else>
          <div class="flex items-start gap-3">
            <Store :size="22" class="mt-0.5 shrink-0 text-dark" aria-hidden="true" />
            <p class="font-body text-sm text-dark">
              Pásate por <strong>recepción</strong> y dicta tu referencia <strong>{{ orden.referencia }}</strong>.
              Ahí registran tu pago en efectivo.
            </p>
          </div>
        </template>
      </TarjetaPortal>

      <!-- Qué sigue. -->
      <div class="mt-6 flex items-start gap-3 border-2 border-dark bg-nodo-400/20 p-4">
        <Clock :size="20" class="mt-0.5 shrink-0 text-dark" aria-hidden="true" />
        <p class="font-body text-sm text-dark">
          Tu membresía se activa <strong>en cuanto contabilidad confirme tu pago</strong>.
          <template v-if="orden.pide_factura"> Tu factura llega después, por correo.</template>
        </p>
      </div>

      <!-- Ya pagué + mis pagos. -->
      <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <button
          type="button" @click="yaPague" :disabled="orden.reportado"
          class="inline-flex min-h-[48px] items-center gap-2 border-2 border-dark px-5 font-display text-sm font-bold
                 text-dark transition-all duration-200 ease-salida hover:-translate-y-0.5 hover:shadow-dura-sm
                 disabled:cursor-default disabled:opacity-60 focus-visible:outline focus-visible:outline-2
                 focus-visible:outline-offset-2 focus-visible:outline-dark"
        >
          <Check :size="16" aria-hidden="true" />
          {{ orden.reportado ? 'Ya avisaste que pagaste' : 'Ya pagué' }}
        </button>
        <Link :href="route('portal.pagos')" class="inline-flex items-center gap-1.5 font-display text-sm font-bold text-dark underline decoration-nodo-500 decoration-2 underline-offset-4">
          Mis pagos <ArrowRight :size="15" aria-hidden="true" />
        </Link>
      </div>
    </div>
  </PortalLayout>
</template>
