<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { Check, X, ArrowRight, Star, Zap, Users, Crown } from 'lucide-vue-next'
import { ref } from 'vue'

const props = defineProps<{
  canLogin: boolean
  canRegister: boolean
  planes: any[]
}>()

const mobileMenuOpen = ref(false)

const navLinks = [
  { label: 'Inicio', href: '/' },
  { label: 'Nosotros', href: route('nosotros') },
  { label: 'Membresías', href: route('membresias') },
  { label: 'Eventos', href: route('eventos') },
]

const planIcons: Record<string, any> = {
  default: Star,
  dia: Zap,
  semana: Star,
  mes: Crown,
  anual: Crown,
}

const faqs = [
  { q: '¿Puedo cancelar mi membresía en cualquier momento?', a: 'Sí, puedes cancelar sin penalidad. Si cancelas una membresía mensual, esta seguirá activa hasta el final del período pagado.' },
  { q: '¿Qué incluye el acceso al coworking?', a: 'Acceso a las áreas de coworking general, internet de alta velocidad, café, agua y acceso a los servicios comunes del espacio.' },
  { q: '¿Cómo funciona el sistema de horas para salas?', a: 'Las horas se descuentan automáticamente al hacer una reserva. Si cancelas con anticipación, las horas son devueltas a tu cuenta.' },
  { q: '¿Qué pasa si se me acaban las horas del mes?', a: 'Puedes seguir usando el coworking general. Las horas adicionales para salas privadas o estudios tienen un costo extra.' },
  { q: '¿El plan Match puede usarlo cualquier persona?', a: 'El plan Match está diseñado para dos personas que quieran compartir una membresía. Ambas deben registrarse y completar el proceso de Face ID.' },
  { q: '¿Qué es el Face ID?', a: 'Es nuestro sistema de control de acceso biométrico. Debes visitar las instalaciones una vez para registrarlo y activar tu acceso completo.' },
]

const openFaq = ref<number | null>(null)

const defaultFeatures = [
  { key: 'coworking', label: 'Acceso coworking general' },
  { key: 'sala', label: 'Horas sala privada/juntas' },
  { key: 'contenido', label: 'Horas estudio contenido' },
  { key: 'wifi', label: 'Internet fibra óptica' },
  { key: 'cafe', label: 'Café y amenidades' },
  { key: 'lockers', label: 'Locker personal' },
]

function planValue(plan: any, key: string): string | boolean {
  if (key === 'coworking') {
    if (plan.dias_cowork_mes === null) return '∞ días'
    if (plan.dias_cowork_mes > 0) return `${plan.dias_cowork_mes} días/mes`
    return false
  }
  if (key === 'sala') return plan.horas_sala_mes ? `${plan.horas_sala_mes}h/mes` : false
  if (key === 'contenido') return plan.horas_contenido_mes ? `${plan.horas_contenido_mes}h/mes` : false
  if (key === 'wifi') return true
  if (key === 'cafe') return true
  if (key === 'lockers') return plan.tipo === 'mes' || plan.tipo === 'anual'
  return false
}

const fallbackPlanes = [
  { id: 1, nombre: 'Day-Pass', subtitulo: 'Ideal para visitas puntuales', tipo: 'dia', precio: 79, color: '#6B7280', destacado: false, dias_cowork_mes: 1, horas_sala_mes: null, horas_contenido_mes: null, personas: 1 },
  { id: 2, nombre: 'Nodico Flex', subtitulo: 'Perfecto para freelancers', tipo: 'mes', precio: 249, color: '#3B82F6', destacado: false, dias_cowork_mes: 8, horas_sala_mes: null, horas_contenido_mes: null, personas: 1 },
  { id: 3, nombre: 'Nodico PRO', subtitulo: 'Para profesionales activos', tipo: 'mes', precio: 599, color: '#F5C600', destacado: true, dias_cowork_mes: null, horas_sala_mes: 10, horas_contenido_mes: 4, personas: 1 },
  { id: 4, nombre: 'Nodico Match', subtitulo: 'Plan compartido para dos', tipo: 'mes', precio: 799, color: '#10B981', destacado: false, dias_cowork_mes: null, horas_sala_mes: 10, horas_contenido_mes: 4, personas: 2 },
]

const displayPlanes = props.planes?.length ? props.planes : fallbackPlanes
</script>

<template>
  <Head title="Membresías — NODICO" />

  <div class="min-h-screen bg-white">
    <!-- NAV -->
    <nav class="fixed top-0 inset-x-0 z-50 bg-dark/95 backdrop-blur border-b border-white/10">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <a href="/" class="font-black text-xl text-white tracking-tight">NODICO</a>
          <div class="hidden lg:flex items-center gap-8">
            <a v-for="link in navLinks" :key="link.label" :href="link.href"
              :class="['text-sm font-medium transition-colors', link.href === route('membresias') ? 'text-nodo-400' : 'text-gray-400 hover:text-nodo-400']">
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
          <a v-for="link in navLinks" :key="link.label" :href="link.href" class="block px-3 py-2.5 text-sm text-gray-300 hover:text-white hover:bg-white/5 rounded-lg transition-all">{{ link.label }}</a>
          <div class="pt-2 border-t border-white/10 flex flex-col gap-2">
            <Link v-if="canLogin" :href="route('login')" class="block px-3 py-2.5 text-sm text-gray-300 hover:text-white rounded-lg">Iniciar sesión</Link>
            <Link v-if="canRegister" :href="route('register')" class="block px-3 py-2.5 text-sm font-bold bg-nodo-400 text-dark rounded-lg text-center">Únete ahora</Link>
          </div>
        </div>
      </div>
    </nav>

    <!-- HERO -->
    <section class="pt-32 pb-16 bg-dark text-center relative overflow-hidden">
      <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(circle, #F5C600 1px, transparent 1px); background-size: 32px 32px;" />
      <div class="relative max-w-3xl mx-auto px-4">
        <span class="inline-block text-nodo-400 text-sm font-bold uppercase tracking-widest mb-4 bg-nodo-400/10 px-4 py-2 rounded-full">Planes y membresías</span>
        <h1 class="text-4xl md:text-5xl font-black text-white mb-4">
          Elige cómo quieres <span class="text-nodo-400">trabajar en NODICO</span>
        </h1>
        <p class="text-gray-400 text-xl">Sin contratos a largo plazo. Sin sorpresas. Solo el espacio que necesitas.</p>
      </div>
    </section>

    <!-- PLANES -->
    <section class="py-20 bg-gray-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-6">
          <div v-for="plan in displayPlanes" :key="plan.id"
            :class="['bg-white rounded-3xl overflow-hidden shadow-sm border-2 flex flex-col relative transition-all hover:-translate-y-1 hover:shadow-xl',
              plan.destacado ? 'border-nodo-400 shadow-nodo-400/20' : 'border-gray-100']">
            <div v-if="plan.destacado" class="bg-nodo-400 text-dark text-xs font-black tracking-widest uppercase text-center py-2">
              ⭐ Más popular
            </div>
            <div class="h-1.5 w-full" :style="`background: ${plan.color}`" />

            <div class="p-6 flex-1 flex flex-col">
              <div class="mb-5">
                <h3 class="text-xl font-black text-dark">{{ plan.nombre }}</h3>
                <p v-if="plan.subtitulo" class="text-sm text-gray-500 mt-0.5">{{ plan.subtitulo }}</p>
                <div class="flex items-baseline gap-1 mt-3">
                  <span class="text-4xl font-black text-dark">${{ Number(plan.precio).toLocaleString('es-MX') }}</span>
                  <span class="text-gray-400 text-sm">MXN / {{ plan.tipo === 'dia' ? 'día' : 'mes' }}</span>
                </div>
                <p v-if="plan.personas === 2" class="text-xs text-emerald-600 font-semibold mt-1 bg-emerald-50 px-2 py-0.5 rounded-full inline-block">Para 2 personas</p>
              </div>

              <ul class="space-y-2.5 flex-1 mb-6">
                <li v-for="f in defaultFeatures" :key="f.key" class="flex items-center gap-2.5 text-sm">
                  <template v-if="planValue(plan, f.key)">
                    <Check :size="15" class="text-emerald-500 flex-shrink-0" />
                    <span class="text-gray-700">
                      {{ f.label }}
                      <span v-if="typeof planValue(plan, f.key) === 'string'" class="text-dark font-semibold ml-1">({{ planValue(plan, f.key) }})</span>
                    </span>
                  </template>
                  <template v-else>
                    <X :size="15" class="text-gray-300 flex-shrink-0" />
                    <span class="text-gray-300">{{ f.label }}</span>
                  </template>
                </li>
              </ul>

              <Link :href="route('register')" :class="['w-full text-center font-bold py-3 rounded-xl transition-all text-sm', plan.destacado ? 'bg-nodo-400 hover:bg-nodo-500 text-dark' : 'bg-dark hover:bg-dark/90 text-white']">
                Empezar con {{ plan.nombre }}
              </Link>
            </div>
          </div>
        </div>

        <p class="text-center text-sm text-gray-400 mt-8">
          ¿Tienes un equipo grande? <a href="/#contacto" class="text-nodo-600 font-medium hover:text-nodo-700">Contáctanos para planes corporativos →</a>
        </p>
      </div>
    </section>

    <!-- COMPARATIVA -->
    <section class="py-20">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <h2 class="text-3xl font-black text-dark">Comparativa detallada</h2>
          <p class="text-gray-500 mt-2">Todos los detalles para que elijas con confianza</p>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-gray-100 shadow-sm">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-100">
                <th class="px-5 py-4 text-left text-xs font-semibold text-gray-500 uppercase w-1/3">Característica</th>
                <th v-for="plan in displayPlanes" :key="plan.id" class="px-4 py-4 text-center text-xs font-semibold text-gray-500 uppercase">
                  <span class="block font-black text-dark text-sm">{{ plan.nombre }}</span>
                  <span class="text-nodo-600">${{ Number(plan.precio).toLocaleString('es-MX') }}</span>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="f in defaultFeatures" :key="f.key" class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium text-gray-700">{{ f.label }}</td>
                <td v-for="plan in displayPlanes" :key="plan.id" class="px-4 py-3 text-center">
                  <template v-if="planValue(plan, f.key) === true">
                    <Check :size="18" class="text-emerald-500 mx-auto" />
                  </template>
                  <template v-else-if="planValue(plan, f.key)">
                    <span class="font-semibold text-dark text-xs bg-nodo-50 text-nodo-700 px-2 py-0.5 rounded-full">{{ planValue(plan, f.key) }}</span>
                  </template>
                  <template v-else>
                    <X :size="16" class="text-gray-200 mx-auto" />
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section class="py-20 bg-gray-50">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <h2 class="text-3xl font-black text-dark">Preguntas frecuentes</h2>
        </div>
        <div class="space-y-3">
          <div v-for="(faq, i) in faqs" :key="i" class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
            <button @click="openFaq = openFaq === i ? null : i"
              class="w-full flex items-center justify-between px-6 py-4 text-left">
              <span class="font-semibold text-dark text-sm">{{ faq.q }}</span>
              <span :class="['text-2xl text-gray-400 flex-shrink-0 transition-transform', openFaq === i ? 'rotate-45' : '']">+</span>
            </button>
            <div v-if="openFaq === i" class="px-6 pb-5">
              <p class="text-gray-500 text-sm leading-relaxed">{{ faq.a }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-dark text-center">
      <div class="max-w-2xl mx-auto px-4">
        <h2 class="text-3xl font-black text-white mb-4">¿Tienes dudas? Escríbenos</h2>
        <p class="text-gray-400 mb-8">Nuestro equipo está listo para ayudarte a encontrar el plan ideal.</p>
        <a href="/#contacto" class="inline-flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 text-dark font-bold px-8 py-4 rounded-xl transition-all">
          Contactar ahora <ArrowRight :size="16" />
        </a>
      </div>
    </section>

    <footer class="bg-black py-8 text-center">
      <p class="text-gray-600 text-sm">© 2025 NODICO Coworking · Mérida, Yucatán</p>
    </footer>
  </div>
</template>
