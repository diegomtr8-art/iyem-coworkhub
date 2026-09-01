<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  LayoutDashboard, CalendarPlus, CalendarDays, CreditCard, User as UserIcon,
  Receipt, DoorOpen, Lightbulb, LogOut, Menu, X, Home, ShieldCheck, Wallet,
} from 'lucide-vue-next'
import { Toaster, toast } from 'vue-sonner'

/**
 * Portal del miembro.
 *
 * Reconstruido en la Fase 2 sobre el sistema de Nódico: el panel gris de antes
 * hablaba a un administrador de sistemas, y quien entra aquí es un emprendedor
 * de 25 años desde su iPhone. Mismo lenguaje que el sitio público —Carmen Sans,
 * `cream`, `tinta`, bloques duros— porque es la misma marca hablándole a la
 * misma persona; que la sesión esté iniciada no la convierte en otra cosa.
 *
 * El `/dashboard` operativo **no** comparte esto a propósito: ver «Los dos
 * registros» en la skill de diseño.
 */
const page = usePage()
const menuAbierto = ref(false)
const flash = computed(() => page.props.flash as Record<string, string> | undefined)

watch(flash, (f) => {
  if (f?.success) toast.success(f.success)
  if (f?.error) toast.error(f.error)
}, { immediate: true, deep: true })

const usuario = computed(() => page.props.auth.user as { name: string; avatar?: string | null })
const iniciales = computed(() =>
  usuario.value.name.split(' ').slice(0, 2).map((p) => p.charAt(0)).join('').toUpperCase(),
)

const nav = [
  { label: 'Inicio',          href: route('portal.dashboard'),      icono: LayoutDashboard, activo: 'portal.dashboard' },
  { label: 'Reservar',        href: route('portal.reservar'),       icono: CalendarPlus,    activo: 'portal.reservar' },
  { label: 'Mis reservas',    href: route('portal.reservas'),       icono: CalendarDays,    activo: 'portal.reservas' },
  { label: 'Asesoría IYEM',   href: route('portal.asesoria'),       icono: Lightbulb,       activo: 'portal.asesoria*' },
  { label: 'Mi membresía',    href: route('portal.suscripcion'),    icono: CreditCard,      activo: 'portal.suscripcion' },
  { label: 'Mis pagos',       href: route('portal.pagos'),          icono: Wallet,          activo: 'portal.pagos*' },
  { label: 'Accesos y pagos', href: route('portal.accesos'),        icono: DoorOpen,        activo: 'portal.accesos' },
  { label: 'Datos fiscales',  href: route('portal.datos-fiscales'), icono: Receipt,         activo: 'portal.datos-fiscales*' },
  { label: 'Mi perfil',       href: route('portal.perfil'),         icono: UserIcon,        activo: 'portal.perfil*' },
]

/**
 * En móvil solo caben cuatro destinos en la barra inferior. Se eligen los que
 * se usan a diario; el resto vive en el menú. «Reservar» va en el centro
 * porque es la razón por la que la mayoría abre el portal.
 */
const navMovil = [nav[0], nav[1], nav[2], nav[4]]

const cerrarMenu = () => { menuAbierto.value = false }
</script>

<template>
  <div class="min-h-screen bg-cream font-body text-dark">
    <Toaster position="top-center" rich-colors close-button />

    <!-- ── Barra lateral, a partir de lg ────────────────────────────────── -->
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r-2 border-dark bg-tinta lg:flex">
      <Link
        :href="route('portal.dashboard')"
        class="flex items-center gap-3 border-b-2 border-white/10 px-6 py-6 focus-visible:outline
               focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-nodo-400"
      >
        <span class="font-display text-2xl font-extrabold tracking-tight text-white">NÓDICO</span>
      </Link>

      <nav class="flex-1 overflow-y-auto px-3 py-5" aria-label="Secciones del portal">
        <ul class="space-y-1">
          <li v-for="item in nav" :key="item.label">
            <Link
              :href="item.href"
              class="flex min-h-[44px] items-center gap-3 px-3 py-2.5 font-display text-sm font-bold
                     transition-colors duration-200 ease-salida focus-visible:outline
                     focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-nodo-400"
              :class="route().current(item.activo)
                ? 'bg-nodo-400 text-dark'
                : 'text-cream/70 hover:bg-white/5 hover:text-white'"
              :aria-current="route().current(item.activo) ? 'page' : undefined"
            >
              <component :is="item.icono" :size="18" aria-hidden="true" />
              {{ item.label }}
            </Link>
          </li>
        </ul>
      </nav>

      <div class="border-t-2 border-white/10 px-3 py-4">
        <Link
          :href="route('seguridad')"
          class="flex min-h-[44px] items-center gap-2.5 px-3 py-2 font-body text-xs text-cream/60
                 transition-colors hover:text-white focus-visible:outline focus-visible:outline-2
                 focus-visible:-outline-offset-2 focus-visible:outline-nodo-400"
        >
          <ShieldCheck :size="15" aria-hidden="true" /> Mi seguridad
        </Link>
        <a
          :href="route('home')"
          class="flex min-h-[44px] items-center gap-2.5 px-3 py-2 font-body text-xs text-cream/60
                 transition-colors hover:text-white focus-visible:outline focus-visible:outline-2
                 focus-visible:-outline-offset-2 focus-visible:outline-nodo-400"
        >
          <Home :size="15" aria-hidden="true" /> Sitio público
        </a>
        <Link
          :href="route('logout')"
          method="post"
          as="button"
          class="flex min-h-[44px] w-full items-center gap-2.5 px-3 py-2 font-body text-xs text-cream/60
                 transition-colors hover:text-coral focus-visible:outline focus-visible:outline-2
                 focus-visible:-outline-offset-2 focus-visible:outline-nodo-400"
        >
          <LogOut :size="15" aria-hidden="true" /> Cerrar sesión
        </Link>
      </div>
    </aside>

    <!-- ── Menú de móvil ────────────────────────────────────────────────── -->
    <Transition
      enter-active-class="transition-opacity duration-200 ease-salida"
      leave-active-class="transition-opacity duration-200 ease-suave"
      enter-from-class="opacity-0" leave-to-class="opacity-0"
    >
      <div v-if="menuAbierto" class="fixed inset-0 z-40 bg-tinta/70 lg:hidden" @click="cerrarMenu" />
    </Transition>

    <Transition
      enter-active-class="transition-transform duration-300 ease-salida"
      leave-active-class="transition-transform duration-200 ease-suave"
      enter-from-class="-translate-x-full" leave-to-class="-translate-x-full"
    >
      <aside
        v-if="menuAbierto"
        class="fixed inset-y-0 left-0 z-50 flex w-[17rem] max-w-[85vw] flex-col bg-tinta lg:hidden"
      >
        <div class="flex items-center justify-between border-b-2 border-white/10 px-5 py-5">
          <span class="font-display text-xl font-extrabold text-white">NÓDICO</span>
          <button
            type="button"
            class="flex h-11 w-11 items-center justify-center text-cream/70 hover:text-white
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-nodo-400"
            aria-label="Cerrar menú"
            @click="cerrarMenu"
          >
            <X :size="22" />
          </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Secciones del portal">
          <ul class="space-y-1">
            <li v-for="item in nav" :key="item.label">
              <Link
                :href="item.href"
                class="flex min-h-[48px] items-center gap-3 px-3 py-3 font-display text-sm font-bold
                       focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2
                       focus-visible:outline-nodo-400"
                :class="route().current(item.activo)
                  ? 'bg-nodo-400 text-dark'
                  : 'text-cream/70 hover:bg-white/5 hover:text-white'"
                :aria-current="route().current(item.activo) ? 'page' : undefined"
                @click="cerrarMenu"
              >
                <component :is="item.icono" :size="18" aria-hidden="true" />
                {{ item.label }}
              </Link>
            </li>
          </ul>
        </nav>

        <div class="border-t-2 border-white/10 px-3 py-4 pb-segura">
          <Link
            :href="route('seguridad')" @click="cerrarMenu"
            class="flex min-h-[44px] items-center gap-2.5 px-3 font-body text-xs text-cream/60 hover:text-white"
          >
            <ShieldCheck :size="15" aria-hidden="true" /> Mi seguridad
          </Link>
          <Link
            :href="route('logout')" method="post" as="button"
            class="flex min-h-[44px] w-full items-center gap-2.5 px-3 font-body text-xs text-cream/60 hover:text-coral"
          >
            <LogOut :size="15" aria-hidden="true" /> Cerrar sesión
          </Link>
        </div>
      </aside>
    </Transition>

    <!-- ── Contenido ────────────────────────────────────────────────────── -->
    <div class="lg:pl-64">
      <header
        class="sticky top-0 z-20 flex items-center gap-3 border-b-2 border-dark bg-cream/95
               px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-cream/80 lg:px-8"
      >
        <button
          type="button"
          class="flex h-11 w-11 items-center justify-center text-dark lg:hidden
                 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                 focus-visible:outline-dark"
          aria-label="Abrir menú"
          :aria-expanded="menuAbierto"
          @click="menuAbierto = true"
        >
          <Menu :size="22" />
        </button>

        <span class="font-display text-lg font-extrabold tracking-tight text-dark lg:hidden">NÓDICO</span>

        <!-- `min-h-[44px]`: el avatar mide 36 px y el enlace se quedaba por debajo
             del área táctil mínima que exige la skill. -->
        <Link
          :href="route('portal.perfil')"
          class="ml-auto flex min-h-[44px] items-center gap-3 focus-visible:outline
                 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
        >
          <span class="hidden font-body text-sm text-dark/70 sm:block">{{ usuario.name }}</span>
          <img
            v-if="usuario.avatar"
            :src="usuario.avatar"
            :alt="''"
            width="36" height="36"
            class="h-9 w-9 border-2 border-dark object-cover"
          />
          <span
            v-else
            class="flex h-9 w-9 items-center justify-center border-2 border-dark bg-nodo-400
                   font-display text-xs font-extrabold text-dark"
            aria-hidden="true"
          >{{ iniciales }}</span>
        </Link>
      </header>

      <!-- El padding inferior deja sitio a la barra de móvil y al notch. -->
      <main class="px-4 py-6 pb-28 sm:px-6 lg:px-8 lg:py-10 lg:pb-10">
        <div class="mx-auto max-w-5xl">
          <slot />
        </div>
      </main>
    </div>

    <!-- ── Barra inferior de móvil ──────────────────────────────────────── -->
    <nav
      class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-4 border-t-2 border-dark bg-white pb-segura lg:hidden"
      aria-label="Accesos rápidos"
    >
      <Link
        v-for="item in navMovil"
        :key="item.label"
        :href="item.href"
        class="flex min-h-[56px] flex-col items-center justify-center gap-1 px-1 py-2
               font-display text-[0.6875rem] font-bold leading-tight
               focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2
               focus-visible:outline-dark"
        :class="route().current(item.activo) ? 'bg-nodo-400 text-dark' : 'text-dark/60'"
        :aria-current="route().current(item.activo) ? 'page' : undefined"
      >
        <component :is="item.icono" :size="19" aria-hidden="true" />
        <span class="truncate">{{ item.label }}</span>
      </Link>
    </nav>
  </div>
</template>
