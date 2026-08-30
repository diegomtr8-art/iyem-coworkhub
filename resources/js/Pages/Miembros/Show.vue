<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import {
  ArrowLeft, ScanFace, Phone, Mail, Building2, Plus, Minus, RefreshCw,
  Ban, Play, ArrowRightLeft, Lock, Users,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'
import Estado from '@/Components/Panel/Estado.vue'

/**
 * Ficha del miembro (Fases 3.2 y 3.3).
 *
 * Todo lo que recepción necesita saber de alguien en una sola pantalla, y las
 * acciones que puede tomar sobre él. La que importa: **el ajuste de horas exige
 * motivo y no edita ningún contador** — escribe un movimiento en el libro, con
 * autor y fecha.
 */
const props = defineProps<{
  miembro: any
  suscripcion: any
  medidores: any[]
  historialMembresias: any[]
  reservas: any[]
  accesos: any[]
  movimientos: any[]
  asesorias: any[]
  facturas: any[]
  datosFiscales: any
  puedeVerFiscales: boolean
  planes: any[]
  candidatosCompanion: any[]
  bolsas: any[]
}>()

const dialogo = ref<null | 'ajuste' | 'plan' | 'suspender' | 'alta' | 'renovar'>(null)

const ajuste = useForm({ suscripcion_id: props.suscripcion?.id, bolsa: props.bolsas[0]?.valor ?? '', horas: 1, motivo: '' })
const plan = useForm({ suscripcion_id: props.suscripcion?.id, plan_id: '', motivo: '' })
const suspension = useForm({ suscripcion_id: props.suscripcion?.id, motivo: '' })
const alta = useForm({ plan_id: '', fecha_inicio: '', precio_pagado: '', nota: '' })
const renovacion = useForm({ suscripcion_id: props.suscripcion?.id, precio_pagado: '' })
const notas = useForm({ notas_admin: props.miembro.notas_admin ?? '' })
const companion = useForm({
  suscripcion_id: props.suscripcion?.id,
  companion_user_id: props.suscripcion?.companion?.id ?? null,
  companion_face_id_ok: props.suscripcion?.companion_face_id_ok ?? false,
})

const numero = (v: number | null) =>
  v === null ? '—' : Number.isInteger(v) ? String(v) : Number(v).toFixed(1).replace(/\.0$/, '')

const precio = (v: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v)

const fecha = (iso: string | null) =>
  iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'

const fechaHora = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString('es-MX', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—'

const estadoMembresia = computed(() => {
  if (!props.suscripcion) return { tono: 'problema' as const, texto: 'sin membresía' }
  if (props.suscripcion.estatus === 'Suspendida') return { tono: 'problema' as const, texto: 'suspendida' }

  const dias = Math.ceil((new Date(`${props.suscripcion.fecha_fin}T12:00:00`).getTime() - Date.now()) / 86400000)
  if (dias < 0) return { tono: 'problema' as const, texto: 'vencida' }
  if (dias <= 7) return { tono: 'atencion' as const, texto: `vence en ${dias} d` }
  return { tono: 'bien' as const, texto: 'activa' }
})

const cerrar = () => { dialogo.value = null }

function enviar(form: any, ruta: string, metodo: 'post' | 'patch' = 'post') {
  form[metodo](ruta, { preserveScroll: true, onSuccess: cerrar })
}

const estadosReserva: Record<string, 'bien' | 'atencion' | 'problema' | 'neutro'> = {
  Confirmada: 'bien', Completada: 'neutro', Cancelada: 'neutro', No_Show: 'problema',
}
</script>

<template>
  <Head :title="miembro.nombre" />

  <AuthenticatedLayout>
    <template #breadcrumb>
      <Link :href="route('miembros.index')" class="flex items-center gap-1 hover:text-dark">
        <ArrowLeft :size="14" aria-hidden="true" /> Miembros
      </Link>
      <span class="font-display font-bold text-dark">· {{ miembro.nombre }}</span>
    </template>

    <div class="space-y-4">
      <!-- Identidad y estado -->
      <Panel padding="md">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="flex items-start gap-4">
            <span
              class="flex h-14 w-14 shrink-0 items-center justify-center border-2 border-dark bg-nodo-400
                     font-display text-xl font-extrabold text-dark"
              aria-hidden="true"
            >{{ miembro.nombre.charAt(0).toUpperCase() }}</span>

            <div class="min-w-0">
              <h1 class="font-display text-xl font-extrabold text-dark">{{ miembro.nombre }}</h1>

              <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-dark/70">
                <a :href="`mailto:${miembro.email}`" class="flex items-center gap-1.5 hover:text-dark">
                  <Mail :size="13" aria-hidden="true" /> {{ miembro.email }}
                </a>
                <a v-if="miembro.telefono" :href="`tel:${miembro.telefono}`" class="flex items-center gap-1.5 hover:text-dark">
                  <Phone :size="13" aria-hidden="true" /> {{ miembro.telefono }}
                </a>
                <span v-if="miembro.empresa" class="flex items-center gap-1.5">
                  <Building2 :size="13" aria-hidden="true" /> {{ miembro.empresa }}
                </span>
              </div>

              <div class="mt-2.5 flex flex-wrap gap-2">
                <Estado :tono="estadoMembresia.tono" :texto="estadoMembresia.texto" />
                <Estado
                  :tono="miembro.face_id_ok ? 'bien' : 'atencion'"
                  :texto="miembro.face_id_ok ? 'Face ID' : 'sin Face ID'"
                />
                <Estado v-if="miembro.estado_cuenta !== 'activa'" tono="problema" :texto="miembro.estado_cuenta" />
                <span class="font-mono text-[0.625rem] text-dark/40">alta {{ fecha(miembro.alta) }}</span>
              </div>
            </div>
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="flex min-h-[36px] items-center gap-1.5 border border-dark/25 px-3 font-display
                     text-xs font-bold text-dark transition-colors hover:border-dark
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                     focus-visible:outline-dark"
              @click="router.patch(route('miembros.faceid', miembro.id), {}, { preserveScroll: true })"
            >
              <ScanFace :size="14" aria-hidden="true" />
              {{ miembro.face_id_ok ? 'Quitar Face ID' : 'Registrar Face ID' }}
            </button>
          </div>
        </div>

        <!-- El contacto de emergencia va visible: es de lo poco que se consulta
             con prisa de verdad. -->
        <div
          v-if="miembro.emergencia.nombre"
          class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-dark/10 pt-3 text-xs"
        >
          <span class="font-mono uppercase tracking-[0.1em] text-dark/50">Emergencia</span>
          <span class="font-display font-bold text-dark">{{ miembro.emergencia.nombre }}</span>
          <a
            v-if="miembro.emergencia.telefono"
            :href="`tel:${miembro.emergencia.telefono}`"
            class="flex items-center gap-1 text-dark underline decoration-dark/30 underline-offset-2 hover:decoration-dark"
          >
            <Phone :size="12" aria-hidden="true" /> {{ miembro.emergencia.telefono }}
          </a>
          <span v-if="miembro.emergencia.parentesco" class="text-dark/60">
            ({{ miembro.emergencia.parentesco }})
          </span>
        </div>
      </Panel>

      <div class="grid gap-4 lg:grid-cols-3">
        <!-- Columna principal -->
        <div class="space-y-4 lg:col-span-2">
          <!-- Bolsas y ajuste -->
          <Panel titulo="Bolsas del ciclo" padding="md">
            <template #acciones>
              <button
                v-if="suscripcion && bolsas.length"
                type="button"
                class="flex min-h-[32px] items-center gap-1.5 border border-dark bg-nodo-400 px-2.5
                       font-display text-xs font-bold text-dark transition-colors hover:bg-nodo-500
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                       focus-visible:outline-dark"
                @click="dialogo = 'ajuste'"
              >
                <Plus :size="13" aria-hidden="true" /> Ajustar horas
              </button>
            </template>

            <p v-if="!medidores.length" class="py-4 text-center text-sm text-dark/50">
              Sin membresía activa.
            </p>

            <div v-else class="grid gap-3 sm:grid-cols-2">
              <div
                v-for="m in medidores" :key="m.bolsa"
                class="border border-dark/15 bg-cream-50 p-3"
              >
                <p class="font-mono text-[0.625rem] uppercase tracking-[0.1em] text-dark/50">
                  {{ m.etiqueta }}
                </p>

                <p class="mt-1 font-display text-lg font-extrabold text-dark">
                  <template v-if="m.ilimitada">Sin límite</template>
                  <template v-else>
                    {{ numero(m.restante) }}
                    <span class="text-xs font-normal text-dark/60">de {{ numero(m.cupo) }} {{ m.unidad }}</span>
                  </template>
                </p>

                <div v-if="!m.ilimitada" class="mt-2 h-1.5 w-full bg-dark/10">
                  <div
                    class="h-full transition-all duration-500"
                    :class="m.agotada ? 'bg-red-600' : m.casi_agotada ? 'bg-amber-500' : 'bg-emerald-600'"
                    :style="{ width: m.porcentaje_usado + '%' }"
                  />
                </div>

                <p v-if="m.tope_diario" class="mt-1.5 text-[0.6875rem] text-dark/50">
                  máx. {{ numero(m.tope_diario) }} al día
                </p>
              </div>
            </div>

            <p v-if="suscripcion" class="mt-3 border-t border-dark/10 pt-3 text-[0.6875rem] text-dark/50">
              Ciclo del {{ fecha(suscripcion.ciclo_inicio) }} al {{ fecha(suscripcion.ciclo_fin) }}.
            </p>
          </Panel>

          <!-- Movimientos del libro -->
          <Panel titulo="Movimientos de horas" :contador="movimientos.length" padding="none">
            <p v-if="!movimientos.length" class="px-4 py-6 text-center text-sm text-dark/50">
              Sin movimientos.
            </p>

            <ul v-else class="max-h-80 divide-y divide-dark/10 overflow-y-auto">
              <li v-for="m in movimientos" :key="m.id" class="px-4 py-2.5">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="font-display text-sm font-bold text-dark">{{ m.descripcion }}</p>
                    <p v-if="m.nota" class="mt-0.5 text-xs italic text-dark/60">{{ m.nota }}</p>
                    <p v-if="m.autor" class="mt-0.5 text-[0.6875rem] text-dark/45">por {{ m.autor }}</p>
                  </div>
                  <span class="shrink-0 font-mono text-[0.6875rem] text-dark/50">{{ fechaHora(m.fecha) }}</span>
                </div>
              </li>
            </ul>
          </Panel>

          <!-- Reservas y accesos -->
          <div class="grid gap-4 sm:grid-cols-2">
            <Panel titulo="Últimas reservas" padding="none">
              <p v-if="!reservas.length" class="px-4 py-6 text-center text-sm text-dark/50">Ninguna.</p>
              <ul v-else class="max-h-72 divide-y divide-dark/10 overflow-y-auto">
                <li v-for="r in reservas" :key="r.id" class="flex items-center justify-between gap-2 px-4 py-2">
                  <div class="min-w-0">
                    <p class="truncate text-sm text-dark">{{ r.espacio }}</p>
                    <p class="font-mono text-[0.6875rem] text-dark/55">
                      {{ fecha(r.fecha) }} · {{ r.inicio }}–{{ r.fin }} · {{ numero(r.horas) }} h
                    </p>
                  </div>
                  <Estado :tono="estadosReserva[r.estatus] ?? 'neutro'" :texto="r.estatus.replace('_', ' ')" :punto="false" />
                </li>
              </ul>
            </Panel>

            <Panel titulo="Últimos accesos" padding="none">
              <p v-if="!accesos.length" class="px-4 py-6 text-center text-sm text-dark/50">Ninguno.</p>
              <ul v-else class="max-h-72 divide-y divide-dark/10 overflow-y-auto">
                <li v-for="a in accesos" :key="a.id" class="flex items-center justify-between gap-2 px-4 py-2">
                  <p class="truncate text-sm text-dark">{{ a.espacio ?? 'Coworking' }}</p>
                  <p class="shrink-0 font-mono text-[0.6875rem] text-dark/55">
                    {{ fechaHora(a.entrada) }}
                    <template v-if="a.minutos"> · {{ Math.round(a.minutos / 60 * 10) / 10 }} h</template>
                    <template v-else-if="!a.salida"> · dentro</template>
                  </p>
                </li>
              </ul>
            </Panel>
          </div>
        </div>

        <!-- Columna lateral -->
        <div class="space-y-4">
          <!-- Membresía -->
          <Panel titulo="Membresía" padding="md">
            <template v-if="suscripcion">
              <p class="font-display text-lg font-extrabold text-dark">{{ suscripcion.plan?.nombre }}</p>
              <dl class="mt-3 space-y-1.5 text-xs">
                <div class="flex justify-between gap-2">
                  <dt class="text-dark/55">Vigencia</dt>
                  <dd class="text-right text-dark">{{ fecha(suscripcion.fecha_inicio) }} — {{ fecha(suscripcion.fecha_fin) }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                  <dt class="text-dark/55">Pagó</dt>
                  <dd class="text-dark">{{ precio(suscripcion.precio_pagado) }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                  <dt class="text-dark/55">Estatus</dt>
                  <dd class="text-dark">{{ suscripcion.estatus }}</dd>
                </div>
              </dl>

              <div class="mt-4 grid grid-cols-2 gap-2">
                <button
                  type="button"
                  class="flex min-h-[36px] items-center justify-center gap-1.5 border border-dark/25 px-2
                         font-display text-xs font-bold text-dark transition-colors hover:border-dark"
                  @click="dialogo = 'renovar'"
                ><RefreshCw :size="13" aria-hidden="true" /> Renovar</button>

                <button
                  type="button"
                  class="flex min-h-[36px] items-center justify-center gap-1.5 border border-dark/25 px-2
                         font-display text-xs font-bold text-dark transition-colors hover:border-dark"
                  @click="dialogo = 'plan'"
                ><ArrowRightLeft :size="13" aria-hidden="true" /> Cambiar plan</button>

                <button
                  v-if="suscripcion.estatus === 'Activa'"
                  type="button"
                  class="col-span-2 flex min-h-[36px] items-center justify-center gap-1.5 border
                         border-red-600/40 px-2 font-display text-xs font-bold text-red-700
                         transition-colors hover:bg-red-50"
                  @click="dialogo = 'suspender'"
                ><Ban :size="13" aria-hidden="true" /> Suspender</button>

                <button
                  v-else
                  type="button"
                  class="col-span-2 flex min-h-[36px] items-center justify-center gap-1.5 border
                         border-emerald-600/40 px-2 font-display text-xs font-bold text-emerald-700
                         transition-colors hover:bg-emerald-50"
                  @click="router.post(route('miembros.reactivar', miembro.id), { suscripcion_id: suscripcion.id }, { preserveScroll: true })"
                ><Play :size="13" aria-hidden="true" /> Reactivar</button>
              </div>
            </template>

            <template v-else>
              <p class="text-sm text-dark/60">Sin membresía activa.</p>
              <button
                type="button"
                class="mt-3 flex min-h-[36px] w-full items-center justify-center gap-1.5 border
                       border-dark bg-nodo-400 px-3 font-display text-xs font-bold text-dark"
                @click="dialogo = 'alta'"
              ><Plus :size="13" aria-hidden="true" /> Dar de alta</button>
            </template>
          </Panel>

          <!-- Acompañante (Nodo Match) -->
          <Panel v-if="suscripcion?.admite_companion" titulo="Acompañante" padding="md">
            <form class="space-y-3" @submit.prevent="enviar(companion, route('miembros.companion', miembro.id), 'patch')">
              <select
                v-model="companion.companion_user_id"
                class="w-full border border-dark/25 bg-white px-2.5 py-2 text-sm focus:border-dark"
              >
                <option :value="null">Sin acompañante</option>
                <option v-for="c in candidatosCompanion" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>

              <label class="flex items-center gap-2 text-xs text-dark">
                <input v-model="companion.companion_face_id_ok" type="checkbox" class="h-4 w-4 rounded-none border-dark" />
                Face ID del acompañante registrado
              </label>

              <p v-if="companion.errors.companion_user_id" class="text-xs text-red-700">
                {{ companion.errors.companion_user_id }}
              </p>

              <button
                type="submit" :disabled="companion.processing"
                class="min-h-[34px] w-full border border-dark bg-nodo-400 px-3 font-display text-xs font-bold text-dark"
              >Guardar</button>

              <p class="flex items-start gap-1.5 text-[0.6875rem] text-dark/55">
                <Users :size="12" class="mt-0.5 shrink-0" aria-hidden="true" />
                Las horas son una bolsa compartida entre los dos.
              </p>
            </form>
          </Panel>

          <!-- Datos fiscales -->
          <Panel titulo="Datos fiscales" padding="md">
            <p v-if="!puedeVerFiscales" class="flex items-start gap-2 text-xs text-dark/55">
              <Lock :size="13" class="mt-0.5 shrink-0" aria-hidden="true" />
              Solo administración puede consultarlos.
            </p>

            <p v-else-if="!datosFiscales" class="text-xs text-dark/55">
              El miembro todavía no los ha llenado.
            </p>

            <dl v-else class="space-y-1.5 text-xs">
              <div class="flex justify-between gap-2">
                <dt class="text-dark/55">RFC</dt>
                <dd class="font-mono text-dark">{{ datosFiscales.rfc }}</dd>
              </div>
              <div>
                <dt class="text-dark/55">Razón social</dt>
                <dd class="text-dark">{{ datosFiscales.razon_social }}</dd>
              </div>
              <div>
                <dt class="text-dark/55">Régimen</dt>
                <dd class="text-dark">{{ datosFiscales.regimen }}</dd>
              </div>
              <div class="flex justify-between gap-2">
                <dt class="text-dark/55">CP</dt>
                <dd class="font-mono text-dark">{{ datosFiscales.codigo_postal }}</dd>
              </div>
              <p class="pt-1.5 text-[0.6875rem] text-dark/45">Tu consulta quedó en la bitácora.</p>
            </dl>
          </Panel>

          <!-- Notas internas -->
          <Panel titulo="Notas internas" padding="md">
            <form @submit.prevent="notas.patch(route('miembros.notas', miembro.id), { preserveScroll: true })">
              <textarea
                v-model="notas.notas_admin" rows="4"
                placeholder="Lo que recepción tiene que saber de esta persona."
                class="w-full border border-dark/25 bg-white px-2.5 py-2 text-sm text-dark
                       placeholder:text-dark/35 focus:border-dark"
              />
              <button
                type="submit" :disabled="notas.processing"
                class="mt-2 min-h-[34px] w-full border border-dark/25 px-3 font-display text-xs
                       font-bold text-dark transition-colors hover:border-dark"
              >{{ notas.processing ? 'Guardando…' : 'Guardar notas' }}</button>
            </form>
          </Panel>

          <!-- Asesorías y facturas -->
          <Panel v-if="asesorias.length" titulo="Asesorías" padding="none">
            <ul class="divide-y divide-dark/10">
              <li v-for="a in asesorias" :key="a.id" class="flex items-center justify-between gap-2 px-4 py-2">
                <p class="truncate text-xs text-dark">{{ a.tema }}</p>
                <Estado :tono="a.tono" :texto="a.estado" :punto="false" />
              </li>
            </ul>
          </Panel>

          <Panel v-if="historialMembresias.length" titulo="Membresías anteriores" padding="none">
            <ul class="divide-y divide-dark/10">
              <li v-for="h in historialMembresias" :key="h.id" class="px-4 py-2">
                <p class="text-xs text-dark">{{ h.plan }}</p>
                <p class="font-mono text-[0.6875rem] text-dark/50">
                  {{ fecha(h.desde) }} — {{ fecha(h.hasta) }} · {{ precio(h.precio) }}
                </p>
              </li>
            </ul>
          </Panel>
        </div>
      </div>
    </div>

    <!-- ── Diálogos ─────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="dialogo"
        class="fixed inset-0 z-50 flex items-center justify-center bg-tinta/60 p-4"
        role="dialog" aria-modal="true"
        @click.self="cerrar"
      >
        <div class="w-full max-w-md border-2 border-dark bg-white">
          <!-- Ajuste de horas -->
          <form v-if="dialogo === 'ajuste'" @submit.prevent="enviar(ajuste, route('miembros.ajustar-horas', miembro.id))">
            <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
              Ajustar horas de {{ miembro.nombre }}
            </h2>

            <div class="space-y-3 p-4">
              <div>
                <label for="aj-bolsa" class="mb-1 block text-xs font-bold text-dark">Bolsa</label>
                <select id="aj-bolsa" v-model="ajuste.bolsa" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark">
                  <option v-for="b in bolsas" :key="b.valor" :value="b.valor">{{ b.etiqueta }}</option>
                </select>
              </div>

              <div>
                <label for="aj-horas" class="mb-1 block text-xs font-bold text-dark">Horas</label>
                <div class="flex items-center gap-2">
                  <button
                    type="button" aria-label="Restar"
                    class="flex h-9 w-9 shrink-0 items-center justify-center border border-dark/25 hover:border-dark"
                    @click="ajuste.horas = Math.round((Number(ajuste.horas) - 0.5) * 10) / 10"
                  ><Minus :size="14" /></button>

                  <input
                    id="aj-horas" v-model.number="ajuste.horas" type="number" step="0.5"
                    class="w-full border border-dark/25 px-2.5 py-2 text-center font-mono text-sm focus:border-dark"
                  />

                  <button
                    type="button" aria-label="Sumar"
                    class="flex h-9 w-9 shrink-0 items-center justify-center border border-dark/25 hover:border-dark"
                    @click="ajuste.horas = Math.round((Number(ajuste.horas) + 0.5) * 10) / 10"
                  ><Plus :size="14" /></button>
                </div>
                <p class="mt-1 text-[0.6875rem] text-dark/55">
                  En positivo le <strong>repones</strong> horas; en negativo se las descuentas.
                </p>
                <p v-if="ajuste.errors.horas" class="mt-1 text-xs text-red-700">{{ ajuste.errors.horas }}</p>
              </div>

              <div>
                <label for="aj-motivo" class="mb-1 block text-xs font-bold text-dark">Motivo (obligatorio)</label>
                <textarea
                  id="aj-motivo" v-model="ajuste.motivo" rows="3" required
                  placeholder="Ej.: se cayó el internet y no pudo usar la sala."
                  class="w-full border border-dark/25 px-2.5 py-2 text-sm placeholder:text-dark/35 focus:border-dark"
                />
                <p v-if="ajuste.errors.motivo" class="mt-1 text-xs text-red-700">{{ ajuste.errors.motivo }}</p>
                <p class="mt-1 text-[0.6875rem] text-dark/55">Queda en la bitácora con tu nombre.</p>
              </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
              <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="cerrar">Cancelar</button>
              <button type="submit" :disabled="ajuste.processing" class="min-h-[36px] border border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark">Aplicar</button>
            </div>
          </form>

          <!-- Cambio de plan -->
          <form v-else-if="dialogo === 'plan'" @submit.prevent="enviar(plan, route('miembros.cambiar-plan', miembro.id))">
            <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
              Cambiar de plan
            </h2>

            <div class="space-y-3 p-4">
              <p class="border border-amber-600/40 bg-amber-50 p-2.5 text-xs text-amber-900">
                Se abre un <strong>ciclo nuevo desde cero</strong>: sus bolsas arrancan completas hoy
                y lo consumido queda en el histórico.
              </p>

              <div>
                <label for="pl-plan" class="mb-1 block text-xs font-bold text-dark">Plan nuevo</label>
                <select id="pl-plan" v-model="plan.plan_id" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark">
                  <option value="" disabled>Elige un plan</option>
                  <option v-for="p in planes" :key="p.id" :value="p.id" :disabled="p.id === suscripcion?.plan?.id">
                    {{ p.nombre }} — {{ precio(p.precio) }}
                  </option>
                </select>
                <p v-if="plan.errors.plan_id" class="mt-1 text-xs text-red-700">{{ plan.errors.plan_id }}</p>
              </div>

              <div>
                <label for="pl-motivo" class="mb-1 block text-xs font-bold text-dark">Motivo (obligatorio)</label>
                <textarea id="pl-motivo" v-model="plan.motivo" rows="3" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
                <p v-if="plan.errors.motivo" class="mt-1 text-xs text-red-700">{{ plan.errors.motivo }}</p>
              </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
              <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="cerrar">Cancelar</button>
              <button type="submit" :disabled="plan.processing" class="min-h-[36px] border border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark">Cambiar</button>
            </div>
          </form>

          <!-- Suspender -->
          <form v-else-if="dialogo === 'suspender'" @submit.prevent="enviar(suspension, route('miembros.suspender', miembro.id))">
            <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
              Suspender membresía
            </h2>

            <div class="p-4">
              <label for="su-motivo" class="mb-1 block text-xs font-bold text-dark">Motivo (obligatorio)</label>
              <textarea id="su-motivo" v-model="suspension.motivo" rows="3" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              <p v-if="suspension.errors.motivo" class="mt-1 text-xs text-red-700">{{ suspension.errors.motivo }}</p>
              <p class="mt-2 text-[0.6875rem] text-dark/55">El miembro va a preguntar por qué. Queda escrito.</p>
            </div>

            <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
              <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="cerrar">Cancelar</button>
              <button type="submit" :disabled="suspension.processing" class="min-h-[36px] border border-red-700 bg-red-700 px-4 font-display text-xs font-bold text-white">Suspender</button>
            </div>
          </form>

          <!-- Alta -->
          <form v-else-if="dialogo === 'alta'" @submit.prevent="enviar(alta, route('miembros.suscripcion', miembro.id))">
            <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
              Dar de alta una membresía
            </h2>

            <div class="space-y-3 p-4">
              <div>
                <label for="al-plan" class="mb-1 block text-xs font-bold text-dark">Plan</label>
                <select id="al-plan" v-model="alta.plan_id" required class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark">
                  <option value="" disabled>Elige un plan</option>
                  <option v-for="p in planes" :key="p.id" :value="p.id">{{ p.nombre }} — {{ precio(p.precio) }}</option>
                </select>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label for="al-fecha" class="mb-1 block text-xs font-bold text-dark">Empieza</label>
                  <input id="al-fecha" v-model="alta.fecha_inicio" type="date" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
                </div>
                <div>
                  <label for="al-precio" class="mb-1 block text-xs font-bold text-dark">Pagó</label>
                  <input id="al-precio" v-model="alta.precio_pagado" type="number" step="0.01" placeholder="precio del plan" class="w-full border border-dark/25 px-2.5 py-2 text-sm placeholder:text-dark/35 focus:border-dark" />
                </div>
              </div>

              <div>
                <label for="al-nota" class="mb-1 block text-xs font-bold text-dark">Nota</label>
                <input id="al-nota" v-model="alta.nota" type="text" placeholder="Ej.: pagó en efectivo." class="w-full border border-dark/25 px-2.5 py-2 text-sm placeholder:text-dark/35 focus:border-dark" />
              </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
              <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="cerrar">Cancelar</button>
              <button type="submit" :disabled="alta.processing" class="min-h-[36px] border border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark">Dar de alta</button>
            </div>
          </form>

          <!-- Renovar -->
          <form v-else-if="dialogo === 'renovar'" @submit.prevent="enviar(renovacion, route('miembros.renovar', miembro.id))">
            <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
              Renovar {{ suscripcion?.plan?.nombre }}
            </h2>

            <div class="p-4">
              <label for="re-precio" class="mb-1 block text-xs font-bold text-dark">Pagó</label>
              <input id="re-precio" v-model="renovacion.precio_pagado" type="number" step="0.01" :placeholder="String(suscripcion?.plan?.precio ?? '')" class="w-full border border-dark/25 px-2.5 py-2 text-sm placeholder:text-dark/35 focus:border-dark" />
              <p class="mt-2 text-[0.6875rem] text-dark/55">
                Si la membresía sigue vigente, la nueva empieza al día siguiente de que termine.
                Si ya venció, empieza hoy.
              </p>
            </div>

            <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
              <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="cerrar">Cancelar</button>
              <button type="submit" :disabled="renovacion.processing" class="min-h-[36px] border border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark">Renovar</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
