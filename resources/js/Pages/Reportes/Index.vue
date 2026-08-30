<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Download, TrendingDown, Users, Building2 } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'
import Estado from '@/Components/Panel/Estado.vue'

/**
 * Reportes (Fase 3.10). Solo administración.
 *
 * Cada informe responde a una decisión concreta, no completa un tablero. El de
 * «miembros en riesgo» va destacado porque es el único con el que todavía se
 * puede hacer algo: cuando alguien no renueva, ya solo sale en el histórico.
 */
const props = defineProps<{
  rango: any
  ocupacion: any[]
  porFranja: any[]
  consumoPorPlan: any[]
  ingresos: any
  noShow: any
  enRiesgo: any[]
}>()

const desde = ref(props.rango.desde)
const hasta = ref(props.rango.hasta)

const aplicar = () =>
  router.get(route('reportes.index'), { desde: desde.value, hasta: hasta.value }, { preserveState: true })

const precio = (v: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(v ?? 0)

const exportar = (informe: string) => {
  const url = new URL(route('reportes.exportar'), window.location.origin)
  url.searchParams.set('informe', informe)
  url.searchParams.set('desde', desde.value)
  url.searchParams.set('hasta', hasta.value)
  window.location.href = url.toString()
}

/** Verde si el cupo está bien puesto; ámbar si sobra o falta mucho. */
function tonoAprovechamiento(pct: number): 'bien' | 'atencion' | 'problema' {
  if (pct >= 60 && pct <= 95) return 'bien'
  if (pct > 95) return 'problema'
  return 'atencion'
}
</script>

<template>
  <Head title="Reportes" />

  <AuthenticatedLayout>
    <template #breadcrumb>
      <span class="font-display font-bold text-dark">Reportes</span>
      <span>· {{ rango.etiqueta }}</span>
    </template>

    <div class="space-y-4">
      <!-- Rango -->
      <Panel padding="sm">
        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label for="rp-desde" class="mb-1 block text-xs font-bold text-dark">Desde</label>
            <input id="rp-desde" v-model="desde" type="date" class="border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
          </div>
          <div>
            <label for="rp-hasta" class="mb-1 block text-xs font-bold text-dark">Hasta</label>
            <input id="rp-hasta" v-model="hasta" type="date" class="border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
          </div>
          <button
            type="button"
            class="min-h-[38px] border border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark hover:bg-nodo-500"
            @click="aplicar"
          >Aplicar</button>
          <span class="font-mono text-[0.6875rem] text-dark/50">{{ rango.dias }} días</span>
        </div>
      </Panel>

      <!-- Ingresos -->
      <div class="grid gap-4 md:grid-cols-3">
        <Panel titulo="Membresías" padding="md">
          <p class="font-display text-2xl font-extrabold text-dark">{{ precio(ingresos.total_membresias) }}</p>
          <ul class="mt-3 space-y-1 text-xs">
            <li v-for="p in ingresos.por_plan" :key="p.plan" class="flex justify-between gap-2">
              <span class="truncate text-dark/70">{{ p.plan }} ({{ p.membresias }})</span>
              <span class="shrink-0 font-mono text-dark">{{ precio(p.ingreso) }}</span>
            </li>
          </ul>
        </Panel>

        <Panel titulo="Salones" padding="md">
          <p class="font-display text-2xl font-extrabold text-dark">{{ precio(ingresos.salones.ingreso) }}</p>
          <dl class="mt-3 space-y-1 text-xs">
            <div class="flex justify-between"><dt class="text-dark/70">Eventos</dt><dd class="font-mono text-dark">{{ ingresos.salones.eventos }}</dd></div>
            <div class="flex justify-between"><dt class="text-dark/70">Cobrado en anticipos</dt><dd class="font-mono text-dark">{{ precio(ingresos.salones.cobrado) }}</dd></div>
          </dl>
        </Panel>

        <Panel titulo="No-show" padding="md">
          <p class="font-display text-2xl font-extrabold text-dark">{{ noShow.tasa_pct }}%</p>
          <dl class="mt-3 space-y-1 text-xs">
            <div class="flex justify-between"><dt class="text-dark/70">Faltas</dt><dd class="font-mono text-dark">{{ noShow.no_show }} de {{ noShow.total_reservas }}</dd></div>
            <div class="flex justify-between"><dt class="text-dark/70">Horas de sala perdidas</dt><dd class="font-mono text-dark">{{ noShow.horas_perdidas }} h</dd></div>
          </dl>
        </Panel>
      </div>

      <!-- Ocupación por espacio -->
      <Panel titulo="Ocupación por espacio" padding="none">
        <template #acciones>
          <button type="button" class="flex min-h-[30px] items-center gap-1.5 border border-dark/25 px-2.5 font-display text-xs font-bold text-dark hover:border-dark" @click="exportar('ocupacion')">
            <Download :size="12" aria-hidden="true" /> CSV
          </button>
        </template>

        <ul class="divide-y divide-dark/10">
          <li v-for="o in ocupacion" :key="o.espacio" class="flex items-center gap-3 px-4 py-2.5">
            <div class="w-44 shrink-0">
              <p class="truncate font-display text-sm font-bold text-dark">{{ o.espacio }}</p>
              <p class="truncate text-[0.6875rem] text-dark/50">{{ o.tipo }}</p>
            </div>

            <div class="h-2.5 flex-1 bg-dark/10">
              <div
                class="h-full transition-all duration-500"
                :class="o.ocupacion_pct >= 80 ? 'bg-red-600' : o.ocupacion_pct >= 40 ? 'bg-emerald-600' : 'bg-amber-500'"
                :style="{ width: Math.min(100, o.ocupacion_pct) + '%' }"
              />
            </div>

            <span class="w-28 shrink-0 text-right font-mono text-xs text-dark">
              {{ o.ocupacion_pct }}% · {{ o.horas }} h
            </span>
          </li>
        </ul>
      </Panel>

      <div class="grid gap-4 lg:grid-cols-2">
        <!-- Franjas -->
        <Panel titulo="Qué horas se saturan" padding="md">
          <div class="flex items-end gap-1" style="height: 8rem">
            <div v-for="f in porFranja" :key="f.hora" class="flex flex-1 flex-col items-center gap-1">
              <div
                class="w-full bg-dark/80 transition-all duration-500"
                :style="{ height: Math.max(2, f.pct) + '%' }"
                :title="`${f.hora}: ${f.reservas} reservas`"
              />
              <span class="font-mono text-[0.5625rem] text-dark/50">{{ f.hora.slice(0, 2) }}</span>
            </div>
          </div>
          <p class="mt-2 text-[0.6875rem] text-dark/55">
            Dice si conviene abrir antes o cerrar después, y qué franja está muerta.
          </p>
        </Panel>

        <!-- Consumo contra incluido -->
        <Panel titulo="¿Los cupos están bien puestos?" padding="none">
          <template #acciones>
            <button type="button" class="flex min-h-[30px] items-center gap-1.5 border border-dark/25 px-2.5 font-display text-xs font-bold text-dark hover:border-dark" @click="exportar('consumo')">
              <Download :size="12" aria-hidden="true" /> CSV
            </button>
          </template>

          <p v-if="!consumoPorPlan.length" class="px-4 py-6 text-center text-sm text-dark/50">
            Sin datos en este rango.
          </p>

          <ul v-else class="divide-y divide-dark/10">
            <li v-for="(c, i) in consumoPorPlan" :key="i" class="px-4 py-2.5">
              <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                  <p class="truncate font-display text-sm font-bold text-dark">{{ c.plan }}</p>
                  <p class="truncate text-[0.6875rem] text-dark/55">{{ c.bolsa }}</p>
                </div>
                <Estado :tono="tonoAprovechamiento(c['aprovechamiento_pct'])" :texto="c['aprovechamiento_pct'] + '%'" />
              </div>

              <p class="mt-1 font-mono text-[0.6875rem] text-dark/60">
                {{ c.miembros }} miembros · usan {{ c['promedio_c/u'] }} de {{ c['incluidas_c/u'] }} h
                <template v-if="c.al_tope"> · {{ c.al_tope }} al tope</template>
              </p>
            </li>
          </ul>

          <p class="border-t border-dark/15 bg-cream-50 px-4 py-2.5 text-[0.6875rem] text-dark/60">
            Por debajo del 60% el plan promete horas que nadie usa. Por encima del 95%, se queda corto.
          </p>
        </Panel>
      </div>

      <!-- Miembros en riesgo: el informe con el que todavía se puede actuar. -->
      <Panel padding="none">
        <template #acciones>
          <button type="button" class="flex min-h-[30px] items-center gap-1.5 border border-dark/25 px-2.5 font-display text-xs font-bold text-dark hover:border-dark" @click="exportar('en_riesgo')">
            <Download :size="12" aria-hidden="true" /> CSV
          </button>
        </template>

        <div class="border-b border-dark/15 bg-amber-50 px-4 py-2.5">
          <h2 class="flex items-center gap-2 font-display text-sm font-bold text-amber-900">
            <TrendingDown :size="15" aria-hidden="true" />
            Miembros en riesgo
            <span class="border border-amber-700/30 bg-white px-1.5 py-0.5 font-mono text-[0.6875rem]">
              {{ enRiesgo.length }}
            </span>
          </h2>
          <p class="mt-0.5 text-xs text-amber-900/80">
            Membresía activa y más de tres semanas sin venir. Todavía se les puede llamar.
          </p>
        </div>

        <p v-if="!enRiesgo.length" class="px-4 py-8 text-center text-sm text-dark/50">
          Nadie en riesgo. Todos los activos han venido en las últimas tres semanas.
        </p>

        <div v-else class="overflow-x-auto">
          <table class="w-full min-w-[40rem] text-sm">
            <thead class="border-b border-dark/15 bg-cream-50">
              <tr class="text-left font-mono text-[0.625rem] uppercase tracking-[0.1em] text-dark/50">
                <th scope="col" class="px-4 py-2 font-normal">Miembro</th>
                <th scope="col" class="px-3 py-2 font-normal">Plan</th>
                <th scope="col" class="px-3 py-2 font-normal">Último acceso</th>
                <th scope="col" class="px-3 py-2 font-normal">Vence</th>
                <th scope="col" class="px-3 py-2 font-normal">Contacto</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-dark/10">
              <tr v-for="m in enRiesgo" :key="m.email" class="hover:bg-cream-50">
                <td class="px-4 py-2.5">
                  <Link :href="m.url" class="font-display font-bold text-dark underline decoration-dark/25 underline-offset-2 hover:decoration-dark">
                    {{ m.miembro }}
                  </Link>
                </td>
                <td class="px-3 py-2.5 text-xs text-dark/70">{{ m.plan }}</td>
                <td class="px-3 py-2.5 text-xs">
                  <span v-if="m.dias_sin_venir !== null" class="text-dark">
                    hace {{ m.dias_sin_venir }} días
                  </span>
                  <span v-else class="text-red-700">nunca ha venido</span>
                </td>
                <td class="px-3 py-2.5 font-mono text-xs text-dark/60">{{ m.vence }}</td>
                <td class="px-3 py-2.5 text-xs">
                  <a v-if="m.telefono" :href="`tel:${m.telefono}`" class="block text-dark underline decoration-dark/25 underline-offset-2">{{ m.telefono }}</a>
                  <a :href="`mailto:${m.email}`" class="block truncate text-dark/60">{{ m.email }}</a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Panel>

      <!-- No-show por miembro -->
      <Panel v-if="noShow.por_miembro.length" titulo="Quién no se presenta" padding="none">
        <template #acciones>
          <button type="button" class="flex min-h-[30px] items-center gap-1.5 border border-dark/25 px-2.5 font-display text-xs font-bold text-dark hover:border-dark" @click="exportar('no_show')">
            <Download :size="12" aria-hidden="true" /> CSV
          </button>
        </template>

        <ul class="divide-y divide-dark/10">
          <li v-for="m in noShow.por_miembro" :key="m.miembro" class="flex items-center justify-between gap-2 px-4 py-2">
            <span class="truncate text-sm text-dark">{{ m.miembro }}</span>
            <span class="shrink-0 font-mono text-xs text-dark/60">{{ m.faltas }} faltas · {{ m.horas }} h</span>
          </li>
        </ul>
      </Panel>
    </div>
  </AuthenticatedLayout>
</template>
