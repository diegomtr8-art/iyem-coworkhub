<script setup lang="ts">
import Boton from '@/Components/Public/Boton.vue'
import ContactSection from '@/Components/Public/ContactSection.vue'
import InstagramSection from '@/Components/Public/InstagramSection.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { Instagram } from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps<{
  proximos?: any[]
  pasados?: any[]
  salon?: any | null
  lumaEmbed?: string
  instagramPosts?: string[]
  /** CNT-02: ambos vienen de la tabla directorio_emprendedores. */
  directorio?: any[]
  destacado?: any | null
}>()

const page = usePage()
const nodico = computed(() => (page.props.nodico ?? {}) as any)

const eventos = computed(() => [...(props.proximos ?? []), ...(props.pasados ?? [])].slice(0, 6))

const fechaLarga = (valor: string) =>
  new Date(valor).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })
</script>

<template>
  <Head>
    <title>Comunidad y actividades — Nódico</title>
    <meta
      name="description"
      content="Talleres, eventos y el directorio de emprendedores de Nódico. Conoce las actividades del mes y a la comunidad que forma parte de los programas de incubación del IYEM."
    />
  </Head>

  <PublicLayout>
    <!-- Portada -->
    <section class="relative isolate flex min-h-[58svh] items-end overflow-hidden bg-tinta">
      <img
        src="/img/nodico/comunidad-fondo.webp"
        srcset="/img/nodico/comunidad-fondo-640.webp 640w, /img/nodico/comunidad-fondo-1280.webp 1280w, /img/nodico/comunidad-fondo.webp 1920w"
        sizes="100vw"
        alt=""
        aria-hidden="true"
        width="1920"
        height="960"
        fetchpriority="high"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover"
      />
      <div class="absolute inset-0 -z-10 bg-gradient-to-t from-tinta via-tinta/80 to-tinta/45" aria-hidden="true" />

      <div class="mx-auto w-full max-w-7xl px-5 pb-16 pt-36 sm:px-8 lg:pb-20">
        <p class="etiqueta-tecnica mb-5 flex items-center gap-3 text-nodo-400">
          <span class="h-1.5 w-1.5 rounded-full bg-nodo-400" aria-hidden="true" />
          Comunidad
        </p>

        <h1 class="max-w-[18ch] font-display text-display-lg font-extrabold text-white">
          Aquí pasan cosas todo el mes
        </h1>

        <p class="mt-7 max-w-2xl font-body text-cuerpo-lg text-white/90">
          Talleres, encuentros y una red de emprendedores que ya forman parte de los programas de
          incubación del Instituto Yucateco de Emprendedores.
        </p>
      </div>
    </section>

    <!-- Contenido reciente -->
    <section class="bg-cream py-20 lg:py-24">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading etiqueta="Agenda" titulo="Nuestro contenido más reciente" tamano="lg" />
        </ScrollReveal>

        <!-- CNT-01: sin eventos cargados la sección ya no desaparece; remite
             al calendario, que es la fuente viva. -->
        <ScrollReveal v-if="!eventos.length" class="mt-12">
          <div class="rounded-3xl bg-white p-10 text-center shadow-sombra-sm ring-1 ring-dark/[.07]">
            <p class="font-body text-cuerpo-lg text-dark/70">
              Todavía no hay actividades cargadas en la agenda.
            </p>
            <p class="mt-3 font-body text-cuerpo text-dark/55">
              Los talleres del mes están siempre al día en el calendario, aquí abajo.
            </p>
            <Boton href="#talleres" variante="oscuro" class="mt-8" flecha>
              Ver los talleres del mes
            </Boton>
          </div>
        </ScrollReveal>

        <ScrollReveal v-else :stagger="70" class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <article
            v-for="evento in eventos"
            :key="evento.id"
            class="flex h-full flex-col overflow-hidden rounded-3xl bg-white shadow-sombra-sm ring-1 ring-dark/[.07]
                   transition-all duration-300 ease-salida hover:-translate-y-1 hover:shadow-sombra"
          >
            <img
              v-if="evento.imagen"
              :src="evento.imagen"
              :alt="evento.titulo"
              width="600"
              height="400"
              loading="lazy"
              decoding="async"
              class="aspect-[3/2] w-full object-cover"
            />
            <div class="flex flex-1 flex-col p-6">
              <time v-if="evento.fecha" :datetime="evento.fecha" class="etiqueta-tecnica text-dark/40">
                {{ fechaLarga(evento.fecha) }}
              </time>
              <h3 class="mt-3 font-display text-lg font-bold leading-snug text-dark">{{ evento.titulo }}</h3>
              <p v-if="evento.descripcion" class="mt-3 flex-1 font-body text-sm leading-relaxed text-dark/65">
                {{ evento.descripcion }}
              </p>
              <p v-if="evento.lugar" class="mt-4 font-body text-sm text-dark/50">{{ evento.lugar }}</p>
            </div>
          </article>
        </ScrollReveal>
      </div>
    </section>

    <!-- Talleres del mes: calendario de Luma integrado -->
    <section id="talleres" class="bg-tinta py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <div class="grid gap-12 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:gap-16">
          <ScrollReveal from="left">
            <div class="lg:sticky lg:top-28">
              <SectionHeading
                etiqueta="Talleres"
                titulo="Conoce los talleres del mes"
                tono="claro"
                tamano="lg"
              />
              <p class="mt-7 font-body text-cuerpo leading-relaxed text-white/65">
                En Nódico creemos que el conocimiento se multiplica cuando se comparte. Nuestros
                talleres están pensados para impulsar tu desarrollo profesional y personal,
                conectándote con expertos y otros emprendedores que, como tú, buscan transformar
                sus ideas en proyectos de impacto.
              </p>
            </div>
          </ScrollReveal>

          <ScrollReveal v-if="lumaEmbed" from="right">
            <div class="overflow-hidden rounded-3xl bg-white/[.04] p-2 ring-1 ring-white/10">
              <iframe
                :src="lumaEmbed"
                title="Calendario de talleres de Nódico"
                loading="lazy"
                class="h-[600px] w-full rounded-2xl border-0 sm:h-[680px]"
                allowfullscreen
              />
            </div>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Emprendedor de la semana (CNT-02: viene de la BD) -->
    <section v-if="destacado" id="emprendedor-semana" class="bg-cream py-20 lg:py-28">
      <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-20">
        <ScrollReveal from="left">
          <div class="rounded-3xl bg-white p-10 shadow-sombra ring-1 ring-dark/[.07]">
            <img
              v-if="destacado.foto"
              :src="destacado.foto"
              :alt="destacado.nombre"
              width="600"
              height="600"
              loading="lazy"
              decoding="async"
              class="mx-auto aspect-square w-full max-w-xs object-contain"
            />
          </div>
        </ScrollReveal>

        <ScrollReveal from="right">
          <SectionHeading etiqueta="Emprendedor de la semana" :titulo="destacado.nombre" tamano="lg" />

          <p v-if="destacado.descripcion" class="mt-7 font-body text-cuerpo-lg leading-relaxed text-dark/70">
            {{ destacado.descripcion }}
          </p>

          <!-- CNT-03: el "Saber más…" se había perdido en la migración. -->
          <Boton v-if="destacado.enlace" :href="destacado.enlace" externo variante="oscuro" class="mt-8" flecha>
            Conocer {{ destacado.nombre }}
          </Boton>
        </ScrollReveal>
      </div>
    </section>

    <!-- Directorio -->
    <section class="bg-cream-50 py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
            etiqueta="Directorio"
            titulo="Conoce a la comunidad"
            align="center"
            tamano="lg"
            descripcion="Emprendedores y empresas que forman parte o han egresado de nuestros programas de incubación del IYEM."
          />
        </ScrollReveal>

        <ScrollReveal :stagger="70" as="ul" class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <li v-for="negocio in directorio" :key="negocio.nombre">
            <a
              :href="`https://www.instagram.com/${negocio.instagram}`"
              target="_blank"
              rel="noopener noreferrer"
              class="group block h-full overflow-hidden rounded-3xl bg-white shadow-sombra-sm ring-1 ring-dark/[.07]
                     transition-all duration-300 ease-salida hover:-translate-y-1 hover:shadow-sombra"
            >
              <img
                :src="negocio.foto"
                :alt="`Logotipo de ${negocio.nombre}`"
                width="500"
                height="500"
                loading="lazy"
                decoding="async"
                class="aspect-square w-full object-cover"
              />
              <div class="flex items-center justify-between gap-3 p-5">
                <div>
                  <h3 class="font-display text-base font-bold text-dark">{{ negocio.nombre }}</h3>
                  <p class="mt-0.5 font-body text-sm text-dark/50">@{{ negocio.instagram }}</p>
                </div>
                <Instagram class="h-5 w-5 shrink-0 text-dark/30 transition group-hover:text-dark" aria-hidden="true" />
              </div>
            </a>
          </li>
        </ScrollReveal>
      </div>
    </section>

    <!-- Instagram -->
    <InstagramSection :handle="nodico.instagram" :publicaciones="instagramPosts" tono="oscuro" />

    <!-- Teaser de salones -->
    <section class="bg-nodo-400 py-16 lg:py-20">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <div class="flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 class="max-w-2xl font-display text-display-md font-extrabold text-dark">
              ¿Organizas un evento?
            </h2>
            <p class="mt-4 max-w-lg font-body text-cuerpo-lg text-dark/70">
              Nuestros salones tienen capacidad para 120 personas, con proyector, sonido y mobiliario incluido.
            </p>
          </div>

          <Boton :href="route('eventos')" variante="secundario" tamano="lg" flecha class="shrink-0">
            Ver los salones
          </Boton>
        </div>
      </div>
    </section>

    <ContactSection />
  </PublicLayout>
</template>
