import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { Tarjeta, TituloSeccion } from '@/componentes/Base';
import { EsqueletoLista } from '@/componentes/Esqueleto';
import { EstadoVacio } from '@/componentes/EstadoVacio';
import { AvisoSinConexion, ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { api } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { fechaCorta } from '@/lib/formato';
import type { Aviso, Comunicado } from '@/lib/tipos';
import { espacio, useTema } from '@/tema';

export default function Avisos() {
  const { p } = useTema();
  const cliente = useQueryClient();
  const consulta = useQuery({
    queryKey: claves.avisos,
    queryFn: () => api<{ anuncios: Aviso[]; comunicados: Comunicado[] }>('/avisos'),
  });
  const [abierto, setAbierto] = useState<number | null>(null);

  const abrir = (c: Comunicado) => {
    setAbierto(abierto === c.id ? null : c.id);
    if (!c.leido) {
      api(`/avisos/comunicados/${c.id}/leido`, { metodo: 'POST' })
        .then(() => cliente.invalidateQueries({ queryKey: claves.avisos }))
        .catch(() => {});
    }
  };

  const d = consulta.data;
  const vacio = d && d.anuncios.length === 0 && d.comunicados.length === 0;

  return (
    <Pantalla conMargenSuperior={false} alRefrescar={() => consulta.refetch()}>
      <AvisoSinConexion consulta={consulta} />
      {consulta.isPending ? (
        <EsqueletoLista filas={3} />
      ) : !d ? (
        <ErrorDeCarga mensaje="No pudimos cargar los avisos." alReintentar={() => void consulta.refetch()} />
      ) : vacio ? (
        <EstadoVacio ilustracion="avisos" titulo="Todo tranquilo" detalle="Cuando Nódico tenga algo que contarte, aparecerá aquí." />
      ) : (
        <>
          {d.comunicados.length > 0 ? (
            <>
              <TituloSeccion>Para ti</TituloSeccion>
              <View style={{ gap: espacio.m }}>
                {d.comunicados.map((c) => (
                  <Pressable key={c.id} onPress={() => abrir(c)}>
                    <Tarjeta acento={c.leido ? null : p.acento}>
                      <View style={estilos.fila}>
                        <Texto variante="cuerpoFuerte" style={{ flex: 1 }}>
                          {c.titulo}
                        </Texto>
                        {!c.leido ? <View style={[estilos.punto, { backgroundColor: p.acento }]} /> : null}
                      </View>
                      <Texto variante="pequeno" tono="suave" numberOfLines={abierto === c.id ? undefined : 2}>
                        {c.mensaje}
                      </Texto>
                      <Texto variante="pequeno" tono="tenue">
                        {fechaCorta(c.fecha ?? c.creado ?? null)}
                      </Texto>
                    </Tarjeta>
                  </Pressable>
                ))}
              </View>
            </>
          ) : null}
          {d.anuncios.length > 0 ? (
            <>
              <TituloSeccion>Del coworking</TituloSeccion>
              <View style={{ gap: espacio.m }}>
                {d.anuncios.map((a) => (
                  <Tarjeta key={a.id}>
                    <Texto variante="cuerpoFuerte">{a.titulo}</Texto>
                    <Texto variante="pequeno" tono="suave">
                      {a.contenido}
                    </Texto>
                    <Texto variante="pequeno" tono="tenue">
                      Desde el {fechaCorta(a.desde)}
                    </Texto>
                  </Tarjeta>
                ))}
              </View>
            </>
          ) : null}
        </>
      )}
    </Pantalla>
  );
}

const estilos = StyleSheet.create({
  fila: { flexDirection: 'row', alignItems: 'center', gap: espacio.s },
  punto: { width: 10, height: 10, borderRadius: 5 },
});
