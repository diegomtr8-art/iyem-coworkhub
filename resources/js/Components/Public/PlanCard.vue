<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
  plan: {
    nombre: string
    precio?: number
    periodo_label?: string | null
    descripcion_corta?: string | null
    color?: string | null
    imagen?: string | null
    personas?: number | null
  }
}>()

/** El original titula el plan de 2 personas como «Nodo Match (2 pax)». */
const titulo = computed(() =>
  (props.plan.personas ?? 1) > 1 ? `${props.plan.nombre} (${props.plan.personas} pax)` : props.plan.nombre,
)

const precio = computed(() =>
  typeof props.plan.precio === 'number'
    ? props.plan.precio.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })
    : null,
)
</script>

<template>
  <article class="flex h-full flex-col overflow-hidden rounded-3xl bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl">
    <img
      v-if="plan.imagen"
      :src="plan.imagen"
      :alt="`Espacio de trabajo del plan ${titulo}`"
      width="600"
      height="400"
      loading="lazy"
      decoding="async"
      class="aspect-[3/2] w-full object-cover"
    />

    <div class="h-2 w-full shrink-0" :style="{ backgroundColor: plan.color ?? '#FFE124' }" aria-hidden="true" />

    <div class="flex flex-1 flex-col p-7">
      <h3 class="font-display text-xl font-extrabold uppercase tracking-tight text-dark">
        {{ titulo }}
      </h3>

      <p v-if="precio" class="mt-2 font-display text-2xl font-bold text-dark">
        {{ precio }}
        <span v-if="plan.periodo_label" class="font-body text-sm font-normal text-dark/60">
          {{ plan.periodo_label }}
        </span>
      </p>

      <p v-if="plan.descripcion_corta" class="mt-4 flex-1 font-body text-sm leading-relaxed text-dark/70">
        {{ plan.descripcion_corta }}
      </p>

      <Link
        :href="route('membresias')"
        class="mt-6 inline-flex w-fit items-center gap-2 rounded-lg border-2 border-dark bg-nodo-400 px-5 py-2.5
               font-display text-sm font-bold text-dark transition hover:brightness-95
               focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
      >
        Saber más
        <span aria-hidden="true">→</span>
        <span class="sr-only">sobre el plan {{ titulo }}</span>
      </Link>
    </div>
  </article>
</template>
