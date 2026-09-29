<script setup lang="ts">
/**
 * Logos de aliados sueltos sobre el fondo de la sección, sin caja.
 * Los PNG llevan fondo transparente y están recortados a su tinta;
 * las alturas se ajustan una a una para alinearlos ópticamente, no por su caja.
 *
 * Nombre y enlace salen del módulo «Página Web» (comun.aliados); logo y
 * medidas son diseño y se quedan aquí.
 */
import { useAliados } from '@/composables/useAliados'

const aliados = useAliados({ iyem: 'h-11 sm:h-14', herencia: 'h-9 sm:h-11', canieti: 'h-14 sm:h-16' })
</script>

<template>
  <ul
    class="sin-scrollbar -mx-5 flex snap-x items-center gap-10 overflow-x-auto px-5
           sm:mx-0 sm:grid sm:grid-cols-3 sm:justify-items-center sm:gap-12 sm:overflow-visible sm:px-0"
  >
    <li
      v-for="aliado in aliados"
      :key="aliado.nombre"
      class="flex shrink-0 snap-center items-center justify-center"
    >
      <a
        v-if="aliado.href"
        :href="aliado.href"
        target="_blank"
        rel="noopener noreferrer"
        class="flex min-h-[44px] items-center rounded transition duration-300 ease-salida
               grayscale hover:grayscale-0 hover:opacity-100 opacity-70 hover:opacity-100"
      >
        <img
          :src="aliado.logo"
          :alt="`${aliado.nombre} — sitio web`"
          :width="aliado.ancho"
          :height="aliado.alto"
          loading="lazy"
          decoding="async"
          class="w-auto object-contain"
          :class="aliado.clase"
        />
      </a>

      <!-- Sin enlace: la imagen va suelta, no dentro de un <a> vacío. -->
      <img
        v-else
        :src="aliado.logo"
        :alt="aliado.nombre"
        :width="aliado.ancho"
        :height="aliado.alto"
        loading="lazy"
        decoding="async"
        class="w-auto object-contain opacity-70 grayscale transition duration-300 ease-salida hover:opacity-100 hover:grayscale-0"
        :class="aliado.clase"
      />
    </li>
  </ul>
</template>
