import { useQuery } from '@tanstack/react-query';
import { View } from 'react-native';

import { Dato, Separador, Tarjeta, TituloSeccion } from '@/componentes/Base';
import { EsqueletoLista } from '@/componentes/Esqueleto';
import { ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { BarraReporte, Cifra, ExportarCsv, SelectorRango } from '@/componentes/Reportes';
import { api } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { dinero } from '@/lib/formato';
import { useRango } from '@/lib/rango';
import type { ReporteIngresos } from '@/lib/tipos';
import { espacio, useTema } from '@/tema';

export default function Ingresos() {
  const { p } = useTema();
  const { desde, hasta } = useRango();
  const consulta = useQuery({
    queryKey: claves.reporte('ingresos', desde, hasta),
    queryFn: () => api<ReporteIngresos>('/reportes/ingresos', { consulta: { desde, hasta } }),
    meta: { persistir: false },
  });
  const d = consulta.data;
  const max = Math.max(1, ...(d?.por_plan ?? []).map((x) => x.ingreso));

  return (
    <Pantalla titulo="Ingresos" alRefrescar={() => consulta.refetch()}>
      <SelectorRango />
      {consulta.isPending ? (
        <EsqueletoLista filas={3} />
      ) : !d ? (
        <ErrorDeCarga mensaje="No pudimos cargar los ingresos." alReintentar={() => void consulta.refetch()} />
      ) : (
        <>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: espacio.m }}>
            <Cifra etiqueta="Membresías" valor={dinero(d.total_membresias)} color={p.lima} />
            <Cifra etiqueta="Facturado y pagado" valor={dinero(d.facturado_pagado)} />
          </View>

          <TituloSeccion>Por plan</TituloSeccion>
          <Tarjeta estilo={{ gap: espacio.xl }}>
            {d.por_plan.map((x) => (
              <BarraReporte
                key={x.plan}
                etiqueta={x.plan}
                valor={dinero(x.ingreso)}
                fraccion={x.ingreso / max}
                color={p.lima}
                detalle={`${x.membresias} membresías`}
              />
            ))}
          </Tarjeta>

          <TituloSeccion>Salones de eventos</TituloSeccion>
          <Tarjeta estilo={{ gap: 0 }}>
            <Dato etiqueta="Eventos" valor={String(d.salones.eventos)} />
            <Separador />
            <Dato etiqueta="Ingreso cotizado" valor={dinero(d.salones.ingreso)} />
            <Separador />
            <Dato etiqueta="Cobrado (anticipos)" valor={dinero(d.salones.cobrado)} />
          </Tarjeta>
          <ExportarCsv reporte="ingresos" />
        </>
      )}
    </Pantalla>
  );
}
