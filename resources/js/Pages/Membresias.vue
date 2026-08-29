<script setup lang="ts">
import ContactSection from '@/Components/Public/ContactSection.vue'
import Meta from '@/Components/Public/Meta.vue'
import PlanesCarousel from '@/Components/Public/PlanesCarousel.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Check, CreditCard, ShieldCheck, Users } from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps<{ planes?: any[] }>()

/** SEO-03 — cada membresía como Offer dentro de un catálogo. */
const ofertas = computed(() => ({
  '@context': 'https://schema.org',
  '@type': 'OfferCatalog',
  name: 'Membresías de Nódico',
  itemListElement: (props.planes ?? []).map((plan: any) => ({
    '@type': 'Offer',
    name: plan.nombre,
    description: plan.descripcion_larga ?? plan.descripcion_corta ?? undefined,
    price: Number(plan.precio),
    priceCurrency: 'MXN',
    url: plan.stripe_url ?? undefined,
    availability: 'https://schema.org/InStock',
  })),
}))

const incluidoEnTodas = [
  'Acceso a la comunidad emprendedora de Nódico',
  'Agua y café durante tu estancia',
  'Wifi con 200 MB de velocidad',
  'Espacio pet friendly',
]

const comoFunciona = [
  { icono: CreditCard, titulo: 'Elige y paga en línea', texto: 'El cobro se procesa por Stripe. Nódico no almacena datos de tarjeta.' },
  { icono: Users, titulo: 'Registra tu acceso', texto: 'Pasa a recepción para dar de alta tu Face ID y activar la membresía.' },
  { icono: ShieldCheck, titulo: 'Usa el espacio', texto: 'Reserva salas y estudio de contenido desde la plataforma, según tu plan.' },
]
</script>

<template>
  <Meta
    titulo="Membresías y precios"
    descripcion="Day-Pass, Nódico Flex, Nodo Pro y Nodo Match: elige la membresía de coworking que se ajusta a tu proyecto. Desde $79 MXN, con sala de creación de contenido, café y comunidad incluidos."
    imagen="membresias"
    :datos-estructurados="ofertas"
  />

  <PublicLayout>
    <!-- Portada -->
    <section class="bg-nodo-400 pb-16 pt-32 lg:pb-20 lg:pt-40">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <p class="etiqueta-tecnica mb-5 flex items-center gap-3 text-dark/55">
          <span class="h-1.5 w-1.5 rounded-full bg-dark" aria-hidden="true" />
          Membresías
        </p>

        <h1 class="max-w-[16ch] font-display text-display-lg font-extrabold text-dark">
          Precios competitivos
        </h1>

        <p class="mt-7 max-w-2xl font-body text-cuerpo-lg text-dark/70">
          Cuatro planes para etapas distintas: desde un día suelto hasta acceso ilimitado para dos
          personas. Todos incluyen comunidad, café y wifi.
        </p>
      </div>
    </section>

    <!-- Planes -->
    <section class="overflow-hidden bg-cream-50 py-16 lg:py-20">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <PlanesCarousel v-if="planes?.length" :planes="planes" detallado />

        <div v-else class="mx-auto max-w-xl">
          <SectionHeading
            titulo="Membresías en actualización"
            align="center"
            descripcion="Estamos afinando los planes. Escríbenos y con gusto te compartimos los precios vigentes."
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
            titulo="Da igual el plan que elijas"
            tamano="lg"
            descripcion="Hay cosas que no dependen de la membresía: vienen con el simple hecho de ser parte de Nódico."
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
            titulo="De la compra al escritorio"
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
              <span class="etiqueta-tecnica text-white/35" aria-hidden="true">
                0{{ i + 1 }}
              </span>
            </div>

            <h3 class="mt-6 font-display text-xl font-bold text-white">{{ paso.titulo }}</h3>
            <p class="mt-3 font-body text-sm leading-relaxed text-white/60">{{ paso.texto }}</p>
          </div>
        </ScrollReveal>

        <p class="mx-auto mt-12 max-w-2xl text-center font-body text-sm text-white/45">
          ¿Eres emprendedor o artesano del interior del estado? Tu day-pass siempre es gratuito:
          escríbenos y te damos acceso sin costo.
        </p>
      </div>
    </section>

    <ContactSection />
  </PublicLayout>
</template>
