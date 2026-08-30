<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Download, Search, AlertTriangle, Check } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'
import Estado from '@/Components/Panel/Estado.vue'

/**
 * Facturación (Fase 3.9).
 *
 * Nódico **no timbra**: esta pantalla prepara lo que hay que pasarle a
 * contabilidad del IYEM. Por eso cada fila avisa si el miembro tiene los datos
 * fiscales completos — sin ellos, exportar solo genera una devolución días
 * después.
 */
const props = defineProps<{
  facturas: any
  filtros: Record<string, any>
  resumen: Record<string, number>
  puedeExportar: boolean
}>()

const buscar = ref(props.filtros.buscar ?? '')
const estatus = ref(props.filtros.estatus ?? '')
const soloFacturables = ref(!!props.filtros.solo_facturables)
const cobrando = ref<any | null>(null)

const pago = useForm({ metodo_pago: 'Efectivo' })

let temporizador: number | undefined

function filtrar() {
  router.get(route('facturas.index'), {
    buscar: buscar.value || undefined,
    estatus: estatus.value || undefined,
    solo_facturables: soloFacturables.value || undefined,
  }, { preserveState: true, replace: true })
}

watch(buscar, () => {
  if (temporizador) window.clearTimeout(temporizador)
  temporizador = window.setTimeout(filtrar, 300)
})
watch([estatus, soloFacturables], filtrar)

const precio = (v: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0)

const fecha = (iso: string | null) =>
  iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'

const tonosPago: Record<string, 'bien' | 'atencion' | 'problema' | 'neutro'> = {
  Pagada: 'bien', Pendiente: 'atencion', Vencida: 'problema', Cancelada: 'neutro',
}

function exportar() {
  const url = new URL(route('facturas.exportar'), window.location.origin)
  if (estatus.value) url.searchParams.set('estatus', estatus.value)
  window.location.href = url.toString()
}
</script>

<template>
  <Head title="Facturación" />

  <AuthenticatedLayout>
    <template #breadcrumb>
      <span class="font-display font-bold text-dark">Facturación</span>
    </template>

    <div class="space-y-4">
      <!-- Lo que hay que resolver antes de exportar -->
      <div
        v-if="resumen.sin_datos_fiscales"
        class="flex items-start gap-3 border border-amber-600/40 bg-amber-50 p-3"
      >
        <AlertTriangle :size="17" class="mt-0.5 shrink-0 text-amber-700" aria-hidden="true" />
        <p class="text-sm text-amber-900">
          <strong>{{ resumen.sin_datos_fiscales }}</strong> pago(s) son de miembros sin datos fiscales.
          Contabilidad no puede timbrarlos: pídeselos antes de exportar.
        </p>
      </div>

      <Panel padding="none">
        <template #acciones>
          <button
            v-if="puedeExportar"
            type="button"
            class="flex min-h-[30px] items-center gap-1.5 border border-dark bg-nodo-400 px-2.5
                   font-display text-xs font-bold text-dark hover:bg-nodo-500"
            @click="exportar"
          ><Download :size="12" aria-hidden="true" /> Exportar para contabilidad</button>
        </template>

        <div class="grid gap-2 border-b border-dark/15 bg-cream-50 p-3 sm:grid-cols-[1fr_auto_auto]">
          <div class="relative">
            <Search :size="15" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-dark/40" aria-hidden="true" />
            <input v-model="buscar" type="search" placeholder="Folio, concepto o miembro…" aria-label="Buscar"
              class="w-full border border-dark/25 bg-white py-2 pl-8 pr-3 text-sm focus:border-dark" />
          </div>

          <select v-model="estatus" aria-label="Filtrar por estatus" class="border border-dark/25 bg-white px-2.5 py-2 text-sm focus:border-dark">
            <option value="">Todos</option>
            <option value="Pendiente">Pendientes ({{ resumen.pendientes }})</option>
            <option value="Pagada">Pagadas ({{ resumen.pagadas }})</option>
          </select>

          <label class="flex items-center gap-2 border border-dark/25 bg-white px-3 py-2 text-sm text-dark">
            <input v-model="soloFacturables" type="checkbox" class="h-4 w-4 rounded-none border-dark" />
            Solo facturables
          </label>
        </div>

        <p v-if="!facturas.data.length" class="px-4 py-10 text-center text-sm text-dark/50">
          Sin pagos con esos filtros.
        </p>

        <div v-else class="overflow-x-auto">
          <table class="w-full min-w-[52rem] text-sm">
            <thead class="border-b border-dark/15 bg-cream-50">
              <tr class="text-left font-mono text-[0.625rem] uppercase tracking-[0.1em] text-dark/50">
                <th scope="col" class="px-4 py-2 font-normal">Folio y concepto</th>
                <th scope="col" class="px-3 py-2 font-normal">Miembro</th>
                <th scope="col" class="px-3 py-2 font-normal">Datos fiscales</th>
                <th scope="col" class="px-3 py-2 text-right font-normal">Total</th>
                <th scope="col" class="px-3 py-2 font-normal">Estatus</th>
                <th scope="col" class="px-3 py-2 font-normal"><span class="sr-only">Acciones</span></th>
              </tr>
            </thead>

            <tbody class="divide-y divide-dark/10">
              <tr v-for="f in facturas.data" :key="f.id" class="hover:bg-cream-50">
                <td class="px-4 py-2.5">
                  <span class="block font-mono text-xs text-dark/60">{{ f.folio }}</span>
                  <span class="block text-dark">{{ f.concepto }}</span>
                  <span class="block font-mono text-[0.6875rem] text-dark/50">{{ fecha(f.fecha) }}</span>
                </td>

                <td class="px-3 py-2.5 text-xs">
                  <Link v-if="f.miembro.url" :href="f.miembro.url" class="text-dark underline decoration-dark/25 underline-offset-2 hover:decoration-dark">
                    {{ f.miembro.nombre }}
                  </Link>
                  <span v-else class="text-dark/60">—</span>
                </td>

                <td class="px-3 py-2.5">
                  <Estado
                    :tono="f.fiscales_completos ? 'bien' : 'atencion'"
                    :texto="f.fiscales_completos ? f.rfc : 'faltan'"
                    :punto="false"
                  />
                </td>

                <td class="px-3 py-2.5 text-right font-display font-bold text-dark">{{ precio(f.total) }}</td>

                <td class="px-3 py-2.5">
                  <Estado :tono="tonosPago[f.estatus] ?? 'neutro'" :texto="f.estatus" />
                  <span v-if="f.metodo_pago" class="mt-1 block font-mono text-[0.625rem] text-dark/50">{{ f.metodo_pago }}</span>
                </td>

                <td class="px-3 py-2.5">
                  <button
                    v-if="f.estatus === 'Pendiente'"
                    type="button"
                    class="flex min-h-[32px] items-center gap-1.5 border border-dark/25 px-2.5
                           font-display text-xs font-bold text-dark transition-colors hover:border-dark"
                    @click="cobrando = f"
                  ><Check :size="12" aria-hidden="true" /> Cobrar</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <nav v-if="facturas.last_page > 1" class="flex flex-wrap justify-center gap-1 border-t border-dark/15 bg-cream-50 px-4 py-3" aria-label="Paginación">
          <Link
            v-for="enlace in facturas.links" :key="enlace.label" :href="enlace.url ?? ''"
            class="min-h-[32px] min-w-[32px] border px-2 py-1 text-center font-mono text-xs"
            :class="enlace.active ? 'border-dark bg-dark text-white' : enlace.url ? 'border-dark/20 text-dark hover:border-dark' : 'border-transparent text-dark/25'"
            v-html="enlace.label"
          />
        </nav>
      </Panel>
    </div>

    <Teleport to="body">
      <div v-if="cobrando" class="fixed inset-0 z-50 flex items-center justify-center bg-tinta/60 p-4" role="dialog" aria-modal="true" @click.self="cobrando = null">
        <form
          class="w-full max-w-sm border-2 border-dark bg-white"
          @submit.prevent="pago.post(route('facturas.pagar', cobrando.id), { preserveScroll: true, onSuccess: () => { cobrando = null } })"
        >
          <h2 class="border-b border-dark/15 bg-cream-50 px-4 py-3 font-display text-sm font-bold text-dark">
            Registrar el pago
          </h2>

          <div class="space-y-3 p-4">
            <p class="text-sm text-dark">
              {{ cobrando.concepto }} · <strong>{{ precio(cobrando.total) }}</strong>
            </p>

            <div>
              <label for="fa-metodo" class="mb-1 block text-xs font-bold text-dark">Cómo pagó</label>
              <select id="fa-metodo" v-model="pago.metodo_pago" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark">
                <option>Efectivo</option>
                <option>Transferencia</option>
                <option>Tarjeta</option>
              </select>
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[36px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="cobrando = null">Cancelar</button>
            <button type="submit" :disabled="pago.processing" class="min-h-[36px] border border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark">Registrar</button>
          </div>
        </form>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
