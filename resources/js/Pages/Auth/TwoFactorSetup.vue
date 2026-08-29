<script setup lang="ts">
/**
 * F — Alta del segundo factor.
 *
 * Tres pasos en una sola pantalla: escanear, verificar y guardar los códigos de
 * recuperación. Los códigos se enseñan **una sola vez** y después solo se
 * pueden regenerar, porque en el servidor se guardan hasheados: si se pudieran
 * volver a mostrar, no estarían hasheados de verdad.
 *
 * La lógica de servidor llega en la fase D.
 */
import AuthLayout from '@/Layouts/AuthLayout.vue'
import CodigoOTP from '@/Components/Auth/CodigoOTP.vue'
import Boton from '@/Components/Public/Boton.vue'
import { useForm } from '@inertiajs/vue3'
import { Check, Copy, Loader2, TriangleAlert } from 'lucide-vue-next'
import { ref } from 'vue'

const props = defineProps<{
  /** SVG del QR generado en el servidor; no se carga ninguna librería de QR en el cliente. */
  qr: string
  claveManual: string
  /** Solo llega tras verificar el primer código. */
  codigosRecuperacion?: string[]
}>()

const form = useForm({ codigo: '' })
const copiado = ref(false)

const enviar = () =>
  form.post(route('dos-factores.confirmar'), { onError: () => (form.codigo = '') })

async function copiarCodigos() {
  try {
    await navigator.clipboard.writeText((props.codigosRecuperacion ?? []).join('\n'))
    copiado.value = true
    setTimeout(() => (copiado.value = false), 2500)
  } catch {
    // Sin permiso de portapapeles no pasa nada: los códigos están a la vista.
    copiado.value = false
  }
}
</script>

<template>
  <AuthLayout
    etiqueta="Segundo factor"
    numero="08"
    titulo="Activa el segundo factor"
    subtitulo="Con esto, saber tu contraseña deja de ser suficiente para entrar a tu cuenta."
    frase="La medida que de verdad para un robo de contraseña"
    ancho="amplio"
  >
    <!-- Paso 3: códigos de recuperación (solo tras verificar) -->
    <section v-if="codigosRecuperacion?.length">
      <div class="flex items-start gap-3 border-l-4 border-nodo-400 bg-nodo-50 px-4 py-3">
        <TriangleAlert class="mt-0.5 h-5 w-5 shrink-0 text-dark" aria-hidden="true" />
        <p class="font-body text-cuerpo text-dark">
          <strong class="font-semibold">Guárdalos ahora.</strong> Es la única vez que
          los verás. Si pierdes el teléfono, son tu única forma de entrar.
        </p>
      </div>

      <ul class="mt-6 grid grid-cols-2 gap-3 border-2 border-dark bg-white p-6 shadow-dura-sm">
        <li
          v-for="codigo in codigosRecuperacion"
          :key="codigo"
          class="font-mono text-base tracking-wider text-dark"
        >{{ codigo }}</li>
      </ul>

      <Boton variante="oscuro" class="mt-4 w-full" @click="copiarCodigos">
        <Check v-if="copiado" class="h-5 w-5" aria-hidden="true" />
        <Copy v-else class="h-5 w-5" aria-hidden="true" />
        {{ copiado ? 'Copiados' : 'Copiar los códigos' }}
      </Boton>

      <p class="mt-3 font-body text-sm text-dark/70" aria-live="polite">
        Cada código sirve una sola vez. Podrás generar una tanda nueva desde «Mi seguridad».
      </p>
    </section>

    <!-- Pasos 1 y 2: escanear y verificar -->
    <section v-else>
      <ol class="space-y-8">
        <li>
          <p class="etiqueta-tecnica text-dark/70">Paso 1</p>
          <h2 class="mt-2 font-display text-cuerpo-lg font-bold text-dark">
            Escanea este código con tu app
          </h2>
          <p class="mt-2 font-body text-cuerpo text-dark/70">
            Sirve Google Authenticator, Authy, 1Password o cualquier app de códigos TOTP.
          </p>

          <div class="mt-5 flex flex-col items-start gap-5 sm:flex-row sm:items-center">
            <!-- El QR viene del servidor como SVG ya saneado. -->
            <div class="border-2 border-dark bg-white p-4 shadow-dura-sm" v-html="qr" />

            <div class="min-w-0">
              <p class="font-body text-sm text-dark/70">
                ¿No puedes escanear? Escribe esta clave a mano:
              </p>
              <p class="mt-2 select-all break-all font-mono text-base tracking-wider text-dark">
                {{ claveManual }}
              </p>
            </div>
          </div>
        </li>

        <li>
          <p class="etiqueta-tecnica text-dark/70">Paso 2</p>
          <h2 class="mt-2 font-display text-cuerpo-lg font-bold text-dark">
            Escribe el código que aparece
          </h2>
          <p class="mt-2 font-body text-cuerpo text-dark/70">
            Así comprobamos que la app quedó bien configurada antes de encenderlo.
          </p>

          <form class="mt-5" novalidate @submit.prevent="enviar">
            <CodigoOTP
              v-model="form.codigo"
              etiqueta="Código de seis dígitos"
              :error="form.errors.codigo"
              @completo="enviar"
            />

            <Boton type="submit" tamano="lg" class="mt-6 w-full" :disabled="form.processing">
              <Loader2 v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
              {{ form.processing ? 'Comprobando…' : 'Activar el segundo factor' }}
            </Boton>
          </form>
        </li>
      </ol>
    </section>
  </AuthLayout>
</template>
