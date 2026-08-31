<script setup lang="ts">
import { Instagram } from 'lucide-vue-next'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

/**
 * Feed de Instagram como el de Herencia Viva / Odoo: un solo **embed del perfil**
 * (`instagram.com/{cuenta}/embed`), que Instagram sirve con la cuadrícula de las
 * últimas publicaciones. Decisión de Nódico (2026-08-31): se prefiere este embed
 * al muro de publicaciones sueltas.
 *
 * Se carga de forma diferida (IntersectionObserver): la sección va al final de
 * cada página, así que el embed —~1 MB de terceros— no compite con la carga
 * inicial y solo se pide cuando alguien se acerca. La altura queda reservada.
 */
const props = withDefaults(defineProps<{
  handle?: string
  tono?: 'claro' | 'oscuro'
}>(), {
  handle: 'nodicomx',
  tono: 'claro',
})

const perfilUrl = computed(() => `https://www.instagram.com/${props.handle}`)
const embedUrl = computed(() => `https://www.instagram.com/${props.handle}/embed`)

const cerca = ref(false)
const marco = ref<HTMLElement | null>(null)
let observador: IntersectionObserver | null = null

onMounted(() => {
  if (!marco.value) return
  if (typeof IntersectionObserver === 'undefined') {
    cerca.value = true
    return
  }
  observador = new IntersectionObserver((entradas) => {
    if (entradas.some((e) => e.isIntersecting)) {
      cerca.value = true
      observador?.disconnect()
    }
  }, { rootMargin: '400px' })
  observador.observe(marco.value)
})

onBeforeUnmount(() => observador?.disconnect())
</script>

<template>
  <section
    class="py-20 lg:py-28"
    :class="tono === 'oscuro' ? 'bg-tinta' : 'bg-cream'"
  >
    <div class="mx-auto max-w-3xl px-5 sm:px-8">
      <!-- Cabecera -->
      <div class="mb-10 text-center">
        <p
          class="etiqueta-tecnica mb-5 flex items-center justify-center gap-3"
          :class="tono === 'oscuro' ? 'text-nodo-400' : 'text-dark/70'"
        >
          <span class="h-1.5 w-1.5 rounded-full bg-nodo-400" aria-hidden="true" />
          Instagram
        </p>

        <h2
          class="font-display text-display-md font-extrabold"
          :class="tono === 'oscuro' ? 'text-white' : 'text-dark'"
        >
          Lo que pasa en Nódico
        </h2>

        <a
          :href="perfilUrl" target="_blank" rel="noopener noreferrer"
          class="mt-4 inline-flex items-center gap-2 font-display text-sm font-bold"
          :class="tono === 'oscuro' ? 'text-nodo-400' : 'text-dark'"
        >
          <Instagram class="h-4 w-4" aria-hidden="true" /> @{{ handle }}
        </a>
      </div>

      <!-- Embed del perfil, diferido. La altura queda reservada. -->
      <div
        ref="marco"
        class="mx-auto overflow-hidden rounded-2xl bg-white shadow-sombra-sm ring-1 ring-dark/[.07]"
      >
        <iframe
          v-if="cerca"
          :src="embedUrl"
          :title="`Publicaciones de @${handle} en Instagram`"
          scrolling="no"
          loading="lazy"
          class="h-[720px] w-full border-0"
        />
        <div v-else class="h-[720px] w-full" aria-hidden="true" />
      </div>

      <div class="mt-8 text-center">
        <a
          :href="perfilUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex min-h-[48px] items-center gap-2.5 rounded-xl bg-nodo-400 px-6 py-3
                 font-display text-sm font-bold text-dark transition hover:-translate-y-0.5 hover:shadow-sombra"
        >
          <Instagram class="h-4 w-4" aria-hidden="true" />
          Síguenos en Instagram
        </a>
      </div>
    </div>
  </section>
</template>
