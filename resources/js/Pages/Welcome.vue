<script setup lang="ts">
import AliadosSection from '@/Components/Public/AliadosSection.vue'
import Boton from '@/Components/Public/Boton.vue'
import ContactSection from '@/Components/Public/ContactSection.vue'
import HeroVideo from '@/Components/Public/HeroVideo.vue'
import Marquee from '@/Components/Public/Marquee.vue'
import PlanesCarousel from '@/Components/Public/PlanesCarousel.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
  planes?: any[]
  salon?: any | null
}>()

const page = usePage()
const nodico = computed(() => (page.props.nodico ?? {}) as any)

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
    ['Capacidad', `${s?.capacidad ?? 120} personas`],
    ['Costo por hora', `$${Number(s?.precio_hora ?? 600).toLocaleString('es-MX')} mxn`],
    ['Auditorio', `${s?.cap_auditorio ?? 120} personas`],
    ['Escuela', `${s?.cap_escuela ?? 54} personas`],
    ['Herradura', `${s?.cap_herradura ?? 45} personas`],
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
    <HeroVideo
      :video-id="'Ml4sprGUqzc'"
      :direccion="nodico.direccionCorta"
      :horarios="nodico.horarios"
      :telefono="nodico.telefono"
      :maps-url="nodico.mapsUrl"
    />

    <!-- 01 · Servicios — split editorial con foto fija y lista numerada -->
    <section class="bg-cream py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <div class="grid gap-14 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-20">
          <ScrollReveal from="left">
            <div class="lg:sticky lg:top-28">
              <SectionHeading
                etiqueta="Servicios"
                titulo="Todo lo que necesitas para trabajar"
                tamano="lg"
                descripcion="Un espacio pensado para que lo único que tengas que traer sea tu proyecto."
              />

              <img
                src="/img/nodico/plan-nodo-pro.webp"
                alt="Miembros trabajando en el área de coworking de Nódico"
                width="1000"
                height="750"
                loading="lazy"
                decoding="async"
                class="mt-10 aspect-[4/3] w-full rounded-3xl object-cover shadow-sombra"
              />
            </div>
          </ScrollReveal>

          <ScrollReveal from="right" :stagger="60" as="ol" class="divide-y divide-dark/10 border-t border-dark/10">
            <li
              v-for="(servicio, i) in servicios"
              :key="servicio.titulo"
              class="group flex items-center gap-6 py-7"
            >
              <span class="etiqueta-tecnica w-8 shrink-0 text-dark/35" aria-hidden="true">
                {{ String(i + 1).padStart(2, '0') }}
              </span>
              <img
                :src="servicio.icono"
                alt=""
                aria-hidden="true"
                width="96"
                height="96"
                loading="lazy"
                decoding="async"
                class="h-12 w-12 shrink-0 object-contain transition-transform duration-500 ease-salida group-hover:scale-110"
              />
              <p class="font-display text-lg font-bold leading-snug text-dark sm:text-xl">
                {{ servicio.titulo }}
              </p>
            </li>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <Marquee :palabras="palabrasMarca" />

    <!-- 02 · Beneficios — banda oscura con mosaico de fotos -->
    <section class="bg-tinta py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <div class="grid gap-14 lg:grid-cols-2 lg:gap-20">
          <ScrollReveal from="left">
            <SectionHeading
              etiqueta="Beneficios"
              titulo="Y otras cosas que solo pasan aquí"
              tono="claro"
              tamano="lg"
            />

            <ul class="mt-10 space-y-1">
              <li
                v-for="beneficio in beneficios"
                :key="beneficio.titulo"
                class="flex items-center gap-5 rounded-2xl px-4 py-4 transition-colors duration-300 hover:bg-white/[.05]"
              >
                <img
                  :src="beneficio.icono"
                  alt=""
                  aria-hidden="true"
                  width="96"
                  height="96"
                  loading="lazy"
                  decoding="async"
                  class="h-11 w-11 shrink-0 object-contain brightness-0 invert"
                />
                <p class="font-body text-base leading-snug text-white/85">{{ beneficio.titulo }}</p>
              </li>
            </ul>
          </ScrollReveal>

          <ScrollReveal from="right">
            <div class="grid grid-cols-2 gap-4">
              <img
                src="/img/nodico/plan-nodico-flex.webp"
                alt="Zona de trabajo compartida de Nódico"
                width="1000" height="1200" loading="lazy" decoding="async"
                class="aspect-[3/4] w-full rounded-3xl object-cover"
              />
              <img
                src="/img/nodico/plan-day-pass.webp"
                alt="Sala de creación de contenido"
                width="1000" height="1200" loading="lazy" decoding="async"
                class="mt-10 aspect-[3/4] w-full rounded-3xl object-cover"
              />
              <img
                src="/img/nodico/salon-detalle.webp"
                alt="Detalle del mobiliario de Nódico"
                width="1000" height="750" loading="lazy" decoding="async"
                class="aspect-[4/3] w-full rounded-3xl object-cover"
              />
              <img
                src="/img/nodico/plan-nodo-match.webp"
                alt="Miembros de la comunidad Nódico"
                width="1000" height="750" loading="lazy" decoding="async"
                class="mt-10 aspect-[4/3] w-full rounded-3xl object-cover"
              />
            </div>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- 03 · Planes -->
    <section class="overflow-hidden bg-cream-50 py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
            etiqueta="Membresías"
            titulo="Elige tu plan ideal"
            align="center"
            tamano="lg"
            descripcion="El éxito comienza con el entorno correcto. Cada membresía te da la flexibilidad, los recursos y la comunidad que necesitas para hacer crecer tu proyecto."
          />
        </ScrollReveal>

        <PlanesCarousel :planes="planesVisibles" />

        <div class="mt-4 flex justify-center">
          <Boton :href="route('membresias')" variante="oscuro" flecha>
            Comparar todas las membresías
          </Boton>
        </div>
      </div>
    </section>

    <!-- 04 · Day-pass del interior del estado -->
    <section class="relative overflow-hidden bg-nodo-400 py-20 lg:py-24">
      <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-2 lg:gap-20">
        <ScrollReveal from="left">
          <p class="etiqueta-tecnica mb-5 flex items-center gap-3 text-dark/55">
            <span class="h-1.5 w-1.5 rounded-full bg-dark" aria-hidden="true" />
            Day-pass emprendedor
          </p>

          <h2 class="font-display text-display-md font-extrabold text-dark">
            ¿Eres emprendedor o artesano del interior del estado?
          </h2>

          <p class="mt-6 font-display text-3xl font-extrabold text-dark sm:text-4xl">
            Tu day-pass siempre es gratuito.
          </p>

          <p class="mt-6 max-w-lg font-body text-cuerpo-lg text-dark/70">
            Si tu negocio está fuera de Mérida y necesitas un lugar para tener una junta,
            trabajar un rato o presentar tu proyecto, el espacio es tuyo sin costo.
          </p>

          <Boton href="#hablemos" variante="secundario" tamano="lg" class="mt-9" flecha>
            Contáctanos para más informes
          </Boton>
        </ScrollReveal>

        <ScrollReveal from="right">
          <img
            src="/img/nodico/daypass-emprendedor.webp"
            alt="Emprendedores del interior del estado trabajando en Nódico"
            width="1000"
            height="750"
            loading="lazy"
            decoding="async"
            class="aspect-[4/3] w-full rounded-3xl object-cover shadow-sombra-lg"
          />
        </ScrollReveal>
      </div>
    </section>

    <!-- 05 · Salones — foto a sangre con ficha superpuesta -->
    <section class="relative isolate overflow-hidden bg-tinta">
      <img
        src="/img/nodico/teaser-salones.png"
        alt="Salón de eventos de Nódico montado para una conferencia"
        width="1600"
        height="900"
        loading="lazy"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover"
      />
      <div class="absolute inset-0 -z-10 bg-gradient-to-r from-tinta via-tinta/90 to-tinta/50" aria-hidden="true" />

      <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:py-28">
        <ScrollReveal class="max-w-2xl">
          <SectionHeading
            etiqueta="Salones"
            titulo="Espacios listos para tu evento"
            tono="claro"
            tamano="lg"
            descripcion="Talleres, conferencias o reuniones. Modernos, cómodos y equipados para que cada idea cobre vida."
          />

          <dl class="mt-10 grid grid-cols-2 gap-x-8 gap-y-5 sm:grid-cols-3">
            <div v-for="[etiqueta, valor] in fichaSalon" :key="etiqueta">
              <dt class="etiqueta-tecnica text-white/40">{{ etiqueta }}</dt>
              <dd class="mt-2 font-display text-lg font-bold text-white">{{ valor }}</dd>
            </div>
          </dl>

          <Boton :href="route('eventos')" variante="primario" tamano="lg" class="mt-10" flecha>
            Ver los salones
          </Boton>
        </ScrollReveal>
      </div>
    </section>

    <!-- 06 · Aliados -->
    <section class="bg-cream py-20 lg:py-24">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
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

    <ContactSection />
  </PublicLayout>
</template>
