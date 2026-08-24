<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  LayoutDashboard, Users, CalendarDays, Clock, Receipt,
  Megaphone, BarChart3, Building2, Tag, LogOut, Menu, X, Home, UserCheck, PartyPopper
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

const nav = [
  { label: 'Dashboard',  href: route('dashboard'),        icon: LayoutDashboard, active: 'dashboard' },
  { label: 'Planes',     href: route('planes.index'),     icon: Tag,             active: 'planes*' },
  { label: 'Espacios',   href: route('espacios.index'),   icon: Building2,       active: 'espacios*' },
  { label: 'Miembros',   href: route('miembros.index'),   icon: Users,           active: 'miembros*' },
  { label: 'Reservas',   href: route('reservas.index'),   icon: CalendarDays,    active: 'reservas*' },
  { label: 'Check-ins',  href: route('checkins.index'),   icon: Clock,           active: 'checkins*' },
  { label: 'Facturas',   href: route('facturas.index'),   icon: Receipt,         active: 'facturas*' },
  { label: 'Anuncios',   href: route('anuncios.index'),        icon: Megaphone,     active: 'anuncios*' },
  { label: 'Eventos',    href: route('eventos.admin.index'),   icon: PartyPopper,   active: 'eventos*' },
  { label: 'Reportes',   href: route('reportes.index'),        icon: BarChart3,     active: 'reportes*' },
]
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
            'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all',
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
            'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all',
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
    <div class="flex-1 lg:ml-64 flex flex-col min-h-screen">
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
        <slot />
      </main>
    </div>
  </div>
</template>
