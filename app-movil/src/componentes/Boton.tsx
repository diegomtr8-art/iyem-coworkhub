import * as Haptics from 'expo-haptics';
import { Pressable, StyleSheet, View, type ViewStyle } from 'react-native';
import Animated, { useAnimatedStyle, useSharedValue, withSpring } from 'react-native-reanimated';

import { radio, useTema } from '@/tema';

import { Icono, type NombreIcono } from './Icono';
import { Texto } from './Texto';

type Variante = 'principal' | 'secundario' | 'fantasma' | 'peligro';

type Props = {
  titulo: string;
  alPulsar?: () => void;
  variante?: Variante;
  icono?: NombreIcono;
  ocupado?: boolean;
  deshabilitado?: boolean;
  compacto?: boolean;
  estilo?: ViewStyle;
  haptica?: boolean;
};

/**
 * Botón con respuesta física: se hunde al tocarlo. Mientras trabaja no gira
 * ningún círculo; cambia el texto y se atenúa, que es lo que dice «ya te oí».
 */
export function Boton({
  titulo,
  alPulsar,
  variante = 'principal',
  icono,
  ocupado,
  deshabilitado,
  compacto,
  estilo,
  haptica = true,
}: Props) {
  const { p } = useTema();
  const escala = useSharedValue(1);
  const animado = useAnimatedStyle(() => ({ transform: [{ scale: escala.value }] }));
  const inactivo = deshabilitado || ocupado;

  const fondo: Record<Variante, string> = {
    principal: p.acento,
    secundario: p.superficieAlta,
    fantasma: 'transparent',
    peligro: 'transparent',
  };
  const texto: Record<Variante, string> = {
    principal: p.sobreAcento,
    secundario: p.texto,
    fantasma: p.texto,
    peligro: p.problema,
  };

  return (
    <Animated.View style={[animado, estilo]}>
      <Pressable
        accessibilityRole="button"
        accessibilityState={{ disabled: !!inactivo, busy: !!ocupado }}
        disabled={inactivo}
        onPressIn={() => {
          escala.set(withSpring(0.97, { damping: 20, stiffness: 400 }));
        }}
        onPressOut={() => {
          escala.set(withSpring(1, { damping: 15, stiffness: 300 }));
        }}
        onPress={() => {
          if (haptica) Haptics.selectionAsync().catch(() => {});
          alPulsar?.();
        }}
        style={[
          estilos.base,
          compacto && estilos.compacto,
          // Deshabilitado se ve neutro, no «amarillo sucio»: la transparencia
          // sobre el acento se leía como error y bajaba el contraste del texto.
          deshabilitado && variante === 'principal'
            ? { backgroundColor: p.superficieAlta }
            : { backgroundColor: fondo[variante], opacity: inactivo ? 0.6 : 1 },
          variante === 'secundario' && { borderWidth: StyleSheet.hairlineWidth, borderColor: p.borde },
          variante === 'peligro' && { borderWidth: 1, borderColor: p.problema },
        ]}>
        <View style={estilos.fila}>
          {icono ? <Icono nombre={icono} color={deshabilitado && variante === 'principal' ? p.textoTenue : texto[variante]} tamano={20} /> : null}
          <Texto variante="boton" color={deshabilitado && variante === 'principal' ? p.textoTenue : texto[variante]}>
            {ocupado ? 'Un momento…' : titulo}
          </Texto>
        </View>
      </Pressable>
    </Animated.View>
  );
}

const estilos = StyleSheet.create({
  base: {
    minHeight: 56,
    borderRadius: radio.total,
    paddingHorizontal: 24,
    alignItems: 'center',
    justifyContent: 'center',
  },
  compacto: { minHeight: 44, paddingHorizontal: 18 },
  fila: { flexDirection: 'row', alignItems: 'center', gap: 10 },
});
