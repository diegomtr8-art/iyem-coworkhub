import { StyleSheet, View } from 'react-native';

import type { EstadoMembresia } from '@/lib/tipos';
import { colorDeTono, espacio, radio, useTema } from '@/tema';

import { Boton } from './Boton';
import { Icono } from './Icono';
import { Texto } from './Texto';

/**
 * El estado de la membresía cuando hay algo que decir: por vencer, vencida o
 * sin membresía. Con claridad y con la acción para resolverlo; un portal que
 * solo deja de funcionar, sin explicar por qué, manda a la gente a recepción.
 */
export function BannerEstado({ estado, alAccion }: { estado: EstadoMembresia; alAccion?: () => void }) {
  const { p } = useTema();
  if (estado.tono === 'bien') return null;

  const color = colorDeTono(p, estado.tono);

  return (
    <View style={[estilos.banner, { backgroundColor: p.superficie, borderColor: color }]}>
      <View style={estilos.fila}>
        <View style={[estilos.punto, { backgroundColor: color }]}>
          <Icono nombre={estado.tono === 'problema' ? 'alerta' : 'reloj'} color={p.sobreAcento} tamano={18} />
        </View>
        <View style={{ flex: 1, gap: 2 }}>
          <Texto variante="seccion">{estado.titulo}</Texto>
          <Texto variante="pequeno" tono="suave">
            {estado.detalle}
          </Texto>
        </View>
      </View>
      {estado.accion && alAccion ? <Boton titulo={estado.accion} alPulsar={alAccion} compacto /> : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  banner: {
    borderRadius: radio.l,
    borderWidth: 1.5,
    padding: espacio.l,
    gap: espacio.l,
    marginBottom: espacio.xl,
  },
  fila: { flexDirection: 'row', gap: espacio.m, alignItems: 'center' },
  punto: { width: 36, height: 36, borderRadius: 18, alignItems: 'center', justifyContent: 'center' },
});
