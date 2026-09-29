<script setup lang="ts">
import Boton from '@/Components/Public/Boton.vue'
import ContactSection from '@/Components/Public/ContactSection.vue'
import Meta from '@/Components/Public/Meta.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { computed } from 'vue'

/** Secciones del módulo «Página Web», ya con su respaldo. */
const props = defineProps<{ contenido: Record<string, any> }>()

/**
 * Valores (nosotros.valores): el texto sale del módulo «Página Web»; el icono
 * es diseño y va por posición (decisión 2). Por eso la lista es de 3 o 6.
 */
const ICONOS_VALORES = [
  '/img/nodico/valor-creatividad.webp',
  '/img/nodico/valor-colaboracion.webp',
  '/img/nodico/valor-innovacion.webp',
  '/img/nodico/valor-diversidad-inclusion.webp',
  '/img/nodico/valor-democratizacion.webp',
  '/img/nodico/valor-comunidad.webp',
]

const valores = computed(() =>
  (props.contenido.valores.elementos as Array<{ titulo: string; descripcion: string }>)
    .map((v, i) => ({ ...v, icono: ICONOS_VALORES[i] })),
)
</script>

<template>
  <Meta />

  <PublicLayout>
    <!-- Portada de sección: foto a sangre con velo -->
    <section class="relative isolate flex min-h-[62svh] items-end overflow-hidden bg-tinta">
      <img
        :src="contenido.portada.imagen.src"
        :srcset="contenido.portada.imagen.srcset ?? undefined"
        sizes="100vw"
        :alt="contenido.portada.imagen.alt"
        :width="contenido.portada.imagen.width"
        :height="contenido.portada.imagen.height"
        fetchpriority="high"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover object-center"
      />
      <div class="absolute inset-0 -z-10 bg-gradient-to-t from-tinta via-tinta/80 to-tinta/45" aria-hidden="true" />

      <div class="mx-auto w-full max-w-7xl px-5 pb-16 pt-36 sm:px-8 lg:pb-20">
        <p class="etiqueta-tecnica mb-5 flex items-center gap-3 text-nodo-400">
          <span class="h-1.5 w-1.5 rounded-full bg-nodo-400" aria-hidden="true" />
          {{ contenido.portada.etiqueta }}
        </p>

        <h1 class="max-w-[14ch] font-display text-display-lg font-extrabold text-white">
          {{ contenido.portada.titulo }}
        </h1>

        <p class="mt-7 max-w-2xl font-body text-cuerpo-lg text-white/90">
          {{ contenido.portada.texto }}
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
            :src="contenido.mision.imagen.src"
            :srcset="contenido.mision.imagen.srcset ?? undefined"
            sizes="(min-width: 1024px) 50vw, 100vw"
            :alt="contenido.mision.imagen.alt"
            :width="contenido.mision.imagen.width"
            :height="contenido.mision.imagen.height"
            loading="lazy"
            decoding="async"
            class="aspect-[4/3] w-full rounded-3xl object-cover shadow-sombra"
          />
        </ScrollReveal>

        <ScrollReveal from="right">
          <SectionHeading etiqueta="Misión" :titulo="contenido.mision.titulo" tamano="lg" />
          <div class="mt-8 space-y-5 font-body text-cuerpo-lg text-dark/70">
            <p>{{ contenido.mision.parrafo1 }}</p>
            <p v-if="contenido.mision.parrafo2">{{ contenido.mision.parrafo2 }}</p>
          </div>
        </ScrollReveal>
      </div>
    </section>

    <!-- Visión -->
    <section class="relative isolate overflow-hidden bg-tinta">
      <img
        :src="contenido.vision.imagen.src"
        :srcset="contenido.vision.imagen.srcset ?? undefined"
        sizes="100vw"
        alt=""
        aria-hidden="true"
        :width="contenido.vision.imagen.width"
        :height="contenido.vision.imagen.height"
        loading="lazy"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover"
      />
      <div class="absolute inset-0 -z-10 bg-tinta/[.88]" aria-hidden="true" />

      <div class="mx-auto max-w-4xl px-5 py-20 text-center sm:px-8 lg:py-28">
        <ScrollReveal>
          <SectionHeading
            etiqueta="Visión"
            :titulo="contenido.vision.titulo"
            tono="claro"
            align="center"
            tamano="lg"
          />
          <p class="mx-auto mt-8 max-w-3xl font-body text-cuerpo-lg text-white/75">
            {{ contenido.vision.texto }}
          </p>
        </ScrollReveal>
      </div>
    </section>

    <!-- Valores -->
    <section v-if="contenido.valores.visible" class="bg-cream-50 py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading etiqueta="Valores" :titulo="contenido.valores.titulo" align="center" tamano="lg" />
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
              <p class="mt-2.5 font-body text-sm leading-relaxed text-dark/70">
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
