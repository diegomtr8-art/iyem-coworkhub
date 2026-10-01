import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import CampoTexto from '@/Components/Auth/CampoTexto.vue'

/**
 * Hallazgo de pruebas (Prueba 06): tras confirmar el correo de registro, «los
 * datos quedaron raros» en el inicio de sesión. La captura lo aclara: el
 * navegador autocompletó correo y contraseña y la etiqueta flotante se quedó
 * ENCIMA del texto. El autocompletado no avisa al `v-model` ni quita
 * `:placeholder-shown` hasta que la persona toca la página, así que la
 * etiqueta creía que el campo estaba vacío.
 *
 * jsdom no autocompleta: se comprueba que la etiqueta sube con el estado
 * `:autofill` del navegador (las dos grafías: Chrome/Safari usan la de
 * prefijo) y que el fondo del autocompletado no tapa el diseño.
 */
describe('CampoTexto', () => {
  const etiqueta = () =>
    mount(CampoTexto, { props: { modelValue: '', etiqueta: 'Correo electrónico', type: 'email' } }).find('label')

  it.each([':-webkit-autofill', ':autofill'])('la etiqueta sube cuando el navegador autocompleta (%s)', (estado) => {
    const clases = etiqueta().classes()

    for (const efecto of ['top-3', 'translate-y-0', 'text-xs']) {
      expect(clases, `falta peer-[${estado}]:${efecto}`).toContain(`peer-[${estado}]:${efecto}`)
    }
  })

  it('el autocompletado no pinta su fondo azul encima del campo', () => {
    const css = readFileSync(join(__dirname, '../../resources/css/app.css'), 'utf8')

    expect(css).toMatch(/:-webkit-autofill/)
    expect(css).toMatch(/-webkit-text-fill-color/)
  })
})
