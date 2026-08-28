<script setup lang="ts">
defineProps<{
  icono: string
  titulo: string
  /** Texto alternativo del icono; si se omite queda decorativo. */
  iconoAlt?: string
}>()
</script>

<template>
  <div
    class="group flex h-full flex-col items-center gap-5 rounded-3xl bg-cream-dark/60 p-7 text-center
           transition duration-300 hover:-translate-y-1 hover:bg-cream-dark hover:shadow-lg"
  >
    <img
      :src="icono"
      :alt="iconoAlt ?? ''"
      :aria-hidden="iconoAlt ? undefined : 'true'"
      width="96"
      height="96"
      loading="lazy"
      decoding="async"
      class="h-20 w-20 shrink-0 object-contain sm:h-24 sm:w-24"
    />
    <p class="font-body text-sm font-medium leading-snug text-dark sm:text-base">
      {{ titulo }}
    </p>
  </div>
</template>
