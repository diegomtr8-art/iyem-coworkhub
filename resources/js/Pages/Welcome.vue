<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { ArrowRight, Menu, X, CheckCircle } from 'lucide-vue-next'
import { ref, computed, onMounted } from 'vue'
import { useRevealOnScroll, useCursor, useCounter, useTilt } from '@/composables/useAnimations'

defineProps<{ canLogin: Boolean; canRegister: Boolean; planes?: any[] }>()

useRevealOnScroll()
useCursor()

const mobileMenuOpen = ref(false)
const navScrolled = ref(false)
const planRefs = ref<HTMLElement[]>([])

const contactForm = useForm({
  nombre: '',
  email: '',
  telefono: '',
  empresa: '',
  asunto: '',
  mensaje: '',
})

const submitContact = () => {
  contactForm.post(route('contacto.store'), {
    preserveScroll: true,
    onSuccess: () => contactForm.reset(),
  })
}

const page = usePage()
const contactoOk = computed(() => (page.props.flash as any)?.contacto_ok)

const servicios = [
  { icon: '/icons/icono-02.png', titulo: 'Espacio colaborativo de trabajo' },
  { icon: '/icons/icono-03.png', titulo: 'Wifi con 200MB de velocidad' },
  { icon: '/icons/icono-04.png', titulo: 'Sala profesional de creación de contenido' },
  { icon: '/icons/icono-05.png', titulo: 'Servicios de recepción de paquetería' },
  { icon: '/icons/icono-06.png', titulo: 'Hasta 5 invitados gratuitos al mes por membresía' },
  { icon: '/icons/icono-07.png', titulo: 'Café y agua durante todo el día' },
]

const beneficios = [
  { icon: '/icons/icono-08.png', titulo: 'Espacio pet-friendly' },
  { icon: '/icons/icono-09.png', titulo: 'Conexión directa con el ecosistema emprendedor local y nacional' },
  { icon: '/icons/icono-10.png', titulo: 'Acceso preferente a eventos, talleres y capacitaciones' },
  { icon: '/icons/icono-11.png', titulo: 'Directorio de servicios y productos de miembros Nódico' },
  { icon: '/icons/icono-02.png', titulo: 'Descuentos exclusivos en Tienda Herencia Viva' },
]

// Colores oficiales por plan, en el mismo orden en que la BD los regresa (por precio ascendente)
const planColors: Record<string, string> = {
  'DAY-PASS':     '#EF7E88',
  'NODICO FLEX':  '#FFDD00',
  'NODO PRO':     '#D6E265',
  'NODO MATCH':   '#864B95',
}
const fallbackColors = ['#EF7E88', '#FFDD00', '#D6E265', '#864B95']

const planesData = [
  { nombre: 'DAY-PASS',    desc: 'Espacio pensado para estudiantes, freelancers ocasionales o quienes necesitan trabajar por un día.', color: '#EF7E88' },
  { nombre: 'NODICO FLEX', desc: 'Opción accesible para jóvenes emprendedores o estudiantes que necesitan el espacio por horas.', color: '#FFDD00' },
  { nombre: 'NODO PRO',    desc: 'Perfecta para emprendedores y creadores que buscan un espacio de trabajo constante.', color: '#D6E265' },
  { nombre: 'NODO MATCH',  desc: 'Ideal para emprendedores, freelancers y creadores de contenido que requieren un espacio estable para trabajar.', color: '#864B95' },
]

const navLinks = [
  { label: 'Inicio',      href: '#inicio' },
  { label: 'Nosotros',    route: 'nosotros' },
  { label: 'Membresías',  route: 'membresias' },
  { label: 'Eventos',     route: 'eventos' },
  { label: 'Actividades', route: 'actividades' },
]

const stats = [
  { value: 70, suffix: '+', label: 'Capacidad en el área coworking' },
  { value: 4,  suffix: '',  label: 'Membresías disponibles' },
  { value: 2,  suffix: '',  label: 'Estudios creativos' },
  { value: 5,  suffix: '',  label: 'Cubículos privados' },
]

const marqueeItems = ['Emprendimiento', 'Creatividad', 'Comunidad', 'Innovación', 'Coworking', 'Mérida', 'IYEM', 'Nódico', 'Pet-Friendly', 'Networking']

onMounted(() => {
  const onScroll = () => { navScrolled.value = window.scrollY > 60 }
  window.addEventListener('scroll', onScroll)

  planRefs.value.forEach(el => el && useTilt(el))

  const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target as HTMLElement
        const target = parseInt(el.dataset.target || '0')
        useCounter(el, target)
        counterObserver.unobserve(el)
      }
    })
  }, { threshold: 0.5 })
  document.querySelectorAll('[data-counter]').forEach(el => counterObserver.observe(el))

  import('lenis').then(({ default: Lenis }) => {
    const lenis = new Lenis({ lerp: 0.08, smoothWheel: true })
    function raf(time: number) { lenis.raf(time); requestAnimationFrame(raf) }
    requestAnimationFrame(raf)
  }).catch(() => { /* smooth scroll is a progressive enhancement, native scroll still works */ })
})
</script>

<template>
  <Head title="Inicio | Nodico" />

  <div id="cursor-outer" class="hidden lg:block" />
  <div id="cursor-inner" class="hidden lg:block" />

  <div class="cursor-zone" style="font-family: 'Carmen Sans', sans-serif;">

  <!-- ── NAV ──────────────────────────────────────────────── -->
  <nav :class="['fixed top-0 inset-x-0 z-50 bg-dark transition-shadow duration-500', navScrolled ? 'shadow-lg shadow-black/30' : '']">
    <div class="max-w-6xl mx-auto px-6 flex items-center justify-between h-16 gap-4">
      <!-- Logo -->
      <a href="#inicio" class="flex-shrink-0" data-cursor-hover>
        <span class="text-xl font-black tracking-tight text-white" style="letter-spacing: -0.01em;">
          N<span style="text-decoration: overline; text-decoration-thickness: 2px;">ō</span>DI<span class="text-nodo-400">Co</span>
        </span>
      </a>

      <!-- Links desktop -->
      <div class="hidden lg:flex items-center gap-1 text-sm font-medium text-white">
        <a v-for="l in navLinks" :key="l.label"
          :href="l.href ?? route(l.route)" data-cursor-hover
          class="px-4 py-2 rounded-full hover:bg-white/10 transition">{{ l.label }}</a>
      </div>

      <!-- Actions -->
      <div class="hidden lg:flex items-center gap-2">
        <template v-if="canLogin">
          <Link :href="route('login')" data-cursor-hover class="text-white text-xs font-medium border border-white/30 px-4 py-2 rounded-full hover:border-nodo-400 hover:text-nodo-400 transition">
            Entrar
          </Link>
          <Link v-if="canRegister" :href="route('register')" data-cursor-hover class="magnetic bg-nodo-400 text-dark font-bold text-xs px-4 py-2 rounded-full hover:bg-nodo-500 transition">
            Únete
          </Link>
        </template>
      </div>

      <!-- Mobile hamburger -->
      <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden p-2 text-white">
        <X v-if="mobileMenuOpen" :size="20" />
        <Menu v-else :size="20" />
      </button>
    </div>

    <!-- Mobile menu -->
    <div v-if="mobileMenuOpen" class="lg:hidden bg-dark border-t border-white/10 px-6 py-4 space-y-1">
      <a v-for="l in navLinks" :key="l.label"
        :href="l.href ?? route(l.route)"
        @click="mobileMenuOpen = false"
        class="block py-2 text-sm font-medium text-white border-b border-white/10 last:border-0">
        {{ l.label }}
      </a>
      <div class="pt-3 flex gap-3" v-if="canLogin">
        <Link :href="route('login')" class="flex-1 text-center text-sm font-bold border border-white/30 py-2 rounded-full text-white">
          Entrar
        </Link>
        <Link v-if="canRegister" :href="route('register')" class="flex-1 text-center text-sm font-black bg-nodo-400 py-2 rounded-full text-dark">
          Únete
        </Link>
      </div>
    </div>
  </nav>

  <!-- ── HERO ─────────────────────────────────────────────── -->
  <section id="inicio" class="pt-16 bg-cream overflow-hidden relative">
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
      <div class="absolute top-24 left-10 w-72 h-72 rounded-full bg-nodo-400/10 animate-float" style="animation-delay: 0s;" />
      <div class="absolute bottom-10 right-24 w-40 h-40 rounded-full bg-nodo-400/10 animate-float" style="animation-delay: 2s;" />
    </div>

    <div class="max-w-6xl mx-auto px-6 py-14 lg:py-20 relative">
      <div class="relative" style="min-height: 420px;">

        <!-- Yellow hero card -->
        <div class="noise bg-nodo-400 rounded-3xl relative flex flex-col justify-center overflow-hidden reveal-left"
          style="width: 100%; min-height: 380px; padding: 48px 32px;">
          <div class="absolute top-0 right-0 w-72 h-72 rounded-full border-2 border-dark/10 opacity-50" style="transform: translate(30%, -30%);" />
          <p class="relative text-dark/60 text-sm font-medium mb-3">Bienvenidos al lugar</p>
          <h1 class="relative text-3xl sm:text-4xl lg:text-5xl font-black text-dark leading-tight mb-8">
            Donde el trabajo<br>
            es un pretexto<br>
            para crear
          </h1>
          <a href="#membresias" data-cursor-hover
            class="magnetic relative inline-flex items-center gap-2 bg-dark text-white font-bold text-sm px-6 py-3 rounded-full hover:bg-dark-light transition w-fit hover:scale-105">
            Conocer más <ArrowRight :size="14" />
          </a>
        </div>

        <!-- Photo overlapping the card (desktop only) -->
        <div class="hidden lg:block absolute rounded-3xl overflow-hidden shadow-2xl reveal-right"
          style="right: 0; top: -8%; bottom: -8%; width: 42%; z-index: 10; transition-delay: .15s;">
          <img src="/hero-nodico.jpg" alt="Nódico Coworking"
            class="w-full h-full object-cover hover:scale-105 transition-transform duration-700" style="object-position: center 30%;" />
        </div>

        <!-- Decorative "Co" dot pattern, peeking from behind the photo -->
        <div class="hidden lg:grid absolute" style="right: -18px; top: 50%; transform: translateY(-50%); grid-template-columns: repeat(2, 1fr); gap: 6px; z-index: 5;">
          <div v-for="i in 6" :key="i" class="rounded-full border-2 border-dark/10 flex items-center justify-center font-black text-nodo-500/70 animate-pulse-slow"
            :style="`width: 30px; height: 30px; font-size: 9px; animation-delay: ${i * 0.2}s`">Co</div>
        </div>

        <!-- Mobile photo below the card -->
        <div class="lg:hidden mt-4 rounded-3xl overflow-hidden h-56 reveal-scale">
          <img src="/hero-nodico.jpg" alt="Nódico Coworking" class="w-full h-full object-cover" style="object-position: center 30%;" />
        </div>
      </div>
    </div>
  </section>

  <!-- ── MARQUEE TICKER ──────────────────────────────────────── -->
  <div class="bg-dark py-4 overflow-hidden">
    <div class="flex whitespace-nowrap">
      <div class="flex animate-marquee min-w-full items-center gap-8 pr-8">
        <span v-for="item in marqueeItems" :key="item" class="inline-flex items-center gap-2 text-white/60 text-sm font-semibold tracking-widest uppercase">
          <span class="text-nodo-400 text-lg">✦</span> {{ item }}
        </span>
      </div>
      <div class="flex animate-marquee2 min-w-full items-center gap-8 pr-8" aria-hidden="true">
        <span v-for="item in marqueeItems" :key="item + '_2'" class="inline-flex items-center gap-2 text-white/60 text-sm font-semibold tracking-widest uppercase">
          <span class="text-nodo-400 text-lg">✦</span> {{ item }}
        </span>
      </div>
    </div>
  </div>

  <!-- ── STATS ────────────────────────────────────────────────── -->
  <section class="py-14 bg-cream">
    <div class="max-w-6xl mx-auto px-6">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 stagger">
        <div v-for="s in stats" :key="s.label"
          class="text-center p-6 rounded-2xl bg-cream-dark hover:shadow-lg hover:shadow-nodo-400/10 transition-all duration-300 group">
          <div class="text-3xl lg:text-4xl font-black text-dark mb-1 group-hover:text-nodo-500 transition-colors">
            <span data-counter :data-target="s.value">0</span>{{ s.suffix }}
          </div>
          <p class="text-xs text-gray-500 font-medium">{{ s.label }}</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ── SERVICIOS ─────────────────────────────────────────── -->
  <section id="servicios" class="py-14 bg-cream">
    <div class="max-w-6xl mx-auto px-6">
      <div class="flex items-center justify-between mb-8 reveal">
        <h2 class="text-3xl font-black text-dark">Servicios</h2>
        <a href="#contacto" data-cursor-hover class="inline-flex items-center gap-1.5 bg-nodo-400 border-2 border-dark text-dark text-xs font-bold px-4 py-2 rounded-lg hover:bg-nodo-500 transition">
          Conocer más <ArrowRight :size="12" />
        </a>
      </div>
      <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 stagger">
        <div v-for="s in servicios" :key="s.titulo" data-cursor-hover
          class="bg-cream-dark rounded-2xl p-4 flex flex-col items-center text-center gap-3 hover:-translate-y-1.5 hover:shadow-lg hover:shadow-nodo-400/10 transition-all duration-300">
          <img :src="s.icon" :alt="s.titulo" class="w-12 h-12 object-contain" />
          <p class="text-xs text-dark font-medium leading-tight">{{ s.titulo }}</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ── BENEFICIOS ADICIONALES ────────────────────────────── -->
  <section class="py-14 bg-cream">
    <div class="max-w-6xl mx-auto px-6">
      <div class="flex items-center justify-between mb-8 reveal">
        <h2 class="text-3xl font-black text-dark">Beneficios Adicionales</h2>
        <a href="#contacto" data-cursor-hover class="inline-flex items-center gap-1.5 bg-nodo-400 border-2 border-dark text-dark text-xs font-bold px-4 py-2 rounded-lg hover:bg-nodo-500 transition">
          Conocer más <ArrowRight :size="12" />
        </a>
      </div>
      <div class="grid grid-cols-3 sm:grid-cols-5 gap-3 stagger">
        <div v-for="b in beneficios" :key="b.titulo" data-cursor-hover
          class="bg-cream-dark rounded-2xl p-5 flex flex-col items-center text-center gap-3 hover:-translate-y-1.5 hover:shadow-lg hover:shadow-nodo-400/10 transition-all duration-300">
          <img :src="b.icon" :alt="b.titulo" class="w-12 h-12 object-contain" />
          <p class="text-xs text-dark font-medium leading-tight">{{ b.titulo }}</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ── ELIGE TU PLAN IDEAL ───────────────────────────────── -->
  <section id="membresias" class="py-14 bg-cream-dark">
    <div class="max-w-6xl mx-auto px-6">
      <div class="flex items-center justify-between mb-4 reveal">
        <h2 class="text-3xl font-black text-dark">Elige tu <strong>plan ideal</strong></h2>
        <Link v-if="canRegister" :href="route('register')" data-cursor-hover
          class="magnetic inline-flex items-center gap-1.5 bg-nodo-400 border-2 border-dark text-dark text-xs font-bold px-4 py-2 rounded-lg hover:bg-nodo-500 transition">
          ¡Regístrate aquí! <ArrowRight :size="12" />
        </Link>
      </div>
      <p class="text-gray-600 text-sm max-w-2xl mb-10 font-body reveal">
        En nuestro coworking creemos que el éxito comienza con el entorno correcto. Nuestras membresías están diseñadas para brindarte la flexibilidad, los recursos y la comunidad que necesitas para hacer crecer tu proyecto. Ya sea que busques un espacio fijo, horas flexibles o el respaldo de una red de mentes creativas, aquí encontrarás la opción perfecta para ti.
      </p>

      <!-- Plan cards from DB or fallback -->
      <div v-if="planes && planes.length" class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 stagger">
        <div v-for="(plan, i) in planes" :key="plan.id"
          :ref="el => { if (el) planRefs[i] = el as HTMLElement }"
          class="card-3d rounded-2xl overflow-hidden flex flex-col cursor-pointer" data-cursor-hover
          :style="`background: ${planColors[plan.nombre] ?? fallbackColors[i % 4]}`">
          <div class="p-6 flex-1 flex flex-col" style="min-height: 190px;">
            <h3 class="text-lg font-black text-white mb-2">{{ plan.nombre }}</h3>
            <p class="text-white/90 text-xs leading-relaxed flex-1">{{ plan.subtitulo }}</p>
            <Link :href="route('register')" class="text-xs font-bold text-white underline hover:no-underline mt-4 self-end">
              Saber más...
            </Link>
          </div>
          <div class="h-28 overflow-hidden">
            <img src="/hero-nodico.jpg" alt="" class="w-full h-full object-cover hover:scale-110 transition-transform duration-700" style="filter: brightness(0.65);" />
          </div>
        </div>
      </div>

      <!-- Fallback -->
      <div v-else class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 stagger">
        <div v-for="(p, i) in planesData" :key="p.nombre"
          :ref="el => { if (el) planRefs[i] = el as HTMLElement }"
          class="card-3d rounded-2xl overflow-hidden flex flex-col cursor-pointer" data-cursor-hover
          :style="`background: ${p.color}`">
          <div class="p-6 flex-1 flex flex-col" style="min-height: 190px;">
            <h3 class="text-lg font-black text-white mb-2">{{ p.nombre }}</h3>
            <p class="text-white/90 text-xs leading-relaxed flex-1">{{ p.desc }}</p>
            <Link :href="route('register')" class="text-xs font-bold text-white underline hover:no-underline mt-4 self-end">
              Saber más...
            </Link>
          </div>
          <div class="h-28 overflow-hidden">
            <img src="/hero-nodico.jpg" alt="" class="w-full h-full object-cover hover:scale-110 transition-transform duration-700" style="filter: brightness(0.65);" />
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ── CTA IYEM ───────────────────────────────────────────── -->
  <section class="py-14 bg-cream">
    <div class="max-w-6xl mx-auto px-6">
      <div class="noise bg-dark rounded-3xl p-10 lg:p-14 text-center relative overflow-hidden reveal-scale">
        <div class="absolute top-0 right-0 w-56 h-56 rounded-full border border-nodo-400/20" style="transform: translate(30%, -30%);" />
        <p class="relative text-white text-xl lg:text-2xl font-black leading-snug mb-6 max-w-2xl mx-auto">
          ¿Eres <span class="text-nodo-400">emprendedor</span> o artesano del Interior del Estado?
        </p>
        <a href="#contacto" data-cursor-hover
          class="magnetic relative inline-flex items-center gap-2 bg-nodo-400 border-2 border-dark text-dark font-black text-sm px-8 py-3 rounded-lg hover:bg-nodo-500 transition mb-4 hover:scale-105">
          ¡Tu daypass siempre es gratuito!
        </a>
        <br />
        <a href="#contacto" class="relative text-gray-400 text-xs underline hover:text-white transition">
          Contáctanos para más informes
        </a>
      </div>
    </div>
  </section>

  <!-- ── CTA INTERIOR DEL ESTADO ───────────────────────────── -->
  <section class="pb-14 bg-cream">
    <div class="max-w-6xl mx-auto px-6">
      <div class="noise bg-dark rounded-3xl overflow-hidden grid lg:grid-cols-2 reveal">
        <div class="h-48 lg:h-auto overflow-hidden order-2 lg:order-1">
          <img src="/hero-nodico.jpg" alt="Interior del Estado" class="w-full h-full object-cover" style="filter: brightness(0.7);" />
        </div>
        <div class="p-10 lg:p-12 flex flex-col justify-center order-1 lg:order-2 relative">
          <p class="text-white text-xl font-black leading-tight mb-6">
            ¿Tu negocio se encuentra<br>
            en el <span class="text-nodo-400">Interior del Estado</span><br>
            y necesitas tener una junta?
          </p>
          <a href="#contacto" data-cursor-hover
            class="magnetic inline-flex items-center gap-2 bg-nodo-400 border-2 border-dark text-dark font-black text-sm px-6 py-3 rounded-lg hover:bg-nodo-500 transition w-fit mb-4 hover:scale-105">
            ¡Conoce nuestro Daypass emprendedor! <ArrowRight :size="14" />
          </a>
          <a href="#contacto" class="text-gray-400 text-xs underline hover:text-white transition">
            Contáctanos para más informes
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- ── VIDEO / AGENDA TU VISITA ──────────────────────────── -->
  <section id="comunidad" class="py-14 bg-cream">
    <div class="max-w-6xl mx-auto px-6">
      <h2 class="text-3xl font-black text-dark mb-1 reveal">¿Quieres conocer más de <strong>Nódico</strong>?</h2>
      <p class="text-gray-500 font-body mb-6 reveal">¡Agenda tu visita aquí!</p>
      <!-- Video embed -->
      <div class="noise bg-dark rounded-3xl overflow-hidden aspect-video flex items-center justify-center reveal-scale">
        <iframe
          class="w-full h-full rounded-3xl"
          src="https://www.youtube.com/embed/dQw4w9WgXcQ"
          title="Conoce Nódico"
          frameborder="0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowfullscreen
        />
      </div>
    </div>
  </section>

  <!-- ── ALIADOS / PARTNERS ─────────────────────────────────── -->
  <section class="py-10 bg-cream border-t border-cream-dark">
    <div class="max-w-6xl mx-auto px-6">
      <div class="flex flex-wrap items-center justify-center gap-10 stagger">
        <!-- IYEM -->
        <div class="flex items-center gap-3 opacity-80 hover:opacity-100 transition">
          <div class="w-10 h-10 rounded-full bg-dark flex items-center justify-center flex-shrink-0">
            <span class="text-white text-xs font-black">YUC</span>
          </div>
          <div>
            <div class="text-xs font-black text-dark leading-tight">RENACIMIENTO MAYA YUCATÁN</div>
            <div class="text-xs text-gray-500 font-black leading-tight">IYEM · Gobierno del Estado</div>
          </div>
        </div>
        <!-- Canieti -->
        <div class="opacity-80 hover:opacity-100 transition">
          <span class="text-2xl font-black" style="color: #1565C0;">canieti</span>
        </div>
        <!-- Herencia Viva -->
        <div class="opacity-80 hover:opacity-100 transition text-center">
          <span class="text-sm font-black tracking-widest uppercase" style="color: #7A9A6B;">✕ Herencia Viva ✕</span>
          <div class="text-xs tracking-widest" style="color: #7A9A6B;">YUCATÁN</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ── HABLEMOS (CONTACTO) ────────────────────────────────── -->
  <section id="contacto" class="py-16 bg-cream">
    <div class="max-w-2xl mx-auto px-6">
      <h2 class="text-4xl lg:text-5xl font-black text-dark text-center mb-12 reveal">Hablemos</h2>

      <div v-if="contactoOk" class="flex items-center gap-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-4 mb-6">
        <CheckCircle :size="18" class="text-emerald-600 flex-shrink-0" />
        <p class="text-sm text-emerald-700 font-medium">¡Mensaje recibido! Te contactaremos pronto.</p>
      </div>

      <form @submit.prevent="submitContact" class="space-y-5 reveal">
        <div>
          <label class="block text-xs font-bold text-dark mb-1.5">Nombre</label>
          <input v-model="contactForm.nombre" type="text" class="field-dark" />
        </div>
        <div>
          <label class="block text-xs font-bold text-dark mb-1.5">Teléfono</label>
          <input v-model="contactForm.telefono" type="tel" class="field-dark" />
        </div>
        <div>
          <label class="block text-xs font-bold text-dark mb-1.5">E-mail</label>
          <input v-model="contactForm.email" type="email" class="field-dark" />
        </div>
        <div>
          <label class="block text-xs font-bold text-dark mb-1.5">Su Empresa</label>
          <input v-model="contactForm.empresa" type="text" class="field-dark" />
        </div>
        <div>
          <label class="block text-xs font-bold text-dark mb-1.5">Asunto</label>
          <input v-model="contactForm.asunto" type="text" class="field-dark" />
        </div>
        <div>
          <label class="block text-xs font-bold text-dark mb-1.5">Comentarios</label>
          <textarea v-model="contactForm.mensaje" rows="3" class="field-dark resize-none" />
        </div>

        <div class="pt-2">
          <button type="submit" :disabled="contactForm.processing" data-cursor-hover
            class="magnetic bg-nodo-400 border-2 border-dark hover:bg-nodo-500 disabled:opacity-60 text-dark font-bold text-sm px-8 py-2.5 rounded-lg transition hover:scale-105">
            {{ contactForm.processing ? 'Enviando...' : 'Enviar' }}
          </button>
        </div>
      </form>
    </div>
  </section>

  <!-- ── FOOTER ────────────────────────────────────────────── -->
  <footer class="bg-dark py-10">
    <div class="max-w-6xl mx-auto px-6">
      <div class="flex flex-wrap items-center justify-between gap-6 mb-8">
        <span class="text-xl font-black tracking-tight text-white">
          N<span style="text-decoration: overline; text-decoration-thickness: 2px;">ō</span>DI<span class="text-nodo-400">Co</span>
        </span>

        <div class="flex flex-wrap items-center gap-5 text-sm font-medium text-gray-300">
          <a :href="route('home')" class="hover:text-nodo-400 transition">Inicio</a>
          <a :href="route('nosotros')" class="hover:text-nodo-400 transition">Nosotros</a>
          <a :href="route('membresias')" class="hover:text-nodo-400 transition">Membresías</a>
          <a :href="route('eventos')" class="hover:text-nodo-400 transition">Eventos</a>
          <a :href="route('actividades')" class="hover:text-nodo-400 transition">Actividades</a>
        </div>

        <div class="flex items-center gap-4 text-gray-400">
          <a href="https://instagram.com/nodico.mx" target="_blank" class="hover:text-nodo-400 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
            </svg>
          </a>
          <a href="https://facebook.com/nodico" target="_blank" class="hover:text-nodo-400 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/>
            </svg>
          </a>
          <a href="https://linkedin.com/company/nodico" target="_blank" class="hover:text-nodo-400 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.446-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
            </svg>
          </a>
        </div>
      </div>

      <div class="border-t border-white/10 pt-6 text-xs text-gray-400 leading-relaxed">
        <p>
          En caso de requerir la emisión de su factura, le solicitamos enviar un correo electrónico a
          <a href="mailto:contacto@nodico.com.mx" class="text-nodo-400 hover:underline">contacto@nodico.com.mx</a>
          con el asunto &ldquo;Solicitud de factura&rdquo;, incluyendo sus datos fiscales completos para su correcta elaboración.
        </p>
        <p class="mt-2"><strong class="text-white">Nodico</strong> es una marca registrada del Instituto Yucateco de Emprendedores. Todos los derechos reservados.</p>
      </div>
    </div>
  </footer>

  </div>
</template>

<style scoped>
.field-dark {
  @apply w-full bg-dark text-white placeholder-gray-500 rounded-lg px-4 py-2.5 text-sm border-0 focus:outline-none focus:ring-2 focus:ring-nodo-400 transition;
}
</style>
