<script setup lang="ts">
import { atraparFoco, useBloqueoScroll, useInerteFuera } from '@/composables/useBloqueoScroll'
import { Link, router, usePage } from '@inertiajs/vue3'
import { ChevronDown, LogOut, Menu, X } from 'lucide-vue-next'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const abierto = ref(false)
const conFondo = ref(false)
const menuCuentaAbierto = ref(false)

const dialogo = ref<HTMLElement | null>(null)
const botonMenu = ref<HTMLElement | null>(null)

const { bloquear, liberar } = useBloqueoScroll()
const { activar: activarInerte, desactivar: desactivarInerte } = useInerteFuera()

const enlaces = [
  { label: 'Inicio', ruta: 'home' },
  { label: 'Nosotros', ruta: 'nosotros' },
  { label: 'Membresías', ruta: 'membresias' },
  { label: 'Eventos', ruta: 'eventos' },
  { label: 'Actividades', ruta: 'actividades' },
]

const page = usePage()
const rutaActual = computed(() => page.url.split('?')[0])
const usuario = computed(() => (page.props.auth as any)?.user ?? null)
const puedeEntrar = computed(() => Boolean((page.props as any).canLogin))
const puedeRegistrarse = computed(() => Boolean((page.props as any).canRegister))
// El destino y la etiqueta salen del payload compartido: comparar `tipo === 'admin'`
// dejaba fuera a recepcion (`staff`), que tambien trabaja en el panel operativo.
const rutaPortal = computed(() => usuario.value?.portalRuta ?? null)
const etiquetaPortal = computed(() => (usuario.value?.esOperativo ? 'Panel' : 'Mi portal'))

function esActivo(ruta: string) {
  const destino = new URL(route(ruta), window.location.origin).pathname
  return destino === '/' ? rutaActual.value === '/' : rutaActual.value.startsWith(destino)
}

const onScroll = () => (conFondo.value = window.scrollY > 40)

function abrirMenu() {
  abierto.value = true
  bloquear()
  nextTick(() => {
    activarInerte(dialogo.value)
    dialogo.value?.querySelector<HTMLElement>('a, button')?.focus()
  })
}

function cerrarMenu(devolverFoco = true) {
  if (!abierto.value) return
  abierto.value = false
  desactivarInerte()
  liberar()
  if (devolverFoco) nextTick(() => botonMenu.value?.focus())
}

function alternarMenu() {
  abierto.value ? cerrarMenu() : abrirMenu()
}

function alPulsarTecla(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    menuCuentaAbierto.value = false
    if (abierto.value) {
      e.preventDefault()
      cerrarMenu()
    }

    return
  }

  if (abierto.value) atraparFoco(e, dialogo.value)
}

function alClicarFuera(e: MouseEvent) {
  const objetivo = e.target as HTMLElement
  if (! objetivo.closest('[data-menu-cuenta]')) menuCuentaAbierto.value = false
}

onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
  document.addEventListener('keydown', alPulsarTecla)
  document.addEventListener('click', alClicarFuera)
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)
  document.removeEventListener('keydown', alPulsarTecla)
  document.removeEventListener('click', alClicarFuera)
  desactivarInerte()
  liberar()
})

// Al navegar se cierra el menú, sin robarle el foco al destino.
watch(rutaActual, () => {
  cerrarMenu(false)
  menuCuentaAbierto.value = false
})

function cerrarSesion() {
  router.post(route('logout'))
}
</script>

<template>
  <header
    class="fixed inset-x-0 top-0 z-50 transition-all duration-300 ease-salida"
    :class="conFondo && !abierto ? 'bg-tinta/90 shadow-sombra backdrop-blur-md' : 'bg-gradient-to-b from-tinta/70 to-transparent'"
  >
    <!-- Tres zonas con grid: logo · navegación centrada · acciones.
         Con posicionamiento absoluto el nav se encimaba con el logo a 1024 px. -->
    <nav
      class="mx-auto grid max-w-7xl grid-cols-[auto_1fr_auto] items-center gap-6 px-5 py-4 sm:px-8"
      aria-label="Navegación principal"
    >
      <Link :href="route('home')" class="shrink-0 rounded" @click="cerrarMenu(false)">
        <img
          src="/img/nodico/logo-nodico-blanco.png"
          alt="Nódico — inicio"
          width="480"
          height="159"
          class="h-9 w-auto sm:h-10"
        />
      </Link>

      <!-- Se oculta ya en xl para que entre 1024 y 1280 px no se apretujen los
           cinco enlaces con las dos acciones. -->
      <ul class="hidden items-center justify-center gap-7 xl:flex">
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
      </ul>

      <div class="col-start-3 flex items-center justify-end gap-3">
        <div v-if="usuario" class="relative hidden xl:block" data-menu-cuenta>
          <button
            type="button"
            class="flex min-h-[44px] items-center gap-2 rounded-xl px-3 font-body text-sm text-white transition hover:text-nodo-400"
            :aria-expanded="menuCuentaAbierto"
            aria-haspopup="menu"
            @click="menuCuentaAbierto = !menuCuentaAbierto"
          >
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-nodo-400 font-display text-xs font-bold uppercase text-dark">
              {{ usuario.name?.charAt(0) }}
            </span>
            {{ usuario.name?.split(' ')[0] }}
            <ChevronDown class="h-4 w-4" aria-hidden="true" />
          </button>

          <div
            v-if="menuCuentaAbierto"
            role="menu"
            class="absolute right-0 mt-2 w-52 overflow-hidden rounded-xl bg-white shadow-sombra ring-1 ring-dark/10"
          >
            <Link
              v-if="rutaPortal"
              :href="route(rutaPortal)"
              role="menuitem"
              class="flex min-h-[44px] items-center px-4 font-body text-sm text-dark transition hover:bg-cream"
            >
              {{ etiquetaPortal }}
            </Link>
            <button
              type="button"
              role="menuitem"
              class="flex min-h-[44px] w-full items-center gap-2 px-4 font-body text-sm text-dark transition hover:bg-cream"
              @click="cerrarSesion"
            >
              <LogOut class="h-4 w-4" aria-hidden="true" />
              Cerrar sesión
            </button>
          </div>
        </div>

        <template v-else>
          <Link
            v-if="puedeEntrar"
            :href="route('login')"
            class="hidden min-h-[44px] items-center px-2 font-body text-sm font-medium text-white transition hover:text-nodo-400 xl:flex"
          >
            Iniciar sesión
          </Link>
          <Link
            v-if="puedeRegistrarse"
            :href="route('register')"
            class="hidden min-h-[44px] items-center rounded-xl bg-nodo-400 px-5 font-display text-sm font-bold text-dark transition hover:-translate-y-0.5 hover:shadow-sombra xl:flex"
          >
            Registrarse
          </Link>
        </template>

        <button
          ref="botonMenu"
          type="button"
          class="flex h-11 w-11 items-center justify-center rounded text-white transition hover:text-nodo-400 xl:hidden"
          :aria-expanded="abierto"
          aria-controls="menu-movil"
          :aria-label="abierto ? 'Cerrar menú' : 'Abrir menú'"
          @click="alternarMenu"
        >
          <X v-if="abierto" class="h-7 w-7" aria-hidden="true" />
          <Menu v-else class="h-7 w-7" aria-hidden="true" />
        </button>
      </div>
    </nav>

    <!-- A11Y-01 — diálogo real: atrapa el foco, marca el resto como inert y
         solo cierra al tocar el fondo, no cualquier hueco del contenedor. -->
    <Transition
      enter-active-class="transition-all duration-300 ease-salida"
      enter-from-class="opacity-0 -translate-y-4"
      leave-active-class="transition-all duration-200 ease-suave"
      leave-to-class="opacity-0"
    >
      <div
        v-if="abierto"
        id="menu-movil"
        ref="dialogo"
        role="dialog"
        aria-modal="true"
        aria-label="Menú de navegación"
        class="fixed inset-0 top-0 z-40 h-[100svh] bg-tinta xl:hidden"
      >
        <div class="absolute inset-0" aria-hidden="true" @click="cerrarMenu()" />

        <div class="relative flex h-full flex-col">
          <ul class="flex flex-1 flex-col justify-center gap-1 px-6 pb-6 pt-24">
            <li v-for="(enlace, i) in enlaces" :key="enlace.ruta">
              <Link
                :href="route(enlace.ruta)"
                :aria-current="esActivo(enlace.ruta) ? 'page' : undefined"
                class="flex min-h-[56px] items-baseline gap-4 py-1.5 font-display text-4xl font-extrabold transition-colors sm:text-5xl"
                :class="esActivo(enlace.ruta) ? 'text-nodo-400' : 'text-white hover:text-nodo-400'"
                @click="cerrarMenu(false)"
              >
                <span class="etiqueta-tecnica text-white/65" aria-hidden="true">0{{ i + 1 }}</span>
                {{ enlace.label }}
              </Link>
            </li>
          </ul>

          <div class="pb-segura space-y-3 px-6">
            <template v-if="usuario">
              <Link
                v-if="rutaPortal"
                :href="route(rutaPortal)"
                class="flex min-h-[56px] w-full items-center justify-center rounded-xl bg-nodo-400 px-6 font-display text-base font-bold text-dark"
                @click="cerrarMenu(false)"
              >
                {{ etiquetaPortal }}
              </Link>
              <button
                type="button"
                class="flex min-h-[56px] w-full items-center justify-center gap-2 rounded-xl border border-white/25 px-6 font-display text-base font-bold text-white"
                @click="cerrarSesion"
              >
                <LogOut class="h-4 w-4" aria-hidden="true" />
                Cerrar sesión
              </button>
            </template>

            <template v-else>
              <Link
                :href="route('membresias')"
                class="flex min-h-[56px] w-full items-center justify-center rounded-xl bg-nodo-400 px-6 font-display text-base font-bold text-dark"
                @click="cerrarMenu(false)"
              >
                Únete a Nódico
              </Link>
              <div class="grid grid-cols-2 gap-3">
                <Link
                  v-if="puedeEntrar"
                  :href="route('login')"
                  class="flex min-h-[56px] items-center justify-center rounded-xl border border-white/25 px-4 font-display text-base font-bold text-white"
                  @click="cerrarMenu(false)"
                >
                  Iniciar sesión
                </Link>
                <Link
                  v-if="puedeRegistrarse"
                  :href="route('register')"
                  class="flex min-h-[56px] items-center justify-center rounded-xl border border-white/25 px-4 font-display text-base font-bold text-white"
                  @click="cerrarMenu(false)"
                >
                  Registrarse
                </Link>
              </div>
            </template>
          </div>
        </div>
      </div>
    </Transition>
  </header>
</template>
