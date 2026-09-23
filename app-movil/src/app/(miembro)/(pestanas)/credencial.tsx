import { useFocusEffect } from 'expo-router';
import { onlineManager } from '@tanstack/react-query';
import * as Brightness from 'expo-brightness';
import { Image } from 'expo-image';
import { useCallback, useEffect, useState } from 'react';
import { Alert, Pressable, StyleSheet, useWindowDimensions, View } from 'react-native';
import QRCode from 'react-native-qrcode-svg';

import { Chip } from '@/componentes/Base';
import { Hueso } from '@/componentes/Esqueleto';
import { Icono } from '@/componentes/Icono';
import { Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { almacen } from '@/lib/almacen';
import { api, ErrorApi } from '@/lib/api';
import { fechaCorta, haceCuanto, primerNombre } from '@/lib/formato';
import { useAhora } from '@/lib/ganchos';
import type { Credencial as DatosCredencial } from '@/lib/tipos';
import { espacio, marca, radio, useTema } from '@/tema';

type Guardada = DatosCredencial & { guardada_en: number };

/**
 * La credencial: el QR a pantalla completa, con el brillo al máximo para que
 * el lector de recepción lo lea a la primera.
 *
 * **Funciona sin conexión**: se guarda en el almacén seguro del teléfono y se
 * refresca cuando hay red. Quien llega sin datos sigue pudiendo identificarse;
 * la validez real la decide recepción al escanear, contra el servidor.
 */
export default function Credencial() {
  const { p } = useTema();
  const { width } = useWindowDimensions();
  const [credencial, setCredencial] = useState<Guardada | null>(null);
  const [cargando, setCargando] = useState(true);
  const [sinRed, setSinRed] = useState(false);
  const [renovando, setRenovando] = useState(false);
  const ahora = useAhora(60_000);

  const cargar = useCallback(async () => {
    try {
      const nueva = await api<DatosCredencial>('/credencial');
      const guardada = { ...nueva, guardada_en: Date.now() };
      setCredencial(guardada);
      setSinRed(false);
      await almacen.guardarCredencial(guardada);
    } catch (e) {
      if (e instanceof ErrorApi && e.sinConexion) setSinRed(true);
    } finally {
      setCargando(false);
    }
  }, []);

  // Primero lo guardado (al instante, con o sin red); luego lo fresco.
  useEffect(() => {
    (async () => {
      const guardada = await almacen.credencial<Guardada>();
      if (guardada) {
        setCredencial(guardada);
        setCargando(false);
      }
      if (onlineManager.isOnline()) await cargar();
      else {
        setSinRed(true);
        setCargando(false);
      }
    })();
  }, [cargar]);

  // Brillo al máximo mientras la credencial está en pantalla; al salir, como estaba.
  useFocusEffect(
    useCallback(() => {
      let previo: number | null = null;
      let vivo = true;
      (async () => {
        try {
          previo = await Brightness.getBrightnessAsync();
          if (vivo) await Brightness.setBrightnessAsync(1);
        } catch {
          // Sin control de brillo, se ve igual.
        }
      })();
      return () => {
        vivo = false;
        if (previo !== null) Brightness.setBrightnessAsync(previo).catch(() => {});
      };
    }, []),
  );

  const renovar = () => {
    Alert.alert('¿Generar un código nuevo?', 'El código actual deja de servir. Úsalo si alguien vio o fotografió tu credencial.', [
      { text: 'No', style: 'cancel' },
      {
        text: 'Generar nuevo',
        style: 'destructive',
        onPress: async () => {
          setRenovando(true);
          try {
            const nueva = await api<DatosCredencial>('/credencial/renovar', { metodo: 'POST' });
            const guardada = { ...nueva, guardada_en: Date.now() };
            setCredencial(guardada);
            await almacen.guardarCredencial(guardada);
          } catch (e) {
            Alert.alert('No se pudo renovar', e instanceof ErrorApi ? e.primero : 'Inténtalo con conexión.');
          } finally {
            setRenovando(false);
          }
        },
      },
    ]);
  };

  const lado = Math.min(width - espacio.xl * 2 - espacio.xl * 2, 320);
  const colorPlan = credencial?.color_plan || p.acento;
  const vencida = credencial?.valida_hasta ? new Date(credencial.valida_hasta).getTime() < ahora : false;

  return (
    <Pantalla titulo="Credencial" alRefrescar={cargar}>
      {cargando && !credencial ? (
        <View style={[estilos.tarjeta, { backgroundColor: '#fff', alignItems: 'center' }]}>
          <Hueso ancho={lado} alto={lado} redondo={radio.m} />
        </View>
      ) : !credencial ? (
        <View style={[estilos.vacia, { backgroundColor: p.superficie }]}>
          <Icono nombre="sinSenal" color={p.textoSuave} tamano={32} />
          <Texto centrado tono="suave">
            Conéctate una vez para descargar tu credencial. Después funcionará aunque no tengas señal.
          </Texto>
        </View>
      ) : (
        <>
          <View style={[estilos.tarjeta, { backgroundColor: '#FFFFFF' }]}>
            <View style={[estilos.banda, { backgroundColor: colorPlan }]} />
            <View style={estilos.persona}>
              {credencial.avatar_url ? (
                <Image source={{ uri: credencial.avatar_url }} style={estilos.foto} contentFit="cover" />
              ) : (
                <View style={[estilos.foto, { backgroundColor: marca.amarillo, alignItems: 'center', justifyContent: 'center' }]}>
                  <Texto variante="subtitulo" color={marca.tinta}>
                    {primerNombre(credencial.nombre).charAt(0).toUpperCase()}
                  </Texto>
                </View>
              )}
              <View style={{ flex: 1 }}>
                <Texto variante="subtitulo" color={marca.tinta} numberOfLines={2}>
                  {credencial.nombre}
                </Texto>
                {credencial.plan ? (
                  <Texto variante="pequeno" color="#5E5A52">
                    {credencial.plan}
                    {credencial.vigente_hasta ? ` · vigente hasta ${fechaCorta(credencial.vigente_hasta)}` : ''}
                  </Texto>
                ) : null}
              </View>
            </View>

            <View style={{ alignItems: 'center', paddingVertical: espacio.l }}>
              {credencial.codigo ? (
                <QRCode value={credencial.codigo} size={lado} color={marca.tinta} backgroundColor="#FFFFFF" ecl="M" quietZone={8} />
              ) : (
                <Texto color="#5E5A52" centrado>
                  Tu credencial se activa cuando tengas una membresía vigente.
                </Texto>
              )}
            </View>

            <Texto variante="pequeno" color="#8E897F" centrado>
              Muéstrala en recepción · Nódico
            </Texto>
          </View>

          <View style={{ gap: espacio.m, marginTop: espacio.xl }}>
            {vencida ? <Chip texto="Código vencido: conéctate para renovarlo" color={p.problema} /> : null}
            {!credencial.face_id_ok ? (
              <Texto variante="pequeno" tono="suave">
                Aún no registras tu rostro para el acceso facial. Mientras, recepción te identifica con este código.
              </Texto>
            ) : null}
            {sinRed ? (
              <View style={estilos.fila}>
                <Icono nombre="sinSenal" color={p.atencion} tamano={16} />
                <Texto variante="pequeno" tono="suave">
                  Sin conexión · guardada {haceCuanto(credencial.guardada_en)}. Sigue sirviendo.
                </Texto>
              </View>
            ) : null}
            <Pressable onPress={renovar} disabled={renovando} style={estilos.fila} accessibilityRole="button">
              <Icono nombre="recargar" color={p.textoSuave} tamano={16} />
              <Texto variante="pequeno" tono="suave" style={{ textDecorationLine: 'underline' }}>
                {renovando ? 'Generando…' : '¿Alguien vio tu código? Genera uno nuevo'}
              </Texto>
            </Pressable>
          </View>
        </>
      )}
    </Pantalla>
  );
}

const estilos = StyleSheet.create({
  tarjeta: { borderRadius: radio.xl, padding: espacio.xl, paddingTop: espacio.xl + 8, overflow: 'hidden', gap: espacio.s },
  banda: { position: 'absolute', top: 0, left: 0, right: 0, height: 8 },
  persona: { flexDirection: 'row', alignItems: 'center', gap: espacio.l },
  foto: { width: 56, height: 56, borderRadius: 28 },
  vacia: { padding: espacio.xxl, borderRadius: radio.l, alignItems: 'center', gap: espacio.l },
  fila: { flexDirection: 'row', gap: espacio.s, alignItems: 'center' },
});
