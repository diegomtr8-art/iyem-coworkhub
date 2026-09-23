import { Text, type TextProps, type TextStyle } from 'react-native';

import { fuentes, useTema } from '@/tema';

/**
 * Tipografía de la app. Carmen Sans para lo que se lee de un vistazo —títulos
 * y, sobre todo, números—; GT Eesti para lo que se lee con calma.
 */

type Variante = 'gigante' | 'numero' | 'titulo' | 'subtitulo' | 'seccion' | 'cuerpo' | 'cuerpoFuerte' | 'pequeno' | 'etiqueta' | 'boton';

type Tono = 'normal' | 'suave' | 'tenue' | 'acento' | 'problema' | 'bien' | 'atencion' | 'sobreAcento';

const estilos: Record<Variante, TextStyle> = {
  gigante: { fontFamily: fuentes.numero, fontSize: 56, lineHeight: 60, letterSpacing: -1.5 },
  numero: { fontFamily: fuentes.numero, fontSize: 32, lineHeight: 36, letterSpacing: -0.5 },
  titulo: { fontFamily: fuentes.tituloFuerte, fontSize: 30, lineHeight: 34, letterSpacing: -0.6 },
  subtitulo: { fontFamily: fuentes.titulo, fontSize: 21, lineHeight: 26, letterSpacing: -0.3 },
  seccion: { fontFamily: fuentes.titulo, fontSize: 17, lineHeight: 22 },
  cuerpo: { fontFamily: fuentes.texto, fontSize: 16, lineHeight: 23 },
  cuerpoFuerte: { fontFamily: fuentes.semi, fontSize: 16, lineHeight: 22 },
  pequeno: { fontFamily: fuentes.texto, fontSize: 14, lineHeight: 20 },
  // Sin mayúsculas forzadas ni tracking abierto: se leen peor y, con lector de
  // pantalla, algunas voces las deletrean.
  etiqueta: { fontFamily: fuentes.semi, fontSize: 13, lineHeight: 18, letterSpacing: 0.1 },
  boton: { fontFamily: fuentes.titulo, fontSize: 17, lineHeight: 22 },
};

type Props = TextProps & { variante?: Variante; tono?: Tono; centrado?: boolean; color?: string };

export function Texto({ variante = 'cuerpo', tono = 'normal', centrado, color, style, ...resto }: Props) {
  const { p } = useTema();
  const colores: Record<Tono, string> = {
    normal: p.texto,
    suave: p.textoSuave,
    tenue: p.textoTenue,
    acento: p.acentoTexto,
    problema: p.problema,
    bien: p.bien,
    atencion: p.atencion,
    sobreAcento: p.sobreAcento,
  };

  // Respeta el tamaño de letra del sistema, con un tope para que los números
  // grandes no rompan la pantalla. Quien usa letra grande la necesita.
  const tope = variante === 'gigante' || variante === 'numero' ? 1.3 : 1.6;

  return (
    <Text
      maxFontSizeMultiplier={tope}
      {...resto}
      style={[estilos[variante], { color: color ?? colores[tono] }, centrado && { textAlign: 'center' }, style]}
    />
  );
}
