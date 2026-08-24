<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { MapPin, Phone, Mail, Instagram, Facebook, Users, Target, Lightbulb, Heart, ArrowRight, CheckCircle } from 'lucide-vue-next'
import { ref } from 'vue'

defineProps<{ canLogin: boolean; canRegister: boolean }>()

const mobileMenuOpen = ref(false)

const navLinks = [
  { label: 'Inicio', href: '/' },
  { label: 'Nosotros', href: route('nosotros') },
  { label: 'Membresías', href: route('membresias') },
  { label: 'Eventos', href: route('eventos') },
]

const valores = [
  { emoji: '🤝', titulo: 'Comunidad', texto: 'Somos un espacio donde las personas se conectan, colaboran y se apoyan mutuamente.' },
  { emoji: '⚡', titulo: 'Impulso', texto: 'Proveemos las herramientas y el entorno ideal para que tu negocio y carrera crezcan.' },
  { emoji: '🎯', titulo: 'Enfoque', texto: 'Un ambiente diseñado para que puedas concentrarte y ser más productivo cada día.' },
  { emoji: '🌱', titulo: 'Crecimiento', texto: 'Nos comprometemos con el desarrollo profesional y personal de cada miembro.' },
]

const equipo = [
  { nombre: 'Equipo NODICO', rol: 'Gestión y operaciones', inicial: 'N' },
]

const stats = [
  { valor: '200+', label: 'Miembros activos' },
  { valor: '9', label: 'Espacios disponibles' },
  { valor: '4', label: 'Años de operación' },
  { valor: '100%', label: 'Satisfacción' },
]
</script>

<template>
  <Head title="Nosotros — NODICO" />

  <div class="min-h-screen bg-white">
    <!-- NAV -->
    <nav class="fixed top-0 inset-x-0 z-50 bg-dark/95 backdrop-blur border-b border-white/10">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <a href="/" class="font-black text-xl text-white tracking-tight">NODICO</a>

          <div class="hidden lg:flex items-center gap-8">
            <a v-for="link in navLinks" :key="link.label" :href="link.href"
              class="text-sm text-gray-400 hover:text-nodo-400 transition-colors font-medium">
              {{ link.label }}
            </a>
          </div>

          <div class="hidden lg:flex items-center gap-3">
            <Link v-if="canLogin" :href="route('login')" class="text-sm text-gray-300 hover:text-white font-medium px-4 py-2 rounded-lg hover:bg-white/5 transition-all">
              Iniciar sesión
            </Link>
            <Link v-if="canRegister" :href="route('register')" class="text-sm font-bold bg-nodo-400 hover:bg-nodo-500 text-dark px-4 py-2 rounded-lg transition-all">
              Únete
            </Link>
          </div>

          <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden p-2 text-gray-400 hover:text-white">
            <div class="w-5 h-0.5 bg-current mb-1" />
            <div class="w-5 h-0.5 bg-current mb-1" />
            <div class="w-5 h-0.5 bg-current" />
          </button>
        </div>

        <div v-if="mobileMenuOpen" class="lg:hidden py-4 border-t border-white/10 space-y-2">
          <a v-for="link in navLinks" :key="link.label" :href="link.href"
            class="block px-3 py-2.5 text-sm text-gray-300 hover:text-white hover:bg-white/5 rounded-lg transition-all">
            {{ link.label }}
          </a>
          <div class="pt-2 border-t border-white/10 flex flex-col gap-2">
            <Link v-if="canLogin" :href="route('login')" class="block px-3 py-2.5 text-sm text-gray-300 hover:text-white rounded-lg">Iniciar sesión</Link>
            <Link v-if="canRegister" :href="route('register')" class="block px-3 py-2.5 text-sm font-bold bg-nodo-400 text-dark rounded-lg text-center">Únete ahora</Link>
          </div>
        </div>
      </div>
    </nav>

    <!-- HERO -->
    <section class="pt-32 pb-20 bg-dark relative overflow-hidden">
      <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(circle, #F5C600 1px, transparent 1px); background-size: 32px 32px;" />
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="inline-block text-nodo-400 text-sm font-bold uppercase tracking-widest mb-4 bg-nodo-400/10 px-4 py-2 rounded-full">Nuestra historia</span>
        <h1 class="text-4xl md:text-6xl font-black text-white mb-6">
          El espacio donde<br/><span class="text-nodo-400">Mérida trabaja diferente</span>
        </h1>
        <p class="text-xl text-gray-400 max-w-2xl mx-auto">
          NODICO nació de la necesidad de crear un espacio de trabajo colaborativo en el corazón de Mérida, donde profesionales, emprendedores y creativos puedan conectar y crecer juntos.
        </p>
      </div>
    </section>

    <!-- STATS -->
    <section class="bg-nodo-400 py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
          <div v-for="stat in stats" :key="stat.label">
            <div class="text-4xl font-black text-dark">{{ stat.valor }}</div>
            <div class="text-sm text-dark/70 mt-1 font-medium">{{ stat.label }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- MISIÓN Y VISIÓN -->
    <section class="py-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-2 gap-12 items-center">
          <div>
            <span class="text-nodo-500 font-bold text-sm uppercase tracking-widest">Quiénes somos</span>
            <h2 class="text-4xl font-black text-dark mt-2 mb-6">Más que un espacio de trabajo</h2>
            <p class="text-gray-600 text-lg leading-relaxed mb-6">
              NODICO es el coworking de referencia en Mérida, Yucatán. Ofrecemos espacios de trabajo modernos, bien equipados y diseñados para impulsar la productividad y la colaboración entre profesionales de todos los sectores.
            </p>
            <p class="text-gray-600 leading-relaxed mb-8">
              Desde freelancers y startups hasta empresas consolidadas, nuestros miembros encuentran en NODICO el ambiente ideal para desarrollar sus proyectos con la flexibilidad que el trabajo moderno exige.
            </p>
            <div class="space-y-3">
              <div v-for="feat in ['Infraestructura de primer nivel', 'Comunidad activa y diversa', 'Eventos y networking regulares', 'Ubicación privilegiada en Mérida']"
                :key="feat" class="flex items-center gap-3">
                <CheckCircle :size="18" class="text-nodo-500 flex-shrink-0" />
                <span class="text-gray-700 font-medium">{{ feat }}</span>
              </div>
            </div>
          </div>
          <div class="bg-dark rounded-3xl p-10 text-center">
            <div class="text-6xl mb-6">🏢</div>
            <h3 class="text-2xl font-black text-white mb-4">Nuestra Misión</h3>
            <p class="text-gray-300 leading-relaxed mb-8">
              Crear el mejor ecosistema de trabajo colaborativo en Yucatán, donde cada persona encuentre el espacio, las herramientas y la comunidad que necesita para alcanzar sus metas profesionales.
            </p>
            <div class="border-t border-white/10 pt-8">
              <h3 class="text-xl font-black text-nodo-400 mb-3">Nuestra Visión</h3>
              <p class="text-gray-400 text-sm leading-relaxed">
                Ser el coworking más innovador del sureste mexicano, reconocido por transformar la manera en que los profesionales trabajan, colaboran y construyen el futuro.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- VALORES -->
    <section class="py-24 bg-gray-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <span class="text-nodo-500 font-bold text-sm uppercase tracking-widest">Lo que nos mueve</span>
          <h2 class="text-4xl font-black text-dark mt-2">Nuestros valores</h2>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="v in valores" :key="v.titulo"
            class="bg-white rounded-2xl p-7 border border-gray-100 shadow-sm hover:border-nodo-300 hover:shadow-md transition-all">
            <div class="text-4xl mb-4">{{ v.emoji }}</div>
            <h3 class="text-lg font-black text-dark mb-2">{{ v.titulo }}</h3>
            <p class="text-sm text-gray-500 leading-relaxed">{{ v.texto }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- UBICACIÓN -->
    <section class="py-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-2 gap-12 items-center">
          <div>
            <span class="text-nodo-500 font-bold text-sm uppercase tracking-widest">Encuéntranos</span>
            <h2 class="text-4xl font-black text-dark mt-2 mb-8">Estamos en el corazón de Mérida</h2>
            <div class="space-y-5">
              <div class="flex items-start gap-4">
                <div class="w-10 h-10 bg-nodo-50 rounded-xl flex items-center justify-center flex-shrink-0">
                  <MapPin :size="20" class="text-nodo-500" />
                </div>
                <div>
                  <p class="font-semibold text-dark">Dirección</p>
                  <p class="text-gray-500 text-sm mt-0.5">Mérida, Yucatán, México</p>
                </div>
              </div>
              <div class="flex items-start gap-4">
                <div class="w-10 h-10 bg-nodo-50 rounded-xl flex items-center justify-center flex-shrink-0">
                  <Mail :size="20" class="text-nodo-500" />
                </div>
                <div>
                  <p class="font-semibold text-dark">Email</p>
                  <a href="mailto:contacto@nodico.com.mx" class="text-nodo-600 text-sm hover:text-nodo-700">contacto@nodico.com.mx</a>
                </div>
              </div>
              <div class="flex items-start gap-4">
                <div class="w-10 h-10 bg-nodo-50 rounded-xl flex items-center justify-center flex-shrink-0">
                  <Instagram :size="20" class="text-nodo-500" />
                </div>
                <div>
                  <p class="font-semibold text-dark">Instagram</p>
                  <a href="https://instagram.com/nodicocoworking" target="_blank" class="text-nodo-600 text-sm hover:text-nodo-700">@nodicocoworking</a>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-nodo-50 rounded-3xl p-10 text-center border border-nodo-100">
            <div class="text-6xl mb-4">📍</div>
            <h3 class="text-2xl font-black text-dark mb-3">¡Ven a conocernos!</h3>
            <p class="text-gray-500 mb-6">Agenda una visita o escríbenos para conocer nuestros espacios y membresías.</p>
            <a href="/#contacto"
              class="inline-flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 text-dark font-bold px-6 py-3 rounded-xl transition-all">
              Agendar visita <ArrowRight :size="16" />
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-dark">
      <div class="max-w-3xl mx-auto px-4 text-center">
        <h2 class="text-4xl font-black text-white mb-4">¿Listo para unirte a NODICO?</h2>
        <p class="text-gray-400 mb-8">Descubre el plan perfecto para ti y empieza a trabajar en el mejor coworking de Mérida.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <Link :href="route('membresias')" class="bg-nodo-400 hover:bg-nodo-500 text-dark font-bold px-8 py-4 rounded-xl transition-all">
            Ver membresías
          </Link>
          <a href="/#contacto" class="border border-white/20 hover:border-white/40 text-white font-medium px-8 py-4 rounded-xl transition-all">
            Contactar
          </a>
        </div>
      </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-black py-8 text-center">
      <p class="text-gray-600 text-sm">© 2025 NODICO Coworking · Mérida, Yucatán</p>
    </footer>
  </div>
</template>
