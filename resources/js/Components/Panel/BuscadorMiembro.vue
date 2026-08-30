<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { Search, Loader2, ScanFace } from 'lucide-vue-next'

/**
 * Buscador global de miembro (Fase 3.1).
 *
 * Siempre a mano en la cabecera del panel. Recepción no navega a un listado ni
 * busca por id: alguien dice su nombre en el mostrador y hay que encontrarlo ya.
 * Por eso busca por nombre, correo, empresa **y teléfono**, incluido el teléfono
 * dictado con espacios.
 *
 * Ctrl/⌘+K lo abre desde cualquier pantalla: con las manos en el teclado, eso
 * es más rápido que apuntar con el ratón.
 */
const abierto = ref(false)
const consulta = ref('')
const resultados = ref<any[]>([])
const cargando = ref(false)
const indice = ref(0)
const campo = ref<HTMLInputElement | null>(null)

let temporizador: number | undefined
let peticion: AbortController | undefined

watch(consulta, (texto) => {
  if (temporizador) window.clearTimeout(temporizador)

  if (texto.trim().length < 2) {
    resultados.value = []
    cargando.value = false
    return
  }

  cargando.value = true

  // 250 ms: teclear rápido no dispara una consulta por letra.
  temporizador = window.setTimeout(async () => {
    peticion?.abort()
    peticion = new AbortController()

    try {
      const url = new URL(route('buscar.miembro'), window.location.origin)
      url.searchParams.set('q', texto)

      const respuesta = await fetch(url, {
        headers: { Accept: 'application/json' },
        signal: peticion.signal,
      })

      resultados.value = respuesta.ok ? (await respuesta.json()).resultados : []
      indice.value = 0
    } catch (e: any) {
      if (e?.name !== 'AbortError') resultados.value = []
    } finally {
      cargando.value = false
    }
  }, 250)
})

function abrir() {
  abierto.value = true
  requestAnimationFrame(() => campo.value?.focus())
}

function cerrar() {
  abierto.value = false
  consulta.value = ''
  resultados.value = []
}

function ir(miembro: any) {
  cerrar()
  router.visit(miembro.url)
}

function teclado(evento: KeyboardEvent) {
  if ((evento.ctrlKey || evento.metaKey) && evento.key.toLowerCase() === 'k') {
    evento.preventDefault()
    abierto.value ? cerrar() : abrir()
    return
  }

  if (!abierto.value) return

  if (evento.key === 'Escape') { cerrar(); return }

  if (evento.key === 'ArrowDown') {
    evento.preventDefault()
    indice.value = Math.min(indice.value + 1, resultados.value.length - 1)
  } else if (evento.key === 'ArrowUp') {
    evento.preventDefault()
    indice.value = Math.max(indice.value - 1, 0)
  } else if (evento.key === 'Enter' && resultados.value[indice.value]) {
    evento.preventDefault()
    ir(resultados.value[indice.value])
  }
}

onMounted(() => window.addEventListener('keydown', teclado))
onUnmounted(() => {
  window.removeEventListener('keydown', teclado)
  if (temporizador) window.clearTimeout(temporizador)
  peticion?.abort()
})
</script>

<template>
  <button
    type="button"
    class="flex min-h-[38px] items-center gap-2 border border-dark/25 bg-white px-3 text-sm
           text-dark/60 transition-colors hover:border-dark/50 focus-visible:outline
           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
    @click="abrir"
  >
    <Search :size="15" aria-hidden="true" />
    <span class="hidden sm:inline">Buscar miembro</span>
    <kbd class="ml-2 hidden border border-dark/20 px-1.5 py-0.5 font-mono text-[0.625rem] lg:inline">
      Ctrl K
    </kbd>
  </button>

  <Teleport to="body">
    <div
      v-if="abierto"
      class="fixed inset-0 z-[60] flex items-start justify-center bg-tinta/60 p-4 pt-[12vh]"
      role="dialog" aria-modal="true" aria-label="Buscar miembro"
      @click.self="cerrar"
    >
      <div class="w-full max-w-xl border-2 border-dark bg-white shadow-dura">
        <div class="flex items-center gap-3 border-b border-dark/15 px-4">
          <Search :size="18" class="shrink-0 text-dark/50" aria-hidden="true" />
          <input
            ref="campo"
            v-model="consulta"
            type="search"
            placeholder="Nombre, correo, empresa o teléfono…"
            class="w-full border-0 bg-transparent py-3.5 text-base text-dark placeholder:text-dark/40"
            autocomplete="off"
            aria-label="Buscar miembro"
          />
          <Loader2 v-if="cargando" :size="16" class="shrink-0 animate-spin text-dark/40 motion-reduce:animate-none" aria-hidden="true" />
        </div>

        <ul v-if="resultados.length" class="max-h-[50vh] divide-y divide-dark/10 overflow-y-auto">
          <li v-for="(m, i) in resultados" :key="m.id">
            <button
              type="button"
              class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors"
              :class="i === indice ? 'bg-nodo-400' : 'hover:bg-cream-50'"
              @click="ir(m)"
              @mouseenter="indice = i"
            >
              <span class="min-w-0">
                <span class="block truncate font-display text-sm font-bold text-dark">{{ m.nombre }}</span>
                <span class="block truncate font-body text-xs text-dark/60">
                  {{ m.email }}<template v-if="m.telefono"> · {{ m.telefono }}</template>
                  <template v-if="m.empresa"> · {{ m.empresa }}</template>
                </span>
              </span>

              <span class="flex shrink-0 items-center gap-2">
                <ScanFace
                  v-if="!m.face_id_ok" :size="15"
                  class="text-amber-600" aria-label="Sin Face ID"
                />
                <span v-if="m.plan" class="border border-dark/20 px-1.5 py-0.5 font-mono text-[0.625rem] text-dark/70">
                  {{ m.plan }}
                </span>
              </span>
            </button>
          </li>
        </ul>

        <p
          v-else-if="consulta.trim().length >= 2 && !cargando"
          class="px-4 py-6 text-center font-body text-sm text-dark/60"
        >
          Nadie con «{{ consulta }}».
        </p>

        <p v-else-if="consulta.trim().length < 2" class="px-4 py-6 text-center font-body text-sm text-dark/50">
          Escribe al menos dos letras.
        </p>
      </div>
    </div>
  </Teleport>
</template>
