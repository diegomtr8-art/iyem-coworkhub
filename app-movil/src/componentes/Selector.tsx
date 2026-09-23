import { useState } from 'react';
import { FlatList, Modal, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { espacio, radio, useTema } from '@/tema';

import { Icono } from './Icono';
import { Texto } from './Texto';

type Opcion = { valor: string; etiqueta: string; detalle?: string };

/** Lista de opciones en una hoja: el selector nativo, sin depender de uno por plataforma. */
export function Selector({
  etiqueta,
  valor,
  opciones,
  alCambiar,
  error,
  marcador = 'Elige una opción',
}: {
  etiqueta: string;
  valor: string | null | undefined;
  opciones: Opcion[];
  alCambiar: (v: string) => void;
  error?: string | null;
  marcador?: string;
}) {
  const { p } = useTema();
  const margen = useSafeAreaInsets();
  const [abierto, setAbierto] = useState(false);
  const actual = opciones.find((o) => o.valor === valor);

  return (
    <View style={{ gap: 6 }}>
      <Texto variante="etiqueta" tono="suave">
        {etiqueta}
      </Texto>
      <Pressable
        onPress={() => setAbierto(true)}
        accessibilityRole="button"
        style={[estilos.caja, { backgroundColor: p.superficie, borderColor: error ? p.problema : p.borde }]}>
        <Texto tono={actual ? 'normal' : 'tenue'} style={{ flex: 1 }} numberOfLines={1}>
          {actual ? actual.etiqueta : marcador}
        </Texto>
        <Icono nombre="flecha" color={p.textoTenue} tamano={18} />
      </Pressable>
      {error ? (
        <Texto variante="pequeno" tono="problema">
          {error}
        </Texto>
      ) : null}

      <Modal visible={abierto} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setAbierto(false)}>
        <View style={{ flex: 1, backgroundColor: p.superficie }}>
          <View style={estilos.cabecera}>
            <Texto variante="subtitulo" style={{ flex: 1 }}>
              {etiqueta}
            </Texto>
            <Pressable onPress={() => setAbierto(false)} accessibilityLabel="Cerrar" hitSlop={12}>
              <Icono nombre="cerrar" color={p.texto} />
            </Pressable>
          </View>
          <FlatList
            data={opciones}
            keyExtractor={(o) => o.valor}
            contentContainerStyle={{ paddingHorizontal: espacio.xl, paddingBottom: margen.bottom + espacio.xl }}
            renderItem={({ item }) => (
              <Pressable
                onPress={() => {
                  alCambiar(item.valor);
                  setAbierto(false);
                }}
                style={[estilos.opcion, { borderColor: p.borde }]}>
                <View style={{ flex: 1 }}>
                  <Texto variante="cuerpoFuerte">{item.etiqueta}</Texto>
                  {item.detalle ? (
                    <Texto variante="pequeno" tono="suave">
                      {item.detalle}
                    </Texto>
                  ) : null}
                </View>
                {item.valor === valor ? <Icono nombre="check" color={p.acentoTexto} /> : null}
              </Pressable>
            )}
          />
        </View>
      </Modal>
    </View>
  );
}

const estilos = StyleSheet.create({
  caja: {
    minHeight: 54,
    borderRadius: radio.m,
    borderWidth: 1.5,
    paddingHorizontal: espacio.l,
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacio.s,
  },
  cabecera: { flexDirection: 'row', alignItems: 'center', padding: espacio.xl, paddingTop: espacio.xxl },
  opcion: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacio.m,
    paddingVertical: espacio.l,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
});
