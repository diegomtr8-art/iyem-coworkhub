<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue'
import AccesosExternos from '@/Components/Auth/AccesosExternos.vue'
import CampoTexto from '@/Components/Auth/CampoTexto.vue'
import MedidorFuerza from '@/Components/Auth/MedidorFuerza.vue'
import Boton from '@/Components/Public/Boton.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { Loader2 } from 'lucide-vue-next'
import { computed, ref } from 'vue'

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  telefono: '',
  ocupacion: '',
  acepta_legales: false,
})

// La casilla no viene marcada: marcarla de antemano invalida el consentimiento
// bajo la LFPDPPP. El servidor lo valida por su cuenta en la fase E, junto con
// la constancia (usuario, versión del documento, fecha e IP).
const intentado = ref(false)

const faltaAceptar = computed(() => intentado.value && !form.acepta_legales)

function enviar() {
  intentado.value = true

  if (!form.acepta_legales) return

  form.post(route('register'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <AuthLayout
    etiqueta="Registro"
    numero="02"
    titulo="Crea tu cuenta"
    subtitulo="Necesitamos poco: lo demás lo completas después, desde tu perfil."
    frase="Un lugar para trabajar y una red para crecer"
    ancho="amplio"
  >
    <AccesosExternos />

    <form novalidate @submit.prevent="enviar">
      <div class="grid gap-5">
        <CampoTexto
          v-model="form.name"
          etiqueta="Nombre completo"
          autocomplete="name"
          requerido
          :error="form.errors.name"
        />

        <CampoTexto
          v-model="form.email"
          etiqueta="Correo electrónico"
          type="email"
          autocomplete="email"
          inputmode="email"
          requerido
          :error="form.errors.email"
        />

        <CampoTexto
          v-model="form.password"
          etiqueta="Contraseña"
          type="password"
          autocomplete="new-password"
          requerido
          :error="form.errors.password"
        >
          <template #bajo-campo>
            <MedidorFuerza
              :contrasena="form.password"
              :datos-personales="[form.name, form.email, 'nodico', 'coworking']"
            />
          </template>
        </CampoTexto>

        <CampoTexto
          v-model="form.password_confirmation"
          etiqueta="Repite la contraseña"
          type="password"
          autocomplete="new-password"
          requerido
          :error="form.errors.password_confirmation"
        />

        <!-- Opcionales de verdad: sin asterisco y dichas como tales. -->
        <div class="grid gap-5 sm:grid-cols-2">
          <CampoTexto
            v-model="form.telefono"
            etiqueta="Teléfono (opcional)"
            type="tel"
            autocomplete="tel"
            inputmode="tel"
            :error="form.errors.telefono"
          />

          <CampoTexto
            v-model="form.ocupacion"
            etiqueta="A qué te dedicas (opcional)"
            autocomplete="organization-title"
            :error="form.errors.ocupacion"
          />
        </div>
      </div>

      <!-- Consentimiento -->
      <div class="mt-7">
        <label class="flex cursor-pointer items-start gap-3">
          <input
            v-model="form.acepta_legales"
            type="checkbox"
            class="mt-0.5 h-5 w-5 shrink-0 rounded border-dark/30 text-nodo-500 focus:ring-2 focus:ring-offset-2 focus:ring-dark"
            :aria-invalid="faltaAceptar ? 'true' : undefined"
            aria-describedby="error-legales"
          />
          <span class="font-body text-cuerpo leading-relaxed text-dark">
            He leído y acepto el
            <a
              :href="route('privacidad')"
              target="_blank"
              rel="noopener"
              class="font-semibold underline underline-offset-4 hover:text-nodo-600"
            >aviso de privacidad</a>
            y los
            <a
              :href="route('terminos')"
              target="_blank"
              rel="noopener"
              class="font-semibold underline underline-offset-4 hover:text-nodo-600"
            >términos y condiciones</a>.
          </span>
        </label>

        <p
          v-if="faltaAceptar || form.errors.acepta_legales"
          id="error-legales"
          class="mt-2 font-body text-sm text-red-600"
        >
          {{ form.errors.acepta_legales ?? 'Para crear tu cuenta necesitas aceptar el aviso de privacidad y los términos.' }}
        </p>
      </div>

      <Boton type="submit" tamano="lg" class="mt-7 w-full" :disabled="form.processing">
        <Loader2 v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
        {{ form.processing ? 'Creando tu cuenta…' : 'Crear mi cuenta' }}
      </Boton>

      <p class="mt-4 font-body text-sm text-dark/70">
        Te enviaremos un correo para confirmar que la dirección es tuya.
      </p>
    </form>

    <template #pie>
      <p class="font-body text-cuerpo text-dark/70">
        ¿Ya tienes cuenta?
        <Link
          :href="route('login')"
          class="font-semibold text-dark underline underline-offset-4 transition hover:text-nodo-600"
        >Entra aquí</Link>
      </p>
    </template>
  </AuthLayout>
</template>
