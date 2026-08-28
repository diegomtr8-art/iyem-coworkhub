<script setup lang="ts">
withDefaults(defineProps<{
  titulo: string
  /** Etiqueta técnica de la sección: «Servicios», «Membresías»… */
  etiqueta?: string
  descripcion?: string
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
    <p
      v-if="etiqueta"
      class="etiqueta-tecnica mb-5 flex items-center gap-3"
      :class="[
        tono === 'claro' ? 'text-nodo-400' : 'text-dark/45',
        align === 'center' ? 'justify-center' : '',
      ]"
    >
      <span class="h-1.5 w-1.5 rounded-full bg-nodo-400" aria-hidden="true" />
      {{ etiqueta }}
    </p>

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
        tono === 'claro' ? 'text-white/65' : 'text-dark/65',
        align === 'center' ? 'mx-auto' : '',
      ]"
    >
      {{ descripcion }}
    </p>

    <slot />
  </div>
</template>
