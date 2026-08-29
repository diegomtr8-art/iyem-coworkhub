<script setup lang="ts">
/**
 * F — Botones de acceso con proveedor externo y el separador «o con tu correo».
 *
 * Van **arriba** del formulario a propósito: quien tiene cuenta de Google entra
 * de un toque y no llega a leer el formulario; quien no, baja y lo rellena. Al
 * revés obliga a todo el mundo a saltarse los campos.
 *
 * Cada botón se dibuja solo si su proveedor está encendido en el servidor, y
 * la ruta de cada proveedor comprueba el mismo interruptor por su cuenta: con
 * el proveedor apagado responde 404, no basta con esconder el botón.
 *
 * **Apple no está.** No se implementó porque su paquete oficial no es
 * instalable en este hosting y Nódico no tiene cuenta de Apple Developer; ver
 * `docs/AUTH-PROVEEDORES.md`.
 */
import { usePage } from '@inertiajs/vue3'
import { Mail } from 'lucide-vue-next'
import { computed } from 'vue'

defineEmits<{ 'enlace-magico': [] }>()

const page = usePage()
const proveedores = computed(() => (page.props.proveedores ?? {}) as Record<string, boolean>)

const hayAlguno = computed(() =>
  Boolean(proveedores.value.google || proveedores.value.enlaceMagico),
)
</script>

<template>
  <div v-if="hayAlguno">
    <div class="grid gap-3">
      <a
        v-if="proveedores.google"
        :href="route('oauth.redirigir', { proveedor: 'google' })"
        class="inline-flex min-h-[52px] w-full items-center justify-center gap-3 rounded-xl border-2
               border-dark bg-white px-5 font-display text-base font-bold text-dark
               transition-all duration-200 ease-salida hover:-translate-y-0.5 hover:shadow-dura-sm
               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-500"
      >
        <!-- Logotipo oficial de Google; los colores no se alteran. -->
        <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
          <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
          <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
          <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
          <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        Continuar con Google
      </a>

      <button
        v-if="proveedores.enlaceMagico"
        type="button"
        class="inline-flex min-h-[52px] w-full items-center justify-center gap-3 rounded-xl border-2
               border-dark/20 bg-transparent px-5 font-display text-base font-bold text-dark
               transition-all duration-200 ease-salida hover:border-dark
               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-500"
        @click="$emit('enlace-magico')"
      >
        <Mail class="h-5 w-5" aria-hidden="true" />
        Enviarme un enlace de acceso
      </button>
    </div>

    <!-- Separador. La línea es decorativa; el texto lo lee todo el mundo. -->
    <div class="my-7 flex items-center gap-4">
      <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
      <span class="etiqueta-tecnica text-dark/70">o con tu correo</span>
      <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
    </div>
  </div>
</template>
