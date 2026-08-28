<script setup lang="ts">
import ContactSection from '@/Components/Public/ContactSection.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'

defineProps<{ salones?: any[] }>()

const ficha = (salon: any) => [
  ['Medidas', salon.medidas],
  ['Costo por hora', `$${Number(salon.precio_hora).toLocaleString('es-MX')} mxn`],
  ['Capacidad', `${salon.capacidad} personas`],
  ['Herradura', `${salon.cap_herradura} personas`],
  ['Mesas de trabajo', `${salon.cap_mesas} personas`],
  ['Escuela', `${salon.cap_escuela} personas`],
  ['Auditorio', `${salon.cap_auditorio} personas`],
].filter(([, valor]) => valor != null && valor !== '')
</script>

<template>
  <Head>
    <title>Salones para eventos — Nódico</title>
    <meta
      name="description"
      content="Renta los salones Yucatán Emprende de Nódico en Mérida: 15x14 m, hasta 120 personas, proyector, sonido, internet y mobiliario incluido desde $600 MXN por hora."
    />
  </Head>

  <PublicLayout>
    <!-- Hero -->
    <section class="relative isolate overflow-hidden bg-nodo-400 pt-32 pb-16 lg:pt-40 lg:pb-24">
      <img
        src="/img/nodico/fondo-amarillo.webp"
        alt=""
        aria-hidden="true"
        class="absolute inset-0 -z-10 h-full w-full object-cover opacity-60"
      />

      <div class="mx-auto max-w-4xl px-5 text-center sm:px-8">
        <h1 class="font-display text-4xl font-extrabold leading-[1.05] tracking-tight text-dark sm:text-5xl lg:text-6xl">
          Conoce nuestros Salones para eventos
        </h1>
        <p class="mx-auto mt-6 max-w-2xl font-body text-base leading-relaxed text-dark/80 sm:text-lg">
          Nuestros espacios están listos para tus talleres, conferencias o reuniones. Modernos,
          cómodos y equipados para que cada idea cobre vida.
        </p>
      </div>
    </section>

    <!-- Salones -->
    <section class="bg-cream py-20 lg:py-28">
      <div class="mx-auto max-w-7xl space-y-16 px-5 sm:px-8 lg:space-y-24">
        <ScrollReveal v-for="(salon, i) in salones ?? []" :key="salon.id ?? salon.nombre" as="article">
          <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-16">
            <img
              v-if="salon.imagen"
              :src="salon.imagen"
              :alt="`Salón ${salon.nombre} de Nódico`"
              width="1000"
              height="750"
              :loading="i === 0 ? 'eager' : 'lazy'"
              decoding="async"
              class="aspect-[4/3] w-full rounded-4xl object-cover"
              :class="i % 2 === 1 ? 'lg:order-2' : ''"
            />

            <div :class="i % 2 === 1 ? 'lg:order-1' : ''">
              <SectionHeading :titulo="salon.nombre" />

              <p v-if="salon.descripcion" class="mt-6 font-body text-base leading-relaxed text-dark/75">
                {{ salon.descripcion }}
              </p>

              <template v-if="salon.incluye?.length">
                <h3 class="mt-8 font-display text-lg font-bold text-dark">Incluye</h3>
                <ul class="mt-4 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                  <li v-for="item in salon.incluye" :key="item" class="flex gap-2.5">
                    <Check class="mt-0.5 h-4 w-4 shrink-0 text-dark" aria-hidden="true" />
                    <span class="font-body text-sm text-dark/80">{{ item }}</span>
                  </li>
                </ul>
              </template>

              <dl class="mt-8 divide-y divide-dark/10 border-y border-dark/10">
                <div v-for="[etiqueta, valor] in ficha(salon)" :key="etiqueta" class="flex justify-between gap-6 py-3">
                  <dt class="font-body text-sm text-dark/60">{{ etiqueta }}</dt>
                  <dd class="font-body text-sm font-semibold text-dark">{{ valor }}</dd>
                </div>
              </dl>
            </div>
          </div>
        </ScrollReveal>

        <ScrollReveal v-if="!salones?.length">
          <SectionHeading
            titulo="Salones en actualización"
            align="center"
            descripcion="Estamos preparando la información de nuestros salones. Escríbenos y te compartimos disponibilidad y precios."
          />
        </ScrollReveal>

        <!-- Coffee break -->
        <ScrollReveal>
          <aside class="rounded-4xl bg-dark p-8 text-white sm:p-12">
            <h2 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Coffee Break</h2>
            <ul class="mt-6 space-y-2 font-body text-base text-white/80">
              <li>25 PAX — $45.00 MXN por persona.</li>
              <li>A partir de 100 PAX — $35.00 MXN por persona.</li>
            </ul>
            <a
              href="#hablemos"
              class="mt-8 inline-flex items-center gap-2 rounded-lg border-2 border-nodo-400 px-7 py-3
                     font-display text-sm font-bold text-nodo-400 transition hover:bg-nodo-400 hover:text-dark
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-400"
            >
              Cotizar mi evento
            </a>
          </aside>
        </ScrollReveal>
      </div>
    </section>

    <ContactSection />
  </PublicLayout>
</template>
