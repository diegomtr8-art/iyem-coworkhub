<script setup lang="ts">
import Boton from '@/Components/Public/Boton.vue'
import ContactSection from '@/Components/Public/ContactSection.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'

const valores = [
  { icono: '/img/nodico/valor-creatividad.svg', titulo: 'Creatividad' },
  { icono: '/img/nodico/valor-colaboracion.svg', titulo: 'Colaboración' },
  { icono: '/img/nodico/valor-innovacion.svg', titulo: 'Innovación' },
  { icono: '/img/nodico/valor-diversidad-inclusion.svg', titulo: 'Diversidad e inclusión' },
  { icono: '/img/nodico/valor-democratizacion.svg', titulo: 'Democratización del acceso a espacios de calidad' },
  { icono: '/img/nodico/valor-comunidad.svg', titulo: 'Comunidad' },
]
</script>

<template>
  <Head>
    <title>Nosotros — Nódico</title>
    <meta
      name="description"
      content="Más que un espacio físico, Nódico es una comunidad profesional en Mérida donde la colaboración, la vinculación estratégica y la formación continua convierten ideas en proyectos de impacto."
    />
  </Head>

  <PublicLayout>
    <!-- Portada de sección: foto a sangre con velo -->
    <section class="relative isolate flex min-h-[62svh] items-end overflow-hidden bg-tinta">
      <img
        src="/img/nodico/nosotros-hero.webp"
        alt="Miembros de la comunidad Nódico"
        width="1000"
        height="750"
        fetchpriority="high"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover object-center"
      />
      <div class="absolute inset-0 -z-10 bg-gradient-to-t from-tinta via-tinta/70 to-tinta/30" aria-hidden="true" />

      <div class="mx-auto w-full max-w-7xl px-5 pb-16 pt-36 sm:px-8 lg:pb-20">
        <p class="etiqueta-tecnica mb-5 flex items-center gap-3 text-nodo-400">
          <span class="h-1.5 w-1.5 rounded-full bg-nodo-400" aria-hidden="true" />
          Nódico MX
        </p>

        <h1 class="max-w-[14ch] font-display text-display-lg font-extrabold text-white">
          ¿Quiénes somos?
        </h1>

        <p class="mt-7 max-w-2xl font-body text-cuerpo-lg text-white/80">
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
            alt="Emprendedores colaborando en una mesa de trabajo de Nódico"
            width="1000"
            height="750"
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
        alt=""
        aria-hidden="true"
        width="1000"
        height="750"
        loading="lazy"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover"
      />
      <div class="absolute inset-0 -z-10 bg-tinta/88" aria-hidden="true" />

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

        <ScrollReveal class="mt-14">
          <ul class="grid overflow-hidden rounded-3xl bg-white shadow-sombra ring-1 ring-dark/[.07] sm:grid-cols-2 lg:grid-cols-3">
            <li
              v-for="(valor, i) in valores"
              :key="valor.titulo"
              class="group flex items-center gap-5 border-dark/[.08] p-7 transition-colors duration-300
                     hover:bg-cream-50 sm:p-8
                     [&:not(:last-child)]:border-b
                     sm:[&:nth-child(-n+4)]:border-b sm:[&:nth-last-child(-n+2)]:border-b-0
                     sm:[&:nth-child(odd)]:border-r
                     lg:[&:nth-child(-n+3)]:border-b lg:[&:nth-last-child(-n+3)]:border-b-0
                     lg:[&:nth-child(3n)]:border-r-0 lg:[&:not(:nth-child(3n))]:border-r"
            >
              <img
                :src="valor.icono"
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
                  {{ valor.titulo }}
                </p>
              </div>
            </li>
          </ul>
        </ScrollReveal>
      </div>
    </section>

    <ContactSection />
  </PublicLayout>
</template>
