/**
 * FE-11 — SiteHeader y HeroVideo escribían los dos `document.body.style.overflow`.
 * Si se abría el modal del video y encima el menú, el primero en cerrarse
 * destrababa el scroll del otro. Un contador compartido lo resuelve: el scroll
 * solo se libera cuando no queda ningún bloqueo activo.
 */
let bloqueos = 0
let overflowPrevio: string | null = null

export function useBloqueoScroll() {
  let propio = false

  function bloquear() {
    if (propio) return
    propio = true

    if (bloqueos === 0) {
      overflowPrevio = document.body.style.overflow
      document.body.style.overflow = 'hidden'
    }
    bloqueos++
  }

  function liberar() {
    if (!propio) return
    propio = false

    bloqueos = Math.max(0, bloqueos - 1)
    if (bloqueos === 0) {
      document.body.style.overflow = overflowPrevio ?? ''
      overflowPrevio = null
    }
  }

  return { bloquear, liberar }
}

/**
 * Marca como `inert` todo lo que no sea el diálogo, para que ni el tabulador ni
 * el lector de pantalla salgan de él (A11Y-01).
 */
export function useInerteFuera() {
  let marcados: HTMLElement[] = []

  function activar(dialogo: HTMLElement | null) {
    if (!dialogo) return

    marcados = Array.from(document.body.children).filter(
      (hijo): hijo is HTMLElement =>
        hijo instanceof HTMLElement && !hijo.contains(dialogo) && hijo !== dialogo,
    )
    marcados.forEach((el) => el.setAttribute('inert', ''))
  }

  function desactivar() {
    marcados.forEach((el) => el.removeAttribute('inert'))
    marcados = []
  }

  return { activar, desactivar }
}

/** Devuelve los elementos enfocables dentro de un contenedor. */
export function enfocables(raiz: HTMLElement): HTMLElement[] {
  return Array.from(
    raiz.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), iframe, [tabindex]:not([tabindex="-1"])',
    ),
  ).filter((el) => el.offsetParent !== null || el.tagName === 'IFRAME')
}

/** Ciclo de tabulación cerrado dentro del diálogo. */
export function atraparFoco(e: KeyboardEvent, contenedor: HTMLElement | null) {
  if (e.key !== 'Tab' || !contenedor) return

  const lista = enfocables(contenedor)
  if (!lista.length) return

  const primero = lista[0]
  const ultimo = lista[lista.length - 1]

  if (e.shiftKey && document.activeElement === primero) {
    e.preventDefault()
    ultimo.focus()
  } else if (!e.shiftKey && document.activeElement === ultimo) {
    e.preventDefault()
    primero.focus()
  }
}
