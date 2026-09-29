<script setup lang="ts">
/**
 * Franja que avisa de que la página muestra borradores del módulo «Página
 * Web». Solo la ve quien edita el sitio, y solo al abrirla con ?vista_previa=1:
 * sin este aviso es fácil creer que el cambio ya está publicado.
 */
import { usePage } from '@inertiajs/vue3'
import { Eye } from 'lucide-vue-next'
import { computed } from 'vue'

const page = usePage()
const activa = computed(() => Boolean(page.props.vistaPrevia))

/** La misma página, sin el parámetro: lo que ve el público. */
const publicada = computed(() => {
  if (typeof window === 'undefined') return '#'
  const url = new URL(window.location.href)
  url.searchParams.delete('vista_previa')
  return url.pathname + url.search + url.hash
})
</script>

<template>
  <div
    v-if="activa"
    role="status"
    class="pb-segura fixed inset-x-0 bottom-0 z-[70] border-t-2 border-dark bg-nodo-400 text-dark"
  >
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-5 py-3 sm:px-8">
      <p class="flex items-center gap-2.5 font-display text-sm font-bold">
        <Eye class="h-4 w-4 shrink-0" aria-hidden="true" />
        Vista previa: el público todavía no ve estos cambios.
      </p>
      <a
        :href="publicada"
        class="inline-flex min-h-[44px] items-center font-body text-sm font-medium underline underline-offset-4"
      >
        Ver lo publicado
      </a>
    </div>
  </div>
</template>
