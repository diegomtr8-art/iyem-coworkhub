<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Check, Mail, ScanFace, UserPlus, Users, X } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import EncabezadoPortal from '@/Components/Portal/EncabezadoPortal.vue'
import TarjetaPortal from '@/Components/Portal/TarjetaPortal.vue'
import BandaMembresia from '@/Components/Portal/BandaMembresia.vue'
import MedidorDeBolsa from '@/Components/Portal/MedidorDeBolsa.vue'

/** Mi membresía (Fase 2.4). */
defineProps<{
  estadoMembresia: any
  suscripcion: any
  medidores: any[]
  acompanante: any
  historial: any[]
  otrosPlanes: any[]
  facturacion: {
    cobro_en_linea: boolean
    metodo_pago: { marca: string; ultimos4: string } | null
    tiene_recurrente: boolean
    renovacion_activa: boolean
    en_periodo_de_gracia: boolean
  }
}>()

function cancelarRenovacion() {
  router.post(route('portal.membresia.cancelar'), {}, { preserveScroll: true })
}
function reactivarRenovacion() {
  router.post(route('portal.membresia.reactivar'), {}, { preserveScroll: true })
}

/** Nodo Match: asignar al acompañante por su correo. */
const formAcompanante = useForm({ email: '' })
function asignarAcompanante() {
  formAcompanante.post(route('portal.membresia.acompanante'), {
    preserveScroll: true,
    onSuccess: () => formAcompanante.reset(),
  })
}
function quitarAcompanante() {
  router.delete(route('portal.membresia.acompanante.quitar'), { preserveScroll: true })
}

const precio = (v: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 0 }).format(v)

const fecha = (iso: string) =>
  new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })
</script>

<template>
  <Head title="Mi membresía" />

  <PortalLayout>
    <EncabezadoPortal titulo="Mi membresía" etiqueta="Membresía" numero="01" />

    <BandaMembresia
      :estado="estadoMembresia"
      :dias-restantes="suscripcion?.dias_restantes"
      class="mb-6"
    />

    <div v-if="suscripcion" class="space-y-6">
      <!-- Plan actual, desglosado. -->
      <TarjetaPortal fondo="oscuro" padding="lg">
        <div class="flex flex-wrap items-start justify-between gap-6">
          <div>
            <p class="etiqueta-tecnica text-nodo-400">Tu plan</p>
            <h2 class="mt-2 font-display text-display-md font-extrabold text-white">
              {{ suscripcion.plan.nombre }}
            </h2>
            <p v-if="suscripcion.plan.subtitulo" class="mt-1 font-body text-cream/80">
              {{ suscripcion.plan.subtitulo }}
            </p>
          </div>

          <div class="text-right">
            <p class="font-display text-display-sm font-extrabold text-nodo-400">
              {{ precio(suscripcion.plan.precio) }}
            </p>
            <p class="font-body text-sm text-cream/70">{{ suscripcion.plan.periodo_label }}</p>
          </div>
        </div>

        <ul class="mt-6 grid gap-2.5 sm:grid-cols-2">
          <li
            v-for="(linea, i) in suscripcion.plan.incluye" :key="i"
            class="flex items-start gap-2.5 font-body text-sm text-cream"
          >
            <Check :size="17" class="mt-0.5 shrink-0 text-nodo-400" aria-hidden="true" />
            {{ linea }}
          </li>
        </ul>

        <dl class="mt-7 grid gap-4 border-t-2 border-white/15 pt-6 sm:grid-cols-3">
          <div>
            <dt class="etiqueta-tecnica text-nodo-400">Desde</dt>
            <dd class="mt-1 font-body text-sm text-cream">{{ fecha(suscripcion.fecha_inicio) }}</dd>
          </div>
          <div>
            <dt class="etiqueta-tecnica text-nodo-400">Hasta</dt>
            <dd class="mt-1 font-body text-sm text-cream">{{ fecha(suscripcion.fecha_fin) }}</dd>
          </div>
          <div v-if="suscripcion.proximo_reinicio">
            <dt class="etiqueta-tecnica text-nodo-400">Tus horas se reinician</dt>
            <dd class="mt-1 font-body text-sm text-cream">{{ fecha(suscripcion.proximo_reinicio) }}</dd>
          </div>
        </dl>

        <!-- 4.A — el cobro ocurre siempre dentro de Nódico. Sin claves de Stripe,
             la pantalla se muestra en vista previa (no sale a buy.stripe.com). -->
        <Link
          :href="route('portal.contratar', { plan: suscripcion.plan.id })"
          class="mt-6 inline-flex min-h-[48px] items-center gap-2 border-2 border-nodo-400 bg-nodo-400
                 px-5 font-display text-sm font-bold text-dark transition-all duration-200 ease-salida
                 hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2
                 focus-visible:outline-offset-2 focus-visible:outline-nodo-400"
        >
          Renovar mi membresía
        </Link>

        <!-- 4.A — método de pago y renovación automática. -->
        <div v-if="facturacion.metodo_pago || facturacion.tiene_recurrente" class="mt-6 border-t-2 border-white/10 pt-5">
          <p v-if="facturacion.metodo_pago" class="font-body text-sm text-cream/70">
            Método de pago: {{ facturacion.metodo_pago.marca }} ···· {{ facturacion.metodo_pago.ultimos4 }}
          </p>
          <p v-if="facturacion.en_periodo_de_gracia" class="mt-2 font-body text-sm text-coral">
            Renovación cancelada. Sigues con acceso hasta el fin del periodo pagado.
            <button type="button" @click="reactivarRenovacion" class="font-bold underline">Reactivar</button>
          </p>
          <p v-else-if="facturacion.renovacion_activa" class="mt-2 font-body text-sm text-cream/70">
            Se renueva sola.
            <button type="button" @click="cancelarRenovacion" class="font-bold underline hover:text-coral">Cancelar renovación</button>
          </p>
        </div>
      </TarjetaPortal>

      <!-- Bolsas del ciclo. -->
      <section v-if="medidores.length">
        <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
          <span class="font-mono">02</span> Tus bolsas este ciclo
          <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
        </h2>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <MedidorDeBolsa v-for="m in medidores" :key="m.bolsa" :medidor="m" />
        </div>
      </section>

      <!-- Nodo Match: el acompañante. -->
      <TarjetaPortal
        v-if="acompanante.admitido"
        etiqueta="Tu acompañante"
        numero="03"
        fondo="crema"
      >
        <div v-if="acompanante.usuario" class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <span
              class="flex h-11 w-11 items-center justify-center border-2 border-dark bg-nodo-400
                     font-display text-sm font-extrabold text-dark"
              aria-hidden="true"
            >{{ acompanante.usuario.name.charAt(0).toUpperCase() }}</span>
            <div>
              <p class="font-display text-sm font-bold text-dark">{{ acompanante.usuario.name }}</p>
              <p class="font-body text-xs text-dark/70">{{ acompanante.usuario.email }}</p>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <p
              class="flex items-center gap-2 border-2 px-3 py-2 font-body text-xs"
              :class="acompanante.face_id_ok
                ? 'border-dark bg-white text-dark'
                : 'border-dark bg-nodo-400 text-dark'"
            >
              <ScanFace :size="15" aria-hidden="true" />
              {{ acompanante.face_id_ok ? 'Face ID registrado' : 'Face ID pendiente' }}
            </p>
            <button
              type="button" @click="quitarAcompanante"
              class="flex min-h-[44px] items-center gap-1.5 border-2 border-dark/25 px-3 font-display
                     text-xs font-bold text-dark transition-colors hover:border-coral hover:text-coral
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
            >
              <X :size="14" aria-hidden="true" /> Quitar
            </button>
          </div>
        </div>

        <!-- Sin acompañante: se agrega por correo. La persona debe tener cuenta activa. -->
        <form v-else class="flex flex-col gap-3" @submit.prevent="asignarAcompanante">
          <div class="flex items-start gap-3">
            <UserPlus :size="20" class="mt-0.5 shrink-0 text-dark" aria-hidden="true" />
            <div>
              <p class="font-body text-sm text-dark">
                Tu plan es para dos personas. Agrega a tu acompañante con su correo.
              </p>
              <p class="mt-1 font-body text-xs text-dark/70">
                Debe tener una cuenta en Nódico con su correo verificado. Luego pásalo por recepción
                para registrar su Face ID.
              </p>
            </div>
          </div>

          <div class="flex flex-col gap-2 sm:flex-row">
            <div class="relative flex-1">
              <Mail :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-dark/40" aria-hidden="true" />
              <input
                v-model="formAcompanante.email"
                type="email" inputmode="email" autocomplete="off"
                placeholder="correo@ejemplo.com"
                aria-label="Correo del acompañante"
                class="min-h-[48px] w-full border-2 border-dark/25 bg-white pl-9 pr-3 font-body text-base
                       text-dark focus:border-dark focus:outline-none"
                :class="formAcompanante.errors.email ? 'border-coral' : ''"
              />
            </div>
            <button
              type="submit" :disabled="formAcompanante.processing"
              class="flex min-h-[48px] items-center justify-center gap-2 border-2 border-dark bg-nodo-400
                     px-5 font-display text-sm font-bold text-dark transition-all duration-200 ease-salida
                     hover:-translate-y-0.5 hover:shadow-dura-sm disabled:opacity-50
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
            >
              {{ formAcompanante.processing ? 'Asignando…' : 'Asignar acompañante' }}
            </button>
          </div>

          <p v-if="formAcompanante.errors.email" class="border-2 border-coral bg-coral/10 px-3 py-2 font-body text-sm text-dark" role="alert">
            {{ formAcompanante.errors.email }}
          </p>
        </form>

        <p class="mt-4 flex items-start gap-2 border-t-2 border-dark/10 pt-4 font-body text-xs text-dark/70">
          <Users :size="14" class="mt-0.5 shrink-0" aria-hidden="true" />
          Las horas de sala son una sola bolsa compartida entre los dos, y el tope diario
          también es del plan, no de cada persona.
        </p>
      </TarjetaPortal>

      <!-- Cambiar de plan. -->
      <section v-if="otrosPlanes.length">
        <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
          <span class="font-mono">04</span> Otros planes
          <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
        </h2>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <article
            v-for="plan in otrosPlanes" :key="plan.id"
            class="flex flex-col border-2 border-dark bg-white p-5 shadow-dura-sm transition-all
                   duration-200 ease-salida hover:-translate-x-1 hover:-translate-y-1 hover:shadow-dura"
          >
            <h3 class="font-display text-display-sm font-extrabold text-dark">{{ plan.nombre }}</h3>
            <p class="mt-1 font-display text-xl font-extrabold text-dark">
              {{ precio(plan.precio) }}
              <span class="font-body text-xs font-normal text-dark/70">{{ plan.periodo_label }}</span>
            </p>

            <ul class="mt-4 flex-1 space-y-1.5">
              <li
                v-for="(linea, i) in plan.incluye" :key="i"
                class="flex items-start gap-2 font-body text-xs text-dark/80"
              >
                <Check :size="14" class="mt-0.5 shrink-0 text-nodo-500" aria-hidden="true" />
                {{ linea }}
              </li>
            </ul>

            <Link
              :href="route('portal.contratar', { plan: plan.id })"
              class="mt-5 inline-flex min-h-[44px] items-center justify-center gap-2 border-2
                     border-dark bg-nodo-400 px-4 font-display text-sm font-bold text-dark
                     transition-colors hover:bg-nodo-500 focus-visible:outline focus-visible:outline-2
                     focus-visible:outline-offset-2 focus-visible:outline-dark"
            >
              Cambiarme
            </Link>
          </article>
        </div>

        <p class="mt-4 font-body text-xs text-dark/70">
          Al cambiar de plan, pásate por recepción para que ajusten tus horas del ciclo en curso.
        </p>
      </section>
    </div>

    <!-- Historial. -->
    <section v-if="historial.length" class="mt-10">
      <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
        <span class="font-mono">05</span> Membresías anteriores
        <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
      </h2>

      <ul class="divide-y-2 divide-dark/10 border-2 border-dark/15 bg-white">
        <li
          v-for="s in historial" :key="s.id"
          class="flex flex-wrap items-center justify-between gap-3 p-4"
        >
          <div>
            <p class="font-display text-sm font-bold text-dark">{{ s.plan }}</p>
            <p class="mt-0.5 font-body text-xs text-dark/70">
              {{ fecha(s.fecha_inicio) }} — {{ fecha(s.fecha_fin) }}
            </p>
          </div>
          <div class="text-right">
            <p class="font-body text-sm text-dark">{{ precio(s.precio_pagado) }}</p>
            <p class="font-mono text-[0.6875rem] uppercase tracking-[0.12em] text-dark/60">{{ s.estatus }}</p>
          </div>
        </li>
      </ul>
    </section>
  </PortalLayout>
</template>
