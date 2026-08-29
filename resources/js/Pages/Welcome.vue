<script setup lang="ts">
import AliadosSection from '@/Components/Public/AliadosSection.vue'
import Boton from '@/Components/Public/Boton.vue'
import ContactSection from '@/Components/Public/ContactSection.vue'
import Meta from '@/Components/Public/Meta.vue'
import HeroVideo from '@/Components/Public/HeroVideo.vue'
import InstagramSection from '@/Components/Public/InstagramSection.vue'
import PlanesCarousel from '@/Components/Public/PlanesCarousel.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
  planes?: any[]
  salon?: any | null
  instagramPosts?: string[]
}>()

const page = usePage()
const nodico = computed(() => (page.props.nodico ?? {}) as any)

const servicios = [
  { icono: '/img/nodico/icono-espacio-colaborativo.webp', titulo: 'Espacio colaborativo de trabajo' },
  { icono: '/img/nodico/icono-wifi.webp', titulo: 'Wifi con 200 MB de velocidad' },
  { icono: '/img/nodico/icono-sala-contenido.webp', titulo: 'Sala profesional de creación de contenido' },
  { icono: '/img/nodico/icono-paqueteria.webp', titulo: 'Servicios de recepción de paquetería' },
  { icono: '/img/nodico/icono-invitados.webp', titulo: 'Hasta 5 invitados gratuitos al mes por membresía' },
  { icono: '/img/nodico/icono-cafe-agua.webp', titulo: 'Café y agua durante todo el día' },
]

const beneficios = [
  'Descuentos exclusivos en Tienda Herencia Viva',
  'Directorio de servicios y productos de miembros Nódico',
  'Acceso preferente a eventos, talleres y capacitaciones',
  'Conexión directa con el ecosistema emprendedor local y nacional',
  'Espacio pet friendly',
]

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
  <Meta
    titulo="Coworking en Mérida para emprendedores"
    descripcion="Nódico es el coworking del Instituto Yucateco de Emprendedores en Mérida: espacio colaborativo, sala de creación de contenido, salones para eventos y una comunidad que impulsa tu proyecto."
    imagen="home"
  />

  <PublicLayout>
    <!-- ═══ HERO — aprobado, no se modifica ═══ -->
    <HeroVideo
      :direccion="nodico.direccionCorta"
      :horarios="nodico.horarios"
      :telefono="nodico.telefono"
      :maps-url="nodico.mapsUrl"
    />

    <!-- Servicios — panel único dividido por finas líneas, tipo ficha técnica -->
    <section class="bg-cream py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
            etiqueta="Servicios"
            titulo="Todo incluido en tu membresía"
            align="center"
            tamano="lg"
          />
        </ScrollReveal>

        <ScrollReveal class="mt-14">
          <ul
            class="grid overflow-hidden rounded-3xl bg-white shadow-sombra ring-1 ring-dark/[.07]
                   sm:grid-cols-2 lg:grid-cols-3"
          >
            <li
              v-for="(servicio, i) in servicios"
              :key="servicio.titulo"
              class="group flex items-center gap-5 border-dark/[.08] p-7 transition-colors duration-300
                     hover:bg-cream-50 sm:p-8
                     [&:not(:last-child)]:border-b sm:[&:nth-child(-n+4)]:border-b sm:[&:nth-last-child(-n+2)]:border-b-0
                     sm:[&:nth-child(odd)]:border-r
                     lg:[&:nth-child(-n+3)]:border-b lg:[&:nth-last-child(-n+3)]:border-b-0
                     lg:[&:nth-child(3n)]:border-r-0 lg:[&:not(:nth-child(3n))]:border-r"
            >
              <img
                :src="servicio.icono"
                alt=""
                aria-hidden="true"
                width="96"
                height="96"
                loading="lazy"
                decoding="async"
                class="h-14 w-14 shrink-0 object-contain transition-transform duration-500 ease-salida group-hover:scale-110"
              />
              <div>
                <p class="etiqueta-tecnica text-dark/30">{{ String(i + 1).padStart(2, '0') }}</p>
                <p class="mt-2 font-display text-base font-bold leading-snug text-dark sm:text-lg">
                  {{ servicio.titulo }}
                </p>
              </div>
            </li>
          </ul>
        </ScrollReveal>
      </div>
    </section>

    <!-- Beneficios — foto a sangre con velo y lista a dos columnas -->
    <section class="relative isolate overflow-hidden bg-tinta">
      <img
        src="/img/nodico/nosotros-hero.webp"
        srcset="/img/nodico/nosotros-hero-640.webp 640w, /img/nodico/nosotros-hero-1280.webp 1280w, /img/nodico/nosotros-hero.webp 1920w"
        sizes="100vw"
        alt=""
        aria-hidden="true"
        width="1920"
        height="1280"
        loading="lazy"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover"
      />
      <div class="absolute inset-0 -z-10 bg-tinta/90" aria-hidden="true" />

      <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:py-28">
        <ScrollReveal>
          <SectionHeading
            etiqueta="Beneficios"
            titulo="Y otras cosas que solo pasan aquí"
            tono="claro"
            tamano="lg"
          />
        </ScrollReveal>

        <ScrollReveal :stagger="70" as="ul" class="mt-14 grid gap-x-14 gap-y-1 lg:grid-cols-2">
          <li
            v-for="(beneficio, i) in beneficios"
            :key="beneficio"
            class="flex items-baseline gap-6 border-b border-white/10 py-6"
          >
            <span class="etiqueta-tecnica shrink-0 text-nodo-400" aria-hidden="true">
              {{ String(i + 1).padStart(2, '0') }}
            </span>
            <p class="font-display text-lg font-bold leading-snug text-white sm:text-xl">
              {{ beneficio }}
            </p>
          </li>
        </ScrollReveal>
      </div>
    </section>

    <!-- ═══ MEMBRESÍAS — aprobado, no se modifica ═══ -->
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

    <!-- Day-pass — foto a la izquierda, dato grande a la derecha -->
    <section class="bg-nodo-400 py-20 lg:py-24">
      <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:gap-20">
        <ScrollReveal from="left">
          <img
            src="/img/nodico/daypass-emprendedor.webp"
        srcset="/img/nodico/daypass-emprendedor-640.webp 640w, /img/nodico/daypass-emprendedor-1280.webp 1280w, /img/nodico/daypass-emprendedor.webp 1677w"
        sizes="(min-width: 1024px) 50vw, 100vw"
            alt="Emprendedores del interior del estado trabajando en Nódico"
            width="1677"
            height="1920"
            loading="lazy"
            decoding="async"
            class="aspect-[4/3] w-full rounded-3xl object-cover shadow-sombra-lg"
          />
        </ScrollReveal>

        <ScrollReveal from="right">
          <p class="etiqueta-tecnica mb-5 flex items-center gap-3 text-dark/55">
            <span class="h-1.5 w-1.5 rounded-full bg-dark" aria-hidden="true" />
            Day-pass emprendedor
          </p>

          <h2 class="font-display text-display-md font-extrabold text-dark">
            ¿Eres emprendedor o artesano del interior del estado?
          </h2>

          <p class="mt-8 rounded-2xl bg-dark px-7 py-6 font-display text-2xl font-extrabold text-nodo-400 sm:text-3xl">
            Tu day-pass siempre es gratuito.
          </p>

          <p class="mt-7 max-w-lg font-body text-cuerpo-lg text-dark/70">
            Si tu negocio está fuera de Mérida y necesitas un lugar para tener una junta,
            trabajar un rato o presentar tu proyecto, el espacio es tuyo sin costo.
          </p>

          <Boton href="#hablemos" variante="secundario" tamano="lg" class="mt-8" flecha>
            Contáctanos para más informes
          </Boton>
        </ScrollReveal>
      </div>
    </section>

    <!-- ═══ SALONES — aprobado, no se modifica ═══ -->
    <section class="relative isolate overflow-hidden bg-tinta">
      <img
        src="/img/nodico/salon-yucatan-emprende-2.webp"
        srcset="/img/nodico/salon-yucatan-emprende-2-640.webp 640w, /img/nodico/salon-yucatan-emprende-2-1280.webp 1280w, /img/nodico/salon-yucatan-emprende-2.webp 1920w"
        sizes="100vw"
        alt="Salón de eventos de Nódico montado para una conferencia"
        width="1920"
        height="1440"
        loading="lazy"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover"
      />
      <div class="absolute inset-0 -z-10 bg-gradient-to-r from-tinta via-tinta/[.92] to-tinta/60" aria-hidden="true" />

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

    <!-- Aliados -->
    <section class="bg-cream-50 py-16 lg:py-20">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <p class="etiqueta-tecnica mb-10 text-center text-dark/40">
            Con el respaldo de
          </p>
          <AliadosSection />
        </ScrollReveal>
      </div>
    </section>

    <!-- Instagram -->
    <InstagramSection :handle="nodico.instagram" :publicaciones="instagramPosts" />

    <ContactSection />
  </PublicLayout>
</template>
