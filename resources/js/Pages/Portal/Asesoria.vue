<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Lightbulb, Clock, User as UserIcon, X, ExternalLink } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import EncabezadoPortal from '@/Components/Portal/EncabezadoPortal.vue'
import TarjetaPortal from '@/Components/Portal/TarjetaPortal.vue'

/**
 * Asesoría IYEM (Fase 2.7).
 *
 * La pantalla insiste en que **esto es una solicitud, no una reserva**: el
 * miembro propone día y franja, y recepción confirma con un asesor. Llamarlo
 * «reservar» haría esperar una confirmación inmediata que no va a llegar, y
 * cada expectativa rota acaba en un mensaje a recepción.
 */
const props = defineProps<{
  incluida: boolean
  bolsa: any
  planQueLaIncluye: any
  vigenciaHasta: string | null
  solicitudes: any[]
  franjas: string[]
  oferta: any[]
}>()

const form = useForm({
  tema_id: null as number | null,
  detalle: '',
  asesor_preferido_id: null as number | null,
  dia_preferido: '',
  horario_preferido: props.franjas[0] ?? '',
  horas: 1,
})

// El tema elegido y sus asesores, para ofrecer solo los que lo imparten.
const temaElegido = computed(() => {
  for (const grupo of props.oferta) {
    const t = grupo.temas.find((x: any) => x.id === form.tema_id)
    if (t) return t
  }
  return null
})
const asesoresDelTema = computed(() => temaElegido.value?.asesores ?? [])

function elegirTema(id: number) {
  form.tema_id = id
  form.asesor_preferido_id = null
  document.getElementById('form-asesoria')?.scrollIntoView({ behavior: 'smooth' })
}

const numero = (v: number | null) =>
  v === null ? '—' : Number.isInteger(v) ? String(v) : Number(v).toFixed(1).replace(/\.0$/, '')

const hoy = new Date().toISOString().slice(0, 10)

const sinSaldo = computed(() => props.bolsa && props.bolsa.restante !== null && props.bolsa.restante <= 0)

const fechaLarga = (iso: string) =>
  new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' })

const fechaHora = (iso: string) =>
  new Date(iso).toLocaleString('es-MX', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })

/** Color semántico del estado; el texto lo dice igual, no depende del color. */
const tonos: Record<string, string> = {
  bien: 'border-dark bg-white text-dark',
  atencion: 'border-dark bg-nodo-400 text-dark',
  problema: 'border-dark/30 bg-cream text-dark/70',
  neutro: 'border-dark/25 bg-cream text-dark/70',
}
</script>

<template>
  <Head title="Asesoría IYEM" />

  <PortalLayout>
    <EncabezadoPortal
      titulo="Asesoría IYEM"
      etiqueta="Asesoría"
      numero="01"
      descripcion="Una sesión con un asesor del Instituto Yucateco de Emprendedores."
    />

    <!-- El plan no la incluye: invitación, no un cero. -->
    <TarjetaPortal v-if="!incluida" fondo="crema" padding="lg">
      <div class="flex items-start gap-4">
        <Lightbulb :size="26" class="mt-1 shrink-0 text-dark" aria-hidden="true" />
        <div>
          <h2 class="font-display text-display-sm font-extrabold text-dark">
            Tu plan no incluye asesoría
          </h2>

          <template v-if="planQueLaIncluye">
            <p class="mt-3 font-body text-cuerpo text-dark/80">
              <strong>{{ planQueLaIncluye.nombre }}</strong> incluye horas de asesoría con un asesor
              del IYEM: alguien que te ayuda a ordenar tu modelo de negocio, tus finanzas o tu
              siguiente paso.
            </p>

            <div class="mt-5 flex flex-wrap gap-3">
              <a
                v-if="planQueLaIncluye.stripe_url"
                :href="planQueLaIncluye.stripe_url" target="_blank" rel="noopener noreferrer"
                class="inline-flex min-h-[48px] items-center gap-2 border-2 border-dark bg-nodo-400 px-5
                       font-display text-sm font-bold text-dark transition-all duration-200 ease-salida
                       hover:-translate-y-0.5 hover:shadow-dura-sm focus-visible:outline
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
              >
                Cambiarme a {{ planQueLaIncluye.nombre }} <ExternalLink :size="15" aria-hidden="true" />
              </a>
              <Link
                :href="route('portal.suscripcion')"
                class="inline-flex min-h-[48px] items-center border-2 border-dark/30 px-5
                       font-display text-sm font-bold text-dark transition-colors hover:border-dark
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                       focus-visible:outline-dark"
              >Ver todos los planes</Link>
            </div>
          </template>
        </div>
      </div>
    </TarjetaPortal>

    <template v-else>
      <!-- Saldo de la bolsa. -->
      <TarjetaPortal fondo="oscuro" class="mb-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <p class="etiqueta-tecnica text-nodo-400">Te quedan</p>
            <p class="mt-2 font-display text-display-md font-extrabold text-white">
              {{ numero(bolsa.restante) }}
              <span class="font-body text-lg font-normal text-cream/70">
                de {{ numero(bolsa.cupo) }} horas
              </span>
            </p>
          </div>
          <div class="space-y-1 text-right font-body text-xs text-cream/70">
            <p v-if="bolsa.tope_diario">Máximo {{ numero(bolsa.tope_diario) }} h al día</p>
            <p v-if="bolsa.reinicia_el">Se reinician el {{ fechaLarga(bolsa.reinicia_el) }}</p>
          </div>
        </div>
      </TarjetaPortal>

      <!-- D.3 — La oferta: temas por categoría, cada uno con sus asesores. -->
      <section v-if="oferta.length" class="mb-8">
        <div v-for="grupo in oferta" :key="grupo.categoria" class="mb-6">
          <h2 class="mb-3 font-display text-sm font-bold uppercase tracking-wide text-dark/60">{{ grupo.etiqueta }}</h2>
          <div class="grid gap-3 sm:grid-cols-2">
            <button
              v-for="tema in grupo.temas" :key="tema.id"
              type="button" @click="elegirTema(tema.id)"
              class="border-2 p-4 text-left transition-colors focus-visible:outline focus-visible:outline-2
                     focus-visible:outline-offset-2 focus-visible:outline-dark"
              :class="form.tema_id === tema.id ? 'border-dark bg-nodo-400' : 'border-dark/20 bg-white hover:border-dark/50'"
            >
              <p class="font-display text-base font-bold text-dark">{{ tema.nombre }}</p>
              <p v-if="tema.descripcion_corta" class="mt-1 font-body text-sm text-dark/70">{{ tema.descripcion_corta }}</p>
              <p v-if="tema.asesores.length" class="mt-2 font-body text-xs text-dark/50">
                {{ tema.asesores.length }} asesor{{ tema.asesores.length === 1 ? '' : 'es' }} disponible{{ tema.asesores.length === 1 ? '' : 's' }}
              </p>
            </button>
          </div>
        </div>
      </section>

      <!-- Solicitud. -->
      <form id="form-asesoria" class="mb-8" @submit.prevent="form.post(route('portal.asesoria.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('tema_id', 'detalle', 'asesor_preferido_id', 'dia_preferido'),
      })">
        <TarjetaPortal etiqueta="Pedir una asesoría" numero="02">
          <p class="mb-5 font-body text-sm text-dark/70">
            Elige un tema de arriba y dinos cuándo te viene bien. Recepción te asigna asesor y te
            confirma el horario: <strong class="text-dark">esto es una solicitud, no una reserva en firme</strong>.
            Las horas se descuentan solo cuando te confirmen.
          </p>

          <div class="space-y-5">
            <div>
              <label for="tema" class="mb-1.5 block font-display text-sm font-bold text-dark">
                Tema
              </label>
              <select
                id="tema" v-model="form.tema_id" required
                class="w-full border-2 bg-cream-50 px-3.5 py-3 text-base text-dark transition-colors
                       focus:bg-white focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-dark"
                :class="form.errors.tema_id ? 'border-coral' : 'border-dark/25 focus:border-nodo-500'"
              >
                <option :value="null" disabled>Elige un tema…</option>
                <optgroup v-for="grupo in oferta" :key="grupo.categoria" :label="grupo.etiqueta">
                  <option v-for="tema in grupo.temas" :key="tema.id" :value="tema.id">{{ tema.nombre }}</option>
                </optgroup>
              </select>
              <p v-if="form.errors.tema_id" class="mt-1.5 font-body text-xs text-coral">{{ form.errors.tema_id }}</p>
            </div>

            <div v-if="asesoresDelTema.length">
              <label for="asesor" class="mb-1.5 block font-display text-sm font-bold text-dark">
                Asesor preferido <span class="font-body font-normal text-dark/50">(opcional)</span>
              </label>
              <select
                id="asesor" v-model="form.asesor_preferido_id"
                class="w-full border-2 border-dark/25 bg-cream-50 px-3.5 py-3 text-base text-dark
                       transition-colors focus:border-nodo-500 focus:bg-white focus:outline
                       focus:outline-2 focus:outline-offset-2 focus:outline-dark"
              >
                <option :value="null">El que haya disponible</option>
                <option v-for="a in asesoresDelTema" :key="a.id" :value="a.id">{{ a.nombre }}</option>
              </select>
            </div>

            <div>
              <label for="detalle" class="mb-1.5 block font-display text-sm font-bold text-dark">
                Detalle <span class="font-body font-normal text-dark/50">(opcional)</span>
              </label>
              <textarea
                id="detalle" v-model="form.detalle" rows="2" maxlength="500"
                placeholder="Por ejemplo: quiero revisar mis precios y saber si me conviene facturar como RESICO."
                class="w-full border-2 border-dark/25 bg-cream-50 px-3.5 py-3 text-base text-dark transition-colors
                       placeholder:text-dark/40 focus:border-nodo-500 focus:bg-white focus:outline focus:outline-2
                       focus:outline-offset-2 focus:outline-dark"
              />
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
              <div>
                <label for="dia" class="mb-1.5 block font-display text-sm font-bold text-dark">
                  Día que prefieres
                </label>
                <input
                  id="dia" v-model="form.dia_preferido" type="date" required
                  :min="hoy" :max="vigenciaHasta ?? undefined"
                  :aria-invalid="!!form.errors.dia_preferido"
                  class="w-full border-2 bg-cream-50 px-3.5 py-3 text-base text-dark transition-colors
                         focus:bg-white focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-dark"
                  :class="form.errors.dia_preferido ? 'border-coral' : 'border-dark/25 focus:border-nodo-500'"
                />
                <p v-if="form.errors.dia_preferido" class="mt-1.5 font-body text-xs text-coral">
                  {{ form.errors.dia_preferido }}
                </p>
              </div>

              <div class="sm:col-span-2">
                <label for="franja" class="mb-1.5 block font-display text-sm font-bold text-dark">
                  Horario que prefieres
                </label>
                <select
                  id="franja" v-model="form.horario_preferido" required
                  class="w-full border-2 border-dark/25 bg-cream-50 px-3.5 py-3 text-base text-dark
                         transition-colors focus:border-nodo-500 focus:bg-white focus:outline
                         focus:outline-2 focus:outline-offset-2 focus:outline-dark"
                >
                  <option v-for="franja in franjas" :key="franja" :value="franja">{{ franja }}</option>
                </select>
              </div>
            </div>
          </div>

          <p v-if="form.errors.general" class="mt-4 border-2 border-coral bg-coral/15 p-3 font-body text-sm text-dark">
            {{ form.errors.general }}
          </p>
          <p v-if="form.errors.horas" class="mt-4 border-2 border-coral bg-coral/15 p-3 font-body text-sm text-dark">
            {{ form.errors.horas }}
          </p>

          <button
            type="submit" :disabled="form.processing || sinSaldo"
            class="mt-6 inline-flex min-h-[52px] items-center gap-2 border-2 border-dark bg-nodo-400
                   px-6 font-display text-sm font-bold text-dark transition-all duration-200 ease-salida
                   hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-dura-sm
                   disabled:cursor-not-allowed disabled:bg-cream-200 disabled:text-dark/50
                   disabled:hover:translate-x-0 disabled:hover:translate-y-0 disabled:hover:shadow-none
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                   focus-visible:outline-dark"
          >
            <Lightbulb :size="17" aria-hidden="true" />
            {{ sinSaldo ? 'Sin horas este ciclo' : form.processing ? 'Enviando…' : 'Pedir asesoría' }}
          </button>
        </TarjetaPortal>
      </form>

      <!-- Historial. -->
      <section>
        <h2 class="etiqueta-tecnica mb-4 flex items-center gap-3 text-dark/70">
          <span class="font-mono">03</span> Tus solicitudes
          <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
        </h2>

        <TarjetaPortal v-if="!solicitudes.length" fondo="crema">
          <p class="font-body text-cuerpo text-dark/70">Todavía no has pedido ninguna asesoría.</p>
        </TarjetaPortal>

        <ul v-else class="space-y-4">
          <li
            v-for="s in solicitudes" :key="s.id"
            class="border-2 border-dark/20 bg-white p-5"
          >
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div class="min-w-0 flex-1">
                <p class="font-body text-cuerpo text-dark">{{ s.tema }}</p>

                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 font-body text-xs text-dark/70">
                  <span class="flex items-center gap-1.5 first-letter:uppercase">
                    <Clock :size="13" aria-hidden="true" />
                    {{ fechaLarga(s.dia_preferido) }} · {{ s.horario_preferido }}
                  </span>
                  <span v-if="s.asesor" class="flex items-center gap-1.5">
                    <UserIcon :size="13" aria-hidden="true" /> {{ s.asesor }}
                  </span>
                </div>

                <p v-if="s.fecha_confirmada" class="mt-2 font-body text-sm text-dark">
                  Confirmada para el <strong>{{ fechaHora(s.fecha_confirmada) }}</strong>
                </p>

                <p
                  v-if="s.notas_operativo"
                  class="mt-3 border-l-2 border-dark/25 pl-3 font-body text-sm text-dark/80"
                >{{ s.notas_operativo }}</p>
              </div>

              <div class="flex flex-col items-end gap-3">
                <span
                  class="border-2 px-2.5 py-1 font-mono text-[0.6875rem] uppercase tracking-[0.12em]"
                  :class="tonos[s.tono]"
                >{{ s.estado_etiqueta }}</span>

                <button
                  v-if="!s.final"
                  type="button"
                  class="inline-flex min-h-[44px] items-center gap-1.5 border-2 border-dark/30 px-3
                         font-display text-xs font-bold text-dark transition-colors hover:border-coral
                         focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                         focus-visible:outline-dark"
                  @click="router.delete(route('portal.asesoria.cancel', s.id), { preserveScroll: true })"
                >
                  <X :size="14" aria-hidden="true" /> Cancelar
                </button>
              </div>
            </div>
          </li>
        </ul>
      </section>
    </template>
  </PortalLayout>
</template>
