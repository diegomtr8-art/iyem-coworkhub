<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineProps<{ mensaje?: string | null }>()

const page = usePage()
const usuario = computed(() => (page.props.auth as any)?.user ?? null)

// Con un rol desconocido `portalRuta` es null: no se ofrece ningun portal.
// Ofrecerlo seria reabrir el rebote entre /dashboard y /portal que este 403
// existe para cortar.
const rutaPortal = computed<string | null>(() => usuario.value?.portalRuta ?? null)
const correo = computed(() => (page.props.nodico as any)?.email ?? 'contacto@nodico.com.mx')

const cerrarSesion = () => router.post(route('logout'))
</script>

<template>
  <Head title="Sin acceso" />

  <main class="flex min-h-screen flex-col items-center justify-center bg-tinta px-6 py-16">
    <div class="w-full max-w-xl">
      <p class="font-mono text-etiqueta uppercase text-nodo-400">Error 403 — Sin acceso</p>
      <div class="mt-4 h-px w-full bg-white/20" />

      <h1 class="mt-8 font-display text-display-md text-cream">
        Esta puerta no es la tuya.
      </h1>

      <p class="mt-6 max-w-prose font-body text-cuerpo-lg text-white/75">
        {{ mensaje || 'No tienes permiso para entrar a esta sección de Nódico.' }}
      </p>

      <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <Link
          v-if="rutaPortal"
          :href="route(rutaPortal)"
          class="inline-flex min-h-[44px] items-center justify-center border-2 border-dark bg-nodo-400 px-6 font-display text-base font-bold text-dark shadow-dura-sm transition-all duration-200 ease-salida hover:-translate-x-1 hover:-translate-y-1 hover:shadow-dura"
        >
          Ir a mi sección
        </Link>

        <Link
          :href="route('home')"
          class="inline-flex min-h-[44px] items-center justify-center border-2 border-white/30 px-6 font-display text-base font-bold text-cream transition hover:border-nodo-400 hover:text-nodo-400"
        >
          Volver al inicio
        </Link>

        <button
          v-if="usuario"
          type="button"
          class="inline-flex min-h-[44px] items-center justify-center px-4 font-body text-cuerpo text-white/70 underline underline-offset-4 transition hover:text-nodo-400"
          @click="cerrarSesion"
        >
          Cerrar sesión
        </button>
      </div>

      <p class="mt-12 font-body text-cuerpo text-white/70">
        Si crees que deberías tener acceso, escríbenos a
        <a
          :href="`mailto:${correo}`"
          class="font-semibold text-nodo-400 underline underline-offset-4"
        >{{ correo }}</a>
        y lo revisamos contigo.
      </p>
    </div>
  </main>
</template>
