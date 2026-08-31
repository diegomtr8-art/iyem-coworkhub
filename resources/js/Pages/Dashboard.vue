<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
  Users, CalendarDays, Lightbulb, PartyPopper, LogOut, Clock, AlertTriangle, ChevronRight,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'
import Estado from '@/Components/Panel/Estado.vue'

/**
 * Tablero del día (Fase 3.1).
 *
 * El orden no es «datos primero»: **las alertas van antes que los números**.
 * Un indicador bonito no es urgente, y esto se lee con alguien esperando
 * enfrente. La línea de tiempo por espacio está para responder de un vistazo la
 * pregunta que más se hace en el mostrador: «¿tienes una sala libre ahora?».
 */
const props = defineProps<{
  hoy: string
  dentroAhora: any[]
  lineaDelDia: any[]
  alertas: any[]
  indicadores: Record<string, number>
}>()

/** Se refresca cada dos minutos: es un tablero de sala, vive abierto todo el día. */
let refresco: number | undefined
onMounted(() => {
  refresco = window.setInterval(
    () => router.reload({ only: ['dentroAhora', 'lineaDelDia', 'alertas', 'indicadores'] }),
    120_000,
  )
})
onUnmounted(() => { if (refresco) window.clearInterval(refresco) })

const hora = (iso: string) =>
  new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })

const duracion = (minutos: number) => {
  const h = Math.floor(minutos / 60)
  return h > 0 ? `${h} h ${minutos % 60} min` : `${minutos} min`
}

const fechaLarga = computed(() =>
  new Date(`${props.hoy}T12:00:00`).toLocaleDateString('es-MX', {
    weekday: 'long', day: 'numeric', month: 'long',
  }),
)

/** Qué tarjeta se pinta para cada contador. La prop `indicadores` trae los valores. */
const tarjetas = [
  { clave: 'dentro_ahora',        etiqueta: 'Dentro ahora',           icono: Users },
  { clave: 'reservas_hoy',        etiqueta: 'Reservas hoy',           icono: CalendarDays },
  { clave: 'miembros_activos',    etiqueta: 'Miembros activos',       icono: Users },
  { clave: 'asesorias_pendientes', etiqueta: 'Asesorías por responder', icono: Lightbulb },
  { clave: 'eventos_proximos',    etiqueta: 'Eventos por venir',      icono: PartyPopper },
] as const
</script>

<template>
  <Head title="Hoy" />

  <AuthenticatedLayout>
    <template #breadcrumb>
      <span class="font-display font-bold text-dark">Hoy</span>
      <span class="first-letter:uppercase">· {{ fechaLarga }}</span>
    </template>

    <div class="space-y-4">
      <!-- 1 · Lo que exige acción. Antes que cualquier número. -->
      <div v-if="alertas.length" class="grid gap-3 lg:grid-cols-2">
        <Panel v-for="alerta in alertas" :key="alerta.clave" padding="none">
          <template #acciones>
            <Estado :tono="alerta.tono" :texto="alerta.items.length + ''" :punto="false" />
          </template>

          <div class="border-b border-dark/15 bg-cream-50 px-4 py-2.5">
            <h2 class="flex items-center gap-2 font-display text-sm font-bold text-dark">
              <AlertTriangle
                v-if="alerta.tono === 'atencion'"
                :size="15" class="shrink-0 text-amber-600" aria-hidden="true"
              />
              {{ alerta.titulo }}
            </h2>
          </div>

          <ul class="divide-y divide-dark/10">
            <li v-for="(item, i) in alerta.items" :key="i">
              <component
                :is="item.url ? Link : 'div'"
                :href="item.url"
                class="flex items-center justify-between gap-2 px-4 py-2 text-sm text-dark/80"
                :class="item.url ? 'transition-colors hover:bg-cream-50' : ''"
              >
                {{ item.texto }}
                <ChevronRight v-if="item.url" :size="14" class="shrink-0 text-dark/30" aria-hidden="true" />
              </component>
            </li>
          </ul>
        </Panel>
      </div>

      <!-- 2 · Quién está dentro. -->
      <Panel titulo="Dentro ahora" :contador="dentroAhora.length" padding="none">
        <p v-if="!dentroAhora.length" class="px-4 py-6 text-center text-sm text-dark/50">
          No hay nadie en el espacio.
        </p>

        <ul v-else class="divide-y divide-dark/10">
          <li
            v-for="persona in dentroAhora" :key="persona.id"
            class="flex items-center justify-between gap-3 px-4 py-2.5"
          >
            <Link
              :href="route('miembros.show', persona.user_id)"
              class="min-w-0 flex-1 focus-visible:outline focus-visible:outline-2
                     focus-visible:outline-offset-2 focus-visible:outline-dark"
            >
              <span class="block truncate font-display text-sm font-bold text-dark">{{ persona.nombre }}</span>
              <span class="block truncate text-xs text-dark/60">
                {{ persona.espacio ?? 'Coworking' }}
                <template v-if="persona.empresa"> · {{ persona.empresa }}</template>
              </span>
            </Link>

            <span class="shrink-0 text-right">
              <span class="block font-mono text-xs text-dark">desde {{ hora(persona.entrada) }}</span>
              <span class="block text-[0.6875rem] text-dark/50">{{ duracion(persona.minutos) }}</span>
            </span>

            <form
              class="shrink-0"
              @submit.prevent="router.post(route('checkins.salida', persona.id), {}, { preserveScroll: true })"
            >
              <button
                type="submit"
                class="flex min-h-[34px] items-center gap-1.5 border border-dark/25 px-2.5
                       font-display text-xs font-bold text-dark transition-colors hover:border-dark
                       hover:bg-dark hover:text-white focus-visible:outline focus-visible:outline-2
                       focus-visible:outline-offset-2 focus-visible:outline-dark"
              >
                <LogOut :size="13" aria-hidden="true" /> Salida
              </button>
            </form>
          </li>
        </ul>
      </Panel>

      <!-- 3 · Línea de tiempo por espacio. -->
      <Panel titulo="Los espacios hoy" padding="none">
        <template #acciones>
          <Link
            :href="route('agenda.index')"
            class="flex min-h-[32px] items-center gap-1.5 border border-dark/25 px-2.5
                   font-display text-xs font-bold text-dark transition-colors hover:border-dark
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                   focus-visible:outline-dark"
          >Ver la semana</Link>
        </template>

        <!-- En lg+ la fila es de tres columnas (nombre · barra · estado) y la barra
             puede desbordar dentro de su caja. Por debajo de lg la fila se reacomoda:
             nombre y estado arriba, la barra de ocupación a ancho completo debajo —
             legible sin scroll horizontal. -->
        <div class="lg:overflow-x-auto">
          <ul class="divide-y divide-dark/10 lg:min-w-[42rem]">
            <li v-for="fila in lineaDelDia" :key="fila.espacio_id" class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-2.5 lg:flex-nowrap">
              <div class="w-40 shrink-0">
                <p class="truncate font-display text-sm font-bold text-dark">{{ fila.nombre }}</p>
                <p class="truncate text-[0.6875rem] text-dark/50">{{ fila.tipo }}</p>
              </div>

              <div class="relative order-last h-9 w-full border border-dark/15 bg-cream-50 lg:order-none lg:w-auto lg:flex-1">
                <template v-if="fila.abierto">
                  <div
                    v-for="tramo in fila.tramos" :key="tramo.id"
                    class="absolute inset-y-0 flex items-center overflow-hidden border-l-2 px-1.5"
                    :class="tramo.tipo === 'bloqueo'
                      ? 'border-l-red-600 bg-red-100'
                      : 'border-l-dark bg-nodo-400'"
                    :style="{ left: tramo.izquierda + '%', width: tramo.ancho + '%' }"
                    :title="`${tramo.etiqueta} · ${tramo.inicio}–${tramo.fin}`"
                  >
                    <span class="truncate font-mono text-[0.625rem] text-dark">{{ tramo.etiqueta }}</span>
                  </div>

                  <!-- La línea de «ahora»: dice de un vistazo en qué momento del día vamos. -->
                  <div
                    v-if="fila.ahora_pct !== null"
                    class="pointer-events-none absolute inset-y-0 w-0.5 bg-red-600"
                    :style="{ left: fila.ahora_pct + '%' }"
                    aria-hidden="true"
                  />
                </template>

                <p v-else class="flex h-full items-center px-2 text-[0.6875rem] text-dark/40">
                  Cerrado
                </p>
              </div>

              <div class="ml-auto w-32 shrink-0 text-right lg:ml-0">
                <Estado
                  v-if="fila.ocupado_ahora"
                  tono="problema"
                  :texto="`libre ${fila.se_libera}`"
                />
                <Estado v-else-if="fila.abierto" tono="bien" texto="libre" />
              </div>
            </li>
          </ul>
        </div>
      </Panel>

      <!-- 4 · Indicadores. Al final: informan, no urgen. -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <div
          v-for="ind in tarjetas" :key="ind.clave"
          class="border border-dark/20 bg-white p-3"
        >
          <p class="flex items-center gap-1.5 font-mono text-[0.625rem] uppercase tracking-[0.1em] text-dark/50">
            <component :is="ind.icono" :size="12" aria-hidden="true" />
            {{ ind.etiqueta }}
          </p>
          <p class="mt-1 font-display text-2xl font-extrabold text-dark">
            {{ indicadores[ind.clave] ?? 0 }}
          </p>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
