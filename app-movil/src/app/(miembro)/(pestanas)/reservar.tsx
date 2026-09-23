import { useQuery } from '@tanstack/react-query';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { useEffect, useMemo, useRef, useState } from 'react';
import { FlatList, Pressable, StyleSheet, View } from 'react-native';
import Animated, { FadeInDown, FadeOut, ReduceMotion } from 'react-native-reanimated';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Boton } from '@/componentes/Boton';
import { Grupo, TituloSeccion } from '@/componentes/Base';
import { Hueso } from '@/componentes/Esqueleto';
import { EstadoVacio } from '@/componentes/EstadoVacio';
import { Icono } from '@/componentes/Icono';
import { AvisoSinConexion, ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { api, ErrorApi } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { aMinutos, deMinutos, diaRelativo, horas, nombreDiaCorto, nombreMes, sumarDias } from '@/lib/formato';
import type { Bloque, DiaDisponible, DisponibilidadDia, Espacio, Espacios } from '@/lib/tipos';
import { colorDeBolsa, espacio, radio, useTema } from '@/tema';

/** Máximo de días por llamada al resumen del calendario (§6.3). */
const DIAS_POR_TRAMO = 45;

/**
 * Reservar: espacio → día → horario → resumen. Es el flujo que más se usa y
 * donde más se nota si la app es buena. La app **anticipa** las reglas con los
 * números que manda el servidor (saldo, tope diario, lo ya usado ese día) para
 * avisar en el momento; quien decide al confirmar es el servidor.
 */
export default function Reservar() {
  const { p } = useTema();
  const margen = useSafeAreaInsets();
  const espacios = useQuery({ queryKey: claves.espacios, queryFn: () => api<Espacios>('/espacios') });

  // Lo elegido se guarda tal cual; lo que se usa se deriva, para que cambiar de
  // espacio o de día no deje una selección que ya no aplica.
  const [espacioElegido, setEspacioId] = useState<number | null>(null);
  // La lista de espacios se pliega en cuanto hay uno elegido: deja a la vista
  // lo que sigue (día y hora) sin obligar a desplazarse.
  const [cambiandoEspacio, setCambiandoEspacio] = useState(false);
  const [fechaElegida, setFecha] = useState<string | null>(null);
  const [seleccionBruta, setSeleccionBruta] = useState<{ clave: string; desde: number; hasta: number } | null>(null);

  const lista = useMemo(() => espacios.data?.espacios ?? [], [espacios.data]);
  // Primer espacio preseleccionado: un toque menos para lo más común.
  const espacioId = espacioElegido ?? lista[0]?.id ?? null;
  const elegido = lista.find((e) => e.id === espacioId) ?? null;
  const horizonte = espacios.data?.horizonte ?? null;
  const granularidad = espacios.data?.operacion.granularidad_minutos ?? 60;

  const hasta = horizonte ? minFecha(horizonte.hasta, sumarDias(horizonte.desde, DIAS_POR_TRAMO - 1)) : null;
  const periodo = useQuery({
    queryKey: claves.periodo(espacioId ?? 0, horizonte?.desde ?? '', hasta ?? ''),
    queryFn: () =>
      api<DiaDisponible[] | { dias: DiaDisponible[] }>(`/espacios/${espacioId}/disponibilidad`, {
        consulta: { desde: horizonte!.desde, hasta: hasta! },
      }).then((r) => (Array.isArray(r) ? r : r.dias)),
    enabled: !!espacioId && !!horizonte && !!hasta,
  });

  // El día elegido si sigue abierto en este espacio; si no, el primero con huecos.
  const fecha = useMemo(() => {
    const dias = periodo.data;
    if (!dias) return null;
    if (fechaElegida && dias.some((d) => d.fecha === fechaElegida && d.abierto)) return fechaElegida;
    return (dias.find((d) => d.abierto && d.libres > 0) ?? dias.find((d) => d.abierto))?.fecha ?? null;
  }, [periodo.data, fechaElegida]);

  const claveSeleccion = `${espacioId}|${fecha}`;
  const seleccion = seleccionBruta?.clave === claveSeleccion ? seleccionBruta : null;
  const setSeleccion = (s: { desde: number; hasta: number } | null) => setSeleccionBruta(s ? { clave: claveSeleccion, ...s } : null);

  const dia = useQuery({
    queryKey: claves.dia(espacioId ?? 0, fecha ?? ''),
    queryFn: () => api<DisponibilidadDia>(`/espacios/${espacioId}/disponibilidad/${fecha}`),
    enabled: !!espacioId && !!fecha,
    staleTime: 15_000,
  });

  // 403 `sin_membresia` o `plan_no_incluye` (este trae la `sugerencia` en la raíz).
  const sinMembresia = espacios.error instanceof ErrorApi && espacios.error.estado === 403;

  if (sinMembresia) {
    return (
      <Pantalla titulo="Reservar">
        <EstadoVacio
          ilustracion="membresia"
          titulo="Reserva con una membresía"
          detalle={(espacios.error as ErrorApi).message || 'Para apartar salas y estudios necesitas una membresía vigente.'}
          accion="Ver planes"
          alPulsar={() => router.push('/contratar')}
        />
      </Pantalla>
    );
  }

  const bloques = dia.data?.bloques ?? [];
  // Lo que ya pasó no se enseña: una rejilla llena de horas tachadas es ruido.
  const visibles = bloques.map((b, i) => ({ b, i })).filter(({ b }) => b.motivo !== 'pasado');
  const horasSel = seleccion ? ((seleccion.hasta - seleccion.desde + 1) * granularidad) / 60 : 0;
  const aviso = dia.data && seleccion ? avisoEnVivo(dia.data, horasSel, elegido?.bolsa_etiqueta ?? '') : null;

  const tocarBloque = (i: number) => {
    const b = bloques[i];
    if (!b?.libre) return;
    Haptics.selectionAsync().catch(() => {});
    if (!seleccion) return setSeleccion({ desde: i, hasta: i });
    // Tocar dentro de la selección la reduce a ese bloque; fuera, la extiende
    // si todo lo de en medio está libre, y si no, empieza de nuevo.
    if (i >= seleccion.desde && i <= seleccion.hasta) {
      return setSeleccion(i === seleccion.desde && i === seleccion.hasta ? null : { desde: i, hasta: i });
    }
    const desde = Math.min(i, seleccion.desde);
    const hasta = Math.max(i, seleccion.hasta);
    const contiguo = bloques.slice(desde, hasta + 1).every((x) => x.libre);
    setSeleccion(contiguo ? { desde, hasta } : { desde: i, hasta: i });
  };

  const revisar = () => {
    if (!elegido || !fecha || !seleccion || !dia.data) return;
    const inicio = bloques[seleccion.desde].hora.slice(0, 5);
    const fin = finDeBloque(bloques[seleccion.hasta], granularidad);
    router.push({
      pathname: '/confirmar-reserva',
      params: {
        espacio_id: String(elegido.id),
        espacio: elegido.nombre,
        bolsa: elegido.bolsa,
        bolsa_etiqueta: elegido.bolsa_etiqueta,
        fecha,
        hora_inicio: inicio,
        hora_fin: fin,
        horas: String(horasSel),
        saldo: dia.data.saldo_ciclo === null ? '' : String(dia.data.saldo_ciclo),
      },
    });
  };

  return (
    <View style={{ flex: 1, backgroundColor: p.fondo }}>
      <Pantalla titulo="Reservar" alRefrescar={() => Promise.all([espacios.refetch(), periodo.refetch(), dia.refetch()])}>
        <AvisoSinConexion consulta={espacios} />

        {espacios.isPending ? (
          <Hueso alto={140} redondo={radio.l} />
        ) : espacios.isError && !espacios.data ? (
          <ErrorDeCarga mensaje={(espacios.error as Error).message} alReintentar={() => void espacios.refetch()} />
        ) : lista.length === 0 ? (
          <EstadoVacio
            ilustracion="reservas"
            titulo="Tu plan no incluye salas ni estudios"
            detalle="Con Nodo Pro o Nodo Match reservas salas de juntas, cubículos y el estudio de contenido."
            accion="Ver planes"
            alPulsar={() => router.push('/contratar')}
          />
        ) : (
          <>
            <TituloSeccion
              accion={
                elegido && !cambiandoEspacio && lista.length > 1 ? (
                  <Texto
                    variante="cuerpoFuerte"
                    tono="acento"
                    accessibilityRole="button"
                    onPress={() => setCambiandoEspacio(true)}
                    suppressHighlighting>
                    Cambiar
                  </Texto>
                ) : undefined
              }>
              Espacio
            </TituloSeccion>
            <Grupo>
              {(cambiandoEspacio || !elegido ? lista : [elegido]).map((e) => (
                <FilaEspacio
                  key={e.id}
                  espacio={e}
                  activo={e.id === espacioId}
                  alPulsar={() => {
                    Haptics.selectionAsync().catch(() => {});
                    setEspacioId(e.id);
                    setCambiandoEspacio(false);
                  }}
                />
              ))}
            </Grupo>

            <TituloSeccion
              accion={
                fecha ? (
                  <Texto variante="pequeno" tono="suave">
                    {nombreMes(fecha).charAt(0).toUpperCase() + nombreMes(fecha).slice(1)}
                  </Texto>
                ) : undefined
              }>
              Día
            </TituloSeccion>
            <Dias dias={periodo.data} cargando={periodo.isPending} fecha={fecha} alElegir={setFecha} />

            <TituloSeccion
              accion={
                dia.data?.apertura ? (
                  <Texto variante="pequeno" tono="suave">
                    De {dia.data.apertura.slice(0, 5)} a {dia.data.cierre?.slice(0, 5)}
                  </Texto>
                ) : undefined
              }>
              Hora
            </TituloSeccion>
            {dia.isPending || !fecha ? (
              <View style={estilos.rejilla}>
                {Array.from({ length: 9 }).map((_, i) => (
                  <Hueso key={i} ancho="31%" alto={56} redondo={radio.m} />
                ))}
              </View>
            ) : dia.data && !dia.data.abierto ? (
              <View style={[estilos.cerrado, { backgroundColor: p.superficie }]}>
                <Texto tono="suave" centrado>
                  {dia.data.motivo_cierre || 'Ese día está cerrado.'}
                </Texto>
              </View>
            ) : (
              <>
                {visibles.length === 0 ? (
                  <View style={[estilos.cerrado, { backgroundColor: p.superficie }]}>
                    <Texto tono="suave" centrado>
                      Ya no quedan horarios este día. Elige otro.
                    </Texto>
                  </View>
                ) : (
                  <>
                    <Texto variante="pequeno" tono="tenue" style={{ marginBottom: espacio.m }}>
                      Toca la hora de inicio; para más de una hora, toca también la última.
                    </Texto>
                    <View style={estilos.rejilla}>
                      {visibles.map(({ b, i }) => (
                        <BloqueHora
                          key={b.indice}
                          bloque={b}
                          seleccionado={!!seleccion && i >= seleccion.desde && i <= seleccion.hasta}
                          alPulsar={() => tocarBloque(i)}
                        />
                      ))}
                    </View>
                  </>
                )}
                {dia.data ? <Cupo dia={dia.data} /> : null}
              </>
            )}
          </>
        )}
      </Pantalla>

      {seleccion && elegido && fecha ? (
        <Animated.View
          entering={FadeInDown.springify().damping(18).reduceMotion(ReduceMotion.System)}
          exiting={FadeOut.duration(150)}
          style={[estilos.barra, { backgroundColor: p.superficieAlta, borderColor: p.borde, bottom: margen.bottom > 0 ? 8 : 12 }]}>
          <View style={{ flex: 1, gap: 2 }}>
            <Texto variante="cuerpoFuerte" numberOfLines={1}>
              {diaRelativo(fecha)} · {bloques[seleccion.desde]?.hora.slice(0, 5)}–{bloques[seleccion.hasta] ? finDeBloque(bloques[seleccion.hasta], granularidad) : ''}
            </Texto>
            {aviso ? (
              <Texto variante="pequeno" tono="problema" numberOfLines={3}>
                {aviso}
              </Texto>
            ) : (
              <Texto variante="pequeno" tono="suave">
                {horas(horasSel)} h de {elegido.bolsa_etiqueta.toLowerCase()}
              </Texto>
            )}
          </View>
          <Boton titulo="Revisar" compacto alPulsar={revisar} deshabilitado={!!aviso} />
        </Animated.View>
      ) : null}
    </View>
  );
}

/**
 * Validación en vivo con los números del servidor, con el mismo texto que
 * devolvería al confirmar. Solo avisa: el servidor vuelve a decidir.
 */
function avisoEnVivo(d: DisponibilidadDia, horasSel: number, etiqueta: string): string | null {
  if (d.tope_diario !== null && d.usado_ese_dia + horasSel > d.tope_diario) {
    const libres = Math.max(0, Math.round((d.tope_diario - d.usado_ese_dia) * 100) / 100);
    return libres > 0
      ? `Tu plan permite ${horas(d.tope_diario)} h al día en ${etiqueta} y ese día ya tienes ${horas(d.usado_ese_dia)} h. Te queda ${horas(libres)} h.`
      : `Tu plan permite ${horas(d.tope_diario)} h al día en ${etiqueta} y ese día ya las usaste.`;
  }
  if (d.saldo_ciclo !== null && horasSel > d.saldo_ciclo) {
    return `No te alcanza: esta reserva son ${horas(horasSel)} h y te quedan ${horas(d.saldo_ciclo)} h de ${etiqueta} en este ciclo.`;
  }
  return null;
}

function minFecha(a: string, b: string) {
  return a < b ? a : b;
}

/** Un espacio como fila elegible: nombre, tipo y capacidad, y la marca de elegido. */
function FilaEspacio({ espacio: e, activo, alPulsar }: { espacio: Espacio; activo: boolean; alPulsar: () => void }) {
  const { p } = useTema();
  const detalle = `${e.tipo_etiqueta}${e.capacidad ? ` · hasta ${e.capacidad} personas` : ''}`;
  return (
    <Pressable
      onPress={alPulsar}
      accessibilityRole="radio"
      accessibilityState={{ checked: activo }}
      accessibilityLabel={`${e.nombre}. ${detalle}. Usa horas de ${e.bolsa_etiqueta.toLowerCase()}.`}
      style={({ pressed }) => [estilos.filaEspacio, { opacity: pressed ? 0.7 : 1 }]}>
      {e.imagen_url ? (
        <Image source={{ uri: e.imagen_url }} style={estilos.miniatura} contentFit="cover" transition={200} />
      ) : (
        <View style={[estilos.miniatura, { backgroundColor: p.superficieAlta }]}>
          <View style={[estilos.puntoBolsa, { backgroundColor: colorDeBolsa(p, e.bolsa) }]} />
        </View>
      )}
      <View style={{ flex: 1, gap: 2 }}>
        <Texto variante="cuerpoFuerte">{e.nombre}</Texto>
        <Texto variante="pequeno" tono="tenue">
          {detalle}
        </Texto>
      </View>
      <View style={[estilos.radio, { borderColor: activo ? p.acentoTexto : p.textoTenue }]}>
        {activo ? <View style={[estilos.radioPunto, { backgroundColor: p.acentoTexto }]} /> : null}
      </View>
    </Pressable>
  );
}

function Dias({
  dias,
  cargando,
  fecha,
  alElegir,
}: {
  dias?: DiaDisponible[];
  cargando: boolean;
  fecha: string | null;
  alElegir: (f: string) => void;
}) {
  const { p } = useTema();
  const lista = useRef<FlatList<DiaDisponible>>(null);

  const indice = useMemo(() => (dias && fecha ? dias.findIndex((d) => d.fecha === fecha) : -1), [dias, fecha]);

  useEffect(() => {
    if (indice > 2) lista.current?.scrollToIndex({ index: indice, viewPosition: 0.4, animated: true });
  }, [indice]);

  if (cargando || !dias) {
    return (
      <View style={{ flexDirection: 'row', gap: espacio.s }}>
        {Array.from({ length: 6 }).map((_, i) => (
          <Hueso key={i} ancho={58} alto={82} redondo={radio.m} />
        ))}
      </View>
    );
  }

  return (
    <FlatList
      ref={lista}
      horizontal
      data={dias}
      keyExtractor={(d) => d.fecha}
      showsHorizontalScrollIndicator={false}
      style={{ marginHorizontal: -espacio.xl }}
      contentContainerStyle={{ gap: espacio.s, paddingHorizontal: espacio.xl }}
      getItemLayout={(_, i) => ({ length: 66, offset: 66 * i, index: i })}
      onScrollToIndexFailed={() => {}}
      renderItem={({ item: d }) => {
        const activo = d.fecha === fecha;
        const lleno = d.abierto && d.libres === 0;
        const inhabil = !d.abierto || lleno;
        return (
          <Pressable
            onPress={() => {
              if (inhabil) return;
              Haptics.selectionAsync().catch(() => {});
              alElegir(d.fecha);
            }}
            accessibilityRole="button"
            accessibilityState={{ selected: activo, disabled: inhabil }}
            accessibilityLabel={`${diaRelativo(d.fecha)}${inhabil ? ', no disponible' : `, ${d.libres} horarios libres`}`}
            style={[
              estilos.dia,
              {
                backgroundColor: activo ? p.acento : p.superficie,
                borderColor: activo ? p.acentoTexto : p.borde,
                opacity: inhabil ? 0.55 : 1,
              },
            ]}>
            <Texto variante="etiqueta" color={activo ? p.sobreAcento : p.textoSuave}>
              {nombreDiaCorto(d.fecha).charAt(0).toUpperCase() + nombreDiaCorto(d.fecha).slice(1)}
            </Texto>
            <Texto variante="numero" color={activo ? p.sobreAcento : p.texto} style={{ fontSize: 24, lineHeight: 28 }}>
              {Number(d.fecha.slice(8, 10))}
            </Texto>
            {inhabil ? (
              <Texto variante="pequeno" color={p.textoTenue} style={{ fontSize: 11, lineHeight: 14 }}>
                {!d.abierto ? 'Cerrado' : 'Lleno'}
              </Texto>
            ) : null}
          </Pressable>
        );
      }}
    />
  );
}

/**
 * Un hueco de una hora. Libre: se puede tocar. Ocupado o demasiado pronto: se
 * ve atenuado y **lo dice con texto**, no solo con el aspecto.
 */
function BloqueHora({ bloque, seleccionado, alPulsar }: { bloque: Bloque; seleccionado: boolean; alPulsar: () => void }) {
  const { p } = useTema();
  const motivo = bloque.libre ? null : bloque.motivo === 'demasiado_pronto' ? 'Muy pronto' : 'Ocupado';
  return (
    <Pressable
      onPress={alPulsar}
      disabled={!bloque.libre}
      accessibilityRole="button"
      accessibilityState={{ selected: seleccionado, disabled: !bloque.libre }}
      accessibilityLabel={`${bloque.hora.slice(0, 5)}${motivo ? `, ${motivo.toLowerCase()}` : seleccionado ? ', elegida' : ', libre'}`}
      style={[
        estilos.bloque,
        {
          backgroundColor: seleccionado ? p.acento : bloque.libre ? p.superficie : 'transparent',
          borderColor: seleccionado ? p.acento : bloque.libre ? p.borde : 'transparent',
        },
      ]}>
      <Texto variante="cuerpoFuerte" color={seleccionado ? p.sobreAcento : bloque.libre ? p.texto : p.textoTenue}>
        {bloque.hora.slice(0, 5)}
      </Texto>
      {motivo ? (
        <Texto variante="pequeno" color={p.textoTenue} style={{ fontSize: 11, lineHeight: 13 }}>
          {motivo}
        </Texto>
      ) : null}
    </Pressable>
  );
}

function Cupo({ dia }: { dia: DisponibilidadDia }) {
  const { p } = useTema();
  const partes: string[] = [];
  if (dia.saldo_ciclo !== null) partes.push(`Te quedan ${horas(dia.saldo_ciclo)} h`);
  if (dia.tope_diario !== null) {
    partes.push(
      dia.usado_ese_dia > 0
        ? `ese día ya llevas ${horas(dia.usado_ese_dia)} de ${horas(dia.tope_diario)} h`
        : `máximo ${horas(dia.tope_diario)} h ese día`,
    );
  }
  if (!partes.length) return null;
  return (
    <View style={[estilos.cupo, { backgroundColor: p.superficie }]}>
      <Icono nombre="reloj" color={p.textoSuave} tamano={16} />
      <Texto variante="pequeno" tono="suave" style={{ flex: 1 }}>
        {partes.join(' · ')}
      </Texto>
    </View>
  );
}

const estilos = StyleSheet.create({
  filaEspacio: { flexDirection: 'row', alignItems: 'center', gap: espacio.l, paddingVertical: espacio.m, minHeight: 64 },
  miniatura: { width: 44, height: 44, borderRadius: radio.m, alignItems: 'center', justifyContent: 'center' },
  puntoBolsa: { width: 10, height: 10, borderRadius: 5 },
  radio: { width: 24, height: 24, borderRadius: 12, borderWidth: 2, alignItems: 'center', justifyContent: 'center' },
  radioPunto: { width: 12, height: 12, borderRadius: 6 },
  dia: { width: 58, height: 72, borderRadius: radio.m, borderWidth: 1, alignItems: 'center', justifyContent: 'center', gap: 2 },
  rejilla: { flexDirection: 'row', flexWrap: 'wrap', gap: espacio.s },
  bloque: {
    // Tres columnas iguales, siempre: la última fila no se estira.
    width: '31%',
    minHeight: 56,
    borderRadius: radio.m,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cerrado: { padding: espacio.xl, borderRadius: radio.l },
  cupo: { flexDirection: 'row', gap: espacio.s, alignItems: 'center', padding: espacio.m, borderRadius: radio.m, marginTop: espacio.l },
  barra: {
    position: 'absolute',
    left: 12,
    right: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacio.m,
    padding: espacio.l,
    borderRadius: radio.l,
    borderWidth: StyleSheet.hairlineWidth,
    shadowColor: '#000',
    shadowOpacity: 0.3,
    shadowRadius: 18,
    shadowOffset: { width: 0, height: 8 },
    elevation: 10,
  },
});

/** Hora en que termina un bloque: su inicio más la granularidad del calendario. */
function finDeBloque(bloque: Bloque, granularidad: number): string {
  return deMinutos(aMinutos(bloque.hora.slice(0, 5)) + granularidad);
}
