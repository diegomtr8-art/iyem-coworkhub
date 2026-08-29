<script setup lang="ts">
/**
 * B/F — Destino común del alta nueva y del correo ya registrado.
 *
 * Esta pantalla es idéntica en los dos casos **a propósito**: es lo que impide
 * averiguar, desde el formulario de registro, qué direcciones tienen cuenta en
 * Nódico. Cualquier texto que distinga un caso del otro —«ya tenías cuenta»,
 * «te creamos la cuenta»— tira abajo la medida entera.
 */
import AuthLayout from '@/Layouts/AuthLayout.vue'
import Boton from '@/Components/Public/Boton.vue'
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

withDefaults(defineProps<{ correo: string; correoEnviado?: boolean }>(), { correoEnviado: true })

const page = usePage()
const contacto = computed(() => (page.props.nodico as any)?.email ?? 'contacto@nodico.com.mx')
</script>

<template>
  <AuthLayout
    etiqueta="Registro · paso 2 de 2"
    numero="02"
    titulo="Revisa tu correo"
    frase="Ya casi eres parte de esto"
  >
    <!--
      Si el correo no llego a salir se dice, en lugar de mandar a la persona a
      esperar un mensaje que no existe. El aviso es identico en los dos caminos
      del registro, asi que no delata cual fue.
    -->
    <div
      v-if="!correoEnviado"
      class="border-2 border-dark bg-nodo-50 p-6 shadow-dura-sm"
      role="alert"
    >
      <p class="font-display text-cuerpo font-bold text-dark">No pudimos enviarte el correo</p>
      <p class="mt-2 font-body text-cuerpo text-dark/70">
        Tu cuenta quedó registrada, pero el mensaje de confirmación no salió por un
        problema nuestro. Inténtalo de nuevo en unos minutos o escríbenos a
        <a
          :href="`mailto:${contacto}`"
          class="font-semibold text-dark underline underline-offset-4"
        >{{ contacto }}</a>
        y lo activamos a mano.
      </p>
    </div>

    <p v-else class="font-body text-cuerpo-lg text-dark/70">
      Enviamos un mensaje a <strong class="font-semibold text-dark">{{ correo }}</strong>.
      Ábrelo y sigue el enlace para terminar.
    </p>

    <div v-if="correoEnviado" class="mt-8 border-2 border-dark bg-white p-6 shadow-dura-sm">
      <p class="font-display text-cuerpo font-bold text-dark">Si no lo ves en unos minutos</p>
      <ul class="mt-3 space-y-2 font-body text-cuerpo text-dark/70">
        <li>· Mira en la carpeta de spam o promociones.</li>
        <li>· Comprueba que la dirección esté bien escrita.</li>
        <li>· El enlace caduca en una hora y solo sirve una vez.</li>
      </ul>
    </div>

    <div class="mt-8 grid gap-3 sm:grid-cols-2">
      <Boton :href="route('login')" tamano="lg">Ir a iniciar sesión</Boton>
      <Boton :href="route('register')" variante="oscuro" tamano="lg">Escribí mal mi correo</Boton>
    </div>

    <template #pie>
      <p class="font-body text-cuerpo text-dark/70">
        ¿Nada de esto funciona? Escríbenos a
        <a
          :href="`mailto:${contacto}`"
          class="font-semibold text-dark underline underline-offset-4"
        >{{ contacto }}</a>.
      </p>

      <p class="mt-4 font-body text-sm text-dark/70">
        <Link :href="route('home')" class="underline underline-offset-4">Volver al sitio de Nódico</Link>
      </p>
    </template>
  </AuthLayout>
</template>
