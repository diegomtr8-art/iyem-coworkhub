<script setup lang="ts">
import Boton from '@/Components/Public/Boton.vue'
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
  ['Auditorio', `${salon.cap_auditorio} personas`],
  ['Escuela', `${salon.cap_escuela} personas`],
  ['Mesas de trabajo', `${salon.cap_mesas} personas`],
  ['Herradura', `${salon.cap_herradura} personas`],
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
    <!-- Portada: foto del salón a sangre -->
    <section class="relative isolate flex min-h-[62svh] items-end overflow-hidden bg-tinta">
      <img
        src="/img/nodico/salon-yucatan-emprende-1.webp"
        alt="Salón Yucatán Emprende montado para un evento"
        width="1000"
        height="750"
        fetchpriority="high"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover"
      />
      <div class="absolute inset-0 -z-10 bg-gradient-to-t from-tinta via-tinta/70 to-tinta/30" aria-hidden="true" />

      <div class="mx-auto w-full max-w-7xl px-5 pb-16 pt-36 sm:px-8 lg:pb-20">
        <p class="etiqueta-tecnica mb-5 flex items-center gap-3 text-nodo-400">
          <span class="h-1.5 w-1.5 rounded-full bg-nodo-400" aria-hidden="true" />
          Salones
        </p>

        <h1 class="max-w-[18ch] font-display text-display-lg font-extrabold text-white">
          Espacios listos para tu evento
        </h1>

        <p class="mt-7 max-w-2xl font-body text-cuerpo-lg text-white/80">
          Nuestros salones están listos para tus talleres, conferencias o reuniones. Modernos,
          cómodos y equipados para que cada idea cobre vida.
        </p>

        <Boton href="#hablemos" variante="primario" tamano="lg" class="mt-9" flecha>
          Cotizar mi evento
        </Boton>
      </div>
    </section>

    <!-- Salones -->
    <section class="bg-cream py-20 lg:py-28">
      <div class="mx-auto max-w-7xl space-y-20 px-5 sm:px-8 lg:space-y-28">
        <ScrollReveal v-for="(salon, i) in salones ?? []" :key="salon.id ?? salon.nombre" as="article">
          <div class="grid items-start gap-12 lg:grid-cols-2 lg:gap-16">
            <img
              v-if="salon.imagen"
              :src="salon.imagen"
              :alt="`Salón ${salon.nombre} de Nódico`"
              width="1000"
              height="750"
              loading="lazy"
              decoding="async"
              class="aspect-[4/3] w-full rounded-3xl object-cover shadow-sombra"
              :class="i % 2 === 1 ? 'lg:order-2' : ''"
            />

            <div :class="i % 2 === 1 ? 'lg:order-1' : ''">
              <SectionHeading :etiqueta="`Sala 0${i + 1}`" :titulo="salon.nombre" tamano="lg" />

              <p v-if="salon.descripcion" class="mt-7 font-body text-cuerpo leading-relaxed text-dark/70">
                {{ salon.descripcion }}
              </p>

              <!-- Ficha técnica -->
              <dl class="mt-9 grid grid-cols-2 gap-x-8 gap-y-5 rounded-3xl bg-white p-7 shadow-sombra-sm ring-1 ring-dark/[.07] sm:grid-cols-3">
                <div v-for="[etiqueta, valor] in ficha(salon)" :key="etiqueta">
                  <dt class="etiqueta-tecnica text-dark/35">{{ etiqueta }}</dt>
                  <dd class="mt-2 font-display text-base font-bold text-dark">{{ valor }}</dd>
                </div>
              </dl>

              <template v-if="salon.incluye?.length">
                <h3 class="mt-10 font-display text-lg font-bold text-dark">Incluye</h3>
                <ul class="mt-5 grid gap-x-6 gap-y-2.5 sm:grid-cols-2">
                  <li v-for="item in salon.incluye" :key="item" class="flex items-center gap-2.5">
                    <Check class="h-4 w-4 shrink-0 text-nodo-500" aria-hidden="true" />
                    <span class="font-body text-sm text-dark/75">{{ item }}</span>
                  </li>
                </ul>
              </template>
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
      </div>
    </section>

    <!-- Coffee break -->
    <section class="relative isolate overflow-hidden bg-tinta">
      <img
        src="/img/nodico/salon-detalle.webp"
        alt=""
        aria-hidden="true"
        width="1000"
        height="750"
        loading="lazy"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover"
      />
      <div class="absolute inset-0 -z-10 bg-tinta/90" aria-hidden="true" />

      <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:py-24">
        <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-20">
          <ScrollReveal from="left">
            <SectionHeading
              etiqueta="Servicio adicional"
              titulo="Coffee break para tu evento"
              tono="claro"
              tamano="lg"
            />
          </ScrollReveal>

          <ScrollReveal from="right" class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-3xl bg-white/[.06] p-7 ring-1 ring-white/10">
              <p class="etiqueta-tecnica text-white/40">Hasta 25 pax</p>
              <p class="mt-3 font-display text-4xl font-extrabold text-nodo-400">$45</p>
              <p class="mt-1 font-body text-sm text-white/55">MXN por persona</p>
            </div>
            <div class="rounded-3xl bg-white/[.06] p-7 ring-1 ring-white/10">
              <p class="etiqueta-tecnica text-white/40">Desde 100 pax</p>
              <p class="mt-3 font-display text-4xl font-extrabold text-nodo-400">$35</p>
              <p class="mt-1 font-body text-sm text-white/55">MXN por persona</p>
            </div>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <ContactSection />
  </PublicLayout>
</template>
