<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const usuario = computed(() => (page.props.auth as any)?.user ?? null)
const rutaPortal = computed<string | null>(() => usuario.value?.portalRuta ?? null)
const correo = computed(() => (page.props.nodico as any)?.email ?? 'contacto@nodico.com.mx')

// La dirección que se pidió, para que la persona vea qué falló (sin dominio).
const direccion = typeof window !== 'undefined' ? window.location.pathname : ''

const volver = () => window.history.back()
</script>

<template>
  <Head title="No encontramos esa página" />

  <main class="flex min-h-screen flex-col items-center justify-center bg-tinta px-6 py-16">
    <div class="w-full max-w-xl">
      <p class="font-mono text-etiqueta uppercase text-nodo-400">Error 404 — No encontrada</p>
      <div class="mt-4 h-px w-full bg-white/20" />

      <h1 class="mt-8 font-display text-display-md text-cream">
        Esta página no existe.
      </h1>

      <p class="mt-6 max-w-prose font-body text-cuerpo-lg text-white/75">
        La dirección
        <code v-if="direccion" class="break-all font-mono text-cuerpo text-nodo-400">{{ direccion }}</code>
        no lleva a ninguna parte de Nódico. Puede que el enlace esté mal escrito, que la página se
        haya movido o que lo que buscabas ya no exista.
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
          class="inline-flex min-h-[44px] items-center justify-center border-2 px-6 font-display text-base font-bold transition"
          :class="rutaPortal
            ? 'border-white/30 text-cream hover:border-nodo-400 hover:text-nodo-400'
            : 'border-dark bg-nodo-400 text-dark shadow-dura-sm duration-200 ease-salida hover:-translate-x-1 hover:-translate-y-1 hover:shadow-dura'"
        >
          Volver al inicio
        </Link>

        <button
          type="button"
          class="inline-flex min-h-[44px] items-center justify-center px-4 font-body text-cuerpo text-white/70 underline underline-offset-4 transition hover:text-nodo-400"
          @click="volver"
        >
          Regresar a la página anterior
        </button>
      </div>

      <p class="mt-12 font-body text-cuerpo text-white/70">
        Si llegaste aquí desde un enlace de Nódico, avísanos en
        <a
          :href="`mailto:${correo}`"
          class="font-semibold text-nodo-400 underline underline-offset-4"
        >{{ correo }}</a>
        para arreglarlo.
      </p>
    </div>
  </main>
</template>
