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

function esActivo(ruta: string) {
  const destino = new URL(route(ruta), window.location.origin).pathname
  return destino === '/' ? rutaActual.value === '/' : rutaActual.value.startsWith(destino)
}

const onScroll = () => (conFondo.value = window.scrollY > 40)

function alternar() {
  abierto.value = !abierto.value
  document.body.style.overflow = abierto.value ? 'hidden' : ''
}

function cerrar() {
  abierto.value = false
  document.body.style.overflow = ''
}

function alPulsarTecla(e: KeyboardEvent) {
  if (e.key === 'Escape' && abierto.value) cerrar()
}

onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
  document.addEventListener('keydown', alPulsarTecla)
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)
  document.removeEventListener('keydown', alPulsarTecla)
  document.body.style.overflow = ''
})

// Al navegar se cierra el menú.
watch(rutaActual, cerrar)
</script>

<template>
  <header
    class="fixed inset-x-0 top-0 z-50 transition-all duration-300 ease-salida"
    :class="conFondo && !abierto ? 'bg-tinta/90 shadow-sombra backdrop-blur-md' : 'bg-gradient-to-b from-tinta/70 to-transparent'"
  >
    <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8" aria-label="Navegación principal">
      <Link :href="route('home')" class="shrink-0 rounded" @click="cerrar">
        <img
          src="/img/nodico/logo-nodico-blanco.png"
          alt="Nódico — inicio"
          width="480"
          height="159"
          class="h-9 w-auto sm:h-10"
        />
      </Link>

      <ul class="hidden items-center gap-9 lg:flex">
        <li v-for="enlace in enlaces" :key="enlace.ruta">
          <Link
            :href="route(enlace.ruta)"
            :aria-current="esActivo(enlace.ruta) ? 'page' : undefined"
            class="relative py-2 font-body text-sm font-medium text-white transition-colors hover:text-nodo-400"
          >
            {{ enlace.label }}
            <span
              v-if="esActivo(enlace.ruta)"
              class="absolute -bottom-0.5 left-0 h-0.5 w-full bg-nodo-400"
              aria-hidden="true"
            />
          </Link>
        </li>
        <li>
          <Link
            :href="route('membresias')"
            class="inline-flex min-h-[44px] items-center rounded-xl bg-nodo-400 px-5 py-2
                   font-display text-sm font-bold text-dark transition hover:-translate-y-0.5 hover:shadow-sombra"
          >
            Únete
          </Link>
        </li>
      </ul>

      <button
        type="button"
        class="flex h-11 w-11 items-center justify-center rounded text-white transition hover:text-nodo-400 lg:hidden"
        :aria-expanded="abierto"
        aria-controls="menu-movil"
        :aria-label="abierto ? 'Cerrar menú' : 'Abrir menú'"
        @click="alternar"
      >
        <X v-if="abierto" class="h-7 w-7" aria-hidden="true" />
        <Menu v-else class="h-7 w-7" aria-hidden="true" />
      </button>
    </nav>

    <!-- Menú móvil a pantalla completa -->
    <Transition
      enter-active-class="transition-all duration-300 ease-salida"
      enter-from-class="opacity-0 -translate-y-4"
      leave-active-class="transition-all duration-200 ease-suave"
      leave-to-class="opacity-0"
    >
      <div
        v-if="abierto"
        id="menu-movil"
        class="fixed inset-0 top-0 z-40 flex h-[100svh] flex-col bg-tinta lg:hidden"
        @click.self="cerrar"
      >
        <ul class="flex flex-1 flex-col justify-center gap-2 px-6 pb-24 pt-24">
          <li v-for="(enlace, i) in enlaces" :key="enlace.ruta">
            <Link
              :href="route(enlace.ruta)"
              :aria-current="esActivo(enlace.ruta) ? 'page' : undefined"
              class="flex min-h-[56px] items-baseline gap-4 py-2 font-display text-4xl font-extrabold
                     transition-colors sm:text-5xl"
              :class="esActivo(enlace.ruta) ? 'text-nodo-400' : 'text-white hover:text-nodo-400'"
              @click="cerrar"
            >
              <span class="etiqueta-tecnica text-white/35" aria-hidden="true">
                0{{ i + 1 }}
              </span>
              {{ enlace.label }}
            </Link>
          </li>
        </ul>

        <div class="pb-segura px-6">
          <Link
            :href="route('membresias')"
            class="flex min-h-[56px] w-full items-center justify-center rounded-xl bg-nodo-400
                   px-6 py-4 font-display text-base font-bold text-dark"
            @click="cerrar"
          >
            Únete a Nódico
          </Link>
        </div>
      </div>
    </Transition>
  </header>
</template>
