<script setup lang="ts">
withDefaults(defineProps<{
  palabras: string[]
  tono?: 'amarillo' | 'oscuro'
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
    class="group relative flex overflow-hidden"
    :class="[
      tono === 'amarillo' ? 'bg-nodo-400 text-dark' : 'bg-tinta text-nodo-400',
      compacto ? 'py-3.5' : 'py-5',
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
          class="px-7 font-display font-bold uppercase tracking-tight"
          :class="compacto ? 'text-base' : 'text-xl sm:text-2xl'"
        >{{ palabra }}</span>
        <span class="text-sm opacity-40" aria-hidden="true">◆</span>
      </template>
    </div>
  </div>
</template>
