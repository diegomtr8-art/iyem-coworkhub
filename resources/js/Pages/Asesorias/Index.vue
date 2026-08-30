<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Check, X, CalendarCheck, Lightbulb, Phone, Mail } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'
import Estado from '@/Components/Panel/Estado.vue'

/**
 * Bandeja de asesorías (Fase 3.7).
 *
 * Lo que la pantalla insiste en dejar claro: **confirmar descuenta las horas y
 * rechazar no**. Es la diferencia entre los dos botones y hay que verla antes de
 * pulsar, no después de haberlo hecho.
 */
const props = defineProps<{
  solicitudes: any
  estado: string
  estados: any[]
  asesores: any[]
  pendientes: number
  cargaPorAsesor: any[]
}>()

const confirmando = ref<any | null>(null)
const rechazando = ref<any | null>(null)

const confirmacion = useForm({ asesor_id: '', fecha_confirmada: '', notas: '' })
const rechazo = useForm({ motivo: '' })

const numero = (v: number | null) =>
  v === null ? '—' : Number.isInteger(v) ? String(v) : Number(v).toFixed(1).replace(/\.0$/, '')

const fecha = (iso: string) =>
  new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short' })

const fechaHora = (iso: string) =>
  new Date(iso).toLocaleString('es-MX', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })

function abrirConfirmar(s: any) {
  confirmando.value = s
  confirmacion.reset()
  // Se propone el día que pidió el miembro, a las 10:00. Recepción lo ajusta.
  confirmacion.fecha_confirmada = `${s.dia_preferido}T10:00`
}

const filtrar = (valor: string) =>
  router.get(route('asesorias.index'), { estado: valor }, { preserveState: true, replace: true })
</script>

<template>
  <Head title="Asesorías" />

  <AuthenticatedLayout>
    <template #breadcrumb>
      <span class="font-display font-bold text-dark">Asesorías</span>
      <span v-if="pendientes">· {{ pendientes }} sin responder</span>
    </template>

    <div class="grid gap-4 lg:grid-cols-4">
      <div class="lg:col-span-3">
        <Panel padding="none">
          <template #acciones>
            <div class="flex flex-wrap gap-1">
              <button
                v-for="f in [{ valor: 'pendientes', etiqueta: 'Pendientes' }, ...estados, { valor: 'todas', etiqueta: 'Todas' }]"
                :key="f.valor" type="button"
                class="min-h-[30px] border px-2 font-mono text-[0.6875rem] uppercase tracking-[0.06em] transition-colors"
                :class="estado === f.valor ? 'border-dark bg-dark text-white' : 'border-dark/20 text-dark/70 hover:border-dark'"
                @click="filtrar(f.valor)"
              >{{ f.etiqueta }}</button>
            </div>
          </template>

          <p v-if="!solicitudes.data.length" class="px-4 py-10 text-center text-sm text-dark/50">
            No hay solicitudes con ese filtro.
          </p>

          <ul v-else class="divide-y divide-dark/10">
            <li v-for="s in solicitudes.data" :key="s.id" class="p-4">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <Link
                      v-if="s.miembro.url" :href="s.miembro.url"
                      class="font-display text-sm font-bold text-dark underline decoration-dark/25
                             underline-offset-2 hover:decoration-dark"
                    >{{ s.miembro.nombre }}</Link>
                    <span v-else class="font-display text-sm font-bold text-dark">{{ s.miembro.nombre }}</span>

                    <Estado :tono="s.tono" :texto="s.estado_etiqueta" />

                    <span v-if="s.miembro.plan" class="border border-dark/20 px-1.5 py-0.5 font-mono text-[0.625rem] text-dark/60">
                      {{ s.miembro.plan }}
                    </span>
                  </div>

                  <p class="mt-2 text-sm text-dark/85">{{ s.tema }}</p>

                  <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 font-mono text-[0.6875rem] text-dark/55">
                    <span>pide: {{ fecha(s.dia_preferido) }} · {{ s.horario_preferido }}</span>
                    <span>{{ numero(s.horas) }} h</span>
                    <span v-if="s.saldo_asesoria !== null">saldo: {{ numero(s.saldo_asesoria) }} h</span>
                    <a v-if="s.miembro.telefono" :href="`tel:${s.miembro.telefono}`" class="flex items-center gap-1 hover:text-dark">
                      <Phone :size="11" aria-hidden="true" /> {{ s.miembro.telefono }}
                    </a>
                    <a v-if="s.miembro.email" :href="`mailto:${s.miembro.email}`" class="flex items-center gap-1 hover:text-dark">
                      <Mail :size="11" aria-hidden="true" /> {{ s.miembro.email }}
                    </a>
                  </div>

                  <p v-if="s.fecha_confirmada" class="mt-2 text-xs text-dark">
                    Confirmada: <strong>{{ fechaHora(s.fecha_confirmada) }}</strong>
                    <template v-if="s.asesor"> con {{ s.asesor }}</template>
                  </p>

                  <p v-if="s.notas_operativo" class="mt-2 border-l-2 border-dark/20 pl-2.5 text-xs text-dark/70">
                    {{ s.notas_operativo }}
                    <span v-if="s.atendida_por" class="text-dark/45">— {{ s.atendida_por }}</span>
                  </p>
                </div>

                <div class="flex shrink-0 flex-col gap-2">
                  <template v-if="s.pendiente">
                    <button
                      type="button"
                      class="flex min-h-[34px] items-center justify-center gap-1.5 border border-dark
                             bg-nodo-400 px-3 font-display text-xs font-bold text-dark transition-colors
                             hover:bg-nodo-500 focus-visible:outline focus-visible:outline-2
                             focus-visible:outline-offset-2 focus-visible:outline-dark"
                      @click="abrirConfirmar(s)"
                    ><Check :size="14" aria-hidden="true" /> Confirmar</button>

                    <button
                      type="button"
                      class="flex min-h-[34px] items-center justify-center gap-1.5 border
                             border-red-600/40 px-3 font-display text-xs font-bold text-red-700
                             transition-colors hover:bg-red-50 focus-visible:outline
                             focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
                      @click="rechazando = s; rechazo.reset()"
                    ><X :size="14" aria-hidden="true" /> Rechazar</button>
                  </template>

                  <button
                    v-else-if="s.confirmada"
                    type="button"
                    class="flex min-h-[34px] items-center justify-center gap-1.5 border border-dark/25
                           px-3 font-display text-xs font-bold text-dark transition-colors hover:border-dark"
                    @click="router.post(route('asesorias.realizada', s.id), {}, { preserveScroll: true })"
                  ><CalendarCheck :size="14" aria-hidden="true" /> Marcar realizada</button>
                </div>
              </div>
            </li>
          </ul>

          <nav
            v-if="solicitudes.last_page > 1"
            class="flex flex-wrap items-center justify-center gap-1 border-t border-dark/15 bg-cream-50 px-4 py-3"
            aria-label="Paginación"
          >
            <Link
              v-for="enlace in solicitudes.links" :key="enlace.label"
              :href="enlace.url ?? ''"
              class="min-h-[32px] min-w-[32px] border px-2 py-1 text-center font-mono text-xs"
              :class="enlace.active ? 'border-dark bg-dark text-white'
                : enlace.url ? 'border-dark/20 text-dark hover:border-dark' : 'border-transparent text-dark/25'"
              v-html="enlace.label"
            />
          </nav>
        </Panel>
      </div>

      <!-- Carga por asesor -->
      <div class="space-y-4">
        <Panel titulo="Carga por asesor" padding="none">
          <p class="px-4 py-2 text-[0.6875rem] text-dark/50">Últimos 90 días.</p>

          <p v-if="!cargaPorAsesor.length" class="px-4 pb-4 text-sm text-dark/50">
            Todavía no hay asesores dados de alta.
          </p>

          <ul v-else class="divide-y divide-dark/10">
            <li v-for="a in cargaPorAsesor" :key="a.id" class="flex items-center justify-between gap-2 px-4 py-2.5">
              <span class="truncate text-sm text-dark">{{ a.nombre }}</span>
              <span class="shrink-0 text-right">
                <span class="block font-display text-sm font-bold text-dark">{{ numero(a.horas) }} h</span>
                <span class="block font-mono text-[0.625rem] text-dark/50">{{ a.sesiones }} sesiones</span>
              </span>
            </li>
          </ul>

          <div class="border-t border-dark/15 bg-cream-50 px-4 py-2.5">
            <Link
              :href="route('asesores.index')"
              class="font-display text-xs font-bold text-dark underline decoration-dark/30 underline-offset-2 hover:decoration-dark"
            >Gestionar el catálogo</Link>
          </div>
        </Panel>
      </div>
    </div>

    <!-- ── Confirmar ────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="confirmando"
        class="fixed inset-0 z-50 flex items-center justify-center bg-tinta/60 p-4"
        role="dialog" aria-modal="true"
        @click.self="confirmando = null"
      >
        <form
          class="w-full max-w-md border-2 border-dark bg-white"
          @submit.prevent="confirmacion.post(route('asesorias.confirmar', confirmando.id), {
            preserveScroll: true, onSuccess: () => { confirmando = null },
          })"
        >
          <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
            Confirmar asesoría de {{ confirmando.miembro.nombre }}
          </h2>

          <div class="space-y-3 p-4">
            <p class="border border-amber-600/40 bg-amber-50 p-2.5 text-xs text-amber-900">
              Al confirmar se le descuentan <strong>{{ numero(confirmando.horas) }} h</strong> de su bolsa.
              <template v-if="confirmando.saldo_asesoria !== null">
                Le quedan {{ numero(confirmando.saldo_asesoria) }} h.
              </template>
            </p>

            <div>
              <label for="cf-asesor" class="mb-1 block text-xs font-bold text-dark">Asesor</label>
              <select id="cf-asesor" v-model="confirmacion.asesor_id" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark">
                <option value="" disabled>Elige del catálogo</option>
                <option v-for="a in asesores" :key="a.id" :value="a.id">
                  {{ a.nombre }}<template v-if="a.especialidad"> — {{ a.especialidad }}</template>
                </option>
              </select>
              <p v-if="confirmacion.errors.asesor_id" class="mt-1 text-xs text-red-700">{{ confirmacion.errors.asesor_id }}</p>
            </div>

            <div>
              <label for="cf-fecha" class="mb-1 block text-xs font-bold text-dark">Día y hora</label>
              <input id="cf-fecha" v-model="confirmacion.fecha_confirmada" type="datetime-local" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              <p class="mt-1 text-[0.6875rem] text-dark/55">
                Pidió: {{ fecha(confirmando.dia_preferido) }}, {{ confirmando.horario_preferido }}.
              </p>
              <p v-if="confirmacion.errors.fecha_confirmada" class="mt-1 text-xs text-red-700">{{ confirmacion.errors.fecha_confirmada }}</p>
            </div>

            <div>
              <label for="cf-notas" class="mb-1 block text-xs font-bold text-dark">Notas para el miembro</label>
              <textarea id="cf-notas" v-model="confirmacion.notas" rows="2" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
            </div>

            <p v-if="confirmacion.errors.horas || confirmacion.errors.dia_preferido" class="border border-red-600/40 bg-red-50 p-2.5 text-xs text-red-800">
              {{ confirmacion.errors.horas || confirmacion.errors.dia_preferido }}
            </p>
          </div>

          <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="confirmando = null">Cancelar</button>
            <button type="submit" :disabled="confirmacion.processing" class="min-h-[36px] border border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark">Confirmar y descontar</button>
          </div>
        </form>
      </div>

      <!-- Rechazar -->
      <div
        v-if="rechazando"
        class="fixed inset-0 z-50 flex items-center justify-center bg-tinta/60 p-4"
        role="dialog" aria-modal="true"
        @click.self="rechazando = null"
      >
        <form
          class="w-full max-w-md border-2 border-dark bg-white"
          @submit.prevent="rechazo.post(route('asesorias.rechazar', rechazando.id), {
            preserveScroll: true, onSuccess: () => { rechazando = null },
          })"
        >
          <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
            Rechazar la solicitud de {{ rechazando.miembro.nombre }}
          </h2>

          <div class="p-4">
            <p class="mb-3 border border-dark/20 bg-cream-50 p-2.5 text-xs text-dark/80">
              Rechazar <strong>no descuenta ninguna hora</strong>: su bolsa se queda como está.
            </p>

            <label for="rz-motivo" class="mb-1 block text-xs font-bold text-dark">Motivo (lo verá el miembro)</label>
            <textarea
              id="rz-motivo" v-model="rechazo.motivo" rows="3" required
              placeholder="Ej.: no hay asesor disponible esa semana; propón otro día."
              class="w-full border border-dark/25 px-2.5 py-2 text-sm placeholder:text-dark/35 focus:border-dark"
            />
            <p v-if="rechazo.errors.motivo" class="mt-1 text-xs text-red-700">{{ rechazo.errors.motivo }}</p>
          </div>

          <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="rechazando = null">Cancelar</button>
            <button type="submit" :disabled="rechazo.processing" class="min-h-[36px] border border-red-700 bg-red-700 px-4 font-display text-xs font-bold text-white">Rechazar</button>
          </div>
        </form>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
