import { Linking, Platform, StyleSheet, View } from 'react-native';

import { espacio, useTema } from '@/tema';

import { Boton } from './Boton';
import { Icono } from './Icono';
import { Texto } from './Texto';

/** El servidor dijo `409 version_obsoleta`: esta versión ya no puede hablar con la API. */
export function VersionObsoleta() {
  const { p } = useTema();
  const tienda = Platform.OS === 'ios' ? 'https://apps.apple.com/' : 'https://play.google.com/store/apps/details?id=mx.com.nodico.app';

  return (
    <View style={[StyleSheet.absoluteFill, estilos.fondo, { backgroundColor: p.fondo }]}>
      <View style={[estilos.circulo, { backgroundColor: p.acento }]}>
        <Icono nombre="recargar" color={p.sobreAcento} tamano={36} />
      </View>
      <Texto variante="titulo" centrado>
        Actualiza la app
      </Texto>
      <Texto tono="suave" centrado>
        Esta versión de Nódico ya no es compatible. Descarga la más reciente para seguir reservando.
      </Texto>
      <Boton
        titulo="Ir a la tienda"
        alPulsar={() => void Linking.openURL(tienda)}
        estilo={{ alignSelf: 'stretch', marginTop: espacio.l }}
      />
    </View>
  );
}

const estilos = StyleSheet.create({
  fondo: { alignItems: 'center', justifyContent: 'center', padding: espacio.xxl, gap: espacio.l, zIndex: 200 },
  circulo: { width: 84, height: 84, borderRadius: 42, alignItems: 'center', justifyContent: 'center' },
});
