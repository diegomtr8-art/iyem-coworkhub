<script setup lang="ts">
/**
 * F — Campo de formulario de las pantallas de acceso.
 *
 * Reproduce el patrón del formulario «Hablemos» de la portada, que ya estaba
 * bien resuelto: etiqueta flotante, fondo `cream-50` que aclara al enfocar y
 * borde amarillo en foco. Encapsularlo evita que once pantallas lo copien y
 * se desincronicen a la tercera.
 *
 * Detalles que no son opcionales:
 * - `placeholder=" "` (un espacio) es lo que hace funcionar la etiqueta
 *   flotante con `:not(:placeholder-shown)`. Sin él, la etiqueta nunca sube.
 * - `min-h-[60px]` cubre de sobra el área táctil de 44 px.
 * - El tamaño de letra lo fuerza `app.css` a 16 px mínimo: por debajo, iOS hace
 *   zoom al enfocar y descoloca la pantalla.
 * - El error va **debajo de su propio campo** y enlazado con `aria-describedby`,
 *   nunca en un bloque genérico arriba.
 */
import { Eye, EyeOff } from 'lucide-vue-next'
import { computed, ref, useId } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: string
    etiqueta: string
    type?: string
    autocomplete?: string
    inputmode?: 'text' | 'email' | 'tel' | 'numeric' | 'none'
    error?: string | null
    requerido?: boolean
    ayuda?: string | null
    autofocus?: boolean
    disabled?: boolean
  }>(),
  { type: 'text', requerido: false, autofocus: false, disabled: false },
)

const emit = defineEmits<{ 'update:modelValue': [string] }>()

const id = useId()
const idError = `${id}-error`
const idAyuda = `${id}-ayuda`

const verContrasena = ref(false)

const esContrasena = computed(() => props.type === 'password')
const tipoReal = computed(() =>
  esContrasena.value && verContrasena.value ? 'text' : props.type,
)

const descritoPor = computed(() => {
  const partes: string[] = []
  if (props.error) partes.push(idError)
  if (props.ayuda) partes.push(idAyuda)
  return partes.length ? partes.join(' ') : undefined
})
</script>

<template>
  <div>
    <div class="relative">
      <input
        :id="id"
        :value="modelValue"
        :type="tipoReal"
        :autocomplete="autocomplete"
        :inputmode="inputmode"
        :required="requerido"
        :disabled="disabled"
        :autofocus="autofocus"
        :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="descritoPor"
        placeholder=" "
        class="peer min-h-[60px] w-full rounded-xl border bg-cream-50 px-4 pb-2.5 pt-7 font-body text-dark
               transition-colors duration-200 focus:bg-white focus:ring-0
               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark
               disabled:cursor-not-allowed disabled:opacity-60"
        :class="[
          error ? 'border-red-600 focus:border-red-600' : 'border-dark/15 focus:border-nodo-500',
          esContrasena ? 'pr-14' : '',
        ]"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      />

      <label
        :for="id"
        class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-body text-dark/70
               transition-all duration-200 ease-salida
               peer-focus:top-3 peer-focus:translate-y-0 peer-focus:text-xs
               peer-[:not(:placeholder-shown)]:top-3
               peer-[:not(:placeholder-shown)]:translate-y-0
               peer-[:not(:placeholder-shown)]:text-xs"
      >
        {{ etiqueta }}<span v-if="requerido" aria-hidden="true"> *</span>
      </label>

      <!--
        Mostrar la contraseña es un botón con etiqueta de verdad, no un icono
        suelto: un lector de pantalla tiene que poder anunciarlo, y el área
        táctil llega a 44 px aunque el icono mida 18.
      -->
      <button
        v-if="esContrasena"
        type="button"
        class="absolute right-1 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center
               rounded-lg text-dark/70 transition hover:text-dark focus-visible:outline-2
               focus-visible:outline-offset-2 focus-visible:outline-nodo-500"
        :aria-label="verContrasena ? 'Ocultar la contraseña' : 'Mostrar la contraseña'"
        :aria-pressed="verContrasena"
        @click="verContrasena = !verContrasena"
      >
        <EyeOff v-if="verContrasena" class="h-5 w-5" aria-hidden="true" />
        <Eye v-else class="h-5 w-5" aria-hidden="true" />
      </button>
    </div>

    <slot name="bajo-campo" />

    <p v-if="ayuda && !error" :id="idAyuda" class="mt-1.5 font-body text-sm text-dark/70">
      {{ ayuda }}
    </p>

    <p v-if="error" :id="idError" class="mt-1.5 font-body text-sm text-red-600">
      {{ error }}
    </p>
  </div>
</template>
