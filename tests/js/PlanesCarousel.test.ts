import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: { auth: { user: null } } }) }))

import PlanesCarousel from '@/Components/Public/PlanesCarousel.vue'

/**
 * Hallazgo de pruebas: «el carrusel de membresías necesita dos clics».
 *
 * En escritorio caben 3 de las 4 tarjetas, así que la primera y la última no
 * pueden quedar centradas: la pista llega a su tope antes. Los botones se
 * apagaban según el índice, no según si la pista todavía podía moverse, y
 * había pulsaciones que no desplazaban nada.
 *
 * jsdom no calcula diseño: se fijan las medidas de una pista de 1000 px con
 * tarjetas de 300 px y 20 px de separación (tope del scroll: 260 px).
 */
const ANCHO_PISTA = 1000
const ANCHO_TARJETA = 300
const SEPARACION = 20

const planes = ['Day-Pass', 'Nódico Flex', 'Nodo Pro', 'Nodo Match'].map((nombre, i) => ({
  id: i + 1, nombre, precio: 100 * (i + 1), personas: 1, beneficios: [], destacado: false,
}))

function montar() {
  const w = mount(PlanesCarousel, { props: { planes: planes as any }, attachTo: document.body })
  const pista = w.find('[role="group"][tabindex="0"]').element as HTMLElement
  const tarjetas = w.findAll('[aria-roledescription="diapositiva"]').map((t) => t.element as HTMLElement)

  const tope = tarjetas.length * ANCHO_TARJETA + (tarjetas.length - 1) * SEPARACION - ANCHO_PISTA
  let scroll = 0
  Object.defineProperty(pista, 'clientWidth', { value: ANCHO_PISTA, configurable: true })
  Object.defineProperty(pista, 'scrollWidth', { value: ANCHO_PISTA + tope, configurable: true })
  Object.defineProperty(pista, 'scrollLeft', {
    configurable: true,
    get: () => scroll,
    set: (v: number) => { scroll = Math.min(Math.max(0, v), tope) },
  })
  // Como el navegador: solo hay evento de scroll si la posición cambia.
  pista.scrollTo = ((opciones: ScrollToOptions) => {
    const antes = scroll
    pista.scrollLeft = opciones.left ?? 0
    if (scroll !== antes) pista.dispatchEvent(new Event('scroll'))
  }) as any
  tarjetas.forEach((t, i) => {
    Object.defineProperty(t, 'offsetLeft', { value: i * (ANCHO_TARJETA + SEPARACION), configurable: true })
    Object.defineProperty(t, 'clientWidth', { value: ANCHO_TARJETA, configurable: true })
  })
  pista.dispatchEvent(new Event('scroll'))

  return {
    w, tope,
    scroll: () => scroll,
    atras: () => w.find('[aria-label="Membresía anterior"]'),
    adelante: () => w.find('[aria-label="Membresía siguiente"]'),
  }
}

beforeEach(() => {
  window.matchMedia = vi.fn().mockReturnValue({ matches: true }) as any
  ;(globalThis as any).route = () => '/register'
})

describe('PlanesCarousel', () => {
  it('cada pulsación de «siguiente» mueve la pista, y se apaga justo al llegar al final', async () => {
    const c = montar()
    await flushPromises()

    let pulsaciones = 0
    while (c.adelante().attributes('disabled') === undefined && pulsaciones < 10) {
      const antes = c.scroll()
      await c.adelante().trigger('click')
      await flushPromises()
      pulsaciones++
      expect(c.scroll(), `la pulsación ${pulsaciones} no movió nada`).toBeGreaterThan(antes)
    }

    expect(c.scroll()).toBe(c.tope)
    expect(c.adelante().attributes('disabled')).toBeDefined()
  })

  it('«anterior» empieza apagado y, de vuelta, cada pulsación mueve hasta el inicio', async () => {
    const c = montar()
    await flushPromises()
    expect(c.atras().attributes('disabled'), 'al inicio no hay a dónde volver').toBeDefined()

    while (c.adelante().attributes('disabled') === undefined) {
      await c.adelante().trigger('click')
      await flushPromises()
    }

    let pulsaciones = 0
    while (c.atras().attributes('disabled') === undefined && pulsaciones < 10) {
      const antes = c.scroll()
      await c.atras().trigger('click')
      await flushPromises()
      pulsaciones++
      expect(c.scroll(), `la pulsación ${pulsaciones} no movió nada`).toBeLessThan(antes)
    }

    expect(c.scroll()).toBe(0)
  })

  it('si la pista se desplaza a mano hasta el tope, «siguiente» se apaga aunque la última no esté centrada', async () => {
    const c = montar()
    await flushPromises()

    const pista = c.w.find('[role="group"][tabindex="0"]').element as HTMLElement
    pista.scrollTo({ left: c.tope })
    await flushPromises()

    expect(c.adelante().attributes('disabled')).toBeDefined()
    expect(c.atras().attributes('disabled')).toBeUndefined()
  })
})
