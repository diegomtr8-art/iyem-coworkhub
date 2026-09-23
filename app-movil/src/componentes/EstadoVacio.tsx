import { StyleSheet, View } from 'react-native';
import Svg, { Circle, Path, Rect } from 'react-native-svg';

import { espacio, useTema } from '@/tema';

import { Boton } from './Boton';
import { Texto } from './Texto';

type Ilustracion = 'reservas' | 'avisos' | 'pagos' | 'asesoria' | 'membresia' | 'sinSenal' | 'reportes';

/**
 * Un estado vacío con personalidad: dibujo, una frase que dice qué pasa y un
 * botón con lo siguiente que tiene sentido hacer. Una pantalla en blanco se lee
 * como un error; esto se lee como una invitación.
 */
export function EstadoVacio({
  ilustracion,
  titulo,
  detalle,
  accion,
  alPulsar,
}: {
  ilustracion: Ilustracion;
  titulo: string;
  detalle?: string;
  accion?: string;
  alPulsar?: () => void;
}) {
  return (
    <View style={estilos.contenedor}>
      <Dibujo tipo={ilustracion} />
      <Texto variante="subtitulo" centrado>
        {titulo}
      </Texto>
      {detalle ? (
        <Texto tono="suave" centrado style={{ maxWidth: 300 }}>
          {detalle}
        </Texto>
      ) : null}
      {accion && alPulsar ? <Boton titulo={accion} alPulsar={alPulsar} compacto estilo={{ marginTop: espacio.s }} /> : null}
    </View>
  );
}

function Dibujo({ tipo }: { tipo: Ilustracion }) {
  const { p } = useTema();
  const a = p.acento;
  const l = p.borde;
  const s = p.superficieAlta;
  const trazo = { stroke: p.textoSuave, strokeWidth: 3, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const, fill: 'none' };

  return (
    <Svg width={148} height={128} viewBox="0 0 148 128">
      <Circle cx={74} cy={66} r={56} fill={s} />
      {tipo === 'reservas' && (
        <>
          <Rect x={40} y={36} width={68} height={60} rx={12} fill={p.superficie} stroke={l} strokeWidth={3} />
          <Path d="M40 54h68" stroke={l} strokeWidth={3} />
          <Path d="M56 28v14M92 28v14" {...trazo} />
          <Circle cx={74} cy={75} r={11} fill={a} />
          <Path d="M69 75l4 4 7-8" stroke={p.sobreAcento} strokeWidth={3} strokeLinecap="round" strokeLinejoin="round" fill="none" />
        </>
      )}
      {tipo === 'avisos' && (
        <>
          <Path d="M50 84V62a24 24 0 0 1 48 0v22l6 8H44z" fill={p.superficie} stroke={l} strokeWidth={3} />
          <Path d="M66 98a8 8 0 0 0 16 0" {...trazo} />
          <Circle cx={98} cy={42} r={9} fill={a} />
        </>
      )}
      {tipo === 'pagos' && (
        <>
          <Rect x={34} y={42} width={80} height={52} rx={10} fill={p.superficie} stroke={l} strokeWidth={3} />
          <Path d="M34 58h80" stroke={l} strokeWidth={6} />
          <Rect x={46} y={72} width={26} height={8} rx={4} fill={a} />
        </>
      )}
      {tipo === 'asesoria' && (
        <>
          <Rect x={30} y={40} width={56} height={38} rx={12} fill={p.superficie} stroke={l} strokeWidth={3} />
          <Rect x={62} y={62} width={56} height={38} rx={12} fill={a} />
          <Path d="M44 55h28M44 64h18" {...trazo} />
          <Path d="M76 78h28M76 86h18" stroke={p.sobreAcento} strokeWidth={3} strokeLinecap="round" />
        </>
      )}
      {tipo === 'membresia' && (
        <>
          <Rect x={36} y={36} width={76} height={56} rx={12} fill={p.superficie} stroke={l} strokeWidth={3} />
          <Path d="M74 48l5 10 11 1.6-8 7.6 2 11L74 73l-10 5.2 2-11-8-7.6 11-1.6z" fill={a} />
        </>
      )}
      {tipo === 'sinSenal' && (
        <>
          <Path d="M44 64a42 42 0 0 1 60 0M54 76a28 28 0 0 1 40 0M64 88a14 14 0 0 1 20 0" {...trazo} />
          <Circle cx={74} cy={98} r={5} fill={a} />
          <Path d="M40 36l68 68" stroke={p.problema} strokeWidth={4} strokeLinecap="round" />
        </>
      )}
      {tipo === 'reportes' && (
        <>
          <Path d="M40 96h68" {...trazo} />
          <Rect x={46} y={66} width={12} height={28} rx={4} fill={l} />
          <Rect x={68} y={46} width={12} height={48} rx={4} fill={a} />
          <Rect x={90} y={58} width={12} height={36} rx={4} fill={l} />
        </>
      )}
    </Svg>
  );
}

const estilos = StyleSheet.create({
  contenedor: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: espacio.m,
    paddingVertical: espacio.xxxl,
    paddingHorizontal: espacio.xl,
  },
});
