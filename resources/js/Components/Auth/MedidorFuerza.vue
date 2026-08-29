<script setup lang="ts">
/**
 * B — Medidor de fuerza de contraseña.
 *
 * Evalúa de verdad, no cuenta caracteres: usa zxcvbn, que reconoce palabras
 * comunes, nombres, fechas, secuencias de teclado y sustituciones tipo `p4ssw0rd`.
 * Un contador de caracteres daría por buenísima `Nodico2026!` cuando es de las
 * primeras que prueba cualquiera.
 *
 * El motor pesa varios cientos de kilobytes por los diccionarios, así que se
 * carga con `import()` dinámico **al escribir el primer carácter**: quien solo
 * pasa por la pantalla de acceso no lo descarga nunca.
 *
 * Nada de esto es una comprobación de seguridad: el servidor valida por su
 * cuenta con `Password::defaults()`. Esto es ayuda para elegir mejor.
 */
import { computed, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    contrasena: string
    /** Nombre y correo: zxcvbn penaliza la contraseña que los contiene. */
    datosPersonales?: string[]
  }>(),
  { datosPersonales: () => [] },
)

type Resultado = { puntaje: number; aviso: string | null; sugerencias: string[] }

const resultado = ref<Resultado | null>(null)
const cargandoMotor = ref(false)

let comprobar: ((clave: string, extra: string[]) => Resultado) | null = null

async function cargarMotor() {
  if (comprobar || cargandoMotor.value) return

  cargandoMotor.value = true

  try {
    const [{ ZxcvbnFactory }, comun, espanol] = await Promise.all([
      import('@zxcvbn-ts/core'),
      import('@zxcvbn-ts/language-common'),
      import('@zxcvbn-ts/language-es-es'),
    ])

    const motor = new ZxcvbnFactory({
      translations: espanol.translations,
      graphs: comun.adjacencyGraphs,
      dictionary: { ...comun.dictionary, ...espanol.dictionary },
    })

    comprobar = (clave, extra) => {
      const r = motor.check(clave, extra)

      return {
        puntaje: r.score,
        aviso: r.feedback.warning || null,
        sugerencias: r.feedback.suggestions ?? [],
      }
    }
  } catch {
    // Si el trozo no carga —red caída, bloqueador— el formulario sigue siendo
    // perfectamente usable: solo se queda sin medidor.
    comprobar = null
  } finally {
    cargandoMotor.value = false
  }
}

watch(
  () => props.contrasena,
  async (clave) => {
    if (!clave) {
      resultado.value = null
      return
    }

    await cargarMotor()

    resultado.value = comprobar ? comprobar(clave, props.datosPersonales) : null
  },
  { immediate: true },
)

// Requisitos duros, los mismos que valida el servidor. Se muestran siempre
// porque son los que impiden enviar el formulario; el puntaje es orientación.
const requisitos = computed(() => [
  { texto: 'Al menos 10 caracteres', cumple: props.contrasena.length >= 10 },
  { texto: 'Alguna letra', cumple: /\p{L}/u.test(props.contrasena) },
  { texto: 'Algún número', cumple: /\d/.test(props.contrasena) },
])

const cumpleTodo = computed(() => requisitos.value.every((r) => r.cumple))

const nivel = computed(() => {
  if (!props.contrasena) return null
  if (!resultado.value) return null

  return [
    { etiqueta: 'Muy débil', barras: 1, color: 'bg-red-500', texto: 'text-red-700' },
    { etiqueta: 'Débil', barras: 2, color: 'bg-orange-500', texto: 'text-orange-700' },
    { etiqueta: 'Aceptable', barras: 3, color: 'bg-amber-500', texto: 'text-amber-700' },
    { etiqueta: 'Buena', barras: 4, color: 'bg-lime-600', texto: 'text-lime-700' },
    { etiqueta: 'Excelente', barras: 5, color: 'bg-emerald-600', texto: 'text-emerald-700' },
  ][resultado.value.puntaje]
})
</script>

<template>
  <div v-if="contrasena" class="mt-3">
    <!-- Barras -->
    <div class="flex gap-1" aria-hidden="true">
      <span
        v-for="i in 5"
        :key="i"
        class="h-1 flex-1 rounded-full transition-colors duration-200"
        :class="nivel && i <= nivel.barras ? nivel.color : 'bg-dark/15'"
      />
    </div>

    <!--
      `aria-live="polite"` y no `assertive`: quien usa lector de pantalla está
      escribiendo, y no se le debe cortar cada tecla.
    -->
    <p class="sr-only" aria-live="polite">
      Fuerza de la contraseña: {{ nivel?.etiqueta ?? 'calculando' }}.
    </p>

    <p v-if="nivel" class="mt-2 font-body text-sm font-semibold" :class="nivel.texto">
      {{ nivel.etiqueta }}
    </p>
    <p v-else-if="cargandoMotor" class="mt-2 font-body text-sm text-dark/70">
      Evaluando…
    </p>

    <!-- Qué falta para poder enviar -->
    <ul v-if="!cumpleTodo" class="mt-2 space-y-1">
      <li
        v-for="requisito in requisitos"
        :key="requisito.texto"
        class="flex items-center gap-2 font-body text-sm"
        :class="requisito.cumple ? 'text-dark/70' : 'text-dark'"
      >
        <span aria-hidden="true" class="w-4 text-center">{{ requisito.cumple ? '✓' : '·' }}</span>
        <span>{{ requisito.texto }}</span>
        <span class="sr-only">{{ requisito.cumple ? 'cumplido' : 'pendiente' }}</span>
      </li>
    </ul>

    <!-- Por qué es débil, en palabras -->
    <p v-if="resultado?.aviso" class="mt-2 font-body text-sm text-dark/70">
      {{ resultado.aviso }}
    </p>
    <p
      v-else-if="resultado?.sugerencias.length && (nivel?.barras ?? 0) < 4"
      class="mt-2 font-body text-sm text-dark/70"
    >
      {{ resultado.sugerencias[0] }}
    </p>
  </div>
</template>
