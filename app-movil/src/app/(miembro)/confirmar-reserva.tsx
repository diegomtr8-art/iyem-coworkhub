import { useQueryClient } from '@tanstack/react-query';
import * as Crypto from 'expo-crypto';
import * as Haptics from 'expo-haptics';
import { router, useLocalSearchParams } from 'expo-router';
import { useRef, useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import Animated, { FadeIn, ZoomIn } from 'react-native-reanimated';

import { Dato, Separador } from '@/componentes/Base';
import { Boton } from '@/componentes/Boton';
import { Icono } from '@/componentes/Icono';
import { Texto } from '@/componentes/Texto';
import { api, ErrorApi } from '@/lib/api';
import { diaRelativo, horas } from '@/lib/formato';
import { anadirAlCalendario, calendarioDisponible, programarRecordatorioLocal, registrarPushTrasPrimeraReserva } from '@/lib/nativo';
import type { ReservaCreada } from '@/lib/tipos';
import { colorDeBolsa, espacio, radio, useTema } from '@/tema';

/**
 * Resumen antes de confirmar, con cuánto le quedará después. Al confirmar, el
 * servidor vuelve a aplicar todas las reglas; si alguna falla, su texto exacto
 * se muestra aquí.
 */
export default function ConfirmarReserva() {
  const { p } = useTema();
  const cliente = useQueryClient();
  const q = useLocalSearchParams<{
    espacio_id: string;
    espacio: string;
    bolsa: string;
    bolsa_etiqueta: string;
    fecha: string;
    hora_inicio: string;
    hora_fin: string;
    horas: string;
    saldo: string;
  }>();
  // Una sola clave para esta reserva: si la red se corta y se reintenta, el
  // servidor devuelve la misma reserva en lugar de crear otra.
  const clave = useRef(Crypto.randomUUID());
  const [enviando, setEnviando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hecha, setHecha] = useState<ReservaCreada | null>(null);

  const horasNum = Number(q.horas);
  const saldo = q.saldo === '' || q.saldo === undefined ? null : Number(q.saldo);
  const quedara = saldo === null ? null : Math.max(0, Math.round((saldo - horasNum) * 100) / 100);
  const color = colorDeBolsa(p, q.bolsa);

  const confirmar = async () => {
    setEnviando(true);
    setError(null);
    try {
      const reserva = await api<ReservaCreada>('/reservas', {
        metodo: 'POST',
        idempotencia: clave.current,
        cuerpo: { espacio_id: Number(q.espacio_id), fecha: q.fecha, hora_inicio: q.hora_inicio, hora_fin: q.hora_fin },
      });
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
      setHecha(reserva);
      void cliente.invalidateQueries({ queryKey: ['inicio'] });
      void cliente.invalidateQueries({ queryKey: ['reservas'] });
      void cliente.invalidateQueries({ queryKey: ['disponibilidad'] });
      void cliente.invalidateQueries({ queryKey: ['membresia'] });
      await registrarPushTrasPrimeraReserva();
      void programarRecordatorioLocal({ id: reserva.reserva.id, espacio: q.espacio, fecha: q.fecha, inicio: q.hora_inicio });
    } catch (e) {
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error).catch(() => {});
      setError(e instanceof ErrorApi ? e.primero : 'No pudimos confirmar la reserva.');
      // Un rechazo del servidor es definitivo para estos datos: el siguiente
      // intento es otra petición, con otra clave.
      if (e instanceof ErrorApi && !e.sinConexion) clave.current = Crypto.randomUUID();
    } finally {
      setEnviando(false);
    }
  };

  if (hecha) {
    const restante = hecha.bolsa_despues?.restante;
    return (
      <View style={[estilos.hoja, { alignItems: 'center' }]}>
        <Animated.View entering={ZoomIn.springify().damping(12)} style={[estilos.exito, { backgroundColor: p.acento }]}>
          <Icono nombre="check" color={p.sobreAcento} tamano={44} grosor={3} />
        </Animated.View>
        <Animated.View entering={FadeIn.delay(150)} style={{ alignItems: 'center', gap: espacio.s }}>
          <Texto variante="titulo" centrado>
            ¡Listo! Te esperamos
          </Texto>
          <Texto tono="suave" centrado>
            {q.espacio} · {diaRelativo(q.fecha)} de {q.hora_inicio} a {q.hora_fin}
          </Texto>
          {restante !== undefined && restante !== null ? (
            <Texto variante="cuerpoFuerte" centrado style={{ marginTop: espacio.s }}>
              Te quedan{' '}
              <Texto variante="cuerpoFuerte" color={color}>
                {horas(restante)} h
              </Texto>{' '}
              de {q.bolsa_etiqueta.toLowerCase()}
            </Texto>
          ) : null}
        </Animated.View>
        <View style={{ alignSelf: 'stretch', gap: espacio.m, marginTop: espacio.xl }}>
          {calendarioDisponible ? (
            <Boton
              titulo="Añadir a mi calendario"
              icono="calendario"
              variante="secundario"
              alPulsar={() =>
                void anadirAlCalendario({
                  titulo: `Reserva en Nódico · ${q.espacio}`,
                  fecha: q.fecha,
                  inicio: q.hora_inicio,
                  fin: q.hora_fin,
                }).catch(() => {})
              }
            />
          ) : null}
          <Boton titulo="Hecho" alPulsar={() => router.back()} />
        </View>
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={estilos.hoja}>
      <Texto variante="etiqueta" tono="tenue">
        Revisa tu reserva
      </Texto>
      <Texto variante="titulo">{q.espacio}</Texto>
      <View style={[estilos.horario, { backgroundColor: p.fondo }]}>
        <Icono nombre="calendario" color={color} />
        <View>
          <Texto variante="cuerpoFuerte">{diaRelativo(q.fecha)}</Texto>
          <Texto tono="suave">
            {q.hora_inicio} a {q.hora_fin}
          </Texto>
        </View>
        <View style={{ flex: 1 }} />
        <Texto variante="numero" color={color}>
          {horas(horasNum)} h
        </Texto>
      </View>

      <View>
        <Dato etiqueta="Se descuenta de" valor={q.bolsa_etiqueta} />
        <Separador />
        {saldo !== null ? (
          <>
            <Dato etiqueta="Tienes ahora" valor={`${horas(saldo)} h`} />
            <Separador />
            <Dato etiqueta="Te quedarán" valor={`${horas(quedara)} h`} fuerte />
          </>
        ) : (
          <Dato etiqueta="Tu bolsa" valor="Ilimitada" />
        )}
      </View>

      <Texto variante="pequeno" tono="tenue">
        Si cancelas con más de 2 horas de anticipación, las horas vuelven a tu bolsa.
      </Texto>

      {error ? (
        <View style={[estilos.error, { borderColor: p.problema }]}>
          <Icono nombre="alerta" color={p.problema} tamano={20} />
          <Texto variante="pequeno" style={{ flex: 1 }}>
            {error}
          </Texto>
        </View>
      ) : null}

      <Boton titulo="Confirmar reserva" alPulsar={confirmar} ocupado={enviando} haptica={false} />
      <Boton titulo="Cambiar horario" variante="fantasma" alPulsar={() => router.back()} />
    </ScrollView>
  );
}

const estilos = StyleSheet.create({
  hoja: { padding: espacio.xl, paddingTop: espacio.xxl, gap: espacio.l },
  horario: { flexDirection: 'row', alignItems: 'center', gap: espacio.m, padding: espacio.l, borderRadius: radio.l },
  error: { flexDirection: 'row', gap: espacio.s, alignItems: 'center', padding: espacio.m, borderRadius: radio.m, borderWidth: 1 },
  exito: { width: 96, height: 96, borderRadius: 48, alignItems: 'center', justifyContent: 'center', marginTop: espacio.l },
});
