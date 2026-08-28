<script setup lang="ts">
import Boton from '@/Components/Public/Boton.vue'
import { Play, X } from 'lucide-vue-next'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

const props = withDefaults(defineProps<{
  videoId?: string
  poster?: string
  posterAlt?: string
}>(), {
  videoId: 'Ml4sprGUqzc',
  poster: '/img/nodico/hero-inicio.webp',
  posterAlt: 'Área de coworking de Nódico en Mérida',
})

const raiz = ref<HTMLElement | null>(null)
const modal = ref<HTMLElement | null>(null)
const botonCerrar = ref<HTMLElement | null>(null)

/** iOS en iPhone bloquea el autoplay en iframes de forma inconsistente. */
const esIPhone = ref(false)
const sinMovimiento = ref(false)
/** Patrón fachada: el iframe de fondo no se monta hasta entrar en viewport. */
const cargarFondo = ref(false)
const modalAbierto = ref(false)

let observador: IntersectionObserver | undefined
let focoPrevio: HTMLElement | null = null

const fondoSrc = computed(() =>
  `https://www.youtube-nocookie.com/embed/${props.videoId}` +
  `?autoplay=1&mute=1&loop=1&playlist=${props.videoId}` +
  '&controls=0&modestbranding=1&rel=0&playsinline=1&disablekb=1',
)

const modalSrc = computed(() =>
  `https://www.youtube-nocookie.com/embed/${props.videoId}` +
  '?autoplay=1&rel=0&modestbranding=1&playsinline=1',
)

/** El video de fondo solo corre donde es fiable y si no se pidió menos movimiento. */
const usaVideoFondo = computed(() => !esIPhone.value && !sinMovimiento.value)

function abrirModal() {
  focoPrevio = document.activeElement as HTMLElement
  modalAbierto.value = true
  document.body.style.overflow = 'hidden'
  nextTick(() => botonCerrar.value?.focus())
}

function cerrarModal() {
  modalAbierto.value = false
  document.body.style.overflow = ''
  focoPrevio?.focus()
}

/** Esc cierra; Tab queda atrapado dentro del diálogo. */
function alPulsarTecla(e: KeyboardEvent) {
  if (!modalAbierto.value) return

  if (e.key === 'Escape') {
    e.preventDefault()
    cerrarModal()
    return
  }

  if (e.key !== 'Tab' || !modal.value) return

  const enfocables = modal.value.querySelectorAll<HTMLElement>(
    'button, [href], iframe, input, select, textarea, [tabindex]:not([tabindex="-1"])',
  )
  if (!enfocables.length) return

  const primero = enfocables[0]
  const ultimo = enfocables[enfocables.length - 1]

  if (e.shiftKey && document.activeElement === primero) {
    e.preventDefault()
    ultimo.focus()
  } else if (!e.shiftKey && document.activeElement === ultimo) {
    e.preventDefault()
    primero.focus()
  }
}

onMounted(() => {
  esIPhone.value = /iPhone|iPod/.test(navigator.userAgent)
  sinMovimiento.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches

  document.addEventListener('keydown', alPulsarTecla)

  if (!usaVideoFondo.value || typeof IntersectionObserver === 'undefined') return

  observador = new IntersectionObserver(
    (entradas) => {
      entradas.forEach((entrada) => {
        if (!entrada.isIntersecting) return
        cargarFondo.value = true
        observador?.disconnect()
      })
    },
    { rootMargin: '200px' },
  )
  if (raiz.value) observador.observe(raiz.value)
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', alPulsarTecla)
  observador?.disconnect()
  document.body.style.overflow = ''
})
</script>

<template>
  <section ref="raiz" class="relative isolate min-h-[100svh] overflow-hidden bg-tinta">
    <!-- Capa de fondo: poster siempre, iframe encima cuando procede -->
    <img
      :src="poster"
      :alt="posterAlt"
      width="1079"
      height="1920"
      fetchpriority="high"
      decoding="async"
      class="absolute inset-0 -z-20 h-full w-full object-cover object-[center_62%]"
    />

    <div v-if="usaVideoFondo && cargarFondo" class="absolute inset-0 -z-20 overflow-hidden" aria-hidden="true">
      <!-- 177.78vh = 16/9 de la altura: cubre el viewport sin deformar el video. -->
      <iframe
        :src="fondoSrc"
        title="Video institucional de Nódico, reproducción de fondo sin sonido"
        tabindex="-1"
        allow="autoplay; encrypted-media"
        class="pointer-events-none absolute left-1/2 top-1/2 h-[100vh] w-[177.78vh] min-h-[56.25vw] min-w-[100vw]
               -translate-x-1/2 -translate-y-1/2 scale-[1.35] border-0"
      />
    </div>

    <!-- Velo para que el titular tenga contraste sobre cualquier fotograma -->
    <div
      class="absolute inset-0 -z-10 bg-gradient-to-b from-tinta/75 via-tinta/55 to-tinta/95"
      aria-hidden="true"
    />

    <div class="mx-auto flex min-h-[100svh] max-w-7xl flex-col justify-center px-5 pb-20 pt-28 sm:px-8">
      <p class="etiqueta-tecnica mb-6 text-nodo-400">Coworking · Mérida, Yucatán</p>

      <h1 class="max-w-4xl font-display text-display-xl font-extrabold text-white">
        Bienvenidos al lugar
        <span class="mt-2 block text-nodo-400">donde el trabajo es un pretexto para crear</span>
      </h1>

      <p class="mt-7 max-w-xl font-body text-cuerpo-lg text-white/75">
        Un espacio del Instituto Yucateco de Emprendedores para quienes están construyendo
        algo propio: comunidad, salas de trabajo y contenido para crecer.
      </p>

      <div class="mt-9 flex flex-wrap items-center gap-4">
        <Boton :href="route('membresias')" variante="primario" tamano="lg" flecha>
          Conocer membresías
        </Boton>
        <Boton :href="route('nosotros')" variante="claro" tamano="lg">
          Conocer más
        </Boton>

        <button
          type="button"
          class="group inline-flex min-h-[56px] items-center gap-3 rounded-lg px-4 font-display text-base
                 font-bold text-white transition hover:text-nodo-400"
          @click="abrirModal"
        >
          <span
            class="flex h-12 w-12 items-center justify-center rounded-full border-2 border-white/40
                   transition group-hover:border-nodo-400 group-hover:bg-nodo-400 group-hover:text-dark"
          >
            <Play class="ml-0.5 h-5 w-5" aria-hidden="true" />
          </span>
          Ver el video
        </button>
      </div>
    </div>

    <!-- Modal: mismo video con sonido y controles -->
    <Teleport to="body">
      <div
        v-if="modalAbierto"
        ref="modal"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-tinta/95 p-4 sm:p-8"
        role="dialog"
        aria-modal="true"
        aria-label="Video institucional de Nódico"
        @click.self="cerrarModal"
      >
        <button
          ref="botonCerrar"
          type="button"
          class="absolute right-4 top-4 flex h-12 w-12 items-center justify-center rounded-full
                 border-2 border-white/30 text-white transition hover:border-nodo-400 hover:text-nodo-400"
          aria-label="Cerrar el video"
          @click="cerrarModal"
        >
          <X class="h-6 w-6" aria-hidden="true" />
        </button>

        <div class="aspect-video w-full max-w-5xl overflow-hidden rounded-2xl border-2 border-white/15 bg-black">
          <iframe
            :src="modalSrc"
            title="Video institucional de Nódico"
            allow="autoplay; encrypted-media; fullscreen"
            allowfullscreen
            class="h-full w-full border-0"
          />
        </div>
      </div>
    </Teleport>
  </section>
</template>
