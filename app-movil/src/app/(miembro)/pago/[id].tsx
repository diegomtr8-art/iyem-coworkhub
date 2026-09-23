import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as Clipboard from 'expo-clipboard';
import * as Haptics from 'expo-haptics';
import { useLocalSearchParams } from 'expo-router';
import { Alert, Pressable, StyleSheet, View } from 'react-native';

import { Chip, Dato, Separador, Tarjeta, TituloSeccion } from '@/componentes/Base';
import { Boton } from '@/componentes/Boton';
import { EsqueletoLista } from '@/componentes/Esqueleto';
import { Icono } from '@/componentes/Icono';
import { ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { api, ErrorApi } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { dinero, fechaCorta } from '@/lib/formato';
import type { OrdenPago } from '@/lib/tipos';
import { espacio, radio, useTema } from '@/tema';

/** Una orden con referencia: el número a pagar, cómo pagarlo y «ya pagué». */
export default function DetallePago() {
  const { p } = useTema();
  const { id } = useLocalSearchParams<{ id: string }>();
  const cliente = useQueryClient();
  const consulta = useQuery({ queryKey: claves.pago(Number(id)), queryFn: () => api<OrdenPago>(`/pagos/${id}`) });

  const yaPague = useMutation({
    mutationFn: () => api<{ message?: string }>(`/pagos/${id}/ya-pague`, { metodo: 'POST' }),
    onSuccess: (r) => {
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
      void cliente.invalidateQueries({ queryKey: claves.pagos });
      Alert.alert('Gracias', r?.message ?? 'Avisamos a contabilidad. En cuanto confirmen tu pago, se activa tu membresía.');
    },
    onError: (e) => Alert.alert('No se pudo', e instanceof ErrorApi ? e.primero : 'Inténtalo de nuevo.'),
  });

  const copiar = async (texto: string, que: string) => {
    await Clipboard.setStringAsync(texto);
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
    Alert.alert('Copiado', `${que} copiado.`);
  };

  const o = consulta.data;

  return (
    <Pantalla conMargenSuperior={false} alRefrescar={() => consulta.refetch()}>
      {consulta.isPending ? (
        <EsqueletoLista filas={2} />
      ) : !o ? (
        <ErrorDeCarga mensaje="No encontramos ese pago." alReintentar={() => void consulta.refetch()} />
      ) : (
        <>
          <View style={[estilos.referencia, { backgroundColor: p.acento }]}>
            <Texto variante="etiqueta" tono="sobreAcento">
              Tu referencia
            </Texto>
            <Pressable
              onPress={() => void copiar(o.referencia, 'La referencia')}
              style={{ flexDirection: 'row', alignItems: 'center', gap: espacio.s }}>
              <Texto variante="numero" tono="sobreAcento" selectable>
                {o.referencia}
              </Texto>
              <Icono nombre="documento" color={p.sobreAcento} tamano={20} />
            </Pressable>
            <Texto variante="subtitulo" tono="sobreAcento">
              {dinero(o.monto)}
            </Texto>
            <Texto variante="pequeno" tono="sobreAcento">
              {o.plan} · {o.metodo_etiqueta}
              {o.vence_el ? ` · vence el ${fechaCorta(o.vence_el)}` : ''}
            </Texto>
          </View>

          <View style={{ flexDirection: 'row', gap: espacio.s, marginTop: espacio.l, flexWrap: 'wrap' }}>
            <Chip texto={o.estado_pago_etiqueta} color={p.atencion} />
            {o.pide_factura ? <Chip texto={o.estado_factura_etiqueta} color={p.textoSuave} /> : null}
          </View>

          {o.datos_bancarios ? (
            <>
              <TituloSeccion>Transfiere a</TituloSeccion>
              <Tarjeta estilo={{ gap: 0 }}>
                <Dato etiqueta="Banco" valor={o.datos_bancarios.banco} />
                <Separador />
                <Pressable onPress={() => void copiar(o.datos_bancarios!.clabe, 'La CLABE')}>
                  <Dato etiqueta="CLABE (toca para copiar)" valor={o.datos_bancarios.clabe} />
                </Pressable>
                <Separador />
                <Dato etiqueta="Beneficiario" valor={o.datos_bancarios.beneficiario} />
                {o.datos_bancarios.cuenta ? (
                  <>
                    <Separador />
                    <Dato etiqueta="Cuenta" valor={o.datos_bancarios.cuenta} />
                  </>
                ) : null}
              </Tarjeta>
              <Texto variante="pequeno" tono="suave" style={{ marginTop: espacio.m }}>
                Escribe la referencia en el concepto de la transferencia: así sabemos que es tuya.
              </Texto>
            </>
          ) : (
            <Texto tono="suave" style={{ marginTop: espacio.xl }}>
              Paga en efectivo en la caja de Nódico diciendo tu referencia.
            </Texto>
          )}

          {Array.isArray(o.instrucciones) && o.instrucciones.length ? (
            <View style={{ gap: espacio.s, marginTop: espacio.l }}>
              {o.instrucciones.map((i) => (
                <Texto key={i} variante="pequeno" tono="suave">
                  · {i}
                </Texto>
              ))}
            </View>
          ) : null}

          {o.estado_pago === 'generada' ? (
            <Boton
              titulo={o.reportado ? 'Ya avisaste que pagaste' : 'Ya pagué'}
              deshabilitado={o.reportado}
              ocupado={yaPague.isPending}
              alPulsar={() => yaPague.mutate()}
              estilo={{ marginTop: espacio.xxl }}
            />
          ) : null}
          <Texto variante="pequeno" tono="tenue" centrado style={{ marginTop: espacio.l }}>
            Tu membresía se activa cuando contabilidad confirma el pago.
          </Texto>
        </>
      )}
    </Pantalla>
  );
}

const estilos = StyleSheet.create({
  referencia: { borderRadius: radio.xl, padding: espacio.xl, gap: espacio.s },
});
