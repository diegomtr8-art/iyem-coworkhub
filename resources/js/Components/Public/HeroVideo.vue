<script setup lang="ts">
import Boton from '@/Components/Public/Boton.vue'
import { Clock, MapPin, Pause, Phone, Play, Volume2, VolumeX, X } from 'lucide-vue-next'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

const props = withDefaults(defineProps<{
  videoId?: string
  poster?: string
  posterAlt?: string
  direccion?: string
  horarios?: string
  telefono?: string
  mapsUrl?: string
}>(), {
  videoId: 'Ml4sprGUqzc',
  poster: '/img/nodico/hero-inicio.webp',
  posterAlt: 'Área de coworking de Nódico en Mérida',
})

const raiz = ref<HTMLElement | null>(null)
const marco = ref<HTMLIFrameElement | null>(null)
const modal = ref<HTMLElement | null>(null)
const botonCerrar = ref<HTMLElement | null>(null)

const esIPhone = ref(false)
const sinMovimiento = ref(false)
const cargarFondo = ref(false)
const modalAbierto = ref(false)
const conSonido = ref(false)
const enPausa = ref(false)
/** El iframe se revela solo cuando ya pinta video, no mientras almacena en bufer. */
const videoVisible = ref(false)

let observador: IntersectionObserver | undefined
let focoPrevio: HTMLElement | null = null

// enablejsapi permite activar el sonido y pausar por postMessage.
const fondoSrc = computed(() =>
  `https://www.youtube-nocookie.com/embed/${props.videoId}` +
  `?autoplay=1&mute=1&loop=1&playlist=${props.videoId}` +
  '&controls=0&modestbranding=1&rel=0&playsinline=1&disablekb=1&enablejsapi=1',
)

const modalSrc = computed(() =>
  `https://www.youtube-nocookie.com/embed/${props.videoId}` +
  '?autoplay=1&rel=0&modestbranding=1&playsinline=1',
)

const usaVideoFondo = computed(() => !esIPhone.value && !sinMovimiento.value)

/** Los navegadores solo permiten quitar el silencio a partir de un gesto del usuario. */
function ordenar(func: string, args: unknown[] = []) {
  marco.value?.contentWindow?.postMessage(
    JSON.stringify({ event: 'command', func, args }),
    'https://www.youtube-nocookie.com',
  )
}

/**
 * YouTube pinta negro mientras almacena en bufer, y como el iframe va encima
 * del poster el hero se veia en negro al entrar. Se mantiene oculto hasta que
 * carga y se le da un margen para que empiece a reproducir.
 */
function alCargarVideo() {
  window.setTimeout(() => (videoVisible.value = true), 900)
}

function alternarSonido() {
  conSonido.value = !conSonido.value
  if (conSonido.value) {
    ordenar('unMute')
    ordenar('setVolume', [60])
  } else {
    ordenar('mute')
  }
}

function alternarPausa() {
  enPausa.value = !enPausa.value
  ordenar(enPausa.value ? 'pauseVideo' : 'playVideo')
}

function abrirModal() {
  // Si el fondo llevaba sonido se silencia: dos pistas a la vez es un desastre.
  if (conSonido.value) {
    conSonido.value = false
    ordenar('mute')
  }
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
  <section ref="raiz" class="relative isolate flex min-h-[100svh] flex-col overflow-hidden bg-tinta">
    <img
      :src="poster"
      :alt="posterAlt"
      width="1079"
      height="1920"
      fetchpriority="high"
      decoding="async"
      class="absolute inset-0 -z-20 h-full w-full object-cover object-[center_58%]"
    />

    <div v-if="usaVideoFondo && cargarFondo" class="absolute inset-0 -z-20 overflow-hidden" aria-hidden="true">
      <iframe
        ref="marco"
        :src="fondoSrc"
        title="Video institucional de Nódico"
        tabindex="-1"
        allow="autoplay; encrypted-media"
        class="pointer-events-none absolute left-1/2 top-1/2 h-[100vh] w-[177.78vh] min-h-[56.25vw] min-w-[100vw]
               -translate-x-1/2 -translate-y-1/2 scale-[1.35] border-0 transition-opacity duration-700 ease-suave"
        :class="videoVisible ? 'opacity-100' : 'opacity-0'"
        @load="alCargarVideo"
      />
    </div>

    <!-- Velo direccional: oscuro abajo, donde va el texto; arriba deja ver el espacio -->
    <div
      class="absolute inset-0 -z-10 bg-gradient-to-t from-tinta via-tinta/55 to-tinta/15"
      aria-hidden="true"
    />

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col justify-end px-5 pb-8 pt-32 sm:px-8">
      <p class="font-display text-lg font-bold text-nodo-400 sm:text-xl">Bienvenidos al lugar</p>

      <h1 class="mt-4 max-w-[17ch] font-display text-display-xl font-extrabold text-white">
        Donde el trabajo es un pretexto para crear
      </h1>

      <p class="mt-7 max-w-lg font-body text-cuerpo-lg text-white/80">
        El coworking del Instituto Yucateco de Emprendedores en Mérida.
      </p>

      <div class="mt-9 flex flex-wrap items-center gap-3">
        <Boton :href="route('membresias')" variante="primario" tamano="lg" flecha>
          Conocer membresías
        </Boton>
        <Boton :href="route('nosotros')" variante="claro" tamano="lg">
          Conocer más
        </Boton>

        <button
          type="button"
          class="group inline-flex min-h-[56px] items-center gap-3 rounded-full px-4 font-body text-sm
                 font-medium text-white/85 transition hover:text-nodo-400"
          @click="abrirModal"
        >
          <span
            class="flex h-11 w-11 items-center justify-center rounded-full border border-white/35
                   transition group-hover:border-nodo-400 group-hover:bg-nodo-400 group-hover:text-tinta"
          >
            <Play class="ml-0.5 h-4 w-4" aria-hidden="true" />
          </span>
          Ver el video completo
        </button>
      </div>

      <!-- Riel inferior: datos reales del lugar + control del video de fondo -->
      <div
        class="mt-12 flex flex-col gap-4 border-t border-white/15 pt-4
               lg:flex-row lg:flex-wrap lg:items-center lg:justify-between lg:gap-6"
      >
        <ul class="flex flex-col gap-1 lg:flex-row lg:flex-wrap lg:items-center lg:gap-8">
          <li v-if="direccion">
            <a
              :href="mapsUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="flex min-h-[44px] items-center gap-2.5 font-body text-sm text-white/60 transition hover:text-nodo-400"
            >
              <MapPin class="h-4 w-4 shrink-0" aria-hidden="true" />
              {{ direccion }}
            </a>
          </li>
          <li v-if="horarios" class="flex min-h-[44px] items-center gap-2.5 font-body text-sm text-white/60">
            <Clock class="h-4 w-4 shrink-0" aria-hidden="true" />
            {{ horarios }}
          </li>
          <li v-if="telefono">
            <a
              :href="`tel:${telefono.replace(/\s/g, '')}`"
              class="flex min-h-[44px] items-center gap-2.5 font-body text-sm text-white/60 transition hover:text-nodo-400"
            >
              <Phone class="h-4 w-4 shrink-0" aria-hidden="true" />
              {{ telefono }}
            </a>
          </li>
        </ul>

        <div v-if="usaVideoFondo && cargarFondo" class="flex shrink-0 items-center gap-2">
          <button
            type="button"
            class="flex h-11 w-11 items-center justify-center rounded-full border border-white/25 text-white
                   transition hover:border-nodo-400 hover:text-nodo-400"
            :aria-label="enPausa ? 'Reanudar el video de fondo' : 'Pausar el video de fondo'"
            @click="alternarPausa"
          >
            <Play v-if="enPausa" class="ml-0.5 h-4 w-4" aria-hidden="true" />
            <Pause v-else class="h-4 w-4" aria-hidden="true" />
          </button>

          <!--
            El navegador prohíbe arrancar con sonido: el video empieza silenciado
            y este botón lo activa, porque el clic ya cuenta como gesto del usuario.
          -->
          <button
            type="button"
            class="flex min-h-[44px] items-center gap-2.5 rounded-full border px-4 font-body text-sm transition"
            :class="conSonido
              ? 'border-nodo-400 bg-nodo-400 font-medium text-tinta'
              : 'border-white/25 text-white/80 hover:border-nodo-400 hover:text-nodo-400'"
            :aria-pressed="conSonido"
            @click="alternarSonido"
          >
            <Volume2 v-if="conSonido" class="h-4 w-4" aria-hidden="true" />
            <VolumeX v-else class="h-4 w-4" aria-hidden="true" />
            {{ conSonido ? 'Sonido activado' : 'Activar sonido' }}
          </button>
        </div>
      </div>
    </div>

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
                 border border-white/30 text-white transition hover:border-nodo-400 hover:text-nodo-400"
          aria-label="Cerrar el video"
          @click="cerrarModal"
        >
          <X class="h-6 w-6" aria-hidden="true" />
        </button>

        <div class="aspect-video w-full max-w-5xl overflow-hidden rounded-2xl bg-black">
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
