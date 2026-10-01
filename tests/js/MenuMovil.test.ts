import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { h } from 'vue'

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({ url: '/', props: { auth: { user: null }, canLogin: true, canRegister: true } }),
  router: { on: () => () => {}, post: vi.fn() },
  Link: { props: ['href'], setup: (p: any, { slots, attrs }: any) => () => h('a', { href: p.href, ...attrs }, slots.default?.()) },
}))

import SiteHeader from '@/Components/Public/SiteHeader.vue'

/**
 * Hallazgo de pruebas (Prueba 05, celular): «un rectángulo raro queda flotando
 * sobre el texto de inicio y desaparece si tocas otro lado». La captura lo
 * aclara: es el contorno de foco sobre «Inicio» del menú móvil. Al abrir el
 * menú se movía el foco al primer enlace, y Safari en iOS pinta ese foco
 * aunque la persona haya tocado con el dedo.
 *
 * Con teclado el foco sí tiene que ir al primer enlace; con el dedo o el
 * ratón va al panel, que no pinta contorno.
 */
beforeEach(() => {
  ;(globalThis as any).route = (nombre: string) => `/${nombre === 'home' ? '' : nombre}`
  window.matchMedia = vi.fn().mockReturnValue({ matches: true, addEventListener() {}, removeEventListener() {} }) as any
  document.body.innerHTML = ''
})

const abrirCon = async (detalle: number) => {
  const w = mount(SiteHeader, { attachTo: document.body, global: { mocks: { route: (globalThis as any).route } } })
  const boton = w.find('[aria-controls="menu-movil"]')
  // `detail` es el número de clics: 0 cuando el «clic» lo generó el teclado.
  boton.element.dispatchEvent(new MouseEvent('click', { bubbles: true, detail: detalle }))
  await flushPromises()
  await flushPromises()
  return w
}

describe('menú móvil', () => {
  it('abierto con el dedo o el ratón, el foco NO cae en «Inicio»', async () => {
    await abrirCon(1)
    const activo = document.activeElement as HTMLElement

    expect(activo.tagName, 'el foco quedó en un enlace del menú').not.toBe('A')
    expect(activo.id).toBe('menu-movil')
  })

  it('abierto con el teclado, el foco va al primer enlace', async () => {
    await abrirCon(0)

    expect((document.activeElement as HTMLElement).textContent).toContain('Inicio')
  })

  it('el panel que recibe el foco no pinta contorno', async () => {
    await abrirCon(1)
    const panel = document.getElementById('menu-movil')!

    expect(panel.getAttribute('tabindex')).toBe('-1')
    expect(panel.className).toMatch(/focus-visible:outline-none/)
    expect(panel.className).toMatch(/focus-visible:shadow-none/)
  })
})
