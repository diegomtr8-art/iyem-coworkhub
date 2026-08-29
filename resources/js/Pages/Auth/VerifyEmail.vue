<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue'
import BotonReenviar from '@/Components/Auth/BotonReenviar.vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{ status?: string; correo?: string }>()

const form = useForm({})

const enviado = computed(() => props.status === 'verification-link-sent')

const reenviar = () => form.post(route('verification.send'), { preserveScroll: true })

const salir = () => router.post(route('logout'))
</script>

<template>
  <AuthLayout
    etiqueta="Verificación"
    numero="05"
    titulo="Confirma tu correo"
    frase="Un paso y estás dentro"
  >
    <p class="font-body text-cuerpo-lg text-dark/70">
      Te enviamos un enlace a
      <strong v-if="correo" class="font-semibold text-dark">{{ correo }}</strong>
      <span v-else>tu correo</span>. Ábrelo para activar tu cuenta y entrar al portal.
    </p>

    <p
      v-if="enviado"
      class="mt-6 border-l-4 border-nodo-400 bg-nodo-50 px-4 py-3 font-body text-cuerpo text-dark"
      role="status"
    >
      Listo, mandamos un enlace nuevo. El anterior dejó de servir.
    </p>

    <div class="mt-8 border-2 border-dark bg-white p-6 shadow-dura-sm">
      <p class="font-display text-cuerpo font-bold text-dark">Si no lo encuentras</p>
      <ul class="mt-3 space-y-2 font-body text-cuerpo text-dark/70">
        <li>· Revisa spam y la pestaña de promociones.</li>
        <li>· El enlace caduca en una hora y solo sirve una vez.</li>
        <li>· Cada reenvío invalida el anterior: usa siempre el último correo.</li>
      </ul>
    </div>

    <div class="mt-8">
      <BotonReenviar :procesando="form.processing" :enviado="enviado" @reenviar="reenviar" />
    </div>

    <template #pie>
      <!--
        Escribir mal el correo al registrarse es el fallo más común de este
        paso, y sin salida deja la cuenta muerta: no puede verificarse ni
        recuperarse. Se sale por la puerta de cerrar sesión y volver a empezar.
      -->
      <p class="font-body text-cuerpo text-dark/70">
        ¿Escribiste mal tu correo?
        <button
          type="button"
          class="font-semibold text-dark underline underline-offset-4 transition hover:text-nodo-600"
          @click="salir"
        >Cierra la sesión y regístrate de nuevo</button>,
        o escríbenos y lo corregimos sin perder tu cuenta.
      </p>

      <p class="mt-4 font-body text-sm text-dark/70">
        <Link :href="route('home')" class="underline underline-offset-4">Volver al sitio de Nódico</Link>
      </p>
    </template>
  </AuthLayout>
</template>
