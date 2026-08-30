<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Search, ScanFace, ChevronRight } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'
import Estado from '@/Components/Panel/Estado.vue'

/** Listado de miembros con filtros (Fase 3.2). */
const props = defineProps<{
  miembros: any
  planes: any[]
  filtros: Record<string, any>
}>()

const buscar = ref(props.filtros.buscar ?? '')
const planId = ref(props.filtros.plan_id ?? '')
const estado = ref(props.filtros.estado ?? '')

let temporizador: number | undefined

function filtrar() {
  router.get(route('miembros.index'), {
    buscar: buscar.value || undefined,
    plan_id: planId.value || undefined,
    estado: estado.value || undefined,
  }, { preserveState: true, replace: true })
}

watch(buscar, () => {
  if (temporizador) window.clearTimeout(temporizador)
  temporizador = window.setTimeout(filtrar, 300)
})

watch([planId, estado], filtrar)

const fecha = (iso: string | null) =>
  iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }) : '—'

/** El estado combina membresía y cuenta: lo que recepción necesita ver de un vistazo. */
function tono(m: any): { tono: 'bien' | 'atencion' | 'problema' | 'neutro'; texto: string } {
  if (m.estado_cuenta === 'suspendida') return { tono: 'problema', texto: 'suspendida' }
  if (!m.plan) return { tono: 'neutro', texto: 'sin plan' }
  if (m.dias_para_vencer === null) return { tono: 'neutro', texto: '—' }
  if (m.dias_para_vencer < 0) return { tono: 'problema', texto: 'vencida' }
  if (m.dias_para_vencer <= 7) return { tono: 'atencion', texto: `${m.dias_para_vencer} d` }
  return { tono: 'bien', texto: 'activa' }
}

const filtrosEstado = [
  { valor: '', etiqueta: 'Todos' },
  { valor: 'activa', etiqueta: 'Con membresía activa' },
  { valor: 'por_vencer', etiqueta: 'Vencen esta semana' },
  { valor: 'vencida', etiqueta: 'Sin membresía activa' },
  { valor: 'suspendida', etiqueta: 'Cuenta suspendida' },
  { valor: 'sin_faceid', etiqueta: 'Sin Face ID' },
]
</script>

<template>
  <Head title="Miembros" />

  <AuthenticatedLayout>
    <template #breadcrumb>
      <span class="font-display font-bold text-dark">Miembros</span>
    </template>

    <Panel padding="none">
      <template #acciones>
        <span class="font-mono text-[0.6875rem] text-dark/55">{{ miembros.total }} en total</span>
      </template>

      <div class="border-b border-dark/15 bg-cream-50 p-3">
        <div class="grid gap-2 sm:grid-cols-[1fr_auto_auto]">
          <div class="relative">
            <Search :size="15" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-dark/40" aria-hidden="true" />
            <input
              v-model="buscar" type="search" placeholder="Nombre, correo, empresa o teléfono…"
              aria-label="Buscar miembro"
              class="w-full border border-dark/25 bg-white py-2 pl-8 pr-3 text-sm focus:border-dark"
            />
          </div>

          <select v-model="planId" aria-label="Filtrar por plan" class="border border-dark/25 bg-white px-2.5 py-2 text-sm focus:border-dark">
            <option value="">Todos los planes</option>
            <option v-for="p in planes" :key="p.id" :value="p.id">{{ p.nombre }}</option>
          </select>

          <select v-model="estado" aria-label="Filtrar por estado" class="border border-dark/25 bg-white px-2.5 py-2 text-sm focus:border-dark">
            <option v-for="f in filtrosEstado" :key="f.valor" :value="f.valor">{{ f.etiqueta }}</option>
          </select>
        </div>
      </div>

      <p v-if="!miembros.data.length" class="px-4 py-10 text-center text-sm text-dark/50">
        Nadie con esos filtros.
      </p>

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[44rem] text-sm">
          <thead class="border-b border-dark/15 bg-cream-50">
            <tr class="text-left font-mono text-[0.625rem] uppercase tracking-[0.1em] text-dark/50">
              <th scope="col" class="px-4 py-2 font-normal">Miembro</th>
              <th scope="col" class="px-3 py-2 font-normal">Contacto</th>
              <th scope="col" class="px-3 py-2 font-normal">Plan</th>
              <th scope="col" class="px-3 py-2 font-normal">Vence</th>
              <th scope="col" class="px-3 py-2 font-normal">Estado</th>
              <th scope="col" class="w-8 px-3 py-2"><span class="sr-only">Abrir</span></th>
            </tr>
          </thead>

          <tbody class="divide-y divide-dark/10">
            <tr
              v-for="m in miembros.data" :key="m.id"
              class="cursor-pointer transition-colors hover:bg-cream-50"
              @click="router.visit(route('miembros.show', m.id))"
            >
              <td class="px-4 py-2.5">
                <Link
                  :href="route('miembros.show', m.id)"
                  class="flex items-center gap-2 font-display font-bold text-dark
                         focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                         focus-visible:outline-dark"
                  @click.stop
                >
                  {{ m.nombre }}
                  <ScanFace v-if="!m.face_id_ok" :size="14" class="shrink-0 text-amber-600" aria-label="Sin Face ID" />
                </Link>
                <span v-if="m.empresa" class="text-xs text-dark/55">{{ m.empresa }}</span>
              </td>

              <td class="px-3 py-2.5 text-xs text-dark/70">
                <span class="block truncate">{{ m.email }}</span>
                <span v-if="m.telefono" class="block font-mono text-[0.6875rem] text-dark/50">{{ m.telefono }}</span>
              </td>

              <td class="px-3 py-2.5 text-xs text-dark">{{ m.plan ?? '—' }}</td>
              <td class="px-3 py-2.5 font-mono text-xs text-dark/70">{{ fecha(m.vence) }}</td>
              <td class="px-3 py-2.5"><Estado v-bind="tono(m)" /></td>
              <td class="px-3 py-2.5 text-dark/30"><ChevronRight :size="15" aria-hidden="true" /></td>
            </tr>
          </tbody>
        </table>
      </div>

      <nav
        v-if="miembros.last_page > 1"
        class="flex flex-wrap items-center justify-center gap-1 border-t border-dark/15 bg-cream-50 px-4 py-3"
        aria-label="Paginación"
      >
        <Link
          v-for="enlace in miembros.links" :key="enlace.label"
          :href="enlace.url ?? ''"
          :disabled="!enlace.url"
          class="min-h-[32px] min-w-[32px] border px-2 py-1 text-center font-mono text-xs transition-colors"
          :class="enlace.active
            ? 'border-dark bg-dark text-white'
            : enlace.url ? 'border-dark/20 text-dark hover:border-dark' : 'border-transparent text-dark/25'"
          v-html="enlace.label"
        />
      </nav>
    </Panel>
  </AuthenticatedLayout>
</template>
