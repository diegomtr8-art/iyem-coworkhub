<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { CalendarPlus, Clock, MapPin, AlertTriangle, X } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import EncabezadoPortal from '@/Components/Portal/EncabezadoPortal.vue'
import TarjetaPortal from '@/Components/Portal/TarjetaPortal.vue'

/**
 * Mis reservas (Fase 2.6).
 *
 * La regla de esta pantalla: **el botón de cancelar dice si devuelve las horas
 * antes de pulsarlo**, y cuánto falta para el límite. Nadie debe descubrir la
 * penalización después de aceptarla.
 */
const props = defineProps<{
  proximas: any[]
  pasadas: any[]
  suscripcion: any
}>()

const cancelando = ref<number | null>(null)
const confirmando = ref<any | null>(null)

const numero = (v: number) => (Number.isInteger(v) ? String(v) : v.toFixed(1).replace(/\.0$/, ''))

const fechaLarga = (iso: string) =>
  new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', {
    weekday: 'long', day: 'numeric', month: 'long',
  })

/** Cuánto falta para que la cancelación empiece a penalizar. */
function margen(reserva: any): string | null {
  if (!reserva.cancelar_devuelve) return null

  const restan = new Date(reserva.limite_cancelacion).getTime() - Date.now()
  if (restan <= 0) return null

  const horas = Math.floor(restan / 3_600_000)
  const minutos = Math.round((restan % 3_600_000) / 60_000)

  if (horas >= 24) {
    const dias = Math.floor(horas / 24)
    return `${dias} ${dias === 1 ? 'día' : 'días'}`
  }
  return horas > 0 ? `${horas} h ${minutos} min` : `${minutos} min`
}

const estados: Record<string, { texto: string; clase: string }> = {
  Confirmada: { texto: 'Confirmada', clase: 'border-dark bg-white text-dark' },
  Completada: { texto: 'Completada', clase: 'border-dark/25 bg-cream text-dark/70' },
  Cancelada:  { texto: 'Cancelada',  clase: 'border-dark/25 bg-cream text-dark/70' },
  No_Show:    { texto: 'No asististe', clase: 'border-coral bg-coral/15 text-dark' },
}

function cancelar(reserva: any) {
  cancelando.value = reserva.id
  router.delete(route('portal.reservas.cancel', reserva.id), {
    preserveScroll: true,
    onFinish: () => { cancelando.value = null; confirmando.value = null },
  })
}
</script>

<template>
  <Head title="Mis reservas" />

  <PortalLayout>
    <EncabezadoPortal titulo="Mis reservas" etiqueta="Reservas" numero="01">
      <Link
        :href="route('portal.reservar')"
        class="mt-5 inline-flex min-h-[48px] items-center gap-2 border-2 border-dark bg-nodo-400 px-5
               font-display text-sm font-bold text-dark transition-all duration-200 ease-salida
               hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-dura-sm
               focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
               focus-visible:outline-dark"
      >
        <CalendarPlus :size="18" aria-hidden="true" /> Reservar un espacio
      </Link>
    </EncabezadoPortal>

    <!-- ── Próximas ────────────────────────────────────────────────────── -->
    <section class="mb-10">
      <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
        <span class="font-mono">02</span> Próximas
        <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
      </h2>

      <TarjetaPortal v-if="!proximas.length" fondo="crema">
        <p class="font-body text-cuerpo text-dark/70">No tienes reservas por delante.</p>
      </TarjetaPortal>

      <ul v-else class="space-y-4">
        <li
          v-for="reserva in proximas" :key="reserva.id"
          class="border-2 border-dark bg-white p-5 shadow-dura-sm"
        >
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <h3 class="font-display text-display-sm font-extrabold text-dark">
                {{ reserva.espacio?.nombre }}
              </h3>
              <p class="mt-1 flex items-center gap-1.5 font-body text-sm first-letter:uppercase text-dark/70">
                <MapPin :size="14" aria-hidden="true" /> {{ reserva.espacio?.tipo_label }}
              </p>
              <p class="mt-3 font-body text-sm first-letter:uppercase text-dark">{{ fechaLarga(reserva.fecha) }}</p>
              <p class="mt-1 flex items-center gap-1.5 font-mono text-lg text-dark">
                <Clock :size="16" aria-hidden="true" />
                {{ reserva.hora_inicio.slice(0, 5) }} – {{ reserva.hora_fin.slice(0, 5) }}
              </p>
              <p class="mt-2 font-body text-xs text-dark/70">
                Consume {{ numero(reserva.horas) }} h de {{ reserva.bolsa_etiqueta?.toLowerCase() }}
              </p>
            </div>

            <div class="flex flex-col items-start gap-3 sm:items-end">
              <span
                class="border-2 px-2.5 py-1 font-mono text-[0.6875rem] uppercase tracking-[0.12em]"
                :class="estados[reserva.estatus]?.clase"
              >{{ estados[reserva.estatus]?.texto ?? reserva.estatus }}</span>

              <!-- El aviso va ANTES del botón, no después de pulsarlo. -->
              <p
                v-if="reserva.cancelar_devuelve"
                class="max-w-[16rem] font-body text-xs text-dark/70 sm:text-right"
              >
                Si cancelas ahora recuperas las
                {{ numero(reserva.horas) }} h.
                <template v-if="margen(reserva)">
                  Te quedan <strong class="text-dark">{{ margen(reserva) }}</strong> para hacerlo.
                </template>
              </p>

              <p
                v-else
                class="flex max-w-[16rem] items-start gap-1.5 font-body text-xs text-dark sm:text-right"
              >
                <AlertTriangle :size="14" class="mt-0.5 shrink-0 text-coral" aria-hidden="true" />
                <span>Ya pasó el plazo: si cancelas, las {{ numero(reserva.horas) }} h se consumen igual.</span>
              </p>

              <button
                type="button"
                :disabled="cancelando === reserva.id"
                class="inline-flex min-h-[44px] items-center gap-2 border-2 border-dark/30 px-4
                       font-display text-sm font-bold text-dark transition-all duration-200 ease-salida
                       hover:border-coral hover:bg-coral/10 disabled:opacity-50
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                       focus-visible:outline-dark"
                @click="confirmando = reserva"
              >
                <X :size="16" aria-hidden="true" /> Cancelar
              </button>
            </div>
          </div>
        </li>
      </ul>
    </section>

    <!-- ── Pasadas ─────────────────────────────────────────────────────── -->
    <section>
      <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
        <span class="font-mono">03</span> Anteriores
        <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
      </h2>

      <TarjetaPortal v-if="!pasadas.length" fondo="crema">
        <p class="font-body text-cuerpo text-dark/70">Todavía no tienes historial.</p>
      </TarjetaPortal>

      <ul v-else class="divide-y-2 divide-dark/10 border-2 border-dark/15 bg-white">
        <li
          v-for="reserva in pasadas" :key="reserva.id"
          class="flex flex-wrap items-center justify-between gap-3 p-4"
        >
          <div class="min-w-0">
            <p class="font-display text-sm font-bold text-dark">{{ reserva.espacio?.nombre }}</p>
            <p class="mt-0.5 font-body text-xs first-letter:uppercase text-dark/70">
              {{ fechaLarga(reserva.fecha) }} ·
              {{ reserva.hora_inicio.slice(0, 5) }}–{{ reserva.hora_fin.slice(0, 5) }} ·
              {{ numero(reserva.horas) }} h
            </p>
          </div>

          <span
            class="border-2 px-2.5 py-1 font-mono text-[0.6875rem] uppercase tracking-[0.12em]"
            :class="estados[reserva.estatus]?.clase ?? 'border-dark/25 bg-cream text-dark/70'"
          >{{ estados[reserva.estatus]?.texto ?? reserva.estatus }}</span>
        </li>
      </ul>
    </section>

    <!-- ── Confirmación de cancelación ─────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="confirmando"
        class="fixed inset-0 z-50 flex items-end justify-center bg-tinta/70 p-4 sm:items-center"
        role="dialog" aria-modal="true" aria-labelledby="titulo-cancelar"
        @click.self="confirmando = null"
      >
        <div class="w-full max-w-md border-2 border-dark bg-cream p-6 shadow-dura pb-segura">
          <h2 id="titulo-cancelar" class="font-display text-display-sm font-extrabold text-dark">
            ¿Cancelar esta reserva?
          </h2>

          <p class="mt-3 font-body text-sm text-dark/80">
            {{ confirmando.espacio?.nombre }} ·
            <span class="first-letter:uppercase">{{ fechaLarga(confirmando.fecha) }}</span>,
            {{ confirmando.hora_inicio.slice(0, 5) }}–{{ confirmando.hora_fin.slice(0, 5) }}.
          </p>

          <p
            class="mt-4 border-2 p-3 font-body text-sm"
            :class="confirmando.cancelar_devuelve
              ? 'border-dark bg-white text-dark'
              : 'border-coral bg-coral/15 text-dark'"
          >
            <template v-if="confirmando.cancelar_devuelve">
              Recuperas las <strong>{{ numero(confirmando.horas) }} h</strong> en tu bolsa.
            </template>
            <template v-else>
              <strong>No recuperas las horas.</strong>
              Faltan menos de 2 h para la reserva, así que las
              {{ numero(confirmando.horas) }} h se consumen igual.
            </template>
          </p>

          <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button
              type="button"
              class="min-h-[48px] border-2 border-dark/30 px-5 font-display text-sm font-bold text-dark
                     transition-colors hover:border-dark focus-visible:outline focus-visible:outline-2
                     focus-visible:outline-offset-2 focus-visible:outline-dark"
              @click="confirmando = null"
            >Mejor no</button>

            <button
              type="button"
              :disabled="cancelando === confirmando.id"
              class="min-h-[48px] border-2 border-dark bg-dark px-5 font-display text-sm font-bold
                     text-white transition-all duration-200 ease-salida hover:-translate-y-0.5
                     disabled:opacity-60 focus-visible:outline focus-visible:outline-2
                     focus-visible:outline-offset-2 focus-visible:outline-dark"
              @click="cancelar(confirmando)"
            >Sí, cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </PortalLayout>
</template>
