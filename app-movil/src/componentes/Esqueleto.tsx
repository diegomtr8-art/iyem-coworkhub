import { useEffect } from 'react';
import { View, type DimensionValue, type ViewStyle } from 'react-native';
import Animated, { Easing, useAnimatedStyle, useSharedValue, withRepeat, withTiming } from 'react-native-reanimated';

import { espacio, radio, useTema } from '@/tema';

/**
 * Esqueletos de carga: la forma de lo que viene, respirando. **Nunca** un
 * círculo girando: el esqueleto dice qué se está cargando y dónde va a
 * aparecer, y la pantalla no salta cuando llega.
 */
export function Hueso({
  ancho = '100%',
  alto = 16,
  redondo = radio.s,
  estilo,
}: {
  ancho?: DimensionValue;
  alto?: number;
  redondo?: number;
  estilo?: ViewStyle;
}) {
  const { p } = useTema();
  const opacidad = useSharedValue(0.55);

  useEffect(() => {
    opacidad.set(withRepeat(withTiming(1, { duration: 850, easing: Easing.inOut(Easing.quad) }), -1, true));
  }, [opacidad]);

  const animado = useAnimatedStyle(() => ({ opacity: opacidad.get() }));

  return <Animated.View style={[{ width: ancho, height: alto, borderRadius: redondo, backgroundColor: p.esqueleto }, animado, estilo]} />;
}

/** Tarjeta genérica en esqueleto. */
export function EsqueletoTarjeta({ lineas = 3, alto }: { lineas?: number; alto?: number }) {
  const { p } = useTema();
  return (
    <View
      style={{
        backgroundColor: p.superficie,
        borderRadius: radio.l,
        padding: espacio.xl,
        gap: espacio.m,
        minHeight: alto,
      }}>
      <Hueso ancho="45%" alto={14} />
      {Array.from({ length: lineas }).map((_, i) => (
        <Hueso key={i} ancho={i === lineas - 1 ? '70%' : '100%'} alto={12} />
      ))}
    </View>
  );
}

export function EsqueletoLista({ filas = 4 }: { filas?: number }) {
  return (
    <View style={{ gap: espacio.m }}>
      {Array.from({ length: filas }).map((_, i) => (
        <EsqueletoTarjeta key={i} lineas={2} />
      ))}
    </View>
  );
}

/** Filas de bolsa en esqueleto, con la misma geometría que `FilaBolsa`. */
export function EsqueletoAnillos() {
  return (
    <View style={{ gap: espacio.l }}>
      {[0, 1, 2].map((i) => (
        <View key={i} style={{ flexDirection: 'row', alignItems: 'center', gap: espacio.l }}>
          <Hueso ancho={48} alto={48} redondo={24} />
          <View style={{ flex: 1, gap: espacio.s }}>
            <Hueso ancho="60%" alto={14} />
            <Hueso ancho="35%" alto={10} />
          </View>
          <Hueso ancho={48} alto={24} />
        </View>
      ))}
    </View>
  );
}
