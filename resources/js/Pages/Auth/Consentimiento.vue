<script setup lang="ts">
/**
 * F — Consentimiento del primer acceso con proveedor externo.
 *
 * Quien entra con Google o Apple nunca ve el formulario de registro, así que
 * nunca marca la casilla legal. Sin esta pantalla, esas cuentas quedarían
 * dentro del portal sin haber aceptado nada — que es precisamente lo que la
 * LFPDPPP no permite.
 *
 * También aparece cuando el IYEM publica una versión nueva de los documentos:
 * por eso se guarda la versión aceptada y no un simple sí/no.
 *
 * La lógica de servidor llega en la fase E.
 */
import AuthLayout from '@/Layouts/AuthLayout.vue'
import Boton from '@/Components/Public/Boton.vue'
import { router, useForm } from '@inertiajs/vue3'
import { Loader2 } from 'lucide-vue-next'
import { computed, ref } from 'vue'

const props = withDefaults(
  defineProps<{
    nombre?: string
    /** Versión vigente de los documentos; queda en la constancia. */
    version: string
    /** true cuando ya había aceptado una versión anterior. */
    esActualizacion?: boolean
    /** true si la cuenta tiene un proveedor externo vinculado. */
    viaExterna?: boolean
  }>(),
  { esActualizacion: false, viaExterna: false },
)

const form = useForm({ acepta: false, version: props.version })
const intentado = ref(false)

const falta = computed(() => intentado.value && !form.acepta)

function enviar() {
  intentado.value = true
  if (!form.acepta) return
  form.post(route('consentimiento.guardar'))
}

const salir = () => router.post(route('logout'))
</script>

<template>
  <AuthLayout
    etiqueta="Aviso legal"
    numero="09"
    :titulo="esActualizacion ? 'Actualizamos nuestros términos' : 'Antes de entrar'"
    frase="Tus datos, con reglas claras"
  >
    <p class="font-body text-cuerpo-lg text-dark/70">
      <template v-if="esActualizacion">
        {{ nombre ? nombre + ', hay' : 'Hay' }} una versión nueva del aviso de privacidad y
        los términos. Necesitamos que la aceptes para seguir usando tu cuenta.
      </template>
      <template v-else-if="viaExterna">
        {{ nombre ? '¡Hola, ' + nombre + '! Entraste' : 'Entraste' }} con tu cuenta externa,
        así que no pasaste por el formulario de registro. Solo falta que aceptes cómo
        tratamos tus datos.
      </template>

      <template v-else>
        {{ nombre ? '¡Hola, ' + nombre + '! Antes' : 'Antes' }} de entrar al portal
        necesitamos que aceptes cómo tratamos tus datos.
      </template>
    </p>

    <div class="mt-8 border-2 border-dark bg-white p-6 shadow-dura-sm">
      <p class="etiqueta-tecnica text-dark/70">Versión {{ version }}</p>

      <ul class="mt-4 space-y-3 font-body text-cuerpo text-dark/70">
        <li>· Guardamos tu nombre, correo y datos de tu membresía para darte el servicio.</li>
        <li>· No vendemos tus datos ni los compartimos con terceros con fines comerciales.</li>
        <li>· Puedes consultar, corregir o borrar tus datos cuando quieras desde tu perfil.</li>
      </ul>

      <p class="mt-5 font-body text-sm text-dark/70">
        El resumen no sustituye a los documentos:
        <a
          :href="route('privacidad')"
          target="_blank"
          rel="noopener"
          class="font-semibold text-dark underline underline-offset-4"
        >aviso de privacidad</a>
        ·
        <a
          :href="route('terminos')"
          target="_blank"
          rel="noopener"
          class="font-semibold text-dark underline underline-offset-4"
        >términos y condiciones</a>
      </p>
    </div>

    <form class="mt-7" novalidate @submit.prevent="enviar">
      <!-- Sin marcar por defecto: marcarla de antemano invalida el consentimiento. -->
      <label class="flex cursor-pointer items-start gap-3">
        <input
          v-model="form.acepta"
          type="checkbox"
          class="mt-0.5 h-5 w-5 shrink-0 rounded border-dark/30 text-nodo-500 focus:ring-2 focus:ring-offset-2 focus:ring-dark"
          :aria-invalid="falta ? 'true' : undefined"
          aria-describedby="error-consentimiento"
        />
        <span class="font-body text-cuerpo leading-relaxed text-dark">
          He leído y acepto el aviso de privacidad y los términos y condiciones de Nódico,
          en su versión {{ version }}.
        </span>
      </label>

      <p
        v-if="falta || form.errors.acepta"
        id="error-consentimiento"
        class="mt-2 font-body text-sm text-red-600"
      >
        {{ form.errors.acepta ?? 'Necesitamos que aceptes para poder continuar.' }}
      </p>

      <Boton type="submit" tamano="lg" class="mt-7 w-full" :disabled="form.processing">
        <Loader2 v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
        {{ form.processing ? 'Guardando…' : 'Aceptar y continuar' }}
      </Boton>
    </form>

    <template #pie>
      <p class="font-body text-cuerpo text-dark/70">
        ¿Prefieres no aceptar?
        <button
          type="button"
          class="font-semibold text-dark underline underline-offset-4 transition hover:text-nodo-600"
          @click="salir"
        >Cierra la sesión</button>. Tu cuenta se queda como está y puedes volver cuando quieras.
      </p>
    </template>
  </AuthLayout>
</template>
