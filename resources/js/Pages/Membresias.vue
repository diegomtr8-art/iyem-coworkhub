<script setup lang="ts">
import ContactSection from '@/Components/Public/ContactSection.vue'
import Meta from '@/Components/Public/Meta.vue'
import PlanesCarousel from '@/Components/Public/PlanesCarousel.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import type { Plan } from '@/tipos'
import { Check, CreditCard, ShieldCheck, Users } from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps<{
  planes?: Plan[]
  /** Secciones del módulo «Página Web», ya con su respaldo. */
  contenido: Record<string, any>
}>()

/** SEO-03 — cada membresía como Offer dentro de un catálogo. */
const ofertas = computed(() => ({
  '@context': 'https://schema.org',
  '@type': 'OfferCatalog',
  name: 'Membresías de Nódico',
  itemListElement: (props.planes ?? []).map((plan) => ({
    '@type': 'Offer',
    name: plan.nombre,
    description: plan.descripcion_larga ?? plan.descripcion_corta ?? undefined,
    price: Number(plan.precio),
    priceCurrency: 'MXN',
    url: plan.stripe_url ?? undefined,
    availability: 'https://schema.org/InStock',
  })),
}))

/** Siempre incluido (membresias.incluido), del módulo «Página Web». */
const incluidoEnTodas = computed(() =>
  (props.contenido.incluido.elementos as Array<{ texto: string }>).map((i) => i.texto),
)

/** Cómo funciona (membresias.pasos): el texto del módulo, el icono por posición. */
const ICONOS_PASOS = [CreditCard, Users, ShieldCheck]

const comoFunciona = computed(() =>
  (props.contenido.pasos.elementos as Array<{ titulo: string; texto: string }>)
    .map((p, i) => ({ ...p, icono: ICONOS_PASOS[i] })),
)
</script>

<template>
  <Meta
    :datos-estructurados="ofertas"
  />

  <PublicLayout>
    <!-- Portada -->
    <section class="bg-nodo-400 pb-16 pt-32 lg:pb-20 lg:pt-40">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <p class="etiqueta-tecnica mb-5 flex items-center gap-3 text-dark/70">
          <span class="h-1.5 w-1.5 rounded-full bg-dark" aria-hidden="true" />
          {{ contenido.portada.etiqueta }}
        </p>

        <h1 class="max-w-[16ch] font-display text-display-lg font-extrabold text-dark">
          {{ contenido.portada.titulo }}
        </h1>

        <p class="mt-7 max-w-2xl font-body text-cuerpo-lg text-dark/80">
          {{ contenido.portada.texto }}
        </p>
      </div>
    </section>

    <!-- Planes -->
    <section class="overflow-hidden bg-cream-50 py-16 lg:py-20">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <PlanesCarousel v-if="planes?.length" :planes="planes" detallado />

        <div v-else class="mx-auto max-w-xl">
          <SectionHeading
            :titulo="contenido.vacio.titulo"
            :descripcion="contenido.vacio.descripcion"
            align="center"
          />
        </div>
      </div>
    </section>

    <!-- Incluido en todas -->
    <section class="bg-cream py-20 lg:py-24">
      <div class="mx-auto grid max-w-7xl gap-14 px-5 sm:px-8 lg:grid-cols-2 lg:gap-20">
        <ScrollReveal from="left">
          <SectionHeading
            etiqueta="Siempre incluido"
            :titulo="contenido.incluido.titulo"
            :descripcion="contenido.incluido.descripcion"
            tamano="lg"
          />
        </ScrollReveal>

        <ScrollReveal from="right" :stagger="70" as="ul" class="space-y-1">
          <li
            v-for="item in incluidoEnTodas"
            :key="item"
            class="flex items-center gap-5 border-b border-dark/10 py-5"
          >
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-nodo-400">
              <Check class="h-4 w-4 text-dark" aria-hidden="true" />
            </span>
            <p class="font-display text-base font-bold text-dark sm:text-lg">{{ item }}</p>
          </li>
        </ScrollReveal>
      </div>
    </section>

    <!-- Cómo funciona -->
    <section class="bg-tinta py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
            etiqueta="Cómo funciona"
            :titulo="contenido.pasos.titulo"
            tono="claro"
            align="center"
            tamano="lg"
          />
        </ScrollReveal>

        <ScrollReveal :stagger="80" class="mt-14 grid gap-6 lg:grid-cols-3">
          <div
            v-for="(paso, i) in comoFunciona"
            :key="paso.titulo"
            class="rounded-3xl bg-white/[.05] p-8 ring-1 ring-white/10"
          >
            <div class="flex items-center gap-4">
              <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-nodo-400">
                <component :is="paso.icono" class="h-5 w-5 text-dark" aria-hidden="true" />
              </span>
              <span class="etiqueta-tecnica text-white/60" aria-hidden="true">
                0{{ i + 1 }}
              </span>
            </div>

            <h3 class="mt-6 font-display text-xl font-bold text-white">{{ paso.titulo }}</h3>
            <p class="mt-3 font-body text-sm leading-relaxed text-white/60">{{ paso.texto }}</p>
          </div>
        </ScrollReveal>

        <p v-if="contenido.pasos.nota" class="mx-auto mt-12 max-w-2xl text-center font-body text-sm text-white/65">
          {{ contenido.pasos.nota }}
        </p>
      </div>
    </section>

    <ContactSection />
  </PublicLayout>
</template>
