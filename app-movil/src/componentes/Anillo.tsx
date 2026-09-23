import { useEffect } from 'react';
import { StyleSheet, View } from 'react-native';
import Animated, { Easing, ReduceMotion, useAnimatedProps, useSharedValue, withDelay, withTiming } from 'react-native-reanimated';
import Svg, { Circle } from 'react-native-svg';

import { horas } from '@/lib/formato';
import type { Bolsa } from '@/lib/tipos';
import { colorDeBolsa, espacio, useTema } from '@/tema';

import { Texto } from './Texto';

const CirculoAnimado = Animated.createAnimatedComponent(Circle);

/**
 * Anillo de progreso. Muestra **lo disponible**, no lo gastado: el anillo lleno
 * es buena noticia. Se dibuja desde cero al aparecer, salvo que el sistema pida
 * reducir el movimiento.
 */
export function Anillo({
  fraccion,
  color,
  tamano = 56,
  grosor = 6,
  retraso = 0,
  children,
}: {
  /** De 0 a 1: la parte disponible. */
  fraccion: number;
  color: string;
  tamano?: number;
  grosor?: number;
  retraso?: number;
  children?: React.ReactNode;
}) {
  const { p } = useTema();
  const r = (tamano - grosor) / 2;
  const circunferencia = 2 * Math.PI * r;
  const progreso = useSharedValue(0);

  useEffect(() => {
    progreso.set(0);
    progreso.set(
      withDelay(
        retraso,
        withTiming(Math.max(0, Math.min(1, fraccion)), {
          duration: 900,
          easing: Easing.out(Easing.cubic),
          reduceMotion: ReduceMotion.System,
        }),
      ),
    );
  }, [fraccion, retraso, progreso]);

  const props = useAnimatedProps(() => ({
    strokeDashoffset: circunferencia * (1 - progreso.get()),
  }));

  return (
    <View style={{ width: tamano, height: tamano }} importantForAccessibility="no-hide-descendants">
      <Svg width={tamano} height={tamano} style={{ transform: [{ rotate: '-90deg' }] }}>
        <Circle cx={tamano / 2} cy={tamano / 2} r={r} stroke={p.pista} strokeWidth={grosor} fill="none" />
        <CirculoAnimado
          cx={tamano / 2}
          cy={tamano / 2}
          r={r}
          stroke={color}
          strokeWidth={grosor}
          strokeLinecap="round"
          fill="none"
          strokeDasharray={`${circunferencia} ${circunferencia}`}
          animatedProps={props}
        />
      </Svg>
      {children ? <View style={[StyleSheet.absoluteFill, estilos.centro]}>{children}</View> : null}
    </View>
  );
}

/**
 * Una bolsa de horas como fila: anillo pequeño, nombre completo (nunca
 * cortado), lo que queda en grande y el límite diario si lo hay. Es la pieza
 * de Inicio y de Membresía, así que las dos pantallas dicen lo mismo igual.
 */
export function FilaBolsa({ bolsa, retraso = 0 }: { bolsa: Bolsa; retraso?: number }) {
  const { p } = useTema();
  const color = colorDeBolsa(p, bolsa.bolsa);
  const unidad = bolsa.unidad === 'días' ? 'días' : 'h';
  const fraccion = bolsa.ilimitada ? 1 : bolsa.cupo ? (bolsa.restante ?? 0) / bolsa.cupo : 0;

  const detalle = bolsa.agotada
    ? 'Ya la usaste toda este ciclo'
    : bolsa.tope_diario
      ? `Máximo ${horas(bolsa.tope_diario)} h al día`
      : null;

  const leido = bolsa.ilimitada
    ? `${bolsa.etiqueta}: ilimitado`
    : `${bolsa.etiqueta}: te quedan ${horas(bolsa.restante)} de ${horas(bolsa.cupo)} ${unidad === 'h' ? 'horas' : 'días'}${detalle ? `. ${detalle}` : ''}`;

  return (
    <View style={estilos.fila} accessible accessibilityLabel={leido}>
      <Anillo fraccion={fraccion} color={color} retraso={retraso} tamano={48} grosor={5} />
      <View style={{ flex: 1, gap: 2 }}>
        <Texto variante="cuerpoFuerte">{bolsa.etiqueta}</Texto>
        {detalle ? (
          <Texto variante="pequeno" tono={bolsa.agotada ? 'problema' : 'tenue'}>
            {detalle}
          </Texto>
        ) : null}
      </View>
      <View style={estilos.cifra}>
        {bolsa.ilimitada ? (
          <Texto variante="subtitulo">Ilimitado</Texto>
        ) : (
          <>
            <Texto variante="numero" style={{ fontSize: 26, lineHeight: 30 }}>
              {horas(bolsa.restante)}
              <Texto variante="cuerpoFuerte" tono="suave">
                {' '}
                {unidad}
              </Texto>
            </Texto>
            <Texto variante="pequeno" tono="tenue">
              de {horas(bolsa.cupo)}
            </Texto>
          </>
        )}
      </View>
    </View>
  );
}

const estilos = StyleSheet.create({
  centro: { alignItems: 'center', justifyContent: 'center' },
  fila: { flexDirection: 'row', alignItems: 'center', gap: espacio.l, paddingVertical: espacio.m },
  cifra: { alignItems: 'flex-end', minWidth: 64 },
});
