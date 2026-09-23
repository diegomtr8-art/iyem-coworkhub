import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import * as Haptics from 'expo-haptics';
import { router } from 'expo-router';
import { useRef, useState } from 'react';
import { FlatList, Pressable, RefreshControl, StyleSheet, View } from 'react-native';
import Swipeable, { type SwipeableMethods } from 'react-native-gesture-handler/ReanimatedSwipeable';
import Animated, { FadeIn, useAnimatedStyle, type SharedValue } from 'react-native-reanimated';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Chip } from '@/componentes/Base';
import { EsqueletoLista } from '@/componentes/Esqueleto';
import { EstadoVacio } from '@/componentes/EstadoVacio';
import { Icono } from '@/componentes/Icono';
import { AvisoSinConexion } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { peticion } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { diaRelativo, fechaCorta, horas } from '@/lib/formato';
import { devuelveSiCancelaAhora, useCancelarReserva } from '@/lib/reservas';
import type { Paginado, Reserva } from '@/lib/tipos';
import { colorDeBolsa, colorDeTono, espacio, radio, useTema } from '@/tema';

type Pestana = 'proximas' | 'pasadas';

export default function MisReservas() {
  const { p } = useTema();
  const margen = useSafeAreaInsets();
  const [pestana, setPestana] = useState<Pestana>('proximas');
  const [aviso, setAviso] = useState<string | null>(null);
  const { pedir } = useCancelarReserva((m) => {
    setAviso(m);
    setTimeout(() => setAviso(null), 4500);
  });

  const proximas = useQuery({
    queryKey: claves.reservas('proximas'),
    queryFn: () => peticion<Paginado<Reserva>>('/reservas', { consulta: { tipo: 'proximas' } }).then((r) => r.data),
  });

  const pasadas = useInfiniteQuery({
    queryKey: claves.reservas('pasadas'),
    queryFn: ({ pageParam }) => peticion<Paginado<Reserva>>('/reservas', { consulta: { tipo: 'pasadas', cursor: pageParam } }),
    initialPageParam: '' as string,
    getNextPageParam: (ultima) => ultima.meta?.siguiente ?? undefined,
    enabled: pestana === 'pasadas',
  });

  const datos = pestana === 'proximas' ? (proximas.data ?? []) : (pasadas.data?.pages.flatMap((pg) => pg.data) ?? []);
  const cargando = pestana === 'proximas' ? proximas.isPending : pasadas.isPending;
  const consulta = pestana === 'proximas' ? proximas : pasadas;

  return (
    <View style={{ flex: 1, backgroundColor: p.fondo }}>
      <View style={[estilos.segmentos, { backgroundColor: p.superficie }]}>
        {(['proximas', 'pasadas'] as Pestana[]).map((t) => (
          <Pressable
            key={t}
            onPress={() => {
              Haptics.selectionAsync().catch(() => {});
              setPestana(t);
            }}
            style={[estilos.segmento, pestana === t && { backgroundColor: p.superficieAlta }]}>
            <Texto variante="cuerpoFuerte" tono={pestana === t ? 'normal' : 'tenue'}>
              {t === 'proximas' ? 'Próximas' : 'Pasadas'}
            </Texto>
          </Pressable>
        ))}
      </View>

      {aviso ? (
        <Animated.View entering={FadeIn} style={[estilos.aviso, { backgroundColor: p.superficieAlta }]}>
          <Icono nombre="check" color={p.bien} tamano={18} />
          <Texto variante="pequeno" style={{ flex: 1 }}>
            {aviso}
          </Texto>
        </Animated.View>
      ) : null}

      <FlatList
        data={datos}
        keyExtractor={(r) => String(r.id)}
        contentContainerStyle={{ padding: espacio.xl, paddingBottom: margen.bottom + espacio.xxxl, gap: espacio.m, flexGrow: 1 }}
        refreshControl={
          <RefreshControl refreshing={consulta.isRefetching} onRefresh={() => void consulta.refetch()} tintColor={p.acentoTexto} />
        }
        onEndReached={() => {
          if (pestana === 'pasadas' && pasadas.hasNextPage && !pasadas.isFetchingNextPage) void pasadas.fetchNextPage();
        }}
        ListHeaderComponent={
          <>
            <AvisoSinConexion consulta={consulta} />
            {pestana === 'proximas' && datos.length > 0 ? (
              <Texto variante="pequeno" tono="tenue" style={{ marginBottom: espacio.s }}>
                Desliza una reserva a la izquierda para cancelarla.
              </Texto>
            ) : null}
          </>
        }
        ListEmptyComponent={
          cargando ? (
            <EsqueletoLista filas={4} />
          ) : pestana === 'proximas' ? (
            <EstadoVacio
              ilustracion="reservas"
              titulo="Todavía no tienes reservas"
              detalle="Aparta una sala de juntas, un cubículo o el estudio en menos de un minuto."
              accion="Reservar ahora"
              alPulsar={() => router.push('/reservar')}
            />
          ) : (
            <EstadoVacio ilustracion="reservas" titulo="Aún no hay historial" detalle="Aquí verás tus reservas pasadas y canceladas." />
          )
        }
        renderItem={({ item }) =>
          pestana === 'proximas' ? <ReservaDeslizable reserva={item} alCancelar={pedir} /> : <TarjetaReserva reserva={item} pasada />
        }
      />
    </View>
  );
}

function AccionCancelar({ progreso, reserva }: { progreso: SharedValue<number>; reserva: Reserva }) {
  const { p } = useTema();
  const devuelve = devuelveSiCancelaAhora(reserva);
  const animado = useAnimatedStyle(() => ({
    opacity: Math.min(1, progreso.value * 1.5),
    transform: [{ scale: 0.8 + Math.min(progreso.value, 1) * 0.2 }],
  }));
  return (
    <View style={[estilos.accion, { backgroundColor: p.problema }]}>
      <Animated.View style={[{ alignItems: 'center', gap: 4 }, animado]}>
        <Icono nombre="papelera" color="#fff" />
        <Texto variante="pequeno" color="#fff" centrado style={{ fontSize: 12 }}>
          Cancelar
        </Texto>
        <Texto variante="pequeno" color="#fff" centrado style={{ fontSize: 11, opacity: 0.85 }}>
          {devuelve ? 'devuelve horas' : 'no devuelve'}
        </Texto>
      </Animated.View>
    </View>
  );
}

function ReservaDeslizable({ reserva, alCancelar }: { reserva: Reserva; alCancelar: (r: Reserva, alDescartar?: () => void) => void }) {
  const ref = useRef<SwipeableMethods>(null);
  return (
    <Swipeable
      ref={ref}
      friction={1.6}
      rightThreshold={60}
      overshootRight={false}
      renderRightActions={(progreso) => <AccionCancelar progreso={progreso} reserva={reserva} />}
      onSwipeableWillOpen={() => alCancelar(reserva, () => ref.current?.close())}
      containerStyle={{ borderRadius: radio.l }}>
      <TarjetaReserva reserva={reserva} />
    </Swipeable>
  );
}

function TarjetaReserva({ reserva: r, pasada }: { reserva: Reserva; pasada?: boolean }) {
  const { p } = useTema();
  const color = colorDeBolsa(p, r.bolsa ?? '');
  const tono = r.estatus === 'Cancelada' ? 'problema' : r.estatus === 'No_Show' ? 'atencion' : r.estatus === 'Completada' ? 'bien' : null;

  return (
    <Pressable
      onPress={() => router.push({ pathname: '/reserva/[id]', params: { id: String(r.id) } })}
      style={[estilos.tarjeta, { backgroundColor: p.superficie, borderColor: p.borde }]}>
      <View style={[estilos.franja, { backgroundColor: pasada ? p.pista : color }]} />
      <View style={{ flex: 1, gap: 4 }}>
        <Texto variante="cuerpoFuerte">{r.espacio.nombre}</Texto>
        <Texto variante="pequeno" tono="suave">
          {pasada ? fechaCorta(r.fecha) : diaRelativo(r.fecha)} · {r.hora_inicio.slice(0, 5)}–{r.hora_fin.slice(0, 5)}
        </Texto>
        {pasada && tono ? <Chip texto={r.estatus === 'No_Show' ? 'No asististe' : r.estatus} color={colorDeTono(p, tono)} /> : null}
      </View>
      <View style={{ alignItems: 'flex-end' }}>
        <Texto variante="numero" style={{ fontSize: 24, lineHeight: 28 }} color={pasada ? p.textoSuave : color}>
          {horas(r.horas)}
        </Texto>
        <Texto variante="pequeno" tono="tenue">
          h
        </Texto>
      </View>
    </Pressable>
  );
}

const estilos = StyleSheet.create({
  segmentos: { flexDirection: 'row', marginHorizontal: espacio.xl, marginTop: espacio.m, borderRadius: radio.total, padding: 4 },
  segmento: { flex: 1, alignItems: 'center', paddingVertical: 10, borderRadius: radio.total },
  aviso: {
    flexDirection: 'row',
    gap: espacio.s,
    alignItems: 'center',
    marginHorizontal: espacio.xl,
    marginTop: espacio.m,
    padding: espacio.m,
    borderRadius: radio.m,
  },
  tarjeta: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacio.l,
    padding: espacio.l,
    paddingLeft: espacio.xl,
    borderRadius: radio.l,
    borderWidth: StyleSheet.hairlineWidth,
    overflow: 'hidden',
  },
  franja: { position: 'absolute', left: 0, top: 0, bottom: 0, width: 5 },
  accion: { width: 110, alignItems: 'center', justifyContent: 'center', borderRadius: radio.l, marginLeft: espacio.s },
});
