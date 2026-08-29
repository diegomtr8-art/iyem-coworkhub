<script setup lang="ts">
/**
 * F — Cuenta suspendida.
 *
 * **Sin bucle de redirección**: es una pantalla final, no un rebote. El
 * middleware que trae aquí ignora esta misma ruta, así que no puede
 * redirigirse a sí mismo — el error de A.1 con otro disfraz.
 */
import AuthLayout from '@/Layouts/AuthLayout.vue'
import Boton from '@/Components/Public/Boton.vue'
import { router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineProps<{ motivo?: string | null }>()

const page = usePage()
const usuario = computed(() => (page.props.auth as any)?.user ?? null)
const contacto = computed(() => (page.props.nodico as any)?.email ?? 'contacto@nodico.com.mx')
const telefono = computed(() => (page.props.nodico as any)?.telefono ?? null)

const salir = () => router.post(route('logout'))
</script>

<template>
  <AuthLayout
    etiqueta="Cuenta suspendida"
    numero="10"
    titulo="Tu cuenta está pausada"
    frase="Hablémoslo y lo resolvemos"
  >
    <p class="font-body text-cuerpo-lg text-dark/70">
      {{ motivo || 'Tu cuenta de Nódico está suspendida temporalmente, así que no puedes usar el portal ni reservar espacios.' }}
    </p>

    <div class="mt-8 border-2 border-dark bg-white p-6 shadow-dura-sm">
      <p class="font-display text-cuerpo font-bold text-dark">Qué puedes hacer</p>
      <p class="mt-3 font-body text-cuerpo text-dark/70">
        Casi siempre se arregla en una conversación: un pago pendiente, un dato por
        actualizar o algo que revisar del espacio. Escríbenos y lo vemos.
      </p>

      <div class="mt-5 grid gap-2">
        <a
          :href="`mailto:${contacto}?subject=${encodeURIComponent('Mi cuenta de Nódico está suspendida')}`"
          class="font-body text-cuerpo font-semibold text-dark underline underline-offset-4"
        >{{ contacto }}</a>

        <a
          v-if="telefono"
          :href="`tel:${telefono.replace(/\s/g, '')}`"
          class="font-body text-cuerpo font-semibold text-dark underline underline-offset-4"
        >{{ telefono }}</a>
      </div>

      <p v-if="usuario" class="mt-5 font-body text-sm text-dark/70">
        Menciona tu correo de la cuenta: <strong class="text-dark">{{ usuario.email }}</strong>
      </p>
    </div>

    <div class="mt-8 grid gap-3 sm:grid-cols-2">
      <Boton :href="route('home')" tamano="lg">Volver al sitio</Boton>
      <Boton variante="oscuro" tamano="lg" @click="salir">Cerrar sesión</Boton>
    </div>
  </AuthLayout>
</template>
