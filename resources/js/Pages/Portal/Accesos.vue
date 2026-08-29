<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { DoorOpen, Receipt, ArrowDownRight, ArrowUpRight, Infinity as Infinito, AlertCircle } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import EncabezadoPortal from '@/Components/Portal/EncabezadoPortal.vue'
import TarjetaPortal from '@/Components/Portal/TarjetaPortal.vue'

/**
 * Mis accesos y mis pagos (Fase 2.8).
 *
 * Incluye el movimiento de horas en cristiano: es lo que permite al miembro
 * entender su saldo sin preguntar en recepción. Un contador que baja sin
 * explicar por qué genera una conversación; un libro de movimientos, no.
 */
defineProps<{
  accesos: any[]
  dias: any
  ilimitado: boolean
  pagos: any[]
  datosFiscales: { completos: boolean; url: string }
  facturacion: { emisor: string; dias_habiles: number; contacto: string }
  movimientos: any[]
}>()

const numero = (v: number | null) =>
  v === null ? '—' : Number.isInteger(v) ? String(v) : Number(v).toFixed(1).replace(/\.0$/, '')

const precio = (v: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v)

const fechaHora = (iso: string) =>
  new Date(iso).toLocaleString('es-MX', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })

const fechaCorta = (iso: string) =>
  new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })

const duracion = (minutos: number | null) => {
  if (minutos === null) return null
  const h = Math.floor(minutos / 60)
  const m = minutos % 60
  return h > 0 ? `${h} h ${m} min` : `${m} min`
}

const estadosPago: Record<string, string> = {
  Pagada: 'border-dark bg-white text-dark',
  Pendiente: 'border-dark bg-nodo-400 text-dark',
  Vencida: 'border-coral bg-coral/15 text-dark',
  Cancelada: 'border-dark/25 bg-cream text-dark/70',
}
</script>

<template>
  <Head title="Accesos y pagos" />

  <PortalLayout>
    <EncabezadoPortal titulo="Accesos y pagos" etiqueta="Historial" numero="01" />

    <!-- Días consumidos: solo en planes por día. -->
    <TarjetaPortal v-if="dias" fondo="oscuro" class="mb-6">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="etiqueta-tecnica text-nodo-400">Días de tu membresía</p>
          <p class="mt-2 font-display text-display-md font-extrabold text-white">
            {{ numero(dias.restante) }}
            <span class="font-body text-lg font-normal text-cream/70">de {{ numero(dias.cupo) }}</span>
          </p>
        </div>
        <p class="font-body text-sm text-cream/70">
          Puedes usarlos hasta el {{ fechaCorta(dias.vence_el) }}
        </p>
      </div>
    </TarjetaPortal>

    <TarjetaPortal v-else-if="ilimitado" fondo="crema" class="mb-6" padding="sm">
      <p class="flex items-center gap-2.5 font-body text-sm text-dark/80">
        <Infinito :size="18" class="shrink-0 text-dark" aria-hidden="true" />
        Tu plan tiene acceso ilimitado al coworking: entra las veces que quieras.
      </p>
    </TarjetaPortal>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Entradas y salidas. -->
      <section>
        <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
          <span class="font-mono">02</span> Entradas y salidas
          <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
        </h2>

        <TarjetaPortal v-if="!accesos.length" fondo="crema">
          <p class="font-body text-cuerpo text-dark/70">Todavía no tienes accesos registrados.</p>
        </TarjetaPortal>

        <ul v-else class="divide-y-2 divide-dark/10 border-2 border-dark/15 bg-white">
          <li v-for="acceso in accesos" :key="acceso.id" class="flex items-center justify-between gap-3 p-4">
            <div class="min-w-0">
              <p class="flex items-center gap-2 font-display text-sm font-bold text-dark">
                <DoorOpen :size="15" class="shrink-0" aria-hidden="true" />
                {{ acceso.espacio ?? 'Nódico' }}
              </p>
              <p class="mt-0.5 font-body text-xs text-dark/70">
                {{ fechaHora(acceso.entrada) }}
                <template v-if="acceso.salida"> — {{ fechaHora(acceso.salida) }}</template>
              </p>
            </div>

            <span
              v-if="acceso.abierto"
              class="shrink-0 border-2 border-dark bg-nodo-400 px-2.5 py-1 font-mono text-[0.6875rem]
                     uppercase tracking-[0.12em] text-dark"
            >Dentro</span>
            <span v-else class="shrink-0 font-mono text-xs text-dark/70">
              {{ duracion(acceso.minutos) }}
            </span>
          </li>
        </ul>
      </section>

      <!-- Pagos. -->
      <section>
        <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
          <span class="font-mono">03</span> Pagos
          <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
        </h2>

        <TarjetaPortal v-if="!pagos.length" fondo="crema">
          <p class="font-body text-cuerpo text-dark/70">Todavía no hay pagos registrados.</p>
        </TarjetaPortal>

        <ul v-else class="divide-y-2 divide-dark/10 border-2 border-dark/15 bg-white">
          <li v-for="pago in pagos" :key="pago.id" class="flex items-center justify-between gap-3 p-4">
            <div class="min-w-0">
              <p class="font-display text-sm font-bold text-dark">{{ pago.concepto }}</p>
              <p class="mt-0.5 font-body text-xs text-dark/70">
                <template v-if="pago.folio">{{ pago.folio }} · </template>
                <template v-if="pago.fecha">{{ fechaCorta(pago.fecha) }}</template>
              </p>
            </div>

            <div class="shrink-0 text-right">
              <p class="font-display text-sm font-bold text-dark">{{ precio(pago.total) }}</p>
              <span
                class="mt-1 inline-block border-2 px-2 py-0.5 font-mono text-[0.625rem] uppercase tracking-[0.12em]"
                :class="estadosPago[pago.estatus] ?? 'border-dark/25 bg-cream text-dark/70'"
              >{{ pago.estatus }}</span>
            </div>
          </li>
        </ul>

        <!-- Cómo pedir factura, aquí donde están los pagos. -->
        <div
          class="mt-4 flex items-start gap-3 border-2 p-4"
          :class="datosFiscales.completos ? 'border-dark/20 bg-white' : 'border-dark bg-nodo-400'"
        >
          <component
            :is="datosFiscales.completos ? Receipt : AlertCircle"
            :size="18" class="mt-0.5 shrink-0 text-dark" aria-hidden="true"
          />
          <div>
            <p class="font-display text-sm font-bold text-dark">
              {{ datosFiscales.completos ? '¿Necesitas factura?' : 'Te faltan datos fiscales' }}
            </p>
            <p class="mt-1 font-body text-xs text-dark/80">
              <template v-if="datosFiscales.completos">
                Escribe a
                <a
                  :href="`mailto:${facturacion.contacto}`"
                  class="underline decoration-2 underline-offset-2 hover:text-dark"
                >{{ facturacion.contacto }}</a>
                con el concepto. La emite {{ facturacion.emisor }} en unos
                {{ facturacion.dias_habiles }} días hábiles.
              </template>
              <template v-else>
                Sin ellos no podemos pedir tu factura a contabilidad.
              </template>
            </p>
            <Link
              :href="datosFiscales.url"
              class="mt-2 inline-block font-display text-xs font-bold text-dark underline
                     decoration-dark decoration-2 underline-offset-4 focus-visible:outline
                     focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
            >{{ datosFiscales.completos ? 'Revisar mis datos fiscales' : 'Llenar mis datos fiscales' }}</Link>
          </div>
        </div>
      </section>
    </div>

    <!-- Movimientos de horas: de dónde sale el saldo. -->
    <section v-if="movimientos.length" class="mt-10">
      <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
        <span class="font-mono">04</span> Movimientos de tus horas
        <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
      </h2>

      <p class="mb-4 font-body text-sm text-dark/70">
        Cada cambio en tus bolsas, con su motivo. Si algo no cuadra, esto es lo que hay que mirar.
      </p>

      <ul class="divide-y-2 divide-dark/10 border-2 border-dark/15 bg-white">
        <li v-for="m in movimientos" :key="m.id" class="flex items-start justify-between gap-3 p-4">
          <div class="flex min-w-0 items-start gap-3">
            <component
              :is="m.consume ? ArrowDownRight : ArrowUpRight"
              :size="17"
              class="mt-0.5 shrink-0"
              :class="m.consume ? 'text-dark/60' : 'text-dark'"
              aria-hidden="true"
            />
            <div class="min-w-0">
              <p class="font-display text-sm font-bold text-dark">{{ m.descripcion }}</p>
              <p class="mt-0.5 font-body text-xs text-dark/70">
                {{ m.bolsa }}
                <template v-if="m.espacio"> · {{ m.espacio }}</template>
              </p>
              <p v-if="m.nota" class="mt-1 font-body text-xs italic text-dark/60">{{ m.nota }}</p>
            </div>
          </div>

          <span class="shrink-0 font-mono text-xs text-dark/60">{{ fechaHora(m.fecha) }}</span>
        </li>
      </ul>
    </section>
  </PortalLayout>
</template>
