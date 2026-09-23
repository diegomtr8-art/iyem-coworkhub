import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Alert, View } from 'react-native';

import { FilaBolsa } from '@/componentes/Anillo';
import { Chip, Fila, Grupo, Tarjeta, TituloSeccion } from '@/componentes/Base';
import { EsqueletoLista } from '@/componentes/Esqueleto';
import { EstadoVacio } from '@/componentes/EstadoVacio';
import { AvisoSinConexion, ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { api, ErrorApi } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { diaMes, diaRelativo, dinero } from '@/lib/formato';
import type { Asesorias } from '@/lib/tipos';
import { colorDeTono, espacio, useTema } from '@/tema';

/**
 * Asesoría IYEM. Esto es una **solicitud**, no una reserva: la persona dice qué
 * tema y cuándo le viene bien, y recepción confirma día y asesor.
 */
export default function Asesoria() {
  const { p } = useTema();
  const cliente = useQueryClient();
  const consulta = useQuery({ queryKey: claves.asesorias, queryFn: () => api<Asesorias>('/asesorias') });
  const d = consulta.data;

  const cancelar = useMutation({
    mutationFn: (id: number) => api(`/asesorias/${id}/cancelar`, { metodo: 'POST' }),
    onSuccess: () => {
      void cliente.invalidateQueries({ queryKey: claves.asesorias });
      void cliente.invalidateQueries({ queryKey: claves.inicio });
    },
    onError: (e) => Alert.alert('No se pudo cancelar', e instanceof ErrorApi ? e.primero : 'Inténtalo de nuevo.'),
  });

  return (
    <Pantalla conMargenSuperior={false} alRefrescar={() => consulta.refetch()}>
      <AvisoSinConexion consulta={consulta} />
      {consulta.isPending ? (
        <EsqueletoLista filas={3} />
      ) : !d ? (
        <ErrorDeCarga mensaje="No pudimos cargar la asesoría." alReintentar={() => void consulta.refetch()} />
      ) : !d.incluida ? (
        <EstadoVacio
          ilustracion="asesoria"
          titulo="Tu plan no incluye asesoría"
          detalle={
            d.plan_que_la_incluye
              ? `${d.plan_que_la_incluye.nombre} incluye asesoría con especialistas del IYEM por ${dinero(d.plan_que_la_incluye.precio)}.`
              : 'Algunos planes incluyen asesoría con especialistas del IYEM.'
          }
          accion="Ver planes"
          alPulsar={() => router.push('/contratar')}
        />
      ) : (
        <>
          {d.bolsa ? (
            <Tarjeta estilo={{ gap: 0, paddingVertical: espacio.m }}>
              <FilaBolsa
                bolsa={{
                  bolsa: 'asesoria',
                  etiqueta: 'Horas de asesoría',
                  unidad: 'horas',
                  incluida: true,
                  ilimitada: false,
                  cupo: d.bolsa.cupo,
                  usado: d.bolsa.usado,
                  restante: d.bolsa.restante,
                  porcentaje_usado: 0,
                  tope_diario: d.bolsa.tope_diario,
                  reinicia_el: d.bolsa.reinicia_el,
                  reinicia_texto: null,
                  casi_agotada: false,
                  agotada: d.bolsa.restante !== null && d.bolsa.restante <= 0,
                }}
              />
              {d.bolsa.reinicia_el ? (
                <Texto variante="pequeno" tono="tenue" style={{ marginTop: espacio.s }}>
                  Se reinicia el {diaMes(d.bolsa.reinicia_el)}
                </Texto>
              ) : null}
            </Tarjeta>
          ) : null}

          {d.solicitudes.length > 0 ? (
            <>
              <TituloSeccion>Tus solicitudes</TituloSeccion>
              <View style={{ gap: espacio.m }}>
                {d.solicitudes.map((s) => (
                  <Tarjeta key={s.id} acento={colorDeTono(p, s.tono)}>
                    <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: espacio.m }}>
                      <Texto variante="cuerpoFuerte" style={{ flex: 1 }}>
                        {s.tema}
                      </Texto>
                      <Chip texto={s.estado_etiqueta} color={colorDeTono(p, s.tono)} />
                    </View>
                    <Texto variante="pequeno" tono="suave">
                      {s.fecha_confirmada
                        ? `Confirmada: ${diaRelativo(s.fecha_confirmada.slice(0, 10))}${s.asesor ? ` con ${s.asesor}` : ''}`
                        : `Pediste ${diaRelativo(s.dia_preferido).toLowerCase()} · ${s.horario_preferido}`}
                    </Texto>
                    {s.notas ? (
                      <Texto variante="pequeno" tono="tenue">
                        {s.notas}
                      </Texto>
                    ) : null}
                    {s.pendiente ? (
                      <Texto
                        variante="cuerpoFuerte"
                        tono="problema"
                        accessibilityRole="button"
                        style={{ paddingVertical: espacio.s, alignSelf: 'flex-start' }}
                        onPress={() =>
                          Alert.alert('¿Cancelar la solicitud?', s.tema, [
                            { text: 'No', style: 'cancel' },
                            {
                              text: 'Cancelar solicitud',
                              style: 'destructive',
                              onPress: () => cancelar.mutate(s.id),
                            },
                          ])
                        }>
                        Cancelar solicitud
                      </Texto>
                    ) : null}
                  </Tarjeta>
                ))}
              </View>
            </>
          ) : null}

          <TituloSeccion>Temas disponibles</TituloSeccion>
          <Texto variante="pequeno" tono="tenue" style={{ marginBottom: espacio.m }}>
            Elige un tema y dinos cuándo te viene bien. Recepción te confirma día y asesor.
          </Texto>
          {d.oferta.map((grupo) => (
            <View key={grupo.categoria} style={{ marginBottom: espacio.l }}>
              <Texto variante="cuerpoFuerte" tono="suave" style={{ marginBottom: espacio.s }}>
                {grupo.etiqueta}
              </Texto>
              <Grupo>
                {grupo.temas.map((t) => (
                  <Fila
                    key={t.id}
                    titulo={t.nombre}
                    detalle={[
                      t.descripcion_corta,
                      t.asesores.length ? `Con ${t.asesores.map((a) => a.nombre.split(' ')[0]).join(', ')}` : null,
                    ]
                      .filter(Boolean)
                      .join('\n')}
                    alPulsar={() => router.push({ pathname: '/solicitar-asesoria', params: { tema_id: String(t.id) } })}
                  />
                ))}
              </Grupo>
            </View>
          ))}
        </>
      )}
    </Pantalla>
  );
}

