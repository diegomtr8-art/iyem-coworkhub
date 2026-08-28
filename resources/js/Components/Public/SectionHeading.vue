<script setup lang="ts">
withDefaults(defineProps<{
  titulo: string
  /** Número de sección para la etiqueta técnica: «01 — SERVICIOS». */
  numero?: string
  etiqueta?: string
  descripcion?: string
  /** `h2` por defecto; `h1` solo en el encabezado principal de la página. */
  as?: 'h1' | 'h2' | 'h3'
  align?: 'left' | 'center'
  tono?: 'oscuro' | 'claro'
  tamano?: 'lg' | 'md'
}>(), {
  as: 'h2',
  align: 'left',
  tono: 'oscuro',
  tamano: 'md',
})
</script>

<template>
  <div :class="align === 'center' ? 'mx-auto max-w-3xl text-center' : 'text-left'">
    <!-- Ficha técnica: etiqueta mono numerada + línea de retícula -->
    <div
      v-if="numero || etiqueta"
      class="mb-6 flex items-center gap-4"
      :class="align === 'center' ? 'justify-center' : ''"
    >
      <p class="etiqueta-tecnica shrink-0" :class="tono === 'claro' ? 'text-nodo-400' : 'text-dark/55'">
        <span v-if="numero">{{ numero }}</span>
        <span v-if="numero && etiqueta" aria-hidden="true"> — </span>
        <span v-if="etiqueta">{{ etiqueta }}</span>
      </p>
      <span
        class="h-px flex-1"
        :class="tono === 'claro' ? 'bg-white/20' : 'bg-dark/15'"
        aria-hidden="true"
      />
    </div>

    <component
      :is="as"
      class="font-display font-extrabold"
      :class="[
        tamano === 'lg' ? 'text-display-lg' : 'text-display-md',
        tono === 'claro' ? 'text-white' : 'text-dark',
      ]"
    >
      {{ titulo }}
    </component>

    <p
      v-if="descripcion"
      class="mt-6 max-w-2xl font-body text-cuerpo-lg"
      :class="[
        tono === 'claro' ? 'text-white/70' : 'text-dark/70',
        align === 'center' ? 'mx-auto' : '',
      ]"
    >
      {{ descripcion }}
    </p>

    <slot />
  </div>
</template>
