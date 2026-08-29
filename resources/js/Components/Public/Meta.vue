<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

/**
 * SEO-01 / SEO-04 — un solo sitio donde se emite todo el juego de metadatos:
 * título, descripción, canónica, Open Graph, Twitter Card y datos estructurados.
 *
 * El sufijo del título lo pone Inertia (`title` en app.js), así que aquí solo
 * va el nombre de la página.
 */
const props = withDefaults(defineProps<{
  titulo: string
  descripcion: string
  /** Nombre del archivo en /img/og/ sin extensión. */
  imagen?: string
  /** JSON-LD adicional propio de la página (SEO-03). */
  datosEstructurados?: Record<string, unknown> | Record<string, unknown>[]
}>(), {
  imagen: 'home',
})

const page = usePage()
const seo = computed(() => (page.props.seo ?? {}) as any)

const urlImagen = computed(() => `${seo.value.origen ?? ''}/img/og/${props.imagen}.jpg`)

/** SEO-03 — la ficha del negocio va en todas las páginas. */
const negocio = computed(() => seo.value.negocio ?? null)

const bloques = computed(() => {
  const extra = props.datosEstructurados
    ? (Array.isArray(props.datosEstructurados) ? props.datosEstructurados : [props.datosEstructurados])
    : []

  return [negocio.value, ...extra].filter(Boolean)
})
</script>

<template>
  <Head :title="titulo">
    <meta name="description" :content="descripcion" />
    <link rel="canonical" :href="seo.canonica" />

    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="Nódico" />
    <meta property="og:locale" content="es_MX" />
    <meta property="og:title" :content="`${titulo} — Nódico`" />
    <meta property="og:description" :content="descripcion" />
    <meta property="og:url" :content="seo.canonica" />
    <meta property="og:image" :content="urlImagen" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" :content="`${titulo} — Nódico`" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" :content="`${titulo} — Nódico`" />
    <meta name="twitter:description" :content="descripcion" />
    <meta name="twitter:image" :content="urlImagen" />

    <component
      :is="'script'"
      v-for="(bloque, i) in bloques"
      :key="i"
      type="application/ld+json"
    >{{ JSON.stringify(bloque) }}</component>
  </Head>
</template>
