import { useQuery, useQueryClient } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { ScrollView, StyleSheet, View } from 'react-native';

import { Dato, Separador } from '@/componentes/Base';
import { Boton } from '@/componentes/Boton';
import { EsqueletoTarjeta } from '@/componentes/Esqueleto';
import { Icono } from '@/componentes/Icono';
import { ErrorDeCarga } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { api } from '@/lib/api';
import { diaRelativo, hora, horas } from '@/lib/formato';
import { useAhora } from '@/lib/ganchos';
import { anadirAlCalendario, calendarioDisponible, compartirTexto } from '@/lib/nativo';
import { textoDeCancelacion, useCancelarReserva } from '@/lib/reservas';
import type { Reserva } from '@/lib/tipos';
import { colorDeBolsa, espacio, radio, useTema } from '@/tema';

/** Detalle de una reserva en una hoja deslizante, no en una pantalla nueva. */
export default function DetalleReserva() {
  const { p } = useTema();
  const { id } = useLocalSearchParams<{ id: string }>();
  const cliente = useQueryClient();

  // Si venimos de una lista, la reserva ya está en caché: se pinta al instante.
  const inicial = [...(cliente.getQueryData<Reserva[]>(['reservas', 'proximas']) ?? [])].find((r) => String(r.id) === id);

  const consulta = useQuery({
    queryKey: ['reservas', 'detalle', id],
    queryFn: () => api<Reserva>(`/reservas/${id}`),
    initialData: inicial,
  });
  const { pedir, cancelando } = useCancelarReserva(() => router.back());
  const ahora = useAhora();
  const r = consulta.data;

  if (!r) {
    return (
      <View style={estilos.hoja}>
        {consulta.isError ? (
          <ErrorDeCarga mensaje="No encontramos esa reserva." alReintentar={() => void consulta.refetch()} />
        ) : (
          <EsqueletoTarjeta lineas={4} />
        )}
      </View>
    );
  }

  const color = colorDeBolsa(p, r.bolsa ?? '');
  const confirmada = r.estatus === 'Confirmada' && new Date(r.termina_en).getTime() > ahora;

  return (
    <ScrollView contentContainerStyle={estilos.hoja}>
      <View style={[estilos.icono, { backgroundColor: color }]}>
        <Icono nombre="reservar" color={p.sobreAcento} tamano={28} />
      </View>
      <Texto variante="titulo">{r.espacio.nombre}</Texto>
      <Texto tono="suave">
        {diaRelativo(r.fecha)} · {hora(r.hora_inicio)} a {hora(r.hora_fin)}
      </Texto>

      <View style={{ marginTop: espacio.m }}>
        <Dato etiqueta="Duración" valor={`${horas(r.horas)} h`} />
        <Separador />
        <Dato etiqueta="Bolsa" valor={r.bolsa_etiqueta ?? '—'} />
        <Separador />
        <Dato etiqueta="Estado" valor={r.estatus === 'No_Show' ? 'No asististe' : r.estatus} />
      </View>

      {confirmada ? (
        <>
          <View style={[estilos.nota, { backgroundColor: p.fondo }]}>
            <Texto variante="pequeno" tono="suave">
              Si la cancelas ahora: {textoDeCancelacion(r)}
            </Texto>
          </View>
          <View style={{ gap: espacio.m }}>
            {calendarioDisponible ? (
              <Boton
                titulo="Añadir a mi calendario"
                icono="calendario"
                variante="secundario"
                alPulsar={() =>
                  void anadirAlCalendario({
                    titulo: `Reserva en Nódico · ${r.espacio.nombre}`,
                    fecha: r.fecha,
                    inicio: hora(r.hora_inicio),
                    fin: hora(r.hora_fin),
                  }).catch(() => {})
                }
              />
            ) : null}
            <Boton
              titulo="Compartir"
              icono="compartir"
              variante="secundario"
              alPulsar={() =>
                void compartirTexto(
                  `Nos vemos en Nódico: ${r.espacio.nombre}, ${diaRelativo(r.fecha).toLowerCase()} de ${hora(r.hora_inicio)} a ${hora(r.hora_fin)}. Instituto Yucateco de Emprendedores, Mérida.`,
                )
              }
            />
            <Boton titulo="Cancelar reserva" variante="peligro" ocupado={cancelando} alPulsar={() => pedir(r)} />
          </View>
        </>
      ) : null}
    </ScrollView>
  );
}

const estilos = StyleSheet.create({
  hoja: { padding: espacio.xl, paddingTop: espacio.xxl, gap: espacio.m },
  icono: { width: 56, height: 56, borderRadius: radio.m, alignItems: 'center', justifyContent: 'center', marginBottom: espacio.s },
  nota: { padding: espacio.l, borderRadius: radio.m, marginVertical: espacio.m },
});
