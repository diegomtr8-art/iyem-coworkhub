import { Children, isValidElement, type ReactNode } from 'react';
import { Pressable, StyleSheet, View, type ViewStyle } from 'react-native';

import { espacio, radio, useTema } from '@/tema';

import { Icono, type NombreIcono } from './Icono';
import { Texto } from './Texto';

/**
 * Superficie base. Sin borde en oscuro —la diferencia de tono ya separa— y con
 * un filo apenas visible en claro. Menos líneas, menos ruido.
 */
export function Tarjeta({
  children,
  estilo,
  alPulsar,
  acento,
  etiquetaAccesible,
}: {
  children: ReactNode;
  estilo?: ViewStyle | ViewStyle[];
  alPulsar?: () => void;
  /** Una marca de color discreta a la izquierda: plan, estado o «sin leer». */
  acento?: string | null;
  /** Lo que lee el lector de pantalla si la tarjeta entera es un botón. */
  etiquetaAccesible?: string;
}) {
  const { p, esOscuro } = useTema();
  const contenido = (
    <View
      style={[
        estilos.tarjeta,
        { backgroundColor: p.superficie },
        !esOscuro && { borderWidth: StyleSheet.hairlineWidth, borderColor: p.borde },
        estilo,
      ]}>
      {acento ? <View style={[estilos.marca, { backgroundColor: acento }]} /> : null}
      {children}
    </View>
  );

  if (!alPulsar) return contenido;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={etiquetaAccesible}
      onPress={alPulsar}
      style={({ pressed }) => ({ opacity: pressed ? 0.8 : 1 })}>
      {contenido}
    </Pressable>
  );
}

/** Etiqueta de estado. Punto de color + texto: el color nunca es la única señal. */
export function Chip({ texto, color, relleno }: { texto: string; color?: string; relleno?: boolean }) {
  const { p } = useTema();
  const c = color ?? p.textoSuave;
  return (
    <View style={[estilos.chip, { backgroundColor: relleno ? c : p.superficieAlta }]}>
      {!relleno ? <View style={[estilos.chipPunto, { backgroundColor: c }]} /> : null}
      <Texto variante="etiqueta" color={relleno ? p.sobreAcento : p.texto}>
        {texto}
      </Texto>
    </View>
  );
}

/** Fila de lista con icono, texto y flecha. Es el ladrillo de Perfil y Membresía. */
export function Fila({
  icono,
  titulo,
  detalle,
  alPulsar,
  derecha,
  peligro,
}: {
  icono?: NombreIcono;
  titulo: string;
  detalle?: string | null;
  alPulsar?: () => void;
  derecha?: ReactNode;
  peligro?: boolean;
}) {
  const { p } = useTema();
  const color = peligro ? p.problema : p.texto;
  return (
    <Pressable
      accessibilityRole={alPulsar ? 'button' : undefined}
      accessibilityLabel={detalle ? `${titulo}. ${detalle}` : titulo}
      onPress={alPulsar}
      disabled={!alPulsar}
      style={({ pressed }) => [estilos.fila, { opacity: pressed ? 0.6 : 1 }]}>
      {icono ? <Icono nombre={icono} color={peligro ? p.problema : p.textoSuave} tamano={22} /> : null}
      <View style={{ flex: 1, gap: 2 }}>
        <Texto variante="cuerpoFuerte" color={color}>
          {titulo}
        </Texto>
        {detalle ? (
          <Texto variante="pequeno" tono="tenue">
            {detalle}
          </Texto>
        ) : null}
      </View>
      {derecha ?? (alPulsar ? <Icono nombre="flecha" color={p.textoTenue} tamano={18} /> : null)}
    </Pressable>
  );
}

/**
 * Grupo de filas dentro de una sola superficie, con separadores que empiezan
 * donde empieza el texto (como las listas de iOS). Sustituye a «tarjeta con
 * filas y líneas a todo lo ancho».
 */
export function Grupo({ children, estilo }: { children: ReactNode; estilo?: ViewStyle }) {
  const { p, esOscuro } = useTema();
  const hijos = Children.toArray(children).filter(isValidElement);

  return (
    <View
      style={[
        estilos.grupo,
        { backgroundColor: p.superficie },
        !esOscuro && { borderWidth: StyleSheet.hairlineWidth, borderColor: p.borde },
        estilo,
      ]}>
      {hijos.map((hijo, i) => (
        <View key={i}>
          {i > 0 ? <View style={[estilos.divisor, { backgroundColor: p.borde }]} /> : null}
          {hijo}
        </View>
      ))}
    </View>
  );
}

export function Separador() {
  const { p } = useTema();
  return <View style={{ height: StyleSheet.hairlineWidth, backgroundColor: p.borde, marginVertical: espacio.xs }} />;
}

/** Título de sección en frase normal, con peso, no en mayúsculas espaciadas. */
export function TituloSeccion({ children, accion }: { children: string; accion?: ReactNode }) {
  return (
    <View style={estilos.tituloSeccion}>
      <Texto variante="seccion" accessibilityRole="header">
        {children}
      </Texto>
      {accion}
    </View>
  );
}

/** Par dato–valor alineado, para resúmenes («Quedarán» · «3 h»). */
export function Dato({ etiqueta, valor, fuerte }: { etiqueta: string; valor: string; fuerte?: boolean }) {
  return (
    <View style={estilos.dato} accessible accessibilityLabel={`${etiqueta}: ${valor}`}>
      <Texto variante="pequeno" tono="suave">
        {etiqueta}
      </Texto>
      <Texto variante={fuerte ? 'seccion' : 'cuerpoFuerte'} style={{ textAlign: 'right', flexShrink: 1 }}>
        {valor}
      </Texto>
    </View>
  );
}

const estilos = StyleSheet.create({
  tarjeta: {
    borderRadius: radio.l,
    padding: espacio.xl,
    gap: espacio.m,
    overflow: 'hidden',
  },
  marca: {
    position: 'absolute',
    left: 0,
    top: espacio.xl,
    bottom: espacio.xl,
    width: 3,
    borderTopRightRadius: 3,
    borderBottomRightRadius: 3,
  },
  chip: {
    alignSelf: 'flex-start',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    borderRadius: radio.total,
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  chipPunto: { width: 8, height: 8, borderRadius: 4 },
  fila: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacio.l,
    paddingVertical: 14,
    minHeight: 56,
  },
  grupo: {
    borderRadius: radio.l,
    paddingHorizontal: espacio.l,
    overflow: 'hidden',
  },
  divisor: { height: StyleSheet.hairlineWidth, marginLeft: 38 },
  tituloSeccion: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: espacio.xxl,
    marginBottom: espacio.m,
  },
  dato: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: espacio.l,
    paddingVertical: 6,
  },
});
