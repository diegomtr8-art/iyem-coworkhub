<script setup lang="ts">
/**
 * Un campo del módulo «Página Web», del tipo que diga el catálogo. En las
 * listas se usa a sí mismo para los campos de cada elemento.
 *
 * No guarda estado propio: recibe el valor y emite el nuevo, y la sección
 * decide cuándo publicarlo.
 */
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-vue-next'
import { computed } from 'vue'
import CampoImagen from './CampoImagen.vue'

defineOptions({ name: 'CampoDelSitio' })

export type Campo = {
  nombre: string
  etiqueta: string
  tipo: 'texto' | 'parrafo' | 'email' | 'telefono' | 'url' | 'usuario' | 'youtube' | 'numero' | 'booleano' | 'lista' | 'imagen'
  ayuda: string | null
  maximo: number | null
  requerido: boolean
  // lista
  elemento?: string
  minimo?: number
  multiplo?: number
  campos?: Campo[]
  // imagen
  formato?: string
  formatoNombre?: string
  proporcion?: [number, number]
  anchoMinimo?: number
  decorativa?: boolean
}

const props = defineProps<{
  campo: Campo
  modelValue: any
  /** Errores de toda la sección, con claves como `elementos.2.titulo`. */
  errores: Record<string, string>
  /** Prefijo de este campo en esas claves (vacío arriba, `elementos.2.` dentro de una lista). */
  prefijo?: string
  /** Lo que ve el sitio para este campo (la miniatura, en las fotos). */
  vista?: any
  idBase: string
}>()

const emit = defineEmits<{ 'update:modelValue': [valor: any] }>()

const clave = computed(() => `${props.prefijo ?? ''}${props.campo.nombre}`)
const id = computed(() => `${props.idBase}-${clave.value.replace(/\./g, '-')}`)
const error = computed(() => props.errores[clave.value])

const tiposInput: Record<string, string> = { email: 'email', telefono: 'tel', url: 'url', numero: 'text' }
const modosTeclado: Record<string, string> = { email: 'email', telefono: 'tel', url: 'url', numero: 'decimal' }
const largo = computed(() => String(props.modelValue ?? '').length)

// --- Listas ---------------------------------------------------------------
const elementos = computed<Record<string, any>[]>(() => (Array.isArray(props.modelValue) ? props.modelValue : []))
const fija = computed(() => props.campo.minimo === props.campo.maximo)

function cambiarElemento(i: number, sub: string, valor: any) {
  const copia = elementos.value.map((e) => ({ ...e }))
  copia[i][sub] = valor
  emit('update:modelValue', copia)
}

function mover(i: number, paso: number) {
  const copia = [...elementos.value]
  const [e] = copia.splice(i, 1)
  copia.splice(i + paso, 0, e)
  emit('update:modelValue', copia)
}

function quitar(i: number) {
  emit('update:modelValue', elementos.value.filter((_, n) => n !== i))
}

function anadir() {
  const vacio = Object.fromEntries((props.campo.campos ?? []).map((c) => [c.nombre, c.tipo === 'imagen' ? { id: null, alt: '' } : '']))
  emit('update:modelValue', [...elementos.value, vacio])
}

const cantidad = computed(() => {
  const { minimo, maximo, multiplo } = props.campo
  if (minimo === maximo) return `Siempre ${minimo}.`
  return `Entre ${minimo} y ${maximo}${(multiplo ?? 1) > 1 ? `, de ${multiplo} en ${multiplo}` : ''}.`
})
</script>

<template>
  <!-- Interruptor: una casilla grande, con el texto a la derecha. -->
  <div v-if="campo.tipo === 'booleano'">
    <label :for="id" class="flex min-h-[44px] cursor-pointer items-center gap-3">
      <input
        :id="id"
        type="checkbox"
        :checked="Boolean(modelValue)"
        class="h-5 w-5 shrink-0 border-dark/40 text-dark focus:ring-2 focus:ring-nodo-400"
        @change="emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
      />
      <span class="font-display text-sm font-bold text-dark">{{ campo.etiqueta }}</span>
    </label>
    <p v-if="campo.ayuda" class="mt-1 text-xs text-dark/70">{{ campo.ayuda }}</p>
  </div>

  <!-- Lista de elementos con los mismos campos -->
  <fieldset v-else-if="campo.tipo === 'lista'">
    <legend class="font-display text-sm font-bold text-dark">{{ campo.etiqueta }}</legend>
    <p class="mt-1 text-xs text-dark/70">
      {{ cantidad }} <template v-if="campo.ayuda">{{ campo.ayuda }}</template>
    </p>
    <p v-if="error" class="mt-1.5 text-sm font-medium text-red-700" role="alert">{{ error }}</p>

    <ol class="mt-3 space-y-3">
      <li v-for="(elemento, i) in elementos" :key="i" class="border border-dark/15 bg-cream-50/60">
        <div class="flex items-center justify-between gap-2 border-b border-dark/10 px-3 py-1.5">
          <span class="font-mono text-[0.6875rem] uppercase tracking-[0.08em] text-dark/70">{{ campo.elemento }} {{ i + 1 }}</span>
          <div class="flex items-center">
            <button
              v-if="i > 0" type="button" class="flex h-11 w-11 items-center justify-center text-dark/70 hover:text-dark"
              :aria-label="`Subir ${campo.elemento} ${i + 1}`" @click="mover(i, -1)"
            ><ArrowUp :size="15" aria-hidden="true" /></button>
            <button
              v-if="i < elementos.length - 1" type="button" class="flex h-11 w-11 items-center justify-center text-dark/70 hover:text-dark"
              :aria-label="`Bajar ${campo.elemento} ${i + 1}`" @click="mover(i, 1)"
            ><ArrowDown :size="15" aria-hidden="true" /></button>
            <button
              v-if="!fija && elementos.length > (campo.minimo ?? 0)" type="button"
              class="flex h-11 w-11 items-center justify-center text-dark/70 hover:text-red-700"
              :aria-label="`Quitar ${campo.elemento} ${i + 1}`" @click="quitar(i)"
            ><Trash2 :size="15" aria-hidden="true" /></button>
          </div>
        </div>
        <div class="space-y-4 px-3 py-3">
          <CampoDelSitio
            v-for="sub in campo.campos"
            :key="sub.nombre"
            :campo="sub"
            :model-value="elemento[sub.nombre]"
            :errores="errores"
            :prefijo="`${clave}.${i}.`"
            :vista="vista?.[i]?.[sub.nombre]"
            :id-base="idBase"
            @update:model-value="(v: any) => cambiarElemento(i, sub.nombre, v)"
          />
        </div>
      </li>
    </ol>

    <button
      v-if="!fija && elementos.length < (campo.maximo ?? 0)"
      type="button"
      class="mt-3 inline-flex min-h-[44px] items-center gap-1.5 border border-dashed border-dark/40 px-3 text-sm font-bold text-dark hover:border-dark"
      @click="anadir"
    >
      <Plus :size="15" aria-hidden="true" /> Añadir {{ campo.elemento?.toLowerCase() }}
    </button>
  </fieldset>

  <!-- Foto -->
  <CampoImagen
    v-else-if="campo.tipo === 'imagen'"
    :campo="campo"
    :model-value="modelValue"
    :vista="vista"
    :error="error ?? errores[`${clave}.alt`]"
    :id="id"
    @update:model-value="(v: any) => emit('update:modelValue', v)"
  />

  <!-- Texto en todas sus formas -->
  <div v-else>
    <div class="flex items-baseline justify-between gap-3">
      <label :for="id" class="font-display text-sm font-bold text-dark">
        {{ campo.etiqueta }}<span v-if="campo.requerido" class="text-dark/70" aria-hidden="true"> *</span>
      </label>
      <span
        v-if="campo.maximo"
        class="font-mono text-[0.6875rem]"
        :class="largo > campo.maximo ? 'text-red-700' : 'text-dark/70'"
        aria-hidden="true"
      >{{ largo }}/{{ campo.maximo }}</span>
    </div>

    <textarea
      v-if="campo.tipo === 'parrafo'"
      :id="id"
      :value="modelValue ?? ''"
      rows="3"
      :required="campo.requerido"
      :aria-invalid="Boolean(error)"
      :aria-describedby="`${id}-ayuda ${id}-error`"
      class="mt-2 w-full resize-y border border-dark/25 bg-white px-3 py-2.5 text-base text-dark focus:border-dark focus:outline-none focus:ring-2 focus:ring-nodo-400"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
    <div v-else class="mt-2 flex items-stretch">
      <span
        v-if="campo.tipo === 'usuario'"
        class="flex items-center border border-r-0 border-dark/25 bg-cream-50 px-3 font-mono text-sm text-dark/70"
        aria-hidden="true"
      >@</span>
      <input
        :id="id"
        :value="modelValue ?? ''"
        :type="tiposInput[campo.tipo] ?? 'text'"
        :inputmode="(modosTeclado[campo.tipo] ?? 'text') as any"
        :autocomplete="campo.tipo === 'email' ? 'email' : 'off'"
        :required="campo.requerido"
        :aria-invalid="Boolean(error)"
        :aria-describedby="`${id}-ayuda ${id}-error`"
        class="min-h-[44px] w-full min-w-0 border border-dark/25 bg-white px-3 text-base text-dark focus:border-dark focus:outline-none focus:ring-2 focus:ring-nodo-400"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      />
    </div>

    <p v-if="campo.ayuda" :id="`${id}-ayuda`" class="mt-1.5 text-xs text-dark/70">{{ campo.ayuda }}</p>
    <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
  </div>
</template>
