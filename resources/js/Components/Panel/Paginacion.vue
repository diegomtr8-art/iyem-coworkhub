<script setup lang="ts">
/**
 * Paginación del panel operativo (Fase 5 · responsive).
 *
 * Antes vivía copiada en cuatro pantallas con botones de 32×32 px —por debajo
 * del mínimo táctil de 44 px—. Aquí, una sola vez y a 44 px. Recibe los `links`
 * tal cual los entrega el paginador de Laravel.
 */
import { Link } from '@inertiajs/vue3'

defineProps<{
  links: { url: string | null; label: string; active: boolean }[]
}>()
</script>

<template>
  <nav
    class="flex flex-wrap items-center justify-center gap-1.5 border-t border-dark/15 bg-cream-50 px-4 py-3"
    aria-label="Paginación"
  >
    <Link
      v-for="enlace in links" :key="enlace.label"
      :href="enlace.url ?? ''"
      class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center border px-2 font-mono text-xs transition-colors
             focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
      :class="enlace.active
        ? 'border-dark bg-dark text-white'
        : enlace.url
          ? 'border-dark/20 text-dark hover:border-dark'
          : 'pointer-events-none border-transparent text-dark/25'"
      :aria-current="enlace.active ? 'page' : undefined"
      v-html="enlace.label"
    />
  </nav>
</template>
