import { useQuery } from '@tanstack/react-query';
import Constants, { ExecutionEnvironment } from 'expo-constants';
import { useEffect, useState } from 'react';
import * as WebBrowser from 'expo-web-browser';

import { api, URL_WEB } from './api';
import { claves } from './consultas';
import type { EstadoServidor } from './tipos';

/** `/estado`: versión mínima, mantenimiento y qué botones de acceso pintar. */
export function useEstadoServidor() {
  return useQuery({
    queryKey: claves.estado,
    queryFn: () => api<EstadoServidor>('/estado', { anonima: true }),
    staleTime: 5 * 60_000,
  });
}

/**
 * La hora actual como estado, refrescada cada `intervaloMs`. Leer el reloj
 * durante el render haría que la pantalla dependiera de cuándo se pinta.
 */
export function useAhora(intervaloMs = 30_000): number {
  const [ahora, setAhora] = useState(() => Date.now());
  useEffect(() => {
    const t = setInterval(() => setAhora(Date.now()), intervaloMs);
    return () => clearInterval(t);
  }, [intervaloMs]);
  return ahora;
}

/** Corriendo dentro de Expo Go (y no en una compilación propia). */
export const enExpoGo = Constants.executionEnvironment === ExecutionEnvironment.StoreClient;

/** Lo que vive en la web (registro, contraseña, seguridad) se abre en el navegador del sistema. */
export function abrirWeb(ruta: string) {
  return WebBrowser.openBrowserAsync(`${URL_WEB}${ruta.startsWith('/') ? ruta : `/${ruta}`}`, {
    presentationStyle: WebBrowser.WebBrowserPresentationStyle.PAGE_SHEET,
  });
}
