import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { join } from 'node:path'

/**
 * Hallazgo de pruebas: «ajustar horas a un miembro: el modal se queda abierto
 * y no guarda» (otro probador decía que funcionaba). Causa: el motivo llevaba
 * `required` y el navegador frenaba el envío con un globo de dos segundos, que
 * en iPhone ni se muestra; quien sí escribía el motivo veía que funcionaba.
 * El arreglo es dejar que el envío llegue al servidor y enseñar su error en el
 * modal, sin tocar la lógica (la prueba del mensaje está en
 * PanelOperativoTest::test_un_ajuste_sin_motivo_se_rechaza).
 *
 * Show.vue es una página entera: se comprueba el marcado del formulario.
 */
const fuente = readFileSync(join(__dirname, '../../resources/js/Pages/Miembros/Show.vue'), 'utf8')
const formulario = fuente.slice(fuente.indexOf(`v-if="dialogo === 'ajuste'"`), fuente.indexOf('</form>', fuente.indexOf(`v-if="dialogo === 'ajuste'"`)))

describe('modal de ajuste de horas', () => {
  it('el navegador no frena el envío: el formulario es novalidate y el motivo no es required', () => {
    expect(formulario).toMatch(/^v-if="dialogo === 'ajuste'"[^>]*\bnovalidate\b/)
    expect(formulario).not.toMatch(/id="aj-motivo"[^>]*\brequired\b/)
  })

  it('enseña el error del motivo, el de las horas y los que no tienen campo', () => {
    expect(formulario).toContain('ajuste.errors.motivo')
    expect(formulario).toContain('ajuste.errors.horas')
    expect(formulario).toMatch(/v-for="\(msg, campo\) in ajuste\.errors"/)
  })
})
