import { onlineManager, useIsRestoring, type UseQueryResult } from '@tanstack/react-query';
import { useState, useSyncExternalStore, type ReactNode } from 'react';
import { RefreshControl, ScrollView, StyleSheet, View, type ViewStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { haceCuanto } from '@/lib/formato';
import { espacio, radio, useTema } from '@/tema';

import { Icono } from './Icono';
import { Texto } from './Texto';

/**
 * Contenedor de pantalla: respeta notch y barra de gestos, deja espacio
 * generoso a los lados, y si se le pasa `alRefrescar` se actualiza deslizando
 * hacia abajo.
 */
export function Pantalla({
  children,
  titulo,
  subtitulo,
  alRefrescar,
  estilo,
  conMargenSuperior = true,
  derecha,
  sinScroll,
}: {
  children: ReactNode;
  titulo?: string;
  subtitulo?: string | null;
  alRefrescar?: () => Promise<unknown>;
  estilo?: ViewStyle;
  /** Las pantallas con cabecera nativa ya tienen el margen del notch. */
  conMargenSuperior?: boolean;
  derecha?: ReactNode;
  sinScroll?: boolean;
}) {
  const { p } = useTema();
  const margen = useSafeAreaInsets();
  const [refrescando, setRefrescando] = useState(false);

  const refrescar = alRefrescar
    ? async () => {
        setRefrescando(true);
        try {
          await alRefrescar();
        } finally {
          setRefrescando(false);
        }
      }
    : undefined;

  const cabecera = titulo ? (
    <View style={estilos.cabecera}>
      <View style={{ flex: 1 }}>
        <Texto variante="titulo" accessibilityRole="header">
          {titulo}
        </Texto>
        {subtitulo ? (
          <Texto tono="suave" style={{ marginTop: 4 }}>
            {subtitulo}
          </Texto>
        ) : null}
      </View>
      {derecha}
    </View>
  ) : null;

  const relleno = {
    paddingTop: conMargenSuperior ? margen.top + espacio.l : espacio.l,
    paddingBottom: margen.bottom + espacio.xxxl + 40,
    paddingHorizontal: espacio.xl,
  };

  if (sinScroll) {
    return (
      <View style={[{ flex: 1, backgroundColor: p.fondo }, relleno, estilo]}>
        {cabecera}
        {children}
      </View>
    );
  }

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: p.fondo }}
      contentContainerStyle={[relleno, estilo]}
      contentInsetAdjustmentBehavior="automatic"
      keyboardShouldPersistTaps="handled"
      refreshControl={
        refrescar ? (
          <RefreshControl
            refreshing={refrescando}
            onRefresh={refrescar}
            tintColor={p.acentoTexto}
            colors={[p.sobreAcento]}
            progressBackgroundColor={p.acento}
          />
        ) : undefined
      }>
      {cabecera}
      {children}
    </ScrollView>
  );
}

function useEnLinea(): boolean {
  return useSyncExternalStore(
    (cb) => onlineManager.subscribe(cb),
    () => onlineManager.isOnline(),
  );
}

/**
 * Aviso honesto cuando lo que se ve viene de la caché: «Sin conexión · datos de
 * hace 20 minutos». Una app que se pone en blanco sin señal se siente rota; una
 * que enseña lo último y lo dice, se siente fiable.
 */
export function AvisoSinConexion({ consulta }: { consulta?: Pick<UseQueryResult, 'dataUpdatedAt' | 'isError' | 'error' | 'data'> }) {
  const { p } = useTema();
  const enLinea = useEnLinea();
  const restaurando = useIsRestoring();
  const errorDeRed = consulta?.isError && (consulta.error as { sinConexion?: boolean } | null)?.sinConexion;

  if (restaurando || (enLinea && !errorDeRed)) return null;

  const cuando = consulta?.data && consulta.dataUpdatedAt ? ` · datos de ${haceCuanto(consulta.dataUpdatedAt)}` : '';

  return (
    <View style={[estilos.aviso, { backgroundColor: p.superficieAlta, borderColor: p.borde }]}>
      <Icono nombre="sinSenal" color={p.atencion} tamano={18} />
      <Texto variante="pequeno" tono="suave" style={{ flex: 1 }}>
        Sin conexión{cuando}. Puede estar desactualizado.
      </Texto>
    </View>
  );
}

/** Mensaje de error de carga con reintento, para cuando no hay nada en caché. */
export function ErrorDeCarga({ mensaje, alReintentar }: { mensaje: string; alReintentar: () => void }) {
  const { p } = useTema();
  return (
    <View style={[estilos.error, { backgroundColor: p.superficie, borderColor: p.borde }]}>
      <Icono nombre="alerta" color={p.problema} />
      <Texto tono="suave" centrado>
        {mensaje}
      </Texto>
      <Texto variante="cuerpoFuerte" tono="acento" onPress={alReintentar} accessibilityRole="button">
        Reintentar
      </Texto>
    </View>
  );
}

const estilos = StyleSheet.create({
  cabecera: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: espacio.l,
    marginBottom: espacio.xl,
  },
  aviso: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacio.s,
    paddingHorizontal: espacio.l,
    paddingVertical: espacio.m,
    borderRadius: radio.m,
    borderWidth: StyleSheet.hairlineWidth,
    marginBottom: espacio.l,
  },
  error: {
    alignItems: 'center',
    gap: espacio.m,
    padding: espacio.xl,
    borderRadius: radio.l,
    borderWidth: StyleSheet.hairlineWidth,
  },
});
