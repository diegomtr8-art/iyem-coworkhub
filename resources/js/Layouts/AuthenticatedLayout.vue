<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  LayoutDashboard, Users, CalendarDays, Clock, Receipt,
  Megaphone, BarChart3, Building2, Tag, LogOut, Menu, X, Home, UserCheck, PartyPopper,
  ScrollText, ShieldCheck, ShieldAlert, Lightbulb, Newspaper, Wallet, DoorOpen
} from 'lucide-vue-next'
import { Toaster } from 'vue-sonner'
import BuscadorMiembro from '@/Components/Panel/BuscadorMiembro.vue'
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

const usuario = computed(() => (page.props.auth as any)?.user ?? null)

// A.3 — El menu se filtra por permiso: recepcion no debe ver Planes ni
// Reportes para descubrir al pulsarlos que le dan 403. Esto **no** es control
// de acceso —cada ruta lo hace en el servidor con su `can:`— sino cortesia.
const permisos = computed<Record<string, boolean>>(
  () => ((usePage().props.auth as any)?.permisos ?? {}),
)

const puede = (permiso: string) => permisos.value[permiso] === true

/**
 * El menu se agrupa por **con que frecuencia se usa**, no por tipo de entidad.
 * Recepcion entra veinte veces al dia al tablero, a check-ins y a la agenda; a
 * planes entra tres veces al ano. Ordenarlo alfabeticamente o por modulo pone
 * lo raro al lado de lo constante.
 */
const grupos = computed(() => [
  {
    titulo: null,
    enlaces: [
      { label: 'Hoy',          href: route('dashboard'),      icon: LayoutDashboard, active: 'dashboard', ver: puede('operar-checkins') },
      { label: 'Check-in',     href: route('checkins.index'), icon: Clock,           active: 'checkins*', ver: puede('operar-checkins') },
      { label: 'Day-pass interior', href: route('daypass.index'), icon: UserCheck,   active: 'daypass*',  ver: puede('operar-checkins') },
      { label: 'Accesos',      href: route('accesos.index'),  icon: DoorOpen,        active: 'accesos.*', ver: puede('operar-checkins') },
      { label: 'Personas',     href: route('personas.index'), icon: Users,           active: 'personas*', ver: puede('operar-checkins') },
      { label: 'Agenda',       href: route('agenda.index'),   icon: CalendarDays,    active: 'agenda*',   ver: puede('gestionar-reservas') },
      { label: 'Miembros',     href: route('miembros.index'), icon: Users,           active: 'miembros*', ver: puede('ver-miembros') },
    ],
  },
  {
    titulo: 'Atender',
    enlaces: [
      { label: 'Asesorías',    href: route('asesorias.index'), icon: Lightbulb,   active: 'asesorias*', ver: puede('gestionar-asesorias'), aviso: 'asesorias_pendientes' },
      { label: 'Eventos',      href: route('salones.index'),   icon: PartyPopper, active: 'salones*',   ver: puede('gestionar-salones') },
      { label: 'Facturación',  href: route('facturas.index'),  icon: Receipt,     active: 'facturas*',  ver: puede('gestionar-facturacion') },
      { label: 'Caja',         href: route('caja.ordenes'),    icon: Wallet,      active: 'caja*',      ver: puede('operar-caja') },
      { label: 'Anuncios',     href: route('anuncios.index'),  icon: Megaphone,   active: 'anuncios*',  ver: puede('gestionar-anuncios') },
    ],
  },
  {
    titulo: 'Configurar',
    enlaces: [
      { label: 'Espacios',     href: route('espacios.index'),      icon: Building2,   active: 'espacios*', ver: puede('gestionar-espacios') },
      { label: 'Planes',       href: route('planes.index'),        icon: Tag,         active: 'planes*',   ver: puede('gestionar-planes') },
      { label: 'Asesores',     href: route('asesores.index'),      icon: UserCheck,   active: 'asesores*', ver: puede('gestionar-catalogos') },
      { label: 'Temas de asesoría', href: route('temas.index'),    icon: Lightbulb,   active: 'temas*',    ver: puede('gestionar-catalogos') },
      { label: 'Contenido web', href: route('eventos.admin.index'), icon: Newspaper,  active: 'eventos*',  ver: puede('gestionar-eventos') },
      { label: 'Emprendedores', href: route('emprendedores.index'), icon: PartyPopper, active: 'emprendedores*', ver: puede('gestionar-eventos') },
    ],
  },
  {
    titulo: 'Revisar',
    enlaces: [
      { label: 'Reportes',     href: route('reportes.index'), icon: BarChart3,  active: 'reportes*', ver: puede('ver-reportes') },
      { label: 'Bitácora',     href: route('bitacora.index'), icon: ScrollText, active: 'bitacora*', ver: puede('ver-bitacora') },
    ],
  },
].map((grupo) => ({ ...grupo, enlaces: grupo.enlaces.filter((e) => e.ver) }))
 .filter((grupo) => grupo.enlaces.length > 0))

/** Plano, para el menu de movil. */
const nav = computed(() => grupos.value.flatMap((g) => g.enlaces))

/** Contadores que pintan el punto de aviso junto a un enlace. */
const avisos = computed<Record<string, number>>(
  () => ((page.props as any)?.avisosPanel ?? {}),
)
</script>

<template>
  <div class="flex min-h-screen bg-cream font-body text-dark">
    <Toaster position="top-right" richColors />

    <!-- Sidebar desktop -->
    <aside class="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-56 lg:flex-col bg-tinta text-white">
      <div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
        <div>
          <span class="font-display text-xl font-extrabold tracking-tight text-white">NÓDICO</span>
          <p class="-mt-0.5 font-mono text-[0.625rem] uppercase tracking-[0.14em] text-nodo-400">
            {{ ($page.props.auth.user as any).rolEtiqueta }}
          </p>
        </div>
      </div>

      <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        <div v-for="(grupo, g) in grupos" :key="g" :class="g > 0 ? 'mt-5' : ''">
          <p
            v-if="grupo.titulo"
            class="px-3 pb-1.5 font-mono text-[0.625rem] uppercase tracking-[0.14em] text-white/35"
          >{{ grupo.titulo }}</p>

          <Link
            v-for="item in grupo.enlaces" :key="item.label"
            :href="item.href"
            :aria-current="route().current(item.active) ? 'page' : undefined"
            :class="[
              'flex min-h-[38px] items-center gap-2.5 px-3 py-2 text-sm font-medium transition-colors',
              route().current(item.active)
                ? 'bg-nodo-400 font-bold text-dark'
                : 'text-white/60 hover:bg-white/5 hover:text-white'
            ]">
            <component :is="item.icon" :size="17" aria-hidden="true" />
            <span class="flex-1">{{ item.label }}</span>
            <span
              v-if="item.aviso && avisos[item.aviso]"
              class="flex h-5 min-w-[1.25rem] items-center justify-center px-1 font-mono text-[0.625rem]"
              :class="route().current(item.active) ? 'bg-dark text-nodo-400' : 'bg-amber-500 text-dark'"
            >{{ avisos[item.aviso] }}</span>
          </Link>
        </div>
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
    <aside :class="['fixed inset-y-0 left-0 z-30 w-64 bg-tinta text-white transition-transform lg:hidden', sidebarOpen ? 'translate-x-0' : '-translate-x-full']">
      <div class="flex items-center justify-between px-6 py-5 border-b border-white/10">
        <span class="font-display text-lg font-extrabold text-white">NÓDICO</span>
        <button @click="sidebarOpen = false"><X :size="20" class="text-gray-400" /></button>
      </div>
      <nav class="flex-1 px-3 py-4 space-y-0.5">
        <Link v-for="item in nav" :key="item.label" :href="item.href" @click="sidebarOpen = false"
          :aria-current="route().current(item.active) ? 'page' : undefined"
          :class="[
            'flex min-h-[44px] items-center gap-3 px-3 py-2.5 text-sm font-medium transition-colors',
            route().current(item.active)
              ? 'bg-nodo-400 font-bold text-dark'
              : 'text-white/60 hover:bg-white/5 hover:text-white'
          ]">
          <component :is="item.icon" :size="18" aria-hidden="true" />
          <span class="flex-1">{{ item.label }}</span>
          <span
            v-if="item.aviso && avisos[item.aviso]"
            class="flex h-5 min-w-[1.25rem] items-center justify-center bg-amber-500 px-1 font-mono text-[0.625rem] text-dark"
          >{{ avisos[item.aviso] }}</span>
        </Link>
      </nav>
    </aside>

    <!-- Main -->
    <div class="flex-1 min-w-0 lg:ml-56 flex flex-col min-h-screen">
      <header
        class="sticky top-0 z-10 flex items-center gap-3 border-b border-dark/20 bg-white px-4 py-2.5 lg:px-6"
      >
        <button
          type="button"
          class="flex h-10 w-10 items-center justify-center text-dark/60 hover:text-dark lg:hidden
                 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                 focus-visible:outline-dark"
          aria-label="Abrir menú"
          @click="sidebarOpen = true"
        >
          <Menu :size="20" />
        </button>

        <div class="flex min-w-0 items-center gap-1 text-sm text-dark/60">
          <slot name="breadcrumb" />
        </div>

        <!-- El buscador va en la cabecera y no en una pantalla: recepcion lo
             necesita desde donde este, con alguien esperando enfrente. -->
        <div class="ml-auto flex items-center gap-3">
          <BuscadorMiembro />

          <span
            class="flex h-8 w-8 shrink-0 items-center justify-center border border-dark bg-nodo-400
                   font-display text-[0.6875rem] font-extrabold uppercase text-dark"
            :title="($page.props.auth.user as any).name"
            aria-hidden="true"
          >{{ ($page.props.auth.user as any).name?.charAt(0) }}</span>
        </div>
      </header>

      <main class="flex-1 bg-cream px-4 pt-4 pb-segura lg:px-6 lg:pt-6">
        <!--
          G — El segundo factor es opcional, asi que no se impone: se recomienda
          donde se nota. Este es el panel que maneja miembros, cobros y datos de
          otras personas, y el aviso queda a la vista mientras no este activo.

          Es una recomendacion, no un bloqueo: `nodico.dos_factores.obligatorio_para`
          esta listo para el dia que el IYEM lo exija por rol.
        -->
        <div
          v-if="usuario && !usuario.dosFactores"
          class="mb-6 flex flex-wrap items-center justify-between gap-3 border-2 border-amber-300 bg-amber-50 px-5 py-4"
          role="note"
        >
          <div class="flex items-start gap-3">
            <ShieldAlert class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" aria-hidden="true" />
            <p class="text-sm text-amber-900">
              <strong class="font-semibold">Tu cuenta no tiene segundo factor.</strong>
              Desde aquí se manejan miembros, cobros y datos de otras personas: con la
              contraseña sola, quien la sepa entra.
            </p>
          </div>

          <Link
            :href="route('dos-factores.crear')"
            class="inline-flex min-h-[44px] shrink-0 items-center bg-amber-600 px-4 text-sm font-semibold text-white transition hover:bg-amber-700"
          >
            Activarlo
          </Link>
        </div>

        <slot />
      </main>
    </div>
  </div>
</template>
