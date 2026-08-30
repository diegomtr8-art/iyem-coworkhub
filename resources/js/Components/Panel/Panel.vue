<script setup lang="ts">
/**
 * Contenedor del panel operativo.
 *
 * Deliberadamente más plano y más apretado que las tarjetas del portal: esto se
 * opera a diario, con prisa y muchas veces desde el mostrador con alguien
 * esperando enfrente. Nada de sombras duras ni desplazamientos al pasar el
 * cursor; el adorno cuesta atención, y aquí la atención está en otra parte.
 */
withDefaults(defineProps<{
  titulo?: string
  contador?: number | string | null
  padding?: 'none' | 'sm' | 'md'
}>(), {
  padding: 'md',
  contador: null,
})

const paddings = { none: '', sm: 'p-3', md: 'p-4' }
</script>

<template>
  <section class="border border-dark/20 bg-white">
    <header
      v-if="titulo || $slots.acciones"
      class="flex items-center justify-between gap-3 border-b border-dark/15 bg-cream-50 px-4 py-2.5"
    >
      <h2 class="flex items-center gap-2 font-display text-sm font-bold text-dark">
        {{ titulo }}
        <span
          v-if="contador !== null && contador !== ''"
          class="border border-dark/25 bg-white px-1.5 py-0.5 font-mono text-[0.6875rem] text-dark/70"
        >{{ contador }}</span>
      </h2>

      <div v-if="$slots.acciones" class="flex shrink-0 items-center gap-2">
        <slot name="acciones" />
      </div>
    </header>

    <div :class="paddings[padding]">
      <slot />
    </div>
  </section>
</template>
