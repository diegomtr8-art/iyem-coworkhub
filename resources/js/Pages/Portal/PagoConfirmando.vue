<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted } from 'vue'
import { Loader2, CheckCircle2, AlertCircle, Clock } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import TarjetaPortal from '@/Components/Portal/TarjetaPortal.vue'

/**
 * La página de retorno NO decide si el pago fue bueno: solo pregunta al
 * servidor. Con Stripe, hasta que el webhook activa la membresía; con BBVA,
 * por el cargo (`cargo`), que el servidor consulta en la API del banco.
 */
const props = withDefaults(defineProps<{
  /** BBVA: el cargo que se está confirmando. Con Stripe no hay. */
  cargo?: number | null
  etiqueta?: string
}>(), {
  cargo: null,
  etiqueta: 'el banco',
})

type Estado = {
  activa: boolean
  estado?: string
  mensaje?: string
  plan_id?: number
  url_pago?: string | null
}

/** esperando | listo | problema | pendiente */
const fase = ref<'esperando' | 'listo' | 'problema' | 'pendiente'>('esperando')
const detalle = ref<Estado | null>(null)
let intentos = 0
let timer: number | undefined

const TERMINAL = ['fallido', 'cancelado', 'abandonado', 'en_revision', 'devuelto', 'desconocido']

async function consultar() {
  try {
    const url = route('portal.pago.estado', props.cargo ? { cargo: props.cargo } : {})
    const r = await fetch(url, { headers: { Accept: 'application/json' } })
    const datos: Estado = await r.json()
    detalle.value = datos

    if (datos.activa) {
      fase.value = 'listo'
      clearInterval(timer)
      setTimeout(() => router.visit(route('portal.suscripcion')), 1500)
      return
    }

    if (datos.estado && TERMINAL.includes(datos.estado)) {
      fase.value = 'problema'
      clearInterval(timer)
      return
    }
  } catch { /* reintenta en el siguiente tick */ }

  // A los ~40 s dejamos de sondear: no dejamos la pantalla girando para
  // siempre. Con BBVA el cargo lo sigue consultando el servidor cada 5 min.
  if (++intentos > 20) {
    clearInterval(timer)
    if (props.cargo) fase.value = 'pendiente'
  }
}

function empezarDeNuevo() {
  if (detalle.value?.plan_id) {
    router.visit(route('portal.contratar', { plan: detalle.value.plan_id }))
  } else {
    router.visit(route('portal.suscripcion'))
  }
}

onMounted(() => {
  consultar()
  timer = window.setInterval(consultar, 2000)
})
onUnmounted(() => clearInterval(timer))
</script>

<template>
  <Head title="Confirmando tu pago" />

  <PortalLayout>
    <div class="mx-auto max-w-md py-12">
      <TarjetaPortal padding="lg">
        <div class="flex flex-col items-center text-center" aria-live="polite">
          <template v-if="fase === 'esperando'">
            <Loader2 :size="40" class="mb-4 animate-spin text-dark/60" aria-hidden="true" />
            <h1 class="font-display text-xl font-bold text-dark">Estamos confirmando tu pago</h1>
            <p class="mt-2 font-body text-sm text-dark/60">
              En cuanto {{ etiqueta }} nos lo confirme, activamos tu membresía.
              Esto suele tardar unos segundos; puedes esperar aquí.
            </p>
          </template>

          <template v-else-if="fase === 'listo'">
            <CheckCircle2 :size="40" class="mb-4 text-nodo-600" aria-hidden="true" />
            <h1 class="font-display text-xl font-bold text-dark">¡Listo! Tu membresía está activa</h1>
            <p class="mt-2 font-body text-sm text-dark/60">Te llevamos a tu membresía…</p>
          </template>

          <template v-else-if="fase === 'problema'">
            <AlertCircle :size="40" class="mb-4 text-coral" aria-hidden="true" />
            <h1 class="font-display text-xl font-bold text-dark">No se completó el pago</h1>
            <p class="mt-2 font-body text-sm text-dark/70">{{ detalle?.mensaje }}</p>
            <div class="mt-6 flex w-full flex-col gap-2">
              <button
                type="button" @click="empezarDeNuevo"
                class="min-h-[48px] w-full bg-dark px-6 font-display text-base font-bold text-white transition hover:bg-dark/90"
              >
                Intentar de nuevo
              </button>
              <a :href="route('portal.suscripcion')" class="font-body text-sm text-dark/60 underline hover:text-dark">Volver a mi membresía</a>
            </div>
          </template>

          <template v-else>
            <Clock :size="40" class="mb-4 text-dark/60" aria-hidden="true" />
            <h1 class="font-display text-xl font-bold text-dark">Tu pago sigue pendiente</h1>
            <p class="mt-2 font-body text-sm text-dark/70">
              {{ detalle?.mensaje ?? 'Tu banco todavía no confirma el pago.' }}
              Si ya lo autorizaste, en unos minutos se activa solo: no hace falta pagar otra vez.
            </p>
            <div class="mt-6 flex w-full flex-col gap-2">
              <a
                v-if="detalle?.url_pago" :href="detalle.url_pago"
                class="flex min-h-[48px] w-full items-center justify-center bg-dark px-6 font-display text-base font-bold text-white transition hover:bg-dark/90"
              >
                Terminar el pago en el banco
              </a>
              <button type="button" @click="empezarDeNuevo" class="font-body text-sm text-dark/60 underline hover:text-dark">
                Empezar de nuevo
              </button>
            </div>
          </template>
        </div>
      </TarjetaPortal>
    </div>
  </PortalLayout>
</template>
