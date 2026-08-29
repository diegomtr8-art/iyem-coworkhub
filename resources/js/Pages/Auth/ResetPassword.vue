<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue'
import CampoTexto from '@/Components/Auth/CampoTexto.vue'
import MedidorFuerza from '@/Components/Auth/MedidorFuerza.vue'
import Boton from '@/Components/Public/Boton.vue'
import { useForm } from '@inertiajs/vue3'
import { Loader2, LogOut } from 'lucide-vue-next'

const props = defineProps<{ email: string; token: string }>()

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
})

const enviar = () =>
  form.post(route('password.store'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
</script>

<template>
  <AuthLayout
    etiqueta="Contraseña nueva"
    numero="04"
    titulo="Elige tu contraseña nueva"
    :subtitulo="`Vas a cambiar la contraseña de ${email}.`"
    frase="Una contraseña larga vale más que una complicada"
  >
    <form novalidate @submit.prevent="enviar">
      <div class="grid gap-5">
        <CampoTexto
          v-model="form.password"
          etiqueta="Contraseña nueva"
          type="password"
          autocomplete="new-password"
          requerido
          autofocus
          :error="form.errors.password"
        >
          <template #bajo-campo>
            <MedidorFuerza :contrasena="form.password" :datos-personales="[email, 'nodico']" />
          </template>
        </CampoTexto>

        <CampoTexto
          v-model="form.password_confirmation"
          etiqueta="Repite la contraseña nueva"
          type="password"
          autocomplete="new-password"
          requerido
          :error="form.errors.password_confirmation"
        />
      </div>

      <!--
        Se avisa **antes** de enviar, no después: cerrar todas las sesiones es
        justo lo que quiere quien restablece porque cree que alguien entró, y
        una sorpresa para quien solo olvidó la contraseña en su portátil.
      -->
      <div class="mt-6 flex items-start gap-3 border-l-4 border-nodo-400 bg-nodo-50 px-4 py-3">
        <LogOut class="mt-0.5 h-5 w-5 shrink-0 text-dark" aria-hidden="true" />
        <p class="font-body text-cuerpo text-dark">
          Al guardarla se cerrará tu sesión en todos los demás dispositivos.
          Tendrás que volver a entrar en ellos.
        </p>
      </div>

      <!-- El error del token no pertenece a ningún campo visible: va aquí. -->
      <p v-if="form.errors.email" class="mt-4 font-body text-sm text-red-600">
        {{ form.errors.email }}
      </p>

      <Boton type="submit" tamano="lg" class="mt-7 w-full" :disabled="form.processing">
        <Loader2 v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
        {{ form.processing ? 'Guardando…' : 'Guardar y cerrar las demás sesiones' }}
      </Boton>
    </form>
  </AuthLayout>
</template>
