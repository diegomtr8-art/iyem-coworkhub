<script setup lang="ts">
/**
 * Encabezado de pantalla del portal. El equivalente de `SectionHeading` del
 * sitio público, con el mismo patrón de «ficha técnica»: etiqueta mono numerada,
 * línea de retícula y debajo el titular grande.
 */
defineProps<{
  titulo: string
  etiqueta?: string
  numero?: string
  descripcion?: string
}>()
</script>

<template>
  <header class="mb-8">
    <p v-if="etiqueta" class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
      <span v-if="numero" class="font-mono">{{ numero }}</span>
      <span v-else class="h-1.5 w-1.5 rounded-full bg-nodo-500" aria-hidden="true" />
      {{ etiqueta }}
      <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
    </p>

    <h1 class="font-display text-display-md font-extrabold text-dark">{{ titulo }}</h1>

    <p v-if="descripcion" class="mt-3 max-w-2xl font-body text-cuerpo-lg text-dark/70">
      {{ descripcion }}
    </p>

    <slot />
  </header>
</template>
