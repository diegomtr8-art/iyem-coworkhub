<script setup lang="ts">
/**
 * F — Confirmación del envío de un enlace mágico.
 *
 * El flujo de servidor llega en la fase C.
 */
import AuthLayout from '@/Layouts/AuthLayout.vue'
import BotonReenviar from '@/Components/Auth/BotonReenviar.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{ correo: string; reenviado?: boolean }>()

const form = useForm({ email: props.correo })

const reenviado = computed(() => Boolean(props.reenviado))

const reenviar = () => form.post(route('enlace-magico.enviar'), { preserveScroll: true })
</script>

<template>
  <AuthLayout
    etiqueta="Enlace de acceso"
    numero="11"
    titulo="Te mandamos un enlace"
    frase="Sin contraseñas que recordar"
  >
    <p class="font-body text-cuerpo-lg text-dark/70">
      Si <strong class="font-semibold text-dark">{{ correo }}</strong> tiene cuenta en Nódico,
      ya va en camino un enlace para entrar sin contraseña.
    </p>

    <div class="mt-8 border-2 border-dark bg-white p-6 shadow-dura-sm">
      <p class="font-display text-cuerpo font-bold text-dark">Cómo funciona</p>
      <ul class="mt-3 space-y-2 font-body text-cuerpo text-dark/70">
        <li>· El enlace vale <strong class="text-dark">15 minutos</strong> y sirve una sola vez.</li>
        <li>· Solo funciona en este navegador, el que lo pidió.</li>
        <li>· Si tienes segundo factor activo, te lo pediremos igualmente.</li>
      </ul>
    </div>

    <div class="mt-8">
      <BotonReenviar
        :procesando="form.processing"
        :enviado="reenviado"
        etiqueta="Enviarme otro enlace"
        @reenviar="reenviar"
      />
    </div>

    <template #pie>
      <p class="font-body text-cuerpo text-dark/70">
        <Link
          :href="route('login')"
          class="font-semibold text-dark underline underline-offset-4 transition hover:text-nodo-600"
        >Prefiero entrar con mi contraseña</Link>
      </p>
    </template>
  </AuthLayout>
</template>
