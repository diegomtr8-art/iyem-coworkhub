<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { CheckCircle2, AlertTriangle, XCircle } from 'lucide-vue-next'

/**
 * El estado de la membresía, arriba de todo (Fase 2.1).
 *
 * Si está pendiente o suspendida, hay que **decirlo con claridad y con la acción
 * para resolverlo**. Un portal que se limita a no dejar reservar, sin explicar
 * por qué, manda a la persona a preguntar a recepción.
 *
 * El estado no se codifica solo en color: lleva icono y texto. Un aviso que solo
 * se distingue por ser rojo no existe para quien no distingue el rojo.
 */
const props = defineProps<{
  estado: {
    tiene: boolean
    tono: 'bien' | 'atencion' | 'problema'
    titulo: string
    detalle: string
    accion: string | null
    accion_href: string | null
  }
  diasRestantes?: number
}>()

const tonos = {
  bien: {
    caja:  'border-dark bg-white',
    icono: 'text-dark',
    punto: 'bg-lima',
  },
  atencion: {
    caja:  'border-dark bg-nodo-400',
    icono: 'text-dark',
    punto: 'bg-dark',
  },
  problema: {
    caja:  'border-dark bg-dark',
    icono: 'text-coral',
    punto: 'bg-coral',
  },
} as const

const iconos = { bien: CheckCircle2, atencion: AlertTriangle, problema: XCircle }

const esOscura = props.estado.tono === 'problema'
/** El enlace de Stripe sale del sitio; el de planes es navegación interna. */
const esExterno = (href: string | null) => !!href && /^https?:\/\//.test(href)
</script>

<template>
  <section
    class="flex flex-col gap-4 border-2 p-5 shadow-dura-sm sm:flex-row sm:items-center sm:justify-between sm:p-6"
    :class="tonos[estado.tono].caja"
  >
    <div class="flex items-start gap-4">
      <component
        :is="iconos[estado.tono]"
        :size="24"
        class="mt-0.5 shrink-0"
        :class="tonos[estado.tono].icono"
        aria-hidden="true"
      />

      <div class="min-w-0">
        <h2
          class="font-display text-display-sm font-extrabold leading-tight"
          :class="esOscura ? 'text-white' : 'text-dark'"
        >
          {{ estado.titulo }}
        </h2>
        <p class="mt-1 font-body text-sm" :class="esOscura ? 'text-cream/80' : 'text-dark/70'">
          {{ estado.detalle }}
        </p>
      </div>
    </div>

    <div class="flex shrink-0 items-center gap-4 sm:flex-col sm:items-end sm:gap-2">
      <p
        v-if="estado.tiene && typeof diasRestantes === 'number'"
        class="font-mono text-[0.6875rem] uppercase tracking-[0.14em]"
        :class="esOscura ? 'text-cream/70' : 'text-dark/70'"
      >
        {{ diasRestantes }} {{ diasRestantes === 1 ? 'día' : 'días' }}
      </p>

      <a
        v-if="estado.accion && esExterno(estado.accion_href)"
        :href="estado.accion_href!"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex min-h-[44px] items-center justify-center border-2 border-dark bg-white px-5
               font-display text-sm font-bold text-dark transition-all duration-200 ease-salida
               hover:-translate-y-0.5 hover:shadow-dura-sm focus-visible:outline focus-visible:outline-2
               focus-visible:outline-offset-2 focus-visible:outline-dark"
      >
        {{ estado.accion }}
      </a>

      <Link
        v-else-if="estado.accion && estado.accion_href"
        :href="estado.accion_href"
        class="inline-flex min-h-[44px] items-center justify-center border-2 border-dark bg-nodo-400 px-5
               font-display text-sm font-bold text-dark transition-all duration-200 ease-salida
               hover:-translate-y-0.5 hover:shadow-dura-sm focus-visible:outline focus-visible:outline-2
               focus-visible:outline-offset-2 focus-visible:outline-dark"
      >
        {{ estado.accion }}
      </Link>
    </div>
  </section>
</template>
