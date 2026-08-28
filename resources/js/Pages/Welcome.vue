<script setup lang="ts">
import IconCard from '@/Components/Public/IconCard.vue'
import ContactSection from '@/Components/Public/ContactSection.vue'
import PlanCard from '@/Components/Public/PlanCard.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
  planes?: any[]
  salon?: any | null
}>()

const servicios = [
  { icono: '/img/nodico/icono-espacio-colaborativo.svg', titulo: 'Espacio colaborativo de trabajo' },
  { icono: '/img/nodico/icono-wifi.svg', titulo: 'Wifi con 200 MB de velocidad' },
  { icono: '/img/nodico/icono-sala-contenido.svg', titulo: 'Sala profesional de creación de contenido' },
  { icono: '/img/nodico/icono-paqueteria.svg', titulo: 'Servicios de recepción de paquetería' },
  { icono: '/img/nodico/icono-invitados.svg', titulo: 'Hasta 5 invitados gratuitos al mes por membresía' },
  { icono: '/img/nodico/icono-cafe-agua.svg', titulo: 'Café y agua durante todo el día' },
]

const beneficios = [
  { icono: '/img/nodico/icono-descuentos.svg', titulo: 'Descuentos exclusivos en Tienda Herencia Viva' },
  { icono: '/img/nodico/icono-directorio.svg', titulo: 'Directorio de servicios y productos de miembros Nódico' },
  { icono: '/img/nodico/icono-eventos-talleres.svg', titulo: 'Acceso preferente a eventos, talleres y capacitaciones' },
  { icono: '/img/nodico/icono-ecosistema.svg', titulo: 'Conexión directa con el ecosistema emprendedor local y nacional' },
  { icono: '/img/nodico/icono-pet-friendly.svg', titulo: 'Espacio pet friendly' },
]

const aliados = [
  { nombre: 'Instituto Yucateco de Emprendedores', logo: '/img/nodico/logo-iyem.webp', href: 'https://iyem.yucatan.gob.mx/' },
  { nombre: 'Herencia Viva', logo: '/img/nodico/logo-herencia-viva.webp', href: 'http://www.herenciaviva.com' },
  { nombre: 'CANIETI', logo: '/img/nodico/logo-canieti.webp', href: 'https://canieti.org' },
]

/** Si la BD viene vacía, el sitio sigue mostrando los cuatro planes reales. */
const planesFallback = [
  { nombre: 'Nódico Flex', color: '#FFDD00', imagen: '/img/nodico/plan-nodico-flex.webp', personas: 1,
    descripcion_corta: 'Opción accesible para jóvenes emprendedores o estudiantes que necesitan el espacio por horas.' },
  { nombre: 'Nodo Pro', color: '#D6E265', imagen: '/img/nodico/plan-nodo-pro.webp', personas: 1,
    descripcion_corta: 'Perfecta para emprendedores y creadores que buscan un espacio de trabajo constante.' },
  { nombre: 'Day-Pass', color: '#EF7E88', imagen: '/img/nodico/plan-day-pass.webp', personas: 1,
    descripcion_corta: 'Espacio pensado para estudiantes, freelancers ocasionales o quienes necesitan trabajar por un día.' },
  { nombre: 'Nodo Match', color: '#864B95', imagen: '/img/nodico/plan-nodo-match.webp', personas: 2,
    descripcion_corta: 'Ideal para emprendedores, freelancers y creadores de contenido que requieren un espacio estable para trabajar.' },
]

const planesVisibles = computed(() => (props.planes?.length ? props.planes : planesFallback))

/** Ficha técnica del teaser: valores reales de /eventos (ver discrepancia #2 de la auditoría). */
const fichaSalon = computed(() => {
  const s = props.salon
  return [
    ['Medidas', s?.medidas ?? '15x14 metros'],
    ['Costo por hora', `$${Number(s?.precio_hora ?? 600).toLocaleString('es-MX')} mxn`],
    ['Capacidad', `${s?.capacidad ?? 120} personas`],
    ['Herradura', `${s?.cap_herradura ?? 45} personas`],
    ['Mesas de trabajo', `${s?.cap_mesas ?? 70} personas`],
    ['Escuela', `${s?.cap_escuela ?? 54} personas`],
    ['Auditorio', `${s?.cap_auditorio ?? 120} personas`],
  ]
})
</script>

<template>
  <Head>
    <title>Nódico — Coworking en Mérida para emprendedores</title>
    <meta
      name="description"
      content="Nódico es el coworking del Instituto Yucateco de Emprendedores en Mérida: espacio colaborativo, sala de creación de contenido, salones para eventos y una comunidad que impulsa tu proyecto."
    />
  </Head>

  <PublicLayout>
    <!-- Hero -->
    <section class="relative isolate overflow-hidden bg-nodo-400 pt-28 lg:pt-32">
      <img
        src="/img/nodico/fondo-amarillo.webp"
        alt=""
        aria-hidden="true"
        class="absolute inset-0 -z-10 h-full w-full object-cover opacity-60"
      />

      <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 py-16 sm:px-8 lg:grid-cols-2 lg:gap-16 lg:py-24">
        <div>
          <h1 class="font-display text-4xl font-extrabold leading-[1.05] tracking-tight text-dark sm:text-5xl lg:text-6xl">
            Bienvenidos al lugar
            <span class="mt-3 block">Donde el trabajo es un pretexto para crear</span>
          </h1>

          <Link
            :href="route('nosotros')"
            class="mt-10 inline-flex items-center gap-2 rounded-lg border-2 border-dark bg-dark px-7 py-3
                   font-display text-sm font-bold text-nodo-400 transition hover:bg-dark-light
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
          >
            Conocer más <span aria-hidden="true">→</span>
          </Link>
        </div>

        <div>
          <img
            src="/img/nodico/hero-inicio.webp"
            alt="Personas trabajando en el área de coworking de Nódico"
            width="1000"
            height="750"
            fetchpriority="high"
            decoding="async"
            class="aspect-[4/3] w-full rounded-4xl object-cover shadow-2xl"
          />
        </div>
      </div>
    </section>

    <!-- Servicios -->
    <section class="bg-cream py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading titulo="Servicios" align="center" />
        </ScrollReveal>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <ScrollReveal v-for="(servicio, i) in servicios" :key="servicio.titulo" :delay="i * 70">
            <IconCard :icono="servicio.icono" :titulo="servicio.titulo" />
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Beneficios adicionales -->
    <section class="bg-white py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading titulo="Beneficios adicionales" align="center" />
        </ScrollReveal>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <ScrollReveal v-for="(beneficio, i) in beneficios" :key="beneficio.titulo" :delay="i * 70">
            <IconCard :icono="beneficio.icono" :titulo="beneficio.titulo" />
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Planes -->
    <section class="bg-cream py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
            titulo="Elige tu plan ideal"
            align="center"
            descripcion="En nuestro coworking creemos que el éxito comienza con el entorno correcto. Nuestras membresías están diseñadas para brindarte la flexibilidad, los recursos y la comunidad que necesitas para hacer crecer tu proyecto. Ya sea que busques un espacio fijo, horas flexibles o el respaldo de una red de mentes creativas, aquí encontrarás la opción perfecta para ti."
          >
            <Link
              :href="route('membresias')"
              class="mt-8 inline-flex items-center gap-2 rounded-lg border-2 border-dark bg-nodo-400 px-7 py-3
                     font-display text-sm font-bold text-dark transition hover:brightness-95
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
            >
              ¡Regístrate aquí! <span aria-hidden="true">→</span>
            </Link>
          </SectionHeading>
        </ScrollReveal>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <ScrollReveal v-for="(plan, i) in planesVisibles" :key="plan.nombre" :delay="i * 80" class="h-full">
            <PlanCard :plan="plan" />
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Day-pass para el interior del estado -->
    <section class="bg-dark py-20 lg:py-24">
      <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-2">
        <ScrollReveal from="left">
          <h2 class="font-display text-3xl font-extrabold leading-tight tracking-tight text-white sm:text-4xl">
            ¿Eres emprendedor o artesano del Interior del Estado?
          </h2>
          <p class="mt-5 font-display text-2xl font-bold text-nodo-400">
            ¡Tu daypass siempre es gratuito!
          </p>
          <p class="mt-5 font-body text-base leading-relaxed text-white/75">
            ¿Tu negocio se encuentra en el Interior del Estado y necesitas tener una junta?
            Conoce nuestro daypass emprendedor.
          </p>
          <a
            href="#hablemos"
            class="mt-8 inline-flex items-center gap-2 rounded-lg border-2 border-nodo-400 px-7 py-3
                   font-display text-sm font-bold text-nodo-400 transition hover:bg-nodo-400 hover:text-dark
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-400"
          >
            Contáctanos para más informes
          </a>
        </ScrollReveal>

        <ScrollReveal from="right">
          <img
            src="/img/nodico/daypass-emprendedor.webp"
            alt="Emprendedores del interior del estado trabajando en Nódico"
            width="1000"
            height="750"
            loading="lazy"
            decoding="async"
            class="aspect-[4/3] w-full rounded-4xl object-cover"
          />
        </ScrollReveal>
      </div>
    </section>

    <!-- Aliados -->
    <section class="bg-white py-20 lg:py-24">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading titulo="¿Quieres conocer más de Nódico?" align="center" />
        </ScrollReveal>

        <ul class="mt-14 flex flex-wrap items-center justify-center gap-10 sm:gap-16">
          <li v-for="aliado in aliados" :key="aliado.nombre">
            <a
              :href="aliado.href"
              target="_blank"
              rel="noopener noreferrer"
              class="block rounded transition hover:opacity-70
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-dark"
            >
              <img
                :src="aliado.logo"
                :alt="aliado.nombre"
                width="220"
                height="110"
                loading="lazy"
                decoding="async"
                class="h-16 w-auto object-contain sm:h-20"
              />
            </a>
          </li>
        </ul>
      </div>
    </section>

    <!-- Teaser de salones -->
    <section class="bg-cream py-20 lg:py-28">
      <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:gap-16">
        <ScrollReveal from="left">
          <SectionHeading
            titulo="Conoce nuestros Salones para eventos"
            descripcion="Nuestros espacios están listos para tus talleres, conferencias o reuniones. Modernos, cómodos y equipados para que cada idea cobre vida."
          />

          <dl class="mt-8 divide-y divide-dark/10 border-y border-dark/10">
            <div v-for="[etiqueta, valor] in fichaSalon" :key="etiqueta" class="flex justify-between gap-6 py-3">
              <dt class="font-body text-sm text-dark/60">{{ etiqueta }}</dt>
              <dd class="font-body text-sm font-semibold text-dark">{{ valor }}</dd>
            </div>
          </dl>

          <Link
            :href="route('eventos')"
            class="mt-8 inline-flex items-center gap-2 rounded-lg border-2 border-dark bg-nodo-400 px-7 py-3
                   font-display text-sm font-bold text-dark transition hover:brightness-95
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
          >
            Ver los salones <span aria-hidden="true">→</span>
          </Link>
        </ScrollReveal>

        <ScrollReveal from="right">
          <img
            src="/img/nodico/teaser-salones.png"
            alt="Salón de eventos de Nódico montado para una conferencia"
            width="1000"
            height="750"
            loading="lazy"
            decoding="async"
            class="aspect-[4/3] w-full rounded-4xl object-cover"
          />
        </ScrollReveal>
      </div>
    </section>

    <ContactSection />
  </PublicLayout>
</template>
