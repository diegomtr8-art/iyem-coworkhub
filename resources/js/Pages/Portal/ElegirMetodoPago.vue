<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { CreditCard, Banknote, Check, X, AlertTriangle, ArrowRight } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import EncabezadoPortal from '@/Components/Portal/EncabezadoPortal.vue'
import TarjetaPortal from '@/Components/Portal/TarjetaPortal.vue'

/**
 * Fase 2 — elegir cómo pagar. La consecuencia (con o sin factura) va escrita en
 * cada tarjeta, antes de cualquier formulario: descubrirla tarde es justo el
 * problema que se resuelve.
 */
const props = defineProps<{
  plan: { id: number; nombre: string; precio: number; periodo_label: string; recurrente: boolean }
  tieneDatosFiscales: boolean
  vencimientoDias: number
}>()

const form = useForm({ metodo: 'transferencia', pide_factura: false })

const faltanFiscales = () => form.pide_factura && !props.tieneDatosFiscales

function generar() {
  if (faltanFiscales()) return
  form.post(route('portal.referencia.generar', { plan: props.plan.id }))
}

const precio = (v: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v)
</script>

<template>
  <Head :title="`Contratar ${plan.nombre}`" />

  <PortalLayout>
    <div class="mx-auto max-w-3xl">
      <EncabezadoPortal seccion="Contratar" :titulo="plan.nombre" />

      <p class="mb-6 font-body text-cuerpo text-dark/70">
        {{ precio(plan.precio) }} · {{ plan.periodo_label }}. Elige cómo pagar; cada opción dice qué implica.
      </p>

      <div class="grid gap-5 md:grid-cols-2">
        <!-- Tarjeta -->
        <TarjetaPortal padding="lg">
          <div class="flex h-full flex-col">
            <CreditCard :size="28" class="text-dark" aria-hidden="true" />
            <h2 class="mt-3 font-display text-display-sm font-extrabold text-dark">Tarjeta</h2>
            <ul class="mt-4 flex-1 space-y-2 font-body text-sm text-dark/80">
              <li class="flex items-start gap-2"><Check :size="16" class="mt-0.5 shrink-0 text-nodo-500" /> Se activa al instante.</li>
              <li class="flex items-start gap-2"><Check :size="16" class="mt-0.5 shrink-0 text-nodo-500" /> Se renueva automáticamente.</li>
              <li class="flex items-start gap-2 font-bold text-dark"><X :size="16" class="mt-0.5 shrink-0 text-coral" /> No genera factura.</li>
            </ul>
            <Link
              :href="route('portal.contratar.tarjeta', { plan: plan.id })"
              class="mt-6 inline-flex min-h-[48px] items-center justify-center gap-2 border-2 border-dark bg-white px-5
                     font-display text-sm font-bold text-dark transition-all duration-200 ease-salida
                     hover:-translate-y-0.5 hover:shadow-dura-sm focus-visible:outline focus-visible:outline-2
                     focus-visible:outline-offset-2 focus-visible:outline-dark"
            >
              Pagar con tarjeta <ArrowRight :size="16" aria-hidden="true" />
            </Link>
          </div>
        </TarjetaPortal>

        <!-- Transferencia / efectivo -->
        <TarjetaPortal padding="lg" fondo="crema">
          <div class="flex h-full flex-col">
            <Banknote :size="28" class="text-dark" aria-hidden="true" />
            <h2 class="mt-3 font-display text-display-sm font-extrabold text-dark">Transferencia o efectivo</h2>
            <ul class="mt-4 space-y-2 font-body text-sm text-dark/80">
              <li class="flex items-start gap-2"><Check :size="16" class="mt-0.5 shrink-0 text-nodo-500" /> Te damos una referencia para pagar.</li>
              <li class="flex items-start gap-2"><Check :size="16" class="mt-0.5 shrink-0 text-nodo-500" /> Se activa cuando confirmamos el pago.</li>
              <li class="flex items-start gap-2 font-bold text-dark"><Check :size="16" class="mt-0.5 shrink-0 text-nodo-500" /> Sí genera factura.</li>
            </ul>

            <form class="mt-5 space-y-4" @submit.prevent="generar">
              <div>
                <p class="mb-2 font-display text-sm font-bold text-dark">¿Cómo pagarás?</p>
                <div class="grid grid-cols-2 gap-2">
                  <label
                    v-for="op in [{ v: 'transferencia', t: 'Transferencia' }, { v: 'efectivo', t: 'Efectivo' }]" :key="op.v"
                    class="flex cursor-pointer items-center justify-center gap-2 border-2 px-3 py-2.5 font-display text-sm font-bold
                           transition-colors"
                    :class="form.metodo === op.v ? 'border-dark bg-nodo-400 text-dark' : 'border-dark/25 text-dark/70 hover:border-dark'"
                  >
                    <input v-model="form.metodo" type="radio" :value="op.v" class="sr-only" />
                    {{ op.t }}
                  </label>
                </div>
              </div>

              <label class="flex items-start gap-2.5 font-body text-sm text-dark">
                <input v-model="form.pide_factura" type="checkbox" class="mt-0.5 h-4 w-4 rounded-none border-2 border-dark text-nodo-500 focus:ring-0" />
                Necesito factura
              </label>

              <div v-if="faltanFiscales()" class="flex items-start gap-2 border-2 border-amber-300 bg-amber-50 px-3 py-2.5 font-body text-sm text-amber-900">
                <AlertTriangle :size="16" class="mt-0.5 shrink-0" aria-hidden="true" />
                <span>
                  Para factura necesitamos tus datos fiscales.
                  <Link :href="route('portal.datos-fiscales')" class="font-bold underline">Complétalos aquí</Link> y vuelve.
                </span>
              </div>

              <p class="font-body text-xs text-dark/60">
                La referencia vence en {{ vencimientoDias }} días; el precio queda congelado hasta entonces.
              </p>

              <button
                type="submit"
                :disabled="form.processing || faltanFiscales()"
                class="inline-flex min-h-[48px] w-full items-center justify-center gap-2 border-2 border-dark bg-nodo-400 px-5
                       font-display text-sm font-bold text-dark transition-all duration-200 ease-salida
                       hover:-translate-y-0.5 hover:shadow-dura-sm disabled:cursor-not-allowed disabled:opacity-50
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
              >
                {{ form.processing ? 'Generando…' : 'Generar mi referencia' }}
              </button>
            </form>
          </div>
        </TarjetaPortal>
      </div>
    </div>
  </PortalLayout>
</template>
