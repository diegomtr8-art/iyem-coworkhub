<script setup lang="ts">
import AliadosSection from '@/Components/Public/AliadosSection.vue'
import Boton from '@/Components/Public/Boton.vue'
import ContactSection from '@/Components/Public/ContactSection.vue'
import Meta from '@/Components/Public/Meta.vue'
import HeroVideo from '@/Components/Public/HeroVideo.vue'
import InstagramSection from '@/Components/Public/InstagramSection.vue'
import PlanesCarousel from '@/Components/Public/PlanesCarousel.vue'
import BeneficiosPaneles from '@/Components/Public/BeneficiosPaneles.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SelloGiratorio from '@/Components/Public/SelloGiratorio.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { useParallax } from '@/composables/useParallax'
import type { Plan, Salon } from '@/tipos'
import { usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps<{
  planes?: Plan[]
  salon?: Salon | null
  instagramPosts?: string[]
}>()

const page = usePage()
const nodico = computed(() => (page.props.nodico ?? {}) as any)

/**
 * 4.3 — mosaico asimétrico. Los dos de más peso comercial ocupan celda grande
 * con foto; los otros cuatro van compactos.
 * Las descripciones las redacté yo: PENDIENTE de que Nódico las valide.
 */
const servicios = [
  {
    icono: '/img/nodico/icono-espacio-colaborativo.webp',
    titulo: 'Espacio colaborativo de trabajo',
    descripcion: 'Escritorios en área abierta, con lugar para ti y para quien venga contigo.',
    protagonista: true,
    foto: '/img/nodico/mision.webp',
  },
  {
    icono: '/img/nodico/icono-sala-contenido.webp',
    titulo: 'Sala profesional de creación de contenido',
    descripcion: 'Estudio equipado para grabar tu podcast, tus reels o tus fotos de producto.',
    protagonista: true,
    foto: '/img/nodico/vision.webp',
  },
  {
    icono: '/img/nodico/icono-wifi.webp',
    titulo: 'Wifi con 200 MB de velocidad',
    descripcion: 'Suficiente para videollamadas, subir contenido y trabajar sin pausas.',
  },
  {
    icono: '/img/nodico/icono-paqueteria.webp',
    titulo: 'Recepción de paquetería',
    descripcion: 'Recibimos tus envíos aunque no estés; te avisamos en cuanto llegan.',
  },
  {
    icono: '/img/nodico/icono-invitados.webp',
    titulo: 'Hasta 5 invitados gratuitos al mes',
    descripcion: 'Trae a tu equipo o a un cliente sin costo adicional.',
  },
  {
    icono: '/img/nodico/icono-cafe-agua.webp',
    titulo: 'Café y agua todo el día',
    descripcion: 'Barra libre mientras trabajas. Sin fichas ni límites.',
  },
]

const protagonistas = computed(() => servicios.filter((x) => x.protagonista))
const compactos = computed(() => servicios.filter((x) => !x.protagonista))

/**
 * 4.4 — paneles expansibles. Descripciones redactadas por mí, PENDIENTES de
 * validación. Las fotos son del espacio, no de cada beneficio concreto: varios
 * son conceptos abstractos y no hay material específico.
 */
const beneficios = [
  {
    titulo: 'Descuentos en Tienda Herencia Viva',
    tituloCorto: 'Descuentos',
    descripcion: 'Precio preferente en artesanía yucateca, para ti y para los regalos de tu negocio.',
    foto: '/img/nodico/salon-detalle.webp',
    acento: '#FFDD00',
  },
  {
    titulo: 'Directorio de miembros Nódico',
    tituloCorto: 'Directorio',
    descripcion: 'Tu proyecto visible ante toda la comunidad, y la comunidad disponible para ti.',
    foto: '/img/nodico/mision.webp',
    acento: '#D6E265',
  },
  {
    titulo: 'Acceso preferente a eventos y talleres',
    tituloCorto: 'Eventos y talleres',
    descripcion: 'Te avisamos antes y apartas lugar antes de que se abra al público.',
    foto: '/img/nodico/comunidad-fondo.webp',
    acento: '#EF7E88',
  },
  {
    titulo: 'Conexión con el ecosistema emprendedor',
    tituloCorto: 'Ecosistema',
    descripcion: 'Programas del IYEM, CANIETI y la red de incubación, a un paso de tu escritorio.',
    foto: '/img/nodico/salon-yucatan-emprende-1.webp',
    acento: '#864B95',
  },
  {
    titulo: 'Espacio pet friendly',
    tituloCorto: 'Pet friendly',
    descripcion: 'Tu perro también tiene lugar aquí. Sin permisos ni explicaciones.',
    foto: '/img/nodico/nosotros-hero.webp',
    acento: '#FFE124',
  },
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

// 4.6 — parallax suave de la foto del day-pass.
const fotoDaypass = ref<HTMLElement | null>(null)
const { desplazamiento } = useParallax(fotoDaypass, 56)

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

    <!-- 4.3 · Servicios — mosaico asimétrico -->
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

        <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          <!-- Protagonistas: celda grande con foto -->
          <ScrollReveal
            v-for="(servicio, i) in protagonistas"
            :key="servicio.titulo"
            :delay="i * 80"
            class="sm:col-span-2"
          >
            <article class="group relative isolate flex h-full min-h-[300px] flex-col justify-end overflow-hidden rounded-3xl p-8">
              <img
                :src="servicio.foto"
                alt=""
                aria-hidden="true"
                width="1920"
                height="1079"
                loading="lazy"
                decoding="async"
                class="absolute inset-0 -z-20 h-full w-full object-cover transition-transform duration-700 ease-salida group-hover:scale-105"
              />
              <div class="absolute inset-0 -z-10 bg-tinta/[.72]" aria-hidden="true" />

              <img
                :src="servicio.icono"
                alt=""
                aria-hidden="true"
                width="160"
                height="160"
                loading="lazy"
                decoding="async"
                class="mb-6 h-14 w-14 object-contain brightness-0 invert"
              />
              <h3 class="font-display text-2xl font-extrabold leading-tight text-white">
                {{ servicio.titulo }}
              </h3>
              <p class="mt-3 max-w-md font-body text-cuerpo leading-relaxed text-white/80">
                {{ servicio.descripcion }}
              </p>
            </article>
          </ScrollReveal>

          <!-- Compactos: icono sobre fondo claro -->
          <ScrollReveal
            v-for="(servicio, i) in compactos"
            :key="`compacto-${servicio.titulo}`"
            :delay="160 + i * 70"
          >
            <article
              class="group flex h-full flex-col rounded-3xl bg-white p-7 shadow-sombra-sm ring-1 ring-dark/[.07]
                     transition-all duration-300 ease-salida hover:-translate-y-1 hover:shadow-sombra"
            >
              <img
                :src="servicio.icono"
                alt=""
                aria-hidden="true"
                width="160"
                height="160"
                loading="lazy"
                decoding="async"
                class="mb-5 h-12 w-12 object-contain transition-transform duration-500 ease-salida group-hover:scale-110"
              />
              <h3 class="font-display text-lg font-bold leading-snug text-dark">
                {{ servicio.titulo }}
              </h3>
              <p class="mt-2.5 font-body text-sm leading-relaxed text-dark/70">
                {{ servicio.descripcion }}
              </p>
            </article>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- 4.4 · Beneficios — paneles expansibles -->
    <section class="bg-tinta py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
            etiqueta="Beneficios"
            titulo="Y otras cosas que solo pasan aquí"
            tono="claro"
            tamano="lg"
          />
        </ScrollReveal>

        <ScrollReveal class="mt-14">
          <BeneficiosPaneles :beneficios="beneficios" />
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

    <!-- 4.6 · Day-pass — sello giratorio, parallax y bloque estampado -->
    <section class="overflow-hidden bg-nodo-400 py-20 lg:py-24">
      <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-2 lg:gap-20">
        <ScrollReveal from="left">
          <div ref="fotoDaypass" class="relative">
            <img
              src="/img/nodico/daypass-emprendedor.webp"
              srcset="/img/nodico/daypass-emprendedor-640.webp 640w, /img/nodico/daypass-emprendedor-1280.webp 1280w, /img/nodico/daypass-emprendedor.webp 1677w"
              sizes="(min-width: 1024px) 50vw, 100vw"
              alt="Emprendedores del interior del estado trabajando en Nódico"
              width="1677"
              height="1920"
              loading="lazy"
              decoding="async"
              class="aspect-[4/3] w-full rounded-3xl object-cover shadow-sombra-lg will-change-transform"
              :style="{ transform: `translate3d(0, ${desplazamiento}px, 0)` }"
            />

            <!-- Sello giratorio superpuesto en la esquina -->
            <div class="absolute bottom-4 right-4 sm:bottom-6 sm:right-6">
              <SelloGiratorio />
            </div>
          </div>
        </ScrollReveal>

        <ScrollReveal from="right">
          <p class="etiqueta-tecnica mb-5 flex items-center gap-3 text-dark/70">
            <span class="h-1.5 w-1.5 rounded-full bg-dark" aria-hidden="true" />
            Day-pass emprendedor
          </p>

          <h2 class="font-display text-display-md font-extrabold text-dark">
            ¿Eres emprendedor o artesano del interior del estado?
          </h2>

          <!-- Entra como sello estampado, después del titular -->
          <ScrollReveal from="scale" :delay="220">
            <p class="mt-8 inline-block rotate-[-1.5deg] rounded-2xl bg-dark px-7 py-6 font-display text-2xl font-extrabold text-nodo-400 sm:text-3xl">
              Tu day-pass siempre es gratuito.
            </p>
          </ScrollReveal>

          <p class="mt-7 max-w-lg font-body text-cuerpo-lg text-dark/80">
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
              <dt class="etiqueta-tecnica text-white/60">{{ etiqueta }}</dt>
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
          <p class="etiqueta-tecnica mb-10 text-center text-dark/70">
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
