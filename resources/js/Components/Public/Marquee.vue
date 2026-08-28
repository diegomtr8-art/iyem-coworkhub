<script setup lang="ts">
withDefaults(defineProps<{
  palabras: string[]
  tono?: 'amarillo' | 'oscuro'
  /** Grosor de la franja. */
  compacto?: boolean
}>(), {
  tono: 'amarillo',
  compacto: false,
})
</script>

<template>
  <!--
    Cinta de marca. La pista se duplica para que el bucle no deje hueco; la
    segunda copia va oculta a lectores de pantalla. Con prefers-reduced-motion
    la animación queda congelada por la regla global de app.css.
  -->
  <div
    class="group relative flex overflow-hidden border-y-2 border-dark"
    :class="[
      tono === 'amarillo' ? 'bg-nodo-400 text-dark' : 'bg-tinta text-nodo-400',
      compacto ? 'py-3' : 'py-5',
    ]"
  >
    <div
      v-for="copia in 2"
      :key="copia"
      class="flex shrink-0 animate-marquee items-center whitespace-nowrap group-hover:[animation-play-state:paused]"
      :aria-hidden="copia === 2 ? 'true' : undefined"
    >
      <template v-for="palabra in palabras" :key="palabra">
        <span
          class="px-6 font-display font-extrabold uppercase tracking-tight"
          :class="compacto ? 'text-lg' : 'text-2xl sm:text-3xl'"
        >{{ palabra }}</span>
        <span class="text-xl opacity-45" aria-hidden="true">●</span>
      </template>
    </div>
  </div>
</template>
