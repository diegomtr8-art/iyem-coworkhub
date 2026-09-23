import * as Haptics from 'expo-haptics';
import { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { descargarYCompartir } from '@/lib/nativo';
import { PREAJUSTES, useRango } from '@/lib/rango';
import { espacio, radio, useTema } from '@/tema';

import { Boton } from './Boton';
import { Texto } from './Texto';

/** Selector de rango con preajustes, compartido por las pestañas de reportes. */
export function SelectorRango() {
  const { p } = useTema();
  const { clave, cambiar } = useRango();
  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={{ gap: espacio.s }}
      style={{ marginBottom: espacio.xl }}>
      {PREAJUSTES.map((r) => {
        const activo = r.clave === clave;
        return (
          <Pressable
            key={r.clave}
            onPress={() => {
              Haptics.selectionAsync().catch(() => {});
              cambiar(r.clave);
            }}
            style={[estilos.chip, { backgroundColor: activo ? p.acento : p.superficie, borderColor: activo ? p.acentoTexto : p.borde }]}>
            <Texto variante="cuerpoFuerte" color={activo ? p.sobreAcento : p.texto}>
              {r.etiqueta}
            </Texto>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

/** Tarjeta de cifra grande: los números son el alma de un reporte. */
export function Cifra({ etiqueta, valor, detalle }: { etiqueta: string; valor: string; detalle?: string; color?: string }) {
  const { p, esOscuro } = useTema();
  // Todas las cifras en el color del texto: cuatro colores distintos para
  // cuatro números competían entre sí sin decir nada más.
  return (
    <View
      accessible
      accessibilityLabel={`${etiqueta}: ${valor}${detalle ? `. ${detalle}` : ''}`}
      style={[estilos.cifra, { backgroundColor: p.superficie }, !esOscuro && { borderWidth: StyleSheet.hairlineWidth, borderColor: p.borde }]}>
      <Texto variante="etiqueta" tono="tenue">
        {etiqueta}
      </Texto>
      <Texto variante="numero" numberOfLines={1} adjustsFontSizeToFit>
        {valor}
      </Texto>
      {detalle ? (
        <Texto variante="pequeno" tono="suave" numberOfLines={2}>
          {detalle}
        </Texto>
      ) : null}
    </View>
  );
}

/** Barra horizontal con etiqueta y valor. Sencilla a propósito: se lee de un vistazo. */
export function BarraReporte({
  etiqueta,
  valor,
  fraccion,
  color,
  detalle,
}: {
  etiqueta: string;
  valor: string;
  fraccion: number;
  color?: string;
  detalle?: string;
}) {
  const { p } = useTema();
  return (
    <View style={{ gap: 6 }}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: espacio.m }}>
        <Texto variante="cuerpoFuerte" style={{ flex: 1 }} numberOfLines={1}>
          {etiqueta}
        </Texto>
        <Texto variante="cuerpoFuerte" color={color ?? p.acentoTexto}>
          {valor}
        </Texto>
      </View>
      <View style={[estilos.pista, { backgroundColor: p.pista }]}>
        <View
          style={[
            estilos.relleno,
            { width: `${Math.max(2, Math.min(100, Math.round(fraccion * 100)))}%`, backgroundColor: color ?? p.acentoGrafico },
          ]}
        />
      </View>
      {detalle ? (
        <Texto variante="pequeno" tono="tenue">
          {detalle}
        </Texto>
      ) : null}
    </View>
  );
}

/** Descarga el CSV del reporte y abre la hoja de compartir. */
export function ExportarCsv({ reporte }: { reporte: string }) {
  const { desde, hasta } = useRango();
  const [ocupado, setOcupado] = useState(false);
  return (
    <Boton
      titulo="Exportar CSV"
      icono="compartir"
      variante="secundario"
      compacto
      ocupado={ocupado}
      estilo={{ marginTop: espacio.xl }}
      alPulsar={async () => {
        setOcupado(true);
        try {
          await descargarYCompartir(`/reportes/${reporte}/csv`, `nodico-${reporte}-${desde}-${hasta}.csv`, 'text/csv', {
            desde,
            hasta,
          });
        } catch {
          Alert.alert('No se pudo exportar', 'Revisa tu conexión e inténtalo de nuevo.');
        } finally {
          setOcupado(false);
        }
      }}
    />
  );
}

const estilos = StyleSheet.create({
  chip: { paddingHorizontal: 16, paddingVertical: 10, borderRadius: radio.total, borderWidth: 1 },
  cifra: { flexBasis: '47%', flexGrow: 1, padding: espacio.l, borderRadius: radio.l, gap: 4 },
  pista: { height: 10, borderRadius: 5, overflow: 'hidden' },
  relleno: { height: '100%', borderRadius: 5 },
});
