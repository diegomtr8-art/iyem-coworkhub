import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue'

/**
 * Parallax suave: el elemento se desplaza más lento que el resto de la sección.
 *
 * Recorrido total de ~50 px, que es lo que pide 4.6: sutil, no un efecto.
 * Se apaga por completo con `prefers-reduced-motion: reduce`.
 */
export function useParallax(elemento: Ref<HTMLElement | null>, recorrido = 50) {
  const desplazamiento = ref(0)

  let activo = false
  let pendiente = false

  function calcular() {
    pendiente = false
    const el = elemento.value
    if (!el) return

    const caja = el.getBoundingClientRect()
    const alto = window.innerHeight

    // Fuera de pantalla no hay nada que calcular.
    if (caja.bottom < 0 || caja.top > alto) return

    // 0 cuando el elemento entra por abajo, 1 cuando sale por arriba.
    const avance = 1 - (caja.top + caja.height / 2) / (alto + caja.height / 2)
    desplazamiento.value = (avance - 0.5) * recorrido
  }

  function alDesplazar() {
    if (pendiente) return
    pendiente = true
    requestAnimationFrame(calcular)
  }

  onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

    activo = true
    calcular()
    window.addEventListener('scroll', alDesplazar, { passive: true })
    window.addEventListener('resize', alDesplazar, { passive: true })
  })

  onBeforeUnmount(() => {
    if (!activo) return
    window.removeEventListener('scroll', alDesplazar)
    window.removeEventListener('resize', alDesplazar)
  })

  return { desplazamiento }
}
