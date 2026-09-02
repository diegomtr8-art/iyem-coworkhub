<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { Plus, Copy, Trash2, ExternalLink, Monitor } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'
import Estado from '@/Components/Panel/Estado.vue'

defineProps<{ enlaces: any[] }>()

const form = useForm({ nombre: '' })
function generar() { form.post(route('tablero.enlaces.generar'), { preserveScroll: true, onSuccess: () => form.reset() }) }
function revocar(e: any) {
  if (!confirm('¿Revocar este enlace? La pantalla que lo use dejará de funcionar.')) return
  router.post(route('tablero.enlaces.revocar', e.id), {}, { preserveScroll: true })
}
const copiado = ref<number | null>(null)
function copiar(e: any) { navigator.clipboard?.writeText(e.url); copiado.value = e.id; setTimeout(() => copiado.value = null, 1500) }
const cuando = (iso: string | null) => iso ? new Date(iso).toLocaleString('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : 'nunca'
</script>

<template>
  <Head title="Tablero — enlaces" />
  <AuthenticatedLayout>
    <template #breadcrumb><span class="font-display font-bold text-dark">Tablero de ocupación</span></template>

    <div class="mb-6 flex items-start gap-3 border-2 border-dark bg-cream-50 p-4">
      <Monitor :size="22" class="mt-0.5 text-dark" aria-hidden="true" />
      <div>
        <p class="font-display text-sm font-bold text-dark">El tablero en vivo para la tele y la tableta</p>
        <p class="font-body text-xs text-dark/60">Genera un enlace de solo lectura y ábrelo en la pantalla. No pide contraseña y no muestra nombres. Si se pierde, revócalo.</p>
      </div>
    </div>

    <Panel titulo="Enlaces" :contador="enlaces.length" padding="none">
      <template #acciones>
        <form class="flex items-center gap-2" @submit.prevent="generar">
          <input v-model="form.nombre" type="text" placeholder="Ej. Tele recepción" maxlength="80" class="min-h-[36px] border border-dark/25 px-2.5 text-sm focus:border-dark" />
          <button type="submit" :disabled="form.processing || !form.nombre" class="flex min-h-[36px] items-center gap-1.5 border-2 border-dark bg-nodo-400 px-3 font-display text-xs font-bold text-dark disabled:opacity-50"><Plus :size="14" /> Generar</button>
        </form>
      </template>

      <ul class="divide-y divide-dark/10">
        <li v-for="e in enlaces" :key="e.id" class="flex flex-wrap items-center gap-3 px-4 py-3" :class="{ 'opacity-50': e.revocado }">
          <div class="min-w-0 flex-1">
            <p class="font-display text-sm font-bold text-dark">{{ e.nombre }}
              <Estado v-if="e.revocado" tono="neutro" texto="revocado" :punto="false" tamano="sm" class="ml-2" />
            </p>
            <p class="truncate font-mono text-xs text-dark/50">{{ e.url }}</p>
            <p class="text-xs text-dark/50">Último uso: {{ cuando(e.ultimo_uso_en) }}</p>
          </div>
          <div v-if="!e.revocado" class="flex items-center gap-2">
            <button type="button" @click="copiar(e)" class="inline-flex min-h-[36px] items-center gap-1 border border-dark/25 px-2.5 text-xs font-bold text-dark hover:border-dark"><Copy :size="13" /> {{ copiado === e.id ? 'Copiado' : 'Copiar' }}</button>
            <a :href="e.url" target="_blank" class="inline-flex min-h-[36px] items-center gap-1 border border-dark/25 px-2.5 text-xs font-bold text-dark hover:border-dark"><ExternalLink :size="13" /> Abrir</a>
            <button type="button" @click="revocar(e)" aria-label="Revocar" class="inline-flex min-h-[36px] items-center px-2 text-dark/50 hover:text-red-700"><Trash2 :size="15" /></button>
          </div>
        </li>
        <li v-if="!enlaces.length" class="px-4 py-8 text-center text-sm text-dark/50">Aún no hay enlaces. Genera el primero.</li>
      </ul>
    </Panel>
  </AuthenticatedLayout>
</template>
