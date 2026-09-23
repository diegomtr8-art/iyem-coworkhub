import { useQuery } from '@tanstack/react-query';
import { Linking, View } from 'react-native';

import { Tarjeta, TituloSeccion } from '@/componentes/Base';
import { EsqueletoLista } from '@/componentes/Esqueleto';
import { EstadoVacio } from '@/componentes/EstadoVacio';
import { ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { Cifra, ExportarCsv, SelectorRango } from '@/componentes/Reportes';
import { Texto } from '@/componentes/Texto';
import { api } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { fechaCorta, horas } from '@/lib/formato';
import { useRango } from '@/lib/rango';
import type { MiembroEnRiesgo, ReporteNoShow } from '@/lib/tipos';
import { espacio, useTema } from '@/tema';

/**
 * Faltas y miembros en riesgo. Traen datos de contacto de personas: no se
 * guardan en la caché del teléfono y cada consulta queda en la bitácora.
 */
export default function Miembros() {
  const { p } = useTema();
  const { desde, hasta } = useRango();
  const noShow = useQuery({
    queryKey: claves.reporte('no-show', desde, hasta),
    queryFn: () => api<ReporteNoShow>('/reportes/no-show', { consulta: { desde, hasta } }),
    meta: { persistir: false },
  });
  const riesgo = useQuery({
    queryKey: claves.reporte('en-riesgo', '', ''),
    queryFn: () => api<MiembroEnRiesgo[]>('/reportes/en-riesgo'),
    meta: { persistir: false },
  });
  const n = noShow.data;

  return (
    <Pantalla titulo="Miembros" alRefrescar={() => Promise.all([noShow.refetch(), riesgo.refetch()])}>
      <SelectorRango />
      {noShow.isPending ? (
        <EsqueletoLista filas={2} />
      ) : !n ? (
        <ErrorDeCarga mensaje="No pudimos cargar las faltas." alReintentar={() => void noShow.refetch()} />
      ) : (
        <>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: espacio.m }}>
            <Cifra
              etiqueta="Tasa de no-show"
              valor={`${n.tasa_pct}%`}
              color={p.coral}
              detalle={`${n.no_show} de ${n.total_reservas} reservas`}
            />
            <Cifra etiqueta="Horas perdidas" valor={`${horas(n.horas_perdidas)} h`} />
          </View>
          {n.por_miembro.length ? (
            <>
              <TituloSeccion>Quién falta más</TituloSeccion>
              <Tarjeta estilo={{ gap: espacio.m }}>
                {n.por_miembro.slice(0, 10).map((m) => (
                  <View key={m.miembro} style={{ flexDirection: 'row', justifyContent: 'space-between', gap: espacio.m }}>
                    <Texto style={{ flex: 1 }} numberOfLines={1}>
                      {m.miembro}
                    </Texto>
                    <Texto variante="cuerpoFuerte" color={p.coral}>
                      {m.faltas} · {horas(m.horas)} h
                    </Texto>
                  </View>
                ))}
              </Tarjeta>
            </>
          ) : null}
          <ExportarCsv reporte="no_show" />
        </>
      )}

      <TituloSeccion>En riesgo de irse</TituloSeccion>
      {riesgo.isPending ? (
        <EsqueletoLista filas={2} />
      ) : !riesgo.data?.length ? (
        <EstadoVacio ilustracion="reportes" titulo="Nadie en riesgo" detalle="Todos los miembros activos han venido recientemente." />
      ) : (
        <View style={{ gap: espacio.m }}>
          {riesgo.data.map((m) => (
            <Tarjeta key={`${m.miembro}-${m.email}`}>
              <Texto variante="cuerpoFuerte">{m.miembro}</Texto>
              <Texto variante="pequeno" tono="suave">
                {m.plan ?? 'Sin plan'} · vence {fechaCorta(m.vence)} ·{' '}
                {m.dias_sin_venir === null ? 'nunca ha venido' : `${m.dias_sin_venir} días sin venir`}
              </Texto>
              <View style={{ flexDirection: 'row', gap: espacio.l }}>
                {m.telefono ? (
                  <Texto variante="pequeno" tono="acento" onPress={() => void Linking.openURL(`tel:${m.telefono}`)}>
                    Llamar
                  </Texto>
                ) : null}
                {m.email ? (
                  <Texto variante="pequeno" tono="acento" onPress={() => void Linking.openURL(`mailto:${m.email}`)}>
                    Escribir
                  </Texto>
                ) : null}
              </View>
            </Tarjeta>
          ))}
          <ExportarCsv reporte="en_riesgo" />
        </View>
      )}
    </Pantalla>
  );
}
