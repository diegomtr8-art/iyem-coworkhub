<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

const props = withDefaults(defineProps<{
  /** Texto que sigue la circunferencia. Conviene que termine en separador. */
  texto: string
  /** Contenido del centro del sello. */
  centro?: string
}>(), {
  centro: '$0',
})

const sinMovimiento = ref(false)

/** Radio del camino del texto, en unidades del viewBox de 200. */
const RADIO = 74
const CIRCUNFERENCIA = 2 * Math.PI * RADIO

/**
 * El texto lo escribe el CMS (hasta 60 caracteres) y antes no cabía: a 15
 * unidades fijas, 42 caracteres ya se pasaban de la circunferencia y lo demás
 * se cortaba. Ahora la letra se calcula para que el texto quepa (una letra
 * monoespaciada mide ~0.6 em) con un tope de 18, y `textLength` reparte el
 * sobrante para cerrar el círculo. Con el sello de 160 px de móvil, 60
 * caracteres quedan a ~10 px reales.
 */
const tamanoLetra = computed(() =>
  // Hacia abajo: redondear hacia arriba ya no cabe.
  Math.floor(Math.min(18, CIRCUNFERENCIA / (Math.max(1, props.texto.length) * 0.6)) * 100) / 100,
)

onMounted(() => {
  sinMovimiento.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches
})
</script>

<template>
  <!--
    Sello circular con el texto siguiendo la circunferencia (textPath).
    Va montado sobre la foto del day-pass: por eso el texto lleva su propio
    disco crema con borde duro. Antes era oscuro sobre transparente y se leía
    o no según la foto (dos probadores, pruebas de servicio social).
    Con prefers-reduced-motion deja de girar.
  -->
  <div class="pointer-events-none relative h-40 w-40 select-none sm:h-44 sm:w-44" aria-hidden="true">
    <svg
      viewBox="0 0 200 200"
      class="h-full w-full"
      :class="sinMovimiento ? '' : 'animate-spin-slow'"
    >
      <defs>
        <path
          id="circulo-sello"
          :d="`M 100,100 m -${RADIO},0 a ${RADIO},${RADIO} 0 1,1 ${RADIO * 2},0 a ${RADIO},${RADIO} 0 1,1 -${RADIO * 2},0`"
          fill="none"
        />
      </defs>
      <circle cx="100" cy="100" r="97" class="fill-cream-50 stroke-dark" stroke-width="4" />
      <text class="fill-dark font-mono font-bold" :font-size="tamanoLetra">
        <textPath href="#circulo-sello" :textLength="CIRCUNFERENCIA.toFixed(2)" lengthAdjust="spacing">{{ texto }}</textPath>
      </text>
    </svg>

    <span class="absolute inset-0 flex items-center justify-center">
      <span
        class="flex h-16 w-16 items-center justify-center rounded-full bg-dark font-display
               text-xl font-extrabold text-nodo-400 sm:h-20 sm:w-20 sm:text-2xl"
      >{{ centro }}</span>
    </span>
  </div>
</template>
