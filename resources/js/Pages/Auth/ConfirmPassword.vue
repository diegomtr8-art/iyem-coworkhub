<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue'
import CampoTexto from '@/Components/Auth/CampoTexto.vue'
import Boton from '@/Components/Public/Boton.vue'
import { useForm } from '@inertiajs/vue3'
import { Loader2, ShieldCheck } from 'lucide-vue-next'

const form = useForm({ password: '' })

const enviar = () =>
  form.post(route('password.confirm'), { onFinish: () => form.reset('password') })
</script>

<template>
  <AuthLayout
    etiqueta="Confirmación"
    numero="06"
    titulo="Confirma que eres tú"
    frase="Una sesión olvidada no debería costarte la cuenta"
  >
    <!-- Explicar por qué se pide evita que la gente teclee su contraseña sin leer. -->
    <div class="flex items-start gap-3 border-l-4 border-nodo-400 bg-nodo-50 px-4 py-3">
      <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-dark" aria-hidden="true" />
      <p class="font-body text-cuerpo text-dark">
        Vas a hacer un cambio delicado en tu cuenta. Te pedimos la contraseña otra vez
        por si dejaste la sesión abierta en un equipo que no es tuyo.
      </p>
    </div>

    <form class="mt-7" novalidate @submit.prevent="enviar">
      <CampoTexto
        v-model="form.password"
        etiqueta="Tu contraseña"
        type="password"
        autocomplete="current-password"
        requerido
        autofocus
        :error="form.errors.password"
      />

      <Boton type="submit" tamano="lg" class="mt-6 w-full" :disabled="form.processing">
        <Loader2 v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
        {{ form.processing ? 'Comprobando…' : 'Continuar' }}
      </Boton>
    </form>
  </AuthLayout>
</template>
