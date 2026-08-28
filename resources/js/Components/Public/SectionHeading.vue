<script setup lang="ts">
withDefaults(defineProps<{
  titulo: string
  /** Texto introductorio opcional bajo el título. */
  descripcion?: string
  /** `h2` por defecto; usa `h1` sólo en el encabezado principal de la página. */
  as?: 'h1' | 'h2' | 'h3'
  align?: 'left' | 'center'
  tono?: 'oscuro' | 'claro'
}>(), {
  as: 'h2',
  align: 'left',
  tono: 'oscuro',
})
</script>

<template>
  <div :class="[align === 'center' ? 'text-center mx-auto max-w-3xl' : 'text-left']">
    <component
      :is="as"
      class="font-display font-extrabold tracking-tight text-3xl sm:text-4xl lg:text-5xl"
      :class="tono === 'claro' ? 'text-white' : 'text-dark'"
    >
      {{ titulo }}
    </component>

    <p
      v-if="descripcion"
      class="font-body mt-5 text-base sm:text-lg leading-relaxed"
      :class="tono === 'claro' ? 'text-white/80' : 'text-dark/75'"
    >
      {{ descripcion }}
    </p>

    <slot />
  </div>
</template>
