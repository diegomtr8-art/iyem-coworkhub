/**
 * Sistema visual de Nódico para el teléfono.
 *
 * La marca traducida, no copiada de la web: fondo oscuro como base (se ve
 * premium, cansa menos de noche y hace que el amarillo resalte de verdad), un
 * único acento fuerte y los colores de plan para distinguir membresías y
 * estados. Todo color de la app sale de aquí; ninguna pantalla escribe un hex.
 */

/**
 * La paleta oficial de Nódico, la misma de la web (`tailwind.config.js`). Ningún
 * color de marca se inventa aquí: si cambia allá, cambia aquí.
 */
export const marca = {
  amarillo: '#FFE124', // nodo-400
  tinta: '#1A1918', // el negro de la marca
  dark: '#2E2D2C',
  darkClaro: '#3D3C3A',
  crema: '#F4F1EA',
  cremaOscura: '#E8E1D1',
  coral: '#EF7E88', // plan Day-Pass
  lima: '#D6E265', // plan Pro; solo sobre oscuro
  morado: '#864B95', // plan Match; como relleno, nunca como texto sobre negro
} as const;

export type Paleta = {
  fondo: string;
  superficie: string;
  superficieAlta: string;
  borde: string;
  texto: string;
  textoSuave: string;
  textoTenue: string;
  /** Relleno de marca: botón principal, selección, insignias. */
  acento: string;
  /** El acento cuando es texto o icono sobre la superficie (legible en claro y oscuro). */
  acentoTexto: string;
  /** El acento en gráficas (anillos, barras): al menos 3:1 contra la superficie. */
  acentoGrafico: string;
  sobreAcento: string;
  bien: string;
  atencion: string;
  problema: string;
  pista: string;
  esqueleto: string;
  coral: string;
  lima: string;
  morado: string;
};

/**
 * La app es negra, siempre (decisión de Diego, 23/09/2026): fondo tinta,
 * superficies en los grises «dark» de la marca y texto en crema.
 */
export const oscuro: Paleta = {
  fondo: marca.tinta,
  superficie: marca.dark,
  superficieAlta: marca.darkClaro,
  borde: '#46443F',
  texto: marca.crema,
  // Crema atenuada: mantiene el tono cálido de la marca con contraste AA
  // (≥ 4.5:1) sobre tinta y sobre dark.
  textoSuave: marca.cremaOscura,
  textoTenue: '#B3ADA2',
  acento: marca.amarillo,
  acentoTexto: marca.amarillo,
  acentoGrafico: marca.amarillo,
  sobreAcento: marca.tinta,
  bien: marca.lima,
  atencion: marca.amarillo,
  problema: marca.coral,
  pista: '#45433F',
  esqueleto: '#35342F',
  coral: marca.coral,
  lima: marca.lima,
  morado: marca.morado,
};

/** Espaciado generoso: el error más común al pasar una web a móvil es apretarlo todo. */
export const espacio = {
  xs: 4,
  s: 8,
  m: 12,
  l: 16,
  xl: 24,
  xxl: 32,
  xxxl: 48,
} as const;

export const radio = {
  s: 10,
  m: 14,
  l: 20,
  xl: 32,
  total: 999,
} as const;

/** Carmen Sans para números y títulos; GT Eesti para texto. */
export const fuentes = {
  titulo: 'CarmenSans-Bold',
  tituloFuerte: 'CarmenSans-ExtraBold',
  numero: 'CarmenSans-Heavy',
  medio: 'CarmenSans-Medium',
  semi: 'CarmenSans-SemiBold',
  texto: 'GTEesti-Regular',
  textoLigero: 'GTEesti-Light',
} as const;

export const archivosDeFuentes = {
  'CarmenSans-Regular': require('../../assets/fonts/CarmenSans-Regular.otf'),
  'CarmenSans-Medium': require('../../assets/fonts/CarmenSans-Medium.otf'),
  'CarmenSans-SemiBold': require('../../assets/fonts/CarmenSans-SemiBold.otf'),
  'CarmenSans-Bold': require('../../assets/fonts/CarmenSans-Bold.otf'),
  'CarmenSans-ExtraBold': require('../../assets/fonts/CarmenSans-ExtraBold.otf'),
  'CarmenSans-Heavy': require('../../assets/fonts/CarmenSans-Heavy.otf'),
  'GTEesti-Regular': require('../../assets/fonts/GTEesti-Regular.ttf'),
  'GTEesti-Light': require('../../assets/fonts/GTEesti-Light.ttf'),
};

/**
 * Color de cada bolsa de horas. Se mantiene fijo en toda la app: el anillo de
 * salas en Inicio y la franja de salas en Reservar son del mismo color.
 */
export function colorDeBolsa(p: Paleta, bolsa: string): string {
  switch (bolsa) {
    case 'sala':
      return p.acentoGrafico;
    case 'contenido':
      return p.coral;
    case 'asesoria':
      // El morado de marca no alcanza 3:1 sobre negro; en gráficas va lima.
      return p.lima;
    case 'dias':
      return p.textoSuave;
    default:
      return p.textoSuave;
  }
}

/** Tono de un estado del servidor (`bien`, `atencion`, `problema`, …) a color. */
export function colorDeTono(p: Paleta, tono?: string | null): string {
  switch (tono) {
    case 'bien':
    case 'exito':
    case 'verde':
      return p.bien;
    case 'atencion':
    case 'pendiente':
    case 'ambar':
      return p.atencion;
    case 'problema':
    case 'error':
    case 'rojo':
      return p.problema;
    default:
      return p.textoSuave;
  }
}
