<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Infinity as Infinito, ArrowUpRight } from 'lucide-vue-next'

/**
 * Un medidor de bolsa: anillo, saldo y fecha de reinicio.
 *
 * Responde de un vistazo a «¿cuánto me queda y hasta cuándo?», que es la regla
 * de oro del portal. Por eso el número grande es **lo que queda**, no lo usado:
 * nadie planea su semana a partir de lo que ya gastó.
 *
 * Cuando el plan no incluye la bolsa, no se pinta un cero —un cero se lee como
 * un error— sino la invitación a mejorar la membresía.
 */
const props = defineProps<{
  medidor: {
    bolsa: string
    etiqueta: string
    unidad: string
    incluida: boolean
    ilimitada: boolean
    cupo: number | null
    usado: number
    restante: number | null
    porcentaje_usado: number
    tope_diario: number | null
    reinicia_texto: string | null
    casi_agotada: boolean
    agotada: boolean
    sugerencia?: {
      plan: string
      precio: number
      periodo: string | null
      incluye: number | null
      stripe_url: string | null
    } | null
  }
  compacto?: boolean
}>()

/** Sin decimales cuando no hacen falta: «10 h», no «10.00 h». */
const numero = (valor: number | null) => {
  if (valor === null) return '—'
  return Number.isInteger(valor) ? String(valor) : valor.toFixed(1).replace(/\.0$/, '')
}

const restante = computed(() => numero(props.medidor.restante))
const cupo = computed(() => numero(props.medidor.cupo))

/**
 * La unidad llega siempre en plural («horas», «días») porque es una etiqueta
 * de la bolsa, no una frase. Aquí se ajusta al número: «1 hora al día», no
 * «1 horas al día».
 */
const unidadPara = (cantidad: number | null) => {
  const plural = props.medidor.unidad
  if (cantidad === null || Math.abs(cantidad) !== 1) return plural
  return plural === 'días' ? 'día' : 'hora'
}

/**
 * Geometría del anillo. El radio y la circunferencia se calculan una vez y el
 * relleno se anima con `stroke-dashoffset`, que es una propiedad compuesta
 * —no dispara relayout— igual que las transformaciones del resto del sistema.
 */
const RADIO = 42
const CIRCUNFERENCIA = 2 * Math.PI * RADIO

const desfase = computed(() => {
  if (props.medidor.ilimitada) return 0
  const restantePct = 100 - Math.min(100, props.medidor.porcentaje_usado)
  return CIRCUNFERENCIA * (1 - restantePct / 100)
})

/**
 * Color del arco. Es el único punto del portal donde el color dice estado, y
 * aun así el mensaje no depende solo de él: el texto de abajo dice «Sin horas»
 * o «Te queda poco» en palabras. El color por sí solo no es accesible.
 */
const colorArco = computed(() => {
  if (props.medidor.ilimitada) return '#D6E265'
  if (props.medidor.agotada) return '#EF7E88'
  if (props.medidor.casi_agotada) return '#e6c700'
  return '#FFE124'
})

const aviso = computed(() => {
  if (props.medidor.ilimitada) return null
  if (props.medidor.agotada) return `Sin ${props.medidor.unidad} este ciclo`
  if (props.medidor.casi_agotada) return `Te queda poco`
  return null
})
</script>

<template>
  <!-- Bolsa que el plan no incluye: invitación, no un cero. -->
  <div
    v-if="!medidor.incluida"
    class="flex h-full flex-col justify-between border-2 border-dashed border-dark/25 bg-cream-50 p-5"
  >
    <div>
      <p class="etiqueta-tecnica text-dark/70">{{ medidor.etiqueta }}</p>
      <p class="mt-3 font-body text-sm text-dark/70">
        Tu plan no incluye esta bolsa.
      </p>
    </div>

    <Link
      v-if="medidor.sugerencia"
      :href="route('portal.suscripcion')"
      class="group mt-4 inline-flex min-h-[44px] items-center gap-1.5 font-display text-sm font-bold
             text-dark underline decoration-nodo-500 decoration-2 underline-offset-4
             hover:decoration-dark focus-visible:outline focus-visible:outline-2
             focus-visible:outline-offset-2 focus-visible:outline-dark"
    >
      {{ medidor.sugerencia.plan }} incluye
      <template v-if="medidor.sugerencia.incluye">
        {{ numero(medidor.sugerencia.incluye) }} {{ medidor.unidad }}
      </template>
      <ArrowUpRight :size="16" class="transition-transform duration-200 ease-salida group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
    </Link>
  </div>

  <!-- Bolsa incluida. -->
  <div
    v-else
    class="flex h-full flex-col border-2 border-dark bg-white p-5 shadow-dura-sm transition-all
           duration-200 ease-salida hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-dura"
  >
    <p class="etiqueta-tecnica text-dark/70">{{ medidor.etiqueta }}</p>

    <div class="mt-4 flex items-center gap-4">
      <!-- El anillo es decorativo: el dato está en el texto de al lado. -->
      <div class="relative shrink-0" :class="compacto ? 'h-16 w-16' : 'h-20 w-20'">
        <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90" aria-hidden="true">
          <circle cx="50" cy="50" :r="RADIO" fill="none" stroke="#E8E1D1" stroke-width="12" />
          <circle
            v-if="!medidor.ilimitada"
            cx="50" cy="50" :r="RADIO"
            fill="none"
            :stroke="colorArco"
            stroke-width="12"
            stroke-linecap="butt"
            :stroke-dasharray="CIRCUNFERENCIA"
            :stroke-dashoffset="desfase"
            class="transition-[stroke-dashoffset] duration-700 ease-salida motion-reduce:transition-none"
          />
          <circle
            v-else
            cx="50" cy="50" :r="RADIO"
            fill="none" :stroke="colorArco" stroke-width="12"
          />
        </svg>

        <span
          v-if="medidor.ilimitada"
          class="absolute inset-0 flex items-center justify-center"
          aria-hidden="true"
        >
          <Infinito :size="compacto ? 20 : 24" class="text-dark" />
        </span>
      </div>

      <div class="min-w-0">
        <p v-if="medidor.ilimitada" class="font-display text-2xl font-extrabold leading-none text-dark">
          Sin límite
        </p>

        <template v-else>
          <p class="font-display font-extrabold leading-none text-dark" :class="compacto ? 'text-2xl' : 'text-3xl'">
            {{ restante }}
            <span class="text-base font-bold text-dark/70">{{ unidadPara(medidor.restante) }}</span>
          </p>
          <p class="mt-1 font-body text-xs text-dark/70">
            de {{ cupo }} · usaste {{ numero(medidor.usado) }}
          </p>
        </template>
      </div>
    </div>

    <p
      v-if="aviso"
      class="mt-3 inline-flex w-fit items-center gap-1.5 border border-dark/20 bg-cream px-2 py-1
             font-mono text-[0.6875rem] uppercase tracking-[0.12em] text-dark"
    >
      <span class="h-1.5 w-1.5 rounded-full" :style="{ background: colorArco }" aria-hidden="true" />
      {{ aviso }}
    </p>

    <div class="mt-auto pt-4 space-y-1">
      <p v-if="medidor.tope_diario" class="font-body text-xs text-dark/70">
        Máximo {{ numero(medidor.tope_diario) }} {{ unidadPara(medidor.tope_diario) }} al día
      </p>
      <p v-if="medidor.reinicia_texto" class="font-body text-xs text-dark/70">
        {{ medidor.reinicia_texto }}
      </p>
    </div>
  </div>
</template>
