<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import { ShieldCheck, Lock, Landmark } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import EncabezadoPortal from '@/Components/Portal/EncabezadoPortal.vue'
import TarjetaPortal from '@/Components/Portal/TarjetaPortal.vue'
import FormularioTarjetaBbva from '@/Components/Portal/FormularioTarjetaBbva.vue'

const props = withDefaults(defineProps<{
  plan: { id: number; nombre: string; precio: number; periodo_label: string; recurrente: boolean; renueva_sola: boolean; color: string }
  modo: 'suscripcion' | 'pago_unico'
  /** Qué pasarela cobra (config/pagos.php). */
  pasarela: 'stripe' | 'bbva'
  etiqueta: string
  clientSecret: string | null
  stripeKey: string | null
  volverA: string
  /** Sin llaves de la pasarela: se ve el flujo, pero la tarjeta y el cobro
   *  están deshabilitados, con su motivo, hasta configurarlas. */
  vistaPrevia?: boolean
  /** BBVA: `token` = la tarjeta se teclea aquí (openpay.js); `vpos` = en el formulario del banco. */
  captura?: 'token' | 'vpos'
  /** Lo que openpay.js necesita. Nada secreto: id de comercio y llave pública. */
  openpay?: { merchant_id: string; llave_publica: string; sandbox: boolean } | null
}>(), {
  vistaPrevia: false,
  captura: 'vpos',
  openpay: null,
})

const esBbva = props.pasarela === 'bbva'

const cargando = ref(true)
const procesando = ref(false)
const error = ref<string | null>(null)

let stripe: any = null
let elements: any = null

/** Carga el script de Stripe.js una sola vez. */
function cargarStripeJs(): Promise<any> {
  return new Promise((resolve, reject) => {
    if ((window as any).Stripe) return resolve((window as any).Stripe)
    const s = document.createElement('script')
    s.src = 'https://js.stripe.com/v3'
    s.onload = () => resolve((window as any).Stripe)
    s.onerror = () => reject(new Error('No se pudo cargar Stripe.'))
    document.head.appendChild(s)
  })
}

onMounted(async () => {
  // En vista previa no se toca ninguna pasarela; con BBVA no hay nada que
  // montar: la tarjeta se teclea en el formulario del banco.
  if (props.vistaPrevia || esBbva) {
    cargando.value = false
    return
  }
  try {
    const Stripe = await cargarStripeJs()
    stripe = Stripe(props.stripeKey)
    // El Payment Element es un iframe de Stripe: la tarjeta se teclea ahí dentro
    // y nunca pasa por nuestro servidor.
    elements = stripe.elements({ clientSecret: props.clientSecret })
    elements.create('payment').mount('#tarjeta-stripe')
  } catch (e: any) {
    error.value = e?.message ?? 'No se pudo iniciar el pago.'
  } finally {
    cargando.value = false
  }
})

const precio = (v: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v)

/** Con BBVA un plan recurrente se paga por periodo: se dice claro. */
const detalleDelCobro = props.plan.recurrente
  ? (props.plan.renueva_sola
      ? 'Se renueva ' + props.plan.periodo_label
      : 'Pagas este periodo. Te avisamos antes de que venza para renovarlo.')
  : 'Pago único'

/**
 * BBVA: el servidor crea el cargo y nos manda al formulario del banco (una
 * redirección fuera del sitio). Al volver, «confirmando» consulta el cargo.
 */
function irAlBanco() {
  if (procesando.value) return
  procesando.value = true
  error.value = null
  router.post(route('portal.contratar.tarjeta.iniciar', { plan: props.plan.id }), {}, {
    onError: (e) => { error.value = Object.values(e)[0] as string; procesando.value = false },
  })
}

async function pagar() {
  if (!stripe || !elements || procesando.value) return
  procesando.value = true
  error.value = null

  const { error: errValidacion } = await elements.submit()
  if (errValidacion) {
    error.value = errValidacion.message
    procesando.value = false
    return
  }

  if (props.modo === 'suscripcion') {
    // Guardar el método (SetupIntent) y crear la suscripción en el servidor.
    const { setupIntent, error: err } = await stripe.confirmSetup({
      elements,
      redirect: 'if_required',
    })
    if (err) {
      error.value = err.message
      procesando.value = false
      return
    }
    // El servidor crea la suscripción; la membresía la activa el webhook.
    router.post(route('portal.contratar.suscripcion', { plan: props.plan.id }), {
      payment_method: setupIntent.payment_method,
    }, {
      onError: (e) => { error.value = Object.values(e)[0] as string; procesando.value = false },
    })
  } else {
    // Pago único: se confirma el cobro y Stripe redirige a «confirmando».
    const { error: err } = await stripe.confirmPayment({
      elements,
      confirmParams: { return_url: window.location.origin + route('portal.pago.confirmando', {}, false) },
    })
    // Si llega aquí es que hubo error (si va bien, Stripe redirige solo).
    if (err) {
      error.value = err.message
      procesando.value = false
    }
  }
}
</script>

<template>
  <Head :title="`Contratar ${plan.nombre}`" />

  <PortalLayout>
    <div class="mx-auto max-w-xl">
      <EncabezadoPortal seccion="Pago" :titulo="`Contratar ${plan.nombre}`" />

      <TarjetaPortal padding="lg">
        <div class="mb-6 flex items-baseline justify-between border-b-2 border-dark/10 pb-4">
          <div>
            <p class="font-display text-lg font-bold text-dark">{{ plan.nombre }}</p>
            <p class="font-body text-sm text-dark/60">{{ detalleDelCobro }}</p>
          </div>
          <p class="font-display text-2xl font-black text-dark">{{ precio(plan.precio) }}</p>
        </div>

        <div v-if="error" class="mb-4 border-2 border-coral bg-coral/10 px-4 py-3 font-body text-sm text-dark" role="alert">
          {{ error }}
        </div>

        <!-- ── Vista previa: sin llaves de la pasarela todavía ── -->
        <template v-if="vistaPrevia">
          <div class="mb-2 flex items-center gap-2 border-2 border-amber-300 bg-amber-50 px-4 py-3 font-body text-sm text-amber-900">
            <span class="text-lg">👁️</span>
            <span>Vista previa. El pago con tarjeta todavía no está disponible: se activa en cuanto se conecte {{ etiqueta }}. Mientras, puedes pagar por referencia.</span>
          </div>

          <template v-if="esBbva">
            <div class="mt-4 flex items-start gap-3 rounded-md border-2 border-dashed border-dark/25 bg-cream-50 p-4 opacity-70">
              <Landmark :size="20" class="mt-0.5 shrink-0 text-dark/50" aria-hidden="true" />
              <p class="font-body text-sm text-dark/60">
                Al pagar te llevamos al formulario seguro de BBVA. Ahí escribes los datos de tu tarjeta y tu banco te pide confirmar el pago.
              </p>
            </div>
          </template>
          <template v-else>
            <label class="mb-1.5 mt-4 block font-display text-sm font-bold text-dark">Datos de la tarjeta</label>
            <!-- Maqueta del campo de tarjeta (el real lo pone Stripe Elements). -->
            <div class="space-y-3 rounded-md border-2 border-dashed border-dark/25 bg-cream-50 p-4 opacity-70">
              <div class="flex items-center justify-between rounded border border-dark/15 bg-white px-3 py-2.5">
                <span class="font-mono text-sm text-dark/40">1234 1234 1234 1234</span>
                <span class="flex gap-1"><span class="h-4 w-6 rounded bg-dark/10"></span><span class="h-4 w-6 rounded bg-dark/10"></span></span>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div class="rounded border border-dark/15 bg-white px-3 py-2.5 font-mono text-sm text-dark/40">MM / AA</div>
                <div class="rounded border border-dark/15 bg-white px-3 py-2.5 font-mono text-sm text-dark/40">CVC</div>
              </div>
            </div>
          </template>

          <button
            type="button" disabled
            class="mt-6 flex min-h-[48px] w-full cursor-not-allowed items-center justify-center gap-2 bg-dark/40 px-6
                   font-display text-base font-bold text-white"
          >
            <Lock :size="18" aria-hidden="true" />
            {{ modo === 'suscripcion' ? 'Suscribirme' : 'Pagar ' + precio(plan.precio) }}
          </button>
        </template>

        <!-- ── Pago real con BBVA: la tarjeta se teclea aquí (openpay.js) ── -->
        <template v-else-if="esBbva && captura === 'token' && openpay">
          <FormularioTarjetaBbva :plan-id="plan.id" :importe="precio(plan.precio)" :openpay="openpay" />
        </template>

        <!-- ── Pago real con BBVA: formulario del banco ── -->
        <template v-else-if="esBbva">
          <div class="flex items-start gap-3 border-2 border-dark/10 bg-cream-50 p-4">
            <Landmark :size="20" class="mt-0.5 shrink-0 text-dark/70" aria-hidden="true" />
            <p class="font-body text-sm text-dark/70">
              Te llevamos al formulario seguro de BBVA. Ahí escribes los datos de tu tarjeta y tu banco te pide confirmar el pago.
              Al terminar regresas a Nódico y activamos tu membresía en cuanto BBVA nos lo confirme.
            </p>
          </div>

          <button
            type="button"
            @click="irAlBanco"
            :disabled="procesando"
            class="mt-6 flex min-h-[48px] w-full items-center justify-center gap-2 bg-dark px-6
                   font-display text-base font-bold text-white transition hover:bg-dark/90 disabled:opacity-50"
          >
            <Lock :size="18" aria-hidden="true" />
            {{ procesando ? 'Abriendo el pago seguro…' : 'Pagar ' + precio(plan.precio) + ' con tarjeta' }}
          </button>
        </template>

        <!-- ── Pago real con Stripe (Elements) ── -->
        <template v-else>
          <p v-if="cargando" class="py-8 text-center font-body text-sm text-dark/50">Cargando el pago seguro…</p>

          <!-- Aquí Stripe monta su iframe con el campo de tarjeta. -->
          <div id="tarjeta-stripe" :class="cargando ? 'hidden' : ''"></div>

          <button
            v-if="!cargando && !error"
            type="button"
            @click="pagar"
            :disabled="procesando"
            class="mt-6 flex min-h-[48px] w-full items-center justify-center gap-2 bg-dark px-6
                   font-display text-base font-bold text-white transition hover:bg-dark/90 disabled:opacity-50"
          >
            <Lock :size="18" aria-hidden="true" />
            {{ procesando ? 'Procesando…' : (plan.recurrente ? 'Suscribirme' : 'Pagar ' + precio(plan.precio)) }}
          </button>
        </template>

        <p class="mt-4 flex items-center justify-center gap-2 font-body text-xs text-dark/50">
          <ShieldCheck :size="14" aria-hidden="true" />
          <template v-if="esBbva && captura === 'token'">Los datos de tu tarjeta van directo a BBVA; nunca tocan los servidores de Nódico.</template>
          <template v-else>El pago lo procesa {{ etiqueta }}. Tu tarjeta nunca toca los servidores de Nódico.</template>
        </p>
      </TarjetaPortal>

      <div class="mt-4 text-center">
        <a :href="volverA" class="font-body text-sm text-dark/60 underline hover:text-dark">Volver a mi membresía</a>
      </div>
    </div>
  </PortalLayout>
</template>
