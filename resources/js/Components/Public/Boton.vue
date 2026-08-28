<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  /** Ruta interna (Inertia) o URL externa; sin `href` se renderiza un <button>. */
  href?: string
  externo?: boolean
  variante?: 'primario' | 'secundario' | 'oscuro' | 'claro'
  tamano?: 'sm' | 'md' | 'lg'
  type?: 'button' | 'submit'
  disabled?: boolean
  /** Desplaza la flecha al pasar el cursor. */
  flecha?: boolean
}>(), {
  externo: false,
  variante: 'primario',
  tamano: 'md',
  type: 'button',
  disabled: false,
  flecha: false,
})

const etiqueta = computed(() => (props.href ? (props.externo ? 'a' : Link) : 'button'))

const variantes = {
  primario:   'bg-nodo-400 text-dark shadow-sombra-sm hover:-translate-y-0.5 hover:shadow-sombra',
  secundario: 'bg-dark text-white hover:-translate-y-0.5 hover:bg-tinta hover:shadow-sombra',
  // Contorno sobre fondo claro.
  oscuro:     'border border-dark/25 bg-transparent text-dark hover:border-dark hover:bg-dark hover:text-white',
  // Contorno sobre fondo oscuro.
  claro:      'border border-white/35 bg-transparent text-white hover:border-nodo-400 hover:bg-nodo-400 hover:text-dark',
}

const tamanos = {
  // min-h garantiza el área táctil mínima de 44px en iOS.
  sm: 'min-h-[44px] px-5 py-2.5 text-sm',
  md: 'min-h-[48px] px-6 py-3 text-sm',
  lg: 'min-h-[56px] px-8 py-4 text-base',
}
</script>

<template>
  <component
    :is="etiqueta"
    :href="href"
    :type="href ? undefined : type"
    :disabled="href ? undefined : disabled"
    :target="externo ? '_blank' : undefined"
    :rel="externo ? 'noopener noreferrer' : undefined"
    class="group inline-flex items-center justify-center gap-2.5 rounded-xl font-display font-bold
           transition-all duration-300 ease-salida disabled:cursor-not-allowed disabled:opacity-60
           disabled:hover:translate-y-0"
    :class="[variantes[variante], tamanos[tamano]]"
  >
    <slot />
    <span
      v-if="flecha"
      class="transition-transform duration-300 ease-salida group-hover:translate-x-1"
      aria-hidden="true"
    >→</span>
  </component>
</template>
