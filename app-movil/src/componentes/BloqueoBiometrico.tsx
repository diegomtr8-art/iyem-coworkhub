import { Image } from 'expo-image';
import { useEffect } from 'react';
import { StyleSheet, View } from 'react-native';

import { useSesion } from '@/lib/sesion';
import { espacio, useTema } from '@/tema';

import { Boton } from './Boton';
import { Texto } from './Texto';

/**
 * Cubre la app hasta que la persona se identifique con Face ID o huella.
 * Protege la app en el teléfono; no tiene nada que ver con el reconocimiento
 * facial de la entrada a Nódico.
 */
export function BloqueoBiometrico() {
  const { p, esOscuro } = useTema();
  const { desbloquear, salir } = useSesion();

  useEffect(() => {
    void desbloquear();
  }, [desbloquear]);

  return (
    <View style={[StyleSheet.absoluteFill, estilos.fondo, { backgroundColor: p.fondo }]}>
      <Image
        source={esOscuro ? require('../../assets/images/logo-blanco.png') : require('../../assets/images/logo-oscuro.png')}
        style={{ width: 180, height: 62 }}
        contentFit="contain"
      />
      <Texto tono="suave" centrado style={{ marginTop: espacio.xl }}>
        Tu app está protegida.
      </Texto>
      <View style={{ width: '100%', gap: espacio.m, marginTop: espacio.xxl }}>
        <Boton titulo="Desbloquear" icono="huella" alPulsar={() => void desbloquear()} />
        <Boton titulo="Cerrar sesión" variante="fantasma" alPulsar={() => void salir()} />
      </View>
    </View>
  );
}

const estilos = StyleSheet.create({
  fondo: { alignItems: 'center', justifyContent: 'center', padding: espacio.xxl, zIndex: 100 },
});
