<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Search, X, CheckCircle2, Ban, RefreshCw, AlertTriangle } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'
import Estado from '@/Components/Panel/Estado.vue'
import ListaResponsiva, { type Columna } from '@/Components/Panel/ListaResponsiva.vue'
import Paginacion from '@/Components/Panel/Paginacion.vue'

/** Fase 4 — caja: ver órdenes, confirmar el pago, cancelar, regenerar. */
const props = defineProps<{
  ordenes: any
  filtros: Record<string, any>
  metodos: { valor: string; etiqueta: string }[]
  estados: { valor: string; etiqueta: string }[]
}>()

// ── Filtros ──────────────────────────────────────────────────────────────────
const buscar = ref(props.filtros.buscar ?? '')
const estado = ref(props.filtros.estado ?? '')
const metodo = ref(props.filtros.metodo ?? '')
let t: number | undefined
function filtrar() {
  router.get(route('caja.ordenes'), {
    buscar: buscar.value || undefined, estado: estado.value || undefined, metodo: metodo.value || undefined,
  }, { preserveState: true, replace: true })
}
watch(buscar, () => { if (t) clearTimeout(t); t = window.setTimeout(filtrar, 350) })
watch([estado, metodo], filtrar)

// ── Modal de gestión de una orden ────────────────────────────────────────────
const abierta = ref<any | null>(null)
const modo = ref<'confirmar' | 'cancelar'>('confirmar')

const confirmar = useForm({
  fecha_pago: new Date().toISOString().slice(0, 10),
  monto_recibido: 0, evidencia: '', nota: '', encadenar: false, aceptar_diferencia: false,
})
const cancelar = useForm({ motivo: '' })

function gestionar(orden: any) {
  abierta.value = orden
  modo.value = 'confirmar'
  confirmar.reset(); confirmar.clearErrors()
  confirmar.monto_recibido = orden.monto
  cancelar.reset(); cancelar.clearErrors()
}

const hayDiferencia = computed(() =>
  abierta.value && Math.abs(Number(confirmar.monto_recibido) - Number(abierta.value.monto)) > 0.005)

function enviarConfirmar() {
  confirmar.post(route('caja.confirmar', { orden: abierta.value.id }), {
    preserveScroll: true, onSuccess: () => { abierta.value = null },
  })
}
function enviarCancelar() {
  cancelar.post(route('caja.cancelar', { orden: abierta.value.id }), {
    preserveScroll: true, onSuccess: () => { abierta.value = null },
  })
}
function regenerar(orden: any) {
  router.post(route('caja.regenerar', { orden: orden.id }), {}, { preserveScroll: true })
}

// ── Presentación ─────────────────────────────────────────────────────────────
const precio = (v: number) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v)
const fecha = (iso: string) => iso ? new Date(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }) : '—'

const tonoEstado = (e: string): 'bien' | 'atencion' | 'problema' | 'neutro' =>
  ({ confirmada: 'bien', generada: 'atencion', vencida: 'problema', cancelada: 'neutro' } as const)[e] ?? 'neutro'

const columnas: Columna[] = [
  { clave: 'referencia', etiqueta: 'Referencia', rol: 'identidad' },
  { clave: 'miembro', etiqueta: 'Miembro', rol: 'resumen' },
  { clave: 'monto', etiqueta: 'Monto', rol: 'resumen' },
  { clave: 'metodo', etiqueta: 'Método', rol: 'detalle' },
  { clave: 'plan', etiqueta: 'Plan', rol: 'detalle' },
  { clave: 'estado', etiqueta: 'Estado', rol: 'resumen', sinEtiqueta: true },
  { clave: 'accion', etiqueta: '', rol: 'resumen', sinEtiqueta: true },
]
</script>

<template>
  <Head title="Caja · Órdenes de pago" />

  <AuthenticatedLayout>
    <template #breadcrumb><span class="font-display font-bold text-dark">Caja · Órdenes</span></template>

    <Panel padding="none">
      <template #acciones>
        <span class="font-mono text-[0.6875rem] text-dark/55">{{ ordenes.total }} en total</span>
      </template>

      <!-- Filtros -->
      <div class="grid gap-2 border-b border-dark/15 bg-cream-50 p-3 sm:grid-cols-[1fr_auto_auto]">
        <div class="relative">
          <Search :size="15" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-dark/40" aria-hidden="true" />
          <input v-model="buscar" type="search" placeholder="Referencia (aunque venga del banco) o nombre…" aria-label="Buscar"
            class="w-full border border-dark/25 bg-white py-2 pl-8 pr-3 text-sm focus:border-dark" />
        </div>
        <select v-model="estado" aria-label="Estado" class="border border-dark/25 bg-white px-2.5 py-2 text-sm focus:border-dark">
          <option value="">Todos los estados</option>
          <option v-for="e in estados" :key="e.valor" :value="e.valor">{{ e.etiqueta }}</option>
        </select>
        <select v-model="metodo" aria-label="Método" class="border border-dark/25 bg-white px-2.5 py-2 text-sm focus:border-dark">
          <option value="">Todos los métodos</option>
          <option v-for="m in metodos" :key="m.valor" :value="m.valor">{{ m.etiqueta }}</option>
        </select>
      </div>

      <ListaResponsiva :columnas="columnas" :filas="ordenes.data" clave-fila="id" vacio="No hay órdenes con esos filtros.">
        <template #referencia="{ fila: o }">
          <span class="font-mono">{{ o.referencia }}</span>
          <span v-if="o.reportado && o.estado_pago === 'generada'" class="mt-0.5 block text-[0.625rem] font-bold uppercase text-amber-700">Reportó pagado</span>
          <span v-else-if="o.por_vencer" class="mt-0.5 block text-[0.625rem] font-bold uppercase text-red-700">Por vencer</span>
        </template>
        <template #miembro="{ fila: o }">{{ o.miembro }}</template>
        <template #monto="{ fila: o }"><span class="font-mono">{{ precio(o.monto) }}</span></template>
        <template #metodo="{ fila: o }">{{ o.metodo_label }}<span v-if="o.pide_factura"> · factura</span></template>
        <template #plan="{ fila: o }">{{ o.plan }}</template>
        <template #estado="{ fila: o }"><Estado :tono="tonoEstado(o.estado_pago)" :texto="o.estado_pago_label" /></template>
        <template #accion="{ fila: o }">
          <button v-if="o.estado_pago === 'generada'" type="button" @click.stop="gestionar(o)"
            class="flex min-h-[36px] items-center gap-1.5 border-2 border-dark bg-nodo-400 px-3 font-display text-xs font-bold text-dark">
            Gestionar
          </button>
          <button v-else-if="o.estado_pago === 'vencida'" type="button" @click.stop="regenerar(o)"
            class="flex min-h-[36px] items-center gap-1.5 border border-dark/25 px-3 font-display text-xs font-bold text-dark hover:border-dark">
            <RefreshCw :size="13" aria-hidden="true" /> Regenerar
          </button>
        </template>
      </ListaResponsiva>

      <Paginacion v-if="ordenes.last_page > 1" :links="ordenes.links" />
    </Panel>

    <!-- Modal: confirmar / cancelar una orden -->
    <Teleport to="body">
      <div v-if="abierta" class="fixed inset-0 z-50 flex items-end justify-center bg-tinta/60 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" @click.self="abierta = null">
        <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto border-2 border-dark bg-white">
          <div class="flex items-center justify-between border-b border-dark/15 bg-cream-50 px-4 py-3">
            <h2 class="font-display text-sm font-bold text-dark">Orden {{ abierta.referencia }} · {{ abierta.miembro }}</h2>
            <button type="button" aria-label="Cerrar" class="text-dark/50 hover:text-dark" @click="abierta = null"><X :size="18" /></button>
          </div>

          <div class="flex gap-1 border-b border-dark/15 px-4 pt-3">
            <button type="button" @click="modo = 'confirmar'" class="border-b-2 px-3 pb-2 font-display text-sm font-bold" :class="modo === 'confirmar' ? 'border-dark text-dark' : 'border-transparent text-dark/50'">Confirmar pago</button>
            <button type="button" @click="modo = 'cancelar'" class="border-b-2 px-3 pb-2 font-display text-sm font-bold" :class="modo === 'cancelar' ? 'border-dark text-dark' : 'border-transparent text-dark/50'">Cancelar</button>
          </div>

          <!-- Confirmar -->
          <form v-if="modo === 'confirmar'" class="space-y-3 p-4" @submit.prevent="enviarConfirmar">
            <p class="border-2 border-dark bg-cream-50 px-3 py-2 font-body text-sm text-dark">
              Esperado: <strong>{{ precio(abierta.monto) }}</strong> · {{ abierta.plan }}
            </p>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label for="cf-fecha" class="mb-1 block text-xs font-bold text-dark">Fecha del pago</label>
                <input id="cf-fecha" v-model="confirmar.fecha_pago" type="date" class="w-full border border-dark/25 px-2 py-2 text-sm focus:border-dark" />
                <p v-if="confirmar.errors.fecha_pago" class="mt-1 text-xs text-red-700">{{ confirmar.errors.fecha_pago }}</p>
              </div>
              <div>
                <label for="cf-monto" class="mb-1 block text-xs font-bold text-dark">Monto recibido</label>
                <input id="cf-monto" v-model="confirmar.monto_recibido" type="number" step="0.01" min="0" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" :class="hayDiferencia ? 'border-amber-500' : ''" />
                <p v-if="confirmar.errors.monto_recibido" class="mt-1 text-xs text-red-700">{{ confirmar.errors.monto_recibido }}</p>
              </div>
            </div>

            <!-- Monto distinto: hay que decidir explícitamente. -->
            <div v-if="hayDiferencia" class="border-2 border-amber-400 bg-amber-50 p-3">
              <p class="flex items-start gap-2 text-xs text-amber-900">
                <AlertTriangle :size="14" class="mt-0.5 shrink-0" aria-hidden="true" />
                <span>El monto no coincide (diferencia {{ precio(Number(confirmar.monto_recibido) - Number(abierta.monto)) }}). Acepta con nota o corrige.</span>
              </p>
              <label class="mt-2 flex items-center gap-2 text-xs font-bold text-amber-900">
                <input v-model="confirmar.aceptar_diferencia" type="checkbox" class="h-4 w-4 rounded-none border-amber-700" />
                Acepto la diferencia
              </label>
            </div>

            <div>
              <label for="cf-ev" class="mb-1 block text-xs font-bold text-dark">Evidencia (folio de transferencia o de caja)</label>
              <input id="cf-ev" v-model="confirmar.evidencia" type="text" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              <p v-if="confirmar.errors.evidencia" class="mt-1 text-xs text-red-700">{{ confirmar.errors.evidencia }}</p>
            </div>
            <div>
              <label for="cf-nota" class="mb-1 block text-xs font-bold text-dark">Nota <span class="font-normal text-dark/50">(opcional; obligatoria si el monto difiere)</span></label>
              <textarea id="cf-nota" v-model="confirmar.nota" rows="2" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark"></textarea>
              <p v-if="confirmar.errors.nota" class="mt-1 text-xs text-red-700">{{ confirmar.errors.nota }}</p>
            </div>
            <label class="flex items-start gap-2 text-xs text-dark">
              <input v-model="confirmar.encadenar" type="checkbox" class="mt-0.5 h-4 w-4 rounded-none border-2 border-dark" />
              Si ya tiene membresía activa, <strong>encadenar</strong> la vigencia (empieza cuando termine la actual) en vez de reemplazarla.
            </label>
            <p v-if="confirmar.errors.orden" class="border-2 border-red-600/40 bg-red-50 px-3 py-2 text-xs text-red-800">{{ confirmar.errors.orden }}</p>

            <button type="submit" :disabled="confirmar.processing" class="flex min-h-[48px] w-full items-center justify-center gap-2 border-2 border-dark bg-nodo-400 px-5 font-display text-sm font-bold text-dark disabled:opacity-50">
              <CheckCircle2 :size="18" aria-hidden="true" /> {{ confirmar.processing ? 'Confirmando…' : 'Confirmar y activar membresía' }}
            </button>
          </form>

          <!-- Cancelar -->
          <form v-else class="space-y-3 p-4" @submit.prevent="enviarCancelar">
            <p class="font-body text-sm text-dark/70">Las órdenes no se borran: se cancelan con motivo, y queda constancia.</p>
            <div>
              <label for="cn-motivo" class="mb-1 block text-xs font-bold text-dark">Motivo</label>
              <input id="cn-motivo" v-model="cancelar.motivo" type="text" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              <p v-if="cancelar.errors.motivo" class="mt-1 text-xs text-red-700">{{ cancelar.errors.motivo }}</p>
            </div>
            <button type="submit" :disabled="cancelar.processing" class="flex min-h-[48px] w-full items-center justify-center gap-2 border-2 border-red-700 bg-red-700 px-5 font-display text-sm font-bold text-white disabled:opacity-50">
              <Ban :size="18" aria-hidden="true" /> Cancelar la orden
            </button>
          </form>
        </div>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
