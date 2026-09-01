<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Search, Download, Link2, X, Wifi, WifiOff, Clock, UserCheck } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'
import Estado from '@/Components/Panel/Estado.vue'
import ListaResponsiva, { type Columna } from '@/Components/Panel/ListaResponsiva.vue'
import Paginacion from '@/Components/Panel/Paginacion.vue'

/** Fase 5 — la pantalla de accesos del panel operativo. */
const props = defineProps<{
  dentroAhora: any[]
  registro: any
  filtros: Record<string, any>
  noReconocidos: any[]
  sinVincular: number[]
  estadoAgente: { visto_en: string | null; minutos: number | null; sano: boolean; nunca: boolean }
}>()

const buscar = ref(props.filtros.buscar ?? '')
const tipo = ref(props.filtros.tipo ?? '')
let t: number | undefined
function filtrar() {
  router.get(route('accesos.index'), { buscar: buscar.value || undefined, tipo: tipo.value || undefined },
    { preserveState: true, replace: true })
}
watch(buscar, () => { if (t) clearTimeout(t); t = window.setTimeout(filtrar, 350) })
watch(tipo, filtrar)

// Vincular un rostro a un miembro.
const vinc = useForm({ person_id: null as number | null, email: '' })
const abierto = ref(false)
function abrirVincular(personId: number) { vinc.reset(); vinc.clearErrors(); vinc.person_id = personId; abierto.value = true }
function enviarVincular() {
  vinc.post(route('accesos.vincular'), { preserveScroll: true, onSuccess: () => { abierto.value = false } })
}

const hora = (iso: string) => iso ? new Date(iso).toLocaleString('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—'
const horaCorta = (iso: string) => iso ? new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) : '—'

const columnas: Columna[] = [
  { clave: 'ocurrido_en', etiqueta: 'Cuándo', rol: 'identidad' },
  { clave: 'quien', etiqueta: 'Quién', rol: 'resumen' },
  { clave: 'tipo', etiqueta: 'Tipo', rol: 'resumen', sinEtiqueta: true },
  { clave: 'device_key', etiqueta: 'Dispositivo', rol: 'detalle' },
]
</script>

<template>
  <Head title="Accesos" />

  <AuthenticatedLayout>
    <template #breadcrumb><span class="font-display font-bold text-dark">Accesos</span></template>

    <!-- Estado del sistema: lo primero, porque un tablero en cero por un agente
         caído es peor que uno que avisa. -->
    <div
      class="mb-6 flex items-center gap-3 border-2 p-4"
      :class="estadoAgente.sano ? 'border-emerald-600/40 bg-emerald-50' : 'border-red-600/50 bg-red-50'"
    >
      <component :is="estadoAgente.sano ? Wifi : WifiOff" :size="22" :class="estadoAgente.sano ? 'text-emerald-700' : 'text-red-700'" aria-hidden="true" />
      <div class="min-w-0">
        <p class="font-display text-sm font-bold" :class="estadoAgente.sano ? 'text-emerald-900' : 'text-red-900'">
          <template v-if="estadoAgente.sano">Agente en línea</template>
          <template v-else-if="estadoAgente.nunca">El agente aún no ha reportado</template>
          <template v-else>El agente lleva {{ estadoAgente.minutos }} min sin reportar</template>
        </p>
        <p class="font-body text-xs" :class="estadoAgente.sano ? 'text-emerald-800/80' : 'text-red-800/80'">
          <template v-if="estadoAgente.sano">Los accesos llegan en tiempo real.</template>
          <template v-else>El registro puede estar incompleto hasta que el agente vuelva. La puerta sigue funcionando sola.</template>
        </p>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Dentro ahora -->
      <Panel titulo="Dentro ahora" :contador="dentroAhora.length" padding="none">
        <p v-if="!dentroAhora.length" class="px-4 py-6 text-center text-sm text-dark/50">No hay nadie en el espacio.</p>
        <ul v-else class="divide-y divide-dark/10">
          <li v-for="p in dentroAhora" :key="p.id" class="flex items-center justify-between gap-3 px-4 py-2.5">
            <div class="min-w-0">
              <p class="truncate font-display text-sm font-bold text-dark">{{ p.miembro ?? 'Sin nombre' }}</p>
              <p class="text-xs text-dark/60">{{ p.espacio }}</p>
            </div>
            <span class="shrink-0 font-mono text-xs text-dark/70">desde {{ horaCorta(p.desde) }}</span>
          </li>
        </ul>
      </Panel>

      <!-- Rostros por vincular (Fase 3, camino de vincular) -->
      <Panel titulo="Rostros por vincular" :contador="sinVincular.length" padding="none" class="lg:col-span-2">
        <p v-if="!sinVincular.length" class="px-4 py-6 text-center text-sm text-dark/50">
          Todos los rostros reconocidos están vinculados a un miembro.
        </p>
        <div v-else class="flex flex-wrap gap-2 p-4">
          <button
            v-for="pid in sinVincular" :key="pid" type="button" @click="abrirVincular(pid)"
            class="flex min-h-[40px] items-center gap-2 border-2 border-dark bg-white px-3 font-display text-sm font-bold text-dark hover:bg-nodo-400"
          >
            <UserCheck :size="15" aria-hidden="true" /> Persona {{ pid }}
          </button>
        </div>
        <p class="border-t border-dark/10 px-4 py-2 font-body text-xs text-dark/60">
          El terminal reconoció estos rostros pero Nódico aún no sabe de quién son. Vincúlalos a un miembro.
        </p>
      </Panel>
    </div>

    <!-- Registro completo -->
    <Panel titulo="Registro de accesos" :contador="registro.total" padding="none" class="mt-6">
      <template #acciones>
        <a :href="route('accesos.exportar', filtros)" class="flex min-h-[36px] items-center gap-1.5 border border-dark/25 px-3 font-display text-xs font-bold text-dark hover:border-dark">
          <Download :size="14" aria-hidden="true" /> Exportar
        </a>
      </template>

      <div class="grid gap-2 border-b border-dark/15 bg-cream-50 p-3 sm:grid-cols-[1fr_auto]">
        <div class="relative">
          <Search :size="15" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-dark/40" aria-hidden="true" />
          <input v-model="buscar" type="search" placeholder="Nombre del miembro…" aria-label="Buscar" class="w-full border border-dark/25 bg-white py-2 pl-8 pr-3 text-sm focus:border-dark" />
        </div>
        <select v-model="tipo" aria-label="Tipo" class="border border-dark/25 bg-white px-2.5 py-2 text-sm focus:border-dark">
          <option value="">Todos</option>
          <option value="reconocido">Reconocidos</option>
          <option value="extrano">No reconocidos</option>
        </select>
      </div>

      <ListaResponsiva :columnas="columnas" :filas="registro.data" clave-fila="id" vacio="Sin accesos con esos filtros.">
        <template #ocurrido_en="{ fila: e }"><span class="font-mono text-xs">{{ hora(e.ocurrido_en) }}</span></template>
        <template #quien="{ fila: e }">
          <template v-if="e.miembro">{{ e.miembro }}</template>
          <template v-else-if="e.reconocido">Persona {{ e.person_id }} <span class="text-dark/50">(sin vincular)</span></template>
          <template v-else>—</template>
        </template>
        <template #tipo="{ fila: e }">
          <Estado :tono="e.reconocido ? 'bien' : 'problema'" :texto="e.reconocido ? 'reconocido' : 'extraño'" :punto="false" tamano="sm" />
        </template>
        <template #device_key="{ fila: e }">{{ e.device_key || '—' }}</template>
      </ListaResponsiva>

      <Paginacion v-if="registro.last_page > 1" :links="registro.links" />
    </Panel>

    <!-- Modal vincular -->
    <Teleport to="body">
      <div v-if="abierto" class="fixed inset-0 z-50 flex items-end justify-center bg-tinta/60 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" @click.self="abierto = false">
        <form class="w-full max-w-md border-2 border-dark bg-white" @submit.prevent="enviarVincular">
          <div class="flex items-center justify-between border-b border-dark/15 bg-cream-50 px-4 py-3">
            <h2 class="font-display text-sm font-bold text-dark">Vincular persona {{ vinc.person_id }}</h2>
            <button type="button" aria-label="Cerrar" class="text-dark/50 hover:text-dark" @click="abierto = false"><X :size="18" /></button>
          </div>
          <div class="space-y-3 p-4">
            <p class="font-body text-xs text-dark/60">
              Amarra este rostro de Smart Pass a un miembro de Nódico. A partir de aquí, sus accesos se registran a su nombre.
            </p>
            <div>
              <label for="v-email" class="mb-1 block text-xs font-bold text-dark">Correo del miembro</label>
              <input id="v-email" v-model="vinc.email" type="email" class="w-full border border-dark/25 px-2.5 py-2 text-sm focus:border-dark" />
              <p v-if="vinc.errors.email" class="mt-1 text-xs text-red-700">{{ vinc.errors.email }}</p>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[40px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="abierto = false">Cancelar</button>
            <button type="submit" :disabled="vinc.processing" class="flex min-h-[40px] items-center gap-1.5 border-2 border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark disabled:opacity-50">
              <Link2 :size="14" aria-hidden="true" /> Vincular
            </button>
          </div>
        </form>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
