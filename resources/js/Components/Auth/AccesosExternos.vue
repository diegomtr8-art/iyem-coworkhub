<script setup lang="ts">
/**
 * F — Botones de acceso con proveedor externo y el separador «o con tu correo».
 *
 * Van **arriba** del formulario a propósito: quien tiene cuenta de Google entra
 * de un toque y no llega a leer el formulario; quien no, baja y lo rellena. Al
 * revés obliga a todo el mundo a saltarse los campos.
 *
 * Cada botón se dibuja solo si su proveedor está encendido en el servidor.
 * Apple llega en la fase C detrás de `NODICO_APPLE_LOGIN_ENABLED`, así que
 * mientras esté apagado ni siquiera existe en el HTML.
 */
import { usePage } from '@inertiajs/vue3'
import { Mail } from 'lucide-vue-next'
import { computed } from 'vue'

defineEmits<{ 'enlace-magico': [] }>()

const page = usePage()
const proveedores = computed(() => (page.props.proveedores ?? {}) as Record<string, boolean>)

const hayAlguno = computed(() =>
  Boolean(proveedores.value.google || proveedores.value.apple || proveedores.value.enlaceMagico),
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

      <a
        v-if="proveedores.apple"
        :href="route('oauth.redirigir', { proveedor: 'apple' })"
        class="inline-flex min-h-[52px] w-full items-center justify-center gap-3 rounded-xl border-2
               border-dark bg-dark px-5 font-display text-base font-bold text-white
               transition-all duration-200 ease-salida hover:-translate-y-0.5 hover:shadow-dura-sm
               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-500"
      >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
          <path d="M17.05 12.54c-.03-2.85 2.33-4.22 2.44-4.29-1.33-1.95-3.4-2.21-4.14-2.24-1.76-.18-3.44 1.04-4.34 1.04-.9 0-2.27-1.02-3.74-.99-1.92.03-3.7 1.12-4.69 2.84-2 3.47-.51 8.6 1.44 11.41.95 1.38 2.09 2.92 3.58 2.87 1.44-.06 1.98-.93 3.72-.93s2.23.93 3.75.9c1.55-.03 2.53-1.4 3.47-2.79 1.1-1.6 1.55-3.15 1.57-3.23-.03-.01-3.01-1.16-3.06-4.59zM14.2 3.99c.79-.96 1.33-2.3 1.18-3.63-1.14.05-2.53.76-3.35 1.72-.73.85-1.38 2.21-1.21 3.51 1.28.1 2.58-.65 3.38-1.6z"/>
        </svg>
        Continuar con Apple
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
