import { useQuery, useQueryClient } from '@tanstack/react-query';
import * as Crypto from 'expo-crypto';
import * as Haptics from 'expo-haptics';
import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';
import { router, useLocalSearchParams } from 'expo-router';
import { useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Switch, View } from 'react-native';

import { Boton } from '@/componentes/Boton';
import { Icono, type NombreIcono } from '@/componentes/Icono';
import { Texto } from '@/componentes/Texto';
import { api, ErrorApi } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { dinero } from '@/lib/formato';
import { useEstadoServidor } from '@/lib/ganchos';
import type { Contratables, EnlacePagoWeb, OrdenPago } from '@/lib/tipos';
import { espacio, radio, useTema } from '@/tema';

type Metodo = 'tarjeta' | 'transferencia' | 'efectivo';

/**
 * Elegir cómo pagar, con la consecuencia de cada opción escrita antes de
 * pulsar. **La app nunca activa nada** y **nunca cobra con tarjeta dentro de
 * la app** (decisión de Diego, 29-sep-2026): la tarjeta se paga en la página
 * web de Nódico, en el navegador del teléfono, y la membresía la activa el
 * servidor cuando la pasarela confirma el cobro. Con referencia, caja al
 * confirmar el pago.
 */
export default function Pagar() {
  const { p } = useTema();
  const cliente = useQueryClient();
  const { plan_id } = useLocalSearchParams<{ plan_id: string }>();
  const { data } = useQuery({ queryKey: claves.contratables, queryFn: () => api<Contratables>('/planes/contratables') });
  const estado = useEstadoServidor();
  const plan = data?.planes.find((x) => String(x.id) === plan_id);
  const tarjetaDisponible =
    !!data?.metodos.tarjeta && plan?.tarjeta_disponible !== false && estado.data?.pagos?.tarjeta !== false;
  const referenciaDisponible = (data?.metodos.referencia?.length ?? 0) > 0 && estado.data?.pagos?.referencia !== false;

  const [metodo, setMetodo] = useState<Metodo>(tarjetaDisponible ? 'tarjeta' : 'transferencia');
  const [factura, setFactura] = useState(false);
  const [fase, setFase] = useState<'elegir' | 'procesando' | 'listo' | 'pendiente'>('elegir');
  const [error, setError] = useState<string | null>(null);
  const [faltanFiscales, setFaltanFiscales] = useState(false);
  const clave = useRef(Crypto.randomUUID());

  const pagarConReferencia = async () => {
    setFase('procesando');
    setError(null);
    try {
      const orden = await api<OrdenPago>('/pagos/referencia', {
        metodo: 'POST',
        idempotencia: clave.current,
        cuerpo: { plan_id: Number(plan_id), metodo, pide_factura: factura },
      });
      void cliente.invalidateQueries({ queryKey: claves.pagos });
      router.replace({ pathname: '/pago/[id]', params: { id: String(orden.id) } });
    } catch (e) {
      setFase('elegir');
      if (e instanceof ErrorApi && e.codigo === 'datos_fiscales_incompletos') setFaltanFiscales(true);
      setError(e instanceof ErrorApi ? e.primero : 'No pudimos generar la referencia.');
      if (e instanceof ErrorApi && !e.sinConexion) clave.current = Crypto.randomUUID();
    }
  };

  /**
   * Tarjeta: en la página de pago de Nódico, en el navegador del teléfono.
   *
   * La app pide un enlace de un solo uso (vence en 5 minutos) que abre la
   * sesión web sin volver a pedir la contraseña y lleva directo a pagar este
   * plan. Al terminar, la página regresa a la app (`vuelta`) diciendo cómo
   * salió. Si la persona cierra el navegador antes, no pasa nada: si pagó, el
   * servidor lo confirma solo.
   */
  const pagarConTarjeta = async () => {
    setFase('procesando');
    setError(null);
    try {
      const vuelta = Linking.createURL('regreso-banco');
      const enlace = await api<EnlacePagoWeb>('/pagos/tarjeta/enlace', {
        metodo: 'POST',
        cuerpo: { plan_id: Number(plan_id), vuelta },
      });

      const resultado = await WebBrowser.openAuthSessionAsync(enlace.url, vuelta);
      const como = resultado.type === 'success' ? Linking.parse(resultado.url).queryParams?.resultado : null;

      void cliente.invalidateQueries();

      if (como === 'ok') {
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
        setFase('listo');
      } else if (como === 'fallido') {
        setFase('elegir');
        setError('No se completó el pago. Puedes intentarlo otra vez o pagar por referencia.');
      } else if (como === 'pendiente') {
        setFase('pendiente');
      } else {
        // Cerró el navegador sin terminar (o antes de que la página regresara).
        setFase('elegir');
      }
    } catch (e) {
      setFase('elegir');
      setError(e instanceof ErrorApi ? e.primero : 'No pudimos abrir el pago. Revisa tu conexión e inténtalo de nuevo.');
    }
  };

  if (fase === 'listo' || fase === 'pendiente') {
    return (
      <View style={[estilos.hoja, { alignItems: 'center', justifyContent: 'center', flex: 1 }]}>
        <View style={[estilos.circulo, { backgroundColor: fase === 'listo' ? p.acento : p.superficieAlta }]}>
          <Icono
            nombre={fase === 'listo' ? 'check' : 'reloj'}
            color={fase === 'listo' ? p.sobreAcento : p.acentoTexto}
            tamano={40}
            grosor={2.6}
          />
        </View>
        <Texto variante="titulo" centrado>
          {fase === 'listo' ? '¡Bienvenido!' : 'Tu pago sigue pendiente'}
        </Texto>
        <Texto tono="suave" centrado>
          {fase === 'listo'
            ? 'Tu pago quedó registrado. Si tu membresía aún no aparece activa, se actualizará en unos minutos.'
            : 'Tu banco todavía no confirma el pago. Si ya lo autorizaste, en unos minutos se activa solo: no hace falta pagar otra vez.'}
        </Texto>
        <Boton titulo="Ir al inicio" alPulsar={() => router.dismissTo('/')} estilo={{ alignSelf: 'stretch', marginTop: espacio.xl }} />
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={estilos.hoja}>
      <Texto variante="etiqueta" tono="tenue">
        Pagar
      </Texto>
      <Texto variante="titulo">{plan?.nombre ?? 'Plan'}</Texto>
      <Texto variante="numero" tono="acento">
        {dinero(plan?.precio)}{' '}
        <Texto variante="cuerpo" tono="suave">
          {plan?.periodo_label ?? plan?.periodo_etiqueta ?? ''}
        </Texto>
      </Texto>

      <View style={{ gap: espacio.m, marginTop: espacio.m }}>
        {/* La tarjeta siempre se enseña: si no está disponible, se dice por
            qué en vez de desaparecer sin explicación. */}
        <Opcion
          icono="tarjeta"
          titulo="Tarjeta"
          detalle={
            tarjetaDisponible
              ? plan?.recurrente && plan?.renueva_sola
                ? 'Pagas en nuestra página web y se renueva sola cada mes. Sin factura.'
                : plan?.recurrente
                  ? 'Pagas este periodo en nuestra página web; te avisamos antes de que venza. Sin factura.'
                  : 'Pagas en nuestra página web y se activa al instante. Sin factura.'
              : 'El pago con tarjeta no está disponible por ahora. Paga con transferencia o en caja.'
          }
          activa={metodo === 'tarjeta'}
          deshabilitada={!tarjetaDisponible}
          alPulsar={() => setMetodo('tarjeta')}
        />
        {referenciaDisponible ? (
          <>
            <Opcion
              icono="banco"
              titulo="Transferencia"
              detalle="Te damos una referencia. Se activa cuando contabilidad confirma el pago. Con factura si la pides."
              activa={metodo === 'transferencia'}
              alPulsar={() => setMetodo('transferencia')}
            />
            <Opcion
              icono="dinero"
              titulo="Efectivo en caja"
              detalle="Pagas en recepción con tu referencia. Con factura si la pides."
              activa={metodo === 'efectivo'}
              alPulsar={() => setMetodo('efectivo')}
            />
          </>
        ) : null}
      </View>

      {metodo !== 'tarjeta' ? (
        <View style={[estilos.factura, { backgroundColor: p.fondo }]}>
          <View style={{ flex: 1 }}>
            <Texto variante="cuerpoFuerte">Quiero factura</Texto>
            <Texto variante="pequeno" tono="suave">
              {data?.tiene_datos_fiscales ? 'Con tus datos fiscales guardados.' : 'Necesitas tus datos fiscales completos.'}
            </Texto>
          </View>
          <Switch value={factura} onValueChange={setFactura} trackColor={{ true: p.acento, false: p.pista }} />
        </View>
      ) : null}

      {error ? (
        <View style={[estilos.error, { borderColor: p.problema }]}>
          <Texto variante="pequeno">{error}</Texto>
          {faltanFiscales ? (
            <Texto variante="pequeno" tono="acento" onPress={() => router.push('/datos-fiscales')}>
              Completar mis datos fiscales
            </Texto>
          ) : null}
        </View>
      ) : null}

      <Boton
        titulo={metodo === 'tarjeta' ? `Pagar ${dinero(plan?.precio)} en la web` : 'Generar referencia'}
        ocupado={fase === 'procesando'}
        deshabilitado={!plan}
        alPulsar={metodo === 'tarjeta' ? pagarConTarjeta : pagarConReferencia}
      />
      <Texto variante="pequeno" tono="tenue" centrado>
        {metodo === 'tarjeta'
          ? 'Se abre la página de pago de Nódico. Al terminar, vuelves aquí.'
          : 'Te mandamos la referencia por correo.'}
      </Texto>
    </ScrollView>
  );
}

function Opcion({
  icono,
  titulo,
  detalle,
  activa,
  deshabilitada,
  alPulsar,
}: {
  icono: NombreIcono;
  titulo: string;
  detalle: string;
  activa: boolean;
  deshabilitada?: boolean;
  alPulsar: () => void;
}) {
  const { p } = useTema();
  return (
    <Pressable
      onPress={alPulsar}
      disabled={deshabilitada}
      accessibilityRole="radio"
      accessibilityState={{ checked: activa, disabled: !!deshabilitada }}
      style={[
        estilos.opcion,
        { backgroundColor: p.fondo, borderColor: activa ? p.acentoTexto : p.borde, opacity: deshabilitada ? 0.6 : 1 },
      ]}>
      <Icono nombre={icono} color={activa ? p.acentoTexto : p.textoSuave} />
      <View style={{ flex: 1, gap: 2 }}>
        <Texto variante="cuerpoFuerte">{titulo}</Texto>
        <Texto variante="pequeno" tono="suave">
          {detalle}
        </Texto>
      </View>
      <View style={[estilos.radio, { borderColor: activa ? p.acentoTexto : p.textoTenue }]}>
        {activa ? <View style={[estilos.radioPunto, { backgroundColor: p.acento }]} /> : null}
      </View>
    </Pressable>
  );
}

const estilos = StyleSheet.create({
  hoja: { padding: espacio.xl, paddingTop: espacio.xxl, gap: espacio.l, paddingBottom: 60 },
  opcion: { flexDirection: 'row', alignItems: 'center', gap: espacio.m, padding: espacio.l, borderRadius: radio.l, borderWidth: 1.5 },
  radio: { width: 22, height: 22, borderRadius: 11, borderWidth: 2, alignItems: 'center', justifyContent: 'center' },
  radioPunto: { width: 10, height: 10, borderRadius: 5 },
  factura: { flexDirection: 'row', alignItems: 'center', gap: espacio.m, padding: espacio.l, borderRadius: radio.l },
  error: { padding: espacio.m, borderRadius: radio.m, borderWidth: 1, gap: espacio.s },
  circulo: { width: 88, height: 88, borderRadius: 44, alignItems: 'center', justifyContent: 'center', marginBottom: espacio.l },
});
