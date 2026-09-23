import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { Alert, StyleSheet, View } from 'react-native';

import { BannerEstado } from '@/componentes/BannerEstado';
import { FilaBolsa } from '@/componentes/Anillo';
import { Chip, Fila, Grupo, Tarjeta, TituloSeccion } from '@/componentes/Base';
import { Boton } from '@/componentes/Boton';
import { Campo } from '@/componentes/Campo';
import { EsqueletoLista } from '@/componentes/Esqueleto';
import { EstadoVacio } from '@/componentes/EstadoVacio';
import { Icono } from '@/componentes/Icono';
import { AvisoSinConexion, ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { api, ErrorApi } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { diaMes, dinero, fechaCorta } from '@/lib/formato';
import type { Membresia as DatosMembresia } from '@/lib/tipos';
import { espacio, radio, useTema } from '@/tema';

export default function Membresia() {
  const { p } = useTema();
  const consulta = useQuery({ queryKey: claves.membresia, queryFn: () => api<DatosMembresia>('/membresia') });
  const d = consulta.data;
  const esAcompanante = d?.rol_en_membresia === 'acompanante';
  const v = d?.vigente;
  const color = v?.plan?.color || p.acento;

  return (
    <Pantalla titulo="Membresía" alRefrescar={() => consulta.refetch()}>
      <AvisoSinConexion consulta={consulta} />
      {consulta.isPending ? (
        <EsqueletoLista filas={3} />
      ) : !d ? (
        <ErrorDeCarga mensaje="No pudimos cargar tu membresía." alReintentar={() => void consulta.refetch()} />
      ) : (
        <>
          <BannerEstado estado={d.estado} alAccion={esAcompanante ? undefined : () => router.push('/contratar')} />

          {!v ? (
            <EstadoVacio
              ilustracion="membresia"
              titulo="Todavía no tienes membresía"
              detalle="Elige un plan y empieza a reservar salas, estudios y asesoría desde aquí."
              accion="Ver planes"
              alPulsar={() => router.push('/contratar')}
            />
          ) : (
            <>
              <View style={[estilos.plan, { backgroundColor: color }]}>
                <Texto variante="etiqueta" tono="sobreAcento">
                  {esAcompanante ? `Acompañante de ${d.titular?.nombre ?? 'tu titular'}` : 'Tu plan'}
                </Texto>
                <Texto variante="titulo" tono="sobreAcento">
                  {v.plan?.nombre}
                </Texto>
                <View style={estilos.vigencia}>
                  <View>
                    <Texto variante="gigante" tono="sobreAcento">
                      {v.dias_restantes}
                    </Texto>
                    <Texto variante="cuerpoFuerte" tono="sobreAcento">
                      {v.dias_restantes === 1 ? 'día restante' : 'días restantes'}
                    </Texto>
                  </View>
                  <View style={{ alignItems: 'flex-end', gap: 2 }}>
                    <Texto variante="pequeno" tono="sobreAcento">
                      Vence el
                    </Texto>
                    <Texto variante="seccion" tono="sobreAcento">
                      {diaMes(v.fecha_fin)}
                    </Texto>
                  </View>
                </View>
              </View>

              {d.bolsas.length > 0 ? (
                <>
                  <TituloSeccion>{esAcompanante ? 'Bolsa compartida' : 'Tus bolsas'}</TituloSeccion>
                  <Tarjeta estilo={{ gap: 0, paddingVertical: espacio.m }}>
                    {d.bolsas
                      .filter((b) => b.incluida && !(b.bolsa === 'dias' && b.ilimitada))
                      .map((b, i) => (
                        <FilaBolsa key={b.bolsa} bolsa={b} retraso={i * 120} />
                      ))}
                    <Texto variante="pequeno" tono="tenue" style={{ marginTop: espacio.s }}>
                      {[
                        d.bolsas.some((b) => b.bolsa === 'dias' && b.incluida && b.ilimitada) ? 'Coworking ilimitado' : null,
                        v.proximo_reinicio ? `Se reinician el ${diaMes(v.proximo_reinicio)}` : null,
                        esAcompanante ? 'Tope diario por persona' : null,
                      ]
                        .filter(Boolean)
                        .join(' · ')}
                    </Texto>
                  </Tarjeta>
                </>
              ) : null}

              {v.plan?.incluye?.length ? (
                <>
                  <TituloSeccion>Qué incluye</TituloSeccion>
                  <Tarjeta estilo={{ gap: espacio.m }}>
                    {v.plan.incluye.map((linea) => (
                      <View key={linea} style={estilos.linea}>
                        <Icono nombre="check" color={color} tamano={18} />
                        <Texto variante="pequeno" style={{ flex: 1 }}>
                          {linea}
                        </Texto>
                      </View>
                    ))}
                  </Tarjeta>
                </>
              ) : null}

              {d.acompanante.admitido && !esAcompanante ? <Acompanante datos={d} /> : null}

              {d.renovacion && !esAcompanante ? <Renovacion datos={d} /> : null}
            </>
          )}

          <TituloSeccion>Más</TituloSeccion>
          <Grupo>
            {!esAcompanante ? (
              <Fila
                icono="membresia"
                titulo={v ? 'Renovar o cambiar de plan' : 'Contratar un plan'}
                alPulsar={() => router.push('/contratar')}
              />
            ) : null}
            <Fila
              icono="personas"
              titulo="Asesoría IYEM"
              detalle="Temas, asesores y tus solicitudes"
              alPulsar={() => router.push('/asesoria')}
            />
            <Fila icono="dinero" titulo="Pagos y facturas" alPulsar={() => router.push('/pagos')} />
            <Fila icono="lista" titulo="Mis reservas" alPulsar={() => router.push('/reservas')} />
          </Grupo>

          {d.historial.length > 0 ? (
            <>
              <TituloSeccion>Historial</TituloSeccion>
              <Grupo>
                {d.historial.map((h) => (
                  <Fila
                    key={h.id}
                    titulo={h.plan ?? 'Plan'}
                    detalle={`${fechaCorta(h.fecha_inicio)} – ${fechaCorta(h.fecha_fin)} · ${h.estatus}`}
                  />
                ))}
              </Grupo>
            </>
          ) : null}
        </>
      )}
    </Pantalla>
  );
}

function Acompanante({ datos }: { datos: DatosMembresia }) {
  const { p } = useTema();
  const cliente = useQueryClient();
  const [correo, setCorreo] = useState('');
  const [error, setError] = useState<string | null>(null);
  const actual = datos.acompanante.usuario;

  const asignar = useMutation({
    mutationFn: () => api<{ message?: string }>('/membresia/acompanante', { metodo: 'POST', cuerpo: { email: correo.trim() } }),
    onSuccess: (r) => {
      setCorreo('');
      setError(null);
      void cliente.invalidateQueries({ queryKey: claves.membresia });
      if (r?.message) Alert.alert('Listo', r.message);
    },
    onError: (e) => setError(e instanceof ErrorApi ? e.primero : 'No pudimos asignarlo.'),
  });

  const quitar = useMutation({
    mutationFn: () => api('/membresia/acompanante', { metodo: 'DELETE' }),
    onSuccess: () => void cliente.invalidateQueries({ queryKey: claves.membresia }),
    onError: (e) => Alert.alert('No se pudo', e instanceof ErrorApi ? e.primero : 'Inténtalo de nuevo.'),
  });

  return (
    <>
      <TituloSeccion>Tu acompañante</TituloSeccion>
      <Tarjeta>
        {actual ? (
          <>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: espacio.m }}>
              <View style={[estilos.avatar, { backgroundColor: p.morado }]}>
                <Texto variante="seccion" color="#fff">
                  {actual.nombre.charAt(0).toUpperCase()}
                </Texto>
              </View>
              <View style={{ flex: 1 }}>
                <Texto variante="cuerpoFuerte">{actual.nombre}</Texto>
                <Texto variante="pequeno" tono="suave">
                  {actual.email}
                </Texto>
              </View>
            </View>
            <Chip
              texto={datos.acompanante.face_id_ok ? 'Rostro registrado' : 'Falta registrar su rostro en recepción'}
              color={datos.acompanante.face_id_ok ? p.bien : p.atencion}
            />
            <Texto variante="pequeno" tono="tenue">
              Comparten la bolsa de horas; cada quien con su propio tope diario.
            </Texto>
            <Boton
              titulo="Quitar acompañante"
              variante="peligro"
              compacto
              ocupado={quitar.isPending}
              alPulsar={() =>
                Alert.alert('¿Quitar a tu acompañante?', `${actual.nombre} dejará de usar tu membresía.`, [
                  { text: 'No', style: 'cancel' },
                  { text: 'Quitar', style: 'destructive', onPress: () => quitar.mutate() },
                ])
              }
            />
          </>
        ) : (
          <>
            <Texto tono="suave">
              Tu plan es para dos. Escribe el correo de la persona que te acompaña; tiene que tener cuenta en Nódico con el correo
              verificado.
            </Texto>
            <Campo
              etiqueta="Correo de tu acompañante"
              value={correo}
              onChangeText={setCorreo}
              autoCapitalize="none"
              keyboardType="email-address"
              error={error}
            />
            <Boton
              titulo="Asignar"
              compacto
              ocupado={asignar.isPending}
              deshabilitado={!correo.includes('@')}
              alPulsar={() => asignar.mutate()}
            />
          </>
        )}
      </Tarjeta>
    </>
  );
}

function Renovacion({ datos }: { datos: DatosMembresia }) {
  const { p } = useTema();
  const cliente = useQueryClient();
  const r = datos.renovacion!;

  const accion = useMutation({
    mutationFn: (que: 'cancelar' | 'reactivar') => api<{ message?: string }>(`/membresia/renovacion/${que}`, { metodo: 'POST' }),
    onSuccess: (res) => {
      void cliente.invalidateQueries({ queryKey: claves.membresia });
      if (res?.message) Alert.alert('Listo', res.message);
    },
    onError: (e) => Alert.alert('No se pudo', e instanceof ErrorApi ? e.primero : 'Inténtalo de nuevo.'),
  });

  if (!r.tiene_recurrente && !r.metodo_pago) return null;

  return (
    <>
      <TituloSeccion>Renovación</TituloSeccion>
      <Tarjeta>
        {r.metodo_pago ? (
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: espacio.m }}>
            <Icono nombre="tarjeta" color={p.textoSuave} />
            <Texto>
              {r.metodo_pago.marca.toUpperCase()} terminada en {r.metodo_pago.ultimos4}
            </Texto>
          </View>
        ) : null}
        {r.tiene_recurrente ? (
          r.renovacion_activa && !r.en_periodo_de_gracia ? (
            <>
              <Texto variante="pequeno" tono="suave">
                Se renueva sola al terminar el periodo. Si la cancelas, sigues con acceso hasta el fin de lo que ya pagaste.
              </Texto>
              <Boton
                titulo="Cancelar renovación"
                variante="peligro"
                compacto
                ocupado={accion.isPending}
                alPulsar={() =>
                  Alert.alert('¿Cancelar la renovación automática?', 'Sigues con acceso hasta el fin del periodo pagado.', [
                    { text: 'No', style: 'cancel' },
                    { text: 'Cancelar renovación', style: 'destructive', onPress: () => accion.mutate('cancelar') },
                  ])
                }
              />
            </>
          ) : r.en_periodo_de_gracia ? (
            <>
              <Texto variante="pequeno" tono="suave">
                Cancelaste la renovación. Puedes reactivarla mientras dure tu periodo pagado.
              </Texto>
              <Boton titulo="Reactivar renovación" compacto ocupado={accion.isPending} alPulsar={() => accion.mutate('reactivar')} />
            </>
          ) : null
        ) : null}
        <Texto variante="pequeno" tono="tenue">
          Pagado: {dinero(datos.vigente?.precio_pagado)}
        </Texto>
      </Tarjeta>
    </>
  );
}

const estilos = StyleSheet.create({
  plan: { borderRadius: radio.xl, padding: espacio.xl, gap: espacio.xs },
  vigencia: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end', marginTop: espacio.l },
  pista: { height: 10, borderRadius: 5, overflow: 'hidden' },
  relleno: { height: '100%', borderRadius: 5 },
  linea: { flexDirection: 'row', gap: espacio.s, alignItems: 'flex-start' },
  avatar: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center' },
  historial: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacio.m,
    paddingVertical: espacio.m,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  punto: { width: 10, height: 10, borderRadius: 5 },
});
