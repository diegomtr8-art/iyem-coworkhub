import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { View } from 'react-native';

import { Boton } from '@/componentes/Boton';
import { Hueso } from '@/componentes/Esqueleto';
import { Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { almacen } from '@/lib/almacen';
import { api, ErrorApi } from '@/lib/api';
import { useSesion } from '@/lib/sesion';
import type { RespuestaAcceso } from '@/lib/tipos';
import { espacio } from '@/tema';

/**
 * Destino del enlace mágico (`nodico://auth/enlace?token=…`, o la URL de Expo
 * Go en desarrollo). Canjea el token junto con el secreto que solo tiene este
 * teléfono.
 */
export default function CanjearEnlace() {
  const { token } = useLocalSearchParams<{ token?: string }>();
  const { entrar, datosDeDispositivo } = useSesion();
  const [error, setError] = useState<string | null>(null);
  const intentado = useRef(false);

  useEffect(() => {
    if (intentado.current) return;
    intentado.current = true;
    (async () => {
      const secreto = await almacen.secretoEnlace();
      if (!token || !secreto) {
        setError('Este enlace se pidió desde otro teléfono o ya se usó. Pide uno nuevo desde aquí.');
        return;
      }
      try {
        const respuesta = await api<RespuestaAcceso>('/auth/enlace-magico/canjear', {
          metodo: 'POST',
          anonima: true,
          cuerpo: { token, secreto, ...(await datosDeDispositivo()) },
        });
        await almacen.guardarSecretoEnlace(null);
        if ('requiere_dos_factores' in respuesta) {
          router.replace({ pathname: '/dos-factores', params: { desafio: respuesta.desafio } });
        } else {
          await entrar(respuesta);
        }
      } catch (e) {
        setError(e instanceof ErrorApi ? e.primero : 'No pudimos usar el enlace.');
      }
    })();
  }, [token, entrar, datosDeDispositivo]);

  return (
    <Pantalla estilo={{ gap: espacio.l, paddingTop: 120 }}>
      {error ? (
        <>
          <Texto variante="titulo">Ese enlace no sirvió</Texto>
          <Texto tono="suave">{error}</Texto>
          <Boton titulo="Volver al acceso" alPulsar={() => router.replace('/')} estilo={{ marginTop: espacio.xl }} />
        </>
      ) : (
        <View style={{ gap: espacio.m }}>
          <Texto variante="titulo">Entrando…</Texto>
          <Hueso ancho="80%" />
          <Hueso ancho="55%" />
        </View>
      )}
    </Pantalla>
  );
}
