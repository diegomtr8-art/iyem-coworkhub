<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted } from 'vue'
import { Loader2, CheckCircle2 } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import TarjetaPortal from '@/Components/Portal/TarjetaPortal.vue'

/**
 * Fase 4.A — la página de retorno NO decide si el pago fue bueno: solo consulta
 * el estado que el webhook deja en el servidor. Mientras el webhook no haya
 * activado la membresía, sigue esperando.
 */
const listo = ref(false)
let intentos = 0
let timer: number | undefined

async function consultar() {
  try {
    const r = await fetch(route('portal.pago.estado'), { headers: { Accept: 'application/json' } })
    const { activa } = await r.json()
    if (activa) {
      listo.value = true
      clearInterval(timer)
      setTimeout(() => router.visit(route('portal.suscripcion')), 1500)
    }
  } catch { /* reintenta en el siguiente tick */ }

  // A los ~40 s dejamos de sondear: el webhook puede tardar, pero no dejamos la
  // pantalla girando para siempre.
  if (++intentos > 20) clearInterval(timer)
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
        <div class="flex flex-col items-center text-center">
          <template v-if="!listo">
            <Loader2 :size="40" class="mb-4 animate-spin text-dark/60" aria-hidden="true" />
            <h1 class="font-display text-xl font-bold text-dark">Estamos confirmando tu pago</h1>
            <p class="mt-2 font-body text-sm text-dark/60">
              En cuanto tu banco y Stripe nos lo confirmen, activamos tu membresía.
              Esto suele tardar unos segundos; puedes esperar aquí.
            </p>
          </template>
          <template v-else>
            <CheckCircle2 :size="40" class="mb-4 text-nodo-600" aria-hidden="true" />
            <h1 class="font-display text-xl font-bold text-dark">¡Listo! Tu membresía está activa</h1>
            <p class="mt-2 font-body text-sm text-dark/60">Te llevamos a tu membresía…</p>
          </template>
        </div>
      </TarjetaPortal>
    </div>
  </PortalLayout>
</template>
