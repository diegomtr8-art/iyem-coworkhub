<script setup lang="ts">
import { computed, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { LayoutDashboard, CalendarDays, Clock, CreditCard, Receipt, LogOut, Menu, X, Home, ShieldCheck } from 'lucide-vue-next'
import { Toaster, toast } from 'vue-sonner'
import { ref } from 'vue'

const page = usePage()
const sidebarOpen = ref(false)
const flash = computed(() => page.props.flash as any)

watch(() => flash.value, (f) => {
  if (f?.success) toast.success(f.success)
  if (f?.error)   toast.error(f.error)
}, { immediate: true })

const nav = [
  { label: 'Inicio',           href: route('portal.dashboard'),   icon: LayoutDashboard, active: 'portal.dashboard' },
  { label: 'Reservar espacio', href: route('portal.reservar'),    icon: CalendarDays,    active: 'portal.reservar' },
  { label: 'Mis reservas',     href: route('portal.reservas'),    icon: Clock,           active: 'portal.reservas' },
  { label: 'Mi membresía',     href: route('portal.suscripcion'), icon: CreditCard,      active: 'portal.suscripcion' },
  { label: 'Mis facturas',     href: route('portal.facturas'),    icon: Receipt,         active: 'portal.facturas' },
  { label: 'Mi seguridad',     href: route('seguridad'),          icon: ShieldCheck,     active: 'seguridad*' },
]
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex">
    <Toaster position="top-right" richColors />

    <!-- Sidebar desktop -->
    <aside class="hidden lg:flex lg:flex-col lg:w-64 lg:fixed lg:inset-y-0 bg-dark text-white">
      <div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
        <div>
          <div class="font-black text-lg tracking-tight text-white">NODICO</div>
          <div class="text-xs text-nodo-400">Portal Miembro</div>
        </div>
      </div>

      <nav class="flex-1 px-3 py-4 space-y-0.5">
        <Link v-for="item in nav" :key="item.label" :href="item.href"
          :class="['flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-400',
            route().current(item.active) ? 'bg-nodo-400 text-dark font-bold' : 'text-gray-400 hover:bg-white/5 hover:text-white']">
          <component :is="item.icon" :size="18" /> {{ item.label }}
        </Link>
      </nav>

      <div class="px-4 py-4 border-t border-white/10 space-y-2">
        <a :href="route('home')" class="flex items-center gap-2 px-3 py-2 text-xs text-gray-500 hover:text-white hover:bg-white/5 rounded-lg transition-all">
          <Home :size="14" /> Sitio público
        </a>
        <div class="flex items-center gap-3 px-3 py-2">
          <div class="w-8 h-8 bg-nodo-400 rounded-full flex items-center justify-center text-xs font-black uppercase text-dark">
            {{ ($page.props.auth.user as any).name?.charAt(0) }}
          </div>
          <div class="min-w-0">
            <p class="text-sm font-semibold truncate text-white">{{ ($page.props.auth.user as any).name }}</p>
            <p class="text-xs text-gray-500">Miembro</p>
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
      <nav class="px-3 py-4 space-y-0.5">
        <Link v-for="item in nav" :key="item.label" :href="item.href" @click="sidebarOpen = false"
          :class="['flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-400',
            route().current(item.active) ? 'bg-nodo-400 text-dark font-bold' : 'text-gray-400 hover:bg-white/5 hover:text-white']">
          <component :is="item.icon" :size="18" /> {{ item.label }}
        </Link>
      </nav>
      <div class="px-4 py-4 border-t border-white/10">
        <Link :href="route('logout')" method="post" as="button"
          class="w-full flex items-center gap-2 px-3 py-2 text-xs text-gray-500 hover:text-red-400 hover:bg-white/5 rounded-lg transition-all">
          <LogOut :size="14" /> Cerrar sesión
        </Link>
      </div>
    </aside>

    <div class="flex-1 min-w-0 lg:ml-64">
      <header class="bg-white border-b border-gray-200 px-4 lg:px-8 py-3 flex items-center gap-4 sticky top-0 z-10">
        <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 text-gray-400 hover:text-gray-600">
          <Menu :size="20" />
        </button>
        <span class="font-black text-dark text-sm hidden sm:block tracking-tight">NODICO</span>
        <div class="ml-auto flex items-center gap-2 text-sm text-gray-600">
          <span class="hidden sm:block">{{ ($page.props.auth.user as any).name }}</span>
          <div class="w-8 h-8 bg-nodo-400 rounded-full flex items-center justify-center text-xs font-black uppercase text-dark">
            {{ ($page.props.auth.user as any).name?.charAt(0) }}
          </div>
        </div>
      </header>

      <main class="p-4 lg:p-8">
        <slot />
      </main>
    </div>
  </div>
</template>
