<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue'
import CampoTexto from '@/Components/Auth/CampoTexto.vue'
import Boton from '@/Components/Public/Boton.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { Loader2 } from 'lucide-vue-next'

defineProps<{ status?: string }>()

const form = useForm({ email: '' })

const enviar = () => form.post(route('password.email'))
</script>

<template>
  <AuthLayout
    etiqueta="Recuperar acceso"
    numero="03"
    titulo="Recupera tu contraseña"
    subtitulo="Escribe tu correo y te mandamos un enlace para elegir una nueva."
    frase="Se te olvidó. Le pasa a todo el mundo."
  >
    <!--
      B — Este mensaje es idéntico exista o no la cuenta, y por eso ocupa el
      lugar del formulario en vez de aparecer como una nota al pie: es la
      respuesta completa, no un acuse.
    -->
    <div
      v-if="status"
      class="border-2 border-dark bg-white p-6 shadow-dura-sm"
      role="status"
    >
      <p class="font-display text-cuerpo font-bold text-dark">Revisa tu correo</p>
      <p class="mt-2 font-body text-cuerpo text-dark/70">{{ status }}</p>
      <p class="mt-4 font-body text-sm text-dark/70">
        El enlace caduca en una hora. Si no lo ves, mira en spam antes de pedir otro.
      </p>
    </div>

    <form v-else novalidate @submit.prevent="enviar">
      <CampoTexto
        v-model="form.email"
        etiqueta="Correo electrónico"
        type="email"
        autocomplete="email"
        inputmode="email"
        requerido
        autofocus
        :error="form.errors.email"
      />

      <Boton type="submit" tamano="lg" class="mt-6 w-full" :disabled="form.processing">
        <Loader2 v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
        {{ form.processing ? 'Enviando…' : 'Enviarme el enlace' }}
      </Boton>
    </form>

    <template #pie>
      <p class="font-body text-cuerpo text-dark/70">
        ¿Ya te acordaste?
        <Link
          :href="route('login')"
          class="font-semibold text-dark underline underline-offset-4 transition hover:text-nodo-600"
        >Volver a iniciar sesión</Link>
      </p>
    </template>
  </AuthLayout>
</template>
