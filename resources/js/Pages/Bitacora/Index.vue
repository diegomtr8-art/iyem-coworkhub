<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Search, Lock, ShieldAlert } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'
import Estado from '@/Components/Panel/Estado.vue'

/**
 * Bitácora, en dos registros (Fases B y 3.11).
 *
 * **Operación** responde a «¿quién le tocó las horas a este miembro y por qué?».
 * **Acceso** a «¿alguien entró a esta cuenta?». Separadas porque el ruido de los
 * intentos de ingreso enterraría los seis ajustes de horas del mes.
 */
const props = defineProps<{
  registro: string
  operacion: any
  acceso: any
  acciones: any[]
  tipos: any[]
  filtros: Record<string, any>
}>()

const buscar = ref(props.filtros.buscar ?? '')
const accion = ref(props.filtros.accion ?? '')
const desde = ref(props.filtros.desde ?? '')
const hasta = ref(props.filtros.hasta ?? '')
const correo = ref(props.filtros.correo ?? '')
const tipo = ref(props.filtros.tipo ?? '')
const fallos = ref(!!props.filtros.fallos)

let temporizador: number | undefined

function filtrar() {
  router.get(route('bitacora.index'), {
    registro: props.registro,
    buscar: buscar.value || undefined,
    accion: accion.value || undefined,
    desde: desde.value || undefined,
    hasta: hasta.value || undefined,
    correo: correo.value || undefined,
    tipo: tipo.value || undefined,
    fallos: fallos.value || undefined,
  }, { preserveState: true, replace: true })
}

watch([buscar, correo], () => {
  if (temporizador) window.clearTimeout(temporizador)
  temporizador = window.setTimeout(filtrar, 350)
})

watch([accion, desde, hasta, tipo, fallos], filtrar)

const cambiarRegistro = (valor: string) =>
  router.get(route('bitacora.index'), { registro: valor }, { preserveState: false })

const fechaHora = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString('es-MX', {
    day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit',
  }) : '—'
</script>

<template>
  <Head title="Bitácora" />

  <AuthenticatedLayout>
    <template #breadcrumb>
      <span class="font-display font-bold text-dark">Bitácora</span>
    </template>

    <Panel padding="none">
      <template #acciones>
        <div class="flex gap-1">
          <button
            v-for="r in [{ v: 'operacion', e: 'Operación' }, { v: 'acceso', e: 'Acceso' }]"
            :key="r.v" type="button"
            class="min-h-[30px] border px-3 font-mono text-[0.6875rem] uppercase tracking-[0.06em] transition-colors"
            :class="registro === r.v ? 'border-dark bg-dark text-white' : 'border-dark/20 text-dark/70 hover:border-dark'"
            @click="cambiarRegistro(r.v)"
          >{{ r.e }}</button>
        </div>
      </template>

      <!-- ── Operación ──────────────────────────────────────────────────── -->
      <template v-if="registro === 'operacion' && operacion">
        <div class="grid gap-2 border-b border-dark/15 bg-cream-50 p-3 sm:grid-cols-[1fr_auto_auto_auto]">
          <div class="relative">
            <Search :size="15" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-dark/40" aria-hidden="true" />
            <input v-model="buscar" type="search" placeholder="Qué, quién o por qué…" aria-label="Buscar en la bitácora"
              class="w-full border border-dark/25 bg-white py-2 pl-8 pr-3 text-sm focus:border-dark" />
          </div>

          <select v-model="accion" aria-label="Filtrar por acción" class="border border-dark/25 bg-white px-2.5 py-2 text-sm focus:border-dark">
            <option value="">Toda acción</option>
            <option v-for="a in acciones" :key="a.valor" :value="a.valor">{{ a.etiqueta }}</option>
          </select>

          <input v-model="desde" type="date" aria-label="Desde" class="border border-dark/25 bg-white px-2 py-2 text-sm focus:border-dark" />
          <input v-model="hasta" type="date" aria-label="Hasta" class="border border-dark/25 bg-white px-2 py-2 text-sm focus:border-dark" />
        </div>

        <p v-if="!operacion.data.length" class="px-4 py-10 text-center text-sm text-dark/50">
          Nada registrado con esos filtros.
        </p>

        <ul v-else class="divide-y divide-dark/10">
          <li
            v-for="e in operacion.data" :key="e.id"
            class="px-4 py-3"
            :class="e.sensible ? 'bg-amber-50/50' : ''"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <Estado
                    :tono="e.sensible ? 'atencion' : 'neutro'"
                    :texto="e.etiqueta"
                    :punto="false"
                  />
                  <Lock v-if="e.sensible" :size="12" class="text-amber-700" aria-label="Toca datos personales" />
                </div>

                <p class="mt-1.5 text-sm text-dark">{{ e.descripcion }}</p>

                <p v-if="e.motivo" class="mt-1 border-l-2 border-dark/20 pl-2.5 text-xs italic text-dark/70">
                  {{ e.motivo }}
                </p>

                <p class="mt-1.5 font-mono text-[0.6875rem] text-dark/50">
                  <template v-if="e.actor">{{ e.actor }}</template>
                  <template v-else>sistema</template>
                  <template v-if="e.sujeto"> → </template>
                  <Link v-if="e.sujeto_url" :href="e.sujeto_url" class="underline hover:text-dark">{{ e.sujeto?.name }}</Link>
                  <template v-if="e.ip"> · {{ e.ip }}</template>
                </p>
              </div>

              <span class="shrink-0 font-mono text-[0.6875rem] text-dark/50">{{ fechaHora(e.cuando) }}</span>
            </div>
          </li>
        </ul>

        <nav v-if="operacion.last_page > 1" class="flex flex-wrap justify-center gap-1 border-t border-dark/15 bg-cream-50 px-4 py-3" aria-label="Paginación">
          <Link
            v-for="enlace in operacion.links" :key="enlace.label" :href="enlace.url ?? ''"
            class="min-h-[32px] min-w-[32px] border px-2 py-1 text-center font-mono text-xs"
            :class="enlace.active ? 'border-dark bg-dark text-white' : enlace.url ? 'border-dark/20 text-dark hover:border-dark' : 'border-transparent text-dark/25'"
            v-html="enlace.label"
          />
        </nav>
      </template>

      <!-- ── Acceso ─────────────────────────────────────────────────────── -->
      <template v-else-if="acceso">
        <div class="grid gap-2 border-b border-dark/15 bg-cream-50 p-3 sm:grid-cols-[1fr_auto_auto]">
          <input v-model="correo" type="search" placeholder="Correo…" aria-label="Filtrar por correo"
            class="w-full border border-dark/25 bg-white px-3 py-2 text-sm focus:border-dark" />

          <select v-model="tipo" aria-label="Filtrar por tipo" class="border border-dark/25 bg-white px-2.5 py-2 text-sm focus:border-dark">
            <option value="">Todo evento</option>
            <option v-for="t in tipos" :key="t.valor" :value="t.valor">{{ t.etiqueta }}</option>
          </select>

          <label class="flex items-center gap-2 border border-dark/25 bg-white px-3 py-2 text-sm text-dark">
            <input v-model="fallos" type="checkbox" class="h-4 w-4 rounded-none border-dark" />
            Solo fallos
          </label>
        </div>

        <p v-if="!acceso.data.length" class="px-4 py-10 text-center text-sm text-dark/50">
          Nada registrado con esos filtros.
        </p>

        <div v-else class="overflow-x-auto">
          <table class="w-full min-w-[46rem] text-sm">
            <thead class="border-b border-dark/15 bg-cream-50">
              <tr class="text-left font-mono text-[0.625rem] uppercase tracking-[0.1em] text-dark/50">
                <th scope="col" class="px-4 py-2 font-normal">Evento</th>
                <th scope="col" class="px-3 py-2 font-normal">Cuenta</th>
                <th scope="col" class="px-3 py-2 font-normal">IP</th>
                <th scope="col" class="px-3 py-2 font-normal">Cuándo</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-dark/10">
              <tr v-for="e in acceso.data" :key="e.id" :class="e.delicado || !e.exito ? 'bg-red-50/40' : ''">
                <td class="px-4 py-2.5">
                  <span class="flex items-center gap-2">
                    <ShieldAlert v-if="e.delicado" :size="13" class="shrink-0 text-red-700" aria-hidden="true" />
                    <span class="text-dark">{{ e.etiqueta }}</span>
                    <Estado v-if="!e.exito" tono="problema" texto="falló" :punto="false" />
                  </span>
                </td>
                <td class="px-3 py-2.5 text-xs">
                  <span class="block text-dark">{{ e.usuario?.name ?? '—' }}</span>
                  <span class="block truncate text-dark/55">{{ e.correo }}</span>
                </td>
                <td class="px-3 py-2.5 font-mono text-[0.6875rem] text-dark/60">{{ e.ip }}</td>
                <td class="px-3 py-2.5 font-mono text-[0.6875rem] text-dark/60">{{ fechaHora(e.cuando) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <nav v-if="acceso.last_page > 1" class="flex flex-wrap justify-center gap-1 border-t border-dark/15 bg-cream-50 px-4 py-3" aria-label="Paginación">
          <Link
            v-for="enlace in acceso.links" :key="enlace.label" :href="enlace.url ?? ''"
            class="min-h-[32px] min-w-[32px] border px-2 py-1 text-center font-mono text-xs"
            :class="enlace.active ? 'border-dark bg-dark text-white' : enlace.url ? 'border-dark/20 text-dark hover:border-dark' : 'border-transparent text-dark/25'"
            v-html="enlace.label"
          />
        </nav>
      </template>
    </Panel>
  </AuthenticatedLayout>
</template>
