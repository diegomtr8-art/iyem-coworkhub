<script setup lang="ts">
/**
 * F — Desafío de segundo factor.
 *
 * Es una pantalla propia después de la contraseña, no un campo más en el login:
 * un campo suelto obligaría a abrir la app de códigos antes de saber siquiera
 * si la contraseña era correcta, y el código habría caducado al llegar.
 *
 * La lógica de servidor llega en la fase D.
 */
import AuthLayout from '@/Layouts/AuthLayout.vue'
import CodigoOTP from '@/Components/Auth/CodigoOTP.vue'
import CampoTexto from '@/Components/Auth/CampoTexto.vue'
import Boton from '@/Components/Public/Boton.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { Loader2, Smartphone } from 'lucide-vue-next'
import { ref } from 'vue'

defineProps<{ errorCodigo?: string | null }>()

const usarRecuperacion = ref(false)

const form = useForm({
  codigo: '',
  codigo_recuperacion: '',
  confiar_en_dispositivo: false,
})

function enviar() {
  form.post(route('dos-factores.desafio'), {
    onError: () => {
      form.codigo = ''
      form.codigo_recuperacion = ''
    },
  })
}
</script>

<template>
  <AuthLayout
    etiqueta="Segundo factor"
    numero="07"
    titulo="Escribe tu código"
    frase="Ni con tu contraseña basta"
  >
    <div class="flex items-start gap-3 border-l-4 border-nodo-400 bg-nodo-50 px-4 py-3">
      <Smartphone class="mt-0.5 h-5 w-5 shrink-0 text-dark" aria-hidden="true" />
      <p class="font-body text-cuerpo text-dark">
        Abre tu app de códigos —Google Authenticator, Authy, 1Password— y escribe
        el código de seis dígitos que muestra para Nódico.
      </p>
    </div>

    <form class="mt-7" novalidate @submit.prevent="enviar">
      <CodigoOTP
        v-if="!usarRecuperacion"
        v-model="form.codigo"
        etiqueta="Código de seis dígitos"
        :error="form.errors.codigo ?? errorCodigo"
        @completo="enviar"
      />

      <CampoTexto
        v-else
        v-model="form.codigo_recuperacion"
        etiqueta="Código de recuperación"
        autocomplete="one-time-code"
        requerido
        autofocus
        ayuda="Uno de los códigos que guardaste al activar el segundo factor. Cada uno sirve una sola vez."
        :error="form.errors.codigo_recuperacion"
      />

      <label class="mt-6 flex min-h-[44px] cursor-pointer items-start gap-3">
        <input
          v-model="form.confiar_en_dispositivo"
          type="checkbox"
          class="mt-0.5 h-5 w-5 shrink-0 rounded border-dark/30 text-nodo-500 focus:ring-2 focus:ring-offset-2 focus:ring-dark"
        />
        <span class="font-body text-cuerpo text-dark">
          Confiar en este dispositivo durante 30 días
          <span class="mt-0.5 block text-sm text-dark/70">
            No lo marques en un equipo compartido. Puedes revocarlo desde «Mi seguridad».
          </span>
        </span>
      </label>

      <Boton type="submit" tamano="lg" class="mt-7 w-full" :disabled="form.processing">
        <Loader2 v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
        {{ form.processing ? 'Comprobando…' : 'Verificar' }}
      </Boton>
    </form>

    <template #pie>
      <p class="font-body text-cuerpo text-dark/70">
        <button
          type="button"
          class="font-semibold text-dark underline underline-offset-4 transition hover:text-nodo-600"
          @click="usarRecuperacion = !usarRecuperacion"
        >
          {{ usarRecuperacion ? 'Usar el código de la app' : '¿Perdiste el teléfono? Usa un código de recuperación' }}
        </button>
      </p>

      <p class="mt-4 font-body text-sm text-dark/70">
        <Link :href="route('login')" class="underline underline-offset-4">Entrar con otra cuenta</Link>
      </p>
    </template>
  </AuthLayout>
</template>
