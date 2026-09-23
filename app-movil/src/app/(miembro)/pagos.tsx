import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import * as Haptics from 'expo-haptics';
import { router } from 'expo-router';
import { useState } from 'react';
import { Alert, Pressable, StyleSheet, View } from 'react-native';

import { Chip, Tarjeta } from '@/componentes/Base';
import { Boton } from '@/componentes/Boton';
import { EsqueletoLista } from '@/componentes/Esqueleto';
import { EstadoVacio } from '@/componentes/EstadoVacio';
import { AvisoSinConexion, ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { api, peticion } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { dinero, fechaCorta } from '@/lib/formato';
import { descargarYCompartir } from '@/lib/nativo';
import type { Cobro, Factura, OrdenPago, Paginado } from '@/lib/tipos';
import { espacio, radio, useTema } from '@/tema';

type Vista = 'pagos' | 'facturas' | 'cobros';

/** Historial de pagos y descarga de facturas. */
export default function Pagos() {
  const { p } = useTema();
  const [vista, setVista] = useState<Vista>('pagos');

  const pagos = useInfiniteQuery({
    queryKey: claves.pagos,
    queryFn: ({ pageParam }) => peticion<Paginado<OrdenPago>>('/pagos', { consulta: { cursor: pageParam } }),
    initialPageParam: '' as string,
    getNextPageParam: (u) => u.meta?.siguiente ?? undefined,
  });
  const facturas = useQuery({ queryKey: claves.facturas, queryFn: () => api<Factura[]>('/facturas'), enabled: vista === 'facturas' });
  const cobros = useInfiniteQuery({
    queryKey: claves.cobros,
    queryFn: ({ pageParam }) => peticion<Paginado<Cobro>>('/cobros', { consulta: { cursor: pageParam } }),
    initialPageParam: '' as string,
    getNextPageParam: (u) => u.meta?.siguiente ?? undefined,
    enabled: vista === 'cobros',
  });

  const consulta = vista === 'pagos' ? pagos : vista === 'facturas' ? facturas : cobros;

  return (
    <Pantalla conMargenSuperior={false} alRefrescar={() => consulta.refetch()}>
      <View style={[estilos.segmentos, { backgroundColor: p.superficie }]}>
        {(
          [
            ['pagos', 'Pagos'],
            ['facturas', 'Facturas'],
            ['cobros', 'Cargos'],
          ] as [Vista, string][]
        ).map(([v, t]) => (
          <Pressable
            key={v}
            onPress={() => {
              Haptics.selectionAsync().catch(() => {});
              setVista(v);
            }}
            style={[estilos.segmento, vista === v && { backgroundColor: p.superficieAlta }]}>
            <Texto variante="cuerpoFuerte" tono={vista === v ? 'normal' : 'tenue'}>
              {t}
            </Texto>
          </Pressable>
        ))}
      </View>

      <AvisoSinConexion consulta={consulta} />

      {vista === 'pagos' ? (
        <ListaPagos consulta={pagos} />
      ) : vista === 'facturas' ? (
        facturas.isPending ? (
          <EsqueletoLista />
        ) : facturas.isError && !facturas.data ? (
          <ErrorDeCarga mensaje="No pudimos cargar tus facturas." alReintentar={() => void facturas.refetch()} />
        ) : !facturas.data?.length ? (
          <EstadoVacio
            ilustracion="pagos"
            titulo="Aún no hay facturas"
            detalle="Cuando contabilidad emita una factura tuya, la descargas desde aquí."
            accion="Mis datos fiscales"
            alPulsar={() => router.push('/datos-fiscales')}
          />
        ) : (
          <View style={{ gap: espacio.m }}>
            {facturas.data.map((f) => (
              <TarjetaFactura key={f.id} factura={f} />
            ))}
          </View>
        )
      ) : cobros.isPending ? (
        <EsqueletoLista />
      ) : !cobros.data?.pages.flatMap((x) => x.data).length ? (
        <EstadoVacio ilustracion="pagos" titulo="Sin cargos registrados" detalle="Aquí aparecen los cargos que registra recepción." />
      ) : (
        <View style={{ gap: espacio.m }}>
          {cobros.data.pages
            .flatMap((x) => x.data)
            .map((c) => (
              <Tarjeta key={c.id}>
                <View style={estilos.fila}>
                  <Texto variante="cuerpoFuerte" style={{ flex: 1 }}>
                    {c.concepto ?? 'Cargo'}
                  </Texto>
                  <Texto variante="seccion">{dinero(c.total)}</Texto>
                </View>
                <Texto variante="pequeno" tono="suave">
                  {c.folio ? `Folio ${c.folio} · ` : ''}
                  {fechaCorta(c.fecha)} · {c.estatus}
                </Texto>
              </Tarjeta>
            ))}
          {cobros.hasNextPage ? (
            <Boton titulo="Ver más" variante="secundario" compacto alPulsar={() => void cobros.fetchNextPage()} />
          ) : null}
        </View>
      )}
    </Pantalla>
  );
}

function ListaPagos({ consulta }: { consulta: ReturnType<typeof useInfiniteQuery<Paginado<OrdenPago>>> }) {
  const { p } = useTema();
  const lista = consulta.data?.pages.flatMap((x) => (x as Paginado<OrdenPago>).data) ?? [];

  if (consulta.isPending) return <EsqueletoLista />;
  if (consulta.isError && !consulta.data)
    return <ErrorDeCarga mensaje="No pudimos cargar tus pagos." alReintentar={() => void consulta.refetch()} />;
  if (!lista.length) {
    return (
      <EstadoVacio
        ilustracion="pagos"
        titulo="Todavía no hay pagos"
        detalle="Cuando contrates o renueves un plan con referencia, lo verás aquí con su estado."
        accion="Ver planes"
        alPulsar={() => router.push('/contratar')}
      />
    );
  }

  return (
    <View style={{ gap: espacio.m }}>
      {lista.map((o) => {
        const pagado = o.estado_pago === 'pagada' || o.estado_pago === 'confirmada' || o.estado_pago === 'pagado';
        return (
          <Tarjeta key={o.id} alPulsar={() => router.push({ pathname: '/pago/[id]', params: { id: String(o.id) } })}>
            <View style={estilos.fila}>
              <Texto variante="cuerpoFuerte" style={{ flex: 1 }}>
                {o.plan ?? 'Pago'}
              </Texto>
              <Texto variante="seccion">{dinero(o.monto)}</Texto>
            </View>
            <Texto variante="pequeno" tono="suave">
              {o.metodo_etiqueta} · Ref. {o.referencia} · {fechaCorta(o.creada)}
            </Texto>
            <View style={{ flexDirection: 'row', gap: espacio.s, flexWrap: 'wrap' }}>
              <Chip texto={o.estado_pago_etiqueta} color={pagado ? p.bien : p.atencion} />
              {o.pide_factura ? <Chip texto={o.estado_factura_etiqueta} color={p.textoSuave} /> : null}
            </View>
          </Tarjeta>
        );
      })}
      {consulta.hasNextPage ? (
        <Boton titulo="Ver más" variante="secundario" compacto alPulsar={() => void consulta.fetchNextPage()} />
      ) : null}
    </View>
  );
}

function TarjetaFactura({ factura: f }: { factura: Factura }) {
  const [bajando, setBajando] = useState<'pdf' | 'xml' | null>(null);

  const bajar = async (tipo: 'pdf' | 'xml') => {
    setBajando(tipo);
    try {
      await descargarYCompartir(
        `/facturas/${f.id}/${tipo}`,
        `factura-${f.referencia}.${tipo}`,
        tipo === 'pdf' ? 'application/pdf' : 'application/xml',
      );
    } catch {
      Alert.alert('No se pudo descargar', 'Revisa tu conexión e inténtalo de nuevo.');
    } finally {
      setBajando(null);
    }
  };

  return (
    <Tarjeta>
      <View style={estilos.fila}>
        <Texto variante="cuerpoFuerte" style={{ flex: 1 }}>
          {f.plan ?? 'Factura'}
        </Texto>
        <Texto variante="seccion">{dinero(f.monto)}</Texto>
      </View>
      <Texto variante="pequeno" tono="suave">
        {f.folio_fiscal ? `Folio ${f.folio_fiscal.slice(0, 8)}… · ` : ''}Emitida {fechaCorta(f.emitida_en)}
      </Texto>
      <View style={{ flexDirection: 'row', gap: espacio.s }}>
        <Boton
          titulo="PDF"
          icono="descarga"
          compacto
          variante="secundario"
          ocupado={bajando === 'pdf'}
          alPulsar={() => void bajar('pdf')}
        />
        {f.tiene_xml ? (
          <Boton
            titulo="XML"
            icono="descarga"
            compacto
            variante="secundario"
            ocupado={bajando === 'xml'}
            alPulsar={() => void bajar('xml')}
          />
        ) : null}
      </View>
    </Tarjeta>
  );
}

const estilos = StyleSheet.create({
  segmentos: { flexDirection: 'row', borderRadius: radio.total, padding: 4, marginBottom: espacio.xl },
  segmento: { flex: 1, alignItems: 'center', paddingVertical: 10, borderRadius: radio.total },
  fila: { flexDirection: 'row', alignItems: 'center', gap: espacio.m },
});
