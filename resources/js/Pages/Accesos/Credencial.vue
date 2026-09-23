<script setup lang="ts">
import { ref, onMounted, nextTick, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { QrCode, CheckCircle2, AlertTriangle, XCircle, UserRound } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'

/**
 * Validar la credencial QR de la app en recepción.
 *
 * Un lector USB o Bluetooth escribe el código en el campo y pulsa Enter; el
 * campo se queda con el foco para el siguiente. El semáforo lo decide el
 * servidor: una membresía suspendida o vencida sale en rojo aunque el código
 * guardado en el teléfono siga siendo válido.
 */
const props = defineProps<{
  resultado: null | {
    encontrada: boolean
    semaforo?: 'verde' | 'ambar' | 'rojo'
    motivo?: string
    persona?: {
      id: number; nombre: string; email: string; avatar_url: string | null
      plan: string | null; vence: string | null; rol: string | null; face_id_ok: boolean; url: string
    }
  }
}>()

const form = useForm({ codigo: '' })
const campo = ref<HTMLInputElement | null>(null)

function validar() {
  if (!form.codigo.trim()) return
  form.post(route('accesos.credencial.validar'), {
    preserveScroll: true,
    onFinish: () => {
      form.reset('codigo')
      nextTick(() => campo.value?.focus())
    },
  })
}

onMounted(() => campo.value?.focus())
watch(() => props.resultado, () => nextTick(() => campo.value?.focus()))

const estilos = {
  verde: { clase: 'border-emerald-600 bg-emerald-50 text-emerald-900', icono: CheckCircle2, titulo: 'Puede pasar' },
  ambar: { clase: 'border-amber-500 bg-amber-50 text-amber-900', icono: AlertTriangle, titulo: 'Revisar' },
  rojo:  { clase: 'border-red-600 bg-red-50 text-red-900', icono: XCircle, titulo: 'No puede pasar' },
}

function fecha(iso: string | null) {
  if (!iso) return '—'
  const [a, m, d] = iso.split('-').map(Number)
  return new Date(a, m - 1, d).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })
}
</script>

<template>
  <Head title="Validar credencial" />

  <AuthenticatedLayout>
    <template #breadcrumb><span class="font-display font-bold text-dark">Validar credencial</span></template>

    <div class="mx-auto grid max-w-3xl gap-4">
      <Panel titulo="Escanea la credencial de la app">
        <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="validar">
          <label class="sr-only" for="codigo">Código de la credencial</label>
          <div class="relative flex-1">
            <QrCode class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-dark/50" aria-hidden="true" />
            <input
              id="codigo"
              ref="campo"
              v-model="form.codigo"
              type="text"
              autocomplete="off"
              spellcheck="false"
              placeholder="Apunta el lector al QR o pega el código"
              class="w-full border border-dark/30 py-3 pl-10 pr-3 font-mono text-base focus:border-dark focus:ring-0"
            >
          </div>
          <button type="submit" :disabled="form.processing" class="border-2 border-dark bg-nodo-400 px-5 py-3 font-bold text-dark disabled:opacity-50">
            Validar
          </button>
        </form>
        <p v-if="form.errors.codigo" class="mt-2 text-sm text-red-700">{{ form.errors.codigo }}</p>
        <p class="mt-3 text-sm text-dark/60">
          La credencial identifica a la persona; no abre el torno. Cada validación queda en la bitácora.
        </p>
      </Panel>

      <div v-if="resultado && !resultado.encontrada" class="flex items-center gap-3 border-2 border-red-600 bg-red-50 p-5 text-red-900" role="status">
        <XCircle class="h-8 w-8 shrink-0" aria-hidden="true" />
        <div>
          <p class="font-display text-lg font-bold">Credencial no reconocida</p>
          <p class="text-sm">No corresponde a ninguna cuenta, o se renovó y esta ya no vale. Pídele que abra la app con datos.</p>
        </div>
      </div>

      <div
        v-else-if="resultado && resultado.persona && resultado.semaforo"
        class="border-2 p-5"
        :class="estilos[resultado.semaforo].clase"
        role="status"
      >
        <div class="flex items-center gap-3">
          <component :is="estilos[resultado.semaforo].icono" class="h-8 w-8 shrink-0" aria-hidden="true" />
          <div>
            <p class="font-display text-xl font-bold">{{ estilos[resultado.semaforo].titulo }}</p>
            <p class="text-sm">{{ resultado.motivo }}</p>
          </div>
        </div>

        <div class="mt-5 flex items-center gap-4 border-t border-current/20 pt-5 text-dark">
          <img
            v-if="resultado.persona.avatar_url"
            :src="resultado.persona.avatar_url"
            alt=""
            class="h-20 w-20 shrink-0 border border-dark/20 object-cover"
          >
          <div v-else class="flex h-20 w-20 shrink-0 items-center justify-center border border-dark/20 bg-white">
            <UserRound class="h-8 w-8 text-dark/40" aria-hidden="true" />
          </div>
          <dl class="grid flex-1 grid-cols-2 gap-x-4 gap-y-1 text-sm">
            <dt class="text-dark/60">Nombre</dt>
            <dd class="font-bold">
              <Link :href="resultado.persona.url" class="underline">{{ resultado.persona.nombre }}</Link>
            </dd>
            <dt class="text-dark/60">Plan</dt>
            <dd>{{ resultado.persona.plan ?? 'Sin membresía' }}<span v-if="resultado.persona.rol === 'acompanante'"> · acompañante</span></dd>
            <dt class="text-dark/60">Vence</dt>
            <dd>{{ fecha(resultado.persona.vence) }}</dd>
            <dt class="text-dark/60">Face ID</dt>
            <dd>{{ resultado.persona.face_id_ok ? 'Registrado' : 'Pendiente' }}</dd>
          </dl>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
