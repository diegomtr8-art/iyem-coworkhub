<script setup lang="ts">
import { Instagram } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = withDefaults(defineProps<{
  handle?: string
  /** Publicaciones de respaldo si el embed de perfil no carga. */
  publicaciones?: string[]
  tono?: 'claro' | 'oscuro'
}>(), {
  handle: 'nodicomx',
  tono: 'claro',
})

const marco = ref<HTMLIFrameElement | null>(null)
const cargado = ref(false)
const fallo = ref(false)
let temporizador: number | undefined

const perfilUrl = `https://www.instagram.com/${props.handle}`

/**
 * Embed de perfil completo (el mismo que usa el snippet de Odoo). No está
 * documentado por Meta, así que si no responde en 8 s se muestra el respaldo
 * y la sección nunca queda en blanco.
 */
onMounted(() => {
  temporizador = window.setTimeout(() => {
    if (!cargado.value) fallo.value = true
  }, 8000)
})

onBeforeUnmount(() => {
  if (temporizador) window.clearTimeout(temporizador)
})

function alCargar() {
  cargado.value = true
  if (temporizador) window.clearTimeout(temporizador)
}
</script>

<template>
  <section
    class="py-20 lg:py-28"
    :class="tono === 'oscuro' ? 'bg-tinta' : 'bg-cream'"
  >
    <div class="mx-auto max-w-5xl px-5 sm:px-8">
      <!-- Cabecera de marca -->
      <div class="mb-10 flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p
            class="etiqueta-tecnica mb-4 flex items-center gap-3"
            :class="tono === 'oscuro' ? 'text-nodo-400' : 'text-dark/45'"
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

          <p
            class="mt-4 max-w-lg font-body text-cuerpo-lg"
            :class="tono === 'oscuro' ? 'text-white/65' : 'text-dark/65'"
          >
            Talleres, miembros nuevos y la vida diaria del espacio, día con día.
          </p>
        </div>

        <a
          :href="perfilUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex min-h-[48px] shrink-0 items-center gap-2.5 rounded-xl bg-nodo-400 px-6 py-3
                 font-display text-sm font-bold text-dark transition-all duration-300 ease-salida
                 hover:-translate-y-0.5 hover:shadow-sombra"
        >
          <Instagram class="h-4 w-4" aria-hidden="true" />
          @{{ handle }}
        </a>
      </div>

      <!-- Embed de perfil -->
      <div
        v-show="!fallo"
        class="overflow-hidden rounded-3xl bg-white shadow-sombra"
      >
        <iframe
          ref="marco"
          :src="`${perfilUrl}/embed`"
          :title="`Publicaciones recientes de @${handle} en Instagram`"
          scrolling="no"
          loading="lazy"
          class="h-[560px] w-full border-0 sm:h-[640px] lg:h-[700px]"
          @load="alCargar"
        />
      </div>

      <!-- Respaldo: si el embed no carga, nunca queda un hueco en blanco -->
      <div v-if="fallo" class="rounded-3xl bg-white p-8 shadow-sombra ring-1 ring-dark/[.07] sm:p-10">
        <div class="flex flex-col items-center gap-6 text-center">
          <img
            src="/img/nodico/logo-nodico-blanco.png"
            alt="Nódico"
            width="180"
            height="58"
            loading="lazy"
            class="h-10 w-auto brightness-0"
          />
          <p class="max-w-md font-body text-dark/70">
            No pudimos cargar el feed de Instagram en este momento.
            Puedes ver todas nuestras publicaciones directamente en el perfil.
          </p>

          <ul v-if="publicaciones?.length" class="grid w-full gap-3 sm:grid-cols-2">
            <li v-for="(post, i) in publicaciones" :key="post">
              <a
                :href="post"
                target="_blank"
                rel="noopener noreferrer"
                class="flex min-h-[48px] items-center justify-center rounded-xl bg-cream px-4 py-3
                       font-body text-sm text-dark transition hover:bg-cream-200"
              >
                Publicación {{ i + 1 }}
              </a>
            </li>
          </ul>

          <a
            :href="perfilUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex min-h-[48px] items-center gap-2.5 rounded-xl bg-nodo-400 px-6 py-3
                   font-display text-sm font-bold text-dark transition hover:-translate-y-0.5 hover:shadow-sombra"
          >
            <Instagram class="h-4 w-4" aria-hidden="true" />
            Ver el perfil
          </a>
        </div>
      </div>
    </div>
  </section>
</template>
