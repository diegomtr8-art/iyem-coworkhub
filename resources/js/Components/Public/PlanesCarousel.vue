<script setup lang="ts">
import { ChevronLeft, ChevronRight, Check, Star } from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = withDefaults(defineProps<{
  planes: any[]
  /** En /membresias se muestran los beneficios completos; en la portada, un resumen. */
  detallado?: boolean
}>(), {
  detallado: false,
})

const pista = ref<HTMLElement | null>(null)
const tarjetas = ref<HTMLElement[]>([])
const indiceActivo = ref(0)
const sinMovimiento = ref(false)

let temporizador: number | undefined
let observadorTamano: ResizeObserver | undefined
let arrastrando = false
let inicioX = 0
let inicioScroll = 0
let recorrido = 0

const total = computed(() => props.planes.length)

const precio = (valor: number) =>
  Number(valor).toLocaleString('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 0 })

const titulo = (plan: any) =>
  (plan.personas ?? 1) > 1 ? `${plan.nombre} (${plan.personas} pax)` : plan.nombre

/** Desplaza para dejar centrada la tarjeta `i`. */
function irA(i: number, suave = true) {
  const contenedor = pista.value
  const tarjeta = tarjetas.value[i]
  if (!contenedor || !tarjeta) return

  const destino = tarjeta.offsetLeft - (contenedor.clientWidth - tarjeta.clientWidth) / 2
  contenedor.scrollTo({
    left: Math.max(0, destino),
    behavior: suave && !sinMovimiento.value ? 'smooth' : 'auto',
  })
  indiceActivo.value = i
}

const anterior = () => irA(Math.max(0, indiceActivo.value - 1))
const siguiente = () => irA(Math.min(total.value - 1, indiceActivo.value + 1))

/** Recalcula qué tarjeta está más cerca del centro del contenedor. */
function alDesplazar() {
  const contenedor = pista.value
  if (!contenedor) return

  const centro = contenedor.scrollLeft + contenedor.clientWidth / 2
  let mejor = 0
  let menorDistancia = Infinity

  tarjetas.value.forEach((tarjeta, i) => {
    if (!tarjeta) return
    const centroTarjeta = tarjeta.offsetLeft + tarjeta.clientWidth / 2
    const distancia = Math.abs(centroTarjeta - centro)
    if (distancia < menorDistancia) {
      menorDistancia = distancia
      mejor = i
    }
  })

  indiceActivo.value = mejor
}

// ── Autoplay ────────────────────────────────────────────────────────
function arrancarAutoplay() {
  if (sinMovimiento.value || total.value < 2 || temporizador) return
  temporizador = window.setInterval(() => {
    const siguienteIndice = indiceActivo.value >= total.value - 1 ? 0 : indiceActivo.value + 1
    irA(siguienteIndice)
  }, 5000)
}

function detenerAutoplay() {
  if (!temporizador) return
  window.clearInterval(temporizador)
  temporizador = undefined
}

// ── Arrastre con ratón ──────────────────────────────────────────────
function alPresionar(e: PointerEvent) {
  if (e.pointerType !== 'mouse' || !pista.value) return
  arrastrando = true
  recorrido = 0
  inicioX = e.clientX
  inicioScroll = pista.value.scrollLeft
  // El snap pelea con el desplazamiento manual; se desactiva mientras se arrastra.
  pista.value.style.scrollSnapType = 'none'
  detenerAutoplay()
}

function alMover(e: PointerEvent) {
  if (!arrastrando || !pista.value) return
  const delta = e.clientX - inicioX
  recorrido = Math.abs(delta)
  pista.value.scrollLeft = inicioScroll - delta
}

function alSoltar() {
  if (!arrastrando || !pista.value) return
  arrastrando = false
  pista.value.style.scrollSnapType = ''
  irA(indiceActivo.value)
}

/** Tras arrastrar, el click sobre el CTA no debe dispararse. */
function alHacerClic(e: MouseEvent) {
  if (recorrido > 6) {
    e.preventDefault()
    e.stopPropagation()
    recorrido = 0
  }
}

/** Centra el plan recomendado sin animacion. */
function centrarDestacado() {
  const destacado = props.planes.findIndex((p) => p.destacado)
  if (destacado > 0) irA(destacado, false)
}

onMounted(() => {
  sinMovimiento.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches

  centrarDestacado()

  // En onMounted las tarjetas aun no tienen su ancho final: faltan las fuentes
  // de marca y las imagenes, asi que el centrado sale descuadrado (FE-03).
  document.fonts?.ready.then(() => {
    if (!arrastrando) centrarDestacado()
  })

  // Y si el ancho de la pista cambia despues (rotacion, carga tardia), se
  // vuelve a centrar la tarjeta que estuviera activa.
  if (typeof ResizeObserver !== 'undefined' && pista.value) {
    let anchoPrevio = pista.value.clientWidth
    observadorTamano = new ResizeObserver(() => {
      const ancho = pista.value?.clientWidth ?? 0
      if (ancho === anchoPrevio || arrastrando) return
      anchoPrevio = ancho
      irA(indiceActivo.value, false)
    })
    observadorTamano.observe(pista.value)
  }

  arrancarAutoplay()
})

onBeforeUnmount(() => {
  detenerAutoplay()
  observadorTamano?.disconnect()
})
</script>

<template>
  <div
    v-if="total"
    class="relative"
    role="region"
    aria-roledescription="carrusel"
    aria-label="Membresías de Nódico"
    @pointerenter="detenerAutoplay"
    @pointerleave="arrancarAutoplay"
    @focusin="detenerAutoplay"
  >
    <!-- Flechas: solo desktop, el móvil usa swipe -->
    <button
      type="button"
      class="absolute -left-2 top-1/2 z-20 hidden h-12 w-12 -translate-y-1/2 items-center justify-center
             rounded-full bg-white text-dark shadow-sombra ring-1 ring-dark/10 transition
             hover:bg-nodo-400 disabled:opacity-25 lg:flex"
      aria-label="Membresía anterior"
      :disabled="indiceActivo === 0"
      @click="anterior"
    >
      <ChevronLeft class="h-6 w-6" aria-hidden="true" />
    </button>

    <button
      type="button"
      class="absolute -right-2 top-1/2 z-20 hidden h-12 w-12 -translate-y-1/2 items-center justify-center
             rounded-full bg-white text-dark shadow-sombra ring-1 ring-dark/10 transition
             hover:bg-nodo-400 disabled:opacity-25 lg:flex"
      aria-label="Membresía siguiente"
      :disabled="indiceActivo === total - 1"
      @click="siguiente"
    >
      <ChevronRight class="h-6 w-6" aria-hidden="true" />
    </button>

    <div
      ref="pista"
      tabindex="0"
      class="sin-scrollbar flex snap-x snap-mandatory gap-5 overflow-x-auto scroll-smooth py-10
             focus-visible:outline-none"
      :class="arrastrando ? 'cursor-grabbing select-none' : 'cursor-grab'"
      @scroll.passive="alDesplazar"
      @pointerdown="alPresionar"
      @pointermove="alMover"
      @pointerup="alSoltar"
      @pointercancel="alSoltar"
      @pointerleave="alSoltar"
      @click.capture="alHacerClic"
      @keydown.left.prevent="anterior"
      @keydown.right.prevent="siguiente"
    >
      <article
        v-for="(plan, i) in planes"
        :key="plan.id ?? plan.nombre"
        :ref="(el) => { if (el) tarjetas[i] = el as HTMLElement }"
        role="group"
        aria-roledescription="diapositiva"
        :aria-label="`${i + 1} de ${total}: ${titulo(plan)}`"
        class="flex shrink-0 snap-center flex-col overflow-hidden rounded-3xl bg-white transition-all duration-300 ease-salida"
        :class="[
          'w-[82%] sm:w-[46%] lg:w-[31.5%]',
          plan.destacado
            ? 'shadow-sombra-lg ring-2 ring-dark lg:-my-5 lg:scale-[1.04]'
            : 'shadow-sombra-sm ring-1 ring-dark/[.08]',
        ]"
      >
        <!-- Franja del color del plan -->
        <div class="h-2 w-full shrink-0" :style="{ backgroundColor: plan.color ?? '#FFE124' }" aria-hidden="true" />

        <div class="flex flex-1 flex-col p-6 sm:p-7">
          <p v-if="plan.destacado" class="mb-4 inline-flex w-fit items-center gap-1.5 rounded-full bg-nodo-400 px-3.5 py-1.5">
            <Star class="h-3.5 w-3.5 fill-dark" aria-hidden="true" />
            <span class="etiqueta-tecnica font-bold text-dark">La más popular</span>
          </p>
          <p v-else class="mb-4 h-[30px]" aria-hidden="true" />

          <h3 class="font-display text-display-sm font-extrabold text-dark">{{ titulo(plan) }}</h3>

          <p class="mt-4 font-display text-5xl font-extrabold leading-none text-dark">
            {{ precio(plan.precio) }}
          </p>
          <p v-if="plan.periodo_label" class="mt-2 font-mono text-etiqueta uppercase text-dark/55">
            {{ plan.periodo_label }}
          </p>

          <p class="mt-5 font-body text-sm leading-relaxed text-dark/70">
            {{ detallado ? plan.descripcion_larga : plan.descripcion_corta }}
          </p>

          <ul v-if="plan.beneficios?.length" class="mt-6 flex-1 space-y-2.5 border-t border-dark/10 pt-6">
            <li v-for="beneficio in plan.beneficios" :key="beneficio" class="flex gap-2.5">
              <Check class="mt-0.5 h-4 w-4 shrink-0 text-dark" aria-hidden="true" />
              <span class="font-body text-sm leading-snug text-dark/80">{{ beneficio }}</span>
            </li>
          </ul>

          <a
            v-if="plan.stripe_url"
            :href="plan.stripe_url"
            target="_blank"
            rel="noopener noreferrer"
            class="group mt-7 inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl
                   px-6 py-3 font-display text-sm font-bold transition-all duration-300 ease-salida
                   hover:-translate-y-0.5 hover:shadow-sombra"
            :class="plan.destacado
              ? 'bg-dark text-nodo-400 hover:bg-tinta'
              : 'bg-nodo-400 text-dark'"
          >
            {{ plan.cta_label ?? 'Empezar Ahora' }}
            <span class="transition-transform duration-200 group-hover:translate-x-1" aria-hidden="true">→</span>
            <span class="sr-only">— {{ titulo(plan) }}</span>
          </a>
        </div>
      </article>
    </div>

    <!-- Puntos indicadores: protagonistas en móvil -->
    <div class="mt-2 flex items-center justify-center gap-2.5 lg:hidden">
      <button
        v-for="(plan, i) in planes"
        :key="`punto-${i}`"
        type="button"
        class="flex h-11 w-11 items-center justify-center"
        :aria-label="`Ir a ${titulo(plan)}`"
        :aria-current="indiceActivo === i ? 'true' : undefined"
        @click="irA(i)"
      >
        <span
          class="block h-2 rounded-full transition-all duration-300"
          :class="indiceActivo === i ? 'w-8 bg-dark' : 'w-2 bg-dark/25'"
        />
      </button>
    </div>
  </div>
</template>
