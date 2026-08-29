<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { CalendarPlus, Lightbulb, LogIn, LogOut, Megaphone, ScanFace, Clock } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import BandaMembresia from '@/Components/Portal/BandaMembresia.vue'
import MedidorDeBolsa from '@/Components/Portal/MedidorDeBolsa.vue'
import TarjetaPortal from '@/Components/Portal/TarjetaPortal.vue'

/**
 * Inicio del portal (Fase 2.1).
 *
 * El orden de la página **es** el contenido: estado de la membresía, medidores,
 * próxima reserva. Todo lo demás va después porque no responde a «¿cuánto me
 * queda y hasta cuándo?», que es lo que la persona viene a saber.
 */
const props = defineProps<{
  estadoMembresia: any
  suscripcion: any
  medidores: any[]
  proximaReserva: any
  checkinActual: any
  asesoriasPendientes: number
  avisos: any[]
  comunicados: any[]
  faceIdPendiente: boolean
}>()

const usuario = usePage().props.auth.user as { name: string }
const nombre = computed(() => usuario.name.split(' ')[0])

const formEntrada = useForm({})
const formSalida = useForm({})

/**
 * Cuenta regresiva de la próxima reserva.
 *
 * Se recalcula cada 30 s y no cada segundo: la reserva más cercana está a horas
 * de distancia, y un temporizador por segundo solo gastaría batería para
 * repintar el mismo texto treinta veces.
 */
const ahora = ref(Date.now())
let reloj: number | undefined

onMounted(() => { reloj = window.setInterval(() => { ahora.value = Date.now() }, 30_000) })
onUnmounted(() => { if (reloj) window.clearInterval(reloj) })

const cuentaRegresiva = computed(() => {
  if (!props.proximaReserva) return null

  const faltan = new Date(props.proximaReserva.empieza_en).getTime() - ahora.value
  if (faltan <= 0) return 'Está ocurriendo ahora'

  const minutos = Math.round(faltan / 60_000)
  if (minutos < 60) return `En ${minutos} min`

  const horas = Math.floor(minutos / 60)
  if (horas < 24) return `En ${horas} h ${minutos % 60 ? `${minutos % 60} min` : ''}`.trim()

  const dias = Math.round(horas / 24)
  return `En ${dias} ${dias === 1 ? 'día' : 'días'}`
})

const fechaLarga = (iso: string) =>
  new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', {
    weekday: 'long', day: 'numeric', month: 'long',
  })

const horaCorta = (iso: string) =>
  new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })

/**
 * Dos tablas de tipos distintas, porque son dos tablas distintas:
 * `anuncios_coworking.tipo` es general/oferta/evento/mantenimiento y
 * `comunicados.tipo` es info/alerta/pago/reserva/bienvenida. El `??` de abajo
 * cubre el día que alguien añada un valor al enum sin pasar por aquí: un aviso
 * sin estilo se ve raro, uno sin borde no se ve.
 */
const tonosAviso: Record<string, string> = {
  // Comunicados personales.
  alerta: 'border-coral bg-coral/10',
  info: 'border-dark/20 bg-white',
  pago: 'border-nodo-500 bg-nodo-50',
  bienvenida: 'border-lima bg-lima/15',
  reserva: 'border-dark/20 bg-white',
  // Anuncios del espacio.
  general: 'border-dark/20 bg-white',
  oferta: 'border-nodo-500 bg-nodo-50',
  evento: 'border-lima bg-lima/15',
  mantenimiento: 'border-coral bg-coral/10',
}

const tonoDe = (tipo: string) => tonosAviso[tipo] ?? tonosAviso.info
</script>

<template>
  <Head title="Mi portal" />

  <PortalLayout>
    <!-- Saludo -->
    <div class="mb-6">
      <p class="etiqueta-tecnica mb-2 text-dark/70">Tu portal</p>
      <h1 class="font-display text-display-md font-extrabold text-dark">
        Hola, {{ nombre }}
      </h1>
    </div>

    <!-- 1 · Estado de la membresía: arriba de todo, siempre. -->
    <BandaMembresia
      :estado="estadoMembresia"
      :dias-restantes="suscripcion?.dias_restantes"
      class="mb-6"
    />

    <!-- Face ID pendiente: bloquea el acceso físico, así que va pronto. -->
    <div
      v-if="faceIdPendiente"
      class="mb-6 flex items-start gap-3 border-2 border-dark bg-nodo-400 p-4 shadow-dura-sm"
    >
      <ScanFace :size="22" class="mt-0.5 shrink-0 text-dark" aria-hidden="true" />
      <div>
        <p class="font-display text-sm font-bold text-dark">Te falta registrar tu Face ID</p>
        <p class="mt-1 font-body text-sm text-dark/80">
          Pásate por recepción la próxima vez que vengas. Sin él tendrás que pedir que te abran cada vez.
        </p>
      </div>
    </div>

    <!-- 2 · Medidores de bolsa. -->
    <section v-if="medidores.length" class="mb-6">
      <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
        <span class="font-mono">01</span> Lo que te queda este ciclo
        <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
      </h2>

      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MedidorDeBolsa
          v-for="medidor in medidores"
          :key="medidor.bolsa"
          :medidor="medidor"
          compacto
        />
      </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- 3 · Próxima reserva, destacada. -->
      <div class="lg:col-span-2 space-y-6">
        <TarjetaPortal
          v-if="proximaReserva"
          etiqueta="Tu próxima reserva"
          numero="02"
          fondo="oscuro"
        >
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
              <p class="font-display text-display-sm font-extrabold text-white">
                {{ proximaReserva.espacio }}
              </p>
              <p class="mt-1 font-body text-sm first-letter:uppercase text-cream/80">
                {{ fechaLarga(proximaReserva.fecha) }}
              </p>
              <p class="mt-3 font-mono text-lg text-nodo-400">
                {{ proximaReserva.inicio }} – {{ proximaReserva.fin }}
              </p>
            </div>

            <div class="text-right">
              <p class="etiqueta-tecnica text-nodo-400">Empieza</p>
              <p class="mt-1 font-display text-xl font-extrabold text-white">{{ cuentaRegresiva }}</p>
            </div>
          </div>

          <Link
            :href="route('portal.reservas')"
            class="mt-5 inline-flex min-h-[44px] items-center gap-2 border-2 border-nodo-400 px-4
                   font-display text-sm font-bold text-nodo-400 transition-all duration-200 ease-salida
                   hover:bg-nodo-400 hover:text-dark focus-visible:outline focus-visible:outline-2
                   focus-visible:outline-offset-2 focus-visible:outline-nodo-400"
          >
            Ver mis reservas
          </Link>
        </TarjetaPortal>

        <TarjetaPortal v-else etiqueta="Tu próxima reserva" numero="02" fondo="crema">
          <p class="font-body text-cuerpo text-dark/70">
            No tienes ninguna reserva por delante.
          </p>
          <Link
            :href="route('portal.reservar')"
            class="mt-4 inline-flex min-h-[48px] items-center gap-2 border-2 border-dark bg-nodo-400 px-5
                   font-display text-sm font-bold text-dark transition-all duration-200 ease-salida
                   hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-dura-sm
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                   focus-visible:outline-dark"
          >
            <CalendarPlus :size="18" aria-hidden="true" /> Reservar un espacio
          </Link>
        </TarjetaPortal>

        <!-- Avisos de Nódico. -->
        <section v-if="avisos.length || comunicados.length">
          <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
            <span class="font-mono">03</span> Avisos
            <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
          </h2>

          <ul class="space-y-3">
            <li
              v-for="aviso in avisos"
              :key="`a-${aviso.id}`"
              class="flex items-start gap-3 border-2 p-4"
              :class="tonoDe(aviso.tipo)"
            >
              <Megaphone :size="18" class="mt-0.5 shrink-0 text-dark" aria-hidden="true" />
              <div>
                <p class="font-display text-sm font-bold text-dark">{{ aviso.titulo }}</p>
                <p class="mt-1 font-body text-sm text-dark/70">{{ aviso.contenido }}</p>
              </div>
            </li>

            <li
              v-for="comunicado in comunicados"
              :key="`c-${comunicado.id}`"
              class="flex items-start gap-3 border-2 p-4"
              :class="tonoDe(comunicado.tipo)"
            >
              <Megaphone :size="18" class="mt-0.5 shrink-0 text-dark" aria-hidden="true" />
              <div>
                <p class="font-display text-sm font-bold text-dark">{{ comunicado.titulo }}</p>
                <p class="mt-1 font-body text-sm text-dark/70">{{ comunicado.mensaje }}</p>
              </div>
            </li>
          </ul>
        </section>
      </div>

      <!-- Columna lateral: acciones. -->
      <div class="space-y-4">
        <!-- Estás dentro / entrar. -->
        <TarjetaPortal padding="sm" :fondo="checkinActual ? 'nodo' : 'blanco'">
          <template v-if="checkinActual">
            <p class="etiqueta-tecnica text-dark/70">Estás en Nódico</p>
            <p class="mt-2 flex items-center gap-2 font-display text-xl font-extrabold text-dark">
              <Clock :size="18" aria-hidden="true" />
              Desde las {{ horaCorta(checkinActual.hora_entrada) }}
            </p>
            <button
              type="button"
              :disabled="formSalida.processing"
              class="mt-4 inline-flex min-h-[48px] w-full items-center justify-center gap-2 border-2
                     border-dark bg-white px-4 font-display text-sm font-bold text-dark
                     transition-all duration-200 ease-salida hover:-translate-y-0.5 hover:shadow-dura-sm
                     disabled:opacity-60 focus-visible:outline focus-visible:outline-2
                     focus-visible:outline-offset-2 focus-visible:outline-dark"
              @click="formSalida.post(route('portal.checkin.salida'), { preserveScroll: true })"
            >
              <LogOut :size="17" aria-hidden="true" /> Registrar mi salida
            </button>
          </template>

          <template v-else>
            <p class="etiqueta-tecnica text-dark/70">Acceso</p>
            <p class="mt-2 font-body text-sm text-dark/70">
              Registra tu entrada al llegar.
            </p>
            <button
              type="button"
              :disabled="formEntrada.processing || !estadoMembresia.tiene"
              class="mt-4 inline-flex min-h-[48px] w-full items-center justify-center gap-2 border-2
                     border-dark bg-nodo-400 px-4 font-display text-sm font-bold text-dark
                     transition-all duration-200 ease-salida hover:-translate-y-0.5 hover:shadow-dura-sm
                     disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline
                     focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
              @click="formEntrada.post(route('portal.checkin.entrada'), { preserveScroll: true })"
            >
              <LogIn :size="17" aria-hidden="true" /> Registrar mi entrada
            </button>
          </template>
        </TarjetaPortal>

        <!-- Accesos rápidos. -->
        <TarjetaPortal padding="sm" fondo="crema">
          <p class="etiqueta-tecnica mb-3 text-dark/70">Accesos rápidos</p>

          <ul class="space-y-2">
            <li>
              <Link
                :href="route('portal.reservar')"
                class="flex min-h-[44px] items-center gap-2.5 border-2 border-dark bg-white px-3
                       font-display text-sm font-bold text-dark transition-all duration-200 ease-salida
                       hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-dura-sm
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                       focus-visible:outline-dark"
              >
                <CalendarPlus :size="17" aria-hidden="true" /> Reservar un espacio
              </Link>
            </li>
            <li>
              <Link
                :href="route('portal.asesoria')"
                class="flex min-h-[44px] items-center justify-between gap-2.5 border-2 border-dark
                       bg-white px-3 font-display text-sm font-bold text-dark transition-all
                       duration-200 ease-salida hover:-translate-x-0.5 hover:-translate-y-0.5
                       hover:shadow-dura-sm focus-visible:outline focus-visible:outline-2
                       focus-visible:outline-offset-2 focus-visible:outline-dark"
              >
                <span class="flex items-center gap-2.5">
                  <Lightbulb :size="17" aria-hidden="true" /> Asesoría IYEM
                </span>
                <span
                  v-if="asesoriasPendientes"
                  class="flex h-5 min-w-5 items-center justify-center bg-nodo-400 px-1
                         font-mono text-[0.6875rem] text-dark"
                >{{ asesoriasPendientes }}</span>
              </Link>
            </li>
          </ul>
        </TarjetaPortal>
      </div>
    </div>
  </PortalLayout>
</template>
