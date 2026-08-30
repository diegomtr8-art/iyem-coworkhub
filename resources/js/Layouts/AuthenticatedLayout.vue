<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  LayoutDashboard, Users, CalendarDays, Clock, Receipt,
  Megaphone, BarChart3, Building2, Tag, LogOut, Menu, X, Home, UserCheck, PartyPopper,
  ScrollText, ShieldCheck, ShieldAlert
} from 'lucide-vue-next'
import { Toaster } from 'vue-sonner'
import { toast } from 'vue-sonner'
import { watch } from 'vue'

const page = usePage()
const sidebarOpen = ref(false)
const flash = computed(() => page.props.flash as any)

watch(() => flash.value, (f) => {
  if (f?.success) toast.success(f.success)
  if (f?.error)   toast.error(f.error)
  if (f?.info)    toast.info(f.info)
  if (f?.warning) toast.warning(f.warning)
}, { immediate: true })

// A.3 — El menu se filtra por permiso: recepcion no debe ver Planes ni
// Reportes para descubrir al pulsarlos que le dan 403. Esto **no** es control
// de acceso —cada ruta lo hace en el servidor con su `can:`— sino cortesia.
const usuario = computed(() => (page.props.auth as any)?.user ?? null)

const permisos = computed<Record<string, boolean>>(
  () => ((usePage().props.auth as any)?.permisos ?? {}),
)

const puede = (permiso: string) => permisos.value[permiso] === true

const nav = computed(() => [
  { label: 'Dashboard',    href: route('dashboard'),           icon: LayoutDashboard, active: 'dashboard',   ver: true },
  { label: 'Planes',       href: route('planes.index'),        icon: Tag,             active: 'planes*',     ver: puede('gestionar-planes') },
  { label: 'Espacios',     href: route('espacios.index'),      icon: Building2,       active: 'espacios*',   ver: puede('gestionar-espacios') },
  { label: 'Miembros',     href: route('miembros.index'),      icon: Users,           active: 'miembros*',   ver: puede('ver-miembros') },
  { label: 'Reservas',     href: route('reservas.index'),      icon: CalendarDays,    active: 'reservas*',   ver: puede('gestionar-reservas') },
  { label: 'Check-ins',    href: route('checkins.index'),      icon: Clock,           active: 'checkins*',   ver: puede('operar-checkins') },
  { label: 'Facturas',     href: route('facturas.index'),      icon: Receipt,         active: 'facturas*',   ver: puede('gestionar-facturacion') },
  { label: 'Anuncios',     href: route('anuncios.index'),      icon: Megaphone,       active: 'anuncios*',   ver: puede('gestionar-anuncios') },
  { label: 'Eventos',      href: route('eventos.admin.index'), icon: PartyPopper,     active: 'eventos*',    ver: puede('gestionar-eventos') },
  { label: 'Reportes',     href: route('reportes.index'),      icon: BarChart3,       active: 'reportes*',   ver: puede('ver-reportes') },
  { label: 'Bitácora',     href: route('bitacora.index'),      icon: ScrollText,      active: 'bitacora*',   ver: puede('ver-bitacora') },
  { label: 'Mi seguridad', href: route('seguridad'),           icon: ShieldCheck,     active: 'seguridad*',  ver: true },
].filter((enlace) => enlace.ver))
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex">
    <Toaster position="top-right" richColors />

    <!-- Sidebar desktop -->
    <aside class="hidden lg:flex lg:flex-col lg:w-64 lg:fixed lg:inset-y-0 bg-dark text-white">
      <div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
        <div>
          <span class="font-black text-lg tracking-tight text-white">NODICO</span>
          <p class="text-xs text-nodo-400 -mt-0.5">Panel Administrador</p>
        </div>
      </div>

      <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        <Link v-for="item in nav" :key="item.label"
          :href="item.href"
          :class="[
            'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-400',
            route().current(item.active)
              ? 'bg-nodo-400 text-dark font-bold'
              : 'text-gray-400 hover:bg-white/5 hover:text-white'
          ]">
          <component :is="item.icon" :size="18" />
          {{ item.label }}
        </Link>
      </nav>

      <div class="px-4 py-4 border-t border-white/10 space-y-2">
        <a :href="route('home')" class="flex items-center gap-2 px-3 py-2 text-xs text-gray-500 hover:text-white hover:bg-white/5 rounded-lg transition-all">
          <Home :size="14" /> Ver sitio público
        </a>
        <div class="flex items-center gap-3 px-3 py-2">
          <div class="w-8 h-8 bg-nodo-400 rounded-full flex items-center justify-center text-xs font-black uppercase text-dark">
            {{ ($page.props.auth.user as any).name?.charAt(0) }}
          </div>
          <div class="min-w-0">
            <p class="text-sm font-semibold truncate text-white">{{ ($page.props.auth.user as any).name }}</p>
            <p class="text-xs text-nodo-400">Administrador</p>
          </div>
        </div>
        <Link :href="route('logout')" method="post" as="button"
          class="w-full flex items-center gap-2 px-3 py-2 text-xs text-gray-500 hover:text-red-400 hover:bg-white/5 rounded-lg transition-all">
          <LogOut :size="14" /> Cerrar sesión
        </Link>
      </div>
    </aside>

    <!-- Mobile overlay -->
    <div v-if="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-black/60 z-20 lg:hidden" />

    <!-- Mobile sidebar -->
    <aside :class="['fixed inset-y-0 left-0 z-30 w-64 bg-dark text-white transition-transform lg:hidden', sidebarOpen ? 'translate-x-0' : '-translate-x-full']">
      <div class="flex items-center justify-between px-6 py-5 border-b border-white/10">
        <span class="font-black text-lg text-white">NODICO</span>
        <button @click="sidebarOpen = false"><X :size="20" class="text-gray-400" /></button>
      </div>
      <nav class="flex-1 px-3 py-4 space-y-0.5">
        <Link v-for="item in nav" :key="item.label" :href="item.href" @click="sidebarOpen = false"
          :class="[
            'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-400',
            route().current(item.active)
              ? 'bg-nodo-400 text-dark font-bold'
              : 'text-gray-400 hover:bg-white/5 hover:text-white'
          ]">
          <component :is="item.icon" :size="18" />
          {{ item.label }}
        </Link>
      </nav>
    </aside>

    <!-- Main -->
    <div class="flex-1 min-w-0 lg:ml-64 flex flex-col min-h-screen">
      <header class="bg-white border-b border-gray-200 px-4 lg:px-8 py-3 flex items-center gap-4 sticky top-0 z-10">
        <button @click="sidebarOpen = true" class="lg:hidden p-2 text-gray-400 hover:text-gray-600">
          <Menu :size="20" />
        </button>
        <div class="flex items-center gap-1 text-sm text-gray-500">
          <slot name="breadcrumb" />
        </div>
        <div class="ml-auto flex items-center gap-2 text-sm text-gray-600">
          <span class="hidden sm:block font-medium">{{ ($page.props.auth.user as any).name }}</span>
          <div class="w-8 h-8 bg-nodo-400 rounded-full flex items-center justify-center text-xs font-black uppercase text-dark">
            {{ ($page.props.auth.user as any).name?.charAt(0) }}
          </div>
        </div>
      </header>

      <main class="flex-1 p-4 lg:p-8">
        <!--
          G — El segundo factor es opcional, asi que no se impone: se recomienda
          donde se nota. Este es el panel que maneja miembros, cobros y datos de
          otras personas, y el aviso queda a la vista mientras no este activo.

          Es una recomendacion, no un bloqueo: `nodico.dos_factores.obligatorio_para`
          esta listo para el dia que el IYEM lo exija por rol.
        -->
        <div
          v-if="usuario && !usuario.dosFactores"
          class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4"
          role="note"
        >
          <div class="flex items-start gap-3">
            <ShieldAlert class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" aria-hidden="true" />
            <p class="text-sm text-amber-900">
              <strong class="font-semibold">Tu cuenta no tiene segundo factor.</strong>
              Desde aqui se manejan miembros, cobros y datos de otras personas: con la
              contrasena sola, quien la sepa entra.
            </p>
          </div>

          <Link
            :href="route('dos-factores.crear')"
            class="inline-flex min-h-[44px] shrink-0 items-center rounded-xl bg-amber-600 px-4 text-sm font-semibold text-white transition hover:bg-amber-700"
          >
            Activarlo
          </Link>
        </div>

        <slot />
      </main>
    </div>
  </div>
</template>
