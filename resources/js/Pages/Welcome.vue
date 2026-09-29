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
  /** Secciones del módulo «Página Web» para la portada, ya con su respaldo. */
  contenido: Record<string, any>
}>()

const page = usePage()
const nodico = computed(() => (page.props.nodico ?? {}) as any)

/**
 * 4.3 — mosaico asimétrico. Los dos de más peso comercial ocupan celda grande
 * con foto; los otros cuatro van compactos.
 *
 * El texto sale del módulo «Página Web» (inicio.servicios); icono, foto y
 * tamaño de celda son diseño y van por posición (decisión 2).
 */
const DISENO_SERVICIOS = [
  { icono: '/img/nodico/icono-espacio-colaborativo.webp', protagonista: true },
  { icono: '/img/nodico/icono-sala-contenido.webp', protagonista: true },
  { icono: '/img/nodico/icono-wifi.webp' },
  { icono: '/img/nodico/icono-paqueteria.webp' },
  { icono: '/img/nodico/icono-invitados.webp' },
  { icono: '/img/nodico/icono-cafe-agua.webp' },
]

/** Las dos tarjetas grandes llevan foto (inicio.servicios.foto_1 y foto_2). */
const servicios = computed(() => {
  const fotos = [props.contenido.servicios.foto_1, props.contenido.servicios.foto_2]
  return (props.contenido.servicios.elementos as Array<{ titulo: string; descripcion: string }>)
    .map((texto, i) => ({ ...DISENO_SERVICIOS[i], ...texto, foto: fotos[i] ?? null }))
})

const protagonistas = computed(() => servicios.value.filter((x) => x.protagonista))
const compactos = computed(() => servicios.value.filter((x) => !x.protagonista))

/**
 * Espacios reservables (inicio.espacios), cada uno con la foto de ese espacio
 * de verdad, no una genérica del coworking: ese era el problema que tenía la
 * tarjeta de la sala de contenido.
 */
const espacios = computed(() => props.contenido.espacios.elementos as Array<Record<string, any>>)

/**
 * 4.4 — paneles expansibles (inicio.beneficios). El color es de la paleta y
 * va por posición; con un sexto panel se repite el primero.
 */
const ACENTOS_BENEFICIOS = ['#FFDD00', '#D6E265', '#EF7E88', '#864B95', '#FFE124']

const beneficios = computed(() =>
  (props.contenido.beneficios.elementos as Array<{ titulo: string; titulo_corto: string; descripcion: string; foto: any }>)
    .map((b, i) => ({
      titulo: b.titulo,
      tituloCorto: b.titulo_corto,
      descripcion: b.descripcion,
      foto: b.foto,
      acento: ACENTOS_BENEFICIOS[i % ACENTOS_BENEFICIOS.length],
    })),
)

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
  <Meta />

  <PublicLayout>
    <!-- ═══ HERO — aprobado, no se modifica ═══ -->
    <HeroVideo
      :video-id="contenido.hero.video_youtube"
      :antetitulo="contenido.hero.antetitulo"
      :titulo="contenido.hero.titulo"
      :subtitulo="contenido.hero.subtitulo"
      :imagen="contenido.hero.imagen"
      :telefono-e164="nodico.telefonoE164"
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
            :titulo="contenido.servicios.titulo"
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
                :src="servicio.foto.src"
                :srcset="servicio.foto.srcset ?? undefined"
                alt=""
                aria-hidden="true"
                :width="servicio.foto.width"
                :height="servicio.foto.height"
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

    <!-- Espacios — qué puedes reservar, con foto de cada uno -->
    <section v-if="contenido.espacios.visible" class="bg-cream-50 py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
            etiqueta="Espacios"
            :titulo="contenido.espacios.titulo"
            :descripcion="contenido.espacios.descripcion"
            align="center"
            tamano="lg"
          />
        </ScrollReveal>

        <div class="mt-14 grid gap-6 sm:grid-cols-2">
          <ScrollReveal
            v-for="(espacio, i) in espacios"
            :key="espacio.nombre"
            :delay="i * 80"
          >
            <article
              class="group flex h-full flex-col overflow-hidden rounded-3xl bg-white shadow-sombra-sm ring-1 ring-dark/[.07]
                     transition-all duration-300 ease-salida hover:-translate-y-1 hover:shadow-sombra"
            >
              <div class="relative aspect-[4/3] overflow-hidden">
                <img
                  :src="espacio.foto.src"
                  :srcset="espacio.foto.srcset ?? undefined"
                  sizes="(min-width: 640px) 50vw, 100vw"
                  :alt="espacio.foto.alt"
                  :width="espacio.foto.width"
                  :height="espacio.foto.height"
                  loading="lazy"
                  decoding="async"
                  class="h-full w-full object-cover transition-transform duration-700 ease-salida group-hover:scale-105"
                />
                <span
                  class="absolute left-4 top-4 rounded-full bg-tinta/85 px-3 py-1 font-body text-xs font-semibold text-white backdrop-blur-sm"
                >
                  {{ espacio.cantidad }}
                </span>
              </div>

              <div class="flex flex-1 flex-col p-7">
                <h3 class="font-display text-xl font-bold leading-snug text-dark">
                  {{ espacio.nombre }}
                </h3>
                <p class="mt-2.5 flex-1 font-body text-cuerpo leading-relaxed text-dark/70">
                  {{ espacio.descripcion }}
                </p>
                <p class="etiqueta-tecnica mt-5 text-dark/55">
                  {{ espacio.capacidad }}
                </p>
              </div>
            </article>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- 4.4 · Beneficios — paneles expansibles -->
    <section v-if="contenido.beneficios.visible" class="bg-tinta py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
            etiqueta="Beneficios"
            :titulo="contenido.beneficios.titulo"
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
            :titulo="contenido.membresias.titulo"
            :descripcion="contenido.membresias.descripcion"
            align="center"
            tamano="lg"
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
    <section v-if="contenido.daypass.visible" class="overflow-hidden bg-nodo-400 py-20 lg:py-24">
      <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-2 lg:gap-20">
        <ScrollReveal from="left">
          <div ref="fotoDaypass" class="relative">
            <img
              :src="contenido.daypass.imagen.src"
              :srcset="contenido.daypass.imagen.srcset ?? undefined"
              sizes="(min-width: 1024px) 50vw, 100vw"
              :alt="contenido.daypass.imagen.alt"
              :width="contenido.daypass.imagen.width"
              :height="contenido.daypass.imagen.height"
              loading="lazy"
              decoding="async"
              class="aspect-[4/3] w-full rounded-3xl object-cover shadow-sombra-lg will-change-transform"
              :style="{ transform: `translate3d(0, ${desplazamiento}px, 0)` }"
            />

            <!-- Sello giratorio superpuesto en la esquina -->
            <div class="absolute bottom-4 right-4 sm:bottom-6 sm:right-6">
              <SelloGiratorio :texto="contenido.daypass.sello_giratorio" />
            </div>
          </div>
        </ScrollReveal>

        <ScrollReveal from="right">
          <p class="etiqueta-tecnica mb-5 flex items-center gap-3 text-dark/70">
            <span class="h-1.5 w-1.5 rounded-full bg-dark" aria-hidden="true" />
            {{ contenido.daypass.etiqueta }}
          </p>

          <h2 class="font-display text-display-md font-extrabold text-dark">
            {{ contenido.daypass.titulo }}
          </h2>

          <!-- Entra como sello estampado, después del titular -->
          <ScrollReveal from="scale" :delay="220">
            <p class="mt-8 inline-block rotate-[-1.5deg] rounded-2xl bg-dark px-7 py-6 font-display text-2xl font-extrabold text-nodo-400 sm:text-3xl">
              {{ contenido.daypass.sello }}
            </p>
          </ScrollReveal>

          <p class="mt-7 max-w-lg font-body text-cuerpo-lg text-dark/80">
            {{ contenido.daypass.texto }}
          </p>

          <Boton href="#hablemos" variante="secundario" tamano="lg" class="mt-8" flecha>
            Contáctanos para más informes
          </Boton>
        </ScrollReveal>
      </div>
    </section>

    <!-- ═══ SALONES — Fase 4.G.5: más altura y aire para que la foto respire
         en 1366 y 1920 px (antes quedaba aplastada) ═══ -->
    <section v-if="contenido.salones.visible" class="relative isolate flex min-h-[560px] items-center overflow-hidden bg-tinta lg:min-h-[680px]">
      <img
        :src="contenido.salones.imagen.src"
        :srcset="contenido.salones.imagen.srcset ?? undefined"
        sizes="100vw"
        :alt="contenido.salones.imagen.alt"
        :width="contenido.salones.imagen.width"
        :height="contenido.salones.imagen.height"
        loading="lazy"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover"
      />
      <div class="absolute inset-0 -z-10 bg-gradient-to-r from-tinta via-tinta/[.92] to-tinta/60" aria-hidden="true" />

      <div class="mx-auto w-full max-w-7xl px-5 py-28 sm:px-8 lg:py-40">
        <ScrollReveal class="max-w-2xl">
          <SectionHeading
            etiqueta="Salones"
            :titulo="contenido.salones.titulo"
            :descripcion="contenido.salones.descripcion"
            tono="claro"
            tamano="lg"
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
    <section v-if="$page.props.comun.aliados.visible" class="bg-cream-50 py-16 lg:py-20">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <p class="etiqueta-tecnica mb-10 text-center text-dark/70">
            {{ $page.props.comun.aliados.etiqueta }}
          </p>
          <AliadosSection />
        </ScrollReveal>
      </div>
    </section>

    <!-- Instagram -->
    <InstagramSection v-if="$page.props.comun.instagram.visible_inicio" :handle="nodico.instagram" />

    <ContactSection />
  </PublicLayout>
</template>
