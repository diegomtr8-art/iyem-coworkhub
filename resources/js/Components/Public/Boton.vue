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
  // Amarillo sobre borde carbón: el CTA principal.
  primario: 'border-2 border-dark bg-nodo-400 text-dark shadow-dura-sm hover:shadow-none hover:translate-x-[2px] hover:translate-y-[2px]',
  // Contorno sobre fondo claro.
  secundario: 'border-2 border-dark bg-transparent text-dark hover:bg-dark hover:text-nodo-400',
  // Sólido oscuro, para usarse sobre el amarillo.
  oscuro: 'border-2 border-dark bg-dark text-nodo-400 hover:bg-tinta',
  // Contorno claro, para usarse sobre fondo oscuro.
  claro: 'border-2 border-nodo-400 bg-transparent text-nodo-400 hover:bg-nodo-400 hover:text-dark',
}

const tamanos = {
  // min-h-[44px] garantiza el área táctil mínima en iOS.
  sm: 'min-h-[44px] px-5 py-2.5 text-sm',
  md: 'min-h-[48px] px-7 py-3 text-sm',
  lg: 'min-h-[56px] px-9 py-4 text-base',
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
    class="group inline-flex items-center justify-center gap-2.5 rounded-lg font-display font-bold
           transition-all duration-200 ease-salida disabled:cursor-not-allowed disabled:opacity-60"
    :class="[variantes[variante], tamanos[tamano]]"
  >
    <slot />
    <span
      v-if="flecha"
      class="transition-transform duration-200 ease-salida group-hover:translate-x-1"
      aria-hidden="true"
    >→</span>
  </component>
</template>
