<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const esStaging = computed(() => Boolean(page.props.isStaging))
</script>

<template>
  <div
    v-if="esStaging"
    role="status"
    class="fixed bottom-4 left-1/2 z-[60] -translate-x-1/2 rounded-full border border-dark/20 bg-nodo-400/95
           px-4 py-1.5 font-display text-[11px] font-bold uppercase tracking-widest text-dark shadow-lg backdrop-blur"
  >
    Ambiente de prueba
  </div>
</template>
