<script setup lang="ts">
import { onMounted, ref } from 'vue'

withDefaults(defineProps<{
  /** Texto que sigue la circunferencia. Conviene que termine en separador. */
  texto?: string
  /** Contenido del centro del sello. */
  centro?: string
}>(), {
  texto: 'DAY-PASS GRATUITO · INTERIOR DEL ESTADO · ',
  centro: '$0',
})

const sinMovimiento = ref(false)

onMounted(() => {
  sinMovimiento.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches
})
</script>

<template>
  <!--
    Sello circular con el texto siguiendo la circunferencia (textPath).
    Usa la animación `spin-slow` que estaba en tailwind.config.js sin usarse.
    Con prefers-reduced-motion deja de girar.
  -->
  <div class="pointer-events-none relative h-32 w-32 select-none sm:h-40 sm:w-40" aria-hidden="true">
    <svg
      viewBox="0 0 200 200"
      class="h-full w-full"
      :class="sinMovimiento ? '' : 'animate-spin-slow'"
    >
      <defs>
        <path
          id="circulo-sello"
          d="M 100,100 m -74,0 a 74,74 0 1,1 148,0 a 74,74 0 1,1 -148,0"
          fill="none"
        />
      </defs>
      <text class="fill-dark font-mono" style="font-size: 15px; letter-spacing: 3.1px">
        <textPath href="#circulo-sello">{{ texto }}</textPath>
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
