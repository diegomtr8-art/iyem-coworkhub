<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import { ShieldCheck, Lock } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import EncabezadoPortal from '@/Components/Portal/EncabezadoPortal.vue'
import TarjetaPortal from '@/Components/Portal/TarjetaPortal.vue'

const props = defineProps<{
  plan: { id: number; nombre: string; precio: number; periodo_label: string; recurrente: boolean; color: string }
  modo: 'suscripcion' | 'pago_unico'
  clientSecret: string
  stripeKey: string
  volverA: string
}>()

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
            <p class="font-body text-sm text-dark/60">
              {{ plan.recurrente ? 'Se renueva ' + plan.periodo_label : 'Pago único' }}
            </p>
          </div>
          <p class="font-display text-2xl font-black text-dark">{{ precio(plan.precio) }}</p>
        </div>

        <div v-if="error" class="mb-4 border-2 border-coral bg-coral/10 px-4 py-3 font-body text-sm text-dark" role="alert">
          {{ error }}
        </div>

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

        <p class="mt-4 flex items-center justify-center gap-2 font-body text-xs text-dark/50">
          <ShieldCheck :size="14" aria-hidden="true" />
          El pago lo procesa Stripe. Tu tarjeta nunca toca los servidores de Nódico.
        </p>
      </TarjetaPortal>

      <div class="mt-4 text-center">
        <a :href="volverA" class="font-body text-sm text-dark/60 underline hover:text-dark">Volver a mi membresía</a>
      </div>
    </div>
  </PortalLayout>
</template>
