import { handleNextAction, initPaymentSheet, initStripe, presentPaymentSheet } from '@/lib/stripe';
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
import type { Contratables, EstadoCobroTarjeta, OrdenPago, PreparacionTarjeta, ResultadoSuscripcion } from '@/lib/tipos';
import { espacio, marca, radio, useTema } from '@/tema';

type Metodo = 'tarjeta' | 'transferencia' | 'efectivo';

const esperar = (ms: number) => new Promise((r) => setTimeout(r, ms));

/** Estados del cargo de BBVA con los que ya no hay nada que esperar. */
const CARGO_TERMINADO = ['fallido', 'cancelado', 'abandonado', 'en_revision', 'devuelto', 'desconocido'];

/**
 * Elegir cómo pagar, con la consecuencia de cada opción escrita antes de
 * pulsar. **La app nunca activa nada**: con tarjeta, lo activa el servidor
 * cuando la pasarela confirma el cobro (webhook de Stripe, o la consulta del
 * cargo a BBVA); con referencia, caja al confirmar el pago.
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
  const conBanco = (data?.metodos.pasarela ?? estado.data?.pagos?.pasarela) === 'bbva';
  const referenciaDisponible = (data?.metodos.referencia?.length ?? 0) > 0 && estado.data?.pagos?.referencia !== false;

  const [metodo, setMetodo] = useState<Metodo>(tarjetaDisponible ? 'tarjeta' : 'transferencia');
  const [factura, setFactura] = useState(false);
  const [fase, setFase] = useState<'elegir' | 'procesando' | 'confirmando' | 'listo' | 'pendiente'>('elegir');
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
   * Tarjeta. Según la pasarela del servidor:
   *
   * - **BBVA** (`modo: redireccion`): se abre el formulario del banco en el
   *   navegador del sistema. Ahí se teclea la tarjeta y se pasa el 3-D Secure;
   *   BBVA regresa a Nódico, que devuelve a la app (`vuelta`). Luego se pregunta
   *   por el cargo hasta que el servidor lo confirme con la API del banco.
   * - **Stripe**: la hoja de pago nativa (ver `pagarConStripe`).
   */
  const pagarConTarjeta = async () => {
    setFase('procesando');
    setError(null);
    try {
      const prep = await api<PreparacionTarjeta>('/pagos/tarjeta', {
        metodo: 'POST',
        idempotencia: clave.current,
        // A dónde vuelve la app desde el banco. Con Stripe se ignora.
        cuerpo: { plan_id: Number(plan_id), vuelta: Linking.createURL('regreso-banco') },
      });

      if (prep.modo === 'redireccion') {
        await pagarEnElBanco(prep.url, prep.cargo_id);
        return;
      }

      await pagarConStripe(prep);
    } catch (e) {
      setFase('elegir');
      setError(e instanceof ErrorApi ? e.primero : e instanceof Error ? e.message : 'No pudimos procesar el pago.');
      clave.current = Crypto.randomUUID();
    }
  };

  /**
   * BBVA. Cerrar el navegador a medias no es un error: el pago puede haberse
   * autorizado igual. Se pregunta al servidor y él decide con lo que diga el
   * banco. Si sigue pendiente, se dice claro que no hace falta pagar otra vez.
   */
  const pagarEnElBanco = async (url: string, cargoId: number) => {
    await WebBrowser.openAuthSessionAsync(url, Linking.createURL('regreso-banco'));

    setFase('confirmando');
    let ultimo: EstadoCobroTarjeta | null = null;
    for (let i = 0; i < 12; i++) {
      try {
        ultimo = await api<EstadoCobroTarjeta>(`/pagos/tarjeta/estado?cargo_id=${cargoId}`);
        if (ultimo.activa || (ultimo.estado && CARGO_TERMINADO.includes(ultimo.estado))) break;
      } catch {
        // Se sigue esperando.
      }
      await esperar(2500);
    }

    clave.current = Crypto.randomUUID();
    if (ultimo?.activa) {
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
      void cliente.invalidateQueries();
      setFase('listo');
    } else if (ultimo?.estado && CARGO_TERMINADO.includes(ultimo.estado)) {
      setFase('elegir');
      setError(ultimo.mensaje ?? 'No se completó el pago.');
    } else {
      setFase('pendiente');
    }
  };

  /**
   * Stripe, con la hoja de pago nativa. Dos caminos, según el plan:
   *
   * - **Recurrente** (`tipo_intent: setup`): la hoja guarda la tarjeta con un
   *   SetupIntent; luego el servidor crea la suscripción con ella. Si el banco
   *   pide 3-D Secure, el servidor devuelve `requiere_accion` y la app lo
   *   resuelve con `handleNextAction`.
   * - **Pago único** (`tipo_intent: payment`): la hoja confirma el PaymentIntent.
   *
   * En los dos casos, después se espera a que el webhook active la membresía.
   */
  const pagarConStripe = async (prep: Extract<PreparacionTarjeta, { tipo_intent: string }>) => {
    const retorno = Linking.createURL('stripe-redirect');
    await initStripe({
      publishableKey: prep.llave_publica || estado.data?.pagos?.llave_publica || '',
      urlScheme: retorno,
      merchantIdentifier: 'merchant.mx.com.nodico',
    });

    const esSetup = prep.tipo_intent === 'setup' || prep.client_secret.startsWith('seti_');
    const base = {
      merchantDisplayName: prep.nombre_comercio || 'Nódico',
      returnURL: retorno,
      appearance: {
        colors: {
          primary: marca.amarillo,
          background: p.superficie,
          componentBackground: p.fondo,
          primaryText: p.texto,
          secondaryText: p.textoSuave,
          placeholderText: p.textoTenue,
          componentText: p.texto,
          icon: p.textoSuave,
        },
        primaryButton: { colors: { background: marca.amarillo, text: marca.tinta } },
        shapes: { borderRadius: 14 },
      },
    };
    const init = await initPaymentSheet(
      esSetup ? { ...base, setupIntentClientSecret: prep.client_secret } : { ...base, paymentIntentClientSecret: prep.client_secret },
    );
    if (init.error) throw new Error(init.error.message);

    const res = await presentPaymentSheet();
    if (res.error) {
      setFase('elegir');
      // Cerrar la hoja no es un error.
      if (res.error.code !== 'Canceled') setError(res.error.message);
      clave.current = Crypto.randomUUID();
      return;
    }

    if (esSetup) {
      // «seti_XXX_secret_YYY» → el id del SetupIntent es lo de antes de «_secret_».
      const setupIntentId = prep.client_secret.split('_secret_')[0];
      const sus = await api<ResultadoSuscripcion>('/pagos/tarjeta/suscribir', {
        metodo: 'POST',
        // Clave fija por SetupIntent: si la red se corta y se reintenta con la
        // misma tarjeta guardada, el servidor devuelve la misma respuesta en
        // vez de crear una segunda suscripción.
        idempotencia: `suscribir-${setupIntentId}`,
        cuerpo: { plan_id: Number(plan_id), setup_intent_id: setupIntentId },
      });
      if (sus.requiere_accion && sus.client_secret) {
        const accion = await handleNextAction(sus.client_secret, retorno);
        if (accion.error) throw new Error(accion.error.message);
      }
    }

    // El cobro salió; ahora se espera a que el webhook active la membresía.
    setFase('confirmando');
    for (let i = 0; i < 20; i++) {
      await esperar(2500);
      try {
        const r = await api<{ activa: boolean }>('/pagos/tarjeta/estado');
        if (r.activa) break;
      } catch {
        // Se sigue esperando.
      }
    }
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
    void cliente.invalidateQueries();
    setFase('listo');
  };

  if (fase === 'confirmando' || fase === 'listo' || fase === 'pendiente') {
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
          {fase === 'listo' ? '¡Bienvenido!' : fase === 'pendiente' ? 'Tu pago sigue pendiente' : 'Confirmando tu pago…'}
        </Texto>
        <Texto tono="suave" centrado>
          {fase === 'listo'
            ? 'Tu pago quedó registrado. Si tu membresía aún no aparece activa, se actualizará en unos minutos.'
            : fase === 'pendiente'
              ? 'Tu banco todavía no confirma el pago. Si ya lo autorizaste, en unos minutos se activa solo: no hace falta pagar otra vez.'
              : 'Tu banco nos está confirmando el cobro. No cierres esta pantalla.'}
        </Texto>
        {fase === 'listo' || fase === 'pendiente' ? (
          <Boton titulo="Ir al inicio" alPulsar={() => router.dismissTo('/')} estilo={{ alignSelf: 'stretch', marginTop: espacio.xl }} />
        ) : null}
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
              ? plan?.recurrente && plan?.renueva_sola === false
                ? 'Pagas este periodo; te avisamos antes de que venza. Sin factura.'
                : 'Se activa al instante. Sin factura.'
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
        titulo={metodo === 'tarjeta' ? `Pagar ${dinero(plan?.precio)}` : 'Generar referencia'}
        ocupado={fase === 'procesando'}
        deshabilitado={!plan}
        alPulsar={metodo === 'tarjeta' ? pagarConTarjeta : pagarConReferencia}
      />
      <Texto variante="pequeno" tono="tenue" centrado>
        {conBanco
          ? 'Pagas en el formulario seguro de BBVA; tu tarjeta nunca pasa por Nódico.'
          : 'Los datos de tu tarjeta van directo a Stripe; nunca pasan por Nódico.'}
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
