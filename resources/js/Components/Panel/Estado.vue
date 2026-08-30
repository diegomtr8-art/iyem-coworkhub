<script setup lang="ts">
/**
 * Etiqueta de estado del panel operativo.
 *
 * El color semántico va **separado del amarillo de marca**: en una herramienta,
 * «atención» tiene que leerse como atención y no confundirse con el acento de
 * Nódico. Y el estado no depende solo del color —lleva su texto y, cuando
 * importa, un punto de forma distinta—, porque un aviso que solo se distingue
 * por ser rojo no existe para quien no distingue el rojo.
 */
withDefaults(defineProps<{
  tono?: 'bien' | 'atencion' | 'problema' | 'neutro'
  texto: string
  punto?: boolean
  tamano?: 'sm' | 'md'
}>(), {
  tono: 'neutro',
  punto: true,
  tamano: 'sm',
})

const tonos = {
  bien:     { caja: 'border-emerald-600/40 bg-emerald-50 text-emerald-800', punto: 'bg-emerald-600' },
  atencion: { caja: 'border-amber-600/40 bg-amber-50 text-amber-900',       punto: 'bg-amber-600' },
  problema: { caja: 'border-red-600/40 bg-red-50 text-red-800',             punto: 'bg-red-600' },
  neutro:   { caja: 'border-dark/20 bg-cream-50 text-dark/80',              punto: 'bg-dark/50' },
}

const tamanos = {
  sm: 'px-2 py-0.5 text-[0.6875rem]',
  md: 'px-2.5 py-1 text-xs',
}
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 whitespace-nowrap border font-mono uppercase tracking-[0.08em]"
    :class="[tonos[tono].caja, tamanos[tamano]]"
  >
    <span v-if="punto" class="h-1.5 w-1.5 rounded-full" :class="tonos[tono].punto" aria-hidden="true" />
    {{ texto }}
  </span>
</template>
