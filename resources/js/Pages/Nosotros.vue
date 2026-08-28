<script setup lang="ts">
import ContactSection from '@/Components/Public/ContactSection.vue'
import IconCard from '@/Components/Public/IconCard.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

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
      content="Conoce a Nódico: más que un espacio físico, una comunidad profesional en Mérida donde la colaboración, la vinculación estratégica y la formación continua impulsan proyectos de impacto."
    />
  </Head>

  <PublicLayout>
    <!-- Hero -->
    <section class="relative isolate overflow-hidden bg-nodo-400 pt-28 lg:pt-32">
      <img
        src="/img/nodico/nosotros-fondo.png"
        alt=""
        aria-hidden="true"
        class="absolute inset-0 -z-10 h-full w-full object-cover opacity-25"
      />

      <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 py-16 sm:px-8 lg:grid-cols-2 lg:gap-16 lg:py-24">
        <div>
          <p class="font-display text-sm font-bold uppercase tracking-[0.3em] text-dark/60">Nódico MX</p>
          <h1 class="mt-4 font-display text-4xl font-extrabold leading-[1.05] tracking-tight text-dark sm:text-5xl lg:text-6xl">
            ¿Quiénes somos?
          </h1>
          <p class="mt-6 max-w-xl font-body text-base leading-relaxed text-dark/80 sm:text-lg">
            Más allá de un espacio físico, Nódico es una comunidad profesional donde se fomenta la
            colaboración, la vinculación estratégica y el desarrollo de habilidades a través de
            experiencias compartidas, eventos y formación continua.
          </p>

          <Link
            :href="route('actividades')"
            class="mt-10 inline-flex items-center gap-2 rounded-lg border-2 border-dark bg-dark px-7 py-3
                   font-display text-sm font-bold text-nodo-400 transition hover:bg-dark-light
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
          >
            Conocer más <span aria-hidden="true">→</span>
          </Link>
        </div>

        <div>
          <img
            src="/img/nodico/nosotros-hero.webp"
            alt="Equipo y miembros de la comunidad Nódico"
            width="1000"
            height="750"
            fetchpriority="high"
            decoding="async"
            class="aspect-[4/3] w-full rounded-4xl object-cover shadow-2xl"
          />
        </div>
      </div>
    </section>

    <!-- Misión -->
    <section class="bg-cream py-20 lg:py-28">
      <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:gap-16">
        <ScrollReveal from="left">
          <img
            src="/img/nodico/mision.webp"
            alt="Emprendedores colaborando en una mesa de trabajo de Nódico"
            width="1000"
            height="750"
            loading="lazy"
            decoding="async"
            class="aspect-[4/3] w-full rounded-4xl object-cover"
          />
        </ScrollReveal>

        <ScrollReveal from="right">
          <SectionHeading titulo="Nuestra Misión" />
          <div class="mt-6 space-y-5 font-body text-base leading-relaxed text-dark/75 sm:text-lg">
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
    <section class="bg-white py-20 lg:py-28">
      <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:gap-16">
        <ScrollReveal from="left" class="lg:order-2">
          <img
            src="/img/nodico/vision.webp"
            alt="Área común de Nódico durante una jornada de trabajo"
            width="1000"
            height="750"
            loading="lazy"
            decoding="async"
            class="aspect-[4/3] w-full rounded-4xl object-cover"
          />
        </ScrollReveal>

        <ScrollReveal from="right" class="lg:order-1">
          <SectionHeading titulo="Nuestra Visión" />
          <p class="mt-6 font-body text-base leading-relaxed text-dark/75 sm:text-lg">
            Consolidarnos como el espacio referente en el sureste de México para el desarrollo de la
            creatividad, el emprendimiento y la innovación, reconocido por ser el punto de encuentro
            donde convergen las nuevas generaciones de creadores, emprendedores y agentes de cambio.
          </p>
        </ScrollReveal>
      </div>
    </section>

    <!-- Valores -->
    <section class="bg-cream py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading titulo="Nuestros Valores" align="center" />
        </ScrollReveal>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <ScrollReveal v-for="(valor, i) in valores" :key="valor.titulo" :delay="i * 70">
            <IconCard :icono="valor.icono" :titulo="valor.titulo" />
          </ScrollReveal>
        </div>
      </div>
    </section>

    <ContactSection />
  </PublicLayout>
</template>
