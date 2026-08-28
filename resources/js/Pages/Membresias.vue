<script setup lang="ts">
import ContactSection from '@/Components/Public/ContactSection.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'

defineProps<{ planes?: any[] }>()

const precio = (valor: number) =>
  Number(valor).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })

const titulo = (plan: any) =>
  (plan.personas ?? 1) > 1 ? `${plan.nombre} (${plan.personas} pax)` : plan.nombre
</script>

<template>
  <Head>
    <title>Membresías y precios — Nódico</title>
    <meta
      name="description"
      content="Day-Pass, Nódico Flex, Nodo Pro y Nodo Match: elige la membresía de coworking que se ajusta a tu proyecto. Precios desde $79 MXN con acceso a sala de creación de contenido, café y comunidad."
    />
  </Head>

  <PublicLayout>
    <section class="bg-nodo-400 pt-32 pb-16 lg:pt-40 lg:pb-20">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <h1 class="text-center font-display text-4xl font-extrabold tracking-tight text-dark sm:text-5xl lg:text-6xl">
          Precios competitivos
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-center font-body text-base leading-relaxed text-dark/80 sm:text-lg">
          Todas nuestras membresías incluyen acceso a la comunidad Nódico, café y agua durante tu estancia.
        </p>
      </div>
    </section>

    <section class="bg-cream py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <div v-if="planes?.length" class="grid gap-7 md:grid-cols-2 xl:grid-cols-4">
          <ScrollReveal v-for="(plan, i) in planes" :key="plan.id ?? plan.nombre" :delay="i * 80" class="h-full">
            <article class="flex h-full flex-col overflow-hidden rounded-3xl bg-white shadow-sm">
              <div class="h-2.5 w-full shrink-0" :style="{ backgroundColor: plan.color ?? '#FFE124' }" aria-hidden="true" />

              <div class="flex flex-1 flex-col p-7">
                <h2 class="font-display text-xl font-extrabold tracking-tight text-dark">
                  {{ titulo(plan) }}
                </h2>

                <p class="mt-3 font-display text-3xl font-extrabold text-dark">
                  {{ precio(plan.precio) }}
                  <span v-if="plan.periodo_label" class="block font-body text-sm font-normal text-dark/60">
                    {{ plan.periodo_label }}
                  </span>
                </p>

                <p v-if="plan.descripcion_larga" class="mt-5 font-body text-sm leading-relaxed text-dark/70">
                  {{ plan.descripcion_larga }}
                </p>

                <ul v-if="plan.beneficios?.length" class="mt-6 flex-1 space-y-3">
                  <li v-for="beneficio in plan.beneficios" :key="beneficio" class="flex gap-3">
                    <Check class="mt-0.5 h-4 w-4 shrink-0 text-dark" aria-hidden="true" />
                    <span class="font-body text-sm leading-snug text-dark/80">{{ beneficio }}</span>
                  </li>
                </ul>

                <a
                  v-if="plan.stripe_url"
                  :href="plan.stripe_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="mt-8 inline-flex w-full items-center justify-center rounded-lg border-2 border-dark bg-nodo-400
                         px-6 py-3 font-display text-sm font-bold text-dark transition hover:brightness-95
                         focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
                >
                  {{ plan.cta_label ?? 'Empezar Ahora' }}
                  <span class="sr-only">— {{ titulo(plan) }}</span>
                </a>
              </div>
            </article>
          </ScrollReveal>
        </div>

        <div v-else class="mx-auto max-w-xl text-center">
          <SectionHeading
            titulo="Membresías en actualización"
            align="center"
            descripcion="Estamos afinando los planes. Escríbenos y con gusto te compartimos los precios vigentes."
          />
        </div>
      </div>
    </section>

    <ContactSection />
  </PublicLayout>
</template>
