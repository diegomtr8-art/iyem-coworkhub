<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { Menu, X } from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const abierto = ref(false)
const conFondo = ref(false)

const enlaces = [
  { label: 'Inicio', ruta: 'home' },
  { label: 'Nosotros', ruta: 'nosotros' },
  { label: 'Membresías', ruta: 'membresias' },
  { label: 'Eventos', ruta: 'eventos' },
  { label: 'Actividades', ruta: 'actividades' },
]

const page = usePage()
const rutaActual = computed(() => page.url.split('?')[0])

const esActivo = (ruta: string) => {
  const destino = new URL(route(ruta), window.location.origin).pathname
  return destino === '/' ? rutaActual.value === '/' : rutaActual.value.startsWith(destino)
}

const onScroll = () => (conFondo.value = window.scrollY > 60)

onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
})
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))

// Al navegar, cierra el menú móvil.
watch(rutaActual, () => (abierto.value = false))
</script>

<template>
  <header
    class="fixed inset-x-0 top-0 z-50 transition-colors duration-300"
    :class="conFondo || abierto ? 'bg-dark shadow-lg' : 'bg-dark/80 backdrop-blur'"
  >
    <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8" aria-label="Navegación principal">
      <Link :href="route('home')" class="shrink-0 rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-nodo-400">
        <img
          src="/img/nodico/logo-nodico-blanco.png"
          alt="Nódico — inicio"
          width="150"
          height="48"
          class="h-9 w-auto sm:h-10"
        />
      </Link>

      <!-- Escritorio -->
      <ul class="hidden items-center gap-8 lg:flex">
        <li v-for="enlace in enlaces" :key="enlace.ruta">
          <Link
            :href="route(enlace.ruta)"
            :aria-current="esActivo(enlace.ruta) ? 'page' : undefined"
            class="font-body text-sm font-medium transition hover:text-nodo-400
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-nodo-400"
            :class="esActivo(enlace.ruta) ? 'text-nodo-400' : 'text-white'"
          >
            {{ enlace.label }}
          </Link>
        </li>
      </ul>

      <button
        type="button"
        class="rounded p-2 text-white transition hover:text-nodo-400 lg:hidden
               focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-400"
        :aria-expanded="abierto"
        aria-controls="menu-movil"
        :aria-label="abierto ? 'Cerrar menú' : 'Abrir menú'"
        @click="abierto = !abierto"
      >
        <X v-if="abierto" class="h-6 w-6" aria-hidden="true" />
        <Menu v-else class="h-6 w-6" aria-hidden="true" />
      </button>
    </nav>

    <!-- Móvil -->
    <div v-show="abierto" id="menu-movil" class="border-t border-white/10 bg-dark lg:hidden">
      <ul class="mx-auto max-w-7xl px-5 py-3 sm:px-8">
        <li v-for="enlace in enlaces" :key="enlace.ruta">
          <Link
            :href="route(enlace.ruta)"
            :aria-current="esActivo(enlace.ruta) ? 'page' : undefined"
            class="block rounded py-3 font-body text-base font-medium transition hover:text-nodo-400
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-400"
            :class="esActivo(enlace.ruta) ? 'text-nodo-400' : 'text-white'"
          >
            {{ enlace.label }}
          </Link>
        </li>
      </ul>
    </div>
  </header>
</template>
