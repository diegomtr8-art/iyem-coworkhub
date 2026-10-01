import type { Directive } from 'vue'

type Accion = () => void

interface ConFondo extends HTMLElement {
  _clicFondo?: {
    accion: Accion | undefined
    alPresionar: (e: Event) => void
    alHacerClic: (e: Event) => void
  }
}

/**
 * `v-clic-fondo="cerrar"`: cierra un modal al hacer clic en su fondo, **solo
 * si pulsar y soltar ocurren los dos en el fondo**.
 *
 * Sustituye a `@click.self`. Con aquel, seleccionar texto en un campo y soltar
 * el botón fuera del modal lo cerraba y se perdía lo escrito: el navegador
 * manda el `click` al ancestro común de donde se pulsó y donde se soltó, que
 * es el fondo (hallazgo de las pruebas de servicio social, 29-sep-2026).
 *
 * `pointerdown` cubre ratón, dedo y lápiz.
 */
export const clicFondo: Directive<ConFondo, Accion | undefined> = {
  mounted(el, binding) {
    let empezoEnElFondo = false

    const alPresionar = (e: Event) => {
      empezoEnElFondo = e.target === el
    }

    const alHacerClic = (e: Event) => {
      const cerrar = empezoEnElFondo && e.target === el
      empezoEnElFondo = false
      if (cerrar) el._clicFondo?.accion?.()
    }

    el._clicFondo = { accion: binding.value, alPresionar, alHacerClic }
    el.addEventListener('pointerdown', alPresionar)
    el.addEventListener('click', alHacerClic)
  },

  // El valor suele ser una función nueva en cada render: se guarda la última.
  updated(el, binding) {
    if (el._clicFondo) el._clicFondo.accion = binding.value
  },

  unmounted(el) {
    if (!el._clicFondo) return
    el.removeEventListener('pointerdown', el._clicFondo.alPresionar)
    el.removeEventListener('click', el._clicFondo.alHacerClic)
    delete el._clicFondo
  },
}
