<script setup lang="ts">
/** Foto de una sección. El cargador con recorte llega con la migración de imágenes (4.4). */
defineProps<{ campo: any; modelValue: any; vista?: any; error?: string; id: string }>()
defineEmits<{ 'update:modelValue': [valor: any] }>()
</script>

<template>
  <div>
    <p class="font-display text-sm font-bold text-dark">{{ campo.etiqueta }}</p>
    <img v-if="vista?.src" :src="vista.src" :alt="vista.alt" class="mt-2 max-h-40 border border-dark/15" />
  </div>
</template>
