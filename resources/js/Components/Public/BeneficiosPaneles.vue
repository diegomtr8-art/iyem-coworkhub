<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

export interface Beneficio {
  titulo: string
  /** Version corta para el panel comprimido, donde el texto va en vertical. */
  tituloCorto?: string
  descripcion: string
  foto: string
  /** Color de acento, tomado de la paleta de planes. */
  acento: string
}

const props = defineProps<{ beneficios: Beneficio[] }>()

const activo = ref(0)
/** En táctil el hover no existe: se muestran tarjetas apiladas. */
const esTactil = ref(false)
const sinMovimiento = ref(false)

let consulta: MediaQueryList | undefined

function alCambiarPuntero(e: MediaQueryListEvent | MediaQueryList) {
  esTactil.value = e.matches
}

onMounted(() => {
  sinMovimiento.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches

  consulta = window.matchMedia('(hover: none) and (pointer: coarse)')
  alCambiarPuntero(consulta)
  consulta.addEventListener('change', alCambiarPuntero)
})

onBeforeUnmount(() => consulta?.removeEventListener('change', alCambiarPuntero))

/** Flechas para recorrer los paneles con teclado. */
function alPulsarTecla(e: KeyboardEvent, i: number) {
  const total = props.beneficios.length

  if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
    e.preventDefault()
    activo.value = (i + 1) % total
    enfocar(activo.value)
  } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
    e.preventDefault()
    activo.value = (i - 1 + total) % total
    enfocar(activo.value)
  }
}

function enfocar(i: number) {
  document.querySelector<HTMLElement>(`[data-panel="${i}"]`)?.focus()
}
</script>

<template>
  <!-- ── Desktop: paneles que se ensanchan ────────────────────────────── -->
  <div
    v-if="!esTactil"
    class="hidden gap-3 lg:flex lg:h-[460px]"
    role="tablist"
    aria-label="Beneficios de la membresía"
  >
    <button
      v-for="(beneficio, i) in beneficios"
      :key="beneficio.titulo"
      :data-panel="i"
      type="button"
      role="tab"
      :aria-selected="activo === i"
      class="group relative isolate overflow-hidden rounded-3xl text-left
             focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-nodo-400"
      :class="[
        // Con reduced-motion todos quedan abiertos y sin transición.
        sinMovimiento ? 'flex-1' : (activo === i ? 'flex-[4]' : 'flex-[1]'),
        sinMovimiento ? '' : 'transition-[flex] duration-500 ease-salida',
      ]"
      @mouseenter="activo = i"
      @focus="activo = i"
      @keydown="alPulsarTecla($event, i)"
    >
      <img
        :src="beneficio.foto"
        alt=""
        aria-hidden="true"
        width="1200"
        height="800"
        loading="lazy"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover"
      />
      <div class="absolute inset-0 -z-10 bg-tinta/[.78]" aria-hidden="true" />

      <!-- Barra de acento -->
      <span
        class="absolute inset-x-0 top-0 h-1.5"
        :style="{ backgroundColor: beneficio.acento }"
        aria-hidden="true"
      />

      <!-- Comprimido: solo el título, en vertical -->
      <span
        v-if="!sinMovimiento && activo !== i"
        class="absolute inset-0 flex items-center justify-center overflow-hidden px-2"
      >
        <span
          class="whitespace-nowrap font-display text-base font-bold text-white/90"
          style="writing-mode: vertical-rl; transform: rotate(180deg)"
        >{{ beneficio.tituloCorto ?? beneficio.titulo }}</span>
      </span>

      <!-- Abierto: título y descripción -->
      <span
        v-else
        class="absolute inset-0 flex flex-col justify-end p-8"
      >
        <span
          class="mb-4 h-1 w-12 rounded-full"
          :style="{ backgroundColor: beneficio.acento }"
          aria-hidden="true"
        />
        <span class="block font-display text-2xl font-extrabold leading-tight text-white">
          {{ beneficio.titulo }}
        </span>
        <span class="mt-3 block max-w-sm font-body text-cuerpo leading-relaxed text-white/80">
          {{ beneficio.descripcion }}
        </span>
      </span>
    </button>
  </div>

  <!-- ── Táctil e intermedios: tarjetas apiladas ──────────────────────── -->
  <ul :class="esTactil ? 'grid gap-5 sm:grid-cols-2' : 'grid gap-5 sm:grid-cols-2 lg:hidden'">
    <li
      v-for="beneficio in beneficios"
      :key="`tarjeta-${beneficio.titulo}`"
      class="relative isolate overflow-hidden rounded-3xl"
    >
      <img
        :src="beneficio.foto"
        alt=""
        aria-hidden="true"
        width="1200"
        height="800"
        loading="lazy"
        decoding="async"
        class="absolute inset-0 -z-20 h-full w-full object-cover"
      />
      <div class="absolute inset-0 -z-10 bg-tinta/[.82]" aria-hidden="true" />

      <span
        class="absolute inset-x-0 top-0 h-1.5"
        :style="{ backgroundColor: beneficio.acento }"
        aria-hidden="true"
      />

      <div class="flex min-h-[240px] flex-col justify-end p-7">
        <span
          class="mb-4 h-1 w-12 rounded-full"
          :style="{ backgroundColor: beneficio.acento }"
          aria-hidden="true"
        />
        <h3 class="font-display text-xl font-extrabold leading-tight text-white">
          {{ beneficio.titulo }}
        </h3>
        <p class="mt-3 font-body text-cuerpo leading-relaxed text-white/80">
          {{ beneficio.descripcion }}
        </p>
      </div>
    </li>
  </ul>
</template>
