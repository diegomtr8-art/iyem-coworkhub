<script setup lang="ts">
withDefaults(defineProps<{
  icono: string
  titulo: string
  /** Índice para la numeración técnica de la tarjeta. */
  numero?: string
  tono?: 'claro' | 'oscuro'
  iconoAlt?: string
}>(), {
  tono: 'claro',
})
</script>

<template>
  <!--
    Bloque duro: borde de 2px y sombra sólida desplazada. Al pasar el cursor la
    tarjeta se desplaza y la sombra se cierra, así el movimiento es solo transform.
  -->
  <div
    class="group flex h-full flex-col gap-6 border-2 p-6 transition-all duration-200 ease-salida sm:p-7"
    :class="tono === 'oscuro'
      ? 'border-white/15 bg-dark-600 hover:border-nodo-400 hover:shadow-dura-nodo hover:-translate-x-1 hover:-translate-y-1'
      : 'border-dark bg-white shadow-dura-sm hover:shadow-dura hover:-translate-x-1 hover:-translate-y-1'"
  >
    <div class="flex items-start justify-between gap-4">
      <img
        :src="icono"
        :alt="iconoAlt ?? ''"
        :aria-hidden="iconoAlt ? undefined : 'true'"
        width="96"
        height="96"
        loading="lazy"
        decoding="async"
        class="h-16 w-16 shrink-0 object-contain transition-transform duration-300 ease-salida group-hover:scale-110 sm:h-20 sm:w-20"
        :class="tono === 'oscuro' ? 'brightness-0 invert' : ''"
      />
      <span
        v-if="numero"
        class="etiqueta-tecnica shrink-0"
        :class="tono === 'oscuro' ? 'text-white/35' : 'text-dark/30'"
        aria-hidden="true"
      >{{ numero }}</span>
    </div>

    <p
      class="mt-auto font-display text-lg font-bold leading-tight"
      :class="tono === 'oscuro' ? 'text-white' : 'text-dark'"
    >
      {{ titulo }}
    </p>
  </div>
</template>
