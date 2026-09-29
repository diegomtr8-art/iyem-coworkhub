<script setup lang="ts">
/**
 * Una sección del sitio público en el módulo «Página Web»: sus campos, dónde
 * sale cada uno y los cuatro gestos que hacen falta para atreverse a tocarla —
 * vista previa, guardar, deshacer y volver al original.
 *
 * Cada sección se guarda por separado y de forma explícita: tocar el teléfono
 * no publica a medias el texto de otra sección.
 */
import Estado from '@/Components/Panel/Estado.vue'
import Panel from '@/Components/Panel/Panel.vue'
import { router, useForm } from '@inertiajs/vue3'
import { Eye, ExternalLink, RotateCcw, Save, Undo2 } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'

type Campo = {
  nombre: string
  etiqueta: string
  tipo: 'texto' | 'parrafo' | 'email' | 'telefono' | 'url' | 'usuario' | 'youtube' | 'numero'
  ayuda: string | null
  maximo: number | null
  requerido: boolean
}

type Seccion = {
  clave: string
  titulo: string
  aparece: string
  enSitio: string
  vistaPrevia: string
  personalizada: boolean
  valores: Record<string, string | null>
  ultimoCambio: { por: string; cuando: string } | null
  puedeDeshacer: boolean
  campos: Campo[]
}

const props = defineProps<{ seccion: Seccion }>()
const emit = defineEmits<{ sucia: [clave: string, sucia: boolean] }>()

const form = useForm<Record<string, string>>(
  Object.fromEntries(props.seccion.campos.map((c) => [c.nombre, props.seccion.valores[c.nombre] ?? ''])),
)

watch(() => form.isDirty, (sucia) => emit('sucia', props.seccion.clave, sucia), { immediate: true })

/** Tras guardar, deshacer o restablecer, el servidor manda los valores nuevos: son la nueva base. */
watch(() => props.seccion.valores, (valores) => {
  if (form.isDirty) return
  props.seccion.campos.forEach((c) => (form[c.nombre] = valores[c.nombre] ?? ''))
  form.defaults()
}, { deep: true })

const tiposInput: Record<string, string> = { email: 'email', telefono: 'tel', url: 'url', texto: 'text', usuario: 'text', youtube: 'text', numero: 'text' }
const modosTeclado: Record<string, string> = { email: 'email', telefono: 'tel', url: 'url', texto: 'text', usuario: 'text', youtube: 'text', numero: 'decimal' }

// preserveState: sin él, Inertia vuelve a montar la pantalla tras cada envío y
// se pierde lo que se esté escribiendo, en esta sección (vista previa) o en otra
// (al guardar la de al lado). Los valores nuevos los recoge el watch de arriba.
const opciones = { errorBag: props.seccion.clave, preserveScroll: true, preserveState: true }

function guardar() {
  form.put(route('pagina-web.guardar', props.seccion.clave), {
    ...opciones,
    onSuccess: () => form.defaults(),
  })
}

const preparandoVista = ref(false)

/**
 * La ventana se abre en el clic, antes de ir al servidor: si se abre después,
 * el navegador la toma por una ventana emergente y la bloquea.
 *
 * Va por `router` y no por `form.post`: Inertia 2 toma como valores base lo que
 * un formulario envía con éxito, y el borrador pasaría a verse como guardado —
 * sin «Sin guardar» ni aviso al salir— sin haberse publicado nada.
 */
function verVistaPrevia() {
  const ventana = window.open('about:blank', '_blank')
  form.clearErrors()
  router.post(route('pagina-web.vista-previa', props.seccion.clave), form.data(), {
    ...opciones,
    onStart: () => (preparandoVista.value = true),
    onFinish: () => (preparandoVista.value = false),
    onSuccess: () => { if (ventana) ventana.location.href = props.seccion.vistaPrevia },
    onError: (errores) => { form.setError(errores as Record<string, string>); ventana?.close() },
  })
}

function deshacer() {
  if (!confirm(`¿Deshacer el último cambio de «${props.seccion.titulo}»? El sitio vuelve a mostrar la versión anterior.`)) return
  router.post(route('pagina-web.deshacer', props.seccion.clave), {}, { preserveScroll: true, preserveState: true })
}

function restablecer() {
  if (!confirm(`¿Volver al texto original de «${props.seccion.titulo}»? Se puede deshacer después.`)) return
  router.post(route('pagina-web.restablecer', props.seccion.clave), {}, { preserveScroll: true, preserveState: true })
}

const cuando = computed(() => props.seccion.ultimoCambio
  ? new Date(props.seccion.ultimoCambio.cuando).toLocaleString('es-MX', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
  : null)

const idCampo = (c: Campo) => `${props.seccion.clave}-${c.nombre}`
const largo = (c: Campo) => String(form[c.nombre] ?? '').length
</script>

<template>
  <Panel :titulo="seccion.titulo" padding="none">
    <template #acciones>
      <Estado v-if="form.isDirty" tono="atencion" texto="Sin guardar" />
      <Estado v-else-if="seccion.personalizada" tono="bien" texto="Editada" />
      <Estado v-else tono="neutro" texto="Texto original" />
    </template>

    <div class="border-b border-dark/10 px-4 py-3">
      <p class="text-xs text-dark/70">
        <span class="font-bold text-dark">Dónde sale:</span> {{ seccion.aparece }}
      </p>
      <p v-if="seccion.ultimoCambio" class="mt-1 text-xs text-dark/70">
        Último cambio: {{ seccion.ultimoCambio.por }}, {{ cuando }}
      </p>
    </div>

    <form class="divide-y divide-dark/10" novalidate @submit.prevent="guardar">
      <div v-for="campo in seccion.campos" :key="campo.nombre" class="px-4 py-4">
        <div class="flex items-baseline justify-between gap-3">
          <label :for="idCampo(campo)" class="font-display text-sm font-bold text-dark">
            {{ campo.etiqueta }}<span v-if="campo.requerido" class="text-dark/70" aria-hidden="true"> *</span>
          </label>
          <span
            v-if="campo.maximo"
            class="font-mono text-[0.6875rem]"
            :class="largo(campo) > campo.maximo ? 'text-red-700' : 'text-dark/70'"
            aria-hidden="true"
          >{{ largo(campo) }}/{{ campo.maximo }}</span>
        </div>

        <textarea
          v-if="campo.tipo === 'parrafo'"
          :id="idCampo(campo)"
          v-model="form[campo.nombre]"
          rows="3"
          :required="campo.requerido"
          :aria-invalid="Boolean(form.errors[campo.nombre])"
          :aria-describedby="`${idCampo(campo)}-ayuda ${idCampo(campo)}-error`"
          class="mt-2 w-full resize-y border border-dark/25 bg-white px-3 py-2.5 text-base text-dark focus:border-dark focus:outline-none focus:ring-2 focus:ring-nodo-400"
        />
        <div v-else class="mt-2 flex items-stretch">
          <span
            v-if="campo.tipo === 'usuario'"
            class="flex items-center border border-r-0 border-dark/25 bg-cream-50 px-3 font-mono text-sm text-dark/70"
            aria-hidden="true"
          >@</span>
          <input
            :id="idCampo(campo)"
            v-model="form[campo.nombre]"
            :type="tiposInput[campo.tipo]"
            :inputmode="modosTeclado[campo.tipo]"
            :autocomplete="campo.tipo === 'email' ? 'email' : 'off'"
            :required="campo.requerido"
            :aria-invalid="Boolean(form.errors[campo.nombre])"
            :aria-describedby="`${idCampo(campo)}-ayuda ${idCampo(campo)}-error`"
            class="min-h-[44px] w-full min-w-0 border border-dark/25 bg-white px-3 text-base text-dark focus:border-dark focus:outline-none focus:ring-2 focus:ring-nodo-400"
          />
        </div>

        <p v-if="campo.ayuda" :id="`${idCampo(campo)}-ayuda`" class="mt-1.5 text-xs text-dark/70">{{ campo.ayuda }}</p>
        <p v-if="form.errors[campo.nombre]" :id="`${idCampo(campo)}-error`" class="mt-1.5 text-sm font-medium text-red-700" role="alert">
          {{ form.errors[campo.nombre] }}
        </p>
      </div>

      <!-- Acciones principales: guardar va primero y es el único botón lleno.
           Solo estas quedan pegadas abajo mientras se edita; con las
           secundarias, a 375 px la barra ocupaba una quinta parte de la pantalla. -->
      <div class="sticky bottom-0 flex flex-wrap items-center gap-2 border-t border-dark/10 bg-cream-50 px-4 py-2.5">
        <button
          type="submit"
          :disabled="!form.isDirty || form.processing"
          class="inline-flex min-h-[44px] items-center gap-1.5 border-2 border-dark bg-nodo-400 px-4 font-display text-sm font-bold text-dark disabled:opacity-50"
        >
          <Save :size="15" aria-hidden="true" />
          <template v-if="form.processing">Guardando…</template>
          <!-- A 375 px las dos acciones principales tienen que caber en una fila. -->
          <template v-else>Guardar<span class="hidden sm:inline">&nbsp;y publicar</span></template>
        </button>
        <button
          type="button"
          :disabled="form.processing || preparandoVista"
          class="inline-flex min-h-[44px] items-center gap-1.5 border border-dark/25 bg-white px-3 text-sm font-bold text-dark hover:border-dark disabled:opacity-50"
          @click="verVistaPrevia"
        >
          <Eye :size="15" aria-hidden="true" /> {{ preparandoVista ? 'Preparando…' : 'Vista previa' }}
        </button>
        <button
          v-if="form.isDirty"
          type="button"
          class="inline-flex min-h-[44px] items-center px-3 text-sm font-medium text-dark/70 underline underline-offset-4 hover:text-dark"
          @click="form.reset(); form.clearErrors()"
        >
          Descartar cambios
        </button>
      </div>

      <div class="flex flex-wrap items-center gap-x-1 px-2 py-1">
        <a
          :href="seccion.enSitio"
          target="_blank"
          rel="noopener"
          class="inline-flex min-h-[44px] items-center gap-1 px-2 text-xs font-bold text-dark/70 hover:text-dark"
        >
          <ExternalLink :size="13" aria-hidden="true" /> Ver en el sitio
        </a>
        <button
          v-if="seccion.puedeDeshacer && !form.isDirty"
          type="button"
          class="inline-flex min-h-[44px] items-center gap-1 px-2 text-xs font-bold text-dark/70 hover:text-dark"
          @click="deshacer"
        >
          <Undo2 :size="13" aria-hidden="true" /> Deshacer último cambio
        </button>
        <button
          v-if="seccion.personalizada && !form.isDirty"
          type="button"
          class="inline-flex min-h-[44px] items-center gap-1 px-2 text-xs font-bold text-dark/70 hover:text-red-700"
          @click="restablecer"
        >
          <RotateCcw :size="13" aria-hidden="true" /> Volver al original
        </button>
      </div>
    </form>
  </Panel>
</template>
