<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { CalendarDays, Clock, MapPin, Users, ArrowRight } from 'lucide-vue-next'
import { ref } from 'vue'

const props = defineProps<{
  canLogin: boolean
  canRegister: boolean
  proximos: any[]
  pasados: any[]
}>()

const mobileMenuOpen = ref(false)
const activeTab = ref<'proximos' | 'pasados'>('proximos')

const navLinks = [
  { label: 'Inicio', href: '/' },
  { label: 'Nosotros', href: route('nosotros') },
  { label: 'Membresías', href: route('membresias') },
  { label: 'Eventos', href: route('eventos') },
]

function formatFecha(d: string) {
  return new Date(d).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}
</script>

<template>
  <Head title="Eventos — NODICO" />

  <div class="min-h-screen bg-white">
    <!-- NAV -->
    <nav class="fixed top-0 inset-x-0 z-50 bg-dark/95 backdrop-blur border-b border-white/10">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <a href="/" class="font-black text-xl text-white tracking-tight">NODICO</a>
          <div class="hidden lg:flex items-center gap-8">
            <a v-for="link in navLinks" :key="link.label" :href="link.href"
              :class="['text-sm font-medium transition-colors', link.href === route('eventos') ? 'text-nodo-400' : 'text-gray-400 hover:text-nodo-400']">
              {{ link.label }}
            </a>
          </div>
          <div class="hidden lg:flex items-center gap-3">
            <Link v-if="canLogin" :href="route('login')" class="text-sm text-gray-300 hover:text-white font-medium px-4 py-2 rounded-lg hover:bg-white/5 transition-all">Iniciar sesión</Link>
            <Link v-if="canRegister" :href="route('register')" class="text-sm font-bold bg-nodo-400 hover:bg-nodo-500 text-dark px-4 py-2 rounded-lg transition-all">Únete</Link>
          </div>
          <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden p-2 text-gray-400 hover:text-white">
            <div class="w-5 h-0.5 bg-current mb-1" /><div class="w-5 h-0.5 bg-current mb-1" /><div class="w-5 h-0.5 bg-current" />
          </button>
        </div>
        <div v-if="mobileMenuOpen" class="lg:hidden py-4 border-t border-white/10 space-y-2">
          <a v-for="link in navLinks" :key="link.label" :href="link.href" class="block px-3 py-2.5 text-sm text-gray-300 hover:text-white hover:bg-white/5 rounded-lg">{{ link.label }}</a>
          <div class="pt-2 border-t border-white/10 flex flex-col gap-2">
            <Link v-if="canLogin" :href="route('login')" class="block px-3 py-2.5 text-sm text-gray-300 rounded-lg">Iniciar sesión</Link>
            <Link v-if="canRegister" :href="route('register')" class="block px-3 py-2.5 text-sm font-bold bg-nodo-400 text-dark rounded-lg text-center">Únete ahora</Link>
          </div>
        </div>
      </div>
    </nav>

    <!-- HERO -->
    <section class="pt-32 pb-16 bg-dark text-center relative overflow-hidden">
      <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(circle, #F5C600 1px, transparent 1px); background-size: 32px 32px;" />
      <div class="relative max-w-3xl mx-auto px-4">
        <span class="inline-block text-nodo-400 text-sm font-bold uppercase tracking-widest mb-4 bg-nodo-400/10 px-4 py-2 rounded-full">Comunidad NODICO</span>
        <h1 class="text-4xl md:text-5xl font-black text-white mb-4">
          Eventos y <span class="text-nodo-400">actividades</span>
        </h1>
        <p class="text-gray-400 text-xl">Talleres, networking, charlas y mucho más. Conecta con la comunidad NODICO.</p>
      </div>
    </section>

    <!-- CONTENT -->
    <section class="py-16">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Tabs -->
        <div class="flex gap-1 bg-gray-100 rounded-xl p-1 w-fit mb-10">
          <button @click="activeTab = 'proximos'"
            :class="['px-5 py-2 text-sm font-semibold rounded-lg transition-all', activeTab === 'proximos' ? 'bg-white text-dark shadow-sm' : 'text-gray-500 hover:text-dark']">
            Próximos eventos
          </button>
          <button @click="activeTab = 'pasados'"
            :class="['px-5 py-2 text-sm font-semibold rounded-lg transition-all', activeTab === 'pasados' ? 'bg-white text-dark shadow-sm' : 'text-gray-500 hover:text-dark']">
            Pasados
          </button>
        </div>

        <!-- Próximos -->
        <div v-if="activeTab === 'proximos'">
          <div v-if="!proximos.length" class="text-center py-20">
            <div class="text-6xl mb-4">🗓️</div>
            <h3 class="text-xl font-bold text-dark mb-2">No hay eventos próximos</h3>
            <p class="text-gray-500 mb-6">Síguenos en Instagram para no perderte los próximos eventos.</p>
            <a href="https://instagram.com/nodicocoworking" target="_blank"
              class="inline-flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 text-dark font-bold px-6 py-3 rounded-xl transition-all">
              @nodicocoworking <ArrowRight :size="16" />
            </a>
          </div>
          <div v-else class="grid md:grid-cols-2 gap-6">
            <div v-for="evento in proximos" :key="evento.id"
              class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition-all hover:-translate-y-0.5">
              <div class="bg-nodo-50 border-b border-nodo-100 px-6 py-4">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2 text-sm text-nodo-700 font-semibold">
                    <CalendarDays :size="16" />
                    {{ formatFecha(evento.fecha) }}
                  </div>
                  <span v-if="evento.solo_miembros" class="text-xs bg-dark text-nodo-400 px-2 py-0.5 rounded-full font-medium">Solo miembros</span>
                </div>
              </div>
              <div class="p-6">
                <h3 class="font-black text-dark text-lg mb-2">{{ evento.titulo }}</h3>
                <p v-if="evento.descripcion" class="text-gray-500 text-sm mb-4 line-clamp-2">{{ evento.descripcion }}</p>
                <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                  <span v-if="evento.hora_inicio" class="flex items-center gap-1.5">
                    <Clock :size="13" /> {{ evento.hora_inicio?.slice(0,5) }}{{ evento.hora_fin ? ' – ' + evento.hora_fin?.slice(0,5) : '' }}
                  </span>
                  <span v-if="evento.lugar" class="flex items-center gap-1.5">
                    <MapPin :size="13" /> {{ evento.lugar }}
                  </span>
                  <span v-if="evento.cupo_maximo" class="flex items-center gap-1.5">
                    <Users :size="13" /> Cupo: {{ evento.cupo_maximo }}
                  </span>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
                  <span class="font-black text-dark">
                    {{ evento.precio > 0 ? '$' + Number(evento.precio).toLocaleString('es-MX') : 'Gratis' }}
                  </span>
                  <a href="/#contacto" class="text-sm font-semibold text-nodo-600 hover:text-nodo-700 flex items-center gap-1">
                    Registrarme <ArrowRight :size="14" />
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pasados -->
        <div v-if="activeTab === 'pasados'">
          <div v-if="!pasados.length" class="text-center py-20">
            <div class="text-6xl mb-4">📸</div>
            <p class="text-gray-500">Próximamente encontrarás aquí el historial de nuestros eventos.</p>
          </div>
          <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div v-for="evento in pasados" :key="evento.id"
              class="bg-white rounded-2xl border border-gray-100 p-5 opacity-75">
              <div class="text-xs text-gray-400 font-medium mb-2">{{ formatFecha(evento.fecha) }}</div>
              <h3 class="font-bold text-dark">{{ evento.titulo }}</h3>
              <p v-if="evento.descripcion" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ evento.descripcion }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-dark text-center">
      <div class="max-w-2xl mx-auto px-4">
        <h2 class="text-3xl font-black text-white mb-4">¿Quieres organizar un evento?</h2>
        <p class="text-gray-400 mb-8">NODICO tiene el espacio perfecto para tu taller, charla o networking.</p>
        <a href="/#contacto" class="inline-flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 text-dark font-bold px-8 py-4 rounded-xl transition-all">
          Contáctanos <ArrowRight :size="16" />
        </a>
      </div>
    </section>

    <footer class="bg-black py-8 text-center">
      <p class="text-gray-600 text-sm">© 2025 NODICO Coworking · Mérida, Yucatán</p>
    </footer>
  </div>
</template>
