<script setup lang="ts">
/**
 * Una foto del sitio en el módulo «Página Web».
 *
 * La coordinación sube **un** archivo; el servidor lo recorta a la proporción
 * del hueco y genera los tamaños. Aquí se le enseña ese recorte antes de
 * subirlo (mismo centro, misma proporción) y se le avisa si la foto se va a
 * quedar corta: si no, acaba subiendo fotos que se ven cortadas y no entiende
 * por qué.
 *
 * Subir no publica: la foto entra en el formulario de la sección y sale al
 * sitio cuando se guarda, como un texto.
 */
import axios from 'axios'
import { ImagePlus, RotateCcw, Upload, X } from 'lucide-vue-next'
import { computed, onBeforeUnmount, ref } from 'vue'

const props = defineProps<{
  campo: {
    etiqueta: string
    ayuda: string | null
    formato?: string
    formatoNombre?: string
    proporcion?: [number, number]
    anchoMinimo?: number
    decorativa?: boolean
  }
  modelValue: { id: number | null; alt: string } | null
  /** Cómo se ve hoy en el sitio (la guardada o la fija). */
  vista?: { src: string; srcset: string | null; alt: string } | null
  error?: string
  id: string
}>()

const emit = defineEmits<{ 'update:modelValue': [valor: { id: number | null; alt: string }] }>()

const PESO_MAXIMO = 15 * 1024 * 1024

const valor = computed(() => props.modelValue ?? { id: null, alt: '' })
const relacion = computed(() => (props.campo.proporcion ? `${props.campo.proporcion[0]} / ${props.campo.proporcion[1]}` : '4 / 3'))

/** La foto recién subida en esta pantalla, hasta que se guarde la sección. */
const subida = ref<{ id: number; src: string } | null>(null)

/** Archivo elegido y todavía sin subir. */
const elegido = ref<File | null>(null)
const vistaLocal = ref<string | null>(null)
const aviso = ref<string | null>(null)
const subiendo = ref(false)
const entrada = ref<HTMLInputElement | null>(null)

/** Qué se enseña como foto actual: la subida si la hay, si no la del sitio. */
const actual = computed(() => {
  if (subida.value && subida.value.id === valor.value.id) return subida.value.src
  return props.vista?.src ?? null
})

function soltarVistaLocal() {
  if (vistaLocal.value) URL.revokeObjectURL(vistaLocal.value)
  vistaLocal.value = null
}

onBeforeUnmount(soltarVistaLocal)

function elegir(evento: Event) {
  const archivo = (evento.target as HTMLInputElement).files?.[0]
  ;(evento.target as HTMLInputElement).value = ''
  aviso.value = null
  soltarVistaLocal()
  elegido.value = null
  if (!archivo) return

  if (!['image/jpeg', 'image/png', 'image/webp'].includes(archivo.type)) {
    aviso.value = 'Tiene que ser una foto JPG, PNG o WebP.'
    return
  }
  if (archivo.size > PESO_MAXIMO) {
    aviso.value = 'La foto pesa más de 15 MB. Expórtala más ligera e inténtalo de nuevo.'
    return
  }

  elegido.value = archivo
  vistaLocal.value = URL.createObjectURL(archivo)

  // El mismo cálculo que hace el servidor: el ancho útil después de recortar.
  const img = new Image()
  img.onload = () => {
    const [pa, pb] = props.campo.proporcion ?? [4, 3]
    const util = img.naturalWidth * pb > img.naturalHeight * pa
      ? Math.floor((img.naturalHeight * pa) / pb)
      : img.naturalWidth
    const minimo = props.campo.anchoMinimo ?? 0
    if (util < minimo) {
      aviso.value = `Esta foto es demasiado pequeña para este lugar: recortada queda de ${util} px de ancho y hacen falta al menos ${minimo} px. Elige una más grande.`
    }
  }
  img.src = vistaLocal.value
}

const puedeSubir = computed(() => elegido.value !== null && aviso.value === null && !subiendo.value)

async function subir() {
  if (!elegido.value) return
  subiendo.value = true
  aviso.value = null

  const datos = new FormData()
  datos.append('imagen', elegido.value)

  try {
    const { data } = await axios.post(route('pagina-web.imagenes', props.campo.formato), datos)
    subida.value = { id: data.id, src: data.src }
    emit('update:modelValue', { id: data.id, alt: valor.value.alt })
    cancelar()
  } catch (e: any) {
    aviso.value = e?.response?.data?.errors?.imagen?.[0]
      ?? e?.response?.data?.message
      ?? 'No se pudo subir la foto. Revisa la conexión e inténtalo de nuevo.'
  } finally {
    subiendo.value = false
  }
}

function cancelar() {
  elegido.value = null
  soltarVistaLocal()
}

function volverAOriginal() {
  subida.value = null
  emit('update:modelValue', { id: null, alt: valor.value.alt })
}

function cambiarAlt(texto: string) {
  emit('update:modelValue', { id: valor.value.id, alt: texto })
}
</script>

<template>
  <div>
    <p class="font-display text-sm font-bold text-dark">{{ campo.etiqueta }}</p>
    <p class="mt-0.5 text-xs text-dark/70">
      {{ campo.formatoNombre }} · al menos {{ campo.anchoMinimo }} px de ancho.
      <template v-if="campo.ayuda"> {{ campo.ayuda }}</template>
    </p>

    <div class="mt-3 grid gap-4 sm:grid-cols-2">
      <!-- Así se ve hoy -->
      <figure>
        <div class="w-full overflow-hidden border border-dark/15 bg-cream-50" :style="{ aspectRatio: relacion }">
          <img v-if="actual" :src="actual" alt="" class="h-full w-full object-cover" />
        </div>
        <figcaption class="mt-1 text-xs text-dark/70">
          {{ valor.id === null ? 'Foto original' : (subida ? 'Nueva, sin publicar todavía' : 'Foto subida') }}
        </figcaption>
      </figure>

      <!-- La elegida, ya recortada como quedará -->
      <figure v-if="vistaLocal">
        <div class="w-full overflow-hidden border-2 border-dark bg-cream-50" :style="{ aspectRatio: relacion }">
          <img :src="vistaLocal" alt="" class="h-full w-full object-cover" />
        </div>
        <figcaption class="mt-1 text-xs text-dark/70">Así quedará recortada.</figcaption>
      </figure>
    </div>

    <p v-if="aviso" class="mt-2 text-sm font-medium text-red-700" role="alert">{{ aviso }}</p>

    <div class="mt-3 flex flex-wrap items-center gap-2">
      <input
        :id="`${id}-archivo`"
        ref="entrada"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        class="sr-only"
        @change="elegir"
      />
      <template v-if="elegido">
        <button
          type="button"
          :disabled="!puedeSubir"
          class="inline-flex min-h-[44px] items-center gap-1.5 border-2 border-dark bg-white px-3 text-sm font-bold text-dark disabled:opacity-50"
          @click="subir"
        >
          <Upload :size="15" aria-hidden="true" /> {{ subiendo ? 'Subiendo…' : 'Usar esta foto' }}
        </button>
        <button type="button" class="inline-flex min-h-[44px] items-center gap-1 px-2 text-sm text-dark/70 hover:text-dark" @click="cancelar">
          <X :size="15" aria-hidden="true" /> Cancelar
        </button>
      </template>
      <label
        v-else
        :for="`${id}-archivo`"
        class="inline-flex min-h-[44px] cursor-pointer items-center gap-1.5 border border-dark/25 bg-white px-3 text-sm font-bold text-dark hover:border-dark focus-within:ring-2 focus-within:ring-nodo-400"
      >
        <ImagePlus :size="15" aria-hidden="true" /> Cambiar foto
      </label>
      <button
        v-if="valor.id !== null && !elegido"
        type="button"
        class="inline-flex min-h-[44px] items-center gap-1 px-2 text-xs font-bold text-dark/70 hover:text-dark"
        @click="volverAOriginal"
      >
        <RotateCcw :size="13" aria-hidden="true" /> Usar la foto original
      </button>
    </div>

    <!-- Texto alternativo: obligatorio salvo en fotos decorativas -->
    <div v-if="!campo.decorativa" class="mt-4">
      <div class="flex items-baseline justify-between gap-3">
        <label :for="`${id}-alt`" class="font-display text-sm font-bold text-dark">
          Descripción de la foto<span class="text-dark/70" aria-hidden="true"> *</span>
        </label>
        <span class="font-mono text-[0.6875rem]" :class="valor.alt.length > 150 ? 'text-red-700' : 'text-dark/70'" aria-hidden="true">
          {{ valor.alt.length }}/150
        </span>
      </div>
      <input
        :id="`${id}-alt`"
        :value="valor.alt"
        type="text"
        :aria-invalid="Boolean(error)"
        :aria-describedby="`${id}-alt-ayuda`"
        class="mt-2 min-h-[44px] w-full border border-dark/25 bg-white px-3 text-base text-dark focus:border-dark focus:outline-none focus:ring-2 focus:ring-nodo-400"
        @input="cambiarAlt(($event.target as HTMLInputElement).value)"
      />
      <p :id="`${id}-alt-ayuda`" class="mt-1.5 text-xs text-dark/70">
        Qué se ve en la foto, en una frase. Es lo que oye quien usa lector de pantalla.
      </p>
    </div>
    <p v-else class="mt-2 text-xs text-dark/70">Foto decorativa: no necesita descripción.</p>

    <p v-if="error" class="mt-1.5 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
  </div>
</template>
