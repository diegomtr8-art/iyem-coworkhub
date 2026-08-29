<script setup lang="ts">
/**
 * El «bloque duro» del sistema, en su versión de portal: borde de 2 px y sombra
 * sólida desplazada.
 *
 * Solo se anima `transform` y la sombra, nunca `width`/`height`/`top`, como
 * manda la skill. El desplazamiento al pasar el cursor va acompañado del cierre
 * de la sombra a 2 px: es lo que da la sensación de que la tarjeta se levanta.
 */
withDefaults(defineProps<{
  /** Etiqueta técnica numerada del encabezado. */
  etiqueta?: string
  numero?: string
  titulo?: string
  /** `plano` quita el hover: para tarjetas que no son pulsables. */
  interactiva?: boolean
  fondo?: 'blanco' | 'crema' | 'oscuro' | 'nodo'
  padding?: 'sm' | 'md' | 'lg'
}>(), {
  interactiva: false,
  fondo: 'blanco',
  padding: 'md',
})

const fondos = {
  blanco: 'bg-white text-dark',
  crema:  'bg-cream-50 text-dark',
  oscuro: 'bg-dark text-cream',
  nodo:   'bg-nodo-400 text-dark',
}

const paddings = {
  sm: 'p-4',
  md: 'p-5 sm:p-6',
  lg: 'p-6 sm:p-8',
}
</script>

<template>
  <section
    class="border-2 border-dark shadow-dura-sm"
    :class="[
      fondos[fondo],
      paddings[padding],
      interactiva
        ? 'transition-all duration-200 ease-salida hover:-translate-x-1 hover:-translate-y-1 hover:shadow-dura'
        : '',
    ]"
  >
    <header v-if="etiqueta || titulo" class="mb-4">
      <p
        v-if="etiqueta"
        class="etiqueta-tecnica flex items-center gap-2"
        :class="fondo === 'oscuro' ? 'text-nodo-400' : 'text-dark/70'"
      >
        <span v-if="numero" class="font-mono">{{ numero }}</span>
        <span v-else class="h-1.5 w-1.5 rounded-full bg-nodo-500" aria-hidden="true" />
        {{ etiqueta }}
      </p>

      <h2
        v-if="titulo"
        class="mt-2 font-display text-display-sm font-extrabold"
        :class="fondo === 'oscuro' ? 'text-white' : 'text-dark'"
      >
        {{ titulo }}
      </h2>
    </header>

    <slot />
  </section>
</template>
