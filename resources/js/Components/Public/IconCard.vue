<script setup lang="ts">
withDefaults(defineProps<{
  icono: string
  titulo: string
  tono?: 'claro' | 'oscuro'
  iconoAlt?: string
}>(), {
  tono: 'claro',
})
</script>

<template>
  <div
    class="group flex h-full flex-col gap-5 rounded-2xl p-6 transition-all duration-300 ease-salida sm:p-7"
    :class="tono === 'oscuro'
      ? 'bg-white/[.04] ring-1 ring-white/10 hover:-translate-y-1 hover:bg-white/[.07]'
      : 'bg-white shadow-sombra-sm ring-1 ring-dark/[.07] hover:-translate-y-1 hover:shadow-sombra'"
  >
    <img
      :src="icono"
      :alt="iconoAlt ?? ''"
      :aria-hidden="iconoAlt ? undefined : 'true'"
      width="96"
      height="96"
      loading="lazy"
      decoding="async"
      class="h-14 w-14 shrink-0 object-contain transition-transform duration-500 ease-salida group-hover:scale-110 sm:h-16 sm:w-16"
      :class="tono === 'oscuro' ? 'brightness-0 invert' : ''"
    />

    <p
      class="mt-auto font-display text-base font-bold leading-snug sm:text-lg"
      :class="tono === 'oscuro' ? 'text-white' : 'text-dark'"
    >
      {{ titulo }}
    </p>
  </div>
</template>
