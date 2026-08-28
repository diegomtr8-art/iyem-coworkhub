<script setup lang="ts">
import AliadosSection from '@/Components/Public/AliadosSection.vue'
import Boton from '@/Components/Public/Boton.vue'
import ContactSection from '@/Components/Public/ContactSection.vue'
import HeroVideo from '@/Components/Public/HeroVideo.vue'
import IconCard from '@/Components/Public/IconCard.vue'
import Marquee from '@/Components/Public/Marquee.vue'
import PlanesCarousel from '@/Components/Public/PlanesCarousel.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
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

const palabrasMarca = ['Emprendimiento', 'Creatividad', 'Comunidad', 'Innovación', 'Coworking', 'Pet friendly', 'Networking']

/** Si la BD viniera vacía, la portada sigue mostrando los cuatro planes reales. */
const planesFallback = [
  { nombre: 'Day-Pass', precio: 79, periodo_label: 'por 1 día', color: '#EF7E88', destacado: false, personas: 1,
    descripcion_corta: 'Espacio pensado para estudiantes, freelancers ocasionales o quienes necesitan trabajar por un día.', beneficios: [] },
  { nombre: 'Nódico Flex', precio: 249, periodo_label: 'por 4 días', color: '#FFDD00', destacado: false, personas: 1,
    descripcion_corta: 'Opción accesible para jóvenes emprendedores o estudiantes que necesitan el espacio por horas.', beneficios: [] },
  { nombre: 'Nodo Pro', precio: 599, periodo_label: 'al mes', color: '#D6E265', destacado: true, personas: 1,
    descripcion_corta: 'Perfecta para emprendedores y creadores que buscan un espacio de trabajo constante.', beneficios: [] },
  { nombre: 'Nodo Match', precio: 799, periodo_label: 'por 1 mes', color: '#864B95', destacado: false, personas: 2,
    descripcion_corta: 'Ideal para emprendedores, freelancers y creadores de contenido que requieren un espacio estable para trabajar.', beneficios: [] },
]

const planesVisibles = computed(() => (props.planes?.length ? props.planes : planesFallback))

/** Ficha del teaser con los valores reales de /eventos (discrepancia #2 de la auditoría). */
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
    <HeroVideo />

    <!-- 01 · Servicios -->
    <section class="bg-cream py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
            numero="01"
            etiqueta="Servicios"
            titulo="Todo lo que necesitas para trabajar en serio"
            tamano="lg"
          />
        </ScrollReveal>

        <ScrollReveal :stagger="70" class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <IconCard
            v-for="(servicio, i) in servicios"
            :key="servicio.titulo"
            :icono="servicio.icono"
            :titulo="servicio.titulo"
            :numero="`0${i + 1}`"
          />
        </ScrollReveal>
      </div>
    </section>

    <Marquee :palabras="palabrasMarca" />

    <!-- 02 · Beneficios -->
    <section class="bg-dark py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
            numero="02"
            etiqueta="Beneficios"
            titulo="Y otras cosas que solo pasan aquí"
            tono="claro"
            tamano="lg"
          />
        </ScrollReveal>

        <ScrollReveal :stagger="70" class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <IconCard
            v-for="(beneficio, i) in beneficios"
            :key="beneficio.titulo"
            :icono="beneficio.icono"
            :titulo="beneficio.titulo"
            :numero="`0${i + 1}`"
            tono="oscuro"
          />
        </ScrollReveal>
      </div>
    </section>

    <!-- 03 · Planes -->
    <section class="overflow-hidden bg-cream-50 py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
            numero="03"
            etiqueta="Membresías"
            titulo="Elige tu plan ideal"
            tamano="lg"
            descripcion="Creemos que el éxito comienza con el entorno correcto. Nuestras membresías te dan la flexibilidad, los recursos y la comunidad que necesitas para hacer crecer tu proyecto."
          />
        </ScrollReveal>

        <PlanesCarousel :planes="planesVisibles" />

        <div class="mt-6 flex justify-center">
          <Boton :href="route('membresias')" variante="secundario" flecha>
            Comparar todas las membresías
          </Boton>
        </div>
      </div>
    </section>

    <!-- 04 · Day-pass del interior del estado -->
    <section class="border-y-2 border-dark bg-nodo-400 py-20 lg:py-24">
      <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:gap-16">
        <ScrollReveal from="left">
          <p class="etiqueta-tecnica mb-6 text-dark/60">04 — Day-pass emprendedor</p>

          <h2 class="font-display text-display-md font-extrabold text-dark">
            ¿Eres emprendedor o artesano del interior del estado?
          </h2>

          <p class="mt-6 font-display text-3xl font-extrabold text-dark sm:text-4xl">
            Tu day-pass siempre es gratuito.
          </p>

          <p class="mt-6 max-w-lg font-body text-cuerpo-lg text-dark/75">
            Si tu negocio está fuera de Mérida y necesitas un lugar para tener una junta,
            trabajar un rato o presentar tu proyecto, el espacio es tuyo sin costo.
          </p>

          <Boton href="#hablemos" variante="oscuro" tamano="lg" class="mt-9" flecha>
            Contáctanos para más informes
          </Boton>
        </ScrollReveal>

        <ScrollReveal from="right">
          <!-- La imagen se sale de su caja: profundidad por superposición. -->
          <div class="relative">
            <img
              src="/img/nodico/daypass-emprendedor.webp"
              alt="Emprendedores del interior del estado trabajando en Nódico"
              width="1000"
              height="750"
              loading="lazy"
              decoding="async"
              class="aspect-[4/3] w-full border-2 border-dark object-cover shadow-dura-lg"
            />
            <p
              class="absolute -bottom-5 -left-3 border-2 border-dark bg-white px-5 py-3 font-display text-sm font-bold text-dark shadow-dura-sm sm:-left-6"
            >
              Sin costo · Todo el año
            </p>
          </div>
        </ScrollReveal>
      </div>
    </section>

    <!-- 05 · Aliados -->
    <section class="bg-cream py-20 lg:py-24">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
            numero="05"
            etiqueta="Ecosistema"
            titulo="¿Quieres conocer más de Nódico?"
            align="center"
          />
        </ScrollReveal>

        <ScrollReveal class="mt-14">
          <AliadosSection />
        </ScrollReveal>
      </div>
    </section>

    <!-- 06 · Salones -->
    <section class="bg-dark py-20 lg:py-28">
      <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:gap-16">
        <ScrollReveal from="left">
          <SectionHeading
            numero="06"
            etiqueta="Salones"
            titulo="Conoce nuestros salones para eventos"
            tono="claro"
            descripcion="Nuestros espacios están listos para tus talleres, conferencias o reuniones. Modernos, cómodos y equipados para que cada idea cobre vida."
          />

          <dl class="mt-9 divide-y divide-white/10 border-y border-white/15">
            <div v-for="[etiqueta, valor] in fichaSalon" :key="etiqueta" class="flex justify-between gap-6 py-3">
              <dt class="font-mono text-xs uppercase tracking-wider text-white/45">{{ etiqueta }}</dt>
              <dd class="font-body text-sm font-semibold text-white">{{ valor }}</dd>
            </div>
          </dl>

          <Boton :href="route('eventos')" variante="claro" class="mt-9" flecha>
            Ver los salones
          </Boton>
        </ScrollReveal>

        <ScrollReveal from="right">
          <img
            src="/img/nodico/teaser-salones.png"
            alt="Salón de eventos de Nódico montado para una conferencia"
            width="1000"
            height="750"
            loading="lazy"
            decoding="async"
            class="aspect-[4/3] w-full border-2 border-nodo-400 object-cover shadow-dura-nodo"
          />
        </ScrollReveal>
      </div>
    </section>

    <ContactSection />
  </PublicLayout>
</template>
