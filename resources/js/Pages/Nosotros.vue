<script setup lang="ts">
import Boton from '@/Components/Public/Boton.vue'
import ContactSection from '@/Components/Public/ContactSection.vue'
import Meta from '@/Components/Public/Meta.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'

/**
 * Descripciones redactadas para esta versión: el original de Odoo solo tenía
 * los nombres sueltos. PENDIENTE de validación por Nódico.
 * «Democratización del acceso a espacios de calidad» era un título de tres
 * líneas; se acorta a «Democratización del acceso» y lo demás pasa al cuerpo.
 */
const valores = [
  {
    icono: '/img/nodico/valor-creatividad.webp',
    titulo: 'Creatividad',
    descripcion: 'Espacios y encuentros pensados para que las ideas nuevas tengan dónde aparecer.',
  },
  {
    icono: '/img/nodico/valor-colaboracion.webp',
    titulo: 'Colaboración',
    descripcion: 'Lo que uno sabe le sirve al de al lado. Aquí eso se provoca a propósito.',
  },
  {
    icono: '/img/nodico/valor-innovacion.webp',
    titulo: 'Innovación',
    descripcion: 'Probar, equivocarse y volver a probar, con la comunidad como red de apoyo.',
  },
  {
    icono: '/img/nodico/valor-diversidad-inclusion.webp',
    titulo: 'Diversidad e inclusión',
    descripcion: 'Cabe todo el mundo: cualquier edad, cualquier sector, cualquier punto de partida.',
  },
  {
    icono: '/img/nodico/valor-democratizacion.webp',
    titulo: 'Democratización del acceso',
    descripcion: 'Un espacio de calidad no debería ser un privilegio. Por eso los precios son los que son.',
  },
  {
    icono: '/img/nodico/valor-comunidad.webp',
    titulo: 'Comunidad',
    descripcion: 'Más que compartir escritorio: compartir contactos, clientes y camino.',
  },
]
</script>

<template>
  <Meta
    titulo="Nosotros"
    descripcion="Más que un espacio físico, Nódico es una comunidad profesional en Mérida donde la colaboración, la vinculación estratégica y la formación continua convierten ideas en proyectos de impacto."
    imagen="nosotros"
  />

  <PublicLayout>
    <!-- Portada de sección: foto a sangre con velo -->
    <section class="relative isolate flex min-h-[62svh] items-end overflow-hidden bg-tinta">
      <img
        src="/img/nodico/nosotros-hero.webp"
        srcset="/img/nodico/nosotros-hero-640.webp 640w, /img/nodico/nosotros-hero-1280.webp 1280w, /img/nodico/nosotros-hero.webp 1920w"
        sizes="100vw"
        alt="Miembros de la comunidad Nódico"
        width="1920"
        height="1280"
        fetchpriority="high"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover object-center"
      />
      <div class="absolute inset-0 -z-10 bg-gradient-to-t from-tinta via-tinta/80 to-tinta/45" aria-hidden="true" />

      <div class="mx-auto w-full max-w-7xl px-5 pb-16 pt-36 sm:px-8 lg:pb-20">
        <p class="etiqueta-tecnica mb-5 flex items-center gap-3 text-nodo-400">
          <span class="h-1.5 w-1.5 rounded-full bg-nodo-400" aria-hidden="true" />
          Nódico MX
        </p>

        <h1 class="max-w-[14ch] font-display text-display-lg font-extrabold text-white">
          ¿Quiénes somos?
        </h1>

        <p class="mt-7 max-w-2xl font-body text-cuerpo-lg text-white/90">
          Más allá de un espacio físico, Nódico es una comunidad profesional donde se fomenta la
          colaboración, la vinculación estratégica y el desarrollo de habilidades a través de
          experiencias compartidas, eventos y formación continua.
        </p>

        <Boton :href="route('actividades')" variante="primario" tamano="lg" class="mt-9" flecha>
          Conocer la comunidad
        </Boton>
      </div>
    </section>

    <!-- Misión -->
    <section class="bg-cream py-20 lg:py-28">
      <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-2 lg:gap-20">
        <ScrollReveal from="left">
          <img
            src="/img/nodico/mision.webp"
        srcset="/img/nodico/mision-640.webp 640w, /img/nodico/mision-1280.webp 1280w, /img/nodico/mision.webp 1920w"
        sizes="(min-width: 1024px) 50vw, 100vw"
            alt="Emprendedores colaborando en una mesa de trabajo de Nódico"
            width="1920"
            height="1079"
            loading="lazy"
            decoding="async"
            class="aspect-[4/3] w-full rounded-3xl object-cover shadow-sombra"
          />
        </ScrollReveal>

        <ScrollReveal from="right">
          <SectionHeading etiqueta="Misión" titulo="Convertir ideas en proyectos de impacto" tamano="lg" />
          <div class="mt-8 space-y-5 font-body text-cuerpo-lg text-dark/70">
            <p>
              Ser el espacio donde los emprendedores encuentran las herramientas, conexiones y
              experiencias necesarias para transformar sus ideas en proyectos de impacto.
            </p>
            <p>
              En Nódico impulsamos la creatividad, la colaboración y la innovación mediante espacios
              funcionales, contenido de valor y una comunidad vibrante que reta el pensamiento y
              promueve el crecimiento.
            </p>
          </div>
        </ScrollReveal>
      </div>
    </section>

    <!-- Visión -->
    <section class="relative isolate overflow-hidden bg-tinta">
      <img
        src="/img/nodico/vision.webp"
        srcset="/img/nodico/vision-640.webp 640w, /img/nodico/vision-1280.webp 1280w, /img/nodico/vision.webp 1920w"
        sizes="100vw"
        alt=""
        aria-hidden="true"
        width="1920"
        height="1079"
        loading="lazy"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover"
      />
      <div class="absolute inset-0 -z-10 bg-tinta/[.88]" aria-hidden="true" />

      <div class="mx-auto max-w-4xl px-5 py-20 text-center sm:px-8 lg:py-28">
        <ScrollReveal>
          <SectionHeading
            etiqueta="Visión"
            titulo="El referente del sureste de México"
            tono="claro"
            align="center"
            tamano="lg"
          />
          <p class="mx-auto mt-8 max-w-3xl font-body text-cuerpo-lg text-white/75">
            Consolidarnos como el espacio referente en el sureste de México para el desarrollo de la
            creatividad, el emprendimiento y la innovación, reconocido por ser el punto de encuentro
            donde convergen las nuevas generaciones de creadores, emprendedores y agentes de cambio.
          </p>
        </ScrollReveal>
      </div>
    </section>

    <!-- Valores -->
    <section class="bg-cream-50 py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading etiqueta="Valores" titulo="Lo que nos mueve" align="center" tamano="lg" />
        </ScrollReveal>

        <ScrollReveal :stagger="70" as="ul" class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <li v-for="valor in valores" :key="valor.titulo">
            <article
              class="group flex h-full flex-col rounded-3xl bg-white p-7 shadow-sombra-sm ring-1 ring-dark/[.07]
                     transition-all duration-300 ease-salida hover:-translate-y-1 hover:shadow-sombra"
            >
              <img
                :src="valor.icono"
                alt=""
                aria-hidden="true"
                width="160"
                height="160"
                loading="lazy"
                decoding="async"
                class="mb-5 h-12 w-12 object-contain transition-transform duration-500 ease-salida group-hover:scale-110"
              />
              <h3 class="font-display text-lg font-bold leading-snug text-dark">
                {{ valor.titulo }}
              </h3>
              <p class="mt-2.5 font-body text-sm leading-relaxed text-dark/65">
                {{ valor.descripcion }}
              </p>
            </article>
          </li>
        </ScrollReveal>
      </div>
    </section>

    <ContactSection />
  </PublicLayout>
</template>
