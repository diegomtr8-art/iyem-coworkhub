<script setup lang="ts">
import { ref } from 'vue'
import { clicFondo as vClicFondo } from '@/composables/clicFondo'

// Igual que los modales del panel: la directiva asigna a un ref.
const abierto = ref(true)
defineExpose({ abierto })
</script>

<template>
  <div v-if="abierto" class="fondo" v-clic-fondo="() => (abierto = false)">
    <div class="caja">contenido</div>
  </div>
</template>
