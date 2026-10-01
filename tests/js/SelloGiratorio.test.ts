import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import SelloGiratorio from '@/Components/Public/SelloGiratorio.vue'

/**
 * Hallazgo de pruebas (dos probadores por separado): «el texto del sello $0
 * no se lee». El sello va montado sobre la foto del day-pass, y el texto
 * circular era oscuro sobre transparente: su contraste dependía de la foto.
 * Además, el texto no cabía en la circunferencia y salía diminuto.
 */
const TOKENS: Record<string, string> = {
  'fill-dark': '#2E2D2C',
  'fill-tinta': '#1A1918',
  'fill-cream': '#F4F1EA',
  'fill-cream-50': '#FAF8F3',
  'fill-nodo-400': '#FFE124',
  'fill-white': '#FFFFFF',
}

function luminancia(hex: string): number {
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
    .map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4))
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

function contraste(a: string, b: string): number {
  const [l1, l2] = [luminancia(a), luminancia(b)].sort((x, y) => y - x)
  return (l1 + 0.05) / (l2 + 0.05)
}

const colorDe = (el: Element) => {
  const clase = [...el.classList].find((c) => c in TOKENS)
  return clase ? TOKENS[clase] : null
}

beforeEach(() => {
  window.matchMedia = vi.fn().mockReturnValue({ matches: true }) as any
})

const TEXTOS = ['DAY-PASS GRATUITO · INTERIOR DEL ESTADO · ', 'X'.repeat(60)]

describe('SelloGiratorio', () => {
  it.each(TEXTOS)('el texto circular va sobre un fondo propio y opaco, con contraste AA (%s)', (texto) => {
    const w = mount(SelloGiratorio, { props: { texto } })
    const fondo = w.find('svg circle')
    const letra = w.find('svg text')

    expect(fondo.exists(), 'sin fondo propio, el texto depende de la foto de abajo').toBe(true)
    expect(Number(fondo.attributes('r'))).toBeGreaterThanOrEqual(90)

    const cFondo = colorDe(fondo.element)
    const cLetra = colorDe(letra.element)
    expect(cFondo, 'el fondo debe usar un color del sistema').not.toBeNull()
    expect(cLetra).not.toBeNull()
    expect(contraste(cFondo!, cLetra!)).toBeGreaterThanOrEqual(4.5)
  })

  it.each(TEXTOS)('el texto cabe en la circunferencia y no baja de 10 px reales (%s)', (texto) => {
    const w = mount(SelloGiratorio, { props: { texto } })
    const letra = w.find('svg text')
    const camino = w.find('svg path')

    const radio = Number(/a ([\d.]+),/.exec(camino.attributes('d')!)![1])
    const circunferencia = 2 * Math.PI * radio
    const tamano = parseFloat(letra.attributes('font-size')!)

    // Cabe: se ajusta a la circunferencia exacta.
    expect(Number(w.find('svg textPath').attributes('textLength'))).toBeCloseTo(circunferencia, 0)
    // Una letra monoespaciada mide ~0.6 em: el texto natural no debe pasarse.
    expect(texto.length * tamano * 0.6).toBeLessThanOrEqual(circunferencia)

    // Tamaño real en el sello más chico (móvil): clase h-NN de Tailwind (NN × 4 px).
    const minimo = Number(/\bh-(\d+)\b/.exec(w.find('div').attributes('class')!)![1]) * 4
    expect(tamano * (minimo / 200)).toBeGreaterThanOrEqual(10)
  })
})
