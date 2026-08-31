<script setup lang="ts">
/**
 * Lista responsiva del panel operativo (Fase 5 · responsive).
 *
 * Una sola fuente de datos, dos presentaciones:
 *  - En `lg` y arriba: la tabla densa de siempre, como se ve hoy en escritorio.
 *  - Por debajo de `lg`: cada fila se apila como **tarjeta**. Arriba lo que
 *    identifica el registro (rol `identidad`); debajo, los dos o tres datos que
 *    se consultan de pie (rol `resumen`); el resto (rol `detalle`) se esconde
 *    tras «Ver detalle».
 *
 * El corte es `lg`, no `sm`, a propósito: entre `md` y `lg` (p. ej. iPad mini a
 * 744 px) la barra lateral ya se escondió pero el contenido se compuso pensando
 * en escritorio, y ahí es donde una tabla de siete columnas revienta.
 *
 * El contenido de cada celda lo pone quien usa el componente, con un slot con
 * ámbito por columna (`#[clave]="{ fila, valor }"`). Así el mismo marcado sirve
 * en la tabla y en la tarjeta, y no se duplica en cada pantalla.
 */
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { ChevronRight } from 'lucide-vue-next'

export interface Columna {
  /** Clave del dato y nombre del slot con ámbito (`#[clave]`). */
  clave: string
  /** Cabecera de la tabla y etiqueta del par en la tarjeta. */
  etiqueta: string
  /**
   * identidad → título de la tarjeta en móvil (lo que identifica el registro).
   * resumen   → siempre visible en la tarjeta, bajo el título.
   * detalle   → en móvil se esconde tras «Ver detalle»; en la tabla es una más.
   * (sin rol → se trata como `resumen`.)
   */
  rol?: 'identidad' | 'resumen' | 'detalle'
  /** Clase extra para la celda y su cabecera (ancho, alineación…). */
  clase?: string
  /** No pinta la etiqueta del par en la tarjeta (para datos que se explican solos). */
  sinEtiqueta?: boolean
}

const props = withDefaults(defineProps<{
  columnas: Columna[]
  filas: any[]
  claveFila?: string
  /** Si se da, la fila y la tarjeta navegan a esta URL al tocarlas. */
  href?: (fila: any) => string | null
  /**
   * Alternativa a `href`: hace la fila «tocable» y, al tocarla, emite
   * `seleccionar` con la fila. Para cuando el clic abre un modal en la misma
   * pantalla en vez de navegar (p. ej. editar una renta).
   */
  seleccionable?: boolean
  /** Clase extra por fila según sus datos (p. ej. resaltar un acceso fallido). */
  filaClase?: (fila: any) => string | undefined
  etiquetaAbrir?: string
  /** Texto cuando no hay filas. */
  vacio?: string
}>(), {
  claveFila: 'id',
  seleccionable: false,
  etiquetaAbrir: 'Ver detalle',
  vacio: 'Sin registros.',
})

const emit = defineEmits<{ seleccionar: [fila: any] }>()

const identidad = computed(() => props.columnas.filter((c) => c.rol === 'identidad'))
const resumen = computed(() => props.columnas.filter((c) => !c.rol || c.rol === 'resumen'))
const detalle = computed(() => props.columnas.filter((c) => c.rol === 'detalle'))

/** Una fila reacciona al toque si navega (`href`) o si se pidió `seleccionable`. */
const interactivo = computed(() => !!props.href || props.seleccionable)

function navegar(fila: any) {
  if (props.href) {
    const url = props.href(fila)
    if (url) router.visit(url)
  } else if (props.seleccionable) {
    emit('seleccionar', fila)
  }
}
</script>

<template>
  <div>
    <!-- ≥ lg: la tabla densa de escritorio, intacta. -->
    <div class="hidden overflow-x-auto lg:block">
      <table class="w-full text-sm">
        <thead class="border-b border-dark/15 bg-cream-50">
          <tr class="text-left font-mono text-[0.625rem] uppercase tracking-[0.1em] text-dark/50">
            <th
              v-for="c in columnas" :key="c.clave"
              scope="col" class="px-3 py-2 font-normal" :class="c.clase"
            >{{ c.etiqueta }}</th>
            <th v-if="href" class="w-8 px-3 py-2"><span class="sr-only">Abrir</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-dark/10">
          <tr
            v-for="fila in filas" :key="fila[claveFila]"
            :class="[interactivo ? 'cursor-pointer transition-colors hover:bg-cream-50' : '', filaClase?.(fila)]"
            @click="navegar(fila)"
          >
            <td v-for="c in columnas" :key="c.clave" class="px-3 py-2.5 align-top" :class="c.clase">
              <slot :name="c.clave" :fila="fila" :valor="fila[c.clave]">{{ fila[c.clave] ?? '—' }}</slot>
            </td>
            <td v-if="href" class="px-3 py-2.5 text-dark/30">
              <ChevronRight :size="15" aria-hidden="true" />
            </td>
          </tr>
          <tr v-if="!filas.length">
            <td :colspan="columnas.length + (href ? 1 : 0)" class="px-4 py-10 text-center text-sm text-dark/50">{{ vacio }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- < lg: tarjetas apiladas. -->
    <ul class="divide-y divide-dark/10 lg:hidden">
      <li
        v-for="fila in filas" :key="fila[claveFila]"
        class="px-4 py-3.5"
        :class="href ? 'cursor-pointer transition-colors hover:bg-cream-50' : ''"
        @click="navegar(fila)"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div v-for="c in identidad" :key="c.clave" class="font-display font-bold text-dark">
              <slot :name="c.clave" :fila="fila" :valor="fila[c.clave]">{{ fila[c.clave] }}</slot>
            </div>
          </div>
          <ChevronRight v-if="href" :size="16" class="mt-0.5 shrink-0 text-dark/30" aria-hidden="true" />
        </div>

        <dl v-if="resumen.length" class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
          <div v-for="c in resumen" :key="c.clave" class="flex items-center gap-1.5">
            <dt v-if="!c.sinEtiqueta" class="font-mono uppercase tracking-[0.08em] text-dark/45">{{ c.etiqueta }}</dt>
            <dd class="text-dark/80">
              <slot :name="c.clave" :fila="fila" :valor="fila[c.clave]">{{ fila[c.clave] ?? '—' }}</slot>
            </dd>
          </div>
        </dl>

        <!-- El resto, tras «Ver detalle». El toggle no dispara la navegación de la tarjeta. -->
        <details v-if="detalle.length" class="mt-2" @click.stop>
          <summary
            class="inline-flex min-h-[44px] cursor-pointer list-none items-center gap-1 font-mono
                   text-[0.6875rem] uppercase tracking-[0.08em] text-dark/55
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
          >{{ etiquetaAbrir }}</summary>
          <dl class="mt-1 grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
            <div v-for="c in detalle" :key="c.clave" class="min-w-0">
              <dt class="font-mono uppercase tracking-[0.08em] text-dark/45">{{ c.etiqueta }}</dt>
              <dd class="mt-0.5 text-dark/80">
                <slot :name="c.clave" :fila="fila" :valor="fila[c.clave]">{{ fila[c.clave] ?? '—' }}</slot>
              </dd>
            </div>
          </dl>
        </details>
      </li>

      <li v-if="!filas.length" class="px-4 py-10 text-center text-sm text-dark/50">{{ vacio }}</li>
    </ul>
  </div>
</template>
