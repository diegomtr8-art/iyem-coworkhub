import { useQuery } from '@tanstack/react-query';
import { View } from 'react-native';
import Svg, { Rect } from 'react-native-svg';

import { Tarjeta, TituloSeccion } from '@/componentes/Base';
import { EsqueletoLista } from '@/componentes/Esqueleto';
import { EstadoVacio } from '@/componentes/EstadoVacio';
import { ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { BarraReporte, ExportarCsv, SelectorRango } from '@/componentes/Reportes';
import { Texto } from '@/componentes/Texto';
import { api } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { horas } from '@/lib/formato';
import { useRango } from '@/lib/rango';
import type { ReporteConsumo, ReporteOcupacion } from '@/lib/tipos';
import { colorDeBolsa, espacio, useTema } from '@/tema';

export default function Ocupacion() {
  const { p } = useTema();
  const { desde, hasta } = useRango();
  const ocupacion = useQuery({
    queryKey: claves.reporte('ocupacion', desde, hasta),
    queryFn: () => api<ReporteOcupacion>('/reportes/ocupacion', { consulta: { desde, hasta } }),
    meta: { persistir: false },
  });
  const consumo = useQuery({
    queryKey: claves.reporte('consumo', desde, hasta),
    queryFn: () => api<ReporteConsumo>('/reportes/consumo', { consulta: { desde, hasta } }),
    meta: { persistir: false },
  });
  const d = ocupacion.data;
  const maxFranja = Math.max(1, ...(d?.por_franja ?? []).map((f) => f.reservas));

  return (
    <Pantalla titulo="Ocupación" alRefrescar={() => Promise.all([ocupacion.refetch(), consumo.refetch()])}>
      <SelectorRango />
      {ocupacion.isPending ? (
        <EsqueletoLista filas={3} />
      ) : !d ? (
        <ErrorDeCarga mensaje="No pudimos cargar la ocupación." alReintentar={() => void ocupacion.refetch()} />
      ) : d.por_espacio.length === 0 ? (
        <EstadoVacio ilustracion="reportes" titulo="Sin reservas en el periodo" />
      ) : (
        <>
          <Tarjeta estilo={{ gap: espacio.xl }}>
            {d.por_espacio.map((e) => (
              <BarraReporte
                key={e.espacio}
                etiqueta={e.espacio}
                valor={`${e.ocupacion_pct}%`}
                fraccion={e.ocupacion_pct / 100}
                detalle={`${e.reservas} reservas · ${horas(e.horas)} de ${horas(e.capacidad_horas)} h`}
              />
            ))}
          </Tarjeta>

          {d.por_franja.length ? (
            <>
              <TituloSeccion>Horas más pedidas</TituloSeccion>
              <Tarjeta>
                <Svg width="100%" height={120} viewBox={`0 0 ${d.por_franja.length * 20} 120`} preserveAspectRatio="none">
                  {d.por_franja.map((f, i) => {
                    const alto = (f.reservas / maxFranja) * 110;
                    return (
                      <Rect
                        key={f.hora}
                        x={i * 20 + 3}
                        y={115 - alto}
                        width={14}
                        height={Math.max(2, alto)}
                        rx={4}
                        fill={f.reservas === maxFranja ? p.acentoGrafico : p.borde}
                      />
                    );
                  })}
                </Svg>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                  <Texto variante="pequeno" tono="tenue">
                    {d.por_franja[0]?.hora}
                  </Texto>
                  <Texto variante="pequeno" tono="tenue">
                    {d.por_franja[d.por_franja.length - 1]?.hora}
                  </Texto>
                </View>
              </Tarjeta>
            </>
          ) : null}

          {consumo.data?.filas?.length ? (
            <>
              <TituloSeccion>Aprovechamiento por plan</TituloSeccion>
              <Tarjeta estilo={{ gap: espacio.xl }}>
                {consumo.data.filas.map((c) => (
                  <BarraReporte
                    key={`${c.plan}-${c.bolsa}`}
                    etiqueta={`${c.plan} · ${c.bolsa}`}
                    valor={`${c.aprovechamiento_pct}%`}
                    fraccion={c.aprovechamiento_pct / 100}
                    color={colorDeBolsa(
                      p,
                      c.bolsa.toLowerCase().startsWith('sala')
                        ? 'sala'
                        : c.bolsa.toLowerCase().startsWith('cont')
                          ? 'contenido'
                          : 'asesoria',
                    )}
                    detalle={`${c.miembros} miembros · promedio ${horas(c.promedio_por_miembro)} de ${horas(c.incluidas_por_miembro)} h · ${c.al_tope} al tope`}
                  />
                ))}
              </Tarjeta>
            </>
          ) : null}
          <ExportarCsv reporte="ocupacion" />
        </>
      )}
    </Pantalla>
  );
}
