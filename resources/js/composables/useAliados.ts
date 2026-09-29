import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

/**
 * Los tres aliados, con nombre y enlace del módulo «Página Web»
 * (comun.aliados) y logo y medidas fijos: los logos están recortados y
 * alineados a mano, y no se cambian desde el panel (docs/CMS-PAGINA-WEB.md).
 *
 * La altura de cada logo depende de dónde se pinta (franja o pie), así que la
 * pone quien llama.
 */
const LOGOS = {
  iyem:     { logo: '/img/nodico/logo-iyem.png',          ancho: 452, alto: 75 },
  herencia: { logo: '/img/nodico/logo-herencia-viva.png', ancho: 418, alto: 63 },
  canieti:  { logo: '/img/nodico/logo-canieti.png',       ancho: 255, alto: 99 },
} as const

type Aliado = keyof typeof LOGOS

export function useAliados(clases: Record<Aliado, string>) {
  const page = usePage()

  return computed(() => {
    const datos = (page.props as any).comun.aliados

    return (Object.keys(LOGOS) as Aliado[]).map((clave) => ({
      ...LOGOS[clave],
      nombre: datos[`${clave}_nombre`] as string,
      href: (datos[`${clave}_url`] as string | null) || null,
      clase: clases[clave],
    }))
  })
}
