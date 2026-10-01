import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, ref } from 'vue'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { clicFondo } from '@/composables/clicFondo'

/**
 * Hallazgo de pruebas: «las ventanas se cierran al soltar el clic fuera». Al
 * seleccionar texto en un campo y soltar el botón fuera del modal, el
 * navegador manda el `click` al ancestro común —el fondo— y `@click.self` lo
 * daba por bueno: se cerraba y se perdía lo escrito. Cerrar exige que pulsar
 * y soltar ocurran los dos en el fondo.
 */
function montarModal(cerrar: () => void) {
  return mount(defineComponent({
    directives: { clicFondo },
    setup: () => ({ cerrar, texto: ref('') }),
    template: `
      <div class="fondo" v-clic-fondo="cerrar">
        <div class="caja"><input class="campo" v-model="texto" /></div>
      </div>`,
  }), { attachTo: document.body })
}

const pulsar = (el: Element) => el.dispatchEvent(new Event('pointerdown', { bubbles: true }))
// El navegador manda el click al ancestro común de pulsar y soltar.
const clic = (el: Element) => el.dispatchEvent(new MouseEvent('click', { bubbles: true }))

describe('v-clic-fondo', () => {
  it('pulsar dentro del campo y soltar en el fondo NO cierra', () => {
    const cerrar = vi.fn()
    const w = montarModal(cerrar)

    pulsar(w.find('.campo').element)
    clic(w.find('.fondo').element)

    expect(cerrar).not.toHaveBeenCalled()
  })

  it('pulsar en el fondo y soltar dentro de la caja NO cierra', () => {
    const cerrar = vi.fn()
    const w = montarModal(cerrar)

    pulsar(w.find('.fondo').element)
    clic(w.find('.caja').element)

    expect(cerrar).not.toHaveBeenCalled()
  })

  it('pulsar y soltar los dos en el fondo SÍ cierra', () => {
    const cerrar = vi.fn()
    const w = montarModal(cerrar)

    pulsar(w.find('.fondo').element)
    clic(w.find('.fondo').element)

    expect(cerrar).toHaveBeenCalledTimes(1)
  })

  it('un clic dentro de la caja no cierra', () => {
    const cerrar = vi.fn()
    const w = montarModal(cerrar)

    pulsar(w.find('.caja').element)
    clic(w.find('.caja').element)

    expect(cerrar).not.toHaveBeenCalled()
  })
})

describe('todos los modales', () => {
  it('ningún componente cierra con @click.self (se usa v-clic-fondo)', () => {
    const raiz = join(__dirname, '../../resources/js')
    const encontrados: string[] = []

    const recorrer = (dir: string) => {
      for (const nombre of readdirSync(dir)) {
        const ruta = join(dir, nombre)
        if (statSync(ruta).isDirectory()) recorrer(ruta)
        else if (ruta.endsWith('.vue')) {
          readFileSync(ruta, 'utf8').split('\n').forEach((linea, i) => {
            if (linea.includes('@click.self')) encontrados.push(`${ruta.slice(raiz.length + 1)}:${i + 1}`)
          })
        }
      }
    }
    recorrer(raiz)

    expect(encontrados).toEqual([])
  })
})

describe('en un componente <script setup> compilado', () => {
  it('`() => (abierto = false)` cierra de verdad el modal', async () => {
    const { default: Modal } = await import('./fixtures/ModalConAsignacion.vue')
    const w = mount(Modal, { attachTo: document.body })

    pulsar(w.find('.fondo').element)
    clic(w.find('.fondo').element)
    await w.vm.$nextTick()

    expect(w.find('.fondo').exists()).toBe(false)
  })
})
