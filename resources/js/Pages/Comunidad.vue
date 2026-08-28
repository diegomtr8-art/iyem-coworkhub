<script setup lang="ts">
import ContactSection from '@/Components/Public/ContactSection.vue'
import ScrollReveal from '@/Components/Public/ScrollReveal.vue'
import SectionHeading from '@/Components/Public/SectionHeading.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
  proximos?: any[]
  pasados?: any[]
  salon?: any | null
  lumaEmbed?: string
}>()

const eventos = computed(() => {
  const lista = [...(props.proximos ?? []), ...(props.pasados ?? [])]
  return lista.slice(0, 6)
})

const directorio = [
  { nombre: 'Ahimsa Daram', foto: '/img/nodico/dir-ahimsa-daram.jpg', instagram: 'ahimsadaram' },
  { nombre: 'Zentto', foto: '/img/nodico/dir-zentto.jpg', instagram: 'zentto.mid' },
  { nombre: 'SaboReli', foto: '/img/nodico/dir-saboreli.webp', instagram: 'saborelimx' },
  { nombre: 'Kinimitas', foto: '/img/nodico/dir-kinimitas.png', instagram: 'kinimitas' },
]

const fechaLarga = (valor: string) =>
  new Date(valor).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })

const fichaSalon = computed(() => {
  const s = props.salon
  return [
    ['Medidas', s?.medidas ?? '15x14 metros'],
    ['Costo por hora', `$${Number(s?.precio_hora ?? 600).toLocaleString('es-MX')} mxn`],
    ['Capacidad', `${s?.capacidad ?? 120} personas`],
    ['Escuela', `${s?.cap_escuela ?? 54} personas`],
    ['Mesas de trabajo', `${s?.cap_mesas ?? 70} personas`],
    ['Herradura', `${s?.cap_herradura ?? 45} personas`],
    ['Auditorio', `${s?.cap_auditorio ?? 120} personas`],
  ]
})
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
    <!-- Encabezado + contenido reciente -->
    <section class="relative isolate overflow-hidden bg-nodo-400 pt-32 pb-16 lg:pt-40 lg:pb-20">
      <img
        src="/img/nodico/comunidad-fondo.webp"
        alt=""
        aria-hidden="true"
        class="absolute inset-0 -z-10 h-full w-full object-cover opacity-25"
      />

      <div class="mx-auto max-w-4xl px-5 text-center sm:px-8">
        <h1 class="font-display text-4xl font-extrabold leading-[1.05] tracking-tight text-dark sm:text-5xl lg:text-6xl">
          Nuestro contenido más reciente
        </h1>
        <p class="mt-6 font-body text-base text-dark/80 sm:text-lg">
          Conozca las novedades de nuestra comunidad.
        </p>
      </div>
    </section>

    <section v-if="eventos.length" class="bg-cream py-20 lg:py-24">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <div class="grid gap-7 sm:grid-cols-2 lg:grid-cols-3">
          <ScrollReveal v-for="(evento, i) in eventos" :key="evento.id" :delay="i * 70" class="h-full">
            <article class="flex h-full flex-col overflow-hidden rounded-3xl bg-white shadow-sm">
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
                <time v-if="evento.fecha" :datetime="evento.fecha" class="font-body text-xs uppercase tracking-widest text-dark/50">
                  {{ fechaLarga(evento.fecha) }}
                </time>
                <h2 class="mt-3 font-display text-lg font-bold leading-snug text-dark">{{ evento.titulo }}</h2>
                <p v-if="evento.descripcion" class="mt-3 flex-1 font-body text-sm leading-relaxed text-dark/70">
                  {{ evento.descripcion }}
                </p>
                <p v-if="evento.lugar" class="mt-4 font-body text-sm text-dark/60">{{ evento.lugar }}</p>
              </div>
            </article>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Talleres del mes -->
    <section class="bg-white py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
            titulo="Conoce los Talleres del mes"
            align="center"
            descripcion="En Nódico creemos que el conocimiento se multiplica cuando se comparte. Nuestros talleres están pensados para impulsar tu desarrollo profesional y personal, conectándote con expertos y otros emprendedores que, como tú, buscan transformar sus ideas en proyectos de impacto. Aquí encontrarás un espacio de aprendizaje dinámico donde la creatividad, la innovación y la colaboración se convierten en herramientas clave para crecer."
          />
        </ScrollReveal>

        <ScrollReveal v-if="lumaEmbed" class="mx-auto mt-14 max-w-4xl">
          <div class="overflow-hidden rounded-4xl border border-dark/10 bg-dark">
            <iframe
              :src="lumaEmbed"
              title="Calendario de talleres de Nódico"
              loading="lazy"
              class="h-[640px] w-full border-0 sm:h-[720px]"
              allowfullscreen
            />
          </div>
        </ScrollReveal>
      </div>
    </section>

    <!-- Emprendedor de la semana -->
    <section id="emprendedor-semana" class="bg-dark py-20 lg:py-28">
      <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:gap-16">
        <ScrollReveal from="left">
          <img
            src="/img/nodico/emprendedor-semana-salabtun.webp"
            alt="Salabtún, sal artesanal de las charcas mayas de Celestún"
            width="800"
            height="800"
            loading="lazy"
            decoding="async"
            class="aspect-square w-full rounded-4xl bg-white/5 object-contain p-8"
          />
        </ScrollReveal>

        <ScrollReveal from="right">
          <SectionHeading titulo="Emprendedor de la semana" tono="claro" />
          <p class="mt-6 font-body text-base leading-relaxed text-white/75 sm:text-lg">
            Salabtún es una sal artesanal única de las charcas mayas de Celestún, Yucatán. Cosechada
            desde hace más de 600 años, combina tradición y naturaleza en un proceso heredado de
            generación en generación. Durante la temporada seca, los salineros recolectan delicadas
            hojuelas de sal, mientras los flamencos rosados ayudan a mantener limpio este ecosistema
            sagrado. El resultado es una mezcla orgánica que enriquece la gastronomía yucateca y
            conserva viva la herencia maya.
          </p>
        </ScrollReveal>
      </div>
    </section>

    <!-- Directorio -->
    <section class="bg-cream py-20 lg:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <ScrollReveal>
          <SectionHeading
            titulo="Conoce nuestro directorio"
            align="center"
            descripcion="Explora a los emprendedores y empresas que forman parte o han egresado de nuestros programas de incubación del IYEM. Conecta con una comunidad innovadora que impulsa el crecimiento y la colaboración dentro de Nódico."
          />
        </ScrollReveal>

        <ul class="mt-14 grid gap-7 sm:grid-cols-2 lg:grid-cols-4">
          <ScrollReveal v-for="(negocio, i) in directorio" :key="negocio.nombre" :delay="i * 70" as="li">
            <a
              :href="`https://www.instagram.com/${negocio.instagram}`"
              target="_blank"
              rel="noopener noreferrer"
              class="group block h-full overflow-hidden rounded-3xl bg-white shadow-sm transition
                     hover:-translate-y-1 hover:shadow-xl
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
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
              <div class="p-5 text-center">
                <h3 class="font-display text-base font-bold text-dark">{{ negocio.nombre }}</h3>
                <p class="mt-1 font-body text-sm text-dark/55">@{{ negocio.instagram }}</p>
              </div>
            </a>
          </ScrollReveal>
        </ul>
      </div>
    </section>

    <!-- Teaser de salones -->
    <section class="bg-white py-20 lg:py-28">
      <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:gap-16">
        <ScrollReveal from="left">
          <SectionHeading
            titulo="Conoce nuestros Salones para eventos"
            descripcion="Nuestros espacios están listos para tus talleres, conferencias o reuniones. Modernos, cómodos y equipados para que cada idea cobre vida."
          />

          <dl class="mt-8 divide-y divide-dark/10 border-y border-dark/10">
            <div v-for="[etiqueta, valor] in fichaSalon" :key="etiqueta" class="flex justify-between gap-6 py-3">
              <dt class="font-body text-sm text-dark/60">{{ etiqueta }}</dt>
              <dd class="font-body text-sm font-semibold text-dark">{{ valor }}</dd>
            </div>
          </dl>

          <Link
            :href="route('eventos')"
            class="mt-8 inline-flex items-center gap-2 rounded-lg border-2 border-dark bg-nodo-400 px-7 py-3
                   font-display text-sm font-bold text-dark transition hover:brightness-95
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
          >
            Ver los salones <span aria-hidden="true">→</span>
          </Link>
        </ScrollReveal>

        <ScrollReveal from="right">
          <img
            src="/img/nodico/teaser-salones.png"
            alt="Salón de eventos de Nódico montado para una conferencia"
            width="1000"
            height="750"
            loading="lazy"
            decoding="async"
            class="aspect-[4/3] w-full rounded-4xl object-cover"
          />
        </ScrollReveal>
      </div>
    </section>

    <ContactSection />
  </PublicLayout>
</template>
