<script setup lang="ts">
import Boton from '@/Components/Public/Boton.vue'
import ContactSection from '@/Components/Public/ContactSection.vue'
import Meta from '@/Components/Public/Meta.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import type { Salon } from '@/tipos'
import { Check } from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps<{ salones?: Salon[] }>()

/**
 * Descripción y equipamiento son idénticos en las dos salas, así que se
 * muestran una sola vez. Si algún día difieren, esto deja de aplicar y
 * habría que volver a mostrarlos por sala.
 */
const descripcionComun = computed(() => props.salones?.[0]?.descripcion ?? '')
const incluyeComun = computed(() => props.salones?.[0]?.incluye ?? [])

const ficha = (salon: Salon) => [
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
  <Meta
    titulo="Salones para eventos"
    descripcion="Renta los salones Yucatán Emprende de Nódico en Mérida: 15x14 m, hasta 120 personas, proyector, sonido, internet y mobiliario incluido desde $600 MXN por hora."
    imagen="eventos"
  />

  <PublicLayout>
    <!-- Portada: foto del salón a sangre -->
    <section class="relative isolate flex min-h-[62svh] items-end overflow-hidden bg-tinta">
      <img
        src="/img/nodico/salon-yucatan-emprende-1.webp"
        srcset="/img/nodico/salon-yucatan-emprende-1-640.webp 640w, /img/nodico/salon-yucatan-emprende-1.webp 1079w"
        sizes="100vw"
        alt="Salón Yucatán Emprende montado para un evento"
        width="1079"
        height="1920"
        fetchpriority="high"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover"
      />
      <div class="absolute inset-0 -z-10 bg-gradient-to-t from-tinta via-tinta/80 to-tinta/45" aria-hidden="true" />

      <div class="mx-auto w-full max-w-7xl px-5 pb-16 pt-36 sm:px-8 lg:pb-20">
        <p class="etiqueta-tecnica mb-5 flex items-center gap-3 text-nodo-400">
          <span class="h-1.5 w-1.5 rounded-full bg-nodo-400" aria-hidden="true" />
          Salones
        </p>

        <h1 class="max-w-[18ch] font-display text-display-lg font-extrabold text-white">
          Espacios listos para tu evento
        </h1>

        <p class="mt-7 max-w-2xl font-body text-cuerpo-lg text-white/90">
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
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <!--
          Las dos salas comparten descripción y equipamiento palabra por palabra.
          En el original de Odoo el párrafo y la lista de once elementos se
          repetían íntegros en cada ficha; aquí se dicen una sola vez.
        -->
        <ScrollReveal class="mx-auto max-w-3xl text-center">
          <p class="font-body text-cuerpo-lg leading-relaxed text-dark/70">
            {{ descripcionComun }}
          </p>
        </ScrollReveal>

        <div class="mt-14 grid gap-6 lg:grid-cols-2">
          <ScrollReveal
            v-for="(salon, i) in salones ?? []"
            :key="salon.id ?? salon.nombre"
            :delay="i * 90"
            as="article"
            class="h-full"
          >
            <div class="flex h-full flex-col overflow-hidden rounded-3xl bg-white shadow-sombra ring-1 ring-dark/[.07]">
              <img
                v-if="salon.imagen"
                :src="salon.imagen"
                :alt="`Salón ${salon.nombre} de Nódico`"
                width="1920"
                height="1440"
                loading="lazy"
                decoding="async"
                class="aspect-[16/10] w-full object-cover"
              />

              <div class="flex flex-1 flex-col p-7 sm:p-8">
                <p class="etiqueta-tecnica text-dark/55">Sala 0{{ i + 1 }}</p>
                <h2 class="mt-3 font-display text-display-sm font-extrabold text-dark">
                  {{ salon.nombre }}
                </h2>

                <dl class="mt-7 grid grid-cols-2 gap-x-6 gap-y-5">
                  <div v-for="[etiqueta, valor] in ficha(salon)" :key="etiqueta">
                    <dt class="etiqueta-tecnica text-dark/55">{{ etiqueta }}</dt>
                    <dd class="mt-1.5 font-display text-base font-bold text-dark">{{ valor }}</dd>
                  </div>
                </dl>
              </div>
            </div>
          </ScrollReveal>
        </div>

        <!-- «Incluye» es idéntico en ambas: se lista una sola vez -->
        <ScrollReveal v-if="incluyeComun.length" class="mt-14">
          <div class="rounded-3xl bg-white p-8 shadow-sombra-sm ring-1 ring-dark/[.07] sm:p-10">
            <h2 class="font-display text-display-sm font-extrabold text-dark">
              Ambas salas incluyen
            </h2>
            <ul class="mt-7 grid gap-x-8 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
              <li v-for="item in incluyeComun" :key="item" class="flex items-center gap-2.5">
                <Check class="h-4 w-4 shrink-0 text-nodo-500" aria-hidden="true" />
                <span class="font-body text-sm text-dark/75">{{ item }}</span>
              </li>
            </ul>
          </div>
        </ScrollReveal>

        <ScrollReveal v-if="!salones?.length" class="mt-14">
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
        srcset="/img/nodico/salon-detalle-640.webp 640w, /img/nodico/salon-detalle-1280.webp 1280w, /img/nodico/salon-detalle.webp 1920w"
        sizes="100vw"
        alt=""
        aria-hidden="true"
        width="1920"
        height="1079"
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
              <p class="etiqueta-tecnica text-white/60">Hasta 25 pax</p>
              <p class="mt-3 font-display text-4xl font-extrabold text-nodo-400">$45</p>
              <p class="mt-1 font-body text-sm text-white/55">MXN por persona</p>
            </div>
            <div class="rounded-3xl bg-white/[.06] p-7 ring-1 ring-white/10">
              <p class="etiqueta-tecnica text-white/60">Desde 100 pax</p>
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
