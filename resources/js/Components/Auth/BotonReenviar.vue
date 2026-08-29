<script setup lang="ts">
/**
 * F — Botón de reenvío con cuenta regresiva.
 *
 * Sin la cuenta regresiva, quien no ve el correo pulsa «reenviar» seis veces
 * seguidas, choca contra el límite del servidor y acaba pensando que el sistema
 * está roto. Decir cuántos segundos faltan convierte un error en una espera.
 *
 * El contador es cortesía, no seguridad: el límite real vive en el servidor
 * (`throttle:reenvio`), y este botón no puede saltárselo.
 */
import Boton from '@/Components/Public/Boton.vue'
import { Loader2 } from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    procesando?: boolean
    /** Segundos entre reenvíos. */
    espera?: number
    etiqueta?: string
    /** Al enviarse con éxito, reinicia la cuenta. */
    enviado?: boolean
  }>(),
  { procesando: false, espera: 60, etiqueta: 'Reenviar el correo', enviado: false },
)

const emit = defineEmits<{ reenviar: [] }>()

const restante = ref(0)
let temporizador: ReturnType<typeof setInterval> | undefined

function arrancar() {
  restante.value = props.espera
  detener()
  temporizador = setInterval(() => {
    restante.value -= 1
    if (restante.value <= 0) detener()
  }, 1000)
}

function detener() {
  if (temporizador) clearInterval(temporizador)
  temporizador = undefined
}

onMounted(arrancar)
onBeforeUnmount(detener)

watch(() => props.enviado, (ok) => { if (ok) arrancar() })

const listo = computed(() => restante.value <= 0 && !props.procesando)
</script>

<template>
  <div>
    <Boton
      type="button"
      variante="oscuro"
      class="w-full"
      :disabled="!listo"
      @click="emit('reenviar')"
    >
      <Loader2 v-if="procesando" class="h-5 w-5 animate-spin" aria-hidden="true" />
      {{ procesando ? 'Enviando…' : etiqueta }}
    </Boton>

    <!--
      `aria-live="polite"` para que un lector de pantalla anuncie cuándo se
      puede volver a pulsar, sin interrumpir lo que la persona esté leyendo.
    -->
    <p class="mt-2 text-center font-body text-sm text-dark/70" aria-live="polite">
      <span v-if="restante > 0">
        Puedes volver a pedirlo en {{ restante }} segundo{{ restante === 1 ? '' : 's' }}.
      </span>
      <span v-else>¿No te llegó? Pídelo otra vez.</span>
    </p>
  </div>
</template>
