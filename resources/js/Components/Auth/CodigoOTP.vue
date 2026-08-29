<script setup lang="ts">
/**
 * F — Código de seis dígitos con avance automático entre casillas.
 *
 * Decisiones que parecen detalles y no lo son:
 *
 * - `autocomplete="one-time-code"` va **solo en la primera casilla**. Es lo que
 *   hace que iOS ofrezca el código del teclado; puesto en las seis, el
 *   autorrelleno se confunde y no ofrece nada.
 * - `inputmode="numeric"` abre el teclado numérico sin usar `type="number"`,
 *   que en móvil añade flechitas y acepta `e`, `+` y `-`.
 * - El pegado se reparte entre las casillas: nadie teclea un código de su
 *   gestor de contraseñas dígito a dígito, lo copia entero.
 * - Retroceso sobre una casilla vacía salta a la anterior, que es lo que la
 *   mano espera.
 */
import { computed, nextTick, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: string
    longitud?: number
    error?: string | null
    etiqueta?: string
    disabled?: boolean
  }>(),
  { longitud: 6, etiqueta: 'Código de verificación', disabled: false },
)

const emit = defineEmits<{ 'update:modelValue': [string]; completo: [string] }>()

const casillas = ref<HTMLInputElement[]>([])

const digitos = computed(() => {
  const limpio = (props.modelValue ?? '').replace(/\D/g, '').slice(0, props.longitud)
  return Array.from({ length: props.longitud }, (_, i) => limpio[i] ?? '')
})

function publicar(valores: string[]) {
  const unido = valores.join('')
  emit('update:modelValue', unido)
  if (unido.length === props.longitud) emit('completo', unido)
}

function alEscribir(indice: number, evento: Event) {
  const campo = evento.target as HTMLInputElement
  const escrito = campo.value.replace(/\D/g, '')

  // Un teclado de móvil puede entregar varios dígitos de golpe (autorrelleno).
  if (escrito.length > 1) {
    repartir(escrito, indice)
    return
  }

  const valores = [...digitos.value]
  valores[indice] = escrito
  publicar(valores)

  campo.value = escrito

  if (escrito && indice < props.longitud - 1) {
    nextTick(() => casillas.value[indice + 1]?.focus())
  }
}

function alPulsar(indice: number, evento: KeyboardEvent) {
  if (evento.key === 'Backspace' && !digitos.value[indice] && indice > 0) {
    evento.preventDefault()
    const valores = [...digitos.value]
    valores[indice - 1] = ''
    publicar(valores)
    nextTick(() => casillas.value[indice - 1]?.focus())
    return
  }

  if (evento.key === 'ArrowLeft' && indice > 0) {
    evento.preventDefault()
    casillas.value[indice - 1]?.focus()
  }

  if (evento.key === 'ArrowRight' && indice < props.longitud - 1) {
    evento.preventDefault()
    casillas.value[indice + 1]?.focus()
  }
}

function alPegar(indice: number, evento: ClipboardEvent) {
  evento.preventDefault()
  const pegado = (evento.clipboardData?.getData('text') ?? '').replace(/\D/g, '')
  if (pegado) repartir(pegado, indice)
}

function repartir(texto: string, desde: number) {
  const valores = [...digitos.value]

  for (let i = 0; i < texto.length && desde + i < props.longitud; i++) {
    valores[desde + i] = texto[i]
  }

  publicar(valores)

  const siguiente = Math.min(desde + texto.length, props.longitud - 1)
  nextTick(() => {
    casillas.value.forEach((c, i) => { if (c) c.value = valores[i] })
    casillas.value[siguiente]?.focus()
  })
}

// Cuando el formulario se limpia desde fuera (código incorrecto), las casillas
// tienen que vaciarse de verdad, no quedarse con el valor pintado.
watch(
  () => props.modelValue,
  (nuevo) => {
    if (nuevo === '') {
      casillas.value.forEach((c) => { if (c) c.value = '' })
      nextTick(() => casillas.value[0]?.focus())
    }
  },
)
</script>

<template>
  <fieldset :disabled="disabled">
    <legend class="font-body text-cuerpo text-dark">{{ etiqueta }}</legend>

    <div class="mt-3 flex gap-2 sm:gap-3">
      <input
        v-for="(digito, indice) in digitos"
        :key="indice"
        :ref="(el) => { if (el) casillas[indice] = el as HTMLInputElement }"
        :value="digito"
        type="text"
        inputmode="numeric"
        maxlength="1"
        :autocomplete="indice === 0 ? 'one-time-code' : 'off'"
        :aria-label="`Dígito ${indice + 1} de ${longitud}`"
        :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="error ? 'error-codigo' : undefined"
        class="h-16 w-full min-w-0 rounded-xl border bg-cream-50 text-center font-display text-2xl
               font-bold text-dark transition-colors duration-200 focus:bg-white focus:ring-0
               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
        :class="error ? 'border-red-600 focus:border-red-600' : 'border-dark/15 focus:border-nodo-500'"
        @input="alEscribir(indice, $event)"
        @keydown="alPulsar(indice, $event)"
        @paste="alPegar(indice, $event)"
        @focus="($event.target as HTMLInputElement).select()"
      />
    </div>

    <p v-if="error" id="error-codigo" class="mt-2 font-body text-sm text-red-600">
      {{ error }}
    </p>
  </fieldset>
</template>
