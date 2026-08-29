<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue'
import AccesosExternos from '@/Components/Auth/AccesosExternos.vue'
import CampoTexto from '@/Components/Auth/CampoTexto.vue'
import Boton from '@/Components/Public/Boton.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { Loader2 } from 'lucide-vue-next'

defineProps<{ canResetPassword?: boolean; status?: string }>()

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const enviar = () =>
  form.post(route('login'), { onFinish: () => form.reset('password') })
</script>

<template>
  <AuthLayout
    etiqueta="Acceso"
    numero="01"
    titulo="Entra a tu cuenta"
    subtitulo="Tu portal de Nódico: membresía, reservas y facturas."
    frase="Tu comunidad te espera"
  >
    <!--
      `status` trae mensajes del servidor que no son errores de campo: sesión
      cerrada por inactividad, contraseña restablecida, correo verificado.
    -->
    <p
      v-if="status"
      class="mb-6 border-l-4 border-nodo-400 bg-nodo-50 px-4 py-3 font-body text-cuerpo text-dark"
      role="status"
    >
      {{ status }}
    </p>

    <AccesosExternos />

    <form novalidate @submit.prevent="enviar">
      <div class="grid gap-5">
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
          autocomplete="current-password"
          requerido
          :error="form.errors.password"
        />
      </div>

      <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
        <label class="inline-flex min-h-[44px] cursor-pointer items-center gap-3 font-body text-cuerpo text-dark">
          <input
            v-model="form.remember"
            type="checkbox"
            class="h-5 w-5 rounded border-dark/30 text-nodo-500 focus:ring-2 focus:ring-offset-2 focus:ring-dark"
          />
          Mantener la sesión abierta
        </label>

        <Link
          v-if="canResetPassword"
          :href="route('password.request')"
          class="inline-flex min-h-[44px] items-center font-body text-cuerpo text-dark underline
                 underline-offset-4 transition hover:text-nodo-600
                 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-500"
        >
          Olvidé mi contraseña
        </Link>
      </div>

      <Boton type="submit" tamano="lg" class="mt-7 w-full" :disabled="form.processing">
        <Loader2 v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
        {{ form.processing ? 'Entrando…' : 'Entrar' }}
      </Boton>
    </form>

    <template #pie>
      <p class="font-body text-cuerpo text-dark/70">
        ¿Todavía no tienes cuenta?
        <Link
          :href="route('register')"
          class="font-semibold text-dark underline underline-offset-4 transition hover:text-nodo-600"
        >Crea una en un minuto</Link>
      </p>
    </template>
  </AuthLayout>
</template>
